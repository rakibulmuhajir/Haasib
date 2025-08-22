-- Module 93 — Schema Version Registry
-- Purpose: record applied modules and versions for auditing and CI
BEGIN;

CREATE SCHEMA IF NOT EXISTS sys;

CREATE TABLE IF NOT EXISTS sys.schema_versions (
  id BIGSERIAL PRIMARY KEY,
  module_code TEXT NOT NULL,          -- e.g., '00_core', '15_posting'
  version TEXT NOT NULL,              -- semver or date tag
  checksum TEXT NOT NULL,             -- sha256 of file contents
  applied_by TEXT NOT NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes TEXT,
  UNIQUE (module_code, version)
);

-- Upsert helper
CREATE OR REPLACE FUNCTION sys.register_migration(p_module TEXT, p_version TEXT, p_checksum TEXT, p_applied_by TEXT, p_notes TEXT DEFAULT NULL)
RETURNS VOID LANGUAGE plpgsql AS $$
BEGIN
  INSERT INTO sys.schema_versions(module_code, version, checksum, applied_by, notes)
  VALUES (p_module, p_version, p_checksum, p_applied_by, p_notes)
  ON CONFLICT (module_code, version) DO UPDATE
     SET checksum = EXCLUDED.checksum,
         applied_by = EXCLUDED.applied_by,
         notes = COALESCE(EXCLUDED.notes, sys.schema_versions.notes),
         applied_at = NOW();
END $$;

COMMIT;
