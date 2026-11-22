# TradingCard101 — ported from SportCard101

This is a full copy of the SportCard101 codebase re-pointed at collectible card
games (Pokémon, Magic: The Gathering, Yu-Gi-Oh!, One Piece, Lorcana, Digimon).
Every feature came across: the eBay scanner, the AI opportunity engine, sold
comps, the Morning Playbook, Snap Shot, bulk lots, high-bid leaderboards and
demand profiles, the error-card catalogue, inventory, alerts, the member area,
the admin area and the landing page.

## What changed, and why

| Sports concept | Gaming concept | Where |
| --- | --- | --- |
| `card_sports()` — baseball, basketball, football, hockey, golf | `card_games()` — pokemon, magic, yugioh, onepiece, lorcana, digimon | `src/helpers.php` |
| `card_brands()` — Topps, Panini, Bowman, Upper Deck… | publishers **and set families** — WotC era, modern Pokémon, Magic, Yu-Gi-Oh!, One Piece, Lorcana, Digimon | `src/helpers.php` |
| `hof_players()` — Hall of Fame athletes | `chase_cards()` — the cards that carry a set (Charizard, Black Lotus, Blue-Eyes…) | `src/helpers.php` |
| `rookie` flag | `first_ed` flag — 1st Edition is the gaming premium marker | schema, alerts, auctions |
| `hof` flag | `chase` flag | schema, alerts |
| `sport` column | `game` column | every table that had it |
| PSA / BGS / SGC | PSA / BGS / **CGC** (CGC is dominant in Pokémon) | `card_companies()` |
| 12 sports error types (wrong photo, wrong stats, relic…) | 13 gaming error types (holo bleed, miscut, set symbol, 1st Ed stamp, language…) | `src/ErrorCards.php` |
| eBay category `212` (Sports Trading Cards), hardcoded | **configurable**, defaults to `2536` (Collectible Card Games) | `src/EbayClient.php` |

### The eBay category is now a setting, not a constant

`EbayClient::DEFAULT_CATEGORY` is `2536`. **Verify that id against eBay's live
category tree before you rely on it** — category ids move, and I could not
check it from the build environment. Override it without touching code:

> Superadmin → Settings → `ebay_category_id`

Narrow it to the individual-cards child category if sealed product and bulk
lots turn out to be noise in your scans.

### Renames that needed judgement, not search-and-replace

A mechanical rename broke three pieces of real logic, because `rookie` was
sometimes a **column name** and sometimes **text being matched against listing
titles**. These were found and rewritten by hand:

- `superadmin/auctions.php` — `LIKE '%rookie%'` became `LIKE '%1st edition%' OR
  '%1st ed%' OR '%first edition%' OR '%shadowless%'`. A literal rename would
  have searched listings for the string `first_ed`, which never appears.
- `src/DealAlerts.php` — the `/\b(rookie|rc)\b/` title regex became
  `/\b(1st\s*ed(ition)?|first\s*edition|shadowless)\b/i`.
- `src/AiAnalyst.php` — the "is this title complete?" heuristic now looks for
  `1st ed` / `first edition` / `holo` instead of `rookie` / `rc`.

## Setup

1. Create a MySQL/MariaDB database, then import `schema.sql`.
2. Copy `config.sample.php` to `config.php` and fill in database, eBay,
   Anthropic and SMTP credentials.
3. `php bin/install.php <admin_username> <admin_password> [admin_email]`
4. Point a vhost at the project root. Visit `/` for the landing page and
   `/superadmin/login.php` to administer.
5. Add the cron job (every 30 minutes, plus a daily run):

   ```
   */30 * * * * /usr/bin/php /path/to/tradingcard101/cron.php <CRON_KEY>
   0 7 * * *    /usr/bin/php /path/to/tradingcard101/cron.php <CRON_KEY> daily
   ```

   The cron key is in Superadmin → Settings.

## Before you launch

- **Replace the hero image.** The hero is a full-width shop photo behind a navy
  wash, with the headline on the left and a floating deals panel on the right.
  `assets/img/hero-shop.{webp,jpg}` is the shop photo (1448x1086). Its neon
  sign still reads "Monster Card Shop" — swap in a version that says
  TradingCard101 before launch. To replace it, save a landscape photo over
  both files; the left ~45% sits under the dark wash and the headline. The page crops it with `object-position: 50% 12%`.
- **Check the chase lists.** `chase_cards()` in `src/helpers.php` is curated
  from memory and is deliberately short. It drives the chase-card alert filter,
  so extend it with the cards your buyers actually chase.
- **Tune `card_brands()` set names.** Set names were written from knowledge of
  these games, not scraped from a price guide — verify spellings against live
  eBay titles before trusting the brand filter.
- **Pricing.** `bin/install.php` seeds Free and a $9/month plan. Change it in
  Superadmin → Pricing.
- Sample figures in the landing page's playbook panel are illustrative and
  labelled **Sample**. Keep that label if you change the numbers.

## Verification done on this port

- `php -l` clean across all 54 PHP files.
- The landing page renders (25.6 KB) against a stubbed data layer, with no
  horizontal overflow at 1440px.
- Assertions pass for: the games taxonomy, chase matching (including that a
  Magic card does not match under `pokemon`), brand matching, an unknown brand
  not constraining results, CGC being present, and the 1st-Edition regex
  matching `1st edition` / `1ST ED` / `shadowless` / `first edition` while
  rejecting `unlimited` and `evolving skies`.

Nothing here has been run against a live database, a real eBay key or a real
Anthropic key.
