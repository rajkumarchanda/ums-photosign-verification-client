<?php

return [
    'url' => env('PHOTOSIGN_URL', 'http://localhost:8080'),
    'key' => env('PHOTOSIGN_KEY'),
    'photo_profile' => env('PHOTOSIGN_PHOTO_PROFILE', 'passport_student'),
    'sign_profile' => env('PHOTOSIGN_SIGN_PROFILE', 'signature_student'),
    'timeout' => (float) env('PHOTOSIGN_TIMEOUT', 8),
    'retries' => (int) env('PHOTOSIGN_RETRIES', 1),
    /*
     | closed: throw on validation failure or outage
     | shadow: call PhotoSign, log would-be failures, never block the user
     | open: never block; still call PhotoSign when possible
     */
    'fail_mode' => env('PHOTOSIGN_FAIL_MODE', 'closed'),
    'shadow' => (bool) env('PHOTOSIGN_SHADOW', false),
    'webhook_secret' => env('PHOTOSIGN_WEBHOOK_SECRET'),
];
