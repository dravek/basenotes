#!/usr/bin/env php
<?php

declare(strict_types=1);

// ── Autoloader ──────────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $base = __DIR__ . '/../src/';
    $rel  = str_replace('App\\', '', $class);
    $file = $base . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Repos\UserRepository;
use App\Util\Env;

function usage(): void
{
    $cmd = basename(__FILE__);
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php {$cmd} promote --email user@example.com\n");
}

$args = $argv;
array_shift($args);

$action = $args[0] ?? '';
if ($action !== 'promote') {
    usage();
    exit(1);
}

$email = null;
for ($i = 1; $i < count($args); $i++) {
    if ($args[$i] === '--email' && isset($args[$i + 1])) {
        $email = $args[$i + 1];
        break;
    }
}

if ($email === null || trim($email) === '') {
    usage();
    exit(1);
}

$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    fwrite(STDERR, "Error: .env file not found. Copy .env.example to .env and fill in values.\n");
    exit(1);
}

try {
    Env::load($envPath, [
        'APP_PEPPER',
        'APP_ENV',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
    ]);
} catch (\RuntimeException $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

try {
    $pdo = new PDO(dsn: sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        Env::get('DB_HOST'),
        Env::get('DB_PORT'),
        Env::get('DB_NAME'),
    ), username: Env::get('DB_USER'), password: Env::get('DB_PASS'), options: [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\PDOException $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$userRepo = new UserRepository($pdo);
$email = strtolower(trim($email));

$user = $userRepo->findByEmail($email);
if ($user === null) {
    fwrite(STDERR, "No user found with email {$email}.\n");
    exit(1);
}

if ($user->isAdmin) {
    echo "User {$email} is already an admin.\n";
    exit(0);
}

$updated = $userRepo->setAdminByEmail($email, true);
if (!$updated) {
    fwrite(STDERR, "Failed to promote {$email}.\n");
    exit(1);
}

echo "Promoted {$email} to admin.\n";
exit(0);
