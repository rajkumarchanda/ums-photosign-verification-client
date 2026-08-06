<?php

namespace PhotoSign\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PhotoSign\PhotoSignServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PhotoSignServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('photosign.url', 'http://photosign.test');
        $app['config']->set('photosign.key', 'ps_test_deadbeef_secret');
        $app['config']->set('photosign.photo_profile', 'passport_student');
        $app['config']->set('photosign.sign_profile', 'signature_student');
        $app['config']->set('photosign.fail_mode', 'closed');
        $app['config']->set('photosign.shadow', false);
        $app['config']->set('photosign.timeout', 2);
        $app['config']->set('photosign.retries', 0);
    }
}
