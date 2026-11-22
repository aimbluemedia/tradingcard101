<?php
declare(strict_types=1);

/**
 * Small view/helper functions shared across the public site, member area,
 * and superadmin area.
 */

/** HTML-escape a value for safe output. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Generate (once per session) and return the CSRF token. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Invalid or expired form token. Go back and try again.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Format dollars from a float. */
function money(?float $amount, string $currency = 'USD'): string
{
    if ($amount === null) {
        return '—';
    }
    $symbol = ['USD' => '$', 'GBP' => '£', 'EUR' => '€', 'CAD' => 'C$', 'AUD' => 'A$'][$currency] ?? '';
    return $symbol . number_format($amount, 2);
}

/** Format dollars from an integer number of cents. */
function money_cents(int $cents): string
{
    return '$' . number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
}

/** URL-safe slug. */
function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-') ?: 'item';
}

/** Read a site setting (key/value table), with a per-request cache. */
function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    global $pdo;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($pdo->query('SELECT skey, sval FROM settings')->fetchAll() as $r) {
                $cache[$r['skey']] = $r['sval'];
            }
        } catch (\Throwable $e) {
            // settings table may not exist yet during install
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * Write a site setting (upsert into the key/value table).
 *
 * Reconnects once if the server has dropped the link. This matters most in
 * error handlers: they run precisely when a long API call has just killed the
 * connection, and a throw here would replace the real error with a useless
 * one. Reassigning the global hands the fresh connection to later callers.
 */
function set_setting(string $key, string $val): void
{
    global $pdo;
    $sql = 'INSERT INTO settings (skey, sval) VALUES (?, ?) ON DUPLICATE KEY UPDATE sval = VALUES(sval)';
    try {
        $pdo->prepare($sql)->execute([$key, $val]);
    } catch (\Throwable $e) {
        if (!\TradingCard101\Database::isGoneAway($e)) {
            throw $e;
        }
        $pdo = \TradingCard101\Database::reconnect();
        $pdo->prepare($sql)->execute([$key, $val]);
    }
}

/**
 * eBay sold/completed-listings search for a card — the public version of
 * eBay's Price Guide data. Affiliate-wrapped like every other eBay link.
 */
function ebay_sold_link(string $card): string
{
    $url = 'https://www.ebay.com/sch/i.html?LH_Sold=1&LH_Complete=1&_sop=13&_nkw=' . rawurlencode(trim($card));
    return function_exists('epn_link') ? epn_link($url) : $url;
}

/**
 * Build the eBay client config, preferring values saved in the admin Settings
 * (DB) and falling back to config.php. Lets the superadmin manage keys in the UI.
 */
function ebay_config(array $fileCfg): array
{
    $endpoint = setting('ebay_endpoint', 'https://api.ebay.com') ?: 'https://api.ebay.com';
    return [
        'client_id'     => setting('ebay_app_id', (string)($fileCfg['client_id'] ?? '')),
        'client_secret' => setting('ebay_cert_id', (string)($fileCfg['client_secret'] ?? '')),
        'account_sid'   => setting('ebay_account_sid', ''),
        'auth_token'    => setting('ebay_auth_token', ''),
        'marketplace'   => setting('ebay_marketplace', (string)($fileCfg['marketplace'] ?? 'EBAY_US')),
        'campaign_id'   => setting('ebay_campaign_id', (string)($fileCfg['campaign_id'] ?? '')),
        'custom_id'     => setting('ebay_custom_id', ''),
        'endpoint'      => $endpoint,
        'environment'   => str_contains($endpoint, 'sandbox') ? 'sandbox' : 'production',
        'cache_hours'   => (int)(setting('ebay_cache_hours', '12') ?? 12),
    ];
}

/**
 * Build the Claude (Anthropic) config, preferring values saved in the admin
 * Settings page and falling back to config.php / environment variables.
 * With no API key anywhere, AiAnalyst runs in deterministic MOCK mode.
 */
function ai_config(array $fileCfg): array
{
    return [
        'api_key'      => setting('ai_api_key', (string)($fileCfg['api_key'] ?? '')),
        'model'        => setting('ai_model', (string)($fileCfg['model'] ?? 'claude-opus-4-8')) ?: 'claude-opus-4-8',
        'max_per_scan' => (int) (setting('ai_max_per_scan', (string)($fileCfg['max_per_scan'] ?? 15)) ?: 15),
    ];
}

/**
 * Wrap an eBay URL with eBay Partner Network affiliate tracking, using the
 * Campaign ID / Custom ID saved in admin Settings. Works for any eBay item or
 * search URL — no API call required (this is EPN's standard link format).
 * Returns the URL unchanged if no Campaign ID is set or it's already tracked.
 */
function epn_link(string $url): string
{
    $url = trim($url);
    if ($url === '' || !str_contains($url, 'ebay.')) {
        return $url;
    }
    if (str_contains($url, 'campid=')) {
        return $url; // already an affiliate link
    }
    $campid = (string) setting('ebay_campaign_id', '');
    if ($campid === '') {
        return $url; // tracking not configured yet
    }

    // siteid + rotation id (mkrid) pair, by marketplace. Override via setting.
    $mp = (string) setting('ebay_marketplace', 'EBAY_US');
    [$siteid, $mkrid] = match ($mp) {
        'EBAY_GB' => ['3', '710-53481-19255-0'],
        'EBAY_CA' => ['2', '706-53473-19255-0'],
        'EBAY_AU' => ['15', '705-53470-19255-0'],
        default   => ['0', '711-53200-19255-0'], // EBAY_US
    };
    $rot = (string) setting('ebay_rotation_id', '');
    if ($rot !== '') {
        $mkrid = $rot;
    }

    $params = [
        'mkcid'  => '1',          // eBay Partner Network
        'mkrid'  => $mkrid,
        'siteid' => $siteid,
        'campid' => $campid,
        'toolid' => '10001',
        'mkevt'  => '1',
    ];
    $custom = (string) setting('ebay_custom_id', '');
    if ($custom !== '') {
        $params['customid'] = $custom;
    }

    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
}

/** Build a tracked eBay search URL for a keyword phrase. */
function epn_search_link(string $keywords): string
{
    $base = 'https://www.ebay.com/sch/i.html?' . http_build_query(['_nkw' => $keywords]);
    return epn_link($base);
}

/** Card games we organise auction channels by: key => [label, emoji]. */
function card_games(): array
{
    return [
        'pokemon'   => ['label' => 'Pokemon',     'emoji' => '\u{26a1}'],
        'magic'     => ['label' => 'Magic: TG',   'emoji' => '\u{1f9d9}'],
        'yugioh'    => ['label' => 'Yu-Gi-Oh!',   'emoji' => '\u{1f409}'],
        'onepiece'  => ['label' => 'One Piece',   'emoji' => '\u{1f3f4}'],
        'lorcana'   => ['label' => 'Lorcana',     'emoji' => '\u{1f3f0}'],
        'digimon'   => ['label' => 'Digimon',     'emoji' => '\u{1f916}'],
    ];
}

/**
 * Publishers / set families for trigger filtering: key => [label, match terms].
 * Match terms cover set names that appear WITHOUT the publisher, which is the
 * normal case in gaming-card listings ("Evolving Skies" rarely says Pokemon
 * Company; "Alpha" never says Wizards of the Coast).
 */
function card_brands(): array
{
    return [
        'wotc'       => ['label' => 'WotC era (Pokemon)', 'match' => [
            'wotc', 'wizards of the coast', 'base set', 'shadowless', 'jungle', 'fossil',
            'team rocket', 'gym heroes', 'gym challenge', 'neo genesis', 'neo discovery',
            'neo revelation', 'neo destiny', 'legendary collection', 'skyridge', 'aquapolis', 'expedition',
        ]],
        'pokemon-modern' => ['label' => 'Pokemon (modern)', 'match' => [
            'pokemon', 'pokmon', 'evolving skies', 'hidden fates', 'shining fates', 'crown zenith',
            'celebrations', 'lost origin', 'brilliant stars', 'astral radiance', 'silver tempest',
            'paldea', 'obsidian flames', 'paradox rift', 'temporal forces', 'twilight masquerade',
            'surging sparks', 'prismatic evolutions', 'scarlet', 'violet', 'sword', 'shield',
            'sun moon', 'xy', 'black white', 'heartgold', 'soulsilver', 'platinum', 'diamond pearl',
        ]],
        'magic'      => ['label' => 'Magic: The Gathering', 'match' => [
            'magic the gathering', 'mtg', 'alpha', 'beta', 'unlimited', 'revised', 'arabian nights',
            'antiquities', 'legends', 'the dark', 'fallen empires', 'ice age', 'mirage', 'tempest',
            'urza', 'invasion', 'ravnica', 'innistrad', 'zendikar', 'modern horizons', 'commander',
            'secret lair', 'double masters', 'reserved list',
        ]],
        'yugioh'     => ['label' => 'Yu-Gi-Oh!', 'match' => [
            'yugioh', 'yu-gi-oh', 'yu gi oh', 'lob', 'legend of blue eyes', 'metal raiders',
            'magic ruler', 'pharaoh', 'dark beginning', 'ghost rare', 'starlight rare', 'ultimate rare',
        ]],
        'onepiece'   => ['label' => 'One Piece TCG', 'match' => [
            'one piece', 'op-01', 'op-02', 'op-03', 'op-04', 'op-05', 'op-06', 'op-07',
            'romance dawn', 'paramount war', 'pillars of strength', 'kingdoms of intrigue',
        ]],
        'lorcana'    => ['label' => 'Disney Lorcana', 'match' => [
            'lorcana', 'first chapter', 'rise of the floodborn', 'into the inklands',
            'ursula', 'shimmering skies', 'azurite sea',
        ]],
        'digimon'    => ['label' => 'Digimon', 'match' => ['digimon', 'bt-01', 'bt01']],
        'topps-ccg'  => ['label' => 'Topps / other', 'match' => ['topps', 'garbage pail', 'star wars ccg', 'metazoo', 'flesh and blood']],
    ];
}

/** Does a lowercased listing title match a card_brands() key? */
function brand_matches(string $titleLc, string $brandKey): bool
{
    $brand = card_brands()[$brandKey] ?? null;
    if ($brand === null) {
        return true; // unknown/all - no constraint
    }
    foreach ($brand['match'] as $term) {
        if (str_contains($titleLc, $term)) {
            return true;
        }
    }
    return false;
}

/**
 * Chase cards by game - the cards that actually carry a set, lowercase, matched
 * against listing titles. These are the gaming-card equivalent of a Hall of
 * Fame list: the names that move money. Curated, not exhaustive: extend freely.
 */
function chase_cards(): array
{
    return [
        'pokemon' => [
            'charizard', 'blastoise', 'venusaur', 'pikachu', 'mewtwo', 'mew', 'lugia', 'ho-oh',
            'rayquaza', 'umbreon', 'espeon', 'sylveon', 'gengar', 'snorlax', 'dragonite',
            'eevee', 'gyarados', 'alakazam', 'machamp', 'zard', 'moonbreon', 'giratina',
            'arceus', 'darkrai', 'lucario', 'greninja', 'tyranitar', 'metagross', 'garchomp',
            'illustrator', 'trophy pikachu', 'tropical mega battle', 'no. 1 trainer',
        ],
        'magic' => [
            'black lotus', 'mox pearl', 'mox sapphire', 'mox jet', 'mox ruby', 'mox emerald',
            'ancestral recall', 'time walk', 'timetwister', 'underground sea', 'volcanic island',
            'tropical island', 'tundra', 'bayou', 'badlands', 'taiga', 'savannah', 'scrubland',
            'plateau', 'force of will', 'tarmogoyf', 'liliana of the veil', 'jace the mind sculptor',
            'the one ring', 'ragavan', 'wasteland', 'gaea\'s cradle', 'dual land',
        ],
        'yugioh' => [
            'blue-eyes white dragon', 'blue eyes white dragon', 'dark magician', 'red-eyes',
            'exodia', 'black luster soldier', 'cyber dragon', 'ash blossom', 'accesscode',
            'crush card virus', 'tournament black luster', 'skuna', 'des volstgalph',
        ],
        'onepiece' => [
            'luffy', 'shanks', 'zoro', 'nami', 'ace', 'law', 'yamato', 'kaido', 'boa hancock',
            'manga rare', 'alternate art',
        ],
        'lorcana' => [
            'elsa', 'mickey mouse', 'stitch', 'maleficent', 'ursula', 'enchanted', 'rapunzel',
        ],
        'digimon' => ['omnimon', 'agumon', 'wargreymon', 'gabumon', 'alternative art'],
    ];
}

/**
 * Is this listing title one of the chase cards? Pass a game key to narrow the
 * list, or null to check every game.
 */
function is_chase_title(string $titleLc, ?string $game = null): bool
{
    $lists = chase_cards();
    $names = $game !== null && isset($lists[$game])
        ? $lists[$game]
        : array_merge(...array_values($lists));

    foreach ($names as $name) {
        if (str_contains($titleLc, $name)) {
            return true;
        }
    }
    return false;
}

/** Grading companies: code (used in the eBay query) => display label. */
function card_companies(): array
{
    return [
        'PSA' => 'PSA',
        'BGS' => 'Beckett (BGS)',
        'SGC' => 'SGC',
        'CGC' => 'CGC',
    ];
}

/** Grade numbers we let users pick. Half grades apply to BGS/SGC/CGC. */
function card_grade_nums(): array
{
    return ['10', '9.5', '9', '8.5', '8', '7'];
}

/**
 * Hours from now until a UTC datetime string (negative if past). TZ-safe: both
 * ends are computed in UTC so it doesn't depend on PHP's default timezone.
 */
function hours_until(?string $utc): ?float
{
    if (!$utc) {
        return null;
    }
    try {
        $end = new DateTime($utc, new DateTimeZone('UTC'));
    } catch (\Exception $e) {
        return null;
    }
    $now = new DateTime('now', new DateTimeZone('UTC'));
    return ($end->getTimestamp() - $now->getTimestamp()) / 3600;
}

/** Human-friendly "time left" for an auction end time (UTC string). */
function time_left(?string $endTimeUtc): string
{
    if (!$endTimeUtc) {
        return '—';
    }
    try {
        $end = new DateTime($endTimeUtc, new DateTimeZone('UTC'));
    } catch (\Exception $e) {
        return '—';
    }
    $secs = $end->getTimestamp() - (new DateTime('now', new DateTimeZone('UTC')))->getTimestamp();
    if ($secs <= 0) {
        return 'ended';
    }
    $d = intdiv($secs, 86400);
    $h = intdiv($secs % 86400, 3600);
    $m = intdiv($secs % 3600, 60);
    if ($d > 0) return "{$d}d {$h}h";
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
}
