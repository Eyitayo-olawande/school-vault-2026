<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class Domain extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('domain_model');
    }

    // ── Trait settings ─────────────────────────────────────────────

    public function traits($type = 'affective')
    {
        $type = in_array($type, ['affective','psychomotor']) ? $type : 'affective';
        if (!get_permission('domain_traits', 'is_view')) {
            access_denied('domain_traits');
        }
        $branchID = get_loggedin_branch_id() ?: $this->application_model->get_branch_id();

        if ($_POST) {
            if (!get_permission('domain_traits', 'is_add')) {
                access_denied('domain_traits');
            }
            $this->domain_model->saveTrait($type, $this->input->post(), $branchID);
            set_alert('success', translate('information_has_been_saved_successfully'));
            redirect("domain/traits/$type");
        }

        $this->data['type']       = $type;
        $this->data['traitList']  = $this->domain_model->getTraits($type, $branchID);
        $this->data['title']      = ucfirst($type) . ' Domain Traits';
        $this->data['sub_page']   = 'domain/traits';
        $this->data['main_menu']  = 'exam_reports';
        $this->load->view('layout/index', $this->data);
    }

    public function trait_delete($type, $traitID)
    {
        if (!get_permission('domain_traits', 'is_delete')) {
            access_denied('domain_traits');
        }
        $branchID = get_loggedin_branch_id() ?: $this->application_model->get_branch_id();
        $this->domain_model->deleteTrait($type, $traitID, $branchID);
        set_alert('success', translate('information_has_been_deleted_successfully'));
        redirect("domain/traits/$type");
    }

    // ── Rating entry ────────────────────────────────────────────────

    public function entry($type = 'affective')
    {
        $type = in_array($type, ['affective','psychomotor']) ? $type : 'affective';
        if (!get_permission('domain_entry', 'is_view')) {
            access_denied('domain_entry');
        }
        $branchID  = get_loggedin_branch_id() ?: $this->application_model->get_branch_id();
        $classID   = $this->input->get_post('class_id');
        $sectionID = $this->input->get_post('section_id');
        $examID    = $this->input->get_post('exam_id');

        if ($_POST && !empty($examID) && !empty($classID) && !empty($sectionID)) {
            if (!get_permission('domain_entry', 'is_add')) {
                ajax_access_denied();
            }
            $ratings = $this->input->post('ratings');
            if (is_array($ratings)) {
                $this->domain_model->saveRatings($type, $examID, $branchID, $ratings, get_loggedin_user_id());
                set_alert('success', translate('information_has_been_saved_successfully'));
            }
            redirect("domain/entry/$type?class_id=$classID&section_id=$sectionID&exam_id=$examID");
        }

        $students    = [];
        $traits      = [];
        $ratingMap   = [];
        $exams       = [];

        if (!empty($classID) && !empty($sectionID)) {
            $students  = $this->db->select('e.id as enroll_id, e.student_id, e.roll, CONCAT_WS(" ", s.first_name, s.last_name) as fullname, s.register_no')
                ->from('enroll e')
                ->join('student s', 's.id = e.student_id')
                ->where('e.class_id', $classID)
                ->where('e.section_id', $sectionID)
                ->where('e.branch_id', $branchID)
                ->where('e.session_id', get_session_id())
                ->where('s.active', 1)
                ->order_by('e.roll', 'ASC')
                ->get()->result_array();

            $exams = $this->db->where(['branch_id' => $branchID, 'session_id' => get_session_id()])->order_by('id', 'ASC')->get('exam')->result_array();

            if (!empty($examID)) {
                $traits    = $this->domain_model->getTraits($type, $branchID);
                $ratingMap = $this->domain_model->getRatings($type, $examID, $branchID, $classID, $sectionID);
            }
        }

        $this->data['type']      = $type;
        $this->data['students']  = $students;
        $this->data['traits']    = $traits;
        $this->data['ratingMap'] = $ratingMap;
        $this->data['exams']     = $exams;
        $this->data['classID']   = $classID;
        $this->data['sectionID'] = $sectionID;
        $this->data['examID']    = $examID;
        $this->data['branchID']  = $branchID;
        $this->data['title']     = ucfirst($type) . ' Domain Entry';
        $this->data['sub_page']  = 'domain/entry';
        $this->data['main_menu'] = 'exam_reports';
        $this->load->view('layout/index', $this->data);
    }
}
