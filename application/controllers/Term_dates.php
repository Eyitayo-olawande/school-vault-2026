<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Term_dates extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        if (!get_permission('exam_term', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if (empty($branchID)) {
            $branchID = get_loggedin_branch_id();
        }
        $this->data['branch_id'] = $branchID;
        $q = $this->db->select('td.*, sy.school_year, b.name AS branch_name')
            ->from('term_dates td')
            ->join('schoolyear sy', 'sy.id = td.session_id')
            ->join('branch b', 'b.id = td.branch_id', 'left')
            ->order_by('sy.school_year DESC, FIELD(td.term,"1ST","2ND","3RD")');
        // Superadmin with no branch selection sees all; others see their branch only
        if (!is_superadmin_loggedin() || !empty($branchID)) {
            $q->where('td.branch_id', $branchID);
        }
        $this->data['term_dates'] = $q->get()->result_array();
        $this->data['sessions']   = $this->db->order_by('school_year DESC')->get('schoolyear')->result_array();
        $this->data['title']      = 'Term Dates';
        $this->data['sub_page']   = 'term_dates/index';
        $this->data['main_menu']  = 'settings';
        $this->load->view('layout/index', $this->data);
    }

    public function save()
    {
        if (!get_permission('exam_term', 'is_add')) {
            ajax_access_denied();
        }
        if (!$_POST) { return; }

        // For superadmin: branch_id comes from POST; fall back to session branch
        $branchID = $this->application_model->get_branch_id();
        if (empty($branchID)) {
            $branchID = get_loggedin_branch_id();
        }
        $sessionID = $this->input->post('session_id');
        $term      = $this->input->post('term');

        $this->form_validation->set_rules('session_id',      'Session',           'trim|required');
        $this->form_validation->set_rules('term',            'Term',              'trim|required|in_list[1ST,2ND,3RD]');
        $this->form_validation->set_rules('resumption_date', 'Resumption Date',   'trim|required');

        if ($this->form_validation->run() === false) {
            echo json_encode(['status' => 'fail', 'error' => $this->form_validation->error_array()]);
            return;
        }

        $data = [
            'branch_id'      => $branchID,
            'session_id'     => $sessionID,
            'term'           => $term,
            'resumption_date'=> $this->input->post('resumption_date'),
            'midterm_date'   => $this->input->post('midterm_date')   ?: null,
            'exam_start_date'=> $this->input->post('exam_start_date') ?: null,
        ];

        $existing = $this->db->where(['branch_id' => $branchID, 'session_id' => $sessionID, 'term' => $term])
                             ->get('term_dates')->row_array();
        if (!empty($existing)) {
            $this->db->where('id', $existing['id'])->update('term_dates', $data);
        } else {
            $this->db->insert('term_dates', $data);
        }

        echo json_encode(['status' => 'success', 'message' => translate('information_has_been_saved_successfully')]);
    }

    public function delete($id)
    {
        if (!get_permission('exam_term', 'is_delete')) {
            ajax_access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $this->db->where(['id' => $id, 'branch_id' => $branchID])->delete('term_dates');
        set_alert('success', translate('information_has_been_deleted_successfully'));
        redirect(base_url('term_dates'));
    }
}
