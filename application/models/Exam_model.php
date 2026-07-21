<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Exam_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function getExamByID($id = null)
    {
        $sql = "SELECT `e`.*, `exam_term`.`name` as `term_name`, `b`.`name` as `branch_name` FROM `exam` as `e` INNER JOIN `branch` as `b` ON `b`.`id` = `e`.`branch_id` LEFT JOIN `exam_term` ON `exam_term`.`id` = `e`.`term_id` WHERE `e`.`id` = {$this->db->escape($id)}";
        return $this->db->query($sql)->row();
    }

    public function searchExamStudentsByRank($class_ID = '', $section_ID = '', $session_ID = '', $exam_ID = '', $branch_id = '')
    {
        $this->db->select('e.*,CONCAT_WS(" ",first_name, last_name) as fullname,register_no,c.name as class_name,se.name as section_name,exam_rank.rank,exam_rank.principal_comments,exam_rank.teacher_comments');
        $this->db->from('enroll as e');
        $this->db->join('student as s', 'e.student_id = s.id', 'inner');
        $this->db->join('login_credential as l', 'l.user_id = s.id and l.role = 7', 'inner');
        $this->db->join('class as c', 'e.class_id = c.id', 'left');
        $this->db->join('section as se', 'e.section_id=se.id', 'left');
        $this->db->join('exam_rank', 'exam_rank.enroll_id=e.id and exam_rank.exam_id = ' . $this->db->escape($exam_ID), 'left');
        $this->db->where('e.class_id', $class_ID);
        if (!empty($section_ID)) {
            $this->db->where('e.section_id', $section_ID);
        }
        $this->db->where('e.branch_id', $branch_id);
        $this->db->where('e.session_id', $session_ID);
        $this->db->order_by('exam_rank.rank', 'ASC');
        $this->db->where('l.active', 1);
        return $this->db->get()->result();
    }

    public function getExamList()
    {
        $this->db->select('e.*,b.name as branch_name');
        $this->db->from('exam as e');
        $this->db->join('branch as b', 'b.id = e.branch_id', 'left');
        if (!is_superadmin_loggedin()) {
            $this->db->where('e.branch_id', get_loggedin_branch_id());
        }
        $this->db->where('e.session_id', get_session_id());
        $this->db->order_by('e.id', 'asc');
        return $this->db->get()->result_array();
    }

    public function exam_save($data)
    {
        $arrayExam = array(
            'name' => $data['name'],
            'branch_id' => $this->application_model->get_branch_id(),
            'term_id' => $data['term_id'],
            'type_id' => $data['type_id'],
            'mark_distribution' => json_encode($data['mark_distribution']),
            'remark' => $data['remark'],
            'session_id' => get_session_id(),
            'status' => (isset($_POST['exam_publish']) ? 1 : 0),
            'publish_result' => 0,
        );
        if (!isset($data['exam_id'])) {
            $this->db->insert('exam', $arrayExam);
        } else {
            // $data['exam_id'] is a client-supplied form field - verify it
            // actually belongs to the caller's branch before updating it.
            $this->app_lib->check_branch_restrictions('exam', $data['exam_id'], true);
            $this->db->where('id', $data['exam_id']);
            $this->db->update('exam', $arrayExam);
        }
    }

    public function termSave($post)
    {
        $arrayTerm = array(
            'name'            => $post['term_name'],
            'branch_id'       => $this->application_model->get_branch_id(),
            'session_id'      => get_session_id(),
            'term_start_date' => !empty($post['term_start_date']) ? $post['term_start_date'] : null,
            'term_end_date'   => !empty($post['term_end_date'])   ? $post['term_end_date']   : null,
            'resumption_date' => !empty($post['resumption_date']) ? $post['resumption_date'] : null,
            'next_term_info'  => !empty($post['next_term_info'])  ? $post['next_term_info']  : null,
        );
        if (!isset($post['term_id'])) {
            $this->db->insert('exam_term', $arrayTerm);
        } else {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $post['term_id']);
            $this->db->update('exam_term', $arrayTerm);
        }
    }

    public function hallSave($post)
    {
        $arrayHall = array(
            'hall_no' => $post['hall_no'],
            'seats' => $post['no_of_seats'],
            'branch_id' => $this->application_model->get_branch_id(),
        );
        if (!isset($post['hall_id'])) {
            $this->db->insert('exam_hall', $arrayHall);
        } else {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $post['hall_id']);
            $this->db->update('exam_hall', $arrayHall);
        }
    }

    public function gradeSave($data)
    {
        $arrayData = array(
            'branch_id' => $this->application_model->get_branch_id(),
            'name' => $data['name'],
            'grade_point' => $data['grade_point'],
            'lower_mark' => $data['lower_mark'],
            'upper_mark' => $data['upper_mark'],
            'remark' => $data['remark'],
        );
        // posted all data XSS filtering
        if (!isset($data['grade_id'])) {
            $this->db->insert('grade', $arrayData);
        } else {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $data['grade_id']);
            $this->db->update('grade', $arrayData);
        }
    }

    public function get_grade($mark, $branch_id)
    {
        $this->db->where('branch_id', $branch_id);
        $query = $this->db->get('grade');
        $grades = $query->result_array();
        foreach ($grades as $row) {
            if ($mark >= $row['lower_mark'] && $mark <= $row['upper_mark']) {
                return $row;
            }
        }
    }

    public function getSubjectList($examID, $classID, $sectionID, $sessionID)
    {
        $branchID = $this->application_model->get_branch_id();
        $this->db->select('t.*,s.name as subject_name');
        $this->db->from('timetable_exam as t');
        $this->db->join('subject as s', 's.id = t.subject_id', 'inner');
        $this->db->where('t.exam_id', $examID);
        $this->db->where('t.class_id', $classID);
        $this->db->where('t.section_id', $sectionID);
        $this->db->where('t.session_id', $sessionID);
        $this->db->where('t.branch_id', $branchID);
        $this->db->group_by('t.subject_id');
        return $this->db->get()->result_array();
    }

    public function getTimetableDetail($classID, $sectionID, $examID, $subjectID)
    {
        $this->db->select('timetable_exam.mark_distribution');
        $this->db->where('class_id', $classID);
        $this->db->where('section_id', $sectionID);
        $this->db->where('exam_id', $examID);
        $this->db->where('subject_id', $subjectID);
        $this->db->where('session_id', get_session_id());
        return $this->db->get('timetable_exam')->row_array();
    }

    public function getMarkAndStudent($branchID, $classID, $sectionID, $examID, $subjectID)
    {
        $this->db->select('en.*,st.first_name,st.last_name,st.register_no,st.category_id,m.mark as get_mark,IFNULL(m.absent, 0) as get_abs,subject.name as subject_name');
        $this->db->from('enroll as en');
        $this->db->join('student as st', 'st.id = en.student_id', 'inner');
        $this->db->join('mark as m', 'm.student_id = en.student_id and m.class_id = en.class_id and m.section_id = en.section_id and m.exam_id = ' . $this->db->escape($examID) . ' and m.subject_id = ' . $this->db->escape($subjectID), 'left');
        $this->db->join('subject', 'subject.id = m.subject_id', 'left');
        $this->db->where('en.class_id', $classID);
        $this->db->where('en.section_id', $sectionID);
        $this->db->where('en.branch_id', $branchID);
        $this->db->where('en.session_id', get_session_id());
        $this->db->order_by('en.roll', 'ASC');
        return $this->db->get()->result_array();
    }

    public function log_mark_audit($action, $mark_id, array $payload, $old_mark = null, $old_absent = null)
    {
        $this->db->insert('mark_audit', [
            'mark_id'    => $mark_id,
            'student_id' => $payload['student_id'],
            'subject_id' => $payload['subject_id'],
            'exam_id'    => $payload['exam_id'],
            'class_id'   => $payload['class_id'],
            'section_id' => $payload['section_id'],
            'session_id' => $payload['session_id'],
            'branch_id'  => $payload['branch_id'],
            'action'     => $action,
            'old_mark'   => $old_mark,
            'new_mark'   => isset($payload['mark'])   ? $payload['mark']   : null,
            'old_absent' => $old_absent,
            'new_absent' => isset($payload['absent']) ? $payload['absent'] : null,
            'changed_by' => get_loggedin_id(),
        ]);
    }

    public function getStudentReportCard($studentID, $examID, $sessionID)
    {
        $result = array();
        $this->db->select('enroll.roll,enroll.id as enrollID,student.*,c.name as class_name,se.name as section_name,IFNULL(parent.father_name,"N/A") as father_name,IFNULL(parent.mother_name,"N/A") as mother_name');
        $this->db->from('enroll');
        $this->db->join('student', 'student.id = enroll.student_id', 'inner');
        $this->db->join('class as c', 'c.id = enroll.class_id', 'left');
        $this->db->join('section as se', 'se.id = enroll.section_id', 'left');
        $this->db->join('parent', 'parent.id = student.parent_id', 'left');
        $this->db->where('enroll.student_id', $studentID);
        $this->db->where('enroll.session_id', $sessionID);
        $result['student'] = $this->db->get()->row_array();

        $this->db->select('m.mark as get_mark,IFNULL(m.absent, 0) as get_abs,subject.name as subject_name, te.mark_distribution');
        $this->db->from('mark as m');
        $this->db->join('subject', 'subject.id = m.subject_id', 'left');
        $this->db->join('timetable_exam as te', 'te.exam_id = m.exam_id and te.class_id = m.class_id and te.section_id = m.section_id and te.subject_id = m.subject_id', 'left');
        $this->db->where('m.exam_id', $examID);
        $this->db->where('m.student_id', $studentID);
        $this->db->where('m.session_id', $sessionID);
        $this->db->group_by('m.subject_id');
        $this->db->order_by('subject.id', 'ASC');
        $result['exam'] = $this->db->get()->result_array();

        // Term info (term name, dates, resumption) for report card
        $examRow = $this->db->select('term_id')->where('id', $examID)->get('exam')->row();
        if ($examRow && $examRow->term_id) {
            $result['term'] = $this->db->select('name as term_name, term_start_date, term_end_date, resumption_date, next_term_info')
                ->where('id', $examRow->term_id)->get('exam_term')->row_array();
        }
        if (empty($result['term'])) {
            $result['term'] = ['term_name' => '', 'term_start_date' => null, 'term_end_date' => null, 'resumption_date' => null, 'next_term_info' => null];
        }

        return $result;
    }

    // Affective domain ratings for a student in an exam
    public function getAffectiveRatings($enrollID, $examID, $branchID)
    {
        $this->db->select('adt.name, adt.sort_order, IFNULL(sa.rating, 0) as rating');
        $this->db->from('affective_domain_type adt');
        $this->db->join('student_affective sa', 'sa.domain_type_id = adt.id AND sa.enroll_id = ' . (int)$enrollID . ' AND sa.exam_id = ' . (int)$examID, 'left');
        $this->db->where('adt.branch_id', $branchID);
        $this->db->order_by('adt.sort_order', 'ASC');
        return $this->db->get()->result_array();
    }

    // Psychomotor domain ratings for a student in an exam
    public function getPsychomotorRatings($enrollID, $examID, $branchID)
    {
        $this->db->select('pdt.name, pdt.sort_order, IFNULL(sp.rating, 0) as rating');
        $this->db->from('psychomotor_domain_type pdt');
        $this->db->join('student_psychomotor sp', 'sp.domain_type_id = pdt.id AND sp.enroll_id = ' . (int)$enrollID . ' AND sp.exam_id = ' . (int)$examID, 'left');
        $this->db->where('pdt.branch_id', $branchID);
        $this->db->order_by('pdt.sort_order', 'ASC');
        return $this->db->get()->result_array();
    }

    // All marks for a class/section/exam in one query (for position generation)
    public function getAllMarksForClass($branchID, $classID, $sectionID, $examID, $sessionID)
    {
        $this->db->select('e.id as enroll_id, e.student_id, e.roll, CONCAT(s.first_name," ",s.last_name) as student_name, s.register_no, m.subject_id, m.mark, m.absent, te.mark_distribution');
        $this->db->from('enroll e');
        $this->db->join('student s', 's.id = e.student_id', 'inner');
        $this->db->join('mark m', 'm.student_id = e.student_id AND m.class_id = ' . (int)$classID . ' AND m.section_id = ' . (int)$sectionID . ' AND m.exam_id = ' . (int)$examID . ' AND m.session_id = ' . (int)$sessionID, 'left');
        $this->db->join('timetable_exam te', 'te.subject_id = m.subject_id AND te.exam_id = ' . (int)$examID . ' AND te.class_id = ' . (int)$classID . ' AND te.section_id = ' . (int)$sectionID . ' AND te.session_id = ' . (int)$sessionID, 'left');
        $this->db->where('e.class_id', $classID);
        $this->db->where('e.section_id', $sectionID);
        $this->db->where('e.branch_id', $branchID);
        $this->db->where('e.session_id', $sessionID);
        $this->db->order_by('e.roll', 'ASC');
        return $this->db->get()->result_array();
    }

    // Compute DENSE_RANK positions in PHP and persist to exam_rank and subject_rank
    public function computeAndSavePositions($examID, $classID, $sectionID, $sessionID, $branchID)
    {
        $rows = $this->getAllMarksForClass($branchID, $classID, $sectionID, $examID, $sessionID);

        // Aggregate: studentTotals[enroll_id] = totalMarks; subjectScores[subject_id][enroll_id] = score
        $studentTotals  = [];
        $subjectScores  = [];
        $enrollMeta     = [];

        foreach ($rows as $r) {
            $enrollID = $r['enroll_id'];
            if (!isset($studentTotals[$enrollID])) {
                $studentTotals[$enrollID] = 0;
                $enrollMeta[$enrollID]    = ['student_name' => $r['student_name'], 'register_no' => $r['register_no']];
            }
            if (!$r['subject_id'] || $r['absent'] === 'on') {
                continue;
            }
            $markData  = json_decode($r['mark'], true) ?: [];
            $distData  = json_decode($r['mark_distribution'], true) ?: [];
            $obtained  = 0;
            foreach ($distData as $i => $val) {
                $obtained += isset($markData[$i]) ? (float)$markData[$i] : 0;
            }
            $studentTotals[$enrollID] += $obtained;
            if (!isset($subjectScores[$r['subject_id']])) {
                $subjectScores[$r['subject_id']] = [];
            }
            $subjectScores[$r['subject_id']][$enrollID] = $obtained;
        }

        $totalStudents = count($studentTotals);

        // DENSE_RANK helper
        $denseRank = function (array $scores) {
            arsort($scores);
            $rank = 1; $prev = null; $skip = 0; $result = [];
            foreach ($scores as $id => $score) {
                if ($prev !== null && $score < $prev) { $rank += $skip; $skip = 1; }
                else { $skip++; }
                $result[$id] = $rank;
                $prev = $score;
            }
            return $result;
        };

        $overallRanks = $denseRank($studentTotals);

        // Upsert exam_rank
        foreach ($overallRanks as $enrollID => $rank) {
            $q = $this->db->select('id')->where(['exam_id' => $examID, 'enroll_id' => $enrollID])->get('exam_rank');
            $data = ['rank' => $rank, 'total_marks' => $studentTotals[$enrollID], 'total_students' => $totalStudents];
            if ($q->num_rows() > 0) {
                $this->db->where('id', $q->row()->id)->update('exam_rank', $data);
            } else {
                $this->db->insert('exam_rank', array_merge($data, ['exam_id' => $examID, 'enroll_id' => $enrollID]));
            }
        }

        // Upsert subject_rank
        foreach ($subjectScores as $subjectID => $scores) {
            $subRanks = $denseRank($scores);
            foreach ($subRanks as $enrollID => $rank) {
                $q = $this->db->select('id')->where(['exam_id' => $examID, 'enroll_id' => $enrollID, 'subject_id' => $subjectID])->get('subject_rank');
                $data = ['rank' => $rank, 'total_marks' => $scores[$enrollID], 'branch_id' => $branchID];
                if ($q->num_rows() > 0) {
                    $this->db->where('id', $q->row()->id)->update('subject_rank', $data);
                } else {
                    $this->db->insert('subject_rank', array_merge($data, ['exam_id' => $examID, 'enroll_id' => $enrollID, 'subject_id' => $subjectID]));
                }
            }
        }

        // Mark rank as generated
        $this->db->where('id', $examID)->update('exam', ['rank_generated' => 1]);

        return ['total_students' => $totalStudents, 'ranks' => $overallRanks];
    }

}
