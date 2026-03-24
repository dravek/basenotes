<?php declare(strict_types=1);
/** @var list<string> $errors */
/** @var string $email */
use App\Util\Csrf;
$errors ??= [];
$email  ??= '';
ob_start();
?>
<div class="auth-box">
    <h1>Register</h1>
    <?php if ($errors): ?>
    <ul class="errors" role="alert" aria-live="assertive" tabindex="-1">
        <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <form method="POST" action="/register">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <label>
            Email
            <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email">
        </label>
        <label>
            Password <small>(10+ characters)</small>
            <input type="password" name="password" required minlength="10" autocomplete="new-password">
        </label>
        <button type="submit">Create Account</button>
    </form>
    <p class="auth-link">Have an account? <a href="/login">Login</a></p>
</div>
<?php
$bodyContent = ob_get_clean();
$title = 'Register';
require __DIR__ . '/../views/layout.php';
