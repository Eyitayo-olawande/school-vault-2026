-- Fix: Duplicate 3rd Term Fee Allocations (2025/2026)
-- Generated: 2026-07-13
-- 25 students have two identical 3rd term fee allocations.
-- Strategy: move any payments from the duplicate (higher alloc_id) to the primary (lower alloc_id), then delete the duplicate.
-- Run this on the production DB. Safe to run multiple times (idempotent after first run).

START TRANSACTION;

-- Step 1: Move fee_payment_history from duplicate allocations to primary allocations
UPDATE fee_payment_history SET allocation_id = 11354 WHERE allocation_id = 14457;
UPDATE fee_payment_history SET allocation_id = 11356 WHERE allocation_id = 14458;
UPDATE fee_payment_history SET allocation_id = 11358 WHERE allocation_id = 14460;
UPDATE fee_payment_history SET allocation_id = 11372 WHERE allocation_id = 14470;
UPDATE fee_payment_history SET allocation_id = 11373 WHERE allocation_id = 14471;
UPDATE fee_payment_history SET allocation_id = 11375 WHERE allocation_id = 14473;
UPDATE fee_payment_history SET allocation_id = 11378 WHERE allocation_id = 14476;
UPDATE fee_payment_history SET allocation_id = 11384 WHERE allocation_id = 14480;
UPDATE fee_payment_history SET allocation_id = 11385 WHERE allocation_id = 14481;
UPDATE fee_payment_history SET allocation_id = 11391 WHERE allocation_id = 14486;

-- Step 2: Delete all duplicate fee_allocation records (no payments remain on them after Step 1)
DELETE FROM fee_allocation WHERE id IN (
    14455, 14457, 14458, 14460, 14461, 14462, 14463, 14464, 14465,
    14467, 14470, 14471, 14473, 14474, 14476, 14477, 14479, 14480,
    14481, 14483, 14484, 14485, 14486,
    12920, 13821
);

-- Verification: should return 0 rows if fix was successful
SELECT
    s.first_name, s.last_name, s.register_no,
    COUNT(DISTINCT fa.group_id) AS duplicate_group_count,
    GROUP_CONCAT(DISTINCT fg.name ORDER BY fg.id SEPARATOR ' | ') AS fee_groups
FROM fee_allocation fa
INNER JOIN fee_groups fg ON fg.id = fa.group_id
       AND fg.name LIKE '%3RD TERM%2025/2026%'
       AND fg.name NOT LIKE '%BUS%'
       AND fg.name NOT LIKE '%MEAL%'
INNER JOIN enroll e ON e.id = fa.student_id
INNER JOIN student s ON s.id = e.student_id
GROUP BY e.student_id, fa.session_id, fg.name
HAVING COUNT(fa.id) > 1;

COMMIT;
