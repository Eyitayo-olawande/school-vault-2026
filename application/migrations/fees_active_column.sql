-- ============================================================
-- Add fees_active flag to student table
-- Run once on each environment (local + production)
-- Safe to re-run: column only added if it doesn't exist
-- ============================================================

ALTER TABLE student
  ADD COLUMN IF NOT EXISTS fees_active           TINYINT(1)   NOT NULL DEFAULT 1
      COMMENT '1 = included in fee reports; 0 = excluded from all fee calculations',
  ADD COLUMN IF NOT EXISTS fees_deactivated_at   DATETIME     DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS fees_deactivated_by   INT(11)      DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS fees_deactivated_reason VARCHAR(500) DEFAULT NULL;

-- All existing students default to active (DEFAULT 1 handles this automatically)
