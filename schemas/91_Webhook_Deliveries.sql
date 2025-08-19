-- 91_webhook_deliveries.sql — Webhook Delivery Log
-- Depends: 90_system.sql (sys.webhooks)
BEGIN;

CREATE SCHEMA IF NOT EXISTS sys;
SET search_path = sys, public;

CREATE TABLE IF NOT EXISTS webhook_deliveries (
  delivery_id BIGSERIAL PRIMARY KEY,
  webhook_id BIGINT NOT NULL REFERENCES sys.webhooks(webhook_id) ON DELETE CASCADE,
  event_name TEXT NOT NULL,
  payload JSONB NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending', -- pending, success, failed
  attempt_count INT NOT NULL DEFAULT 0,
  last_attempt_at TIMESTAMP,
  next_attempt_at TIMESTAMP,
  response_status INT,
  response_body TEXT,
  response_time_ms INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS webhook_delivery_events (
  delivery_event_id BIGSERIAL PRIMARY KEY,
  delivery_id BIGINT NOT NULL REFERENCES sys.webhook_deliveries(delivery_id) ON DELETE CASCADE,
  attempt_no INT NOT NULL,
  requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  response_status INT,
  response_time_ms INT,
  error_message TEXT,
  endpoint TEXT
);

CREATE OR REPLACE FUNCTION sys.log_webhook_attempt(p_delivery_id BIGINT,
                                                   p_status INT,
                                                   p_time_ms INT,
                                                   p_error TEXT,
                                                   p_endpoint TEXT)
RETURNS VOID LANGUAGE plpgsql AS $$
DECLARE n INT;
BEGIN
  SELECT COALESCE(attempt_count,0)+1 INTO n
  FROM sys.webhook_deliveries WHERE delivery_id=p_delivery_id FOR UPDATE;
  INSERT INTO sys.webhook_delivery_events(delivery_id, attempt_no, response_status, response_time_ms, error_message, endpoint)
  VALUES (p_delivery_id, n, p_status, p_time_ms, p_error, p_endpoint);

  UPDATE sys.webhook_deliveries
     SET attempt_count = n,
         last_attempt_at = NOW(),
         response_status = p_status,
         response_time_ms = p_time_ms,
         status = CASE WHEN p_status BETWEEN 200 AND 299 THEN 'success' ELSE 'failed' END
   WHERE delivery_id = p_delivery_id;
END $$;

CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_status ON sys.webhook_deliveries(status, next_attempt_at);
CREATE INDEX IF NOT EXISTS idx_webhook_delivery_events_delivery ON sys.webhook_delivery_events(delivery_id);

COMMIT;
-- 91_webhook_deliveries.sql — Webhook Delivery Log
-- Depends: 90_system.sql (sys.webhooks)
BEGIN;

CREATE SCHEMA IF NOT EXISTS sys;
SET search_path = sys, public;

CREATE TABLE IF NOT EXISTS webhook_deliveries (
  delivery_id BIGSERIAL PRIMARY KEY,
  webhook_id BIGINT NOT NULL REFERENCES sys.webhooks(webhook_id) ON DELETE CASCADE,
  event_name TEXT NOT NULL,
  payload JSONB NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending', -- pending, success, failed
  attempt_count INT NOT NULL DEFAULT 0,
  last_attempt_at TIMESTAMP,
  next_attempt_at TIMESTAMP,
  response_status INT,
  response_body TEXT,
  response_time_ms INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS webhook_delivery_events (
  delivery_event_id BIGSERIAL PRIMARY KEY,
  delivery_id BIGINT NOT NULL REFERENCES sys.webhook_deliveries(delivery_id) ON DELETE CASCADE,
  attempt_no INT NOT NULL,
  requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  response_status INT,
  response_time_ms INT,
  error_message TEXT,
  endpoint TEXT
);

CREATE OR REPLACE FUNCTION sys.log_webhook_attempt(p_delivery_id BIGINT,
                                                   p_status INT,
                                                   p_time_ms INT,
                                                   p_error TEXT,
                                                   p_endpoint TEXT)
RETURNS VOID LANGUAGE plpgsql AS $$
DECLARE n INT;
BEGIN
  SELECT COALESCE(attempt_count,0)+1 INTO n
  FROM sys.webhook_deliveries WHERE delivery_id=p_delivery_id FOR UPDATE;
  INSERT INTO sys.webhook_delivery_events(delivery_id, attempt_no, response_status, response_time_ms, error_message, endpoint)
  VALUES (p_delivery_id, n, p_status, p_time_ms, p_error, p_endpoint);

  UPDATE sys.webhook_deliveries
     SET attempt_count = n,
         last_attempt_at = NOW(),
         response_status = p_status,
         response_time_ms = p_time_ms,
         status = CASE WHEN p_status BETWEEN 200 AND 299 THEN 'success' ELSE 'failed' END
   WHERE delivery_id = p_delivery_id;
END $$;

CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_status ON sys.webhook_deliveries(status, next_attempt_at);
CREATE INDEX IF NOT EXISTS idx_webhook_delivery_events_delivery ON sys.webhook_delivery_events(delivery_id);

COMMIT;
