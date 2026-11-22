<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

use TradingCard101\Auth;

Auth::requireAdmin();

$campid     = (string) setting('ebay_campaign_id', '');
$configured = $campid !== '';

// POST (not GET) so the pasted eBay URL never lands in the query string —
// some shared-host firewalls (mod_security) return 403 for URLs-in-querystring.
$input  = '';
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $input = trim((string) ($_POST['url'] ?? ''));
    if ($input !== '') {
        $result = preg_match('#^https?://#i', $input)
            ? epn_link($input)
            : epn_search_link($input);
    }
}

$presets = ['Charizard PSA 10', 'Umbreon VMAX PSA 10', 'Black Lotus BGS', 'Blue-Eyes White Dragon PSA 10', 'Lugia 1st Edition'];

layout_header('Affiliate Links', 'admin');
?>
<h1>🔗 eBay affiliate link generator</h1>
<p class="sub">Turn any eBay item or search into a commission-tracked link (eBay Partner Network).
    Paste it in emails, your Skool community, or social posts.</p>

<?php if (!$configured): ?>
<div class="mock-note">
    ⚠️ No <strong>ePN Campaign ID</strong> saved yet — links won't be tracked.
    Add it in <a href="/superadmin/settings.php">Settings → eBay Partner Network</a>.
</div>
<?php endif; ?>

<form method="post" class="card" style="max-width:760px;margin-bottom:22px">
    <?= csrf_field() ?>
    <label>eBay URL or keywords</label>
    <input name="url" value="<?= e($input) ?>" placeholder="https://www.ebay.com/itm/123…  — or —  Charizard Base Set 1st Edition PSA 10" autofocus>
    <p class="field-help">Paste an eBay item/search URL to wrap it, or type keywords to build a tracked search link.</p>
    <div style="margin-top:12px"><button class="btn btn-primary" type="submit">Generate tracked link</button></div>
</form>

<?php if ($result !== null): ?>
<div class="card" style="max-width:760px;margin-bottom:22px">
    <label>Your tracked link</label>
    <input class="copy-field" value="<?= e($result) ?>" readonly onclick="this.select()">
    <p style="margin-top:10px"><a class="btn btn-sm" href="<?= e($result) ?>" target="_blank" rel="noopener">Open link →</a></p>
    <?php if ($configured): ?>
        <p class="field-help">Tracking: campid <?= e($campid) ?><?php if (setting('ebay_custom_id', '')): ?> · customid <?= e(setting('ebay_custom_id', '')) ?><?php endif; ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<h2>Quick search links</h2>
<table style="max-width:760px">
    <tbody>
    <?php foreach ($presets as $p): $link = epn_search_link($p); ?>
        <tr>
            <td><strong><?= e($p) ?></strong></td>
            <td><input class="copy-field" value="<?= e($link) ?>" readonly onclick="this.select()"></td>
            <td><a class="btn btn-sm" href="<?= e($link) ?>" target="_blank" rel="noopener">Open</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
layout_footer();
