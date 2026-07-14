-- DVA Communication System migration
-- Run once on production after deploying code changes.

-- 1. term_dates table (drives cron resumption/exam reminders)
CREATE TABLE IF NOT EXISTS `term_dates` (
  `id`              int(11)         NOT NULL AUTO_INCREMENT,
  `branch_id`       int(11)         NOT NULL,
  `session_id`      int(11)         NOT NULL,
  `term`            enum('1ST','2ND','3RD') NOT NULL,
  `resumption_date` date            NOT NULL,
  `midterm_date`    date            DEFAULT NULL,
  `exam_start_date` date            DEFAULT NULL,
  `created_at`      timestamp       NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_session_term` (`branch_id`,`session_id`,`term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. WhatsApp notify column on sms_template_details
ALTER TABLE `sms_template_details`
  ADD COLUMN IF NOT EXISTS `notify_whatsapp` tinyint(3) NOT NULL DEFAULT 0;

-- 3. SMS templates 12-17 (DVA-specific)
INSERT IGNORE INTO `sms_template` (`id`, `name`, `tags`) VALUES
(12, 'dva_fee_allocation',  '{guardian_name},{child_name},{term},{amount},{fee_name},{dva_account},{dva_bank}'),
(13, 'dva_resumption_14d',  '{guardian_name},{child_name},{term},{resumption_date},{balance},{dva_account},{dva_bank}'),
(14, 'dva_resumption_7d',   '{guardian_name},{child_name},{term},{resumption_date},{balance},{dva_account},{dva_bank}'),
(15, 'dva_resumption_wknd', '{guardian_name},{child_name},{term},{resumption_date},{balance},{dva_account},{dva_bank}'),
(16, 'dva_midterm',         '{guardian_name},{child_name},{term},{midterm_date},{balance},{dva_account},{dva_bank}'),
(17, 'dva_exam',            '{guardian_name},{child_name},{term},{exam_start_date},{balance},{dva_account},{dva_bank}');
