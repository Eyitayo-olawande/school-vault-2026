<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class Transcript extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        if (!get_permission('transcript', 'is_view')) {
            access_denied('transcript');
        }
        $branchID  = get_loggedin_branch_id() ?: $this->application_model->get_branch_id();
        $studentID = $this->input->get_post('student_id');

        $data = [];
        $student = null;

        if (!empty($studentID)) {
            $student = $this->db->select('CONCAT_WS(" ",s.first_name,s.last_name) as fullname, s.register_no, s.admission_no, sc.name as category')
                ->from('student s')
                ->join('student_category sc', 'sc.id = s.category_id', 'left')
                ->where('s.id', $studentID)
                ->get()->row_array();

            $rows = $this->db->select('t.*, sy.school_year, e.name as exam_name, sub.name as subject_name, c.name as class_name')
                ->from('transcript t')
                ->join('schoolyear sy', 'sy.id = t.session_id', 'left')
                ->join('exam e',        'e.id = t.exam_id',     'left')
                ->join('subject sub',   'sub.id = t.subject_id','left')
                ->join('class c',       'c.id = t.class_id',   'left')
                ->where('t.student_id', $studentID)
                ->where('t.branch_id', $branchID)
                ->order_by('t.session_id', 'ASC')
                ->order_by('t.exam_id',    'ASC')
                ->order_by('sub.name',     'ASC')
                ->get()->result_array();

            foreach ($rows as $r) {
                $data[$r['school_year']][$r['exam_name']][] = $r;
            }
        }

        $this->data['student']        = $student;
        $this->data['studentID']      = $studentID;
        $this->data['transcriptData'] = $data;
        $this->data['branchID']       = $branchID;
        $this->data['title']          = 'Student Transcript';
        $this->data['sub_page']       = 'transcript/index';
        $this->data['main_menu']      = 'exam_reports';
        $this->load->view('layout/index', $this->data);
    }
}
