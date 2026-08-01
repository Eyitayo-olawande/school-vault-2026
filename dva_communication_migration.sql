-- ============================================================
-- DVA Payment Communication System - Database Migration
-- Run once on both schoolvault26 (dev) and production DB
-- ============================================================

-- 1. term_dates table
CREATE TABLE IF NOT EXISTS `term_dates` (
    `id`               INT          NOT NULL AUTO_INCREMENT,
    `branch_id`        INT          NOT NULL,
    `session_id`       INT          NOT NULL,
    `term`             ENUM('1ST','2ND','3RD') NOT NULL,
    `resumption_date`  DATE         NOT NULL,
    `midterm_date`     DATE         DEFAULT NULL,
    `exam_start_date`  DATE         DEFAULT NULL,
    `created_at`       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_branch_session_term` (`branch_id`, `session_id`, `term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. New SMS API providers (Termii and SmartSMS)
-- Only inserts if id 9/10 don't exist yet
INSERT IGNORE INTO `sms_api` (`id`, `name`) VALUES (9, 'termii');
INSERT IGNORE INTO `sms_api` (`id`, `name`) VALUES (10, 'smartsms');

-- 3. DVA SMS templates (IDs 12-17)
-- These are global templates; per-branch template_body is in sms_template_details
INSERT IGNORE INTO `sms_template` (`id`, `name`, `tags`) VALUES
(12, 'DVA Fee Allocation Notice',    '{guardian_name},{child_name},{fee_name},{amount},{dva_account},{dva_bank}'),
(13, 'DVA Resumption Reminder 14d', '{guardian_name},{child_name},{term},{resumption_date},{balance},{dva_account},{dva_bank}'),
(14, 'DVA Resumption Reminder 7d',  '{guardian_name},{child_name},{term},{resumption_date},{balance},{dva_account},{dva_bank}'),
(15, 'DVA Resumption Reminder Weekend', '{guardian_name},{child_name},{term},{resumption_date},{balance},{dva_account},{dva_bank}'),
(16, 'DVA Mid-Term Reminder',       '{guardian_name},{child_name},{term},{midterm_date},{balance},{dva_account},{dva_bank}'),
(17, 'DVA Pre-Exam Reminder',       '{guardian_name},{child_name},{term},{exam_start_date},{balance},{dva_account},{dva_bank}');

-- 4. Default template bodies (insert for branch_id=1; admins should customise per branch)
-- notify_parent=1, notify_student=0 for all DVA templates
INSERT IGNORE INTO `sms_template_details` (`template_id`, `branch_id`, `template_body`, `notify_student`, `notify_parent`, `dlt_template_id`) VALUES
(12, 1, 'Dear {guardian_name}, a fee of NGN {amount} ({fee_name}) has been allocated for {child_name}. Pay directly to your Dedicated Virtual Account: {dva_account} ({dva_bank}). No transfer charges.', 0, 1, ''),
(13, 1, 'Dear {guardian_name}, school resumes for {child_name} in 14 days on {resumption_date} ({term} Term). Outstanding balance: NGN {balance}. Pay via DVA: {dva_account} ({dva_bank}).', 0, 1, ''),
(14, 1, 'Dear {guardian_name}, school resumes for {child_name} in 7 days on {resumption_date} ({term} Term). Outstanding balance: NGN {balance}. Pay via DVA: {dva_account} ({dva_bank}).', 0, 1, ''),
(15, 1, 'Dear {guardian_name}, school resumes for {child_name} on {resumption_date} ({term} Term) — this Monday! Outstanding balance: NGN {balance}. Pay via DVA: {dva_account} ({dva_bank}).', 0, 1, ''),
(16, 1, 'Dear {guardian_name}, mid-term break begins {midterm_date} for {child_name} ({term} Term). Outstanding school fees: NGN {balance}. Pay via DVA: {dva_account} ({dva_bank}).', 0, 1, ''),
(17, 1, 'Dear {guardian_name}, exams begin {exam_start_date} for {child_name} ({term} Term). Clear outstanding balance of NGN {balance} before exam day. DVA: {dva_account} ({dva_bank}).', 0, 1, '');

-- ============================================================
-- Permissions — add term_dates to the permissions table
-- (skip if your system auto-discovers controllers)
-- ============================================================
INSERT IGNORE INTO `permission` (`prefix`) VALUES ('term_dates');
