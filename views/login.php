<?php declare(strict_types=1);
/** @var list<string> $errors */
/** @var string $email */
use App\Util\Csrf;
$errors ??= [];
$email  ??= '';
ob_start();
?>
<div class="auth-box">
    <h1>Login</h1>
    <?php if ($errors): ?>
    <ul class="errors">
        <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <form method="POST" action="/login">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <label>
            Email
            <input type="email" name="email" value="<?= e($email) ?>" required autofocus>
        </label>
        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Login</button>
    </form>
    <p class="auth-link">No account? <a href="/register">Register</a></p>
</div>
<?php
$bodyContent = ob_get_clean();
$title = 'Login';
require __DIR__ . '/../views/layout.php';
