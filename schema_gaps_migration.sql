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

-- 4. student is missing stoppage_point_id ------------------------------------
-- Fees_model::getInvoiceBasic() selects s.stoppage_point_id to look up
-- transport fees. Fees.php::invoice() and invoicePrint() both pass this value
-- to getStudentTransportFees(). Without the column every invoice page 500s.
-- NULL means the student has no assigned transport stop (no transport fees).

ALTER TABLE `student`
  ADD COLUMN IF NOT EXISTS `stoppage_point_id` INT(11) NULL DEFAULT NULL;


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


-- 5. transport_stoppage_point and transport_fee_details are missing ----------
-- getInvoiceStatus() (Fees_model line ~185) and invoice/collect-fees pages
-- reference these tables. Without them any invoice page 500s.
-- The code now guards with table_exists(), but create them so transport can
-- be enabled later without further migrations.
-- transport_fee_details_id is also missing from fee_payment_history.

CREATE TABLE IF NOT EXISTS `transport_stoppage_point` (
  `id`         INT(11)        NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255)   NOT NULL DEFAULT '',
  `route_fare` DECIMAL(18,2)  NOT NULL DEFAULT '0.00',
  `branch_id`  INT(11)        NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transport_fee_fine` (
  `id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `month`    VARCHAR(20)  NOT NULL DEFAULT '',
  `due_date` DATE         NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transport_fee_details` (
  `id`                    INT(11) NOT NULL AUTO_INCREMENT,
  `enroll_id`             INT(11) NOT NULL DEFAULT 0,
  `stoppage_point_id`     INT(11) NOT NULL DEFAULT 0,
  `transport_fee_fine_id` INT(11) NOT NULL DEFAULT 0,
  `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enroll` (`enroll_id`),
  KEY `idx_stop`   (`stoppage_point_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `fee_payment_history`
  ADD COLUMN IF NOT EXISTS `transport_fee_details_id` INT(11) NULL DEFAULT NULL;
