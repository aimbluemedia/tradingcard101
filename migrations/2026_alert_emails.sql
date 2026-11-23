-- TradingCard101 — keep a copy of every deal-alert email for the members'
-- Deal Alerts history page. The app also creates this table on first use;
-- run manually only if that is blocked.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS alert_emails (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sent_at     DATETIME     NOT NULL,          -- UTC
    match_count INT UNSIGNED NOT NULL DEFAULT 0,
    matches     MEDIUMTEXT   NOT NULL,          -- JSON snapshot of each auction
    PRIMARY KEY (id),
    KEY idx_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
