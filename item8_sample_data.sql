-- ============================================================
-- CBT Items 5, 6, 8, 13 — Sample Data for Testing
-- Run in cPanel browser terminal:
--   mysql -u clemmyschools_schoolV12 -p'HY{6z^KFpzT9y?JJ' clemmyschools_schoolV12 < ~/item8_sample_data.sql
-- ============================================================

-- Step 0: Confirm uploads/exam_media/ folder exists on server
--   (created automatically on first upload, or create via File Manager)

-- Step 1: Diagnostic — show existing exams with submissions
SELECT 'EXAMS_WITH_SUBMISSIONS' AS info,
       oe.id, oe.title, oe.branch_id, oe.class_id,
       COUNT(DISTINCT oes.student_id) AS submission_count
FROM online_exam oe
LEFT JOIN online_exam_submitted oes ON oes.online_exam_id = oe.id
GROUP BY oe.id
HAVING submission_count > 0
ORDER BY submission_count DESC
LIMIT 10;

-- Step 2: Show first available question group (for adding test questions)
SELECT 'QUESTION_GROUPS' AS info, id, group_name, branch_id
FROM question_group
LIMIT 5;

-- Step 3: Insert a test question WITH image reference
--   Replace @group_id, @class_id, @section_id, @subject_id, @branch_id below
--   with real IDs from your Step 2 output or question bank.
--
--   Also upload a test image (any JPG/PNG) via cPanel File Manager to:
--     ~/uploads/exam_media/
--   and note the filename (e.g. test_question.jpg)

SET @branch_id  = (SELECT id FROM branch LIMIT 1);
SET @class_id   = (SELECT id FROM class LIMIT 1);
SET @section_id = (SELECT id FROM section WHERE class_id = @class_id LIMIT 1);
SET @subject_id = (SELECT subject_id FROM class_subject WHERE class_id = @class_id LIMIT 1);
SET @group_id   = (SELECT id FROM question_group WHERE branch_id = @branch_id LIMIT 1);

-- Test question 1: Single choice WITH image placeholder
INSERT INTO questions
    (branch_id, class_id, section_id, subject_id, group_id, type, level, question, image, opt_1, opt_2, opt_3, opt_4, answer, mark, ca_type, created_by)
VALUES
    (@branch_id, @class_id, @section_id, @subject_id, @group_id,
     1, 1,
     'SAMPLE TEST — Which planet is closest to the Sun?',
     'uploads/exam_media/test_question.jpg',   -- upload this file via File Manager
     'Mercury', 'Venus', 'Earth', 'Mars',
     1, 2, 'GENERAL', 1);

-- Test question 2: Single choice WITHOUT image (baseline comparison)
INSERT INTO questions
    (branch_id, class_id, section_id, subject_id, group_id, type, level, question, opt_1, opt_2, opt_3, opt_4, answer, mark, ca_type, created_by)
VALUES
    (@branch_id, @class_id, @section_id, @subject_id, @group_id,
     1, 2,
     'SAMPLE TEST — How many continents are on Earth?',
     'Five', 'Six', 'Seven', 'Eight',
     3, 2, 'GENERAL', 1);

-- Test question 3: True/False
INSERT INTO questions
    (branch_id, class_id, section_id, subject_id, group_id, type, level, question, answer, mark, ca_type, created_by)
VALUES
    (@branch_id, @class_id, @section_id, @subject_id, @group_id,
     3, 1,
     'SAMPLE TEST — The Sun is a planet.',
     2, 1, 'GENERAL', 1);

SELECT 'DONE' AS status,
       'Test questions inserted. Check question bank for SAMPLE TEST entries.' AS message;

SELECT 'NEXT_STEP' AS info,
       'Upload uploads/exam_media/test_question.jpg via File Manager to see image in exam.' AS action;
