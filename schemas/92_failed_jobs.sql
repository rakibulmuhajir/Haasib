-- 92_failed_jobs.sql — Failed Job Registry (PostgreSQL)
-- Depends on: 90_system.sql
BEGIN;

CREATE SCHEMA IF NOT EXISTS sys;
SET search_path = sys, public;

CREATE TABLE IF NOT EXISTS failed_jobs (
  job_id BIGSERIAL PRIMARY KEY,
  queue TEXT NOT NULL DEFAULT 'default',
  job_type TEXT NOT NULL,             -- e.g. 'webhook', 'report', 'email'
  payload JSONB NOT NULL,
  error_message TEXT,
  error_backtrace TEXT,
  status TEXT NOT NULL DEFAULT 'failed', -- failed, retried, ignored
  attempts INT NOT NULL DEFAULT 0,
  last_attempt_at TIMESTAMP,
  next_attempt_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP,
  resolved_by TEXT
);

-- Indexes for dashboard/monitoring
CREATE INDEX IF NOT EXISTS idx_failed_jobs_status ON sys.failed_jobs(status, next_attempt_at);
CREATE INDEX IF NOT EXISTS idx_failed_jobs_queue  ON sys.failed_jobs(queue);

-- Log a failure
CREATE OR REPLACE FUNCTION sys.log_failed_job(p_queue TEXT,
                                              p_job_type TEXT,
                                              p_payload JSONB,
                                              p_error TEXT,
                                              p_backtrace TEXT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE new_id BIGINT;
BEGIN
  INSERT INTO sys.failed_jobs(queue, job_type, payload, error_message, error_backtrace, last_attempt_at, attempts)
  VALUES (p_queue, p_job_type, p_payload, p_error, p_backtrace, NOW(), 1)
  RETURNING job_id INTO new_id;
  RETURN new_id;
END $$;

-- Mark retried
CREATE OR REPLACE FUNCTION sys.retry_failed_job(p_job_id BIGINT, p_next TIMESTAMP)
RETURNS VOID LANGUAGE plpgsql AS $$
BEGIN
  UPDATE sys.failed_jobs
     SET status='retried', attempts = attempts+1, next_attempt_at=p_next
   WHERE job_id=p_job_id;
END $$;

-- Resolve job manually
CREATE OR REPLACE FUNCTION sys.resolve_failed_job(p_job_id BIGINT, p_user TEXT)
RETURNS VOID LANGUAGE plpgsql AS $$
BEGIN
  UPDATE sys.failed_jobs
     SET status='resolved', resolved_at=NOW(), resolved_by=p_user
   WHERE job_id=p_job_id;
END $$;

COMMIT;
