<?php

namespace PhotoSign\Support;

use PhotoSign\Exceptions\PhotoSignUnavailableException;
use Psr\Http\Message\StreamInterface;
use SplFileInfo;

/**
 * Resolve upload inputs for PhotoSign multipart requests.
 *
 * Supports local paths, UploadedFile, Livewire TemporaryUploadedFile on S3/GCS
 * (via readStream()/get() when the SplFileInfo path is not a real local file),
 * raw resources, and browser data URLs from the capture widget.
 */
class UploadReader
{
    /**
     * @return array{0: string|resource|StreamInterface, 1: string} [contents, filename]
     */
    public static function forMultipart(mixed $file): array
    {
        if (is_string($file)) {
            if (str_starts_with($file, 'data:image/')) {
                $decoded = self::decodeDataUrl($file);

                return [$decoded['contents'], $decoded['filename']];
            }
            if (is_file($file)) {
                return [fopen($file, 'r') ?: throw new PhotoSignUnavailableException('Unable to read file.'), basename($file)];
            }
            throw new PhotoSignUnavailableException('Unable to read file.');
        }

        if (is_resource($file)) {
            return [$file, 'upload.bin'];
        }

        if (is_object($file)) {
            $filename = self::filenameFrom($file);

            // Livewire TemporaryUploadedFile on S3/GCS: pathname is not a real local file.
            if (method_exists($file, 'readStream') && ! self::hasReadableLocalPath($file)) {
                $stream = $file->readStream();
                if ($stream === false || $stream === null) {
                    throw new PhotoSignUnavailableException('Unable to read temporary upload from storage.');
                }

                return [$stream, $filename];
            }

            if (method_exists($file, 'get') && ! self::hasReadableLocalPath($file)) {
                $contents = $file->get();
                if (! is_string($contents) || $contents === '') {
                    throw new PhotoSignUnavailableException('Unable to read temporary upload from storage.');
                }

                return [$contents, $filename];
            }

            if ($file instanceof SplFileInfo) {
                $path = $file->getPathname();
                if (is_string($path) && is_file($path)) {
                    return [fopen($path, 'r') ?: throw new PhotoSignUnavailableException('Unable to read file.'), $filename ?: $file->getFilename()];
                }
            }

            if (method_exists($file, 'getRealPath')) {
                $path = $file->getRealPath();
                if (is_string($path) && $path !== '' && is_file($path)) {
                    return [fopen($path, 'r') ?: throw new PhotoSignUnavailableException('Unable to read upload.'), $filename];
                }
            }
        }

        throw new PhotoSignUnavailableException('Unsupported file input for PhotoSign.');
    }

    /**
     * Decode a capture-widget data URL for validation and/or storage.
     *
     * @return array{contents: string, extension: string, filename: string, mime: string}
     */
    public static function decodeDataUrl(string $dataUrl): array
    {
        if (! preg_match('#^data:(image/(jpeg|jpg|png));base64,#i', $dataUrl, $matches)) {
            throw new PhotoSignUnavailableException('Invalid image data URL.');
        }

        $mime = strtolower($matches[1]);
        $extension = str_contains($mime, 'png') ? 'png' : 'jpg';
        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($binary === false || $binary === '') {
            throw new PhotoSignUnavailableException('Could not decode image data URL.');
        }

        return [
            'contents' => $binary,
            'extension' => $extension,
            'filename' => 'capture.'.$extension,
            'mime' => $mime === 'image/jpg' ? 'image/jpeg' : $mime,
        ];
    }

    private static function hasReadableLocalPath(object $file): bool
    {
        if (method_exists($file, 'getRealPath')) {
            $path = $file->getRealPath();
            if (is_string($path) && $path !== '' && is_file($path)) {
                return true;
            }
        }
        if ($file instanceof SplFileInfo) {
            $path = $file->getPathname();

            return is_string($path) && $path !== '' && is_file($path);
        }

        return false;
    }

    private static function filenameFrom(object $file): string
    {
        if (method_exists($file, 'getClientOriginalName')) {
            $name = $file->getClientOriginalName();
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }
        if (method_exists($file, 'getFilename')) {
            $name = $file->getFilename();
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return 'upload.bin';
    }
}
