-- Prevent exact-duplicate fee allocations (same student, same fee group, same session).
-- Run AFTER fix_duplicate_term_allocations has cleaned existing duplicates.
-- Safe to re-run: IF NOT EXISTS guard.

ALTER TABLE fee_allocation
    ADD UNIQUE KEY IF NOT EXISTS uq_student_group_session (student_id, group_id, session_id);
