<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

require dirname(__DIR__) . '/src/Util/Clock.php';
require dirname(__DIR__) . '/src/Util/Id.php';
require dirname(__DIR__) . '/src/Util/Validate.php';
require dirname(__DIR__) . '/src/Util/Slug.php';
require dirname(__DIR__) . '/src/Auth/Password.php';
require dirname(__DIR__) . '/src/Auth/Token.php';
require dirname(__DIR__) . '/src/Auth/RecoveryCode.php';
require dirname(__DIR__) . '/src/Repos/NoteRepository.php';
require dirname(__DIR__) . '/src/Repos/TokenRepository.php';
require dirname(__DIR__) . '/src/Repos/RecoveryCodeRepository.php';
require dirname(__DIR__) . '/src/Repos/TagRepository.php';
require dirname(__DIR__) . '/src/Repos/NoteTagRepository.php';
