<?php declare(strict_types=1);
/** @var string $title */
/** @var string $bodyContent */
use App\Auth\Session;
use App\Util\Csrf;
$userId = Session::userId();
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
    <header class="site-header">
        <a href="<?= $userId ? '/app/notes' : '/login' ?>" class="site-title">📝 Basenotes</a>
        <?php if ($userId): ?>
        <nav class="site-nav">
            <a href="/app/notes">Notes</a>
            <a href="/app/settings/tokens">Tokens</a>
            <a href="/app/settings/password">Password</a>
            <a href="/app/settings/recovery">Recovery</a>
            <form method="POST" action="/logout" class="logout-form">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <button type="submit">Logout</button>
            </form>
        </nav>
        <?php endif; ?>
    </header>
    <main class="site-main">
        <?= $bodyContent ?>
    </main>
    <script src="/assets/app.js"></script>
</body>
</html>
