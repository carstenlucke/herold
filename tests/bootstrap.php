<?php

// Override Docker-level env vars for the test environment.
// Docker's env_file directive sets $_ENV and $_SERVER at the OS level,
// which Laravel's Env::get() reads before putenv() values.
// We must override all three sources before autoloading.
foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
] as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}

require __DIR__.'/../vendor/autoload.php';
