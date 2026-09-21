-- Item 8: Question images & rich media
ALTER TABLE `questions` ADD COLUMN `image` VARCHAR(255) NULL DEFAULT NULL AFTER `question`;
