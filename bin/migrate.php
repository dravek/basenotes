#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Util/Env.php';

use App\Util\Env;

$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    fwrite(STDERR, "Error: .env file not found. Copy .env.example to .env and fill in values.\n");
    exit(1);
}

try {
    Env::load($envPath, ['APP_PEPPER', 'APP_ENV', 'DB_PATH']);
} catch (\RuntimeException $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

$dbPath = Env::get('DB_PATH');

try {
    $pdo = new PDO('sqlite:' . $dbPath, options: [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\PDOException $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$migrationsDir = __DIR__ . '/../migrations';
$files = glob($migrationsDir . '/*.sql');
if ($files === false || count($files) === 0) {
    echo "No migration files found in {$migrationsDir}.\n";
    exit(0);
}
sort($files);

$exitCode = 0;
foreach ($files as $file) {
    $name = basename($file);
    $sql  = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read migration file: {$name}\n");
        $exitCode = 1;
        continue;
    }
    try {
        $pdo->exec($sql);
        echo "Migration {$name} applied successfully.\n";
    } catch (\PDOException $e) {
        fwrite(STDERR, "Migration {$name} FAILED: " . $e->getMessage() . "\n");
        $exitCode = 1;
    }
}

if ($exitCode === 0) {
    echo "Database setup complete.\n";
}

exit($exitCode);
