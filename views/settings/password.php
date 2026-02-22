<?php declare(strict_types=1);
/** @var list<string> $errors */
/** @var string|null $success */
use App\Util\Csrf;
$errors  ??= [];
$success ??= null;
ob_start();
?>
<div class="settings-box">
    <h1>Change Password</h1>
    <?php if ($success !== null): ?>
    <p class="success"><?= e($success) ?></p>
    <?php endif; ?>
    <?php if ($errors): ?>
    <ul class="errors">
        <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <form method="POST" action="/app/settings/password">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <label>
            Current Password
            <input type="password" name="current_password" required autofocus>
        </label>
        <label>
            New Password <small>(10+ characters)</small>
            <input type="password" name="new_password" required minlength="10">
        </label>
        <label>
            Confirm New Password
            <input type="password" name="confirm_password" required minlength="10">
        </label>
        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
</div>
<?php
$bodyContent = ob_get_clean();
$title = 'Change Password';
require __DIR__ . '/../../views/layout.php';
