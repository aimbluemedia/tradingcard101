-- ==========================================================================
-- TradingCard101 — complete database (19 tables + starter data)
--
-- Import into an EMPTY database in phpMyAdmin:
--   select the database → Import → choose this file → Go
--
-- Creates the same 19 tables as SportCard101, with TradingCard101's columns
-- (game, first_ed, chase, ...). Seeds the Free/Pro plans, site settings and
-- one sample lesson. Creates NO user — make the superadmin afterwards by
-- visiting /setup.php (then delete setup.php from the server).
--
-- Safe to re-run: tables use CREATE TABLE IF NOT EXISTS and rows use
-- INSERT IGNORE, so nothing existing is dropped or overwritten.
--
-- Tables are created in dependency order (a table always comes after the
-- tables it links to), so the import works with phpMyAdmin's "Enable
-- foreign key checks" option on or off. Tables take the database's default
-- collation.
-- ==========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('superadmin','member') NOT NULL DEFAULT 'member',
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `plan_id` int(10) unsigned DEFAULT NULL,
  `sub_status` enum('none','trialing','active','past_due','canceled') NOT NULL DEFAULT 'none',
  `trial_ends_at` datetime DEFAULT NULL,
  `stripe_customer_id` varchar(64) DEFAULT NULL,
  `stripe_sub_id` varchar(64) DEFAULT NULL,
  `affiliate_code` varchar(16) DEFAULT NULL,
  `referred_by` varchar(16) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`),
  UNIQUE KEY `uniq_email` (`email`),
  UNIQUE KEY `uniq_affiliate` (`affiliate_code`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `skey` varchar(64) NOT NULL,
  `sval` text DEFAULT NULL,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `price_cents` int(10) unsigned NOT NULL DEFAULT 0,
  `bill_interval` enum('month','year') NOT NULL DEFAULT 'month',
  `stripe_price_id` varchar(64) DEFAULT NULL,
  `blurb` varchar(255) DEFAULT NULL,
  `features` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `content_modules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `summary` varchar(512) DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `content_lessons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `module_id` int(10) unsigned NOT NULL,
  `title` varchar(190) NOT NULL,
  `body` mediumtext DEFAULT NULL,
  `video_url` varchar(512) DEFAULT NULL,
  `is_free` tinyint(1) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `sort` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_module` (`module_id`),
  CONSTRAINT `fk_lesson_module` FOREIGN KEY (`module_id`) REFERENCES `content_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `searches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `label` varchar(190) NOT NULL,
  `keywords` varchar(255) NOT NULL,
  `grade` varchar(32) NOT NULL DEFAULT 'PSA 10',
  `max_price` decimal(10,2) DEFAULT NULL,
  `threshold_pct` tinyint(3) unsigned NOT NULL DEFAULT 25,
  `buying_option` enum('AUCTION','FIXED_PRICE','ANY') NOT NULL DEFAULT 'AUCTION',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `last_scanned_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_search_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `listings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `search_id` int(10) unsigned NOT NULL,
  `ebay_item_id` varchar(64) NOT NULL,
  `title` varchar(512) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `bid_count` int(11) DEFAULT NULL,
  `buying_option` varchar(32) DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `image_url` varchar(1024) DEFAULT NULL,
  `item_url` varchar(1024) NOT NULL,
  `item_condition` varchar(128) DEFAULT NULL,
  `seller` varchar(190) DEFAULT NULL,
  `baseline_price` decimal(10,2) DEFAULT NULL,
  `discount_pct` decimal(6,2) DEFAULT NULL,
  `is_deal` tinyint(1) NOT NULL DEFAULT 0,
  `ai_verdict` varchar(8) DEFAULT NULL,
  `ai_confidence` tinyint(3) unsigned DEFAULT NULL,
  `ai_card` varchar(255) DEFAULT NULL,
  `ai_reason` varchar(512) DEFAULT NULL,
  `ai_flip_pct` decimal(6,2) DEFAULT NULL,
  `ai_hidden_gem` tinyint(1) NOT NULL DEFAULT 0,
  `notified` tinyint(1) NOT NULL DEFAULT 0,
  `error_notified` tinyint(1) NOT NULL DEFAULT 0,
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_search_item` (`search_id`,`ebay_item_id`),
  KEY `idx_deal` (`search_id`,`is_deal`),
  CONSTRAINT `fk_listing_search` FOREIGN KEY (`search_id`) REFERENCES `searches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bid_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` bigint(20) unsigned NOT NULL,
  `bid_count` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `snapped_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_listing_time` (`listing_id`,`snapped_at`),
  CONSTRAINT `fk_snap_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `daily_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_date` date NOT NULL,
  `summary` text DEFAULT NULL,
  `budget_day` decimal(10,2) NOT NULL DEFAULT 0.00,
  `exposure` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ai_mode` varchar(16) NOT NULL DEFAULT 'mock',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_date` (`plan_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plan_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned NOT NULL,
  `kind` enum('BUY','WATCH') NOT NULL DEFAULT 'BUY',
  `ebay_item_id` varchar(64) NOT NULL,
  `card` varchar(255) DEFAULT NULL,
  `game` varchar(32) DEFAULT NULL,
  `grade` varchar(32) DEFAULT NULL,
  `current_price` decimal(10,2) DEFAULT NULL,
  `bid_count` int(11) DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `item_url` varchar(1024) DEFAULT NULL,
  `comp_median` decimal(10,2) DEFAULT NULL,
  `comp_count` int(11) DEFAULT NULL,
  `comp_low` decimal(10,2) DEFAULT NULL,
  `comp_high` decimal(10,2) DEFAULT NULL,
  `max_bid` decimal(10,2) DEFAULT NULL,
  `est_resale` decimal(10,2) DEFAULT NULL,
  `est_net` decimal(10,2) DEFAULT NULL,
  `reason` varchar(512) DEFAULT NULL,
  `final_price` decimal(10,2) DEFAULT NULL,
  `would_have_won` tinyint(1) DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_plan` (`plan_id`),
  KEY `idx_item` (`ebay_item_id`),
  CONSTRAINT `fk_pt_plan` FOREIGN KEY (`plan_id`) REFERENCES `daily_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `referrals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `affiliate_user_id` int(10) unsigned NOT NULL,
  `referred_user_id` int(10) unsigned NOT NULL,
  `reward_cents` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','credited','void') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_affiliate` (`affiliate_user_id`),
  KEY `fk_ref_user` (`referred_user_id`),
  CONSTRAINT `fk_ref_aff` FOREIGN KEY (`affiliate_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_user` FOREIGN KEY (`referred_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventory` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `card_name` varchar(255) NOT NULL,
  `game` varchar(32) DEFAULT NULL,
  `year` varchar(10) DEFAULT NULL,
  `set_name` varchar(120) DEFAULT NULL,
  `player` varchar(120) DEFAULT NULL,
  `card_number` varchar(40) DEFAULT NULL,
  `parallel` varchar(120) DEFAULT NULL,
  `grade_company` varchar(8) NOT NULL DEFAULT 'PSA',
  `grade` varchar(8) DEFAULT NULL,
  `cert_number` varchar(40) DEFAULT NULL,
  `card_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ship_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `purchase_source` varchar(120) DEFAULT NULL,
  `purchased_at` date DEFAULT NULL,
  `status` enum('RAW','AT_GRADER','GRADED','LISTED','SOLD') NOT NULL DEFAULT 'GRADED',
  `list_price` decimal(10,2) DEFAULT NULL,
  `listed_at` date DEFAULT NULL,
  `sold_price` decimal(10,2) DEFAULT NULL,
  `sold_fees` decimal(10,2) DEFAULT NULL,
  `sold_ship` decimal(10,2) DEFAULT NULL,
  `sold_at` date DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `image_url` varchar(1024) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `card_key` varchar(255) DEFAULT NULL,
  `base_key` varchar(255) DEFAULT NULL,
  `est_value` decimal(10,2) DEFAULT NULL,
  `est_at` datetime DEFAULT NULL,
  `sell_signal_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`status`),
  CONSTRAINT `fk_inv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventory_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `snap_date` date NOT NULL,
  `cards` int(11) NOT NULL DEFAULT 0,
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `est_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_day` (`user_id`,`snap_date`),
  CONSTRAINT `fk_snap_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `alert_triggers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(120) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `game` varchar(32) NOT NULL DEFAULT 'all',
  `grade` varchar(16) NOT NULL DEFAULT 'any',
  `brand` varchar(32) NOT NULL DEFAULT 'all',
  `signed` tinyint(1) NOT NULL DEFAULT 0,
  `first_ed` tinyint(1) NOT NULL DEFAULT 0,
  `chase` tinyint(1) NOT NULL DEFAULT 0,
  `keywords` varchar(190) DEFAULT NULL,
  `max_price` decimal(10,2) DEFAULT NULL,
  `min_under_comp` decimal(6,2) DEFAULT NULL,
  `require_comp` tinyint(1) NOT NULL DEFAULT 0,
  `within_hours` decimal(6,1) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `error_cards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(190) NOT NULL,
  `error_name` varchar(190) NOT NULL,
  `game` varchar(32) DEFAULT NULL,
  `year` varchar(10) DEFAULT NULL,
  `set_name` varchar(120) DEFAULT NULL,
  `card_number` varchar(40) DEFAULT NULL,
  `player` varchar(120) DEFAULT NULL,
  `error_type` varchar(20) NOT NULL DEFAULT 'other',
  `description` text DEFAULT NULL,
  `what_to_look_for` text DEFAULT NULL,
  `corrected_exists` tinyint(1) NOT NULL DEFAULT 0,
  `scarcer` varchar(12) NOT NULL DEFAULT 'UNKNOWN',
  `slab_label` varchar(190) DEFAULT NULL,
  `premium_note` varchar(255) DEFAULT NULL,
  `rarity_note` varchar(255) DEFAULT NULL,
  `image_url` varchar(1024) DEFAULT NULL,
  `search_terms` varchar(500) DEFAULT NULL,
  `source_title` varchar(512) DEFAULT NULL,
  `confidence` tinyint(3) unsigned DEFAULT NULL,
  `source` varchar(10) NOT NULL DEFAULT 'MANUAL',
  `status` enum('DRAFT','PUBLISHED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  `views` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_status` (`status`,`game`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `error_candidates_hidden` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title_key` char(40) NOT NULL,
  `title` varchar(512) NOT NULL,
  `hidden_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_title` (`title_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ebay_item_id` varchar(64) NOT NULL,
  `title` varchar(512) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `bid_count` int(11) DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `image_url` varchar(1024) DEFAULT NULL,
  `item_url` varchar(1024) NOT NULL,
  `est_cards` int(11) DEFAULT NULL,
  `ai_verdict` varchar(8) DEFAULT NULL,
  `ai_est_low` decimal(10,2) DEFAULT NULL,
  `ai_est_high` decimal(10,2) DEFAULT NULL,
  `ai_reason` varchar(512) DEFAULT NULL,
  `analyzed_at` datetime DEFAULT NULL,
  `notified` tinyint(1) NOT NULL DEFAULT 0,
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_item` (`ebay_item_id`),
  KEY `idx_end` (`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sold_comps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ebay_item_id` varchar(64) NOT NULL,
  `search_id` int(10) unsigned DEFAULT NULL,
  `game` varchar(32) DEFAULT NULL,
  `grade` varchar(32) DEFAULT NULL,
  `canonical_card` varchar(255) DEFAULT NULL,
  `card_key` varchar(255) DEFAULT NULL,
  `title` varchar(512) DEFAULT NULL,
  `final_price` decimal(10,2) NOT NULL,
  `final_bids` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `image_url` varchar(1024) DEFAULT NULL,
  `item_url` varchar(1024) DEFAULT NULL,
  `closed_at` datetime NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_item` (`ebay_item_id`),
  KEY `idx_card` (`game`,`grade`,`card_key`),
  KEY `idx_closed` (`closed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `trades` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card` varchar(255) NOT NULL,
  `ebay_item_id` varchar(64) DEFAULT NULL,
  `game` varchar(32) DEFAULT NULL,
  `grade` varchar(32) DEFAULT NULL,
  `buy_price` decimal(10,2) NOT NULL,
  `bought_at` date NOT NULL,
  `status` enum('BOUGHT','LISTED','SOLD') NOT NULL DEFAULT 'BOUGHT',
  `listed_at` date DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `sold_at` date DEFAULT NULL,
  `fees` decimal(10,2) DEFAULT NULL,
  `notes` varchar(512) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- starter data ----------
INSERT IGNORE INTO `plans` (`id`, `name`, `slug`, `price_cents`, `bill_interval`, `stripe_price_id`, `blurb`, `features`, `is_active`, `sort`, `created_at`) VALUES (1,'Free','free',0,'month',NULL,'Start learning the hobby.','Top 3 daily AI deals\nFree starter lessons\nMember community',1,0,'2026-11-22 17:35:49'),
(2,'Pro','pro',900,'month',NULL,'The full deal engine + all lessons.','Full AI deal board\nFlip-margin math\nHidden-gem alerts\nAll lessons & courses\nAffiliate program',1,1,'2026-11-22 17:35:49');
INSERT IGNORE INTO `settings` (`skey`, `sval`) VALUES ('enable_member_searches','0'),
('hero_subtitle','TradingCard101 watches the graded Pokemon, Magic and Yu-Gi-Oh auctions all day, prices every card against what it actually sold for, and tells you the one number that decides whether you make money: *your maximum bid*.'),
('hero_title','Pokemon cards are a *real business*. Most people just bid blind.'),
('site_url','https://tradingcard101.com'),
('skool_url','');
INSERT IGNORE INTO `content_modules` (`id`, `title`, `slug`, `summary`, `sort`, `is_published`, `created_at`) VALUES (1,'Getting Started','getting-started','The absolute basics of trading-card collecting and flipping.',0,1,'2026-11-22 17:35:49');
INSERT IGNORE INTO `content_lessons` (`id`, `module_id`, `title`, `body`, `video_url`, `is_free`, `is_published`, `sort`, `created_at`) VALUES (1,1,'What is a PSA 10 and why it matters','PSA 10 means a card graded Gem Mint by PSA. Graded cards sell for predictable prices, which is exactly what makes deal-hunting possible. In this lesson we cover grading basics and how to read a comp.',NULL,1,1,0,'2026-11-22 17:35:49');

SET FOREIGN_KEY_CHECKS = 1;
