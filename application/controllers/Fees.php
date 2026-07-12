<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom school management system
 * @version : 6.0
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
        if (!moduleIsEnabled('student_accounting')) {
            access_denied();
        }
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
                    // group_id is client-supplied - verify it belongs to the
                    // caller's branch before updating/deleting its contents.
                    $this->app_lib->check_branch_restrictions('fee_groups', $groupID, true);
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
        $this->data['group'] = $this->app_lib->getTable('fee_groups', array('t.id' => $id), true);
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
                // $id is a client-supplied URL segment - verify it belongs
                // to the caller's branch before updating it.
                $this->app_lib->check_branch_restrictions('fee_fine', $id, true);
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
            $student_sel_array = isset($student_array) ? $student_array : array();
            $delStudent = array_diff($student_ids, $student_sel_array);
            $fee_groupID = $this->input->post('fee_group_id');
            foreach ($student_array as $key => $value) {
                $arrayData = array(
                    'student_id' => $value,
                    'group_id' => $fee_groupID,
                    'session_id' => get_session_id(),
                    'branch_id' => $branchID,
                );
                $this->db->where($arrayData);
                $q = $this->db->get('fee_allocation');
                if ($q->num_rows() == 0) {
                    $this->db->insert('fee_allocation', $arrayData);
                }
            }
            if (!empty($delStudent)) {
                $this->db->where_in('student_id', $delStudent);
                $this->db->where('group_id', $fee_groupID);
                $this->db->where('session_id', get_session_id());
                $this->db->delete('fee_allocation');
            }
            set_alert('success', translate('information_has_been_saved_successfully'));
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
            if (!empty($student_sel_array)) {
                foreach ($student_array as $key => $value) {
                    $arrayData = array(
                        'student_id' => $value,
                        'group_id' => $fee_groupID,
                        'session_id' => get_session_id(),
                        'branch_id' => $branchID,
                    );
                    $this->db->where($arrayData);
                    $q = $this->db->get('fee_allocation');
                    if ($q->num_rows() == 0) {
                        $this->db->insert('fee_allocation', $arrayData);
                    }
                }
            }
            if (!empty($delStudent)) {
                $this->db->where_in('student_id', $delStudent);
                $this->db->where('group_id', $fee_groupID);
                $this->db->where('session_id', get_session_id());
                $this->db->delete('fee_allocation');
            }

            $message = translate('information_has_been_saved_successfully');
            $array = array('status' => 'success', 'message' => $message);
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
        if ($this->input->post('search')) {
            $this->data['class_id'] = $this->input->post('class_id');
            $this->data['section_id'] = $this->input->post('section_id');
            $this->data['invoicelist'] = $this->fees_model->getInvoiceList($this->data['class_id'], $this->data['section_id'], $branchID);
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('payments_history');
        $this->data['sub_page'] = 'fees/invoice_list';
        $this->data['main_menu'] = 'fees';
        $this->load->view('layout/index', $this->data);
    }

    public function invoice_delete($student_id)
    {
        if (!get_permission('invoice', 'is_delete')) {
            access_denied();
        }

        if (!is_superadmin_loggedin()) {
            $this->db->where('branch_id', get_loggedin_branch_id());
        }
        $this->db->where('student_id', $student_id);
        $result = $this->db->get('fee_allocation')->result_array();
        foreach ($result as $key => $value) {
            $this->db->where('allocation_id', $value['id']);
            $this->db->delete('fee_payment_history');
        }

        if (!is_superadmin_loggedin()) {
            $this->db->where('branch_id', get_loggedin_branch_id());
        }
        $this->db->where('student_id', $student_id);
        $this->db->delete('fee_allocation');
    }

    /* invoice user interface with information are controlled here */
    public function invoice($id = '')
    {
        if (!get_permission('invoice', 'is_view')) {
            access_denied();
        }
        $basic = $this->fees_model->getInvoiceBasic($id);
        if (empty($basic))
            redirect(base_url('dashboard'));
        $this->data['invoice'] = $this->fees_model->getInvoiceStatus($id);
        $this->data['basic'] = $this->fees_model->getInvoiceBasic($id);
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

    public function wallet_reconciliation()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $this->data['wallets']           = $this->fees_model->getWalletReconciliation($branchID);
        $this->data['parent_wallets']    = $this->fees_model->getParentWalletReconciliation($branchID);
        $this->data['unmatched_gateway'] = $this->fees_model->getUnmatchedGatewayPayments($branchID);
        $this->data['branch_id']         = $branchID;
        $this->data['title']             = 'DVA Wallet Reconciliation';
        $this->data['sub_page']   = 'fees/wallet_reconciliation';
        $this->data['main_menu']  = 'fees';
        $this->load->view('layout/index', $this->data);
    }

    public function due_invoice()
    {
        if (!get_permission('due_invoice', 'is_view')) {
            access_denied();
        }
        $branchID        = $this->application_model->get_branch_id();
        $activeSessionID = get_session_id();

        $years = $this->db->order_by('id', 'DESC')->get('schoolyear')->result();
        $sessionList = [];
        foreach ($years as $y) {
            $sessionList[$y->id] = $y->school_year . ($y->id == $activeSessionID ? ' (Current)' : '');
        }

        if ($this->input->post('search')) {
            $this->data['class_id']   = $this->input->post('class_id');
            $this->data['section_id'] = $this->input->post('section_id');
            $sessionID = (int)($this->input->post('session_id') ?: $activeSessionID);
            $term      = $this->input->post('term') ?: '';
            $this->data['session_id'] = $sessionID;
            $this->data['term']       = $term;
            $this->data['invoicelist'] = $this->fees_model->getDueInvoiceList(
                $this->data['class_id'], $this->data['section_id'], $sessionID, $term
            );
        }

        $this->data['session_list']  = $sessionList;
        $this->data['session_id']    = isset($this->data['session_id']) ? $this->data['session_id'] : $activeSessionID;
        $this->data['term']          = isset($this->data['term']) ? $this->data['term'] : '';
        $this->data['branch_id']     = $branchID;
        $this->data['title']         = translate('due_fees_invoice');
        $this->data['sub_page']      = 'fees/due_invoice';
        $this->data['main_menu']     = 'fees';
        $this->load->view('layout/index', $this->data);
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
                'receipt_no'   => $this->fees_model->generateReceiptNo(),
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

            // send payment confirmation sms
            if (isset($_POST['guardian_sms'])) {
                $arrayData = array(
                    'student_id' => $this->input->post('student_id'),
                    'amount' => ($amount + $fineAmount) - $discountAmount,
                    'paid_date' => _d($date),
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
            $fine = $this->fees_model->feeFineCalculation($feesType[0], $feesType[1]);
            $b = $this->fees_model->getBalance($feesType[0], $feesType[1]);
            $balance = $b['balance'];
            $fine = abs($fine - $b['fine']);
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
            if (count($result)) {
                $html .= "<option value=''>" . translate('select') . "</option>";
                foreach ($result as $row) {
                    $html .= '<optgroup label="' . $row['name'] . '">';
                    $this->db->where('fee_groups_id', $row['id']);
                    $resultdetails = $this->db->get('fee_groups_details')->result_array();
                    foreach ($resultdetails as $t) {
                        $sel = ($t['fee_groups_id'] . "|" . $t['fee_type_id'] == $typeID ? 'selected' : '');
                        $html .= '<option value="' . $t['fee_groups_id'] . "|" . $t['fee_type_id'] . '"' . $sel . '>' . get_type_name_by_id('fees_type', $t['fee_type_id']) . '</option>';
                    }
                    $html .= '</optgroup>';
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

    public function due_report()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        $years = $this->db->order_by('id', 'DESC')->get('schoolyear')->result();
        $sessionList = [];
        $activeSessionID = get_session_id();
        foreach ($years as $y) {
            $sessionList[$y->id] = $y->school_year . ($y->id == $activeSessionID ? ' (Current)' : '');
        }

        if ($this->input->post('search')) {
            $this->data['class_id']   = $this->input->post('class_id');
            $this->data['section_id'] = $this->input->post('section_id');
            $dueBefore  = $this->input->post('due_before');
            $due_before = (!empty($dueBefore)) ? date('Y-m-d', strtotime($dueBefore)) : '';
            $sessPost   = (int)($this->input->post('session_id') ?: 0) ?: null;
            $term       = $this->input->post('term') ?: '';
            $this->data['invoicelist'] = $this->fees_model->getDueReport(
                $this->data['class_id'],
                $this->data['section_id'],
                $branchID,
                $due_before,
                $sessPost,
                $term
            );
        }
        $this->data['branch_id']      = $branchID;
        $this->data['session_list']   = $sessionList;
        $this->data['active_session'] = $activeSessionID;
        $this->data['title']          = translate('due_fees_report');
        $this->data['sub_page']       = 'fees/due_report';
        $this->data['main_menu']      = 'fees_reports';
        $this->data['headerelements'] = array(
            'css' => array('vendor/daterangepicker/daterangepicker.css'),
            'js'  => array('vendor/moment/moment.js', 'vendor/daterangepicker/daterangepicker.js'),
        );
        $this->load->view('layout/index', $this->data);
    }

    public function payment_history()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        $years = $this->db->order_by('id', 'DESC')->get('schoolyear')->result();
        $sessionList = [];
        $activeSessionID = get_session_id();
        foreach ($years as $y) {
            $sessionList[$y->id] = $y->school_year . ($y->id == $activeSessionID ? ' (Current)' : '');
        }

        if ($this->input->post('search')) {
            $classID    = $this->input->post('class_id');
            $sectionID  = $this->input->post('section_id');
            $paymentVia = $this->input->post('payment_via');
            $daterange  = explode(' - ', $this->input->post('daterange'));
            $start      = date("Y-m-d", strtotime($daterange[0]));
            $end        = date("Y-m-d", strtotime($daterange[1]));
            $sessPost   = (int)($this->input->post('session_id') ?: 0) ?: null;
            $term       = $this->input->post('term') ?: '';

            $rows = $this->fees_model->getStuPaymentHistory($classID, $sectionID, $paymentVia, $start, $end, $branchID, false, $sessPost, $term);
            $this->data['invoicelist'] = $rows;
            $this->data['totals'] = [
                'amount'   => array_sum(array_column($rows, 'amount')),
                'discount' => array_sum(array_column($rows, 'discount')),
                'fine'     => array_sum(array_column($rows, 'fine')),
                'net'      => array_sum(array_column($rows, 'amount')) + array_sum(array_column($rows, 'fine')) - array_sum(array_column($rows, 'discount')),
            ];
        }
        $this->data['branch_id']      = $branchID;
        $this->data['session_list']   = $sessionList;
        $this->data['active_session'] = $activeSessionID;
        $this->data['title']          = translate('fees_payment_history');
        $this->data['sub_page']       = 'fees/payment_history';
        $this->data['main_menu']      = 'fees_reports';
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

        $years = $this->db->order_by('id', 'DESC')->get('schoolyear')->result();
        $sessionList = [];
        $activeSessionID = get_session_id();
        foreach ($years as $y) {
            $sessionList[$y->id] = $y->school_year . ($y->id == $activeSessionID ? ' (Current)' : '');
        }

        if ($this->input->post('search')) {
            $classID   = $this->input->post('class_id');
            $sectionID = $this->input->post('section_id');
            $sessPost  = (int)($this->input->post('session_id') ?: 0) ?: null;
            $term      = $this->input->post('term') ?: '';

            $rows = $this->fees_model->getStudentFeeStatus($classID, $sectionID, $branchID, $sessPost, $term);
            $this->data['invoicelist'] = $rows;

            $expected = array_sum(array_column($rows, 'expected'));
            $paid     = array_sum(array_column($rows, 'net_paid'));
            $this->data['summary'] = [
                'expected'        => $expected,
                'collected'       => $paid,
                'outstanding'     => $expected - $paid,
                'collection_rate' => $expected > 0 ? round(($paid / $expected) * 100, 1) : 0,
                'count_paid'      => count(array_filter($rows, function($r) { return $r['status'] === 'paid'; })),
                'count_partial'   => count(array_filter($rows, function($r) { return $r['status'] === 'partial'; })),
                'count_owing'     => count(array_filter($rows, function($r) { return $r['status'] === 'owing'; })),
            ];
        }
        $this->data['branch_id']      = $branchID;
        $this->data['session_list']   = $sessionList;
        $this->data['active_session'] = $activeSessionID;
        $this->data['title']          = translate('student_fees_report');
        $this->data['sub_page']       = 'fees/student_fees_report';
        $this->data['main_menu']      = 'fees_reports';
        $this->load->view('layout/index', $this->data);
    }

    public function fine_report()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        $years = $this->db->order_by('id', 'DESC')->get('schoolyear')->result();
        $sessionList = [];
        $activeSessionID = get_session_id();
        foreach ($years as $y) {
            $sessionList[$y->id] = $y->school_year . ($y->id == $activeSessionID ? ' (Current)' : '');
        }

        if ($this->input->post('search')) {
            $classID    = $this->input->post('class_id');
            $sectionID  = $this->input->post('section_id');
            $paymentVia = $this->input->post('payment_via');
            $daterange  = explode(' - ', $this->input->post('daterange'));
            $start      = date("Y-m-d", strtotime($daterange[0]));
            $end        = date("Y-m-d", strtotime($daterange[1]));
            $sessPost   = (int)($this->input->post('session_id') ?: 0) ?: null;
            $term       = $this->input->post('term') ?: '';

            $rows = $this->fees_model->getStuPaymentHistory($classID, $sectionID, $paymentVia, $start, $end, $branchID, true, $sessPost, $term);
            $this->data['invoicelist'] = $rows;
            $this->data['totals'] = [
                'fine' => array_sum(array_column($rows, 'fine')),
            ];
        }
        $this->data['branch_id']      = $branchID;
        $this->data['session_list']   = $sessionList;
        $this->data['active_session'] = $activeSessionID;
        $this->data['title']          = translate('fees_fine_reports');
        $this->data['sub_page']       = 'fees/fine_report';
        $this->data['main_menu']      = 'fees_reports';
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
        foreach ($ids as $key => $value) {

            $feeDetails = $this->db->select('id,amount,fine')->where('id', $value)->get('fee_payment_history')->row();
            if (!empty($feeDetails)) {
                // fee_payment_history has no branch_id of its own - trace
                // ownership through fee_allocation before reverting a
                // payment/adjusting balances for it.
                if (!is_superadmin_loggedin()) {
                    $paymentBranch = $this->db->select('fee_allocation.branch_id')
                        ->from('fee_payment_history')
                        ->join('fee_allocation', 'fee_allocation.id = fee_payment_history.allocation_id')
                        ->where('fee_payment_history.id', $value)
                        ->get()->row();
                    if (empty($paymentBranch) || $paymentBranch->branch_id != get_loggedin_branch_id()) {
                        continue;
                    }
                }

                $amount = ($feeDetails->amount + $feeDetails->fine);

                $sql = "SELECT `transactions`.`account_id`, `transactions_links_details`.`transactions_id` FROM `transactions_links_details` INNER JOIN `transactions` ON `transactions`.`id` = `transactions_links_details`.`transactions_id` WHERE `transactions_links_details`.`payment_id` = " . $this->db->escape($value);
                $transactionsDetails = $this->db->query($sql)->row();
                if (!empty($transactionsDetails)) {

                    $sql = "UPDATE `transactions` SET `amount` = `amount` + $amount, `cr` = `cr` - $amount, `bal` = `bal` - $amount WHERE `id` = " . $this->db->escape($transactionsDetails->transactions_id);
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

            $allocations = $this->fees_model->getInvoiceDetails($invoiceID);
            $totalBalance = 0;
            $totalFine = 0;

            foreach ($allocations as $row) {
                $fine = $this->fees_model->feeFineCalculation($row['allocation_id'], $row['fee_type_id']);
                $b = $this->fees_model->getBalance($row['allocation_id'], $row['fee_type_id']);
                $fine = abs($fine - $b['fine']);
                if ($b['balance'] != 0) {
                    $totalBalance += $b['balance'];
                    $totalFine += $fine;
                    $arrayFees = array(
                        'receipt_no'   => $this->fees_model->generateReceiptNo(),
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

                }
            }

            // transaction voucher save function
            if (isset($_POST['account_id'])) {
                $arrayTransaction = array(
                    'account_id' => $this->input->post('account_id'),
                    'amount' => ($totalBalance + $totalFine),
                    'date' => $date,
                );
                $this->fees_model->saveTransaction($arrayTransaction);
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
                $deposit = $this->fees_model->getStudentFeeDeposit($value->allocationID, $value->feeTypeID);
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
                    'receipt_no'   => $this->fees_model->generateReceiptNo(),
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
                $this->db->insert('fee_payment_history', $arrayFees);

                // transaction voucher save function
                if (isset($value['account_id'])) {
                    $arrayTransaction = array(
                        'account_id' => $value['account_id'],
                        'amount' => ($amount + $fineAmount) - $discountAmount,
                        'date' => $date,
                    );
                    $this->fees_model->saveTransaction($arrayTransaction);
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

    /* student dva list by class and section */
    public function dedicated_virtual_accounts_reports()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            $classID   = $this->input->post('class_id');
            $sectionID = $this->input->post('section_id');
            $students  = $this->application_model->getStudentDVAListByClassSection($classID, $sectionID, $branchID, false, true);
            $student_ids = array_column($students, 'student_id');
            $financials  = $this->fees_model->getDVAFinancialByStudents($student_ids);
            foreach ($students as &$s) {
                $fin = $financials[$s['student_id']] ?? [];
                $s['total_received']    = isset($fin['total_received']) ? (float)$fin['total_received'] : 0.0;
                $s['last_payment_date'] = $fin['last_payment_date'] ?? null;
            }
            unset($s);
            $this->data['students'] = $students;
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('dedicated_virtual_account');
        $this->data['main_menu'] = 'fees';
        $this->data['sub_page'] = 'fees/dedicated_virtual_accounts_reports';
        $this->load->view('layout/index', $this->data);
    }

    public function dva_transaction_history()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $start = $end = '';
        if ($this->input->post('search')) {
            $daterange = explode(' - ', $this->input->post('daterange'));
            $start = date('Y-m-d', strtotime($daterange[0]));
            $end   = date('Y-m-d', strtotime($daterange[1]));
        }
        $this->data['transactions'] = $this->fees_model->getDVATransactionHistory($branchID, $start, $end);
        $this->data['branch_id']  = $branchID;
        $this->data['title']      = translate('dva_transaction_history');
        $this->data['sub_page']   = 'fees/dva_transaction_history';
        $this->data['main_menu']  = 'fees_reports';
        $this->data['headerelements'] = [
            'css' => ['vendor/daterangepicker/daterangepicker.css'],
            'js'  => ['vendor/moment/moment.js', 'vendor/daterangepicker/daterangepicker.js'],
        ];
        $this->load->view('layout/index', $this->data);
    }

    public function dva_sync()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        if ($this->input->is_ajax_request()) {
            $action = $this->input->post('action');

            if ($action === 'fetch') {
                $this->load->library('Paystack_utility');
                $this->paystack_utility->initialize($branchID);
                $secretKey = $this->paystack_utility->api_config['paystack_secret_key'] ?? '';

                if (empty($secretKey)) {
                    echo json_encode(['status' => false, 'message' => 'Paystack secret key not configured for this branch.']);
                    return;
                }

                // Paginate through all dedicated accounts on Paystack
                $allDvas = [];
                $page    = 1;
                do {
                    $ch = curl_init("https://api.paystack.co/dedicated_account?perPage=100&page={$page}");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $secretKey,
                        'Content-Type: application/json',
                    ]);
                    $decoded = json_decode(curl_exec($ch), true);
                    curl_close($ch);
                    if (empty($decoded['status']) || empty($decoded['data'])) break;
                    foreach ($decoded['data'] as $d) {
                        $email = strtolower($d['customer']['email'] ?? '');
                        if ($email) $allDvas[$email] = $d;
                    }
                    $page++;
                } while (count($decoded['data']) === 100);

                $students  = $this->_dva_unsynced_students($branchID);
                $matched   = [];
                $notFound  = [];
                foreach ($students as $stu) {
                    $email          = strtolower($stu['email'] ?? '');
                    $generatedEmail = strtolower($stu['first_name'] . '.' . $stu['last_name'] . '@clemmyschools.com');
                    $dva            = $allDvas[$email] ?? $allDvas[$generatedEmail] ?? null;

                    if (!$dva) {
                        $notFound[] = [
                            'student_name' => $stu['first_name'] . ' ' . $stu['last_name'],
                            'class'        => $stu['class_name'],
                            'section'      => $stu['section_name'],
                            'register_no'  => $stu['register_no'],
                            'email_tried'  => $email ?: $generatedEmail,
                        ];
                        continue;
                    }

                    $assignedAt = $dva['assignment']['assigned_at'] ?? '';
                    $expiredAt  = $dva['assignment']['expired_at']  ?? '';

                    $matched[] = [
                        'student_id'      => $stu['id'],
                        'student_name'    => $stu['first_name'] . ' ' . $stu['last_name'],
                        'class'           => $stu['class_name'],
                        'section'         => $stu['section_name'],
                        'register_no'     => $stu['register_no'],
                        'matched_email'   => $dva['customer']['email'],
                        'account_number'  => $dva['account_number'],
                        'account_name'    => $dva['account_name'],
                        'bank'            => $dva['bank']['name'],
                        'bank_id'         => $dva['bank']['id'],
                        'customer_id'     => $dva['customer']['id'],
                        'customer_code'   => $dva['customer']['customer_code'],
                        'active'          => (int)($dva['active'] ?? 1),
                        'account_id'      => $dva['id'],
                        'currency'        => $dva['currency'] ?? 'NGN',
                        'created_at'      => $dva['created_at'] ?? date('Y-m-d H:i:s'),
                        'assigned_at'     => $assignedAt ?: '0000-00-00 00:00:00',
                        'expired'         => (int)($dva['assignment']['expired'] ?? 0),
                        'assignee_type'   => $dva['assignment']['assignee_type'] ?? '',
                        'account_type'    => $dva['assignment']['account_type'] ?? '',
                        'expired_at'      => $expiredAt ?: '0000-00-00 00:00:00',
                        'assigned_status' => !empty($dva['assigned']) ? 'true' : 'false',
                        'raw_response'    => json_encode($dva),
                    ];
                }

                echo json_encode([
                    'status'         => true,
                    'matched'        => $matched,
                    'not_found'      => $notFound,
                    'total_unsynced' => count($students),
                    'paystack_total' => count($allDvas),
                ]);
                return;
            }

            if ($action === 'import') {
                $rows     = $this->input->post('rows');
                $imported = 0;
                $skipped  = 0;
                if (!is_array($rows) || empty($rows)) {
                    echo json_encode(['status' => false, 'message' => 'No rows supplied.']);
                    return;
                }
                foreach ($rows as $r) {
                    $studentID = (int)($r['student_id'] ?? 0);
                    if (!$studentID) { $skipped++; continue; }
                    if ($this->db->where('user_id', $studentID)->get('dedicated_virtual_account')->num_rows()) {
                        $skipped++; continue;
                    }
                    $this->db->insert('dedicated_virtual_account', [
                        'user_id'                  => $studentID,
                        'customer_id'              => (int)($r['customer_id']  ?? 0),
                        'customer_code'            => $r['customer_code']       ?? '',
                        'dedicated_account_bank'   => $r['bank']                ?? '',
                        'dedicated_account_bank_id'=> (int)($r['bank_id']      ?? 0),
                        'account_name'             => $r['account_name']        ?? '',
                        'account_number'           => $r['account_number']      ?? '',
                        'assigned_status'          => $r['assigned_status']     ?? 'true',
                        'currency'                 => $r['currency']            ?? 'NGN',
                        'active'                   => (int)($r['active']        ?? 1),
                        'account_id'               => (int)($r['account_id']   ?? 0),
                        'created_at'               => $r['created_at']          ?? date('Y-m-d H:i:s'),
                        'assignee_type'            => $r['assignee_type']       ?? '',
                        'expired'                  => (int)($r['expired']       ?? 0),
                        'account_type'             => $r['account_type']        ?? '',
                        'assigned_at'              => $r['assigned_at']         ?: '0000-00-00 00:00:00',
                        'expired_at'               => $r['expired_at']          ?: '0000-00-00 00:00:00',
                        'assignment_expires_at'    => null,
                        'raw_response'             => $r['raw_response']        ?? '',
                    ]);
                    $imported++;
                }
                echo json_encode(['status' => true, 'imported' => $imported, 'skipped' => $skipped]);
                return;
            }

            echo json_encode(['status' => false, 'message' => 'Unknown action.']);
            return;
        }

        // GET — show page
        $this->data['unsynced_students'] = $this->_dva_unsynced_students($branchID);
        $this->data['branch_id']  = $branchID;
        $this->data['title']      = 'DVA Account Sync';
        $this->data['sub_page']   = 'fees/dva_sync';
        $this->data['main_menu']  = 'fees_reports';
        $this->load->view('layout/index', $this->data);
    }

    private function _dva_unsynced_students($branchID)
    {
        return $this->db
            ->select('s.id, s.first_name, s.last_name, s.email, sc.class_name, ss.section_name, s.register_no')
            ->from('student s')
            ->join('student_session sse', 'sse.student_id = s.id')
            ->join('classes sc', 'sc.id = sse.class_id')
            ->join('sections ss', 'ss.id = sse.section_id')
            ->join('dedicated_virtual_account dva', 'dva.user_id = s.id', 'left')
            ->where('sse.session_id', get_session_id())
            ->where('sse.branch_id', $branchID)
            ->where('dva.id IS NULL', null, false)
            ->group_by('s.id')
            ->order_by('sc.class_name, ss.section_name, s.last_name')
            ->get()->result_array();
    }

    /*
    Hafeez Lawal - 2025-01-21
    Requirement 5: report that will list students without allocated fees per class filtered by all sections 
    student fees invoice search user interface 
    */
    public function student_without_invoice_list()
    {
        if (!get_permission('invoice', 'is_view')) {
            access_denied();
        }

        $branchID        = $this->application_model->get_branch_id();
        $activeSessionID = get_session_id();

        $years = $this->db->order_by('id', 'DESC')->get('schoolyear')->result();
        $sessionList = [];
        foreach ($years as $y) {
            $sessionList[$y->id] = $y->school_year . ($y->id == $activeSessionID ? ' (Current)' : '');
        }

        if ($this->input->post('search')) {
            $this->data['class_id']   = $this->input->post('class_id');
            $this->data['section_id'] = $this->input->post('section_id');
            $sessionID = (int)($this->input->post('session_id') ?: $activeSessionID);
            $term      = $this->input->post('term') ?: '';
            $this->data['session_id'] = $sessionID;
            $this->data['term']       = $term;
            $this->data['invoicelist'] = $this->fees_model->getStudentWithoutInvoiceList(
                $this->data['class_id'], $this->data['section_id'], $branchID, $sessionID, $term
            );
        }

        $this->data['session_list'] = $sessionList;
        $this->data['session_id']   = isset($this->data['session_id']) ? $this->data['session_id'] : $activeSessionID;
        $this->data['term']         = isset($this->data['term']) ? $this->data['term'] : '';
        $this->data['branch_id']    = $branchID;
        $this->data['title']        = translate('no_fees_allocated');
        $this->data['sub_page']     = 'fees/student_without_invoice_list';
        $this->data['main_menu']    = 'fees';
        $this->load->view('layout/index', $this->data);
    }

    public function student_ledger($student_id = '')
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        if (empty($student_id)) {
            show_404();
        }
        $basic = $this->fees_model->getInvoiceBasic($student_id);
        if (empty($basic)) {
            access_denied();
        }
        if (!is_superadmin_loggedin()) {
            if ((int)$basic['branch_id'] !== (int)get_loggedin_branch_id()) {
                access_denied();
            }
        }
        $ledger = $this->fees_model->getStudentLedger($student_id);
        $sessRow = $this->db->select('school_year')->where('id', get_session_id())->get('schoolyear')->row();
        $this->data['basic']         = $basic;
        $this->data['details']       = $ledger['details'];
        $this->data['transactions']  = $ledger['transactions'];
        $this->data['student_id']    = $student_id;
        $this->data['session_label'] = $sessRow ? $sessRow->school_year : '';
        $this->data['title']        = translate('student') . ' ' . translate('ledger');
        $this->data['sub_page']     = 'fees/student_ledger';
        $this->data['main_menu']    = 'fees_reports';
        $this->load->view('layout/index', $this->data);
    }

    public function export_due_report_csv()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID  = $this->application_model->get_branch_id();
        $classID   = $this->input->get('class_id');
        $sectionID = $this->input->get('section_id');
        $dueBefore = $this->input->get('due_before');
        $due_before = (!empty($dueBefore)) ? date('Y-m-d', strtotime($dueBefore)) : '';

        $rows = $this->fees_model->getDueReport($classID, $sectionID, $branchID, $due_before);

        $filename = 'due_fees_report_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Student', 'Register No', 'Roll', 'Mobile', 'Class', 'Section',
            'Total Fees', 'Paid', 'Discount', 'Fine', 'Balance']);
        foreach ($rows as $r) {
            $paid = $r['payment']['total_paid'] + $r['payment']['total_discount'];
            if ((float)$r['total_fees'] <= (float)$paid) { continue; }
            fputcsv($out, [
                $r['first_name'] . ' ' . $r['last_name'],
                $r['register_no'], $r['roll'], $r['mobileno'],
                $r['class_name'], $r['section_name'],
                $r['total_fees'], $r['payment']['total_paid'],
                $r['payment']['total_discount'], $r['payment']['total_fine'],
                $r['total_fees'] - $paid,
            ]);
        }
        fclose($out);
        exit;
    }

    public function export_payment_history_csv()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID   = $this->application_model->get_branch_id();
        $classID    = $this->input->get('class_id', true) ?: '';
        $paymentVia = $this->input->get('payment_via', true) ?: 'all';
        $start      = date('Y-m-d', strtotime($this->input->get('start', true) ?: date('Y-m-01')));
        $end        = date('Y-m-d', strtotime($this->input->get('end', true) ?: date('Y-m-d')));

        $rows = $this->fees_model->getStuPaymentHistory($classID, '', $paymentVia, $start, $end, $branchID);

        $filename = 'payment_history_' . $start . '_to_' . $end . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Receipt No', 'Date', 'Student', 'Register No', 'Class', 'Section',
            'Fees Type', 'Payment Via', 'Amount', 'Discount', 'Fine', 'Net']);
        foreach ($rows as $r) {
            $net = ($r['amount'] + $r['fine']) - $r['discount'];
            fputcsv($out, [
                $r['receipt_no'] ?? '', $r['date'],
                $r['first_name'] . ' ' . $r['last_name'],
                $r['register_no'], $r['class_name'], $r['section_name'],
                $r['type_name'], $r['pay_via'],
                $r['amount'], $r['discount'], $r['fine'], $net,
            ]);
        }
        fclose($out);
        exit;
    }

    public function session_outstanding_report()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }

        // Superadmin picks branch via POST; non-superadmin is fixed to their branch
        $branchID = is_superadmin_loggedin()
            ? (int)$this->input->post('branch_id')
            : (int)get_loggedin_branch_id();

        $sessions  = $this->db->order_by('id', 'DESC')->get('schoolyear')->result_array();
        $branches  = is_superadmin_loggedin()
            ? $this->db->order_by('name')->get('branch')->result_array()
            : [];

        // Classes/sections: show branch-filtered list once branch is known, else all
        $classQ  = $this->db->order_by('name');
        $sectQ   = $this->db->order_by('name');
        if ($branchID) {
            $classQ->where('branch_id', $branchID);
            $sectQ->where('branch_id', $branchID);
        }
        $classes  = $classQ->get('class')->result_array();
        $sections = $sectQ->get('section')->result_array();

        $rows    = [];
        $searched = false;
        $totals  = ['fee_charged' => 0, 'carried_forward' => 0, 'total_paid' => 0, 'outstanding' => 0];

        if ($this->input->post('search')) {
            $searched  = true;
            $sessionID = (int)$this->input->post('session_id');
            $term      = $this->input->post('term');
            $classID   = (int)$this->input->post('class_id');
            $sectionID = (int)$this->input->post('section_id');

            $rows = $this->fees_model->getSessionOutstandingReport($branchID, $sessionID, $term, $classID, $sectionID);
            foreach ($rows as $r) {
                $totals['fee_charged']     += $r['fee_charged'];
                $totals['carried_forward'] += $r['carried_forward'];
                $totals['total_paid']      += $r['total_paid'];
                $totals['outstanding']     += $r['outstanding'];
            }
        }

        $this->data['rows']       = $rows;
        $this->data['totals']     = $totals;
        $this->data['searched']   = $searched;
        $this->data['sessions']   = $sessions;
        $this->data['branches']   = $branches;
        $this->data['classes']    = $classes;
        $this->data['sections']   = $sections;
        $this->data['branch_id']  = $branchID;
        $this->data['title']      = 'Outstanding Balances by Session';
        $this->data['sub_page']   = 'fees/session_outstanding_report';
        $this->data['main_menu']  = 'fees_reports';
        $this->data['headerelements'] = [
            'js' => ['vendor/datatables/datatables.min.js'],
            'css'=> ['vendor/datatables/datatables.min.css'],
        ];
        $this->load->view('layout/index', $this->data);
    }

    public function financial_overview()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        if (is_superadmin_loggedin()) {
            $branchID = $this->input->get('branch_id') ?: $this->input->post('branch_id');
            if (empty($branchID)) {
                $first = $this->db->select('id')->order_by('id', 'ASC')->limit(1)->get('branch')->row();
                $branchID = $first ? (int)$first->id : 1;
            }
        } else {
            $branchID = get_loggedin_branch_id();
        }
        $daterange = $this->input->get('daterange') ?: '';
        $start = $end = '';
        if ($daterange) {
            $parts = explode(' - ', $daterange);
            $start = date('Y-m-d', strtotime(trim($parts[0])));
            $end   = isset($parts[1]) ? date('Y-m-d', strtotime(trim($parts[1]))) : $start;
        }

        $sessionID = (int)($this->input->get('session_id') ?: 0) ?: null;
        $term      = $this->input->get('term') ?: '';

        // Build session list for dropdown
        $years = $this->db->order_by('id', 'DESC')->get('schoolyear')->result();
        $sessionList = [];
        $activeSessionID = get_session_id();
        foreach ($years as $y) {
            $sessionList[$y->id] = $y->school_year . ($y->id == $activeSessionID ? ' (Current)' : '');
        }

        $this->data['overview']    = $this->fees_model->getFinancialOverview($branchID, $start, $end, $sessionID, $term);
        $this->data['branch_id']   = $branchID;
        $this->data['daterange']   = $daterange;
        $this->data['session_id']  = $sessionID ?: $activeSessionID;
        $this->data['session_list'] = $sessionList;
        $this->data['term']        = $term;
        $this->data['title']      = 'Financial Overview';
        $this->data['sub_page']   = 'fees/financial_overview';
        $this->data['main_menu']  = 'fees_reports';
        $this->data['headerelements'] = [
            'css' => ['vendor/daterangepicker/daterangepicker.css'],
            'js'  => [
                'vendor/moment/moment.js',
                'vendor/daterangepicker/daterangepicker.js',
                'vendor/chartjs/chart.min.js',
            ],
        ];
        $this->load->view('layout/index', $this->data);
    }

    public function financial_exceptions()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $this->data['exceptions']  = $this->fees_model->getFinancialExceptions($branchID, 0);
        $this->data['resolved']    = $this->fees_model->getFinancialExceptions($branchID, 1);
        $this->data['branch_id']   = $branchID;
        $this->data['title']       = 'Financial Exceptions';
        $this->data['sub_page']    = 'fees/financial_exceptions';
        $this->data['main_menu']   = 'fees_reports';
        $this->load->view('layout/index', $this->data);
    }

    public function run_exception_detection()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            ajax_access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $count = $this->fees_model->detectAndSaveExceptions($branchID);
        echo json_encode(['status' => 'success', 'new_exceptions' => $count]);
    }

    public function resolve_exception($id = 0)
    {
        if (!get_permission('fees_reports', 'is_view')) {
            ajax_access_denied();
        }
        if (!$id) {
            echo json_encode(['status' => 'fail']);
            return;
        }
        $this->fees_model->resolveException($id, get_loggedin_user_id());
        echo json_encode(['status' => 'success']);
    }

    public function classwise_fees_summary()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        $years = $this->db->order_by('id', 'DESC')->get('schoolyear')->result();
        $sessionList = ['' => translate('select')];
        foreach ($years as $y) {
            $sessionList[$y->id] = $y->school_year;
        }

        if ($this->input->post('search')) {
            $sessionID = (int)$this->input->post('session_id');
            $classID   = $this->input->post('class_id');
            $term      = $this->input->post('term') ?: '';

            if ($sessionID > 0) {
                $rows = $this->fees_model->getClasswiseFeesSummary($sessionID, $branchID, $classID, $term);
                $this->data['rows']          = $rows;
                $this->data['session_id']    = $sessionID;
                $this->data['class_id']      = $classID;
                $this->data['term']          = $term;
                $this->data['session_label'] = $sessionList[$sessionID] ?? '';
                $this->data['totals'] = [
                    'enrolled'          => array_sum(array_column($rows, 'total_enrolled')),
                    'expected'          => array_sum(array_column($rows, 'total_expected')),
                    'collected'         => array_sum(array_column($rows, 'total_collected')),
                    'outstanding'       => array_sum(array_column($rows, 'total_outstanding')),
                    'students_paid'     => array_sum(array_column($rows, 'students_paid')),
                    'students_not_paid' => array_sum(array_column($rows, 'students_not_paid')),
                ];
            }
        }

        $this->data['session_list'] = $sessionList;
        $this->data['branch_id']    = $branchID;
        $this->data['term']         = isset($this->data['term']) ? $this->data['term'] : '';
        $this->data['title']        = 'Class-wise Fees Summary';
        $this->data['sub_page']     = 'fees/classwise_fees_summary';
        $this->data['main_menu']    = 'fees_reports';
        $this->load->view('layout/index', $this->data);
    }

    public function section_fees_summary()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        $years      = $this->db->order_by('id', 'DESC')->get('schoolyear')->result();
        $currentSID = (int)get_session_id();
        $sessionList = ['' => translate('select')];
        foreach ($years as $y) {
            $sessionList[$y->id] = $y->school_year . ($y->id == $currentSID ? ' (Current)' : '');
        }

        $term           = '';
        $dateA          = date('Y-m-d', strtotime('-7 days'));
        $dateB          = date('Y-m-d');
        $includePrevDue = false;

        if ($this->input->post('search')) {
            $sessionID      = (int)$this->input->post('session_id');
            $term           = $this->input->post('term') ?: '';
            $dateA          = $this->input->post('date_a') ?: $dateA;
            $dateB          = $this->input->post('date_b') ?: $dateB;
            $includePrevDue = (bool)$this->input->post('include_prev_due');

            // Ensure dateA <= dateB
            if ($dateA > $dateB) { [$dateA, $dateB] = [$dateB, $dateA]; }

            if ($sessionID > 0) {
                $rows = $this->fees_model->getSectionFeesSummary(
                    $sessionID, $branchID, $term, $dateA, $dateB, $includePrevDue
                );
                $this->data['rows']          = $rows;
                $this->data['session_id']    = $sessionID;
                $this->data['session_label'] = $sessionList[$sessionID] ?? '';
                $this->data['totals'] = [
                    'enrolled'  => array_sum(array_column($rows, 'total_enrolled')),
                    'expected'  => array_sum(array_column($rows, 'total_expected')),
                    'paid_a'    => array_sum(array_column($rows, 'paid_a')),
                    'balance_a' => array_sum(array_column($rows, 'balance_a')),
                    'paid_b'    => array_sum(array_column($rows, 'paid_b')),
                    'balance_b' => array_sum(array_column($rows, 'balance_b')),
                ];
            }
        }

        // Load existing schedule config for this branch
        $schedule = $this->db->where('branch_id', $branchID)->get('fee_report_schedules')->row_array();

        $this->data['term']            = $term;
        $this->data['date_a']          = $dateA;
        $this->data['date_b']          = $dateB;
        $this->data['include_prev_due'] = $includePrevDue;
        $this->data['session_list']    = $sessionList;
        $this->data['branch_id']       = $branchID;
        $this->data['schedule']        = $schedule ?: [];
        $this->data['title']           = 'Section-wise Fees Summary';
        $this->data['sub_page']        = 'fees/section_fees_summary';
        $this->data['main_menu']       = 'fees_reports';
        $this->load->view('layout/index', $this->data);
    }

    public function section_fees_summary_save_schedule()
    {
        if (!get_permission('fees_reports', 'is_view') || !is_superadmin_loggedin()) {
            show_error('Access denied', 403);
        }
        $branchID = $this->application_model->get_branch_id();

        $data = [
            'branch_id'       => $branchID,
            'session_id'      => (int)$this->input->post('sched_session_id'),
            'term'            => $this->input->post('sched_term') ?: '',
            'include_prev_due'=> (int)(bool)$this->input->post('sched_include_prev_due'),
            'day_of_week'     => (int)$this->input->post('sched_day_of_week'),
            'recipients_email'=> strip_tags($this->input->post('sched_recipients_email') ?: ''),
            'recipients_wa'   => strip_tags($this->input->post('sched_recipients_wa') ?: ''),
            'active'          => 1,
        ];

        $existing = $this->db->where('branch_id', $branchID)->get('fee_report_schedules')->row();
        if ($existing) {
            $this->db->where('branch_id', $branchID)->update('fee_report_schedules', $data);
        } else {
            $this->db->insert('fee_report_schedules', $data);
        }

        $this->session->set_flashdata('success', 'Weekly report schedule saved.');
        redirect('fees/section_fees_summary');
    }

    // Called by server cron: fees/send_weekly_report/{branch_id}/{token}
    public function send_weekly_report($branchID = 0, $token = '')
    {
        $branchID = (int)$branchID;
        $schedule = $this->db->where('branch_id', $branchID)->where('active', 1)->get('fee_report_schedules')->row_array();
        if (empty($schedule)) {
            echo "No active schedule for branch $branchID\n"; exit;
        }

        // Simple token check: sha256(branch_id + encryption_key)
        $expected = hash('sha256', $branchID . config_item('encryption_key'));
        if (!hash_equals($expected, $token)) {
            show_error('Forbidden', 403);
        }

        $today   = date('Y-m-d');
        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        $rows    = $this->fees_model->getSectionFeesSummary(
            $schedule['session_id'], $branchID,
            $schedule['term'], $weekAgo, $today,
            (bool)$schedule['include_prev_due']
        );

        if (empty($rows)) {
            echo "No data to send.\n"; exit;
        }

        $sessionLabel = $this->db->select('school_year')->where('id', $schedule['session_id'])->get('schoolyear')->row()->school_year ?? '';
        $subject      = "Weekly Fees Collection Report – {$schedule['term']} {$sessionLabel} ({$today})";
        $body         = $this->_build_report_email_html($rows, $schedule['term'], $sessionLabel, $weekAgo, $today);

        $sent = 0;
        foreach (array_filter(array_map('trim', explode(',', $schedule['recipients_email']))) as $email) {
            $this->load->model('email_model');
            $ok = $this->email_model->sendEmail([
                'branch_id' => $branchID,
                'recipient' => $email,
                'subject'   => $subject,
                'message'   => $body,
            ]);
            if ($ok) $sent++;
        }

        $this->db->where('branch_id', $branchID)->update('fee_report_schedules', ['last_sent_at' => date('Y-m-d H:i:s')]);
        echo "Sent to $sent email recipient(s).\n";
    }

    private function _build_report_email_html($rows, $term, $sessionLabel, $dateA, $dateB)
    {
        $html  = '<html><body style="font-family:Arial,sans-serif;font-size:13px">';
        $html .= '<h2 style="color:#333">Fee Collection Report</h2>';
        $html .= "<p><strong>Term:</strong> " . htmlspecialchars($term ?: 'All Terms') . " &nbsp; <strong>Session:</strong> " . htmlspecialchars($sessionLabel) . "</p>";
        $html .= "<p><strong>Period:</strong> Up to " . date('d M Y', strtotime($dateA)) . " vs. up to " . date('d M Y', strtotime($dateB)) . "</p>";
        $html .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">';
        $html .= '<thead style="background:#f0f0f0"><tr>';
        $html .= '<th>Class</th><th>Section</th><th>Students</th><th>Expected</th>';
        $html .= '<th>Paid (' . date('d/m/y', strtotime($dateA)) . ')</th><th>Balance</th>';
        $html .= '<th>Paid (' . date('d/m/y', strtotime($dateB)) . ')</th><th>Balance</th>';
        $html .= '</tr></thead><tbody>';

        $prevClass = null;
        $classTot  = [];
        foreach ($rows as $r) {
            $cid = $r['class_id'];
            if (!isset($classTot[$cid])) {
                $classTot[$cid] = ['name'=>$r['class_name'],'enrolled'=>0,'expected'=>0,'paid_a'=>0,'balance_a'=>0,'paid_b'=>0,'balance_b'=>0];
            }
            $classTot[$cid]['enrolled']  += $r['total_enrolled'];
            $classTot[$cid]['expected']  += $r['total_expected'];
            $classTot[$cid]['paid_a']    += $r['paid_a'];
            $classTot[$cid]['balance_a'] += $r['balance_a'];
            $classTot[$cid]['paid_b']    += $r['paid_b'];
            $classTot[$cid]['balance_b'] += $r['balance_b'];
        }

        foreach ($rows as $r) {
            if ($prevClass !== null && $prevClass !== $r['class_id']) {
                $ct = $classTot[$prevClass];
                $html .= '<tr style="background:#e8e8e8;font-weight:bold">';
                $html .= '<td colspan="2">' . htmlspecialchars($ct['name']) . ' TOTAL</td>';
                $html .= '<td>' . $ct['enrolled'] . '</td><td>' . number_format($ct['expected']) . '</td>';
                $html .= '<td>' . number_format($ct['paid_a']) . '</td><td>' . number_format($ct['balance_a']) . '</td>';
                $html .= '<td>' . number_format($ct['paid_b']) . '</td><td>' . number_format($ct['balance_b']) . '</td>';
                $html .= '</tr>';
            }
            $prevClass = $r['class_id'];
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($r['class_name']) . '</td><td>' . htmlspecialchars($r['section_name']) . '</td>';
            $html .= '<td>' . $r['total_enrolled'] . '</td><td>' . number_format($r['total_expected']) . '</td>';
            $html .= '<td>' . number_format($r['paid_a']) . '</td><td>' . number_format($r['balance_a']) . '</td>';
            $html .= '<td>' . number_format($r['paid_b']) . '</td><td>' . number_format($r['balance_b']) . '</td>';
            $html .= '</tr>';
        }
        if ($prevClass !== null) {
            $ct = $classTot[$prevClass];
            $html .= '<tr style="background:#e8e8e8;font-weight:bold">';
            $html .= '<td colspan="2">' . htmlspecialchars($ct['name']) . ' TOTAL</td>';
            $html .= '<td>' . $ct['enrolled'] . '</td><td>' . number_format($ct['expected']) . '</td>';
            $html .= '<td>' . number_format($ct['paid_a']) . '</td><td>' . number_format($ct['balance_a']) . '</td>';
            $html .= '<td>' . number_format($ct['paid_b']) . '</td><td>' . number_format($ct['balance_b']) . '</td>';
            $html .= '</tr>';
        }

        $grandEnrolled = array_sum(array_column($rows, 'total_enrolled'));
        $grandExpected = array_sum(array_column($rows, 'total_expected'));
        $grandPaidA    = array_sum(array_column($rows, 'paid_a'));
        $grandBalA     = array_sum(array_column($rows, 'balance_a'));
        $grandPaidB    = array_sum(array_column($rows, 'paid_b'));
        $grandBalB     = array_sum(array_column($rows, 'balance_b'));

        $html .= '<tr style="background:#333;color:#fff;font-weight:bold">';
        $html .= '<td colspan="2">GRAND TOTAL</td>';
        $html .= '<td>' . $grandEnrolled . '</td><td>' . number_format($grandExpected) . '</td>';
        $html .= '<td>' . number_format($grandPaidA) . '</td><td>' . number_format($grandBalA) . '</td>';
        $html .= '<td>' . number_format($grandPaidB) . '</td><td>' . number_format($grandBalB) . '</td>';
        $html .= '</tr>';
        $html .= '</tbody></table></body></html>';
        return $html;
    }

    public function export_classwise_fees_csv()
    {
        if (!get_permission('fees_reports', 'is_view')) {
            access_denied();
        }
        $branchID  = $this->application_model->get_branch_id();
        $sessionID = (int)$this->input->get('session_id');
        $classID   = $this->input->get('class_id');

        if (!$sessionID) {
            show_error('session_id required', 400);
        }

        $years = $this->db->order_by('id', 'DESC')->get('schoolyear')->result();
        $sessionLabel = '';
        foreach ($years as $y) {
            if ((int)$y->id === $sessionID) {
                $sessionLabel = $y->school_year;
                break;
            }
        }

        $rows = $this->fees_model->getClasswiseFeesSummary($sessionID, $branchID, $classID);

        $filename = 'classwise_fees_' . preg_replace('/[^a-z0-9]/i', '_', $sessionLabel) . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Class-wise Fees Summary — ' . $sessionLabel]);
        fputcsv($out, []);
        fputcsv($out, [
            'Class', 'Enrolled', 'Expected (NGN)', 'Collected (NGN)',
            'Outstanding (NGN)', 'Collection Rate (%)', 'Students Paid', 'Students Unpaid',
        ]);

        $totEnrolled = $totExpected = $totCollected = $totOutstanding = $totPaid = $totUnpaid = 0;
        foreach ($rows as $r) {
            $rate = $r['total_expected'] > 0
                ? round(($r['total_collected'] / $r['total_expected']) * 100, 1)
                : 0;
            fputcsv($out, [
                $r['class_name'],
                $r['total_enrolled'],
                number_format($r['total_expected'], 2, '.', ''),
                number_format($r['total_collected'], 2, '.', ''),
                number_format($r['total_outstanding'], 2, '.', ''),
                $rate,
                $r['students_paid'],
                $r['students_not_paid'],
            ]);
            $totEnrolled    += $r['total_enrolled'];
            $totExpected    += $r['total_expected'];
            $totCollected   += $r['total_collected'];
            $totOutstanding += $r['total_outstanding'];
            $totPaid        += $r['students_paid'];
            $totUnpaid      += $r['students_not_paid'];
        }
        $totRate = $totExpected > 0 ? round(($totCollected / $totExpected) * 100, 1) : 0;
        fputcsv($out, [
            'TOTAL',
            $totEnrolled,
            number_format($totExpected, 2, '.', ''),
            number_format($totCollected, 2, '.', ''),
            number_format($totOutstanding, 2, '.', ''),
            $totRate,
            $totPaid,
            $totUnpaid,
        ]);
        fclose($out);
    }

}