<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom school management system
 * @version : 7.0
 * @developed by : RamomCoder
 * @support : ramomcoder@yahoo.com
 * @author url : http://codecanyon.net/user/RamomCoder
 * @filename : Fees.php
 * @copyright : Reserved RamomCoder Team
 */

class Fees extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('fees_model');
        $this->load->model('email_model');
        $this->load->library('datatables');
        if (!moduleIsEnabled('student_accounting')) {
            access_denied();
        }

        // Build session list once for all fee-report views that need it.
        $sessions    = $this->db->order_by('school_year DESC')->get('schoolyear')->result_array();
        $sessionList = [];
        foreach ($sessions as $s) {
            $sessionList[$s['id']] = $s['school_year'];
        }
        $this->data['session_list']   = $sessionList;
        $this->data['active_session'] = get_session_id();
    }

    public function index()
    {
        redirect(base_url('fees/type'));
    }

    /* fees type form validation rules */
    protected function type_validation()
    {
        if (is_superadmin_loggedin()) {
            $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
        }
        $this->form_validation->set_rules('type_name', translate('name'), 'trim|required|callback_unique_type');
    }

    /* fees type control */
    public function type()
    {
        if (!get_permission('fees_type', 'is_view')) {
            access_denied();
        }
        if ($_POST) {
            if (!get_permission('fees_type', 'is_add')) {
                ajax_access_denied();
            }
            $this->type_validation();
            if ($this->form_validation->run() !== false) {
                $post = $this->input->post();
                $this->fees_model->typeSave($post);
                set_alert('success', translate('information_has_been_saved_successfully'));
                $array = array('status' => 'success');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
        $this->data['categorylist'] = $this->app_lib->getTable('fees_type', array('system' => 0));
        $this->data['title'] = translate('fees_type');
        $this->data['sub_page'] = 'fees/type';
        $this->data['main_menu'] = 'fees';
        $this->load->view('layout/index', $this->data);
    }

    public function type_edit($id = '')
    {
        if (!get_permission('fees_type', 'is_edit')) {
            access_denied();
        }

        if ($_POST) {
            $this->type_validation();
            if ($this->form_validation->run() !== false) {
                $post = $this->input->post();
                $this->fees_model->typeSave($post);
                set_alert('success', translate('information_has_been_updated_successfully'));
                $url = base_url('fees/type');
                $array = array('status' => 'success', 'url' => $url);
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
        $this->data['category'] = $this->app_lib->getTable('fees_type', array('t.id' => $id), true);
        $this->data['title'] = translate('fees_type');
        $this->data['sub_page'] = 'fees/type_edit';
        $this->data['main_menu'] = 'fees';
        $this->load->view('layout/index', $this->data);
    }

    public function type_delete($id = '')
    {
        if (get_permission('fees_type', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('fees_type');
        }
    }

    public function unique_type($name)
    {
        $branchID = $this->application_model->get_branch_id();
        $typeID = $this->input->post('type_id');
        if (!empty($typeID)) {
            $this->db->where_not_in('id', $typeID);
        }
        $this->db->where(array('name' => $name, 'branch_id' => $branchID));
        $uniform_row = $this->db->get('fees_type')->num_rows();
        if ($uniform_row == 0) {
            return true;
        } else {
            $this->form_validation->set_message("unique_type", translate('already_taken'));
            return false;
        }
    }

    public function group($branch_id = '')
    {
        if (!get_permission('fees_group', 'is_view')) {
            access_denied();
        }
        if ($_POST) {
            if (!get_permission('fees_group', 'is_add')) {
                ajax_access_denied();
            }
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('name', translate('group_name'), 'trim|required');
            $elems = $this->input->post('elem');
            $sel = 0;
            if (count($elems)) {
                foreach ($elems as $key => $value) {
                    if (isset($value['fees_type_id'])) {
                        $sel++;
                        $this->form_validation->set_rules('elem[' . $key . '][due_date]', translate('due_date'), 'trim|required');
                        $this->form_validation->set_rules('elem[' . $key . '][amount]', translate('amount'), 'trim|required|greater_than[0]');
                    }
                }
            }
            if ($this->form_validation->run() !== false) {
                if ($sel != 0) {
                    $arrayGroup = array(
                        'name' => $this->input->post('name'),
                        'description' => $this->input->post('description'),
                        'session_id' => get_session_id(),
                        'branch_id' => $this->application_model->get_branch_id(),
                    );
                    $this->db->insert('fee_groups', $arrayGroup);
                    $groupID = $this->db->insert_id();
                    foreach ($elems as $key => $row) {
                        if (isset($row['fees_type_id'])) {
                            $arrayData = array(
                                'fee_groups_id' => $groupID,
                                'fee_type_id' => $row['fees_type_id'],
                                'due_date' => date("Y-m-d", strtotime($row['due_date'])),
                                'amount' => $row['amount'],
                            );
                            $this->db->where(array('fee_groups_id' => $groupID, 'fee_type_id' => $row['fees_type_id']));
                            $query = $this->db->get("fee_groups_details");
                            if ($query->num_rows() == 0) {
                                $this->db->insert('fee_groups_details', $arrayData);
                            }
                        }
                    }
                    set_alert('success', translate('information_has_been_saved_successfully'));
                } else {
                    set_alert('error', 'At least one type has to be selected.');
                }
                $url = base_url('fees/group');
                $array = array('status' => 'success', 'url' => $url);
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
        $this->data['branch_id'] = $branch_id;
        $this->data['categorylist'] = $this->app_lib->getTable('fee_groups', array('t.session_id' => get_session_id(), 't.system' => 0));
        $this->data['title'] = translate('fees_group');
        $this->data['sub_page'] = 'fees/group';
        $this->data['main_menu'] = 'fees';
        $this->load->view('layout/index', $this->data);
    }

    public function group_edit($id = '')
    {
        if (!get_permission('fees_group', 'is_edit')) {
            access_denied();
        }
        if ($_POST) {
            $this->form_validation->set_rules('name', translate('group_name'), 'trim|required');
            $elems = $this->input->post('elem');
            $sel = array();
            if (count($elems)) {
                foreach ($elems as $key => $value) {
                    if (isset($value['fees_type_id'])) {
                        $sel[] = $value['fees_type_id'];
                        $this->form_validation->set_rules('elem[' . $key . '][due_date]', translate('due_date'), 'trim|required');
                        $this->form_validation->set_rules('elem[' . $key . '][amount]', translate('amount'), 'trim|required|greater_than[0]');
                    }
                }
            }
            if ($this->form_validation->run() !== false) {
                if (count($sel)) {
                    $groupID = $this->input->post('group_id');
                    $arrayGroup = array(
                        'name' => $this->input->post('name'),
                        'description' => $this->input->post('description'),
                    );
                    $this->db->where('id', $groupID);
                    $this->db->update('fee_groups', $arrayGroup);
                    foreach ($elems as $key => $row) {
                        if (isset($row['fees_type_id'])) {
                            $arrayData = array(
                                'fee_groups_id' => $groupID,
                                'fee_type_id' => $row['fees_type_id'],
                                'due_date' => date("Y-m-d", strtotime($row['due_date'])),
                                'amount' => $row['amount'],
                            );
                            $this->db->where(array('fee_groups_id' => $groupID, 'fee_type_id' => $row['fees_type_id']));
                            $query = $this->db->get("fee_groups_details");
                            if ($query->num_rows() == 0) {
                                $this->db->insert('fee_groups_details', $arrayData);
                            } else {
                                $this->db->where('id', $query->row()->id);
                                $this->db->update('fee_groups_details', $arrayData);
                            }
                        }
                    }
                    $this->db->where_not_in('fee_type_id', $sel);
                    $this->db->where('fee_groups_id', $groupID);
                    $this->db->delete('fee_groups_details');
                    set_alert('success', translate('information_has_been_updated_successfully'));
                } else {
                    set_alert('error', 'At least one type has to be selected.');
                }
                $url = base_url('fees/group');
                $array = array('status' => 'success', 'url' => $url);
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
        $group = $this->app_lib->getTable('fee_groups', array('t.id' => $id), true);
        if (empty($group)) {
            set_alert('error', translate('record_not_found'));
            redirect(base_url('fees/group'));
        }
        $this->data['group'] = $group;
        $this->data['title'] = translate('fees_group');
        $this->data['sub_page'] = 'fees/group_edit';
        $this->data['main_menu'] = 'fees';
        $this->load->view('layout/index', $this->data);
    }

    public function group_delete($id)
    {
        if (get_permission('fees_group', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('fee_groups');
            if ($this->db->affected_rows() > 0) {
                $this->db->where('fee_groups_id', $id);
                $this->db->delete('fee_groups_details');
            }
        }
    }

    /* fees type form validation rules */
    protected function fine_validation()
    {
        if (is_superadmin_loggedin()) {
            $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
        }
        $this->form_validation->set_rules('group_id', translate('group_name'), 'trim|required');
        $this->form_validation->set_rules('fine_type_id', translate('fees_type'), 'trim|required|callback_check_feetype');
        $this->form_validation->set_rules('fine_type', translate('fine_type'), 'trim|required');
        $this->form_validation->set_rules('fine_value', translate('fine') . " " . translate('value'), 'trim|required|numeric|greater_than[0]');
        $this->form_validation->set_rules('fee_frequency', translate('late_fee_frequency'), 'trim|required');
    }

    public function fine_setup()
    {
        if (!get_permission('fees_fine_setup', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if ($_POST) {
            if (!get_permission('fees_fine_setup', 'is_add')) {
                ajax_access_denied();
            }
            $this->fine_validation();
            if ($this->form_validation->run() !== false) {
                $insertData = array(
                    'group_id' => $this->input->post('group_id'),
                    'type_id' => $this->input->post('fine_type_id'),
                    'fine_value' => $this->input->post('fine_value'),
                    'fine_type' => $this->input->post('fine_type'),
                    'fee_frequency' => $this->input->post('fee_frequency'),
                    'branch_id' => $branchID,
                    'session_id' => get_session_id(),
                );
                $this->db->insert('fee_fine', $insertData);
                set_alert('success', translate('information_has_been_saved_successfully'));
                $array = array('status' => 'success');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
        $this->data['finelist'] = $this->app_lib->getTable('fee_fine');
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('fine_setup');
        $this->data['main_menu'] = 'fees';
        $this->data['sub_page'] = 'fees/fine_setup';
        $this->load->view('layout/index', $this->data);
    }

    public function fine_setup_edit($id = '')
    {
        if (!get_permission('fees_fine_setup', 'is_edit')) {
            access_denied();
        }

        if ($_POST) {
            $branchID = $this->application_model->get_branch_id();
            $this->fine_validation();
            if ($this->form_validation->run() !== false) {
                $insertData = array(
                    'group_id' => $this->input->post('group_id'),
                    'type_id' => $this->input->post('fine_type_id'),
                    'fine_value' => $this->input->post('fine_value'),
                    'fine_type' => $this->input->post('fine_type'),
                    'fee_frequency' => $this->input->post('fee_frequency'),
                    'branch_id' => $branchID,
                    'session_id' => get_session_id(),
                );
                $this->db->where('id', $id);
                $this->db->update('fee_fine', $insertData);
                set_alert('success', translate('information_has_been_updated_successfully'));
                $url = base_url('fees/fine_setup');
                $array = array('status' => 'success', 'url' => $url);
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
        $this->data['fine'] = $this->app_lib->getTable('fee_fine', array('t.id' => $id), true);
        $this->data['title'] = translate('fine_setup');
        $this->data['sub_page'] = 'fees/fine_setup_edit';
        $this->data['main_menu'] = 'fees';
        $this->load->view('layout/index', $this->data);
    }

    public function check_feetype($id)
    {
        $groupID = $this->input->post('group_id');
        $fineID = $this->input->post('fine_id');
        if (!empty($fineID)) {
            $this->db->where_not_in('id', $fineID);
        }
        $this->db->where('group_id', $groupID);
        $this->db->where('type_id', $id);
        $query = $this->db->get('fee_fine');
        if ($query->num_rows() > 0) {
            $this->form_validation->set_message("check_feetype", translate('already_taken'));
            return false;
        } else {
            return true;
        }
    }

    public function fine_delete($id)
    {
        if (get_permission('fees_fine_setup', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('fee_fine');
        }
    }

    /**
     * Check if student already has a fee allocation for a same-type fee group in the same session.
     * "Same type" means the fee group name shares the same prefix before the academic year (e.g. "3RD TERM SCHOOL FEES ").
     * BUS, MEAL, TRANSPORT variants are excluded — those may legitimately stack with school fees.
     * Returns the conflicting group name, or false if no conflict.
     */
    private function _has_same_type_allocation($studentId, $groupId, $sessionId)
    {
        // Get the name of the group being allocated
        $group = $this->db->select('name')->where('id', $groupId)->get('fee_groups')->row_array();
        if (empty($group)) return false;
        $name = $group['name'];

        // Extract prefix before the academic year "(20..." — e.g. "3RD TERM SCHOOL FEES "
        $prefix = strstr($name, '(20', true);
        if ($prefix === false) return false; // name doesn't follow expected pattern

        // Skip the check for transport/meal/bus variants (they may stack with tuition)
        $upperPrefix = strtoupper($prefix);
        foreach (['BUS', 'MEAL', 'TRANSPORT'] as $exempt) {
            if (strpos($upperPrefix, $exempt) !== false) return false;
        }

        // Also skip if the group being allocated IS a bus/meal/transport type
        // (already covered above since we check the prefix)

        // Check for any existing allocation with a different group_id but same type prefix
        $conflict = $this->db->query("
            SELECT fg.name
            FROM fee_allocation fa
            INNER JOIN fee_groups fg ON fg.id = fa.group_id
            WHERE fa.student_id = ?
              AND fa.session_id  = ?
              AND fa.group_id   != ?
              AND fg.name LIKE ?
              AND fg.name NOT LIKE '%BUS%'
              AND fg.name NOT LIKE '%MEAL%'
              AND fg.name NOT LIKE '%TRANSPORT%'
            LIMIT 1
        ", [$studentId, $sessionId, $groupId, $prefix . '%'])->row_array();

        return $conflict ? $conflict['name'] : false;
    }

    /**
     * Fire DVA fee-allocation SMS if the student has a dedicated virtual account.
     * $studentId here is enroll.id (fee_allocation.student_id = enroll.id).
     */
    private function _triggerDvaAllocationSms($enrollId, $groupId, $branchID)
    {
        $enroll = $this->db->select('student_id')->where('id', $enrollId)->get('enroll')->row_array();
        if (empty($enroll)) return;

        $hasDva = $this->db->where('user_id', $enroll['student_id'])
                           ->where('account_number IS NOT NULL', null, false)
                           ->count_all_results('dedicated_virtual_account');
        if ($hasDva == 0) return;

        $group = $this->db->select('name')->where('id', $groupId)->get('fee_groups')->row_array();
        $groupName = $group['name'] ?? '';

        // Extract term from group name, e.g. "2025/2026 3RD TERM FEES" → "3RD TERM"
        $term = '';
        if (preg_match('/\b(1ST|2ND|3RD)\s+TERM\b/i', $groupName, $m)) {
            $term = strtoupper($m[0]);
        }

        // Sum all fee lines in this group for the displayed amount
        $amount = (float) $this->db->select_sum('amount')
            ->where('fee_groups_id', $groupId)
            ->get('fee_groups_details')->row()->amount;

        $this->sms_model->dvaFeeAllocationNotice([
            'branch_id'      => $branchID,
            'enroll_id'      => $enrollId,
            'fee_group_name' => $groupName,
            'amount'         => $amount,
            'term'           => $term,
        ]);
    }

    public function allocation()
    {
        if (!get_permission('fees_allocation', 'is_add')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            $this->data['class_id'] = $this->input->post('class_id');
            $this->data['section_id'] = $this->input->post('section_id');
            $this->data['fee_group_id'] = $this->input->post('fee_group_id');
            $this->data['branch_id'] = $branchID;
            $this->data['studentlist'] = $this->fees_model->getStudentAllocationList($this->data['class_id'], $this->data['section_id'], $this->data['fee_group_id'], $branchID);
        }
        if (isset($_POST['save'])) {
            $student_array = $this->input->post('stu_operations');
            $student_ids = $this->input->post('student_ids');
            $student_sel_array = is_array($student_array) ? $student_array : [];
            $delStudent = array_diff(is_array($student_ids) ? $student_ids : [], $student_sel_array);
            $fee_groupID = $this->input->post('fee_group_id');
            $sessionId = get_session_id();
            $blocked = [];
            foreach ($student_sel_array as $key => $value) {
                $arrayData = array(
                    'student_id' => $value,
                    'group_id' => $fee_groupID,
                    'session_id' => $sessionId,
                    'branch_id' => $branchID,
                );
                $this->db->where($arrayData);
                $q = $this->db->get('fee_allocation');
                if ($q->num_rows() == 0) {
                    $conflict = $this->_has_same_type_allocation($value, $fee_groupID, $sessionId);
                    if ($conflict !== false) {
                        $blocked[] = $value; // skip — already has same-type fee
                    } else {
                        $this->db->insert('fee_allocation', $arrayData);
                        // DVA allocation notice — send only if student has a virtual account
                        $this->_triggerDvaAllocationSms($value, $fee_groupID, $branchID);
                    }
                }
            }
            if (!empty($delStudent)) {
                $this->db->where_in('student_id', $delStudent);
                $this->db->where('group_id', $fee_groupID);
                $this->db->where('session_id', $sessionId);
                $this->db->delete('fee_allocation');
            }
            if (!empty($blocked)) {
                set_alert('warning', count($blocked) . ' student(s) skipped — they already have a same-type fee allocated for this term.');
            } else {
                set_alert('success', translate('information_has_been_saved_successfully'));
            }
            redirect(base_url('fees/allocation'));
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('fees_allocation');
        $this->data['sub_page'] = 'fees/allocation';
        $this->data['main_menu'] = 'fees';
        $this->load->view('layout/index', $this->data);
    }

    public function allocation_save()
    {
        if (!get_permission('fees_allocation', 'is_add')) {
            access_denied();
        }
        if ($_POST) {
            $branchID = $this->application_model->get_branch_id();
            $student_array = $this->input->post('stu_operations');
            $student_ids = $this->input->post('student_ids');
            $student_sel_array = isset($student_array) ? $student_array : array();
            $delStudent = array_diff($student_ids, $student_sel_array);
            $fee_groupID = $this->input->post('fee_group_id');
            $sessionId = get_session_id();
            $blocked = [];
            if (!empty($student_sel_array)) {
                foreach ($student_array as $key => $value) {
                    $arrayData = array(
                        'student_id' => $value,
                        'group_id' => $fee_groupID,
                        'session_id' => $sessionId,
                        'branch_id' => $branchID,
                    );
                    $this->db->where($arrayData);
                    $q = $this->db->get('fee_allocation');
                    if ($q->num_rows() == 0) {
                        $conflict = $this->_has_same_type_allocation($value, $fee_groupID, $sessionId);
                        if ($conflict === false) {
                            $this->db->insert('fee_allocation', $arrayData);
                            $this->_triggerDvaAllocationSms($value, $fee_groupID, $branchID);
                        } else {
                            $blocked[] = $value;
                        }
                    }
                }
            }
            if (!empty($delStudent)) {
                $this->db->where_in('student_id', $delStudent);
                $this->db->where('group_id', $fee_groupID);
                $this->db->where('session_id', $sessionId);
                $this->db->delete('fee_allocation');
            }

            if (!empty($blocked)) {
                $message = count($blocked) . ' student(s) skipped — already have a same-type fee allocated for this term.';
                $array = array('status' => 'warning', 'message' => $message);
            } else {
                $message = translate('information_has_been_saved_successfully');
                $array = array('status' => 'success', 'message' => $message);
            }
            echo json_encode($array);
        }
    }

    /* student fees invoice search user interface */
    public function invoice_list()
    {
        if (!get_permission('invoice', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if ($_POST) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'trim|required');
            }
            $this->form_validation->set_rules('class_id', translate('class'), 'trim');
            $this->form_validation->set_rules('section_id', translate('section'), 'trim');
            if ($this->form_validation->run() == true) {
                $export_title = get_type_name_by_id('branch', $branchID) . ' - ' . translate('invoice_list');
                $array = array('status' => 'success', 'export_title' => $export_title,'error' => '');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail','error' => $error);
                
            }
            echo json_encode($array);
            exit();
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('payments_history');
        $this->data['sub_page'] = 'fees/invoice_list';
        $this->data['main_menu'] = 'fees';
        $this->load->view('layout/index', $this->data);
    }

    public function getInvoiceListDT()
    {
        if ($_POST) {
            if (get_permission('invoice', 'is_view')) {
                $submit_btn = $this->input->post('submit_btn');
                if (empty($submit_btn)) {
                    $json_data = array(
                        "draw"                => intval(0),
                        "recordsTotal"        => intval(0),
                        "recordsFiltered"     => intval(0),
                        "data"                => [],
                    );
                    echo json_encode($json_data);
                } else {
                    echo $this->fees_model->getInvoiceList();
                }
            }
        }
    }

    public function invoice_delete($enrollID = '')
    {
        if (!get_permission('invoice', 'is_delete')) {
            access_denied();
        }

        if (!is_superadmin_loggedin()) {
            $this->db->where('branch_id', get_loggedin_branch_id());
        }
        $this->db->where('student_id', $enrollID);
        $this->db->where('session_id', get_session_id());
        $result = $this->db->get('fee_allocation')->result_array();
        foreach ($result as $key => $value) {
            $this->db->where('allocation_id', $value['id']);
            $this->db->delete('fee_payment_history');
        }

        if (!is_superadmin_loggedin()) {
            $this->db->where('branch_id', get_loggedin_branch_id());
        }
        $this->db->where('student_id', $enrollID);
        $this->db->where('session_id', get_session_id());
        $this->db->delete('fee_allocation');
    }

    /* invoice user interface with information are controlled here */
    public function invoice($enrollID = '')
    {
        if (!get_permission('invoice', 'is_view')) {
            access_denied();
        }
        $basic = $this->fees_model->getInvoiceBasic($enrollID);
        if (empty($basic))
            redirect(base_url('dashboard'));

        if (moduleIsEnabled('transport')) {
            $this->data['transport_fees'] = $this->fees_model->getStudentTransportFees($enrollID, $basic['stoppage_point_id']);
        }
        $this->data['invoice']           = $this->fees_model->getInvoiceStatus($basic['id']);
        $this->data['scholarship']       = $this->fees_model->getStudentScholarship($basic['id']);
        $this->data['scholarship_types'] = $this->fees_model->getScholarshipTypes($basic['branch_id']);
        $this->data['basic'] = $basic;
        $this->data['title'] = translate('invoice_history');
        $this->data['main_menu'] = 'fees';
        $this->data['sub_page'] = 'fees/collect';
        $this->load->view('layout/index', $this->data);
    }

    public function invoicePrint()
    {
        if (!get_permission('invoice', 'is_view')) {
            access_denied();
        }
        if ($_POST) {
            $this->data['student_array'] = $this->input->post('student_id');
            echo $this->load->view('fees/invoicePrint', $this->data, true);
        }
    }

    public function invoicePDFdownload()
    {
        if (!get_permission('invoice', 'is_view')) {
            access_denied();
        }
        if ($_POST) {
            $this->data['student_array'] = $this->input->post('student_id');
            $html = $this->load->view('fees/invoicePDFdownload', $this->data, true);

            $this->load->library('html2pdf');
            $this->html2pdf->mpdf->WriteHTML(file_get_contents(base_url('assets/vendor/bootstrap/css/bootstrap.min.css')), 1);
            $this->html2pdf->mpdf->WriteHTML(file_get_contents(base_url('assets/css/custom-style.css')), 1);
            $this->html2pdf->mpdf->WriteHTML(file_get_contents(base_url('assets/css/ramom.css')), 1);
            $this->html2pdf->mpdf->WriteHTML($html);
            $this->html2pdf->mpdf->SetDisplayMode('fullpage');
            $this->html2pdf->mpdf->baseScript        = 1;
            $this->html2pdf->mpdf->autoScriptToLang  = true;
            $this->html2pdf->mpdf->autoLangToFont    = true;
            header("Content-Type: application/pdf");
            echo $this->html2pdf->mpdf->Output('', "S");
        }
    }

    public function pdf_sendByemail()
    {
        if (!get_permission('invoice', 'is_view')) {
            access_denied();
        }
        if ($_POST) {
            $this->data['student_array'] = [$this->input->post('enrollID')];
            $html = $this->load->view('fees/invoicePDFdownload', $this->data, true);
            $this->load->library('html2pdf');
            $this->html2pdf->mpdf->WriteHTML(file_get_contents(base_url('assets/vendor/bootstrap/css/bootstrap.min.css')), 1);
            $this->html2pdf->mpdf->WriteHTML(file_get_contents(base_url('assets/css/custom-style.css')), 1);
            $this->html2pdf->mpdf->WriteHTML(file_get_contents(base_url('assets/css/ramom.css')), 1);
            $this->html2pdf->mpdf->WriteHTML($html);
            $this->html2pdf->mpdf->SetDisplayMode('fullpage');
            $this->html2pdf->mpdf->autoScriptToLang  = true;
            $this->html2pdf->mpdf->baseScript        = 1;
            $this->html2pdf->mpdf->autoLangToFont    = true;

            $file = $this->html2pdf->mpdf->Output(time() . '.pdf', "S");
            $data['file'] = $file;
            $data['enroll_id'] = $this->input->post('enrollID');
            $response = $this->email_model->emailPDF_Fee_invoice($data);
            if ($response == true) {
                $array = array('status' => 'success', 'message' => translate('mail_sent_successfully'));
            } else {
                $array = array('status' => 'error', 'message' => translate('something_went_wrong'));

            }
            echo json_encode($array);
        }
    }

    public function due_invoice()
    {
        if (!get_permission('due_invoice', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if ($_POST) {
                if (is_superadmin_loggedin()) {
                    $this->form_validation->set_rules('branch_id', translate('branch'), 'trim|required');
                }
                $this->form_validation->set_rules('class_id', translate('class'), 'trim|required');
                $this->form_validation->set_rules('section_id', translate('section'), 'trim|required');
                $this->form_validation->set_rules('fees_type', translate('fees_type'), 'trim|required');
                if ($this->form_validation->run() == true) {
                    $export_title = get_type_name_by_id('branch', $branchID) . ' - ' . translate('due_invoice') . " " . translate('list');
                    $array = array('status' => 'success', 'export_title' => $export_title,'error' => '');
                } else {
                    $error = $this->form_validation->error_array();
                    $array = array('status' => 'fail','error' => $error);
                }
                echo json_encode($array);
                exit();
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('payments_history');
        $this->data['sub_page'] = 'fees/due_invoice';
        $this->data['main_menu'] = 'fees';
        $this->load->view('layout/index', $this->data);
    }

    public function getDueInvoiceListDT()
    {
        if ($_POST) {
            if (get_permission('due_invoice', 'is_view')) {
                $branchID = $this->application_model->get_branch_id();
                $class_id = $this->input->post('class_id');
                $section_id = $this->input->post('section_id');
                $submit_btn = $this->input->post('submit_btn');

                if (empty($submit_btn)) {
                    $json_data = array(
                        "draw"                => intval(0),
                        "recordsTotal"        => intval(0),
                        "recordsFiltered"     => intval(0),
                        "data"                => [],
                    );
                    echo json_encode($json_data);
                } else {
                    $feegroup = explode("|", $this->input->post('fees_type'));
                    $feegroup_id = $feegroup[0];
                    $fee_feetype_id = $feegroup[1];

                    $results = $this->fees_model->getDueInvoiceDT_list($class_id, $section_id, $feegroup_id, $fee_feetype_id);
                    $records = array();
                    $records = json_decode($results);
                    $dt_data = array();
                    foreach ($records->data as $key => $record) {

                        $paid = $record->total_amount + $record->total_discount;
                        $prev_due = empty($record->prev_due) ? 0 : $record->prev_due;
                        if ((float)($record->full_amount + $prev_due) <= (float)$paid) {

                        } else {
                            // actions btn
                            $actions = "";
                            if (get_permission('collect_fees', 'is_add')) {
                                $actions .= '<a href="' . base_url('fees/invoice/' . $record->enroll_id) . '" class="btn btn-default btn-circle"><i class="far fa-arrow-alt-circle-right"></i> ' . translate('collect') . '</a>';
                            }
                            if (get_permission('invoice', 'is_delete')) {
                                $actions .=  btn_delete('fees/invoice_delete/' . $record->student_id);
                            }

                            // getting fees group list
                            $feegroup = $this->fees_model->getfeeGroup($record->student_id);
                            $groupList = '';
                            foreach ($feegroup as $key => $value) {
                                $groupList .= "- " . $value['name'] . "<br>";
                            }

                            // dt-data array 
                            $row   = array();
                            $row[] = '<div class="checked-area"><div class="checkbox-replace"><label class="i-checks"><input type="checkbox" name="student_id[]" value="' . $record->enroll_id  . '"><i></i></label></div></div>';
                            $row[] = $record->first_name . ' ' . $record->last_name;
                            $row[] = $record->register_no;
                            $row[] = $record->roll;
                            $row[] = $record->mobileno;
                            $row[] = $groupList;
                            $row[] = _d($record->due_date);
                            $row[] = currencyFormat($record->full_amount);
                            $row[] = currencyFormat($record->total_amount);
                            $row[] = currencyFormat($record->total_discount);
                            $row[] = currencyFormat($record->full_amount - $paid);
                            $row[] = $actions;
                            $dt_data[] = $row;
                        }
                    }
                    $json_data = array(
                        "draw"                => intval($records->draw),
                        "recordsTotal"        => intval($records->recordsTotal),
                        "recordsFiltered"     => intval($records->recordsFiltered),
                        "data"                => $dt_data,
                    );
                    echo json_encode($json_data);
                }
            }
        }
    }

    public function fee_add()
    {
        if (!get_permission('collect_fees', 'is_add')) {
            ajax_access_denied();
        }
        $this->form_validation->set_rules('fees_type', translate('fees_type'), 'trim|required');
        $this->form_validation->set_rules('date', translate('date'), 'trim|required');
        $this->form_validation->set_rules('amount', translate('amount'), array('trim', 'required', 'numeric', 'greater_than[0]', array('deposit_verify', array($this->fees_model, 'depositAmountVerify'))));
        $this->form_validation->set_rules('discount_amount', translate('discount'), array('trim', 'numeric', array('deposit_verify', array($this->fees_model, 'depositAmountVerify'))));
        $this->form_validation->set_rules('pay_via', translate('payment_method'), 'trim|required');
        if ($this->form_validation->run() !== false) {
            $feesType = explode("|", $this->input->post('fees_type'));
            $amount = $this->input->post('amount');
            $fineAmount = $this->input->post('fine_amount');
            $discountAmount = $this->input->post('discount_amount');
            $date = $this->input->post('date');
            $payVia = $this->input->post('pay_via');
            $arrayFees = array(
                'allocation_id' => $feesType[0],
                'type_id' => $feesType[1],
                'collect_by' => get_loggedin_user_id(),
                'amount' => ($amount - $discountAmount),
                'discount' => $discountAmount,
                'fine' => $fineAmount,
                'pay_via' => $payVia,
                'remarks' => $this->input->post('remarks'),
                'date' => $date,
            );
            // transport fees data processing
            if ($feesType[0] == 'transport') {
                $arrayFees['allocation_id'] = NULL;
                $arrayFees['type_id'] = NULL;
                $arrayFees['transport_fee_details_id'] = $feesType[1];
            }
            $this->db->insert('fee_payment_history', $arrayFees);
            $payment_historyID = $this->db->insert_id();

            // transaction voucher save function
            if (isset($_POST['account_id'])) {
                $arrayTransaction = array(
                    'account_id' => $this->input->post('account_id'),
                    'amount' => ($amount + $fineAmount) - $discountAmount,
                    'date' => $date,
                );
                $this->fees_model->saveTransaction($arrayTransaction, $payment_historyID);
            }

            // send payment confirmation sms / WhatsApp
            if (isset($_POST['guardian_sms'])) {
                $arrayData = array(
                    'student_id' => $this->input->post('student_id'),
                    'amount'     => ($amount + $fineAmount) - $discountAmount,
                    'paid_date'  => _d($date),
                    'receipt_no' => $payment_historyID,
                    'fee_type'   => get_type_name_by_id('fees_type', $feesType[1], 'name'),
                );
                $this->sms_model->send_sms($arrayData, 2);
            }
            set_alert('success', translate('information_has_been_saved_successfully'));
            $array = array('status' => 'success');
        } else {
            $error = $this->form_validation->error_array();
            $array = array('status' => 'fail', 'url' => '', 'error' => $error);
        }
        echo json_encode($array);
    }

    public function getBalanceByType()
    {
        $input = $this->input->post('typeID');
        if (empty($input)) {
            $balance = 0;
            $fine = 0;
        } else {
            $feesType = explode("|", $input);
            if ($feesType[0] == 'transport') {
                $fine = $this->fees_model->transportFeeFineCalculation($feesType[1], $feesType[2]);
                $b = $this->fees_model->getTransportBalance($feesType[1]);
                $balance = $b['balance'];
                $fine = abs($fine - $b['fine']);
            } else {
                $fine = $this->fees_model->feeFineCalculation($feesType[0], $feesType[1]);
                $b = $this->fees_model->getBalance($feesType[0], $feesType[1]);
                $balance = $b['balance'];
                $fine = abs($fine - $b['fine']);
            }
        }
        echo json_encode(array('balance' => $balance, 'fine' => $fine));
    }

    public function getTypeByBranch()
    {
        $html = "";
        $branchID = $this->application_model->get_branch_id();
        $typeID = (isset($_POST['type_id']) ? $_POST['type_id'] : 0);
        if (!empty($branchID)) {
            $this->db->where('session_id', get_session_id());
            $this->db->where('branch_id', $branchID);
            $result = $this->db->get('fee_groups')->result_array();

            if (moduleIsEnabled('transport')) {
                $this->db->where('branch_id', $branchID);
                $this->db->where('session_id', get_session_id());
                $this->db->order_by('month', 'asc');
                $transport_results = $this->db->get('transport_fee_fine')->result();
            }
            if (count($result)) {
                $html .= "<option value=''>" . translate('select') . "</option>";
                foreach ($result as $row) {
                    $html .= '<optgroup label="' . $row['name'] . '">';
                    $this->db->where('fee_groups_id', $row['id']);
                    $resultdetails = $this->db->get('fee_groups_details')->result_array();
                    foreach ($resultdetails as $t) {
                        $sel = ($t['fee_groups_id'] . "|" . $t['fee_type_id'] == $typeID ? ' selected ' : '');
                        $html .= '<option value="' . $t['fee_groups_id'] . "|" . $t['fee_type_id'] . '"' . $sel . '>' . get_type_name_by_id('fees_type', $t['fee_type_id']) . '</option>';
                    }
                    $html .= '</optgroup>';
                }
                if (!empty($transport_results)) {
                    $getMonths = $this->app_lib->getMonthslist();
                    $html .= '<optgroup label="' . translate('transport_fees') . '">';
                    foreach ($transport_results as $t_key => $t_value) {
                        $sel = ("transport|" . $t_value->id == $typeID ? ' selected ' : '');
                        $html .= '<option value="' . "transport|" . $t_value->id . '"' . $sel . '>' . translate('transport_fees') ." - ". $getMonths[$t_value->month] . '</option>';
                    }
                }
            } else {
                $html .= '<option value="">' . translate('no_information_available') . '</option>';
            }
        } else {
            $html .= '<option value="">' . translate('select_branch_first') . '</option>';
        }
        echo $html;
    }

    public function getGroupByBranch()
    {
        $html = "";
        $branch_id = $this->application_model->get_branch_id();
        if (!empty($branch_id)) {
            $result = $this->db->select('id,name')
                ->where(array('branch_id' => $branch_id, 'session_id' => get_session_id(), 'system' => 0))
                ->get('fee_groups')->result_array();
            if (count($result)) {
                $html .= "<option value=''>" . translate('select') . "</option>";
                foreach ($result as $row) {
                    $html .= '<option value="' . $row['id'] . '">' . $row['name'] . '</option>';
                }
            } else {
                $html .= '<option value="">' . translate('no_information_available') . '</option>';
            }
        } else {
            $html .= '<option value="">' . translate('select_branch_first') . '</option>';
        }
        echo $html;
    }

    public function getTypeByGroup()
    {
        $html = "";
        $groupID = $this->input->post('group_id');
        $typeID = (isset($_POST['type_id']) ? $_POST['type_id'] : 0);
        if (!empty($groupID)) {
            $this->db->select('t.id,t.name');
            $this->db->from('fee_groups_details as gd');
            $this->db->join('fees_type as t', 't.id = gd.fee_type_id', 'left');
            $this->db->where('gd.fee_groups_id', $groupID);
            $result = $this->db->get()->result_array();
            if (count($result)) {
                $html .= "<option value=''>" . translate('select') . "</option>";
                foreach ($result as $row) {
                    $sel = ($row['id'] == $typeID ? 'selected' : '');
                    $html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . $row['name'] . '</option>';
                }
            } else {
                $html .= '<option value="">' . translate('no_information_available') . '</option>';
            }
        } else {
            $html .= '<option value="">' . translate('first_select_the_group') . '</option>';
        }
        echo $html;
    }

    protected function reminder_validation()
    {
        if (is_superadmin_loggedin()) {
            $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
        }
        $this->form_validation->set_rules('frequency', translate('frequency'), 'trim|required');
        $this->form_validation->set_rules('days', translate('days'), 'trim|required|numeric');
        $this->form_validation->set_rules('message', translate('message'), 'trim|required');
    }

    public function reminder()
    {
        if (!get_permission('fees_reminder', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if ($_POST) {
            if (!get_permission('fees_reminder', 'is_add')) {
                ajax_access_denied();
            }
            $this->reminder_validation();
            if ($this->form_validation->run() !== false) {
                $post = $this->input->post();
                $post['branch_id'] = $branchID;
                $this->fees_model->reminderSave($post);
                set_alert('success', translate('information_has_been_saved_successfully'));
                $array = array('status' => 'success');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
        $this->data['branch_id'] = $branchID;
        $this->data['reminderlist'] = $this->app_lib->getTable('fees_reminder');
        $this->data['title'] = translate('fees_reminder');
        $this->data['main_menu'] = 'fees';
        $this->data['sub_page'] = 'fees/reminder';
        $this->load->view('layout/index', $this->data);
    }

    public function edit_reminder($id = '')
    {
        if (!get_permission('fees_reminder', 'is_edit')) {
            ajax_access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if ($_POST) {
            $this->reminder_validation();
            if ($this->form_validation->run() !== false) {
                $post = $this->input->post();
                $post['branch_id'] = $branchID;
                $this->fees_model->reminderSave($post);
                $url = base_url('fees/reminder');
                set_alert('success', translate('information_has_been_updated_successfully'));
                $array = array('status' => 'success', 'url' => $url);
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
        $this->data['reminder'] = $this->app_lib->getTable('fees_reminder', array('t.id' => $id), true);
        $this->data['title'] = translate('fees_reminder');
        $this->data['main_menu'] = 'fees';
        $this->data['sub_page'] = 'fees/edit_reminder';
        $this->load->view('layout/index', $this->data);
    }

    public function reminder_delete($id = '')
    {
        if (get_permission('fees_reminder', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('fees_reminder');
        }
    }

    // ── Scholarship endpoints ─────────────────────────────────────────────────

    public function scholarship_assign()
    {
        if (!get_permission('collect_fees', 'is_add')) {
            ajax_access_denied();
        }
        $student_id  = (int)$this->input->post('student_id');
        $type_id     = (int)$this->input->post('scholarship_type_id');
        $notes       = $this->input->post('notes') ?? '';
        $session_id  = get_session_id();
        $branch_id   = $this->application_model->get_branch_id();

        if (!$student_id || !$type_id) {
            echo json_encode(['status' => 'fail', 'error' => 'Missing required fields.']);
            return;
        }
        $this->fees_model->assignScholarship($student_id, $type_id, $session_id, $branch_id, $notes);
        echo json_encode(['status' => 'success']);
    }

    public function scholarship_remove()
    {
        if (!get_permission('collect_fees', 'is_add')) {
            ajax_access_denied();
        }
        $student_id = (int)$this->input->post('student_id');
        $session_id = get_session_id();
        $this->fees_model->removeScholarship($student_id, $session_id);
        echo json_encode(['status' => 'success']);
    }

    public function scholarship_types()
    {
        if (!get_permission('fees_allocation', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        if ($this->input->post('action') === 'save') {
            if (!get_permission('fees_allocation', 'is_add')) {
                ajax_access_denied();
            }
            $data = [
                'id'          => (int)$this->input->post('id'),
                'name'        => $this->input->post('name'),
                'description' => $this->input->post('description'),
                'branch_id'   => is_superadmin_loggedin() ? (int)$this->input->post('branch_id') : $branchID,
            ];
            $this->fees_model->saveScholarshipType($data);
            set_alert('success', 'Scholarship type saved.');
            echo json_encode(['status' => 'success']);
            return;
        }
        if ($this->input->post('action') === 'delete') {
            if (!get_permission('fees_allocation', 'is_delete')) {
                ajax_access_denied();
            }
            $this->fees_model->deleteScholarshipType((int)$this->input->post('id'));
            echo json_encode(['status' => 'success']);
            return;
        }

        $this->data['types']     = $this->fees_model->getScholarshipTypes($branchID);
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'Scholarship Types';
        $this->data['main_menu'] = 'fees';
        $this->data['sub_page']  = 'fees/scholarship_types';
        $this->load->view('layout/index', $this->data);
    }

    public function scholarship_type_delete($id)
    {
        if (!get_permission('fees_allocation', 'is_delete')) {
            ajax_access_denied();
        }
        $this->fees_model->deleteScholarshipType((int)$id);
        set_alert('success', 'Scholarship type deleted.');
        redirect('fees/scholarship_types');
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function due_report()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if ($this->input->post('search')) {
            $this->data['class_id'] = $this->input->post('class_id');
            $this->data['section_id'] = $this->input->post('section_id');
            $this->data['term'] = $this->input->post('term') ?? '';
            $this->data['invoicelist'] = $this->fees_model->getDueReport($this->data['class_id'], $this->data['section_id'], $this->data['term']);
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('due_fees_report');
        $this->data['sub_page'] = 'fees/due_report';
        $this->data['main_menu'] = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    public function payment_history()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if ($this->input->post('search')) {
            $classID    = $this->input->post('class_id');
            $sectionID  = $this->input->post('section_id');
            $paymentVia = $this->input->post('payment_via');
            $term       = $this->input->post('term') ?: '';
            $sessionID  = (int)($this->input->post('session_id') ?: get_session_id());
            $daterange  = explode(' - ', $this->input->post('daterange'));
            $start = date("Y-m-d", strtotime($daterange[0]));
            $end   = date("Y-m-d", strtotime($daterange[1]));
            $invoicelist = $this->fees_model->getStuPaymentHistory($classID, $sectionID, $paymentVia, $start, $end, $branchID, false, $sessionID, $term);
            $this->data['invoicelist'] = $invoicelist;
            $totals = ['amount' => 0, 'discount' => 0, 'fine' => 0, 'net' => 0];
            foreach ($invoicelist as $row) {
                $totals['amount']   += $row['amount'];
                $totals['discount'] += $row['discount'];
                $totals['fine']     += $row['fine'];
                $totals['net']      += ($row['amount'] + $row['fine']) - $row['discount'];
            }
            $this->data['totals'] = $totals;
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('fees_payment_history');
        $this->data['sub_page'] = 'fees/payment_history';
        $this->data['main_menu'] = 'fees_repots';
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/daterangepicker/daterangepicker.css',
            ),
            'js' => array(
                'vendor/moment/moment.js',
                'vendor/daterangepicker/daterangepicker.js',
            ),
        );
        $this->load->view('layout/index', $this->data);
    }

    public function student_fees_report()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if ($this->input->post('search')) {
            $classID   = $this->input->post('class_id');
            $sectionID = $this->input->post('section_id');
            $term      = $this->input->post('term') ?: '';
            $sessionID = (int)($this->input->post('session_id') ?: get_session_id());
            $rows = $this->fees_model->getStudentFeesSummary($classID, $sectionID, $branchID, $sessionID, $term);
            foreach ($rows as &$r) {
                $bal = $r['expected'] - $r['net_paid'];
                if ($bal <= 0) {
                    $r['status'] = 'paid';
                } elseif ($r['net_paid'] > 0) {
                    $r['status'] = 'partial';
                } else {
                    $r['status'] = 'owing';
                }
                $r['balance'] = max(0, $bal);
            }
            unset($r);
            $summary = [
                'expected'        => array_sum(array_column($rows, 'expected')),
                'collected'       => array_sum(array_column($rows, 'net_paid')),
                'outstanding'     => array_sum(array_column($rows, 'balance')),
                'count_paid'      => count(array_filter($rows, fn($r) => $r['status'] === 'paid')),
                'count_partial'   => count(array_filter($rows, fn($r) => $r['status'] === 'partial')),
                'count_owing'     => count(array_filter($rows, fn($r) => $r['status'] === 'owing')),
            ];
            $summary['collection_rate'] = $summary['expected'] > 0
                ? round(($summary['collected'] / $summary['expected']) * 100, 1) : 0;
            $this->data['invoicelist'] = $rows;
            $this->data['summary']     = $summary;
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('student_fees_report');
        $this->data['sub_page'] = 'fees/student_fees_report';
        $this->data['main_menu'] = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    public function fine_report()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if ($this->input->post('search')) {
            $classID    = $this->input->post('class_id');
            $sectionID  = $this->input->post('section_id');
            $paymentVia = $this->input->post('payment_via');
            $sessionID  = (int)($this->input->post('session_id') ?: get_session_id());
            $daterange  = explode(' - ', $this->input->post('daterange'));
            $start = date("Y-m-d", strtotime($daterange[0]));
            $end   = date("Y-m-d", strtotime($daterange[1]));
            $this->data['invoicelist'] = $this->fees_model->getStuPaymentHistory($classID, $sectionID, $paymentVia, $start, $end, $branchID, true, $sessionID);
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('fees_fine_reports');
        $this->data['sub_page'] = 'fees/fine_report';
        $this->data['main_menu'] = 'fees_repots';
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/daterangepicker/daterangepicker.css',
            ),
            'js' => array(
                'vendor/moment/moment.js',
                'vendor/daterangepicker/daterangepicker.js',
            ),
        );
        $this->load->view('layout/index', $this->data);
    }

    public function paymentRevert()
    {
        if (!get_permission('fees_revert', 'is_delete')) {
            $array = array('status' => 'error', 'message' => translate('access_denied'));
            echo json_encode($array);
            exit();
        }
        $array = array('status' => 'success', 'message' => translate('information_deleted'));
        $ids = $this->input->post('id');
        $branchID = get_loggedin_branch_id();
        foreach ($ids as $key => $value) {

            // Verify the payment belongs to this branch (cross-campus restriction)
            $branchCheck = $this->db->select('fph.id')->from('fee_payment_history fph')
                ->join('fee_allocation fa', 'fa.id = fph.allocation_id', 'inner')
                ->where('fph.id', $value)
                ->where('fa.branch_id', $branchID)
                ->get()->row();
            if (!is_superadmin_loggedin() && empty($branchCheck)) {
                continue;
            }

            $feeDetails = $this->db->select('id,amount,fine')->where('id', $value)->get('fee_payment_history')->row();
            if (!empty($feeDetails)) {

                $amount = ($feeDetails->amount + $feeDetails->fine);

                $sql = "SELECT `transactions`.`account_id`, `transactions_links_details`.`transactions_id` FROM `transactions_links_details` INNER JOIN `transactions` ON `transactions`.`id` = `transactions_links_details`.`transactions_id` WHERE `transactions_links_details`.`payment_id` = " . $this->db->escape($value);
                $transactionsDetails = $this->db->query($sql)->row();
                if (!empty($transactionsDetails)) {

                    $sql = "UPDATE `transactions` SET `amount` = `amount` - $amount, `cr` = `cr` - $amount, `bal` = `bal` - $amount WHERE `id` = " . $this->db->escape($transactionsDetails->transactions_id);
                    $this->db->query($sql);

                    $sql = "UPDATE `accounts` SET `balance` = `balance` - $amount WHERE `id` = " . $this->db->escape($transactionsDetails->account_id);
                    $this->db->query($sql);

                    /*$this->db->set('amount', 'amount+' . $amount, false);
                    $this->db->set('cr', 'cr-' . $amount, false);
                    $this->db->set('bal', 'bal-' . $amount, false);
                    $this->db->where('id', $transactionsDetails->transactions_id);
                    $this->db->update('transactions');

                    $this->db->set('balance', 'balance-' . $amount, false);
                    $this->db->where('id', $transactionsDetails->account_id);
                    $this->db->update('accounts');*/
                }
                $this->db->where('id', $value);
                $this->db->delete('fee_payment_history');
            }
        }
        echo json_encode($array);
    }

    public function fee_fully_paid()
    {
        if (!get_permission('collect_fees', 'is_add')) {
            ajax_access_denied();
        }
        $this->form_validation->set_rules('date', translate('date'), 'trim|required');
        $this->form_validation->set_rules('pay_via', translate('payment_method'), 'trim|required');
        if ($this->form_validation->run() !== false) {
            $date = $this->input->post('date');
            $payVia = $this->input->post('pay_via');
            $invoiceID = $this->input->post('invoice_id');
            $basic = $this->fees_model->getInvoiceBasic($invoiceID);
            if (empty($basic))
                ajax_access_denied();

            $allocations = $this->fees_model->getInvoiceDetails($basic['id']);
            $totalBalance = 0;
            $totalFine = 0;
            $allPaymentIDs = [];

            foreach ($allocations as $row) {
                $fine = $this->fees_model->feeFineCalculation($row['allocation_id'], $row['fee_type_id']);
                $b = $this->fees_model->getBalance($row['allocation_id'], $row['fee_type_id']);
                $fine = abs($fine - $b['fine']);
                if ($b['balance'] != 0) {
                    $totalBalance += $b['balance'];
                    $totalFine += $fine;
                    $arrayFees = array(
                        'allocation_id' => $row['allocation_id'],
                        'type_id' => $row['fee_type_id'],
                        'collect_by' => get_loggedin_user_id(),
                        'amount' => $b['balance'],
                        'discount' => 0,
                        'fine' => $fine,
                        'pay_via' => $payVia,
                        'remarks' => $this->input->post('remarks'),
                        'date' => $date,
                    );
                    $this->db->insert('fee_payment_history', $arrayFees);
                    $allPaymentIDs[] = $this->db->insert_id();
                }
            }

            if (moduleIsEnabled('transport')) {
                $transport_fees = $this->fees_model->getStudentTransportFees($invoiceID, $basic['stoppage_point_id']);
                foreach ($transport_fees as $key => $value) {
                    $fine = $this->fees_model->transportFeeFineCalculation($value->id);
                    $b = $this->fees_model->getTransportBalance($value->id);
                    $balance = $b['balance'];
                    $fine = abs($fine - $b['fine']);

                    if ($b['balance'] != 0) {
                        $totalBalance += $b['balance'];
                        $totalFine += $fine;
                        $arrayFees = array(
                            'allocation_id' => NULL,
                            'type_id' => NULL,
                            'transport_fee_details_id' => $value->id,
                            'collect_by' => get_loggedin_user_id(),
                            'amount' => $b['balance'],
                            'discount' => 0,
                            'fine' => $fine,
                            'pay_via' => $payVia,
                            'remarks' => $this->input->post('remarks'),
                            'date' => $date,
                        );
                        $this->db->insert('fee_payment_history', $arrayFees);
                        $allPaymentIDs[] = $this->db->insert_id();
                    }
                }
            }

            // transaction voucher save function — link each payment row to the ledger entry
            if (isset($_POST['account_id'])) {
                $arrayTransaction = array(
                    'account_id' => $this->input->post('account_id'),
                    'amount' => ($totalBalance + $totalFine),
                    'date' => $date,
                );
                foreach ($allPaymentIDs as $pid) {
                    $this->fees_model->saveTransaction($arrayTransaction, $pid);
                }
            }

            // send payment confirmation sms
            if (isset($_POST['guardian_sms'])) {
                $arrayData = array(
                    'student_id' => $this->input->post('student_id'),
                    'amount' => ($totalBalance + $totalFine),
                    'paid_date' => $date,
                );
                $this->sms_model->send_sms($arrayData, 2);
            }
            set_alert('success', translate('information_has_been_saved_successfully'));
            $array = array('status' => 'success');
        } else {
            $error = $this->form_validation->error_array();
            $array = array('status' => 'fail', 'url' => '', 'error' => $error);
        }
        echo json_encode($array);
    }

    public function printFeesPaymentHistory()
    {
        if ($_POST) {
            $record = $this->input->post('data');
            $record_array = json_decode($record, true);
            $this->db->where_in('id', array_column($record_array, 'payment_id'));
            $paymentHistory = $this->db->select("sum(amount) as total_amount,sum(discount) as total_discount,sum(fine) as total_fine")->get('fee_payment_history')->row_array();
            $this->data['total_paid'] = $paymentHistory['total_amount'];
            $this->data['total_discount'] = $paymentHistory['total_discount'];
            $this->data['total_fine'] = $paymentHistory['total_fine'];
            $this->load->view('fees/printFeesPaymentHistory', $this->data);
        }
    }

    public function printFeesInvoice()
    {
        if ($_POST) {
            $record = $this->input->post('data');
            $record_array = json_decode($record);
            $total_fine = 0;
            $total_discount = 0;
            $total_paid = 0;
            $total_balance = 0;
            $total_amount = 0;
            foreach ($record_array as $key => $value) {
                if ($value->feeType == 'general') {
                    $deposit = $this->fees_model->getStudentFeeDeposit($value->allocationID, $value->feeTypeID);
                } elseif ($value->feeType == 'transport') {
                    $deposit = $this->fees_model->getStudentTransportFeeDeposit($value->trans_fd_id);
                }
                $full_amount = $value->feeAmount;
                $type_discount = $deposit['total_discount'];
                $type_fine = $deposit['total_fine'];
                $type_amount = $deposit['total_amount'];
                $balance = $full_amount - ($type_amount + $type_discount);
                $total_discount += $type_discount;
                $total_fine += $type_fine;
                $total_paid += $type_amount;
                $total_balance += $balance;
                $total_amount += $full_amount;
            }
            $this->data['total_amount'] = $total_amount;
            $this->data['total_paid'] = $total_paid;
            $this->data['total_discount'] = $total_discount;
            $this->data['total_fine'] = $total_fine;
            $this->data['total_balance'] = $total_balance;
            $this->load->view('fees/printFeesInvoice', $this->data);
        }
    }

    public function payReceiptPrint()
    {
        if ($_POST) {
            if (!get_permission('collect_fees', 'is_add')) {
                ajax_access_denied();
            }
            $studentID = $this->input->post('student_id');
            $record = $this->input->post('data');
            $this->data['studentID'] = $studentID;
            $this->data['record'] = $record;
            $this->load->view('fees/paySlipPrint', $this->data);
        }
    }

    public function selectedFeesPay()
    {
        if (!get_permission('collect_fees', 'is_add')) {
            ajax_access_denied();
        }

        $items = $this->input->post('collect_fees');
        foreach ($items as $key => $value) {
            $this->form_validation->set_rules('collect_fees[' . $key . '][date]', translate('date'), 'trim|required');
            $this->form_validation->set_rules('collect_fees[' . $key . '][pay_via]', translate('payment_method'), 'trim|required');
            $this->form_validation->set_rules('collect_fees[' . $key . '][amount]', translate('amount'), 'trim|required|numeric|greater_than[0]');
            $this->form_validation->set_rules('collect_fees[' . $key . '][discount_amount]', translate('discount'), 'trim|numeric');
            $this->form_validation->set_rules('collect_fees[' . $key . '][fine_amount]', translate('fine'), 'trim|numeric');
            if (isset($value['account_id'])) {
                $this->form_validation->set_rules('collect_fees[' . $key . '][account_id]', translate('account'), 'trim|required');
            }

            if ($value['fee_type'] == 'general') {
                $remainAmount = $this->fees_model->getBalance($value['allocation_id'], $value['type_id']);
                if ($remainAmount['balance'] < $value['amount']) {
                    $error = array('collect_fees[' . $key . '][amount]' => 'Amount cannot be greater than the remaining.');
                    $array = array('status' => 'fail', 'error' => $error);
                    echo json_encode($array);
                    exit;
                }

                $remainAmount = $this->fees_model->getBalance($value['allocation_id'], $value['type_id']);
                if ($remainAmount['balance'] < $value['discount_amount']) {
                    $error = array('collect_fees[' . $key . '][discount_amount]' => 'Amount cannot be greater than the remaining.');
                    $array = array('status' => 'fail', 'error' => $error);
                    echo json_encode($array);
                    exit;
                }
            } elseif($value['fee_type'] == 'transport') {
                // transport fees data processing
                $remainAmount = $this->fees_model->getTransportBalance($value['trans_fd_id']);
                if ($remainAmount['balance'] < $value['amount']) {
                    $error = array('collect_fees[' . $key . '][amount]' => 'Amount cannot be greater than the remaining.');
                    $array = array('status' => 'fail', 'error' => $error);
                    echo json_encode($array);
                    exit;
                }

                $remainAmount = $this->fees_model->getTransportBalance($value['trans_fd_id']);
                if ($remainAmount['balance'] < $value['discount_amount']) {
                    $error = array('collect_fees[' . $key . '][discount_amount]' => 'Amount cannot be greater than the remaining.');
                    $array = array('status' => 'fail', 'error' => $error);
                    echo json_encode($array);
                    exit;
                }  
            }
        }

        if ($this->form_validation->run() !== false) {
            $studentID = $this->input->post('student_id');
            foreach ($items as $key => $value) {
                $amount = $value['amount'];
                $fineAmount = $value['fine_amount'];
                $discountAmount = $value['discount_amount'];
                $date = $value['date'];
                $payVia = $value['pay_via'];
                $arrayFees = array(
                    'allocation_id' => $value['allocation_id'],
                    'type_id' => $value['type_id'],
                    'collect_by' => get_loggedin_user_id(),
                    'amount' => ($amount - $discountAmount),
                    'discount' => $discountAmount,
                    'fine' => $fineAmount,
                    'pay_via' => $payVia,
                    'remarks' => $value['remarks'],
                    'date' => $date,
                );
                // transport fees data processing
                if ($value['fee_type'] == 'transport') {
                    $arrayFees['allocation_id'] = NULL;
                    $arrayFees['type_id'] = NULL;
                    $arrayFees['transport_fee_details_id'] = $value['trans_fd_id'];
                }
                $this->db->insert('fee_payment_history', $arrayFees);
                $payment_historyID = $this->db->insert_id();

                // transaction voucher save function
                if (isset($value['account_id'])) {
                    $arrayTransaction = array(
                        'account_id' => $value['account_id'],
                        'amount' => ($amount + $fineAmount) - $discountAmount,
                        'date' => $date,
                    );
                    $this->fees_model->saveTransaction($arrayTransaction, $payment_historyID);
                }
                // send payment confirmation sms
                $arrayData = array(
                    'student_id' => $studentID,
                    'amount' => ($amount + $fineAmount) - $discountAmount,
                    'paid_date' => _d($date),
                );
                $this->sms_model->send_sms($arrayData, 2);
            }
            set_alert('success', translate('information_has_been_saved_successfully'));
            $array = array('status' => 'success');
        } else {
            $error = $this->form_validation->error_array();
            $array = array('status' => 'fail', 'error' => $error);
        }
        echo json_encode($array);
    }

    public function selectedFeesCollect()
    {
        if ($_POST) {
            $record = $this->input->post('data');
            $record_array = json_decode($record);
            $this->data['student_id'] = $this->input->post('student_id');
            $this->data['branch_id'] = $this->application_model->get_branch_id();
            $this->data['record_array'] = $record_array;
            $this->load->view('fees/selectedFeesCollect', $this->data);
        }
    }

    // ── Session Outstanding Report ────────────────────────────────────────────

    public function session_outstanding_report()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        if ($this->input->post('search')) {
            $sessionID = $this->input->post('session_id') ?: get_session_id();
            $term      = $this->input->post('term')       ?: '';
            $classID   = $this->input->post('class_id')   ?: '';
            $sectionID = $this->input->post('section_id') ?: '';
            $rows = $this->fees_model->getSessionOutstandingReport($branchID, $sessionID, $term, $classID, $sectionID);
            $this->data['rows']    = $rows;
            $this->data['totals']  = [
                'fee_charged'     => array_sum(array_column($rows, 'fee_charged')),
                'carried_forward' => array_sum(array_column($rows, 'carried_forward')),
                'total_paid'      => array_sum(array_column($rows, 'total_paid')),
                'outstanding'     => array_sum(array_column($rows, 'outstanding')),
            ];
            $this->data['session_id'] = $sessionID;
            $this->data['term']       = $term;
            $this->data['class_id']   = $classID;
            $this->data['section_id'] = $sectionID;
            $this->data['searched']   = true;
        } else {
            $this->data['searched']   = false;
            $this->data['rows']       = [];
            $this->data['totals']     = ['fee_charged' => 0, 'carried_forward' => 0, 'total_paid' => 0, 'outstanding' => 0];
        }

        $this->data['branch_id'] = $branchID;
        $this->data['sessions'] = $this->db->select('id, school_year')->order_by('id','DESC')->get('schoolyear')->result_array();
        $this->data['classes'] = $this->db->select('id, name')->where('branch_id', $branchID)->get('class')->result_array();
        if (is_superadmin_loggedin()) {
            $this->data['branches'] = $this->db->get('branch')->result_array();
        }
        $this->data['title']    = translate('outstanding_balances');
        $this->data['sub_page'] = 'fees/session_outstanding_report';
        $this->data['main_menu'] = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    // ── Section-wise Fees Summary ─────────────────────────────────────────────

    public function section_fees_summary()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        if ($this->input->post('search')) {
            $sessionID      = $this->input->post('session_id')       ?: get_session_id();
            $term           = $this->input->post('term')             ?: '';
            $dateA          = $this->input->post('date_a')           ?: date('Y-m-d');
            $dateB          = $this->input->post('date_b')           ?: date('Y-m-d');
            $includePrevDue = (int) ($this->input->post('include_prev_due') ?: 0);
            // The view iterates $rows and reads $totals; both must be set or the
            // entire results block is skipped and the page renders filters only.
            $rows = $this->fees_model->getSectionFeesSummary($sessionID, $branchID, $term, $dateA, $dateB, $includePrevDue);

            $totals = ['enrolled' => 0, 'expected' => 0, 'paid_a' => 0, 'balance_a' => 0, 'paid_b' => 0, 'balance_b' => 0];
            foreach ($rows as $r) {
                $totals['enrolled']  += (int) $r['total_enrolled'];
                $totals['expected']  += (float) $r['total_expected'];
                $totals['paid_a']    += (float) $r['paid_a'];
                $totals['balance_a'] += (float) $r['balance_a'];
                $totals['paid_b']    += (float) $r['paid_b'];
                $totals['balance_b'] += (float) $r['balance_b'];
            }

            $this->data['rows']            = $rows;
            $this->data['totals']          = $totals;
            $this->data['session_id']      = $sessionID;
            $this->data['term']            = $term;
            $this->data['date_a']          = $dateA;
            $this->data['date_b']          = $dateB;
            $this->data['include_prev_due'] = $includePrevDue;
        }

        $this->data['branch_id'] = $branchID;
        $this->data['sessions'] = $this->db->select('id, school_year')->order_by('id','DESC')->get('schoolyear')->result_array();
        if (is_superadmin_loggedin()) {
            $this->data['branches'] = $this->db->get('branch')->result_array();
        }
        $this->data['title']    = translate('section_fees_summary');
        $this->data['sub_page'] = 'fees/section_fees_summary';
        $this->data['main_menu'] = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    // ── DVA Account Sync ──────────────────────────────────────────────────────

    public function dva_sync()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $action   = $this->input->post('action') ?: $this->input->get('action');

        if ($action === 'fetch') {
            // Pull all dedicated accounts from Paystack API (paginated).
            $config    = $this->get_payment_config();
            $secretKey = $config['paystack_secret_key'] ?? '';
            if (empty($secretKey)) {
                echo json_encode(['status' => 'error', 'message' => 'Paystack secret key not configured.']);
                return;
            }
            $allAccounts = [];
            $page = 1;
            do {
                $url = "https://api.paystack.co/dedicated_account?perPage=100&page={$page}";
                $ch  = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secretKey]);
                $raw  = curl_exec($ch);
                $err  = curl_error($ch);
                curl_close($ch);
                if ($err || !$raw) {
                    echo json_encode(['status' => 'error', 'message' => 'Paystack API error: ' . $err]);
                    return;
                }
                $resp = json_decode($raw, true);
                if (empty($resp['status'])) {
                    echo json_encode(['status' => 'error', 'message' => $resp['message'] ?? 'API returned error.']);
                    return;
                }
                $batch = $resp['data'] ?? [];
                $allAccounts = array_merge($allAccounts, $batch);
                $meta  = $resp['meta'] ?? [];
                $total = (int)($meta['total'] ?? 0);
                $page++;
            } while (count($allAccounts) < $total && count($batch) === 100);

            // Cross-reference against local students to find unsynced ones.
            $unsynced = $this->_dva_unsynced_students($branchID, $allAccounts);
            echo json_encode(['status' => 'ok', 'total_fetched' => count($allAccounts), 'unsynced' => $unsynced]);
            return;
        }

        if ($action === 'import') {
            $rows    = $this->input->post('rows');
            $imported = 0;
            $skipped  = 0;
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $userID = (int)($row['user_id'] ?? 0);
                    if (!$userID) { $skipped++; continue; }
                    // Skip if already exists for this user.
                    $exists = $this->db->where('user_id', $userID)->count_all_results('dedicated_virtual_account');
                    if ($exists) { $skipped++; continue; }
                    $this->db->insert('dedicated_virtual_account', [
                        'user_id'                  => $userID,
                        'customer_id'              => (int)($row['customer_id']      ?? 0),
                        'customer_code'            => $row['customer_code']           ?? '',
                        'dedicated_account_bank'   => $row['bank_name']              ?? '',
                        'dedicated_account_bank_id'=> $row['bank_id']               ?? '',
                        'account_name'             => $row['account_name']           ?? '',
                        'account_number'           => $row['account_number']         ?? '',
                        'assigned_status'          => $row['assigned_status']        ?? 'assigned',
                        'currency'                 => $row['currency']               ?? 'NGN',
                        'active'                   => 1,
                        'account_id'               => (int)($row['account_id']       ?? 0),
                        'created_at'               => date('Y-m-d H:i:s'),
                        'assignee_type'            => $row['assignee_type']          ?? '',
                        'expired'                  => 0,
                        'account_type'             => $row['account_type']           ?? '',
                        'assigned_at'              => date('Y-m-d H:i:s'),
                        'expired_at'               => '0000-00-00 00:00:00',
                        'assignment_expires_at'    => null,
                        'raw_response'             => $row['raw_response']           ?? '',
                    ]);
                    $imported++;
                }
            }
            echo json_encode(['status' => 'ok', 'imported' => $imported, 'skipped' => $skipped]);
            return;
        }

        // Default: render the page.
        $this->data['branch_id']  = $branchID;
        $this->data['csrf_data']  = json_encode(csrf_jquery_token());
        $this->data['title']      = 'DVA Account Sync';
        $this->data['sub_page']   = 'fees/dva_sync';
        $this->data['main_menu']  = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    /**
     * Returns Paystack DVA accounts that don't yet have a matching row in
     * dedicated_virtual_account for the given branch, matched by student email.
     */
    private function _dva_unsynced_students($branchID, array $allAccounts)
    {
        if (empty($allAccounts)) return [];

        // Build email→student map for this branch.
        $students = $this->db
            ->select('s.id AS student_id, s.email, s.first_name, s.last_name, s.register_no')
            ->from('student s')
            ->join('enroll e', 'e.student_id = s.id', 'inner')
            ->where('e.branch_id', $branchID)
            ->group_by('s.id')
            ->get()->result_array();
        $emailMap = [];
        foreach ($students as $s) {
            if (!empty($s['email'])) {
                $emailMap[strtolower(trim($s['email']))] = $s;
            }
        }

        // Already-synced user IDs.
        $synced = $this->db->select('user_id')->get('dedicated_virtual_account')->result_array();
        $syncedIDs = array_flip(array_column($synced, 'user_id'));

        $unsynced = [];
        foreach ($allAccounts as $acct) {
            $cEmail = strtolower(trim($acct['customer']['email'] ?? ''));
            if (!isset($emailMap[$cEmail])) continue;
            $stu = $emailMap[$cEmail];
            if (isset($syncedIDs[(int)$stu['student_id']])) continue;
            $bank = $acct['bank'] ?? [];
            $unsynced[] = [
                'user_id'        => $stu['student_id'],
                'register_no'    => $stu['register_no'],
                'name'           => $stu['first_name'] . ' ' . $stu['last_name'],
                'email'          => $cEmail,
                'account_number' => $acct['account_number'] ?? '',
                'account_name'   => $acct['account_name']   ?? '',
                'bank_name'      => $bank['name']            ?? '',
                'bank_id'        => (string)($bank['id']    ?? ''),
                'customer_id'    => $acct['customer']['id'] ?? 0,
                'customer_code'  => $acct['customer']['customer_code'] ?? '',
                'account_id'     => $acct['id']              ?? 0,
                'assigned_status'=> $acct['assigned']        ? 'assigned' : 'unassigned',
                'currency'       => $acct['currency']        ?? 'NGN',
                'assignee_type'  => $acct['assignee_type']   ?? '',
                'account_type'   => $acct['account_type']    ?? '',
                'raw_response'   => json_encode($acct),
            ];
        }
        return $unsynced;
    }

    // ── DVA Transaction History ───────────────────────────────────────────────

    public function dva_transaction_history()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        if ($this->input->post('search')) {
            $start = $this->input->post('start_date') ?: date('Y-m-01');
            $end   = $this->input->post('end_date')   ?: date('Y-m-d');
            $sql = "
                SELECT pl.id, pl.reference, pl.paid_date, pl.amount, pl.customer_email,
                       pl.authorization_bank, pl.authorization_sender_name, pl.authorization_narration,
                       pl.status, s.first_name, s.last_name, s.register_no,
                       dva.account_number, dva.dedicated_account_bank
                FROM paystack_logs pl
                LEFT JOIN dedicated_virtual_account dva ON dva.customer_id = pl.customer_id
                LEFT JOIN student s ON s.id = dva.user_id
                LEFT JOIN enroll  e ON e.student_id = s.id AND e.branch_id = " . (int)$branchID . "
                WHERE pl.paid_date BETWEEN " . $this->db->escape($start . ' 00:00:00') . "
                                        AND " . $this->db->escape($end   . ' 23:59:59') . "
                  AND (s.id IS NULL OR e.branch_id = " . (int)$branchID . ")
                ORDER BY pl.paid_date DESC
            ";
            $this->data['transactions'] = $this->db->query($sql)->result_array();
            $this->data['start_date']   = $start;
            $this->data['end_date']     = $end;
        }

        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'DVA Transaction History';
        $this->data['sub_page']  = 'fees/dva_transaction_history';
        $this->data['main_menu'] = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    // ── Financial Overview ────────────────────────────────────────────────────

    public function financial_overview()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }

        // --- Filters ---
        $branchID  = (int)($this->input->get('branch_id') ?: ($this->application_model->get_branch_id() ?: get_loggedin_branch_id() ?: 1));
        $sessionID = (int)($this->input->get('session_id') ?: get_session_id());
        $term      = $this->input->get('term', true) ?: '';
        $daterange = $this->input->get('daterange', true) ?: '';

        // Parse "YYYY-MM-DD - YYYY-MM-DD" or "MM/DD/YYYY - MM/DD/YYYY"
        $dateFrom = null;
        $dateTo   = null;
        if ($daterange && strpos($daterange, ' - ') !== false) {
            $parts = explode(' - ', $daterange, 2);
            $df    = date('Y-m-d', strtotime(trim($parts[0])));
            $dt    = date('Y-m-d', strtotime(trim($parts[1])));
            if ($df && $dt && $df !== '1970-01-01' && $dt !== '1970-01-01') {
                $dateFrom = $df;
                $dateTo   = $dt;
            }
        }
        $dateFiltered = ($dateFrom !== null && $dateTo !== null);

        // --- Session list ---
        $sessions    = $this->db->order_by('school_year DESC')->get('schoolyear')->result_array();
        $sessionList = [];
        foreach ($sessions as $s) {
            $sessionList[$s['id']] = $s['school_year'];
        }

        // --- SQL fragments ---
        $bW    = "fa.session_id = {$sessionID} AND e.branch_id = {$branchID}";
        $termW = $term ? " AND fg.name LIKE '%" . $this->db->escape_like_str($term) . "%'" : '';
        $dateW = $dateFiltered
            ? " AND fph.date BETWEEN " . $this->db->escape($dateFrom) . " AND " . $this->db->escape($dateTo)
            : '';

        // --- Summary: expected, collected (full term), outstanding ---
        $summaryRow = $this->db->query("
            SELECT
                IFNULL(SUM(fgd.amount + fa.prev_due), 0)                         AS expected,
                IFNULL(SUM(fph.amount + fph.discount), 0)                        AS collected_full,
                IFNULL(SUM(fgd.amount + fa.prev_due), 0)
                  - IFNULL(SUM(fph.amount + fph.discount), 0)                    AS outstanding
            FROM fee_allocation fa
            INNER JOIN enroll e     ON e.id  = fa.student_id
            LEFT  JOIN fee_groups fg ON fg.id = fa.group_id
            LEFT  JOIN fee_groups_details fgd ON fgd.fee_groups_id = fa.group_id
            LEFT  JOIN fee_payment_history fph ON fph.allocation_id = fa.id AND fph.status = 'paid'
            WHERE {$bW}{$termW}
        ")->row_array();

        $expected      = (float)$summaryRow['expected'];
        $collectedFull = (float)$summaryRow['collected_full'];
        $outstanding   = (float)$summaryRow['outstanding'];
        $collectionRate = $expected > 0 ? round(($collectedFull / $expected) * 100, 2) : 0;

        // Date-filtered collection amount (only differs from full when daterange set)
        if ($dateFiltered) {
            $collectedRow = $this->db->query("
                SELECT IFNULL(SUM(fph.amount + fph.discount), 0) AS collected
                FROM fee_payment_history fph
                INNER JOIN fee_allocation fa ON fa.id = fph.allocation_id
                INNER JOIN enroll e           ON e.id  = fa.student_id
                LEFT  JOIN fee_groups fg      ON fg.id = fa.group_id
                WHERE {$bW}{$termW}{$dateW} AND fph.status = 'paid'
            ")->row_array();
            $collected = (float)$collectedRow['collected'];
        } else {
            $collected = $collectedFull;
        }

        // --- Open exceptions: students whose balance > 0 ---
        $openExceptions = (int)$this->db->query("
            SELECT COUNT(*) AS cnt FROM (
                SELECT fa.student_id
                FROM fee_allocation fa
                INNER JOIN enroll e     ON e.id  = fa.student_id
                LEFT  JOIN fee_groups fg ON fg.id = fa.group_id
                LEFT  JOIN fee_groups_details fgd ON fgd.fee_groups_id = fa.group_id
                LEFT  JOIN fee_payment_history fph ON fph.allocation_id = fa.id AND fph.status = 'paid'
                WHERE {$bW}{$termW}
                GROUP BY fa.student_id
                HAVING IFNULL(SUM(fgd.amount + fa.prev_due), 0)
                         - IFNULL(SUM(fph.amount + fph.discount), 0) > 0
            ) sub
        ")->row()->cnt;

        // --- Top 10 students by outstanding balance ---
        $topOwed = $this->db->query("
            SELECT e.student_id,
                   CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                   s.register_no, c.name AS class_name,
                   IFNULL(SUM(fgd.amount + fa.prev_due), 0)
                     - IFNULL(SUM(fph.amount + fph.discount), 0) AS balance
            FROM fee_allocation fa
            INNER JOIN enroll e     ON e.id  = fa.student_id
            INNER JOIN student s    ON s.id  = e.student_id
            INNER JOIN class c      ON c.id  = e.class_id
            LEFT  JOIN fee_groups fg ON fg.id = fa.group_id
            LEFT  JOIN fee_groups_details fgd ON fgd.fee_groups_id = fa.group_id
            LEFT  JOIN fee_payment_history fph ON fph.allocation_id = fa.id AND fph.status = 'paid'
            WHERE {$bW}{$termW}
            GROUP BY fa.student_id
            HAVING balance > 0
            ORDER BY balance DESC
            LIMIT 10
        ")->result_array();

        // --- Recent 10 payments ---
        $recentPayments = $this->db->query("
            SELECT fph.date,
                   CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                   s.register_no, ft.name AS type_name,
                   fph.amount + fph.discount AS amount
            FROM fee_payment_history fph
            INNER JOIN fee_allocation fa ON fa.id  = fph.allocation_id
            INNER JOIN enroll e          ON e.id   = fa.student_id
            INNER JOIN student s         ON s.id   = e.student_id
            INNER JOIN fees_type ft      ON ft.id  = fph.type_id
            LEFT  JOIN fee_groups fg     ON fg.id  = fa.group_id
            WHERE {$bW}{$termW}{$dateW} AND fph.status = 'paid'
            ORDER BY fph.date DESC, fph.id DESC
            LIMIT 10
        ")->result_array();

        // --- Monthly collections ---
        $monthlyData = $this->db->query("
            SELECT DATE_FORMAT(fph.date, '%b %Y') AS month,
                   SUM(fph.amount + fph.discount)  AS net
            FROM fee_payment_history fph
            INNER JOIN fee_allocation fa ON fa.id = fph.allocation_id
            INNER JOIN enroll e          ON e.id  = fa.student_id
            LEFT  JOIN fee_groups fg     ON fg.id = fa.group_id
            WHERE {$bW}{$termW}{$dateW} AND fph.status = 'paid'
            GROUP BY DATE_FORMAT(fph.date, '%Y-%m')
            ORDER BY DATE_FORMAT(fph.date, '%Y-%m') ASC
        ")->result_array();

        $this->data['overview'] = [
            'expected'        => $expected,
            'collected'       => $collected,
            'collected_full'  => $collectedFull,
            'outstanding'     => $outstanding,
            'collection_rate' => $collectionRate,
            'open_exceptions' => $openExceptions,
            'date_filtered'   => $dateFiltered,
            'top_owed'        => $topOwed,
            'recent_payments' => $recentPayments,
            'monthly'         => $monthlyData,
        ];
        $this->data['session_id']   = $sessionID;
        $this->data['session_list'] = $sessionList;
        $this->data['term']         = $term;
        $this->data['daterange']    = $daterange;
        $this->data['branch_id']    = $branchID;
        $this->data['title']        = 'Financial Overview';
        $this->data['sub_page']     = 'fees/financial_overview';
        $this->data['main_menu']    = 'fees_repots';
        $this->data['headerelements'] = [
            'js' => ['vendor/chartjs/chart.min.js'],
        ];
        $this->load->view('layout/index', $this->data);
    }

    // ── Financial Exceptions ──────────────────────────────────────────────────

    public function financial_exceptions()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID  = $this->application_model->get_branch_id() ?: get_loggedin_branch_id() ?: 1;
        $sessionID = get_session_id();

        // 1. Payments that exceed the invoiced amount (overpayments).
        $overSql = "
            SELECT s.first_name, s.last_name, s.register_no, c.name AS class_name,
                   SUM(fgd.amount + fa.prev_due)          AS invoiced,
                   SUM(fph.amount + fph.discount)         AS paid,
                   SUM(fph.amount + fph.discount)
                     - SUM(fgd.amount + fa.prev_due)      AS overpaid
            FROM fee_allocation fa
            INNER JOIN enroll e  ON e.id  = fa.student_id
            INNER JOIN student s ON s.id  = e.student_id
            INNER JOIN class   c ON c.id  = e.class_id
            LEFT JOIN fee_groups_details  fgd ON fgd.fee_groups_id = fa.group_id
            LEFT JOIN fee_payment_history fph ON fph.allocation_id = fa.id
            WHERE fa.session_id = {$sessionID} AND e.branch_id = {$branchID}
            GROUP BY fa.student_id
            HAVING overpaid > 0
            ORDER BY overpaid DESC
        ";
        $overpayments = $this->db->query($overSql)->result_array();

        // 2. Allocations with no payment at all (untouched invoices).
        $unpaidSql = "
            SELECT s.first_name, s.last_name, s.register_no, c.name AS class_name,
                   sec.name AS section_name,
                   SUM(fgd.amount + fa.prev_due) AS invoiced
            FROM fee_allocation fa
            INNER JOIN enroll  e   ON e.id   = fa.student_id
            INNER JOIN student s   ON s.id   = e.student_id
            INNER JOIN class   c   ON c.id   = e.class_id
            INNER JOIN section sec ON sec.id  = e.section_id
            LEFT JOIN fee_groups_details fgd ON fgd.fee_groups_id = fa.group_id
            WHERE fa.session_id = {$sessionID} AND e.branch_id = {$branchID}
              AND fa.id NOT IN (SELECT DISTINCT allocation_id FROM fee_payment_history)
            GROUP BY fa.student_id
            ORDER BY c.name, s.first_name
        ";
        $unpaidAllocations = $this->db->query($unpaidSql)->result_array();

        // 3. DVA payments in paystack_logs not matched to a fee_payment_history row.
        // Remarks format: 'Fees deposits online via DVA Wallet: {pl.id} for allocation {fa.id} at ...'
        $unmatchedSql = "
            SELECT pl.reference, pl.paid_date, pl.amount, pl.customer_email,
                   pl.authorization_sender_name, pl.status
            FROM paystack_logs pl
            WHERE pl.status = 'success'
            AND pl.id NOT IN (
                SELECT DISTINCT CAST(
                    SUBSTRING_INDEX(SUBSTRING_INDEX(remarks, 'DVA Wallet: ', -1), ' for ', 1)
                    AS UNSIGNED)
                FROM fee_payment_history
                WHERE remarks LIKE '%DVA Wallet%'
            )
            ORDER BY pl.paid_date DESC
            LIMIT 200
        ";
        $unmatchedDVA = $this->db->query($unmatchedSql)->result_array();

        $this->data['overpayments']      = $overpayments;
        $this->data['unpaid_allocations'] = $unpaidAllocations;
        $this->data['unmatched_dva']     = $unmatchedDVA;
        $this->data['branch_id']         = $branchID;
        $this->data['title']             = 'Financial Exceptions';
        $this->data['sub_page']          = 'fees/financial_exceptions';
        $this->data['main_menu']         = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    // ── Student Ledger ────────────────────────────────────────────────────────

    public function student_ledger()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        if ($this->input->post('search')) {
            $studentID = (int)$this->input->post('student_id');
            $sessionID = (int)($this->input->post('session_id') ?: get_session_id());

            if ($studentID) {
                // Verify student belongs to this branch.
                if (!is_superadmin_loggedin()) {
                    $check = $this->db->where('student_id', $studentID)
                        ->where('branch_id', $branchID)
                        ->count_all_results('enroll');
                    if (!$check) { access_denied(); }
                }

                // Per-fee-type breakdown ($details) — matches view columns: name, due_date, amount, paid, discount, fine, balance
                $detailsSql = "
                    SELECT ft.name,
                           fgd.due_date,
                           CASE WHEN ft.system = 1 THEN fa.prev_due ELSE fgd.amount END AS amount,
                           IFNULL(SUM(fph.amount),    0) AS paid,
                           IFNULL(SUM(fph.discount),  0) AS discount,
                           IFNULL(SUM(fph.fine),      0) AS fine,
                           CASE WHEN ft.system = 1 THEN fa.prev_due ELSE fgd.amount END
                             - IFNULL(SUM(fph.amount),   0)
                             - IFNULL(SUM(fph.discount), 0) AS balance
                    FROM fee_allocation fa
                    INNER JOIN fee_groups_details fgd ON fgd.fee_groups_id = fa.group_id
                    INNER JOIN fees_type ft ON ft.id = fgd.fee_type_id
                    LEFT JOIN fee_payment_history fph ON fph.allocation_id = fa.id AND fph.type_id = fgd.fee_type_id
                    WHERE fa.student_id = (SELECT id FROM enroll WHERE student_id = {$studentID} LIMIT 1)
                      AND fa.session_id = {$sessionID}
                    GROUP BY fa.id, fgd.fee_type_id
                    ORDER BY fa.group_id, ft.name
                ";
                $details = $this->db->query($detailsSql)->result_array();

                // Payment transaction history ($transactions) — matches view columns
                $paymentSql = "
                    SELECT fph.id AS receipt_no, fph.date,
                           ft.name AS type_name,
                           pt.name AS payment_method, fph.pay_via,
                           fph.amount, fph.discount, fph.fine,
                           fph.collect_by,
                           IFNULL(fph.status, 'paid') AS status
                    FROM fee_payment_history fph
                    INNER JOIN fee_allocation fa ON fa.id = fph.allocation_id
                    LEFT JOIN fees_type ft ON ft.id = fph.type_id
                    LEFT JOIN payment_types pt ON pt.id = fph.pay_via
                    WHERE fa.student_id = (SELECT id FROM enroll WHERE student_id = {$studentID} LIMIT 1)
                      AND fa.session_id = {$sessionID}
                    ORDER BY fph.date ASC, fph.id ASC
                ";
                $transactions = $this->db->query($paymentSql)->result_array();

                // Fetch enroll.id to call getInvoiceBasic (which expects enroll.id, not student.id)
                $enrollRow = $this->db->select('id')->where('student_id', $studentID)->get('enroll')->row_array();
                $enrollID  = $enrollRow ? $enrollRow['id'] : null;
                $basic     = $enrollID ? $this->fees_model->getInvoiceBasic($enrollID) : [];
                $sessionLabel = $this->db->select('school_year')->where('id', $sessionID)->get('schoolyear')->row_array();

                $dva = $this->db->where('user_id', $studentID)->where('active', 1)
                               ->get('dedicated_virtual_account')->row_array();

                $this->data['basic']        = $basic;
                $this->data['details']      = $details;
                $this->data['transactions'] = $transactions;
                $this->data['dva']          = $dva;
                $this->data['session_id']   = $sessionID;
                $this->data['student_id']   = $studentID;
                $this->data['session_label'] = $sessionLabel['school_year'] ?? '';
            }
        }

        $this->data['branch_id'] = $branchID;
        $this->data['sessions'] = $this->db->select('id, school_year')->order_by('id','DESC')->get('schoolyear')->result_array();
        $this->data['classes'] = $this->db->select('id, name')->where('branch_id', $branchID)->get('class')->result_array();
        if (is_superadmin_loggedin()) {
            $this->data['branches'] = $this->db->get('branch')->result_array();
        }
        $this->data['title']    = 'Student Ledger';
        $this->data['sub_page'] = 'fees/student_ledger';
        $this->data['main_menu'] = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    // ── Wallet Reconciliation ─────────────────────────────────────────────────

    public function wallet_reconciliation()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID  = $this->application_model->get_branch_id() ?: get_loggedin_branch_id() ?: 1;
        $sessionID = get_session_id();

        // Students with a wallet balance that doesn't match their fee_payment_history sum.
        $sql = "
            SELECT s.id AS student_id, s.first_name, s.last_name, s.register_no,
                   s.email, c.name AS class_name,
                   sw.amount           AS wallet_balance,
                   sw.payment_channel,
                   sw.payment_gateway,
                   sw.payment_gateway_reference,
                   sw.updated_at,
                   IFNULL(fph_sum.paid, 0) AS fees_paid,
                   sw.amount - IFNULL(fph_sum.paid, 0) AS discrepancy
            FROM student_wallet sw
            INNER JOIN student s ON s.id = sw.student_id
            INNER JOIN enroll  e ON e.student_id = s.id AND e.branch_id = {$branchID}
                                                        AND e.session_id = {$sessionID}
            INNER JOIN class   c ON c.id = e.class_id
            LEFT JOIN (
                SELECT fa.student_id AS enroll_id, SUM(fph.amount + fph.discount) AS paid
                FROM fee_payment_history fph
                INNER JOIN fee_allocation fa ON fa.id = fph.allocation_id
                WHERE fa.session_id = {$sessionID}
                GROUP BY fa.student_id
            ) fph_sum ON fph_sum.enroll_id = e.id
            ORDER BY ABS(sw.amount - IFNULL(fph_sum.paid, 0)) DESC
        ";
        $rows = $this->db->query($sql)->result_array();

        // Summary: total wallet, total applied, total discrepancy.
        $totalWallet      = array_sum(array_column($rows, 'wallet_balance'));
        $totalApplied     = array_sum(array_column($rows, 'fees_paid'));
        $totalDiscrepancy = array_sum(array_column($rows, 'discrepancy'));

        $this->data['rows']               = $rows;
        $this->data['total_wallet']       = $totalWallet;
        $this->data['total_applied']      = $totalApplied;
        $this->data['total_discrepancy']  = $totalDiscrepancy;
        $this->data['branch_id']          = $branchID;
        $this->data['title']              = 'Wallet Reconciliation';
        $this->data['sub_page']           = 'fees/wallet_reconciliation';
        $this->data['main_menu']          = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    // ── DVA Term Payment Report ──────────────────────────────────────────────
    public function dva_term_payment_report()
    {
        $branchID = $this->application_model->get_branch_id() ?: get_loggedin_branch_id() ?: 1;

        // --- summary: unique paystack transfers & amounts per term ---
        $summarySQL = "
            SELECT
                CASE
                    WHEN fg.name LIKE '1ST TERM%' OR fg.name LIKE '1st TERM%' THEN '1ST TERM'
                    WHEN fg.name LIKE '2ND TERM%' OR fg.name LIKE '2nd TERM%' THEN '2ND TERM'
                    WHEN fg.name LIKE '3RD TERM%' OR fg.name LIKE '3rd TERM%' THEN '3RD TERM'
                    ELSE 'OTHER'
                END                            AS term,
                fg.session_id,
                COUNT(DISTINCT pl.id)          AS unique_transfers,
                COUNT(DISTINCT fa.student_id)  AS unique_students,
                COUNT(DISTINCT fph.id)         AS payment_lines,
                SUM(fph.amount)                AS total_applied
            FROM fee_payment_history fph
            INNER JOIN paystack_logs pl
                ON pl.id = CAST(
                    SUBSTRING_INDEX(SUBSTRING_INDEX(fph.remarks, 'DVA Wallet: ', -1), ' for ', 1)
                    AS UNSIGNED)
            INNER JOIN fee_allocation fa  ON fa.id  = fph.allocation_id
                                         AND fa.branch_id = {$branchID}
            INNER JOIN fee_groups     fg  ON fg.id  = fa.group_id
            WHERE fph.remarks LIKE '%DVA Wallet%'
            GROUP BY term, fg.session_id
            ORDER BY fg.session_id, FIELD(term,'1ST TERM','2ND TERM','3RD TERM','OTHER')
        ";
        $summary = $this->db->query($summarySQL)->result_array();

        // --- per-student detail rows ---
        $detailSQL = "
            SELECT
                CASE
                    WHEN fg.name LIKE '1ST TERM%' OR fg.name LIKE '1st TERM%' THEN '1ST TERM'
                    WHEN fg.name LIKE '2ND TERM%' OR fg.name LIKE '2nd TERM%' THEN '2ND TERM'
                    WHEN fg.name LIKE '3RD TERM%' OR fg.name LIKE '3rd TERM%' THEN '3RD TERM'
                    ELSE 'OTHER'
                END                                     AS term,
                fg.name                                 AS fee_group_name,
                s.first_name,
                s.last_name,
                s.register_no,
                c.name                                  AS class_name,
                COUNT(DISTINCT pl.id)                   AS transfers,
                COUNT(DISTINCT fph.id)                  AS payment_lines,
                SUM(fph.amount)                         AS total_applied,
                MIN(fph.date)                           AS first_payment,
                MAX(fph.date)                           AS last_payment
            FROM fee_payment_history fph
            INNER JOIN paystack_logs pl
                ON pl.id = CAST(
                    SUBSTRING_INDEX(SUBSTRING_INDEX(fph.remarks, 'DVA Wallet: ', -1), ' for ', 1)
                    AS UNSIGNED)
            INNER JOIN fee_allocation fa  ON fa.id      = fph.allocation_id
                                         AND fa.branch_id = {$branchID}
            INNER JOIN fee_groups     fg  ON fg.id      = fa.group_id
            INNER JOIN enroll         e   ON e.id       = fa.student_id
            INNER JOIN student        s   ON s.id       = e.student_id
            LEFT  JOIN class          c   ON c.id       = e.class_id
            WHERE fph.remarks LIKE '%DVA Wallet%'
            GROUP BY term, fg.name, s.id
            ORDER BY term, s.last_name, s.first_name
        ";
        $detail = $this->db->query($detailSQL)->result_array();

        // Group detail rows by term for view
        $byTerm = [];
        foreach ($detail as $row) {
            $byTerm[$row['term']][] = $row;
        }

        $this->data['summary']         = $summary;
        $this->data['by_term']         = $byTerm;
        $this->data['branch_id']       = $branchID;
        $this->data['title']           = 'DVA Term Payment Report';
        $this->data['sub_page']        = 'fees/dva_term_payment_report';
        $this->data['main_menu']       = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    /**
     * Fix duplicate same-term fee allocations.
     * Finds students with more than one 3rd-term (or any-term) 2025/2026 allocation
     * of the same fee group name, consolidates payments to the first allocation,
     * and removes the duplicate allocations.
     * Access: /fees/fix_duplicate_term_allocations
     */
    public function fix_duplicate_term_allocations()
    {
        if (!is_superadmin_loggedin()) {
            show_error('Forbidden', 403);
        }

        // Find all (keep_id, delete_id) pairs for 2025/2026 same-name 3rd term duplicates
        $pairs = $this->db->query("
            SELECT a_keep.id AS keep_id, a_del.id AS delete_id,
                   s.first_name, s.last_name, s.register_no, fg.name AS fee_group
            FROM fee_allocation a_keep
            INNER JOIN fee_groups fg ON fg.id = a_keep.group_id
                   AND fg.name LIKE '%3RD TERM%2025/2026%'
                   AND fg.name NOT LIKE '%BUS%'
                   AND fg.name NOT LIKE '%MEAL%'
            INNER JOIN enroll e ON e.id = a_keep.student_id
            INNER JOIN student s ON s.id = e.student_id
            INNER JOIN fee_allocation a_del
                   ON a_del.student_id = a_keep.student_id
                  AND a_del.session_id  = a_keep.session_id
                  AND a_del.id          > a_keep.id
            INNER JOIN fee_groups fg_del ON fg_del.id = a_del.group_id
                   AND fg_del.name = fg.name
            ORDER BY a_keep.id
        ")->result_array();

        $fixed   = [];
        $skipped = [];

        foreach ($pairs as $pair) {
            $keepId   = (int)$pair['keep_id'];
            $deleteId = (int)$pair['delete_id'];

            // Move payments from duplicate to primary
            $this->db->set('allocation_id', $keepId)
                     ->where('allocation_id', $deleteId)
                     ->update('fee_payment_history');

            // Delete the duplicate allocation
            $deleted = $this->db->where('id', $deleteId)->delete('fee_allocation');

            if ($deleted) {
                $fixed[] = array_merge($pair, ['status' => 'fixed']);
            } else {
                $skipped[] = array_merge($pair, ['status' => 'skipped']);
            }
        }

        $this->data['fixed']      = $fixed;
        $this->data['skipped']    = $skipped;
        $this->data['title']      = 'Fix Duplicate Term Allocations';
        $this->data['sub_page']   = 'fees/fix_duplicate_allocations_result';
        $this->data['main_menu']  = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    // GAP 1 — Class-wise Fees Summary
    public function classwise_fees_summary()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID  = $this->application_model->get_branch_id();
        $sessionID = (int)($this->input->post('session_id') ?: get_session_id());
        if ($this->input->post('search')) {
            $classID   = $this->input->post('class_id');
            $term      = $this->input->post('term') ?: '';
            $sessionID = (int)($this->input->post('session_id') ?: get_session_id());
            $rows      = $this->fees_model->getClasswiseFeesSummary($sessionID, $branchID, $classID, $term);
            $totals = [
                'expected'       => array_sum(array_column($rows, 'total_expected')),
                'collected'      => array_sum(array_column($rows, 'total_collected')),
                'outstanding'    => array_sum(array_column($rows, 'total_outstanding')),
                'enrolled'       => array_sum(array_column($rows, 'total_enrolled')),
                'students_paid'  => array_sum(array_column($rows, 'students_paid')),
                'students_not_paid' => array_sum(array_column($rows, 'students_not_paid')),
            ];
            $sessions = $this->db->order_by('school_year DESC')->get('schoolyear')->result_array();
            $sessionLabel = '';
            foreach ($sessions as $s) {
                if ($s['id'] == $sessionID) { $sessionLabel = $s['school_year']; break; }
            }
            $this->data['rows']          = $rows;
            $this->data['totals']        = $totals;
            $this->data['term']          = $term;
            $this->data['session_id']    = $sessionID;
            $this->data['session_label'] = $sessionLabel;
            $this->data['class_id']      = $classID;
        }
        $this->data['branch_id']  = $branchID;
        $this->data['title']      = 'Class-wise Fees Summary';
        $this->data['sub_page']   = 'fees/classwise_fees_summary';
        $this->data['main_menu']  = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    // GAP 1 — CSV export for classwise summary
    public function export_classwise_fees_csv()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID  = $this->application_model->get_branch_id();
        $sessionID = (int)($this->input->get('session_id') ?: get_session_id());
        $classID   = $this->input->get('class_id');
        $term      = $this->input->get('term') ?: '';
        $rows      = $this->fees_model->getClasswiseFeesSummary($sessionID, $branchID, $classID, $term);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="classwise_fees_summary.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Class', 'Total Students', 'Expected', 'Collected', 'Outstanding', 'Paid', 'Unpaid']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['class_name'],
                $r['total_enrolled'],
                $r['total_expected'],
                $r['total_collected'],
                $r['total_outstanding'],
                $r['students_paid'],
                $r['students_not_paid'],
            ]);
        }
        fclose($out);
        exit;
    }

    // GAP 1 — Branch Fees Report
    public function branch_fees_report()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $sessions  = $this->db->order_by('school_year DESC')->get('schoolyear')->result_array();
        $sessionID = (int)($this->input->post('session_id') ?: get_session_id());
        $searched  = false;
        $allRows   = [];
        $outstandingOnly = (bool)$this->input->post('outstanding_only');

        if ($this->input->post('generate')) {
            $searched = true;
            $allRows  = $this->fees_model->getBranchFeesReport($sessionID, $outstandingOnly);
        }

        $this->data['sessions']        = $sessions;
        $this->data['sessionID']       = $sessionID;
        $this->data['outstandingOnly'] = $outstandingOnly;
        $this->data['searched']        = $searched;
        $this->data['allRows']         = $allRows;
        $this->data['branch_id']       = $this->application_model->get_branch_id();
        $this->data['title']           = 'Branch Fees Collection Report';
        $this->data['sub_page']        = 'fees/branch_fees_report';
        $this->data['main_menu']       = 'fees_repots';
        $this->load->view('layout/index', $this->data);
    }

    // GAP 2 & 3 — CSV export for payment history
    public function export_payment_history_csv()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID   = $this->application_model->get_branch_id();
        $classID    = $this->input->get('class_id');
        $sectionID  = $this->input->get('section_id');
        $paymentVia = $this->input->get('payment_via') ?: 'all';
        $term       = $this->input->get('term') ?: '';
        $sessionID  = (int)($this->input->get('session_id') ?: get_session_id());
        $start      = $this->input->get('start') ?: date('Y-m-01');
        $end        = $this->input->get('end')   ?: date('Y-m-d');

        $rows = $this->fees_model->getStuPaymentHistory($classID, $sectionID, $paymentVia, $start, $end, $branchID, false, $sessionID, $term);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="payment_history.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Receipt No', 'Date', 'Student', 'Reg No', 'Roll', 'Class', 'Collected By', 'Payment Via', 'Fee Type', 'Amount', 'Discount', 'Fine', 'Net']);
        foreach ($rows as $r) {
            $net = ($r['amount'] + $r['fine']) - $r['discount'];
            $collectedBy = $r['collect_by'] === 'online' ? 'Online' :
                ($r['collect_by'] === 'wallet' ? 'DVA Wallet' : get_type_name_by_id('staff', $r['collect_by']));
            fputcsv($out, [
                $r['receipt_no'],
                $r['date'],
                $r['first_name'] . ' ' . $r['last_name'],
                $r['register_no'],
                $r['roll'],
                $r['class_name'] . ' (' . $r['section_name'] . ')',
                $collectedBy,
                $r['pay_via'],
                $r['type_name'],
                $r['amount'],
                $r['discount'],
                $r['fine'],
                $net,
            ]);
        }
        fclose($out);
        exit;
    }

    // GAP 3 — CSV export for due report
    public function export_due_report_csv()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $classID   = $this->input->get('class_id');
        $sectionID = $this->input->get('section_id');
        $term      = $this->input->get('term') ?: '';
        $sessionID = (int)($this->input->get('session_id') ?: get_session_id());
        // Temporarily swap active session if different
        $rows = $this->fees_model->getDueReport($classID, $sectionID, $term);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="due_report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Student', 'Reg No', 'Roll', 'Mobile', 'Total Fees', 'Paid', 'Discount', 'Fine', 'Balance']);
        foreach ($rows as $row) {
            $paid = $row['payment']['total_paid'] + $row['payment']['total_discount'];
            if ((float)$row['total_fees'] <= (float)$paid) {
                continue;
            }
            fputcsv($out, [
                $row['first_name'] . ' ' . $row['last_name'],
                $row['register_no'],
                $row['roll'],
                $row['mobileno'],
                $row['total_fees'],
                $row['payment']['total_paid'],
                $row['payment']['total_discount'],
                $row['payment']['total_fine'],
                $row['total_fees'] - $paid,
            ]);
        }
        fclose($out);
        exit;
    }

    // GAP 6 — Discount Register
    public function discount_register()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if ($this->input->post('search')) {
            $classID   = $this->input->post('class_id');
            $sectionID = $this->input->post('section_id');
            $sessionID = (int)($this->input->post('session_id') ?: get_session_id());
            $daterange = explode(' - ', $this->input->post('daterange'));
            $start = date('Y-m-d', strtotime($daterange[0]));
            $end   = date('Y-m-d', strtotime($daterange[1] ?? $daterange[0]));
            $rows  = $this->fees_model->getDiscountRegister($branchID, $sessionID, $start, $end, $classID, $sectionID);
            $this->data['rows']        = $rows;
            $this->data['total_discount'] = array_sum(array_column($rows, 'discount'));
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'Discount Register';
        $this->data['sub_page']  = 'fees/discount_register';
        $this->data['main_menu'] = 'fees_repots';
        $this->data['headerelements'] = [
            'css' => ['vendor/daterangepicker/daterangepicker.css'],
            'js'  => ['vendor/moment/moment.js', 'vendor/daterangepicker/daterangepicker.js'],
        ];
        $this->load->view('layout/index', $this->data);
    }

    // GAP 8 — Send fee reminders via SMS
    public function send_fee_reminders()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            ajax_access_denied();
        }
        $studentIDs = $this->input->post('student_ids');
        if (empty($studentIDs) || !is_array($studentIDs)) {
            echo json_encode(['status' => 'error', 'message' => 'No students selected']);
            exit;
        }
        $sent = 0;
        foreach ($studentIDs as $sid) {
            $sid = (int)$sid;
            if (!$sid) continue;
            $this->sms_model->send_sms(['student_id' => $sid, 'amount' => 0, 'paid_date' => date('d/m/Y')], 2);
            $sent++;
        }
        echo json_encode(['status' => 'success', 'message' => "Reminders sent to {$sent} student(s)"]);
        exit;
    }

    // GAP 11 — Cashflow / Payment Method Summary
    public function cashflow_report()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if ($this->input->post('search')) {
            $sessionID = (int)($this->input->post('session_id') ?: get_session_id());
            $groupBy   = $this->input->post('group_by') ?: 'day';
            $daterange = explode(' - ', $this->input->post('daterange'));
            $start = date('Y-m-d', strtotime($daterange[0]));
            $end   = date('Y-m-d', strtotime($daterange[1] ?? $daterange[0]));
            $rows  = $this->fees_model->getCashflowReport($branchID, $sessionID, $start, $end, $groupBy);
            $totals = [
                'online'      => array_sum(array_column($rows, 'online_total')),
                'dva'         => array_sum(array_column($rows, 'dva_total')),
                'cash'        => array_sum(array_column($rows, 'cash_total')),
                'grand'       => array_sum(array_column($rows, 'grand_total')),
                'tx_count'    => array_sum(array_column($rows, 'transaction_count')),
            ];
            $this->data['rows']     = $rows;
            $this->data['totals']   = $totals;
            $this->data['group_by'] = $groupBy;
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'Cashflow / Payment Method Summary';
        $this->data['sub_page']  = 'fees/cashflow_report';
        $this->data['main_menu'] = 'fees_repots';
        $this->data['headerelements'] = [
            'css' => ['vendor/daterangepicker/daterangepicker.css'],
            'js'  => ['vendor/moment/moment.js', 'vendor/daterangepicker/daterangepicker.js'],
        ];
        $this->load->view('layout/index', $this->data);
    }
}
