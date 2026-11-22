-- TradingCard101 — add the Chase card flag to alert triggers.
-- NOTE: the Alerts page applies this automatically on first load after
-- deploying; run manually only if that self-migration is blocked.

ALTER TABLE alert_triggers ADD COLUMN chase TINYINT(1) NOT NULL DEFAULT 0 AFTER first_ed;
