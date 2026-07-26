-- ---------------------------------------------------------------------------
-- Schema gaps: columns and tables the application code writes but which were
-- never created. Each of these causes a hard failure at runtime, not a silent
-- degradation, so they must be applied before the affected feature is used.
--
-- Safe to re-run: MariaDB 10.4 supports IF NOT EXISTS on both forms.
-- Adds nullable columns only, so existing rows are untouched.
--
-- Apply to: local dev, staging, and production (in that order).
-- ---------------------------------------------------------------------------


-- 1. exam_attendance is missing created_by / updated_by --------------------
-- Attendance.php::exam_entry() sets 'created_by' on INSERT and 'updated_by'
-- on UPDATE. Without these columns every exam-attendance save fails with
-- "Unknown column 'created_by' in 'field list'".
-- student_attendance and staff_attendance already have both; exam_attendance
-- was missed. Types below match those tables exactly.

ALTER TABLE `exam_attendance`
  ADD COLUMN IF NOT EXISTS `created_by` INT(11) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `updated_by` INT(11) NULL DEFAULT NULL;


-- 2. student_attendance is missing capture_method --------------------------
-- Qrcode_attendance.php::scan() writes 'qr' here and reads it back to reject
-- a second scan on the same day. NULL means the row was entered manually,
-- which is the correct reading for every pre-existing row.

ALTER TABLE `student_attendance`
  ADD COLUMN IF NOT EXISTS `capture_method` VARCHAR(16) NULL DEFAULT NULL;


-- 3. qr_attendance_sessions does not exist ---------------------------------
-- Qrcode_attendance.php::open_session() inserts here and ::scan() requires a
-- matching row inside its time window before it will record attendance, so
-- without this table QR attendance cannot work at all.
--
-- Column list is derived from the controller's own INSERT and WHERE clauses:
-- branch_id, class_id, section_id, date, opened_by, window_open, window_close,
-- status; plus id, which open_session() returns via insert_id().

CREATE TABLE IF NOT EXISTS `qr_attendance_sessions` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `branch_id`    INT(11) NOT NULL,
  `class_id`     INT(11) NOT NULL,
  `section_id`   INT(11) NOT NULL,
  `date`         DATE NOT NULL,
  `opened_by`    INT(11) NULL DEFAULT NULL,
  `window_open`  DATETIME NOT NULL,
  `window_close` DATETIME NOT NULL,
  `status`       VARCHAR(16) NOT NULL DEFAULT 'open',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- scan() looks up by branch/class/section/status, then filters on the window
  KEY `idx_session_lookup` (`branch_id`, `class_id`, `section_id`, `status`),
  KEY `idx_session_window` (`window_open`, `window_close`),
  -- open_session() closes stale rows by branch/class/section/date/status
  KEY `idx_session_date` (`branch_id`, `class_id`, `section_id`, `date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
