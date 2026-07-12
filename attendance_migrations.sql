-- ============================================================
-- School Vault — Attendance Module Migrations
-- Run this file ONCE before deploying the updated PHP code.
-- Safe to run on empty tables; harmless on existing data.
-- ============================================================

-- ------------------------------------------------------------
-- Phase 0: Missing indexes on staff_attendance & exam_attendance
-- ------------------------------------------------------------

-- staff_attendance: needed for monthly employee report and daily lookups
ALTER TABLE `staff_attendance`
    ADD INDEX `idx_sa_staff_date`  (`staff_id`, `date`),
    ADD INDEX `idx_sa_branch_date` (`branch_id`, `date`);

-- exam_attendance: needed for exam-wise report queries
ALTER TABLE `exam_attendance`
    ADD INDEX `idx_ea_student_exam` (`student_id`, `exam_id`),
    ADD INDEX `idx_ea_branch`       (`branch_id`);

-- ------------------------------------------------------------
-- Phase 2: Schema additions (capture_method, exam audit trail,
--           QR token, QR session table)
-- ------------------------------------------------------------

-- capture_method on student_attendance (distinguishes manual / QR / geo)
ALTER TABLE `student_attendance`
    ADD COLUMN `capture_method` ENUM('manual','qr','geo')
        NOT NULL DEFAULT 'manual'
        AFTER `remark`;

-- exam_attendance audit trail (matching student_attendance columns)
ALTER TABLE `exam_attendance`
    ADD COLUMN `created_by` INT NULL AFTER `branch_id`,
    ADD COLUMN `updated_by` INT NULL AFTER `created_by`,
    ADD COLUMN `updated_at` DATETIME NULL AFTER `updated_by`;

-- ------------------------------------------------------------
-- Phase 3: QR attendance schema
-- ------------------------------------------------------------

-- QR token column on students (one token per student, rotated termly)
ALTER TABLE `student`
    ADD COLUMN `qr_token` VARCHAR(64) NULL UNIQUE AFTER `register_no`;

-- Time-bounded QR scan sessions (teacher opens a window, scanner marks within it)
CREATE TABLE IF NOT EXISTS `qr_attendance_sessions` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `branch_id`    INT          NOT NULL,
    `class_id`     INT          NOT NULL,
    `section_id`   INT          NOT NULL,
    `date`         DATE         NOT NULL,
    `period_id`    INT          NULL,
    `opened_by`    INT          NOT NULL,
    `window_open`  DATETIME     NOT NULL,
    `window_close` DATETIME     NOT NULL,
    `status`       ENUM('open','closed') NOT NULL DEFAULT 'open',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_qas_branch_date` (`branch_id`, `date`),
    INDEX `idx_qas_class_status` (`class_id`, `section_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- (Optional) Phase 5 geo-fence columns — add when geo is built
-- ------------------------------------------------------------
-- ALTER TABLE `branch`
--     ADD COLUMN `geo_lat`       DECIMAL(9,6) NULL,
--     ADD COLUMN `geo_lng`       DECIMAL(9,6) NULL,
--     ADD COLUMN `geo_fence_m`   INT          NOT NULL DEFAULT 200;
--
-- ALTER TABLE `student_attendance`
--     ADD COLUMN `check_lat`  DECIMAL(9,6) NULL AFTER `capture_method`,
--     ADD COLUMN `check_lng`  DECIMAL(9,6) NULL AFTER `check_lat`;
