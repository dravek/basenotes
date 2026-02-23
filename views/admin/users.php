<?php declare(strict_types=1);
/** @var list<\App\Repos\UserDto> $users */
use App\Auth\Session;
use App\Util\Csrf;
$currentUserId = Session::userId();
ob_start();
?>
<div class="settings-box">
    <h1>Users</h1>
    <p>Manage access for registered users.</p>
    <?php if (count($users) === 0): ?>
    <p>No users found.</p>
    <?php else: ?>
    <table class="tokens-table">
        <thead>
            <tr>
                <th>Email</th>
                <th>Admin</th>
                <th>Status</th>
                <th>Created</th>
                <th>Updated</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?= e($user->email) ?></td>
            <td><?= $user->isAdmin ? 'Yes' : 'No' ?></td>
            <td><?= $user->disabledAt !== null ? 'Disabled' : 'Enabled' ?></td>
            <td><?= e(date('d M Y', $user->createdAt)) ?></td>
            <td><?= e(date('d M Y', $user->updatedAt)) ?></td>
            <td>
                <?php if ($user->disabledAt !== null): ?>
                <form method="POST" action="/app/admin/users/<?= e($user->id) ?>/enable">
                    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Enable</button>
                </form>
                <?php else: ?>
                    <?php if ($currentUserId !== null && $currentUserId === $user->id): ?>
                    <button type="button" class="btn btn-secondary btn-sm" disabled>You</button>
                    <?php else: ?>
                    <form method="POST" action="/app/admin/users/<?= e($user->id) ?>/disable">
                        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Disable this user?')">Disable</button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
$bodyContent = ob_get_clean();
$title = 'User Admin';
require __DIR__ . '/../layout.php';
