<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class DedicatedVirtualAccount_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function save($data = array(), $getBranch = array())
    {
        $inser_data1 = array(
            'user_id' => $student_id,
            'customer_id' => $customer_id,
            'customer_code' => $customer_code,
            'dedicated_account_bank' => $dedicated_account_bank,
            'dedicated_account_bank_id' => $dedicated_account_bank_id,
            'account_name' => $account_name,
            'account_number' => $account_number,
            'assigned_status' => $assigned_status,
            'currency' => $currency,
            'active' => $active,
            'account_id' => $account_id,
            'created_at' => $created_at,
            'updated_at' => $updated_at,
            'assignee_type' => $assignee_type,
            'expired' => $expired,
            'account_type' => $account_type,
            'assigned_at' => $assigned_at,
            'expired_at' => $expired_at,
            'assignment_expires_at' => $assignment_expires_at,
            'raw_response' => $data,
        );

        $this->db->insert('dedicated_virtual_account', $inser_data1);
    }
}