-- Scholarship feature migration
-- Run once. Safe to re-run (uses IF NOT EXISTS / INSERT IGNORE).

CREATE TABLE IF NOT EXISTS `scholarship_types` (
    `id`          int(11)      NOT NULL AUTO_INCREMENT,
    `name`        varchar(255) NOT NULL,
    `branch_id`   tinyint(11)  NOT NULL DEFAULT 0,  -- 0 = all branches
    `description` text,
    `created_at`  timestamp    NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Seed common types (branch_id=0 means available to all branches)
INSERT IGNORE INTO `scholarship_types` (`id`, `name`, `branch_id`) VALUES
(1, 'Proprietress Scholarship', 0),
(2, 'Full Scholarship',         0),
(3, 'Partial Scholarship',      0),
(4, 'Staff Ward',               0),
(5, 'Academic Excellence',      0);

CREATE TABLE IF NOT EXISTS `student_scholarship` (
    `id`                   int(11)      NOT NULL AUTO_INCREMENT,
    `student_id`           int(11)      NOT NULL,  -- stores student.id (matches fee_allocation.student_id)
    `scholarship_type_id`  int(11)      NOT NULL,
    `session_id`           int(11)      NOT NULL,
    `branch_id`            tinyint(11)  NOT NULL,
    `notes`                text,
    `created_at`           timestamp    NOT NULL DEFAULT current_timestamp(),
    `updated_at`           datetime     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `student_session` (`student_id`, `session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
