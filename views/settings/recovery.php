<?php declare(strict_types=1);
/** @var list<string>|null $codes */
use App\Util\Csrf;
$codes ??= null;
ob_start();
?>
<div class="settings-box">
    <h1>Recovery Codes</h1>

    <?php if (is_array($codes) && $codes !== []): ?>
    <div class="token-reveal">
        <p><strong>Save these codes now.</strong> They will not be shown again.</p>
        <ul class="recovery-codes" id="recovery-codes">
            <?php foreach ($codes as $code): ?>
            <li><code><?= e($code) ?></code></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn btn-secondary btn-sm" onclick="copyRecoveryCodes()">Copy all</button>
    </div>
    <?php else: ?>
    <p>Generate a new set of recovery codes. This will invalidate any unused codes.</p>
    <?php endif; ?>

    <form method="POST" action="/app/settings/recovery">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <button type="submit" class="btn btn-primary">Generate 10 New Codes</button>
    </form>
</div>
<script>
function copyRecoveryCodes() {
    var list = document.querySelectorAll('#recovery-codes code');
    var lines = [];
    list.forEach(function (item) { lines.push(item.textContent); });
    navigator.clipboard.writeText(lines.join("\n")).then(function () {
        alert('Recovery codes copied to clipboard.');
    });
}
</script>
<?php
$bodyContent = ob_get_clean();
$title = 'Recovery Codes';
require __DIR__ . '/../../views/layout.php';
