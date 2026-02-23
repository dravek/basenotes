<?php declare(strict_types=1);
/** @var list<string> $errors */
use App\Util\Csrf;
$errors ??= [];
ob_start();
?>
<div class="auth-box">
    <h1>Account Recovery</h1>
    <?php if ($errors): ?>
    <ul class="errors">
        <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <form method="POST" action="/recovery">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <label>
            Recovery Code
            <input type="text" name="recovery_code" required autofocus>
        </label>
        <label>
            New Password <small>(10+ characters)</small>
            <input type="password" name="new_password" required minlength="10">
        </label>
        <label>
            Confirm New Password
            <input type="password" name="confirm_password" required minlength="10">
        </label>
        <button type="submit">Reset Password</button>
    </form>
    <p class="auth-link">Remembered your password? <a href="/login">Login</a></p>
</div>
<?php
$bodyContent = ob_get_clean();
$title = 'Account Recovery';
require __DIR__ . '/../views/layout.php';
