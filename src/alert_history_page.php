<?php
declare(strict_types=1);

/**
 * Deal Alerts history — every alert email that went out, grouped by day
 * (newest day first) and newest email first within each day, each laid out
 * like the email itself.
 *
 * Included by member/alerts.php and superadmin/alerthistory.php, which set:
 *   $AH_AREA  'member' | 'admin'  (layout + sidebar)
 *   $AH_FULL  bool                (false = free preview: latest email, top 3)
 */

use TradingCard101\DealAlerts;

$AH_AREA = $AH_AREA ?? 'member';
$AH_FULL = $AH_FULL ?? false;

const AH_PREVIEW_ITEMS = 3;

$emails = DealAlerts::history($pdo, $AH_FULL ? 200 : 1);
$tz     = new DateTimeZone(date_default_timezone_get());
$utc    = new DateTimeZone('UTC');
$today  = (new DateTime('now', $tz))->format('Y-m-d');
$yday   = (new DateTime('yesterday', $tz))->format('Y-m-d');

// Group by local calendar day; history() is already newest-first, so both the
// days and the emails inside each day come out last-in-first.
$days = [];
foreach ($emails as $em) {
    $at = new DateTime($em['sent_at'], $utc);
    $at->setTimezone($tz);
    $em['local'] = $at;
    $days[$at->format('Y-m-d')][] = $em;
}

$dayLabel = static function (string $ymd) use ($today, $yday, $tz): string {
    if ($ymd === $today) {
        return 'Today';
    }
    if ($ymd === $yday) {
        return 'Yesterday';
    }
    $d = new DateTime($ymd, $tz);
    return $d->format($d->format('Y') === date('Y') ? 'l, M j' : 'l, M j, Y');
};

/** "ends in ~4h" while live, "ended" once closed. */
$endsText = static function (string $endUtc): string {
    $h = hours_until($endUtc);
    if ($h === null) {
        return '';
    }
    if ($h <= 0) {
        return 'ended';
    }
    $hrs = (int) max(1, round($h));
    return 'ends in ' . ($hrs >= 48 ? '~' . (int) round($hrs / 24) . ' days' : "~{$hrs}h");
};

layout_header('Deal Alerts', $AH_AREA);
?>
<h1>🔔 Deal Alerts</h1>
<p class="sub">
    Every deal alert email, newest first and grouped by day.
    Bids and prices are as they stood when each alert was sent.
</p>

<?php if (!$AH_FULL): ?>
<div class="mock-note">
    🔒 You're seeing a <strong>free preview</strong>: the latest alert, top <?= AH_PREVIEW_ITEMS ?> auctions.
    <a href="/member/account.php">Upgrade to Pro</a> for every alert and the full history.
</div>
<?php endif; ?>

<?php if (!$days): ?>
    <div class="empty">
        No deal alerts yet. Alerts appear here each time an alert email goes out.
        <?php if ($AH_AREA === 'admin'): ?>
            <br>Set up triggers and the alert email address in <a href="/superadmin/alerts.php">Alerts</a>.
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php $first = true; foreach ($days as $ymd => $dayEmails):
        $dayCount = array_sum(array_column($dayEmails, 'match_count')); ?>
        <section class="ah-day">
            <h2 class="ah-day-head">
                <?= e($dayLabel($ymd)) ?>
                <span><?= count($dayEmails) ?> email<?= count($dayEmails) === 1 ? '' : 's' ?> · <?= $dayCount ?> auction<?= $dayCount === 1 ? '' : 's' ?></span>
            </h2>

            <?php foreach ($dayEmails as $em):
                $items = $AH_FULL ? $em['matches'] : array_slice($em['matches'], 0, AH_PREVIEW_ITEMS);
                $n = $em['match_count']; ?>
                <details class="ah-email"<?= $first ? ' open' : '' ?>>
                    <summary>
                        <span class="ah-time"><?= e($em['local']->format('g:ia')) ?></span>
                        <span class="ah-count"><?= $n ?> auction<?= $n === 1 ? '' : 's' ?> matched your alert triggers<?= $n > 1 ? ', best value first' : '' ?>.</span>
                    </summary>

                    <ol class="ah-list">
                        <?php foreach ($items as $m):
                            $bids  = (int) ($m['bids'] ?? 0);
                            $under = $m['under'] !== null ? (int) round((float) $m['under']) : null;
                            $ends  = $endsText((string) ($m['end_time'] ?? ''));
                            $url   = epn_link((string) ($m['url'] ?? '')); ?>
                            <li class="ah-item<?= $ends === 'ended' ? ' is-ended' : '' ?>">
                                <div class="ah-card"><?= e((string) $m['card']) ?></div>
                                <div class="ah-line">
                                    Bid <b><?= e(money((float) $m['price'], (string) ($m['currency'] ?? 'USD'))) ?></b>
                                    · <?= $bids ?> bid<?= $bids === 1 ? '' : 's' ?>
                                    <?php if ($ends !== ''): ?>· <span class="ah-ends"><?= e($ends) ?></span><?php endif; ?>
                                </div>
                                <?php if ($m['median'] !== null): ?>
                                    <div class="ah-comp<?= $under !== null && $under > 0 ? ' is-under' : '' ?>">
                                        <?php if ($under !== null && $under > 0): ?>
                                            <b><?= $under ?>% under comp</b> · median <?= e(money((float) $m['median'])) ?> · <?= (int) $m['sales'] ?> sale<?= (int) $m['sales'] === 1 ? '' : 's' ?>
                                        <?php else: ?>
                                            Comp median <?= e(money((float) $m['median'])) ?> · <?= (int) $m['sales'] ?> sale<?= (int) $m['sales'] === 1 ? '' : 's' ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($m['triggers'])): ?>
                                    <div class="ah-trig">Matched: <?= e(implode(', ', (array) $m['triggers'])) ?></div>
                                <?php endif; ?>
                                <?php if ($url !== ''): ?>
                                    <a class="btn btn-primary btn-sm" href="<?= e($url) ?>" target="_blank" rel="noopener">View on eBay →</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>

                    <?php if (!$AH_FULL && $n > count($items)): ?>
                        <p class="ah-more">+ <?= $n - count($items) ?> more in this alert. <a href="/member/account.php">Upgrade to Pro</a> to see them.</p>
                    <?php endif; ?>
                </details>
            <?php $first = false; endforeach; ?>
        </section>
    <?php endforeach; ?>

    <?php if (!$AH_FULL): ?>
        <div class="upsell"><a class="btn btn-primary btn-lg" href="/member/account.php">Unlock every alert with Pro →</a></div>
    <?php else: ?>
        <p class="ah-keep">Alerts are kept for <?= DealAlerts::HISTORY_DAYS ?> days.</p>
    <?php endif; ?>
<?php endif; ?>
<?php
layout_footer();
