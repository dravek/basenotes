<?php declare(strict_types=1);
/** @var list<\App\Repos\TokenDto> $tokens */
/** @var string|null $newRawToken */
/** @var list<string> $errors */
use App\Util\Csrf;
$errors      ??= [];
$newRawToken ??= null;
ob_start();
?>
<div class="settings-box">
    <h1>API Tokens</h1>

    <?php if ($newRawToken !== null): ?>
    <div class="token-reveal">
        <p><strong>Token created.</strong> Copy it now — it will not be shown again.</p>
        <code id="raw-token"><?= e($newRawToken) ?></code>
        <button type="button" onclick="copyToken()">Copy</button>
    </div>
    <?php endif; ?>

    <h2>Generate New Token</h2>
    <?php if ($errors): ?>
    <ul class="errors">
        <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <form method="POST" action="/app/settings/tokens" class="token-form">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <label>
            Token Name
            <input type="text" name="name" placeholder="e.g. My Script" required>
        </label>
        <label>
            Scope
            <select name="scopes">
                <option value="notes:read">notes:read — read only</option>
                <option value="notes:read,notes:write">notes:read,notes:write — read &amp; write</option>
            </select>
        </label>
        <button type="submit" class="btn btn-primary">Generate Token</button>
    </form>

    <h2>Active Tokens</h2>
    <?php if (count($tokens) === 0): ?>
    <p>No tokens yet.</p>
    <?php else: ?>
    <table class="tokens-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Scopes</th>
                <th>Created</th>
                <th>Last Used</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tokens as $token): ?>
        <tr class="<?= $token->revokedAt !== null ? 'token-revoked' : '' ?>">
            <td><?= e($token->name) ?></td>
            <td><code><?= e($token->scopes) ?></code></td>
            <td><?= e(date('d M Y', $token->createdAt)) ?></td>
            <td><?= $token->lastUsedAt !== null ? e(date('d M Y', $token->lastUsedAt)) : '—' ?></td>
            <td><?= $token->revokedAt !== null ? 'Revoked' : 'Active' ?></td>
            <td>
                <?php if ($token->revokedAt === null): ?>
                <form method="POST" action="/app/settings/tokens/<?= e($token->id) ?>/revoke">
                    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Revoke this token?')">Revoke</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<script>
function copyToken() {
    const el = document.getElementById('raw-token');
    navigator.clipboard.writeText(el.textContent).then(function() {
        alert('Token copied to clipboard.');
    });
}
</script>
<?php
$bodyContent = ob_get_clean();
$title = 'API Tokens';
require __DIR__ . '/../../views/layout.php';
