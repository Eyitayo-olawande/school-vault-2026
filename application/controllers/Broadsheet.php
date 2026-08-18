<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class Broadsheet extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        if (!get_permission('broadsheet', 'is_view')) {
            access_denied('broadsheet');
        }
        $branchID  = get_loggedin_branch_id() ?: $this->application_model->get_branch_id();
        $sessionID = $this->input->get_post('session_id') ?: get_session_id();
        $classID   = $this->input->get_post('class_id');
        $sectionID = $this->input->get_post('section_id');
        $submitted = $this->input->get_post('submit');

        $rows       = [];
        $subjects   = [];
        $exams      = [];
        $students   = [];

        if ($submitted && !empty($classID) && !empty($sectionID)) {
            $exams = $this->db->select('id, name')
                ->where(['branch_id' => $branchID, 'session_id' => $sessionID])
                ->order_by('id', 'ASC')
                ->get('exam')->result_array();

            $subjects = $this->db->select('DISTINCT s.id as subject_id, s.name as subject_name')
                ->from('timetable_exam te')
                ->join('subject s', 's.id = te.subject_id')
                ->where(['te.class_id' => $classID, 'te.section_id' => $sectionID,
                         'te.session_id' => $sessionID, 'te.branch_id' => $branchID])
                ->order_by('s.name', 'ASC')
                ->get()->result_array();

            $students = $this->db->select('e.id as enroll_id, e.student_id, e.roll, CONCAT_WS(" ",s.first_name,s.last_name) as fullname, s.register_no')
                ->from('enroll e')
                ->join('student s', 's.id = e.student_id')
                ->where(['e.class_id' => $classID, 'e.section_id' => $sectionID,
                         'e.session_id' => $sessionID, 'e.branch_id' => $branchID,
                         's.active' => 1])
                ->order_by('e.roll', 'ASC')
                ->get()->result_array();

            if (!empty($students) && !empty($subjects) && !empty($exams)) {
                $examIDs    = array_column($exams, 'id');
                $studentIDs = array_column($students, 'student_id');
                $subjectIDs = array_column($subjects, 'subject_id');

                $rawMarks = $this->db->select('m.student_id, m.subject_id, m.exam_id, m.mark, m.absent, te.mark_distribution')
                    ->from('mark m')
                    ->join('timetable_exam te', 'te.exam_id=m.exam_id AND te.class_id=m.class_id AND te.section_id=m.section_id AND te.subject_id=m.subject_id', 'left')
                    ->where('m.class_id', $classID)
                    ->where('m.section_id', $sectionID)
                    ->where('m.session_id', $sessionID)
                    ->where_in('m.exam_id', $examIDs)
                    ->where_in('m.student_id', $studentIDs)
                    ->where_in('m.subject_id', $subjectIDs)
                    ->get()->result_array();

                $markMap = [];
                foreach ($rawMarks as $m) {
                    $markMap[$m['student_id']][$m['subject_id']][$m['exam_id']] = $m;
                }

                $gradeBands = $this->db->where('branch_id', $branchID)->get('grade')->result_array();

                foreach ($students as $stu) {
                    $row = [
                        'enroll_id'   => $stu['enroll_id'],
                        'student_id'  => $stu['student_id'],
                        'fullname'    => $stu['fullname'],
                        'register_no' => $stu['register_no'],
                        'roll'        => $stu['roll'],
                        'subjects'    => [],
                        'grand_obtained' => 0,
                        'grand_full'     => 0,
                    ];
                    foreach ($subjects as $sub) {
                        $subObt  = 0; $subFull = 0; $termScores = [];
                        $absent  = false;
                        foreach ($exams as $ex) {
                            $m = $markMap[$stu['student_id']][$sub['subject_id']][$ex['id']] ?? null;
                            if (!$m) { $termScores[$ex['id']] = null; continue; }
                            if ($m['absent'] === 'on') { $termScores[$ex['id']] = 'ABS'; $absent = true; continue; }
                            $dist = json_decode($m['mark_distribution'] ?? '[]', true);
                            $mkArr = json_decode($m['mark'], true);
                            $obt = 0; $full = 0;
                            foreach ((array)$dist as $i => $d) {
                                $obt  += floatval($mkArr[$i] ?? 0);
                                $full += floatval($d['full_mark'] ?? 0);
                            }
                            $termScores[$ex['id']] = $obt . '/' . $full;
                            $subObt += $obt; $subFull += $full;
                        }
                        $subPct   = ($subFull > 0) ? ($subObt * 100) / $subFull : 0;
                        $subGrade = '';
                        foreach ($gradeBands as $gb) {
                            if ($subPct >= $gb['lower_mark'] && $subPct <= $gb['upper_mark']) {
                                $subGrade = $gb['name']; break;
                            }
                        }
                        $row['subjects'][$sub['subject_id']] = [
                            'term_scores' => $termScores,
                            'total_obt'   => $subObt,
                            'total_full'  => $subFull,
                            'pct'         => $subPct,
                            'grade'       => $subGrade,
                            'absent'      => $absent,
                        ];
                        $row['grand_obtained'] += $subObt;
                        $row['grand_full']     += $subFull;
                    }
                    $row['grand_pct'] = $row['grand_full'] > 0 ? ($row['grand_obtained'] * 100) / $row['grand_full'] : 0;
                    $rows[] = $row;
                }

                usort($rows, fn($a,$b) => $b['grand_obtained'] <=> $a['grand_obtained']);
                foreach ($rows as $idx => &$r) { $r['position'] = $idx + 1; }
                unset($r);
            }
        }

        $this->data['rows']       = $rows;
        $this->data['subjects']   = $subjects;
        $this->data['exams']      = $exams;
        $this->data['students']   = $students;
        $this->data['classID']    = $classID;
        $this->data['sectionID']  = $sectionID;
        $this->data['sessionID']  = $sessionID;
        $this->data['branchID']   = $branchID;
        $this->data['branch']     = $this->db->where('id',$branchID)->get('branch')->row_array();
        $this->data['submitted']  = $submitted;
        $this->data['title']      = 'Broadsheet';
        $this->data['sub_page']   = 'broadsheet/index';
        $this->data['main_menu']  = 'exam_reports';
        $this->load->view('layout/index', $this->data);
    }
}
