<?php declare(strict_types=1);
/** @var string $title */
/** @var string $bodyContent */
use App\Auth\Session;
use App\Util\Csrf;
$userId = Session::userId();
$isGuest = $userId === null;
$isAdmin = (bool)Session::get('is_admin', false);
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isNotesActive = str_starts_with($currentPath, '/app/notes');
$isTokensActive = $currentPath === '/app/settings/tokens';
$isPasswordActive = $currentPath === '/app/settings/password';
$isRecoveryActive = $currentPath === '/app/settings/recovery';
$isAdminActive = str_starts_with($currentPath, '/app/admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Notes') ?> — Basenotes</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <header class="site-header<?= $userId ? '' : ' guest-header' ?>">
        <a href="<?= $userId ? '/app/notes' : '/login' ?>" class="site-title">
            <span class="brand-mark" aria-hidden="true">
                <span class="brand-mark-dot"></span>
                <span class="brand-mark-line"></span>
            </span>
            <span class="brand-text">Base<span>notes</span></span>
        </a>
        <?php if ($userId): ?>
        <nav class="site-nav" aria-label="Primary">
            <a href="/app/notes" class="<?= $isNotesActive ? 'is-active' : '' ?>" <?= $isNotesActive ? 'aria-current="page"' : '' ?>>Notes</a>
            <a href="/app/settings/tokens" class="<?= $isTokensActive ? 'is-active' : '' ?>" <?= $isTokensActive ? 'aria-current="page"' : '' ?>>Tokens</a>
            <a href="/app/settings/password" class="<?= $isPasswordActive ? 'is-active' : '' ?>" <?= $isPasswordActive ? 'aria-current="page"' : '' ?>>Password</a>
            <a href="/app/settings/recovery" class="<?= $isRecoveryActive ? 'is-active' : '' ?>" <?= $isRecoveryActive ? 'aria-current="page"' : '' ?>>Recovery</a>
            <?php if ($isAdmin): ?>
            <a href="/app/admin/users" class="<?= $isAdminActive ? 'is-active' : '' ?>" <?= $isAdminActive ? 'aria-current="page"' : '' ?>>Admin</a>
            <?php endif; ?>
            <form method="POST" action="/logout" class="logout-form">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <button type="submit">Logout</button>
            </form>
        </nav>
        <?php endif; ?>
    </header>
    <main class="site-main" id="main-content" tabindex="-1">
        <?= $bodyContent ?>
    </main>
    <script src="/assets/app.js"></script>
</body>
</html>
