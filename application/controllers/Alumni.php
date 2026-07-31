<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Alumni extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!moduleIsEnabled('alumni')) {
            access_denied();
        }
    }

    public function index()
    {
        if (!get_permission('manage_alumni', 'is_view')) {
            access_denied();
        }

        $branchID  = $this->application_model->get_branch_id();
        $sessionID = $this->input->get('session_id') ?: null;

        // All sessions for filter dropdown
        $sessions = $this->db->order_by('id', 'desc')->get('schoolyear')->result_array();

        // Alumni: enroll rows with is_alumni = 1 for this branch
        $this->db->select(
            'e.id AS enroll_id, e.student_id, e.session_id, e.class_id, e.section_id,
             e.roll, e.is_alumni,
             s.first_name, s.last_name, s.register_no, s.photo, s.email, s.mobileno,
             s.birthday, s.admission_date,
             c.name AS class_name, sec.name AS section_name,
             sy.school_year,
             CONCAT_WS(" ", p.father_name, p.mother_name) AS guardian_name'
        );
        $this->db->from('enroll e');
        $this->db->join('student s',    'e.student_id = s.id', 'inner');
        $this->db->join('class c',      'e.class_id   = c.id', 'left');
        $this->db->join('section sec',  'e.section_id = sec.id', 'left');
        $this->db->join('schoolyear sy','e.session_id  = sy.id', 'left');
        $this->db->join('parents p',    's.parent_id   = p.id', 'left');
        $this->db->where('e.branch_id',  $branchID);
        $this->db->where('e.is_alumni',  1);
        if (!empty($sessionID)) {
            $this->db->where('e.session_id', (int)$sessionID);
        }
        $this->db->order_by('sy.id DESC, s.first_name ASC');
        $alumni = $this->db->get()->result_array();

        $this->data['alumni']          = $alumni;
        $this->data['sessions']        = $sessions;
        $this->data['selected_session'] = $sessionID;
        $this->data['branch_id']       = $branchID;
        $this->data['title']           = translate('manage_alumni');
        $this->data['sub_page']        = 'alumni/index';
        $this->data['main_menu']       = 'alumni';
        $this->load->view('layout/index', $this->data);
    }

    public function event()
    {
        if (!get_permission('alumni_events', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = translate('alumni_events');
        $this->data['sub_page']  = 'alumni/events';
        $this->data['main_menu'] = 'alumni';
        $this->load->view('layout/index', $this->data);
    }
}
