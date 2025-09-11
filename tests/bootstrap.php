<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Set up SQLite test database for tests (simpler, more reliable)
$testDbPath = dirname(__DIR__) . '/var/test.db';

// Clean up previous test database
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

// Set test database URL to use SQLite
$_ENV['DATABASE_URL'] = 'sqlite:///' . $testDbPath;
$_SERVER['DATABASE_URL'] = 'sqlite:///' . $testDbPath;

// Ensure test environment
$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';
