<?php

namespace PhotoSign\Tests\Unit;

use PhotoSign\Exceptions\PhotoSignUnavailableException;
use PhotoSign\Support\UploadReader;
use PhotoSign\Tests\TestCase;
use SplFileInfo;

class UploadReaderTest extends TestCase
{
    public function test_reads_local_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ps_');
        file_put_contents($path, 'hello-photo');

        try {
            [$contents, $filename] = UploadReader::forMultipart($path);
            $this->assertIsResource($contents);
            $this->assertSame('hello-photo', stream_get_contents($contents));
            $this->assertSame(basename($path), $filename);
            fclose($contents);
        } finally {
            @unlink($path);
        }
    }

    public function test_reads_spl_file_info_when_local(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ps_');
        file_put_contents($path, 'spl-bytes');

        try {
            [$contents, $filename] = UploadReader::forMultipart(new SplFileInfo($path));
            $this->assertIsResource($contents);
            $this->assertSame('spl-bytes', stream_get_contents($contents));
            $this->assertSame(basename($path), $filename);
            fclose($contents);
        } finally {
            @unlink($path);
        }
    }

    public function test_prefers_read_stream_when_pathname_is_not_local(): void
    {
        $fake = new class
        {
            public function getRealPath(): string
            {
                return 'livewire-tmp/missing-on-disk.jpg';
            }

            public function getPathname(): string
            {
                return 'livewire-tmp/missing-on-disk.jpg';
            }

            public function getClientOriginalName(): string
            {
                return 'passport.jpg';
            }

            public function readStream()
            {
                $stream = fopen('php://temp', 'r+');
                fwrite($stream, 'from-s3');
                rewind($stream);

                return $stream;
            }
        };

        [$contents, $filename] = UploadReader::forMultipart($fake);
        $this->assertIsResource($contents);
        $this->assertSame('from-s3', stream_get_contents($contents));
        $this->assertSame('passport.jpg', $filename);
        fclose($contents);
    }

    public function test_falls_back_to_get_when_stream_unavailable_and_path_missing(): void
    {
        $fake = new class
        {
            public function getRealPath(): string
            {
                return 'livewire-tmp/missing.jpg';
            }

            public function getClientOriginalName(): string
            {
                return 'sign.png';
            }

            public function get(): string
            {
                return 'blob-bytes';
            }
        };

        [$contents, $filename] = UploadReader::forMultipart($fake);
        $this->assertSame('blob-bytes', $contents);
        $this->assertSame('sign.png', $filename);
    }

    public function test_decodes_data_url(): void
    {
        $binary = 'fake-jpeg-bytes';
        $dataUrl = 'data:image/jpeg;base64,'.base64_encode($binary);
        $decoded = UploadReader::decodeDataUrl($dataUrl);

        $this->assertSame($binary, $decoded['contents']);
        $this->assertSame('jpg', $decoded['extension']);
        $this->assertSame('capture.jpg', $decoded['filename']);

        [$contents, $filename] = UploadReader::forMultipart($dataUrl);
        $this->assertSame($binary, $contents);
        $this->assertSame('capture.jpg', $filename);
    }

    public function test_rejects_invalid_data_url(): void
    {
        $this->expectException(PhotoSignUnavailableException::class);
        UploadReader::decodeDataUrl('data:text/plain;base64,YQ==');
    }
}
