<?php if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Fees_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('sms_model');
    }

    public function getPreviousSessionBalance($student_id = '', $session_id = '', $with_fine = 0)
    {
        $total_balance = 0;
        $total_fine = 0;
        $variable = $this->db->where(array('student_id' => $student_id, 'session_id' => $session_id))->get('fee_allocation')->result();
        foreach ($variable as $key => $allocation) {
            $groupsDetails = $this->db->select('fee_type_id')->where('fee_groups_id', $allocation->group_id)->get('fee_groups_details')->result();
            foreach ($groupsDetails as $k => $type) {
                $fine = $this->feeFineCalculation($allocation->id, $type->fee_type_id);
                $b = $this->getBalance($allocation->id, $type->fee_type_id);
                $total_balance += $b['balance'];
                $total_fine += abs($fine - $b['fine']);
            }
        }
        if ($with_fine == 1) {
            return round($total_balance + $total_fine);
        } else {
            return ($total_balance);
        }
    }

    public function feeFineCalculation($allocationID, $typeID)
    {
        $this->db->select('fd.amount,fd.due_date,f.*');
        $this->db->from('fee_allocation as a');
        $this->db->join('fee_groups_details as fd', 'fd.fee_groups_id = a.group_id and fd.fee_type_id = ' . $this->db->escape($typeID), 'left');
        $this->db->join('fee_fine as f', 'f.group_id = fd.fee_groups_id and f.type_id = fd.fee_type_id', 'inner');
        $this->db->where('a.id', $allocationID);
        $this->db->where('f.session_id', get_session_id());
        $getDB = $this->db->get()->row_array();
        if (is_array($getDB) && count($getDB)) {
            $dueDate = $getDB['due_date'];
            if (strtotime($dueDate) < strtotime(date('Y-m-d'))) {
                $feeAmount = $getDB['amount'];
                $feeFrequency = $getDB['fee_frequency'];
                $fineValue = $getDB['fine_value'];
                if ($getDB['fine_type'] == 1) {
                    $fineAmount = $fineValue;
                } else {
                    $fineAmount = ($feeAmount / 100) * $fineValue;
                }
                $now = time(); // or your date as well
                $dueDate = strtotime($dueDate);
                $datediff = $now - $dueDate;
                $overDay = round($datediff / (60 * 60 * 24));
                if ($feeFrequency != 0) {
                    $fineAmount = ($overDay / $feeFrequency) * $fineAmount;
                }
                return $fineAmount;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

    public function getStudentAllocationList($classID = '', $sectionID = '', $groupID = '', $branchID = '')
    {
        $sql = "SELECT e.*, s.photo, CONCAT_WS(' ',s.first_name, s.last_name) as fullname, s.gender, s.register_no, s.parent_id, s.email, s.mobileno, IFNULL(fa.id, 0) as allocation_id
        FROM enroll as e INNER JOIN student as s ON e.student_id = s.id LEFT JOIN login_credential as l ON l.user_id = s.id AND l.role = '7' LEFT JOIN
        fee_allocation as fa ON fa.student_id=e.student_id AND fa.group_id = " . $this->db->escape($groupID) . " AND
        fa.session_id= " . $this->db->escape(get_session_id()) . " WHERE e.class_id = " . $this->db->escape($classID) .
        " AND e.branch_id = " . $this->db->escape($branchID) . " AND e.session_id = " . $this->db->escape(get_session_id());
        if ($sectionID != 'all') {
            $sql .= " AND e.section_id =" . $this->db->escape($sectionID);
        }
        $sql .= " ORDER BY s.id ASC";
        return $this->db->query($sql)->result_array();
    }

    public function getInvoiceStatus($studentID = '')
    {
        $status = "";
        // prev_due is per-allocation; joining to fee_groups_details multiplies it once per fee
        // type. Fix: sum fee type amounts separately and add the true prev_due total via subquery.
        $sid = $this->db->escape($studentID);
        $sess = $this->db->escape(get_session_id());
        $sql = "SELECT SUM(fgd.amount) + COALESCE((SELECT SUM(fa2.prev_due) FROM fee_allocation fa2 WHERE fa2.student_id = {$sid} AND fa2.session_id = {$sess}), 0) AS total, MIN(fa.id) AS inv_no FROM fee_allocation fa LEFT JOIN fee_groups_details fgd ON fgd.fee_groups_id = fa.group_id LEFT JOIN fees_type ft ON ft.id = fgd.fee_type_id WHERE fa.student_id = {$sid} AND fa.session_id = {$sess}";
        $balance = $this->db->query($sql)->row_array();
        $invNo = str_pad($balance['inv_no'], 4, '0', STR_PAD_LEFT);

        $sql = "SELECT IFNULL(SUM(`fee_payment_history`.`amount`), 0) as `amount`, IFNULL(SUM(`fee_payment_history`.`discount`), 0) as `discount`, IFNULL(SUM(`fee_payment_history`.`fine`), 0) as `fine` FROM `fee_payment_history` LEFT JOIN `fee_allocation` ON `fee_payment_history`.`allocation_id` = `fee_allocation`.`id` WHERE `fee_allocation`.`student_id` = " . $this->db->escape($studentID) . " AND `fee_allocation`.`session_id` = " . $this->db->escape(get_session_id());
        $paid = $this->db->query($sql)->row_array();
        log_message('info', $this->db->last_query());

        if ($paid['amount'] == 0) {
            $status = 'unpaid';
        } elseif ($balance['total'] == ($paid['amount'] + $paid['discount'])) {
            $status = 'total';
        } elseif ($paid['amount'] > 1) {
            $status = 'partly';
        }
        return array('status' => $status, 'invoice_no' => $invNo);
    }

    public function getInvoiceDetails($studentID = '')
    {
        $sql = "SELECT `fee_allocation`.`group_id`,`fee_allocation`.`prev_due`,`fee_allocation`.`id` as `allocation_id`, `fees_type`.`name`, `fees_type`.`system`, `fee_groups_details`.`amount`, `fee_groups_details`.`due_date`, `fee_groups_details`.`fee_type_id` FROM `fee_allocation` LEFT JOIN
        `fee_groups_details` ON `fee_groups_details`.`fee_groups_id` = `fee_allocation`.`group_id` LEFT JOIN `fees_type` ON `fees_type`.`id` = `fee_groups_details`.`fee_type_id` WHERE
        `fee_allocation`.`student_id` = " . $this->db->escape($studentID) . " AND `fee_allocation`.`session_id` = " . $this->db->escape(get_session_id()) . " ORDER BY `fee_allocation`.`group_id` ASC";
        $student = array();
        $r = $this->db->query($sql)->result_array();
        log_message('info', $this->db->last_query());

        foreach ($r as $key => $value) {
            if ($value['system'] == 1) {
                $value['amount'] = $value['prev_due'];
            }
            $student[] = $value;
        }
        return $student;
    }

    public function getInvoiceBasic($studentID = '')
    {
        $sessionID = get_session_id();
        $this->db->select('s.id,s.register_no,e.branch_id,s.first_name,s.last_name,s.email as student_email,s.current_address as student_address,c.name as class_name,b.school_name,b.email as school_email,b.mobileno as school_mobileno,b.address as school_address,p.father_name,se.name as section_name');
        $this->db->from('enroll as e');
        $this->db->join('student as s', 's.id = e.student_id', 'inner');
        $this->db->join('class as c', 'c.id = e.class_id', 'left');
        $this->db->join('section as se', 'se.id = e.section_id', 'left');
        $this->db->join('parent as p', 'p.id = s.parent_id', 'left');
        $this->db->join('branch as b', 'b.id = e.branch_id', 'left');
        $this->db->where('e.student_id', $studentID);
        $this->db->where('e.session_id', $sessionID);
        $result = $this->db->get()->row_array();
        log_message('info', $this->db->last_query());
        return $result; 
    }

    public function getStudentFeeDeposit($allocationID, $typeID)
    {
        $sqlDeposit = "SELECT IFNULL(SUM(`amount`), '0.00') as `total_amount`, IFNULL(SUM(`discount`), '0.00') as `total_discount`, IFNULL(SUM(`fine`), '0.00') as `total_fine` FROM `fee_payment_history` WHERE `allocation_id` = " . $this->db->escape($allocationID) . " AND `type_id` = " . $this->db->escape($typeID);
        return $this->db->query($sqlDeposit)->row_array();
    }

    public function getPaymentHistory($allocationID, $groupID)
    {
        $this->db->select('h.*,t.name,t.fee_code,pt.name as payvia');
        $this->db->from('fee_payment_history as h');
        $this->db->join('fees_type as t', 't.id = h.type_id', 'left');
        $this->db->join('payment_types as pt', 'pt.id = h.pay_via', 'left');
        $this->db->where('h.allocation_id', $allocationID);
        $this->db->order_by('h.id', 'asc');
        $result = $this->db->get()->result_array();
        return $result;
    }

    public function typeSave($data = array())
    {
        $arrayData = array(
            'branch_id' => $this->application_model->get_branch_id(),
            'name' => $data['type_name'],
            'fee_code' => strtolower(str_replace(' ', '-', $data['type_name'])),
            'description' => $data['description'],
        );
        if (!isset($data['type_id'])) {
            $this->db->insert('fees_type', $arrayData);
        } else {
            // $data['type_id'] is client-supplied - verify it actually
            // belongs to the caller's branch before updating it.
            $this->app_lib->check_branch_restrictions('fees_type', $data['type_id'], true);
            $this->db->where('id', $data['type_id']);
            $this->db->update('fees_type', $arrayData);
        }
    }

    // add partly of the fee
    public function add_fees($data = array(), $id = '')
    {
        $total_due = get_type_name_by_id('fee_invoice', $id, 'total_due');
        $payment_amount = $data['amount'];
        if (($payment_amount <= $total_due) && ($payment_amount > 0)) {
            $arrayHistory = array(
                'fee_invoice_id' => $id,
                'collect_by' => get_user_stamp(),
                'remarks' => $data['remarks'],
                'method' => $data['method'],
                'amount' => $payment_amount,
                'date' => date("Y-m-d"),
                'session_id' => get_session_id(),
            );
            $this->db->insert('payment_history', $arrayHistory);

            if ($total_due <= $payment_amount) {
                $this->db->where('id', $id);
                $this->db->update('fee_invoice', array('status' => 2));
            } else {
                $this->db->where('id', $id);
                $this->db->update('fee_invoice', array('status' => 1));
            }
            $this->db->where('id', $id);
            $this->db->set('total_paid', 'total_paid + ' . $payment_amount, false);
            $this->db->set('total_due', 'total_due - ' . $payment_amount, false);
            $this->db->update('fee_invoice');

            // send payment confirmation sms
            $arrayHistory['student_id'] = $data['student_id'];
            $arrayHistory['timestamp'] = date("Y-m-d");
            $this->sms_model->send_sms($arrayHistory, 2);
            return true;
        } else {
            return false;
        }
    }

    public function getInvoiceList($class_id = '', $section_id = '', $branch_id = '')
    {
        $this->db->select('e.student_id,e.roll,s.first_name,s.last_name,s.register_no,s.mobileno,c.name as class_name,se.name as section_name');
        $this->db->from('fee_allocation as fa');
        $this->db->join('enroll as e', 'e.student_id = fa.student_id and e.session_id = fa.session_id', 'inner');
        $this->db->join('student as s', 's.id = e.student_id', 'left');
        $this->db->join('class as c', 'c.id = e.class_id', 'left');
        $this->db->join('section as se', 'se.id = e.section_id', 'left');
        $this->db->where('fa.branch_id', $branch_id);
        $this->db->where('fa.session_id', get_session_id());
        $this->db->where('e.class_id', $class_id);
        if ($section_id != 'all') {
            $this->db->where('e.section_id', $section_id);
        }
        $this->db->group_by('fa.student_id');
        $this->db->order_by('e.id', 'asc');
        $result = $this->db->get()->result_array();
        log_message('info', $this->db->last_query());
        foreach ($result as $key => $value) {
            $result[$key]['feegroup'] = $this->getfeeGroup($value['student_id']);
        }
        return $result;
    }

    public function getDueInvoiceList($class_id = '', $section_id = '', $session_id = null, $term = '')
    {
        $rawSessID = $session_id ?: get_session_id();
        $sessEsc   = $this->db->escape($rawSessID);
        $classEsc  = $this->db->escape($class_id);

        $termFilter  = '';
        $prevDueSel  = ', COALESCE(pd.total_prev_due, 0) AS prev_due';
        $prevDueJoin = "LEFT JOIN (
            SELECT student_id, SUM(prev_due) AS total_prev_due
            FROM fee_allocation WHERE session_id = {$sessEsc}
            GROUP BY student_id
        ) pd ON pd.student_id = e.student_id";

        if (!empty($term)) {
            $termEsc  = $this->db->escape($term . '%');
            $sessRow  = $this->db->select('school_year')->where('id', (int)$rawSessID)->get('schoolyear')->row();
            $yearEsc  = $sessRow ? $this->db->escape('%(' . $sessRow->school_year . ')%') : "'%'";
            $termFilter = "AND fa.group_id IN (
                SELECT id FROM fee_groups WHERE name LIKE {$termEsc} AND name LIKE {$yearEsc}
            )";
            $prevDueSel  = ', 0 AS prev_due';
            $prevDueJoin = '';
        }

        $secWhere = ($section_id !== 'all' && !empty($section_id))
            ? 'AND e.section_id = ' . $this->db->escape($section_id) : '';

        $sql = "SELECT
            e.student_id, e.roll,
            s.first_name, s.last_name, s.register_no, s.mobileno,
            c.name  AS class_name,
            se.name AS section_name,
            MIN(fgd_agg.min_due_date)              AS due_date,
            COALESCE(SUM(fgd_agg.group_total), 0)  AS full_amount
            {$prevDueSel},
            COALESCE(SUM(pay_agg.total_paid), 0)   AS total_amount,
            COALESCE(SUM(pay_agg.total_disc), 0)   AS total_discount
        FROM enroll e
        INNER JOIN student s ON s.id = e.student_id
        LEFT JOIN class c ON c.id = e.class_id
        LEFT JOIN section se ON se.id = e.section_id
        LEFT JOIN fee_allocation fa ON fa.student_id = e.student_id
            AND fa.session_id = {$sessEsc} {$termFilter}
        LEFT JOIN (
            SELECT fee_groups_id,
                   SUM(amount)   AS group_total,
                   MIN(due_date) AS min_due_date
            FROM fee_groups_details
            GROUP BY fee_groups_id
        ) fgd_agg ON fgd_agg.fee_groups_id = fa.group_id
        LEFT JOIN (
            SELECT allocation_id,
                   SUM(amount)   AS total_paid,
                   SUM(discount) AS total_disc
            FROM fee_payment_history
            GROUP BY allocation_id
        ) pay_agg ON pay_agg.allocation_id = fa.id
        {$prevDueJoin}
        WHERE e.session_id = {$sessEsc}
          AND e.class_id   = {$classEsc}
          {$secWhere}
        GROUP BY e.student_id
        ORDER BY e.roll ASC";

        $result = $this->db->query($sql)->result_array();
        foreach ($result as $key => $value) {
            $result[$key]['feegroup'] = $this->getfeeGroup($value['student_id']);
        }
        return $result;
    }

    public function getDueReport($class_id = '', $section_id = '', $branch_id = null, $due_before = '', $session_id_param = null, $term = '')
    {
        // prev_due is a per-allocation scalar. Joining to fee_groups_details (one row per fee
        // type) would multiply it by the fee-type count. Fix: pre-aggregate prev_due per student
        // in a subquery (pd) and add it once outside the per-type SUM.
        $branch_id  = ($branch_id !== null) ? $branch_id : $this->application_model->get_branch_id();
        $rawSessID  = $session_id_param ?: get_session_id();
        $session_id = $this->db->escape($rawSessID);
        $this->db->select('fa.id as allocation_id, SUM(gd.amount) + COALESCE(MAX(pd.total_prev_due), 0) as total_fees, e.student_id, e.roll, s.first_name, s.last_name, s.register_no, s.mobileno, c.name as class_name, se.name as section_name', false);
        $this->db->from('fee_allocation as fa');
        $this->db->join('fee_groups_details as gd', 'gd.fee_groups_id = fa.group_id', 'left');
        if (!empty($term)) {
            $termEsc = $this->db->escape($term . '%');
            $this->db->join('fee_groups as fg_t', "fg_t.id = fa.group_id AND fg_t.name LIKE {$termEsc}", 'inner');
        }
        $this->db->join("(SELECT student_id, SUM(prev_due) AS total_prev_due FROM fee_allocation WHERE session_id = {$session_id} GROUP BY student_id) AS pd", 'pd.student_id = fa.student_id', 'left');
        $this->db->join('enroll as e', 'e.student_id = fa.student_id and e.session_id = ' . $session_id, 'inner');
        $this->db->join('student as s', 's.id = e.student_id', 'left');
        $this->db->join('class as c', 'c.id = e.class_id', 'left');
        $this->db->join('section as se', 'se.id = e.section_id', 'left');
        $this->db->where('fa.session_id', $rawSessID);
        $this->db->where('e.class_id', $class_id);
        $this->db->where('e.branch_id', $branch_id);
        if (!empty($section_id)) {
            $this->db->where('e.section_id', $section_id);
        }
        if (!empty($due_before)) {
            $this->db->where('gd.due_date <=', $due_before);
        }
        $this->db->group_by('fa.student_id');
        $this->db->order_by('e.roll', 'asc');
        $result = $this->db->get()->result_array();

        if (!empty($result)) {
            // Batch payment totals — one query for all students instead of N queries
            $student_ids = array_column($result, 'student_id');
            $paymentMap  = $this->getBatchPaymentDetails($student_ids, $rawSessID, $term);
            foreach ($result as $key => $value) {
                $result[$key]['payment'] = $paymentMap[$value['student_id']] ?? [
                    'total_paid' => 0, 'total_discount' => 0, 'total_fine' => 0
                ];
            }
        }
        return $result;
    }

    public function getStudentFeeStatus($classID, $sectionID, $branchID, $sessionID = null, $term = '')
    {
        $rawSessID = $sessionID ?: get_session_id();
        $sessEsc   = $this->db->escape($rawSessID);
        $branchEsc = $this->db->escape($branchID);
        $classEsc  = $this->db->escape($classID);
        $whereSec  = $sectionID ? 'AND e.section_id = ' . $this->db->escape($sectionID) : '';

        // Term filter applied as IN-subquery on fa so it doesn't fan out rows
        $termFilter  = '';
        $prevDueSel  = '+ COALESCE(pd.total_prev_due, 0)';
        $prevDueJoin = "LEFT JOIN (
            SELECT student_id, SUM(prev_due) AS total_prev_due
            FROM fee_allocation WHERE session_id = {$sessEsc}
            GROUP BY student_id
        ) pd ON pd.student_id = e.student_id";

        if (!empty($term)) {
            $termEsc    = $this->db->escape($term . '%');
            $termFilter = "AND fa.group_id IN (SELECT id FROM fee_groups WHERE name LIKE {$termEsc})";
            $prevDueSel  = '';
            $prevDueJoin = '';
        }

        // Pre-aggregate fgd and fph separately to prevent N×M row multiplication
        // when a student has multiple payments for the same allocation or a fee
        // group has multiple detail rows.
        $sql = "SELECT
            e.student_id,
            s.first_name, s.last_name, s.register_no, e.roll,
            c.name AS class_name, se.name AS section_name,
            COALESCE(SUM(fgd_agg.group_total), 0) {$prevDueSel} AS expected,
            COALESCE(SUM(pay_agg.total_paid), 0)     AS total_paid,
            COALESCE(SUM(pay_agg.total_discount), 0) AS total_discount,
            COALESCE(SUM(pay_agg.total_fine), 0)     AS total_fine
        FROM enroll e
        INNER JOIN student s ON s.id = e.student_id
        LEFT JOIN class c ON c.id = e.class_id
        LEFT JOIN section se ON se.id = e.section_id
        LEFT JOIN fee_allocation fa ON fa.student_id = e.student_id
            AND fa.session_id = {$sessEsc} {$termFilter}
        LEFT JOIN (
            SELECT fee_groups_id, SUM(amount) AS group_total
            FROM fee_groups_details
            GROUP BY fee_groups_id
        ) fgd_agg ON fgd_agg.fee_groups_id = fa.group_id
        LEFT JOIN (
            SELECT allocation_id,
                SUM(amount)   AS total_paid,
                SUM(discount) AS total_discount,
                SUM(fine)     AS total_fine
            FROM fee_payment_history
            GROUP BY allocation_id
        ) pay_agg ON pay_agg.allocation_id = fa.id
        {$prevDueJoin}
        WHERE e.session_id = {$sessEsc}
          AND e.branch_id  = {$branchEsc}
          AND e.class_id   = {$classEsc}
          {$whereSec}
        GROUP BY e.student_id
        ORDER BY se.name ASC, e.roll ASC";

        $rows = $this->db->query($sql)->result_array();
        foreach ($rows as &$r) {
            $r['net_paid'] = (float)$r['total_paid'] + (float)$r['total_fine'] - (float)$r['total_discount'];
            $r['balance']  = max(0.0, (float)$r['expected'] - $r['net_paid']);
            if ($r['balance'] < 0.01) {
                $r['status'] = 'paid';
            } elseif ($r['net_paid'] > 0) {
                $r['status'] = 'partial';
            } else {
                $r['status'] = 'owing';
            }
        }
        unset($r);
        return $rows;
    }

    private function getBatchPaymentDetails(array $student_ids, $sessionID = null, $term = '')
    {
        if (empty($student_ids)) {
            return [];
        }
        $this->db->select('fa.student_id,
            IFNULL(SUM(fph.amount), 0)    AS total_paid,
            IFNULL(SUM(fph.discount), 0)  AS total_discount,
            IFNULL(SUM(fph.fine), 0)      AS total_fine', false);
        $this->db->from('fee_allocation fa');
        $this->db->join('fee_payment_history fph', 'fph.allocation_id = fa.id', 'left');
        if (!empty($term)) {
            // Restrict payments to the selected term only — without this, payments
            // from earlier terms inflate total_paid and make unpaid students appear settled
            $termEsc = $this->db->escape($term . '%');
            $this->db->join('fee_groups fg_bp', "fg_bp.id = fa.group_id AND fg_bp.name LIKE {$termEsc}", 'inner');
        }
        $this->db->where('fa.session_id', $sessionID ?: get_session_id());
        $this->db->where_in('fa.student_id', $student_ids);
        $this->db->group_by('fa.student_id');
        $rows = $this->db->get()->result_array();
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['student_id']] = $r;
        }
        return $map;
    }

    public function getPaymentDetails($student_id = '', $branch_id = null)
    {
        $this->db->select('IFNULL(SUM(amount), 0) as total_paid, IFNULL(SUM(discount), 0) as total_discount, IFNULL(SUM(fine), 0) as total_fine');
        $this->db->from('fee_allocation');
        $this->db->join('fee_payment_history', 'fee_payment_history.allocation_id = fee_allocation.id', 'left');
        $this->db->where('fee_allocation.student_id', $student_id);
        $this->db->where('fee_allocation.session_id', get_session_id());
        return $this->db->get()->row_array();
    }

    public function getStudentLedger($student_id)
    {
        $details = $this->getInvoiceDetails($student_id);
        foreach ($details as $k => $row) {
            $deposit = $this->getStudentFeeDeposit($row['allocation_id'], $row['fee_type_id']);
            $details[$k]['paid']     = (float)$deposit['total_amount'];
            $details[$k]['discount'] = (float)$deposit['total_discount'];
            $details[$k]['fine']     = (float)$deposit['total_fine'];
            $details[$k]['balance']  = (float)$row['amount'] - (float)$deposit['total_amount'] - (float)$deposit['total_discount'];
        }

        $this->db->select('h.id, h.receipt_no, h.date, h.amount, h.discount, h.fine, h.pay_via, h.collect_by, h.remarks, h.status, ft.name as type_name, pt.name as payment_method');
        $this->db->from('fee_payment_history h');
        $this->db->join('fee_allocation fa', 'fa.id = h.allocation_id', 'inner');
        $this->db->join('fees_type ft', 'ft.id = h.type_id', 'left');
        $this->db->join('payment_types pt', 'pt.id = h.pay_via', 'left');
        $this->db->where('fa.student_id', $student_id);
        $this->db->where('fa.session_id', get_session_id());
        $this->db->order_by('h.date', 'asc');
        $transactions = $this->db->get()->result_array();

        return ['details' => $details, 'transactions' => $transactions];
    }

    public function getStuPaymentHistory($classID = '', $SectionID = '', $paymentVia = '', $start = '', $end = '', $branchID = '', $onlyFine = false, $sessionID = null, $term = '')
    {
        $sessID = $sessionID ?: get_session_id();
        $this->db->select('h.*,ft.name as type_name,e.student_id,e.roll,s.first_name,s.last_name,s.register_no,s.mobileno,c.name as class_name,se.name as section_name,pt.name as pay_via');
        $this->db->from('fee_payment_history as h');
        $this->db->join('fee_allocation as fa', 'fa.id = h.allocation_id', 'inner');
        if (!empty($term)) {
            $termEsc = $this->db->escape($term . '%');
            $this->db->join('fee_groups as fg_t', "fg_t.id = fa.group_id AND fg_t.name LIKE {$termEsc}", 'inner');
        }
        $this->db->join('fees_type as ft', 'ft.id = h.type_id', 'left');
        $this->db->join('enroll as e', 'e.student_id = fa.student_id', 'inner');
        $this->db->join('student as s', 's.id = e.student_id', 'left');
        $this->db->join('class as c', 'c.id = e.class_id', 'left');
        $this->db->join('section as se', 'se.id = e.section_id', 'left');
        $this->db->join('payment_types as pt', 'pt.id = h.pay_via', 'left');
        $this->db->where('fa.session_id', $sessID);
        $this->db->where('e.session_id', $sessID);
        $this->db->where('h.date  >=', $start);
        $this->db->where('h.date <=', $end);
        $this->db->where('e.branch_id', $branchID);
        if ($onlyFine == true) {
            $this->db->where('h.fine !=', 0);
        }
        if (!empty($classID)) {
            $this->db->where('e.class_id', $classID);
        }
        if (!empty($SectionID)) {
            $this->db->where('e.section_id', $SectionID);
        }
        if ($paymentVia != 'all') {
            if ($paymentVia == 'online') {
                $this->db->where('h.collect_by', 'online');
            } else {
                $this->db->where('h.collect_by !=', 'online');
            }
        }
        $this->db->order_by('h.id', 'asc');
        $result = $this->db->get()->result_array();
        log_message('info', $this->db->last_query());
        return $result;
    }

    public function getStuPaymentReport($classID = '', $sectionID = '', $studentID = '', $typeID = '', $start = '', $end = '', $branchID = '')
    {
        $this->db->select('h.*,gd.due_date,ft.name as type_name,e.student_id,e.roll,s.first_name,s.last_name,s.register_no,pt.name as pay_via');
        $this->db->from('fee_payment_history as h');
        $this->db->join('fee_allocation as fa', 'fa.id = h.allocation_id', 'inner');
        $this->db->join('fees_type as ft', 'ft.id = h.type_id', 'left');
        $this->db->join('fee_groups_details as gd', 'gd.fee_groups_id = fa.group_id and gd.fee_type_id = h.type_id', 'left');
        $this->db->join('enroll as e', 'e.student_id = fa.student_id and e.session_id =  ' . $this->db->escape(get_session_id()), 'inner');
        $this->db->join('student as s', 's.id = e.student_id', 'left');
        $this->db->join('payment_types as pt', 'pt.id = h.pay_via', 'left');
        $this->db->where('fa.session_id', get_session_id());
        $this->db->where('h.date >=', $start);
        $this->db->where('h.date <=', $end);
        $this->db->where('e.branch_id', $branchID);
        $this->db->where('e.class_id', $classID);
        if (!empty($typeID)) {
            $typeID = explode("|", $typeID);
            $this->db->where('h.type_id', $typeID[1]);
        }
        if (!empty($studentID)) {
            $this->db->where('e.student_id', $studentID);
        }
        if (!empty($sectionID)) {
            $this->db->where('e.section_id', $sectionID);
        }
        $this->db->order_by('h.id', 'asc');
        $result = $this->db->get()->result_array();
        return $result;
    }

    public function getStuFeesSummary($classID, $sectionID, $branchID, $studentID = '', $typeID = '')
    {
        $sessID    = $this->db->escape(get_session_id());
        $branchEsc = $this->db->escape($branchID);
        $classEsc  = $this->db->escape($classID);

        $studentEsc     = !empty($studentID) ? $this->db->escape($studentID) : '';
        $sectionEsc     = !empty($sectionID) ? $this->db->escape($sectionID) : '';
        $whereSection   = $sectionEsc  ? "AND e.section_id = {$sectionEsc}"   : '';
        $whereSection2  = $sectionEsc  ? "AND e2.section_id = {$sectionEsc}"  : '';
        $whereSection3  = $sectionEsc  ? "AND e3.section_id = {$sectionEsc}"  : '';
        $whereStudent   = $studentEsc  ? "AND fa.student_id = {$studentEsc}"  : '';
        $whereStudent2  = $studentEsc  ? "AND fa2.student_id = {$studentEsc}" : '';
        $whereStudent3  = $studentEsc  ? "AND fa3.student_id = {$studentEsc}" : '';
        $whereType      = '';
        $whereTypePH    = '';
        if (!empty($typeID)) {
            $parts     = explode('|', $typeID);
            $typeEsc   = $this->db->escape($parts[1]);
            $whereType   = "AND fgd.fee_type_id = {$typeEsc}";
            $whereTypePH = "AND fph.type_id = {$typeEsc}";
        }

        $sql = "SELECT
            COALESCE((
                SELECT SUM(fgd.amount)
                FROM fee_allocation fa
                INNER JOIN enroll e ON e.student_id = fa.student_id AND e.session_id = {$sessID}
                INNER JOIN fee_groups_details fgd ON fgd.fee_groups_id = fa.group_id
                WHERE fa.session_id = {$sessID} AND e.branch_id = {$branchEsc}
                  AND e.class_id = {$classEsc} {$whereSection} {$whereStudent} {$whereType}
            ), 0) +
            COALESCE((
                SELECT SUM(fa2.prev_due)
                FROM fee_allocation fa2
                INNER JOIN enroll e2 ON e2.student_id = fa2.student_id AND e2.session_id = {$sessID}
                WHERE fa2.session_id = {$sessID} AND e2.branch_id = {$branchEsc}
                  AND e2.class_id = {$classEsc} {$whereSection2} {$whereStudent2}
            ), 0) AS total_expected,
            COALESCE((
                SELECT SUM(fph.amount) + SUM(fph.fine) - SUM(fph.discount)
                FROM fee_payment_history fph
                INNER JOIN fee_allocation fa3 ON fa3.id = fph.allocation_id
                INNER JOIN enroll e3 ON e3.student_id = fa3.student_id AND e3.session_id = {$sessID}
                WHERE fa3.session_id = {$sessID} AND e3.branch_id = {$branchEsc}
                  AND e3.class_id = {$classEsc} {$whereSection3} {$whereStudent3} {$whereTypePH}
            ), 0) AS total_collected";

        return $this->db->query($sql)->row_array();
    }

    public function generateReceiptNo()
    {
        $year = date('Y');
        $row = $this->db->query(
            "SELECT MAX(CAST(SUBSTRING_INDEX(receipt_no, '-', -1) AS UNSIGNED)) AS last_seq
             FROM fee_payment_history
             WHERE receipt_no LIKE 'RCP-{$year}-%'"
        )->row_array();
        $next = (int)($row['last_seq'] ?? 0) + 1;
        return 'RCP-' . $year . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    public function getPaySlipHistory(array $ids)
    {
        if (empty($ids)) {
            return [];
        }
        $this->db->where_in('h.id', $ids);
        $this->db->select('h.*,t.name');
        $this->db->from('fee_payment_history as h');
        $this->db->join('fees_type as t', 't.id = h.type_id', 'left');
        return $this->db->get()->result();
    }

    public function getfeeGroup($studentID = '')
    {
        $this->db->select('g.name');
        $this->db->from('fee_allocation as fa');
        $this->db->join('fee_groups as g', 'g.id = fa.group_id', 'inner');
        $this->db->where('fa.student_id', $studentID);
        $this->db->where('fa.session_id', get_session_id());
        return $this->db->get()->result_array();
    }

    public function reminderSave($data = array())
    {
        $arrayData = array(
            'frequency' => $data['frequency'],
            'days' => $data['days'],
            'student' => (isset($data['chk_student']) ? 1 : 0),
            'guardian' => (isset($data['chk_guardian']) ? 1 : 0),
            'message' => $data['message'],
            'dlt_template_id' => $data['dlt_template_id'],
            'branch_id' => $data['branch_id'],
        );
        if (!isset($data['reminder_id'])) {
            $this->db->insert('fees_reminder', $arrayData);
        } else {
            $this->db->where('id', $data['reminder_id']);
            $this->db->update('fees_reminder', $arrayData);
        }
    }

    public function getFeeReminderByDate($date = '', $branch_id = '')
    {
        $this->db->select('fee_groups_details.*,fees_type.name');
        $this->db->from('fee_groups_details');
        $this->db->join('fees_type', 'fees_type.id = fee_groups_details.fee_type_id', 'inner');
        $this->db->where('fee_groups_details.due_date', $date);
        $this->db->where('fees_type.branch_id', $branch_id);
        $this->db->order_by('fee_groups_details.id', 'asc');
        return $this->db->get()->result_array();
    }

    public function getStudentsListReminder($groupID = '', $typeID = '')
    {
        $sessionID = get_type_name_by_id('global_settings', 1, 'session_id');
        $this->db->select('a.id as allocation_id,CONCAT_WS(" ",s.first_name, s.last_name) as child_name,s.mobileno as child_mobileno,pr.name as guardian_name,pr.mobileno as guardian_mobileno');
        $this->db->from('fee_allocation as a');
        $this->db->join('student as s', 's.id = a.student_id', 'inner');
        $this->db->join('parent as pr', 'pr.id = s.parent_id', 'left');
        $this->db->where('a.group_id', $groupID);
        $this->db->where('a.session_id', $sessionID);
        $result = $this->db->get()->result_array();
        foreach ($result as $key => $value) {
            $result[$key]['payment'] = $this->getPaymentDetailsByTypeID($value['allocation_id'], $typeID);
        }
        return $result;
    }

    public function getPaymentDetailsByTypeID($allocationID, $typeID)
    {
        $this->db->select('IFNULL(SUM(amount), 0) as total_paid, IFNULL(SUM(discount), 0) as total_discount');
        $this->db->from('fee_payment_history');
        $this->db->where('allocation_id', $allocationID);
        $this->db->where('type_id', $typeID);
        return $this->db->get()->row_array();
    }

    public function depositAmountVerify($amount = '')
    {
        if ($amount != "") {
            $typeID = $this->input->post('fees_type');
            if (empty($typeID)) {
                return true;
            }
            $feesType = explode("|", $typeID);
            $remainAmount = $this->getBalance($feesType[0], $feesType[1]);
            if ($remainAmount['balance'] < $amount) {
                $this->form_validation->set_message('deposit_verify', '{field} cannot be greater than the remaining.');
                return false;
            } else {
                return true;
            }
        }
        return true;
    }

    public function getBalance($allocationID, $typeID)
    {
        $groupsID = get_type_name_by_id('fee_allocation', $allocationID, 'group_id');
        $systemFeesType = get_type_name_by_id('fees_type', $typeID, 'system');
        if ($systemFeesType == 1) {
            $totalAmount = get_type_name_by_id('fee_allocation', $allocationID, 'prev_due');
        } else {
            $totalAmount = $this->db->select('amount')->where(array('fee_groups_id' => $groupsID, 'fee_type_id' => $typeID))->get('fee_groups_details')->row_array();
            $totalAmount = $totalAmount['amount'];
        }

        $this->db->select('IFNULL(sum(p.amount), 0) as total_amount,IFNULL(sum(p.discount), 0) as total_discount,IFNULL(sum(p.fine), 0) as total_fine');
        $this->db->from('fee_payment_history as p');
        $this->db->where('p.allocation_id', $allocationID);
        $this->db->where('p.type_id', $typeID);
        $paid = $this->db->get()->row_array();
        $balance = $totalAmount - ($paid['total_amount'] + $paid['total_discount']);
        $total_fine = $paid['total_fine'];
        return array('balance' => $balance, 'fine' => $total_fine);
    }

    // voucher transaction save function
    public function saveTransaction($data = array(), $payment_historyID = '', $branch_id = null)
    {
        $branchID = ($branch_id !== null) ? (int) $branch_id : $this->application_model->get_branch_id();
        $accountID = $data['account_id'];
        $date = $data['date'];
        $amount = $data['amount'];

        // get the current balance of the selected account
        $qbal = $this->app_lib->get_table('accounts', $accountID, true);
        $cbal = $qbal['balance'];
        $bal = $cbal + $amount;
        // query system voucher head / insert
        $arrayHead = array(
            'name' => 'Student Fees Collection',
            'type' => 'income',
            'system' => 1,
            'branch_id' => $branchID,
        );
        $this->db->where($arrayHead);
        $query = $this->db->get('voucher_head');
        if ($query->num_rows() > 0) {
            $voucher_headID = $query->row()->id;
        } else {
            $this->db->insert('voucher_head', $arrayHead);
            $voucher_headID = $this->db->insert_id();
        }
        // query system transactions / insert
        $arrayTransactions = array(
            'account_id' => $accountID,
            'voucher_head_id' => $voucher_headID,
            'type' => 'deposit',
            'system' => 1,
            'date' => date("Y-m-d", strtotime($date)),
            'branch_id' => $branchID,
        );
        $this->db->where($arrayTransactions);
        $query = $this->db->get('transactions');
        if ($query->num_rows() == 1) {
            $transactionsID = $query->row()->id;
            $this->db->set('amount', 'amount+' . $amount, false);
            $this->db->set('cr', 'cr+' . $amount, false);
            $this->db->set('bal', $bal);
            $this->db->where('id', $transactionsID);
            $this->db->update('transactions');
        } else {
            $arrayTransactions['ref'] = '';
            $arrayTransactions['amount'] = $amount;
            $arrayTransactions['dr'] = 0;
            $arrayTransactions['cr'] = $amount;
            $arrayTransactions['bal'] = $bal;
            $arrayTransactions['pay_via'] = 5;
            $arrayTransactions['description'] = date("d-M-Y", strtotime($date)) . " Total Fees Collection";
            $this->db->insert('transactions', $arrayTransactions);
            $transactionsID = $this->db->insert_id();
        }

        $this->db->where('id', $accountID);
        $this->db->update('accounts', array('balance' => $bal));

        // insert transactions links details in DB
        $arrayLinkDetails = array(
            'payment_id' => $payment_historyID,
            'transactions_id' => $transactionsID,
        );
        $this->db->insert('transactions_links_details', $arrayLinkDetails);
    }

    public function carryForwardDue($data = array())
    {
        $type_name = "Previous Session Balance";
        $group_name = "Due Record";
        $branchID = $data['branch_id'];
        $sessionID = $data['session_id'];
        $fee_type_id = 0;
        $fee_group_id = 0;

        $arrayType = array(
            'name' => $type_name, 
            'branch_id' => $branchID, 
            'system' => 1, 
        );
        $fee_type_exists  = $this->checkExistsData('fees_type', $arrayType);
        if (!$fee_type_exists) {
            $arrayType['fee_code'] = 'previous-balance';
            $this->db->insert('fees_type', $arrayType);
            $fee_type_id = $this->db->insert_id();
        } else {
            $fee_type_id = $fee_type_exists->id;
        }

        $arrayGroup = array(
            'name' => $group_name, 
            'branch_id' => $branchID, 
            'session_id' => $sessionID, 
            'system' => 1, 
        );
        $fee_group_exists  = $this->checkExistsData('fee_groups', $arrayGroup);
        if (!$fee_group_exists) {
            $this->db->insert('fee_groups', $arrayGroup);
            $fee_group_id = $this->db->insert_id();
        } else {
            $fee_group_id = $fee_group_exists->id;
        }

        $arrayGroupsDetails = array(
            'fee_groups_id' => $fee_group_id, 
            'fee_type_id' => $fee_type_id,
        );
        $fee_group_details_exists = $this->checkExistsData('fee_groups_details', $arrayGroupsDetails);
        if (!$fee_group_details_exists) {

            
            $arrayGroupsDetails['amount'] = 0;
            $arrayGroupsDetails['due_date'] = $data['due_date'];
            $this->db->insert('fee_groups_details', $arrayGroupsDetails);
        } 

        $arrayAllocation = array(
            'student_id' => $data['student_id'], 
            'group_id' => $fee_group_id,
            'branch_id' => $branchID,
            'session_id' => $sessionID,
        );
        $fee_allocation_exists = $this->checkExistsData('fee_allocation', $arrayAllocation);
        if (!$fee_allocation_exists) {
            $arrayAllocation['prev_due'] = $data['prev_due'];
            $this->db->insert('fee_allocation', $arrayAllocation);
        } else {
            $arrayAllocation['prev_due'] = $data['prev_due'];
            $this->db->where('id', $fee_allocation_exists->id);
            $this->db->update('fee_allocation', $arrayAllocation);
        }

    }

    function checkExistsData($table = '', $data = array()) {
        $this->db->where($data);
        $query = $this->db->get($table);
        log_message('info', 'checkExistsData : '. $this->db->last_query());
        if ($query->num_rows() > 0) {
            return $query->row();
        } else {
            return false;
        }
    }

    /*
    Hafeez Lawal - 2025-01-21
    Requirement 5: report that will list students without allocated fees per class filtered by all sections
    SELECT `e`.`student_id`, `e`.`roll`, `s`.`first_name`, `s`.`last_name`, `s`.`register_no`, `s`.`mobileno`, `c`.`name` as `class_name`, `se`.`name` as `section_name`, `fa`.`session_id` FROM `enroll` as `e` 
    left join `fee_allocation` as `fa` ON `e`.`student_id` = `fa`.`student_id`
    LEFT JOIN `student` as `s` ON `s`.`id` = `e`.`student_id`
    LEFT JOIN `class` as `c` ON `c`.`id` = `e`.`class_id` 
    LEFT JOIN `section` as `se` ON `se`.`id` = `e`.`section_id`
    where `e`.class_id = 1 AND `fa`.`session_id` IS NULL;
    */
    public function getStudentWithoutInvoiceList($class_id = '', $section_id = '', $branch_id = '', $session_id = null, $term = '')
    {
        $rawSessID = $session_id ?: get_session_id();
        $sessEsc   = $this->db->escape($rawSessID);
        $classEsc  = $this->db->escape($class_id);

        $termCondition = '';
        if (!empty($term)) {
            $termEsc       = $this->db->escape($term . '%');
            $termCondition = "AND fa.group_id IN (
                SELECT id FROM fee_groups WHERE name LIKE {$termEsc}
            )";
        }

        $secWhere    = ($section_id !== 'all' && !empty($section_id))
            ? 'AND e.section_id = ' . $this->db->escape($section_id) : '';
        $branchWhere = !empty($branch_id)
            ? 'AND e.branch_id = ' . $this->db->escape($branch_id) : '';

        $sql = "SELECT e.student_id, e.roll,
                       s.first_name, s.last_name, s.register_no, s.mobileno,
                       c.name AS class_name, se.name AS section_name
                FROM enroll e
                LEFT JOIN fee_allocation fa
                    ON fa.student_id = e.student_id
                    AND fa.session_id = {$sessEsc}
                    {$termCondition}
                LEFT JOIN student s ON s.id = e.student_id
                LEFT JOIN class c ON c.id = e.class_id
                LEFT JOIN section se ON se.id = e.section_id
                WHERE e.session_id = {$sessEsc}
                  AND e.class_id = {$classEsc}
                  {$secWhere}
                  {$branchWhere}
                  AND fa.id IS NULL
                GROUP BY e.student_id
                ORDER BY e.id ASC";

        $result = $this->db->query($sql)->result_array();
        foreach ($result as $key => $value) {
            $result[$key]['feegroup'] = $this->getfeeGroup($value['student_id']);
        }
        return $result;
    }

    public function getWalletReconciliation($branch_id = null)
    {
        $session_id = get_session_id();
        $sql = "
            SELECT
                sw.id                                        AS wallet_id,
                sw.student_id,
                CONCAT(s.first_name, ' ', s.last_name)       AS student_name,
                s.email,
                s.register_no,
                sw.amount                                    AS wallet_balance,
                sw.update_count,
                sw.updated_at,
                sw.payment_gateway_reference,
                COUNT(DISTINCT fa.id)                        AS allocs_in_session,
                COALESCE(SUM(fph.amount), 0)                 AS total_wallet_applied_session
            FROM student_wallet sw
            INNER JOIN student s ON s.id = sw.student_id
            LEFT JOIN fee_allocation fa
                ON fa.student_id = sw.student_id
                AND fa.session_id = " . $this->db->escape($session_id);
        if (!empty($branch_id)) {
            $sql .= " AND fa.branch_id = " . $this->db->escape($branch_id);
        }
        $sql .= "
            LEFT JOIN fee_payment_history fph
                ON fph.allocation_id = fa.id AND fph.pay_via = 99
            WHERE sw.amount > 0
              AND sw.student_id > 0
            GROUP BY sw.id, sw.student_id, s.first_name, s.last_name, s.email,
                     s.register_no, sw.amount, sw.update_count, sw.updated_at,
                     sw.payment_gateway_reference
            ORDER BY sw.amount DESC";
        return $this->db->query($sql)->result_array();
    }

    // Phase 6: Parent wallet reconciliation — parents with unspent family DVA balances.
    public function getParentWalletReconciliation($branch_id = null)
    {
        $session_id = get_session_id();
        $sql = "
            SELECT
                pw.id                                        AS wallet_id,
                pw.parent_id,
                p.name                                       AS parent_name,
                p.email,
                pw.amount                                    AS wallet_balance,
                pw.update_count,
                pw.updated_at,
                pw.payment_gateway_reference,
                COUNT(DISTINCT s.id)                         AS children_count,
                COALESCE(SUM(fph.amount), 0)                 AS total_wallet_applied_session
            FROM parent_wallet pw
            INNER JOIN parent p ON p.id = pw.parent_id
            LEFT JOIN student s ON s.parent_id = pw.parent_id
            LEFT JOIN fee_allocation fa
                ON fa.student_id = s.id
                AND fa.session_id = " . $this->db->escape($session_id);
        if (!empty($branch_id)) {
            $sql .= " AND fa.branch_id = " . $this->db->escape($branch_id);
        }
        $sql .= "
            LEFT JOIN fee_payment_history fph
                ON fph.allocation_id = fa.id AND fph.pay_via = 99
            WHERE pw.amount > 0
            GROUP BY pw.id, pw.parent_id, p.name, p.email,
                     pw.amount, pw.update_count, pw.updated_at,
                     pw.payment_gateway_reference
            ORDER BY pw.amount DESC";
        return $this->db->query($sql)->result_array();
    }

    public function getDVAFinancialByStudents(array $student_ids)
    {
        if (empty($student_ids)) {
            return [];
        }
        $sess = $this->db->escape(get_session_id());
        $in   = implode(',', array_map('intval', $student_ids));
        $sql  = "SELECT fa.student_id,
                    COALESCE(SUM(fph.amount), 0)    AS total_received,
                    MAX(fph.date)                   AS last_payment_date
                 FROM fee_allocation fa
                 INNER JOIN fee_payment_history fph ON fph.allocation_id = fa.id
                 WHERE fa.session_id = {$sess}
                   AND fph.collect_by = 'wallet'
                   AND fa.student_id IN ({$in})
                 GROUP BY fa.student_id";
        $rows = $this->db->query($sql)->result_array();
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['student_id']] = $r;
        }
        return $map;
    }

    public function getDVATransactionHistory($branchID, $start = '', $end = '')
    {
        $sess   = $this->db->escape(get_session_id());
        $branch = $this->db->escape($branchID);

        $sql = "SELECT
                    pl.id                            AS log_id,
                    pl.reference,
                    pl.paid_date,
                    pl.amount                        AS gateway_amount,
                    pl.customer_email,
                    pl.authorization_sender_name,
                    pl.authorization_narration,
                    pl.status                        AS gateway_status,
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    s.register_no,
                    dv.account_number,
                    COALESCE(SUM(fph.amount), 0)     AS applied_amount,
                    COUNT(fph.id)                    AS allocation_count,
                    MIN(fph.date)                    AS applied_date
                FROM paystack_logs pl
                LEFT JOIN fee_payment_history fph
                    ON fph.gateway_reference LIKE CONCAT(pl.reference, '_%')
                LEFT JOIN fee_allocation fa
                    ON fa.id = fph.allocation_id AND fa.session_id = {$sess}
                LEFT JOIN enroll e
                    ON e.student_id = fa.student_id AND e.session_id = {$sess}
                    AND e.branch_id = {$branch}
                LEFT JOIN student s ON s.id = fa.student_id
                LEFT JOIN dedicated_virtual_account dv ON dv.user_id = s.id
                WHERE 1=1";
        if (!empty($start)) {
            $sql .= " AND pl.paid_date >= " . $this->db->escape($start);
        }
        if (!empty($end)) {
            $sql .= " AND pl.paid_date <= " . $this->db->escape($end . ' 23:59:59');
        }
        $sql .= " GROUP BY pl.id, pl.reference, pl.paid_date, pl.amount, pl.customer_email,
                           pl.authorization_sender_name, pl.authorization_narration, pl.status,
                           s.first_name, s.last_name, s.register_no, dv.account_number
                  ORDER BY pl.paid_date DESC";
        return $this->db->query($sql)->result_array();
    }

    public function getFinancialOverview($branchID, $start = '', $end = '', $sessionID = null, $term = '')
    {
        $sessID = $sessionID ?: get_session_id();
        $sess   = $this->db->escape($sessID);
        $branch = $this->db->escape($branchID);

        $termJoin = '';
        if (!empty($term)) {
            $termEsc  = $this->db->escape($term . '%');
            $termJoin = "INNER JOIN fee_groups fg_t ON fg_t.id = fa.group_id AND fg_t.name LIKE {$termEsc}";
        }

        $dateWhere = ($start && $end)
            ? 'AND fph.date BETWEEN ' . $this->db->escape($start) . ' AND ' . $this->db->escape($end)
            : '';

        $monthlyDateWhere = ($start && $end)
            ? 'AND fph.date BETWEEN ' . $this->db->escape($start) . ' AND ' . $this->db->escape($end)
            : 'AND fph.date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)';

        // Total expected — always full session, never date-filtered
        $expectedSql = "SELECT
            COALESCE(SUM(fgd.amount), 0) AS billed,
            COALESCE(SUM(fa.prev_due), 0) AS prev_due
            FROM fee_allocation fa
            {$termJoin}
            INNER JOIN enroll e ON e.student_id = fa.student_id AND e.session_id = {$sess}
                AND e.branch_id = {$branch}
            LEFT JOIN fee_groups_details fgd ON fgd.fee_groups_id = fa.group_id
            WHERE fa.session_id = {$sess}";
        $exp = $this->db->query($expectedSql)->row_array();
        $expected = (float)$exp['billed'] + (float)$exp['prev_due'];

        // Full-session collected — used for Outstanding and Collection Rate (never date-filtered)
        $collectedFullSql = "SELECT
            COALESCE(SUM(fph.amount), 0) AS paid,
            COALESCE(SUM(fph.fine), 0) AS fine,
            COALESCE(SUM(fph.discount), 0) AS discount
            FROM fee_payment_history fph
            INNER JOIN fee_allocation fa ON fa.id = fph.allocation_id
            {$termJoin}
            INNER JOIN enroll e ON e.student_id = fa.student_id AND e.session_id = {$sess}
                AND e.branch_id = {$branch}
            WHERE fa.session_id = {$sess}";
        $colFull = $this->db->query($collectedFullSql)->row_array();
        $collected_full = (float)$colFull['paid'] + (float)$colFull['fine'] - (float)$colFull['discount'];

        // Date-range collected — shown in "Collected" KPI card only
        if ($dateWhere) {
            $collectedSql = "SELECT
                COALESCE(SUM(fph.amount), 0) AS paid,
                COALESCE(SUM(fph.fine), 0) AS fine,
                COALESCE(SUM(fph.discount), 0) AS discount
                FROM fee_payment_history fph
                INNER JOIN fee_allocation fa ON fa.id = fph.allocation_id
                {$termJoin}
                INNER JOIN enroll e ON e.student_id = fa.student_id AND e.session_id = {$sess}
                    AND e.branch_id = {$branch}
                WHERE fa.session_id = {$sess} {$dateWhere}";
            $col = $this->db->query($collectedSql)->row_array();
            $collected = (float)$col['paid'] + (float)$col['fine'] - (float)$col['discount'];
        } else {
            $collected = $collected_full;
        }

        // Monthly collection chart — date range if set, else last 6 months
        $monthlySql = "SELECT DATE_FORMAT(fph.date, '%b %Y') AS month,
                COALESCE(SUM(fph.amount) + SUM(fph.fine) - SUM(fph.discount), 0) AS net
            FROM fee_payment_history fph
            INNER JOIN fee_allocation fa ON fa.id = fph.allocation_id
            {$termJoin}
            INNER JOIN enroll e ON e.student_id = fa.student_id AND e.session_id = {$sess}
                AND e.branch_id = {$branch}
            WHERE fa.session_id = {$sess}
              {$monthlyDateWhere}
            GROUP BY DATE_FORMAT(fph.date, '%Y-%m')
            ORDER BY MIN(fph.date) ASC";
        $monthly = $this->db->query($monthlySql)->result_array();

        // Top 10 outstanding students — pre-aggregated subqueries prevent N×M fan-out
        $topOwedSql = "SELECT
                fa.student_id,
                CONCAT(s.first_name,' ',s.last_name) AS student_name,
                s.register_no,
                c.name AS class_name,
                (COALESCE(SUM(fgd_agg.group_total), 0)
                    + COALESCE(SUM(fa.prev_due), 0)
                    - COALESCE(SUM(pay_agg.total_paid), 0)
                    - COALESCE(SUM(pay_agg.total_disc), 0)) AS balance
            FROM fee_allocation fa
            {$termJoin}
            INNER JOIN enroll e ON e.student_id = fa.student_id AND e.session_id = {$sess}
                AND e.branch_id = {$branch}
            INNER JOIN student s ON s.id = fa.student_id
            INNER JOIN class c ON c.id = e.class_id
            LEFT JOIN (
                SELECT fee_groups_id, SUM(amount) AS group_total
                FROM fee_groups_details GROUP BY fee_groups_id
            ) fgd_agg ON fgd_agg.fee_groups_id = fa.group_id
            LEFT JOIN (
                SELECT allocation_id, SUM(amount) AS total_paid, SUM(discount) AS total_disc
                FROM fee_payment_history GROUP BY allocation_id
            ) pay_agg ON pay_agg.allocation_id = fa.id
            WHERE fa.session_id = {$sess}
            GROUP BY fa.student_id, s.first_name, s.last_name, s.register_no, c.name
            HAVING balance > 0.01
            ORDER BY balance DESC
            LIMIT 10";
        $topOwed = $this->db->query($topOwedSql)->result_array();

        // Recent payments (last 10) — filtered by date range if set
        $recentSql = "SELECT fph.date, fph.amount, fph.receipt_no,
                CONCAT(s.first_name,' ',s.last_name) AS student_name,
                s.register_no, ft.name AS type_name
            FROM fee_payment_history fph
            INNER JOIN fee_allocation fa ON fa.id = fph.allocation_id
            {$termJoin}
            INNER JOIN enroll e ON e.student_id = fa.student_id AND e.session_id = {$sess}
                AND e.branch_id = {$branch}
            INNER JOIN student s ON s.id = fa.student_id
            LEFT JOIN fees_type ft ON ft.id = fph.type_id
            WHERE fa.session_id = {$sess} AND fph.status = 'paid' {$dateWhere}
            ORDER BY fph.id DESC LIMIT 10";
        $recent = $this->db->query($recentSql)->result_array();

        // Open exception count
        $exCount = $this->db->where('branch_id', $branchID)->where('resolved', 0)
            ->count_all_results('financial_exceptions');

        return [
            'expected'           => $expected,
            'collected'          => $collected,          // date-range filtered (or full if no range)
            'collected_full'     => $collected_full,     // always full-session
            'outstanding'        => $expected - $collected_full,  // always full-session
            'collection_rate'    => $expected > 0 ? round($collected_full / $expected * 100, 1) : 0,
            'date_filtered'      => !empty($dateWhere),
            'monthly'            => $monthly,
            'top_owed'           => $topOwed,
            'recent_payments'    => $recent,
            'open_exceptions'    => $exCount,
        ];
    }

    public function detectAndSaveExceptions($branchID)
    {
        $sess   = $this->db->escape(get_session_id());
        $branch = $this->db->escape($branchID);
        $inserts = [];

        // 1. Overpayments: paid > allocated + prev_due for a student in this session
        $overSql = "SELECT
                fa.student_id,
                fa.id AS allocation_id,
                SUM(fgd.amount) AS allocated,
                COALESCE(SUM(fa.prev_due), 0) AS prev_due_total,
                SUM(fph.amount) AS paid,
                SUM(fph.discount) AS discounted
            FROM fee_allocation fa
            INNER JOIN enroll e ON e.student_id = fa.student_id AND e.session_id = {$sess}
                AND e.branch_id = {$branch}
            LEFT JOIN fee_groups_details fgd ON fgd.fee_groups_id = fa.group_id
            LEFT JOIN fee_payment_history fph ON fph.allocation_id = fa.id
            WHERE fa.session_id = {$sess}
            GROUP BY fa.student_id, fa.id
            HAVING (paid + discounted) > (allocated + prev_due_total) + 0.01";
        foreach ($this->db->query($overSql)->result_array() as $r) {
            $excess = ($r['paid'] + $r['discounted']) - ($r['allocated'] + $r['prev_due_total']);
            $inserts[] = [
                'branch_id'      => $branchID,
                'exception_type' => 'overpayment',
                'severity'       => 'critical',
                'student_id'     => $r['student_id'],
                'allocation_id'  => $r['allocation_id'],
                'amount'         => $excess,
                'description'    => "Student ID {$r['student_id']}: paid exceeds allocated by {$excess}.",
            ];
        }

        // 2. Orphaned payment rows: fee_payment_history with no matching fee_allocation
        $orphanSql = "SELECT fph.id, fph.allocation_id, fph.amount
            FROM fee_payment_history fph
            LEFT JOIN fee_allocation fa ON fa.id = fph.allocation_id
            WHERE fa.id IS NULL";
        foreach ($this->db->query($orphanSql)->result_array() as $r) {
            $inserts[] = [
                'branch_id'      => $branchID,
                'exception_type' => 'orphaned_payment',
                'severity'       => 'critical',
                'student_id'     => null,
                'allocation_id'  => $r['allocation_id'],
                'amount'         => $r['amount'],
                'description'    => "fee_payment_history row {$r['id']} references missing allocation {$r['allocation_id']}.",
            ];
        }

        // 3. Wallet balance with no allocations in current session (student has wallet funds
        //    but no fee_allocation assigned — funds are stranded)
        $strandedSql = "SELECT sw.student_id, sw.amount AS wallet_balance
            FROM student_wallet sw
            WHERE sw.amount > 0
              AND NOT EXISTS (
                  SELECT 1 FROM fee_allocation fa2
                  INNER JOIN enroll e2 ON e2.student_id = fa2.student_id
                      AND e2.session_id = {$sess} AND e2.branch_id = {$branch}
                  WHERE fa2.student_id = sw.student_id AND fa2.session_id = {$sess}
              )";
        foreach ($this->db->query($strandedSql)->result_array() as $r) {
            $inserts[] = [
                'branch_id'      => $branchID,
                'exception_type' => 'stranded_wallet',
                'severity'       => 'warning',
                'student_id'     => $r['student_id'],
                'allocation_id'  => null,
                'amount'         => $r['wallet_balance'],
                'description'    => "Student ID {$r['student_id']} has wallet balance {$r['wallet_balance']} but no fee allocation in current session.",
            ];
        }

        if (!empty($inserts)) {
            $this->db->insert_batch('financial_exceptions', $inserts);
        }
        return count($inserts);
    }

    public function getFinancialExceptions($branchID, $resolved = 0)
    {
        $this->db->select('fe.*, CONCAT(s.first_name," ",s.last_name) AS student_name, s.register_no');
        $this->db->from('financial_exceptions fe');
        $this->db->join('student s', 's.id = fe.student_id', 'left');
        $this->db->where('fe.branch_id', $branchID);
        $this->db->where('fe.resolved', $resolved);
        $this->db->order_by('FIELD(fe.severity,"critical","warning","info")', null, false);
        $this->db->order_by('fe.detected_at', 'desc');
        return $this->db->get()->result_array();
    }

    public function resolveException($exception_id, $resolved_by)
    {
        $this->db->where('id', $exception_id);
        $this->db->update('financial_exceptions', [
            'resolved'    => 1,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolved_by' => $resolved_by,
        ]);
    }

    public function getClasswiseFeesSummary($sessionID, $branchID, $classID = '', $term = '')
    {
        $sessEsc     = $this->db->escape((int)$sessionID);
        $branchEsc   = $this->db->escape((int)$branchID);
        $classFilter = !empty($classID) ? 'AND c.id = ' . $this->db->escape((int)$classID) : '';

        $termWhere    = '';
        $termWhereFA3 = '';
        $termWhereFA4 = '';
        $prevDueSel   = "+ COALESCE(SUM(fa.prev_due), 0)";
        if (!empty($term)) {
            $termEsc      = $this->db->escape($term . '%');
            $termSub      = "(SELECT id FROM fee_groups WHERE name LIKE {$termEsc})";
            $termWhere    = "AND fa.group_id IN {$termSub}";
            $termWhereFA3 = "AND fa3.group_id IN {$termSub}";
            $termWhereFA4 = "AND fa4.group_id IN {$termSub}";
            $prevDueSel   = '';
        }

        $sql = "SELECT
            c.id   AS class_id,
            c.name AS class_name,
            COALESCE(enrolled.total_enrolled, 0)    AS total_enrolled,
            COALESCE(expected.total_expected, 0)    AS total_expected,
            COALESCE(collected.total_collected, 0)  AS total_collected,
            COALESCE(expected.total_expected, 0) - COALESCE(collected.total_collected, 0) AS total_outstanding,
            COALESCE(paid_stu.students_paid, 0)     AS students_paid,
            COALESCE(enrolled.total_enrolled, 0) - COALESCE(paid_stu.students_paid, 0) AS students_not_paid

        FROM class c

        LEFT JOIN (
            SELECT class_id, COUNT(DISTINCT student_id) AS total_enrolled
            FROM enroll
            WHERE session_id = {$sessEsc} AND branch_id = {$branchEsc}
            GROUP BY class_id
        ) enrolled ON enrolled.class_id = c.id

        LEFT JOIN (
            SELECT e2.class_id,
                COALESCE(SUM(fgd_agg.group_total), 0) {$prevDueSel} AS total_expected
            FROM fee_allocation fa
            INNER JOIN enroll e2
                ON e2.student_id = fa.student_id AND e2.session_id = {$sessEsc} AND e2.branch_id = {$branchEsc}
            LEFT JOIN (
                SELECT fee_groups_id, SUM(amount) AS group_total
                FROM fee_groups_details
                GROUP BY fee_groups_id
            ) fgd_agg ON fgd_agg.fee_groups_id = fa.group_id
            WHERE fa.session_id = {$sessEsc} AND fa.branch_id = {$branchEsc} {$termWhere}
            GROUP BY e2.class_id
        ) expected ON expected.class_id = c.id

        LEFT JOIN (
            SELECT e3.class_id,
                SUM(h.amount + h.fine - h.discount) AS total_collected
            FROM fee_payment_history h
            INNER JOIN fee_allocation fa3
                ON fa3.id = h.allocation_id AND fa3.session_id = {$sessEsc} AND fa3.branch_id = {$branchEsc}
                {$termWhereFA3}
            INNER JOIN enroll e3
                ON e3.student_id = fa3.student_id AND e3.session_id = {$sessEsc} AND e3.branch_id = {$branchEsc}
            WHERE h.status = 'paid'
            GROUP BY e3.class_id
        ) collected ON collected.class_id = c.id

        LEFT JOIN (
            SELECT e4.class_id,
                COUNT(DISTINCT fa4.student_id) AS students_paid
            FROM fee_payment_history h4
            INNER JOIN fee_allocation fa4
                ON fa4.id = h4.allocation_id AND fa4.session_id = {$sessEsc} AND fa4.branch_id = {$branchEsc}
                {$termWhereFA4}
            INNER JOIN enroll e4
                ON e4.student_id = fa4.student_id AND e4.session_id = {$sessEsc} AND e4.branch_id = {$branchEsc}
            WHERE h4.status = 'paid'
            GROUP BY e4.class_id
        ) paid_stu ON paid_stu.class_id = c.id

        WHERE c.branch_id = {$branchEsc}
          AND enrolled.total_enrolled IS NOT NULL
          {$classFilter}
        ORDER BY c.name ASC";

        return $this->db->query($sql)->result_array();
    }

    public function getSectionFeesSummary($sessionID, $branchID, $term = '', $dateA = '', $dateB = '', $includePrevDue = false)
    {
        $sessEsc   = $this->db->escape((int)$sessionID);
        $branchEsc = $this->db->escape((int)$branchID);
        $dateA     = $dateA ?: date('Y-m-d');
        $dateB     = $dateB ?: date('Y-m-d');
        $dateAEsc  = $this->db->escape($dateA);
        $dateBEsc  = $this->db->escape($dateB);

        // Term filter: match by name prefix only; session_id on fee_allocation already
        // scopes to the correct year, making a year-in-name LIKE redundant (and broken
        // since school_year uses dashes but fee group names use slashes).
        $termSub   = '';
        $termWhereFA  = '';
        $termWherePA  = '';
        $termWherePB  = '';
        if (!empty($term)) {
            $termEsc     = $this->db->escape($term . '%');
            $termSub     = "(SELECT id FROM fee_groups WHERE name LIKE {$termEsc})";
            $termWhereFA = "AND fa.group_id IN {$termSub}";
            $termWherePA = "AND fa_pa.group_id IN {$termSub}";
            $termWherePB = "AND fa_pb.group_id IN {$termSub}";
        }

        $prevDueSel = $includePrevDue ? '+ COALESCE(SUM(fa.prev_due), 0)' : '';

        $sql = "SELECT
            c.id    AS class_id,
            c.name  AS class_name,
            se.id   AS section_id,
            se.name AS section_name,
            COALESCE(enr.total_enrolled, 0)                                     AS total_enrolled,
            COALESCE(exp.total_expected, 0)                                     AS total_expected,
            COALESCE(col_a.paid_a, 0)                                           AS paid_a,
            COALESCE(exp.total_expected, 0) - COALESCE(col_a.paid_a, 0)        AS balance_a,
            COALESCE(col_b.paid_b, 0)                                           AS paid_b,
            COALESCE(exp.total_expected, 0) - COALESCE(col_b.paid_b, 0)        AS balance_b

        FROM class c
        INNER JOIN section se ON se.branch_id = {$branchEsc}

        LEFT JOIN (
            SELECT class_id, section_id, COUNT(DISTINCT student_id) AS total_enrolled
            FROM enroll
            WHERE session_id = {$sessEsc} AND branch_id = {$branchEsc}
            GROUP BY class_id, section_id
        ) enr ON enr.class_id = c.id AND enr.section_id = se.id

        LEFT JOIN (
            SELECT e2.class_id, e2.section_id,
                COALESCE(SUM(fgd_agg.group_total), 0) {$prevDueSel} AS total_expected
            FROM fee_allocation fa
            INNER JOIN enroll e2
                ON e2.student_id = fa.student_id
                AND e2.session_id = {$sessEsc}
                AND e2.branch_id  = {$branchEsc}
            LEFT JOIN (
                SELECT fee_groups_id, SUM(amount) AS group_total
                FROM fee_groups_details
                GROUP BY fee_groups_id
            ) fgd_agg ON fgd_agg.fee_groups_id = fa.group_id
            WHERE fa.session_id = {$sessEsc}
              AND fa.branch_id  = {$branchEsc}
              {$termWhereFA}
            GROUP BY e2.class_id, e2.section_id
        ) exp ON exp.class_id = c.id AND exp.section_id = se.id

        LEFT JOIN (
            SELECT e_a.class_id, e_a.section_id,
                SUM(h.amount + COALESCE(h.fine,0) - COALESCE(h.discount,0)) AS paid_a
            FROM fee_payment_history h
            INNER JOIN fee_allocation fa_pa
                ON fa_pa.id = h.allocation_id
                AND fa_pa.session_id = {$sessEsc}
                AND fa_pa.branch_id  = {$branchEsc}
                {$termWherePA}
            INNER JOIN enroll e_a
                ON e_a.student_id = fa_pa.student_id
                AND e_a.session_id = {$sessEsc}
                AND e_a.branch_id  = {$branchEsc}
            WHERE h.status = 'paid' AND h.date <= {$dateAEsc}
            GROUP BY e_a.class_id, e_a.section_id
        ) col_a ON col_a.class_id = c.id AND col_a.section_id = se.id

        LEFT JOIN (
            SELECT e_b.class_id, e_b.section_id,
                SUM(h.amount + COALESCE(h.fine,0) - COALESCE(h.discount,0)) AS paid_b
            FROM fee_payment_history h
            INNER JOIN fee_allocation fa_pb
                ON fa_pb.id = h.allocation_id
                AND fa_pb.session_id = {$sessEsc}
                AND fa_pb.branch_id  = {$branchEsc}
                {$termWherePB}
            INNER JOIN enroll e_b
                ON e_b.student_id = fa_pb.student_id
                AND e_b.session_id = {$sessEsc}
                AND e_b.branch_id  = {$branchEsc}
            WHERE h.status = 'paid' AND h.date <= {$dateBEsc}
            GROUP BY e_b.class_id, e_b.section_id
        ) col_b ON col_b.class_id = c.id AND col_b.section_id = se.id

        WHERE c.branch_id = {$branchEsc}
          AND enr.total_enrolled IS NOT NULL
        ORDER BY c.name ASC, se.name ASC";

        return $this->db->query($sql)->result_array();
    }


    public function getUnmatchedGatewayPayments($branchID)
    {
        // paystack_logs rows where no fee_payment_history row references this gateway reference.
        // These are DVA payments the webhook received but never distributed to fee allocations.
        $sql = "SELECT
                    pl.id            AS log_id,
                    pl.reference,
                    pl.paid_date,
                    pl.amount / 100  AS gateway_amount,
                    pl.customer_email,
                    pl.authorization_sender_name,
                    pl.authorization_narration,
                    pl.status
                FROM paystack_logs pl
                WHERE pl.status = 'success'
                  AND NOT EXISTS (
                      SELECT 1 FROM fee_payment_history fph
                      WHERE fph.gateway_reference LIKE CONCAT(pl.reference, '%')
                  )
                ORDER BY pl.paid_date DESC
                LIMIT 200";
        return $this->db->query($sql)->result_array();
    }

    /**
     * Returns every fee allocation with an outstanding balance > 0.
     * Used for the "Session Outstanding" report and for the production SQL export.
     *
     * @param int    $branchID   Required – school branch
     * @param int    $sessionID  0 = all sessions, otherwise filter by session
     * @param string $term       '' = all, '1ST TERM', '2ND TERM', or '3RD TERM'
     * @param int    $classID    0 = all classes
     * @param int    $sectionID  0 = all sections
     */
    public function getSessionOutstandingReport($branchID, $sessionID = 0, $term = '', $classID = 0, $sectionID = 0)
    {
        $branchID  = (int) $branchID;
        $sessionID = (int) $sessionID;
        $classID   = (int) $classID;
        $sectionID = (int) $sectionID;

        $branchWhere  = $branchID  ? "AND fa.branch_id  = {$branchID}"  : '';
        $sessionWhere = $sessionID ? "AND fa.session_id = {$sessionID}" : '';
        $classWhere   = $classID   ? "AND e.class_id   = {$classID}"   : '';
        $sectionWhere = $sectionID ? "AND e.section_id = {$sectionID}" : '';

        $termWhere = '';
        if ($term === '1ST TERM') {
            $termWhere = "AND (fg.name LIKE '1ST TERM%' OR fg.name LIKE '1st TERM%')";
        } elseif ($term === '2ND TERM') {
            $termWhere = "AND (fg.name LIKE '2ND TERM%' OR fg.name LIKE '2nd TERM%')";
        } elseif ($term === '3RD TERM') {
            $termWhere = "AND (fg.name LIKE '3RD TERM%' OR fg.name LIKE '3rd TERM%')";
        }

        $sql = "
            SELECT
                fa.id                             AS allocation_id,
                s.id                              AS student_id,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.register_no,
                cl.name                           AS class_name,
                sec.name                          AS section_name,
                sy.school_year                    AS session_label,
                CASE
                    WHEN fg.name LIKE '1ST TERM%' OR fg.name LIKE '1st TERM%' THEN '1st Term'
                    WHEN fg.name LIKE '2ND TERM%' OR fg.name LIKE '2nd TERM%' THEN '2nd Term'
                    WHEN fg.name LIKE '3RD TERM%' OR fg.name LIKE '3rd TERM%' THEN '3rd Term'
                    ELSE 'Full Year'
                END                               AS term,
                ROUND(COALESCE(fgd_sum.total_charged, 0), 2) AS fee_charged,
                ROUND(fa.prev_due, 2)             AS carried_forward,
                ROUND(COALESCE(fph_sum.total_paid, 0), 2)    AS total_paid,
                ROUND(
                    COALESCE(fgd_sum.total_charged, 0)
                    + fa.prev_due
                    - COALESCE(fph_sum.total_paid, 0)
                , 2)                              AS outstanding
            FROM fee_allocation fa
            JOIN fee_groups fg
                ON fg.id = fa.group_id
            JOIN (
                SELECT fee_groups_id, SUM(amount) AS total_charged
                FROM fee_groups_details
                GROUP BY fee_groups_id
            ) fgd_sum ON fgd_sum.fee_groups_id = fa.group_id
            LEFT JOIN (
                SELECT allocation_id, SUM(amount - discount) AS total_paid
                FROM fee_payment_history
                WHERE status = 'paid'
                GROUP BY allocation_id
            ) fph_sum ON fph_sum.allocation_id = fa.id
            JOIN schoolyear sy  ON sy.id  = fa.session_id
            JOIN student s      ON s.id   = fa.student_id
            JOIN enroll e       ON e.student_id = fa.student_id
            JOIN class cl       ON cl.id  = e.class_id
            JOIN section sec    ON sec.id = e.section_id
            WHERE 1=1
              {$branchWhere}
              {$sessionWhere}
              {$termWhere}
              {$classWhere}
              {$sectionWhere}
            HAVING outstanding > 0
            ORDER BY sy.school_year,
                     FIELD(
                       CASE WHEN fg.name LIKE '1ST TERM%' OR fg.name LIKE '1st TERM%' THEN '1ST TERM'
                            WHEN fg.name LIKE '2ND TERM%' OR fg.name LIKE '2nd TERM%' THEN '2ND TERM'
                            WHEN fg.name LIKE '3RD TERM%' OR fg.name LIKE '3rd TERM%' THEN '3RD TERM'
                            ELSE 'ZZZ' END,
                       '1ST TERM', '2ND TERM', '3RD TERM', 'ZZZ'
                     ),
                     cl.name, sec.name, s.last_name, s.first_name
        ";

        return $this->db->query($sql)->result_array();
    }
}