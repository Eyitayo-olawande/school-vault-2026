<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Fees_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('sms_model');
    }

    public function getPreviousSessionBalance($enrollID = '', $session_id = '', $with_fine = 0)
    {
        $total_balance = 0;
        $total_fine = 0;
        $variable = $this->db->where(array('student_id' => $enrollID, 'session_id' => $session_id))->get('fee_allocation')->result();
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

    /**
     * Batch outstanding balance calculation — replaces N+1 per-student calls on the promotion page.
     * Returns array keyed by enroll_id:
     *   [ enroll_id => [ 'total' => float, 'breakdown' => [ ['group_id'=>int, 'group_name'=>str, 'outstanding'=>float], ... ] ] ]
     * Fine is calculated per allocation type and included in the total when $with_fine = 1.
     */
    public function getOutstandingBalancesBatch(array $enrollIDs, $sessionID, $with_fine = 0)
    {
        if (empty($enrollIDs)) return [];

        $ids = implode(',', array_map('intval', $enrollIDs));

        // Single query: charged - paid - discount per (allocation, fee_type)
        $sql = "
            SELECT
                fa.student_id                                              AS enroll_id,
                fa.id                                                      AS allocation_id,
                fa.group_id,
                fg.name                                                    AS group_name,
                ft.system                                                  AS is_system,
                fgd.fee_type_id,
                fgd.due_date,
                CASE WHEN ft.system = 1 THEN fa.prev_due
                     ELSE IFNULL(fgd.amount, 0) END                       AS charged,
                IFNULL(SUM(fph.amount),    0)                             AS paid,
                IFNULL(SUM(fph.discount),  0)                             AS discounted,
                IFNULL(SUM(fph.fine),      0)                             AS fine_paid
            FROM fee_allocation fa
            INNER JOIN fee_groups           fg  ON fg.id            = fa.group_id
            INNER JOIN fee_groups_details   fgd ON fgd.fee_groups_id = fa.group_id
            INNER JOIN fees_type            ft  ON ft.id            = fgd.fee_type_id
            LEFT  JOIN fee_payment_history  fph ON fph.allocation_id = fa.id
                                               AND fph.type_id      = fgd.fee_type_id
            WHERE fa.student_id IN ($ids)
              AND fa.session_id  = " . (int)$sessionID . "
            GROUP BY fa.id, fgd.fee_type_id
        ";
        $rows = $this->db->query($sql)->result_array();

        // Accumulate per student per fee_group
        $raw = [];
        foreach ($rows as $r) {
            $eid         = (int)$r['enroll_id'];
            $gid         = (int)$r['group_id'];
            $outstanding = (float)$r['charged'] - (float)$r['paid'] - (float)$r['discounted'];
            if ($outstanding <= 0) continue;

            // Fine for overdue items
            $fine = 0;
            if ($with_fine && !empty($r['due_date']) && strtotime($r['due_date']) < time()) {
                $fine = $this->feeFineCalculation($r['allocation_id'], $r['fee_type_id']);
                $fine = max(0, $fine - (float)$r['fine_paid']);
            }

            if (!isset($raw[$eid])) $raw[$eid] = [];
            if (!isset($raw[$eid][$gid])) {
                $raw[$eid][$gid] = [
                    'group_id'    => $gid,
                    'group_name'  => $r['group_name'],
                    'outstanding' => 0.0,
                    'fine'        => 0.0,
                ];
            }
            $raw[$eid][$gid]['outstanding'] += $outstanding;
            $raw[$eid][$gid]['fine']        += $fine;
        }

        $result = [];
        foreach ($raw as $eid => $groups) {
            $total = 0.0;
            $breakdown = [];
            foreach ($groups as $g) {
                $amount = $g['outstanding'] + ($with_fine ? $g['fine'] : 0);
                if ($amount <= 0) continue;
                $breakdown[] = [
                    'group_id'    => $g['group_id'],
                    'group_name'  => $g['group_name'],
                    'outstanding' => round($amount, 2),
                ];
                $total += $amount;
            }
            if ($total > 0) {
                $result[$eid] = ['total' => round($total, 2), 'breakdown' => $breakdown];
            }
        }
        return $result;
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
        $sql = "SELECT e.*, s.photo, CONCAT_WS(' ',s.first_name, s.last_name) as fullname, s.gender, s.register_no, s.parent_id, s.email, s.mobileno, IFNULL(fa.id, 0) as allocation_id FROM enroll as e INNER JOIN student as s ON e.student_id = s.id LEFT JOIN login_credential as l ON l.user_id = s.id AND l.role = '7' LEFT JOIN fee_allocation as fa ON fa.student_id = e.id AND fa.group_id = " . $this->db->escape($groupID) . " AND fa.session_id= " . $this->db->escape(get_session_id()) . " WHERE e.class_id = " . $this->db->escape($classID) . " AND e.branch_id = " . $this->db->escape($branchID) . " AND e.session_id = " . $this->db->escape(get_session_id());
        if ($sectionID != 'all') {
            $sql .= " AND e.section_id =" . $this->db->escape($sectionID);
        }
        $sql .= " AND l.active = '1' ORDER BY s.id ASC";
        return $this->db->query($sql)->result_array();
    }

    public function getInvoiceStatus($enrollID = '')
    {

        $status = "";
        $sessionID = get_session_id();
        // Use CASE to avoid multiplying prev_due by number of fee_groups_details rows:
        // for system types fgd.amount=0 and prev_due holds the amount; for regular types prev_due=0.
        $sql = "SELECT SUM(CASE WHEN `fees_type`.`system`=1 THEN `fee_allocation`.`prev_due` ELSE `fee_groups_details`.`amount` END) as `total`, min(`fee_allocation`.`id`) as `inv_no` FROM `fee_allocation` LEFT JOIN `fee_groups_details` ON `fee_groups_details`.`fee_groups_id` = `fee_allocation`.`group_id` LEFT JOIN `fees_type` ON `fees_type`.`id` = `fee_groups_details`.`fee_type_id` WHERE `fee_allocation`.`student_id` = " . $this->db->escape($enrollID) . " AND `fee_allocation`.`session_id` = " . $this->db->escape($sessionID);
        $balance = $this->db->query($sql)->row_array();
        $invNo = empty($balance['inv_no']) ? 0 : str_pad($balance['inv_no'], 4, '0', STR_PAD_LEFT);

        // calculation total transport fee
        $this->db->select("IFNULL(SUM(transport_stoppage_point.route_fare), 0) as amount");
        $this->db->from('transport_fee_details');
        $this->db->join('transport_stoppage_point', 'transport_stoppage_point.id = transport_fee_details.stoppage_point_id', 'inner');
        $this->db->where('transport_fee_details.enroll_id', $enrollID);
        $trans_amount = $this->db->get()->row()->amount;

        // calculation payment history
        $this->db->select("IFNULL(SUM(fee_payment_history.amount), 0) as amount, IFNULL(SUM(fee_payment_history.discount), 0) as discount, IFNULL(SUM(fee_payment_history.fine), 0) as fine");
        $this->db->from("fee_payment_history");
        $this->db->join("fee_allocation", "fee_allocation.id = fee_payment_history.allocation_id", "left");
        $this->db->join("transport_fee_details", "transport_fee_details.id = fee_payment_history.transport_fee_details_id", "left");
        $this->db->where("fee_allocation.student_id", $enrollID);
        $this->db->where("fee_allocation.session_id", $sessionID);
        $this->db->or_group_start();
        $this->db->where("transport_fee_details.enroll_id", $enrollID);
        $this->db->group_end();
        $paid = $this->db->get()->row_array();

        if (($paid['amount'] + $paid['discount']) == 0) {
            $status = 'unpaid';
        } elseif (($balance['total'] + $trans_amount) == ($paid['amount'] + $paid['discount'])) {
            $status = 'total';
        } elseif (($paid['amount'] + $paid['discount']) > 0) {
            $status = 'partly';
        }
        return array('status' => $status, 'invoice_no' => $invNo);
    }

    public function getInvoiceDetails($enrollID = '')
    {
        $sql = "SELECT `fee_allocation`.`group_id`,`fee_allocation`.`prev_due`,`fee_allocation`.`id` as `allocation_id`, `fees_type`.`name`, `fees_type`.`system`, `fee_groups_details`.`amount`, `fee_groups_details`.`due_date`, `fee_groups_details`.`fee_type_id` FROM `fee_allocation` LEFT JOIN
        `fee_groups_details` ON `fee_groups_details`.`fee_groups_id` = `fee_allocation`.`group_id` LEFT JOIN `fees_type` ON `fees_type`.`id` = `fee_groups_details`.`fee_type_id` WHERE
        `fee_allocation`.`student_id` = " . $this->db->escape($enrollID) . " AND `fee_allocation`.`session_id` = " . $this->db->escape(get_session_id()) . " ORDER BY `fee_allocation`.`group_id` ASC, `fees_type`.`id` ASC";
        $student = array();
        $r = $this->db->query($sql)->result_array();
        foreach ($r as $key => $value) {
            if ($value['system'] == 1) {
                $value['amount'] = $value['prev_due'];
            }
            $student[] = $value;
        }
        return $student;
    }

    /**
     * Same as getInvoiceDetails(), but across every session rather than only the
     * active one, with the school year attached so the caller can group by it.
     *
     * Promotion moves a student's single enroll row forward, so once they are
     * promoted their earlier fees sit under a previous session_id and vanish
     * from any view filtered on the active session -- including fees that were
     * fully paid. enroll.id is stable across promotion, so the history is still
     * attached; it just needs to be asked for without the session filter.
     *
     * Deliberately separate from getInvoiceDetails(): fee collection, invoice
     * printing and the parent portal must stay scoped to the current session,
     * or a student would be billed for previous years.
     */
    public function getInvoiceDetailsAllSessions($enrollID = '')
    {
        $sql = "SELECT fa.group_id, fa.prev_due, fa.id AS allocation_id, fa.session_id,
                       sy.school_year, fg.name AS group_name,
                       ft.name, ft.system, fgd.amount, fgd.due_date, fgd.fee_type_id
                FROM fee_allocation fa
                LEFT JOIN fee_groups_details fgd ON fgd.fee_groups_id = fa.group_id
                LEFT JOIN fees_type   ft ON ft.id = fgd.fee_type_id
                LEFT JOIN fee_groups  fg ON fg.id = fa.group_id
                LEFT JOIN schoolyear  sy ON sy.id = fa.session_id
                WHERE fa.student_id = " . $this->db->escape($enrollID) . "
                ORDER BY fa.session_id DESC, fa.group_id ASC, ft.id ASC";

        $rows = array();
        foreach ($this->db->query($sql)->result_array() as $value) {
            if ($value['system'] == 1) {
                $value['amount'] = $value['prev_due'];
            }
            $rows[] = $value;
        }
        return $rows;
    }

    public function getInvoiceBasic($enrollID = '')
    {
        $sessionID = get_session_id();
        $this->db->select('s.id,s.register_no,e.branch_id,e.id as enroll_id,s.first_name,s.last_name,s.stoppage_point_id,s.email as student_email,s.current_address as student_address,c.name as class_name,b.school_name,b.email as school_email,b.mobileno as school_mobileno,b.address as school_address,p.father_name,se.name as section_name');
        $this->db->from('enroll as e');
        $this->db->join('student as s', 's.id = e.student_id', 'inner');
        $this->db->join('class as c', 'c.id = e.class_id', 'left');
        $this->db->join('section as se', 'se.id = e.section_id', 'left');
        $this->db->join('parent as p', 'p.id = s.parent_id', 'left');
        $this->db->join('branch as b', 'b.id = e.branch_id', 'left');
        if (!is_superadmin_loggedin()) {
            $this->db->where('e.branch_id', get_loggedin_branch_id());
        }
        $this->db->where('e.id', $enrollID);
        $this->db->where('e.session_id', $sessionID);
        return $this->db->get()->row_array();
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
        return $this->db->get()->result_array();
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
            $this->db->where('id', $data['type_id']);
            $this->db->update('fees_type', $arrayData);
        }
    }

    public function getInvoiceList()
    {
        $branchID = $this->application_model->get_branch_id();
        $class_id = $this->input->post('class_id');
        $section_id = $this->input->post('section_id');

        // getting a list of classes assigned to a teacher
        $assigned_cs_list = $this->app_lib->get_ownClassSection();

        $this->datatables->select('e.id as enroll_id,e.student_id,e.roll,s.first_name,s.last_name,s.register_no,s.mobileno,c.name as class_name,se.name as section_name');
        $this->datatables->from('fee_allocation as fa');
        $this->datatables->join('enroll as e', 'e.student_id = fa.student_id', 'inner');
        $this->datatables->join('student as s', 's.id = e.student_id', 'left');
        $this->datatables->join('class as c', 'c.id = e.class_id', 'left');
        $this->datatables->join('section as se', 'se.id = e.section_id', 'left');
        $this->datatables->where('fa.session_id', get_session_id());
        // student.active column does not exist in this schema — omitted
        $this->datatables->where('fa.branch_id', $branchID);
        if (!empty($class_id)) {
            $this->datatables->where('e.class_id', $class_id);
        }
        if (!empty($section_id)) {
            $this->datatables->where('e.section_id', $section_id);
        }
        // filter classes by teacher assigned classes
        if ($assigned_cs_list != false && !empty($assigned_cs_list)) {
            $this->datatables->group_start();
            foreach ($assigned_cs_list as $class_key => $class_value) {
                foreach ($class_value as $section_key => $section_value) {
                    $this->datatables->or_group_start();
                    $this->datatables->where('e.class_id', $class_key);
                    $this->datatables->where('e.section_id', $section_value);
                    $this->datatables->group_end();

                }
            }
            $this->datatables->group_end();
        }
        $this->datatables->search_value('s.first_name,s.last_name,s.register_no,s.mobileno');
        $this->datatables->column_order('e.id,s.first_name,c.id,se.id,s.register_no,e.roll,s.mobileno,fa.id');
        $this->datatables->group_by('fa.student_id');
        $this->datatables->order_by('fa.id', 'asc');
        $results = $this->datatables->generate();
        $records = array();
        $records = json_decode($results);
        $data = array();
        foreach ($records->data as $key => $record) {
            $full_name = $record->first_name . ' ' . $record->last_name;
            // actions btn
            $actions = '<button type="button" data-loading-text="<i class=\'fas fa-spinner fa-spin\'></i>" data-placement="top" data-toggle="tooltip" data-original-title="' . translate('email') . " " . translate('invoice') . '" class="btn btn-default icon btn-circle" onclick="pdf_sendByemail(' . "'" . $record->enroll_id . "'" . ', this)"><i class="fa-solid fa-envelope"></i></button>';
            if (get_permission('collect_fees', 'is_add')) {
                $actions .= '<a href="' . base_url('fees/invoice/' . $record->enroll_id) . '" class="btn btn-default btn-circle"><i class="far fa-arrow-alt-circle-right"></i> ' . translate('collect') . '</a>';
            }
            if (get_permission('invoice', 'is_delete')) {
                $actions .= btn_delete('fees/invoice_delete/' . $record->student_id);
            }

            // scholarship status for badge
            $scholarship = $this->getStudentScholarship($record->student_id);

            // dt-data array
            $row = array();
            $row[] = '<div class="checked-area"><div class="checkbox-replace"><label class="i-checks"><input type="checkbox" name="student_id[]" value="' . $record->enroll_id . '"><i></i></label></div></div>';
            $row[] = '<a class="hidden-print" href="' . base_url('student/profile/') . $record->enroll_id . '">' . $full_name . '</a>' . '<span class="visible-print">' . $full_name . '</span>';
            $row[] = $record->class_name;
            $row[] = $record->section_name;
            $row[] = $record->register_no;
            $row[] = $record->roll;
            $row[] = $record->mobileno;
            // getting fees group list
            $feegroup = $this->getfeeGroup($record->student_id);
            $groupList = '';
            foreach ($feegroup as $key => $value) {
                $groupList .= "- " . $value['name'] . "<br>";
            }
            $row[] = $groupList;
            // fees status — scholarship overrides paid/unpaid display
            if (!empty($scholarship)) {
                $statusLabel = "<span class='value label label-scholarship'><i class='fas fa-graduation-cap'></i> " . htmlspecialchars($scholarship['scholarship_name']) . "</span>";
            } else {
                $labelmode = '';
                $status = $this->getInvoiceStatus($record->student_id)['status'];
                if ($status == 'unpaid') {
                    $status = translate('unpaid');
                    $labelmode = 'label-danger-custom';
                } elseif ($status == 'partly') {
                    $status = translate('partly_paid');
                    $labelmode = 'label-info-custom';
                } elseif ($status == 'total') {
                    $status = translate('total_paid');
                    $labelmode = 'label-success-custom';
                }
                $statusLabel = "<span class='value label " . $labelmode . " '>" . $status . "</span>";
            }
            $row[] = $statusLabel;
            $row[] = $actions;

            $data[] = $row;
        }
        $json_data = array(
            "draw" => intval($records->draw),
            "recordsTotal" => intval($records->recordsTotal),
            "recordsFiltered" => intval($records->recordsFiltered),
            "data" => $data,
        );
        return json_encode($json_data);
    }

    public function getDueInvoiceDT_list($class_id = '', $section_id = '', $feegroup_id = '', $fee_feetype_id = '')
    {
        $get_session_id = get_session_id();
        if ($feegroup_id == 'transport') {
            $this->datatables->select('IFNULL(SUM(h.amount), 0) as total_amount, IFNULL(SUM(h.discount), 0) as total_discount, sp.route_fare as full_amount,ff.due_date, e.student_id, e.id as enroll_id,e.roll, s.first_name, s.last_name, s.register_no, s.mobileno, c.name as class_name, se.name as section_name');
            $this->datatables->from('transport_fee_details as fa');
            $this->datatables->join('fee_payment_history as h', 'h.transport_fee_details_id = fa.id', 'left');
            $this->datatables->join('transport_stoppage_point as sp', 'sp.id = fa.stoppage_point_id', 'left');
            $this->datatables->join('transport_fee_fine as ff', 'ff.id = fa.transport_fee_fine_id', 'inner');
            $this->datatables->join('enroll as e', 'e.id = fa.enroll_id', 'inner');;
            $this->datatables->where('fa.transport_fee_fine_id', $fee_feetype_id);
            $this->datatables->where('sp.session_id', $get_session_id);
            $this->datatables->group_by('fa.enroll_id');
            $this->datatables->search_value('s.first_name,s.register_no,e.roll,s.mobileno,ff.due_date');
        } else {
            $this->datatables->select('IFNULL(SUM(h.amount), 0) as total_amount, IFNULL(SUM(h.discount), 0) as total_discount, gd.amount as full_amount, fa.prev_due as prev_due, gd.due_date, e.student_id, e.id as enroll_id,e.roll, s.first_name, s.last_name, s.register_no, s.mobileno, c.name as class_name, se.name as section_name');
            $this->datatables->from('fee_allocation as fa');
            $this->datatables->join('fee_payment_history as h', 'h.allocation_id = fa.id and h.type_id = ' . $this->db->escape($fee_feetype_id), 'left');
            $this->datatables->join('fee_groups_details as gd', 'gd.fee_groups_id = fa.group_id and gd.fee_type_id =' . $this->db->escape($fee_feetype_id), 'inner');
            $this->datatables->join('enroll as e', 'e.student_id = fa.student_id', 'inner');
            $this->datatables->where('fa.group_id', $feegroup_id);
            $this->datatables->where('fa.session_id', $get_session_id);
            $this->datatables->group_by('fa.student_id');
            $this->datatables->search_value('s.first_name,s.register_no,e.roll,s.mobileno,gd.due_date');
        }
        $this->datatables->join('student as s', 's.id = e.student_id', 'left');
        $this->datatables->join('class as c', 'c.id = e.class_id', 'left');
        $this->datatables->join('section as se', 'se.id = e.section_id', 'left');
        $this->datatables->where('e.class_id', $class_id);
        if ($section_id != 'all') {
            $this->datatables->where('e.section_id', $section_id);
        }
        $this->datatables->column_order('fa.id,s.first_name,s.register_no,e.roll,s.mobileno,enroll_id,due_date,full_amount,total_amount,total_discount,full_amount');
        $this->datatables->order_by('e.id', 'asc');
        $results = $this->datatables->generate();
        return $results;
    }

    public function getDueReport($class_id = '', $section_id = '', $term = '')
    {
        $this->db->select('fa.id as allocation_id,sum(gd.amount + fa.prev_due) as total_fees,MIN(gd.due_date) as due_date,e.id as enroll_id,e.student_id,e.roll,s.first_name,s.last_name,s.register_no,s.mobileno,c.name as class_name,se.name as section_name');
        $this->db->from('fee_allocation as fa');
        $this->db->join('fee_groups_details as gd', 'gd.fee_groups_id = fa.group_id', 'left');
        $this->db->join('enroll as e', 'e.student_id = fa.student_id', 'inner');
        $this->db->join('student as s', 's.id = e.student_id', 'left');
        $this->db->join('class as c', 'c.id = e.class_id', 'left');
        $this->db->join('section as se', 'se.id = e.section_id', 'left');
        $this->db->where('fa.session_id', get_session_id());
        $this->db->where('e.class_id', $class_id);
        if (!empty($section_id)) {
            $this->db->where('e.section_id', $section_id);
        }
        if (!empty($term)) {
            // Term filter: scope to fee_groups whose name starts with the term label.
            $termEsc = $this->db->escape($term . '%');
            $this->db->where("fa.group_id IN (SELECT id FROM fee_groups WHERE name LIKE {$termEsc})", null, false);
        }
        $this->db->group_by('fa.student_id');
        $this->db->order_by('e.id', 'asc');
        $result = $this->db->get()->result_array();
        foreach ($result as $key => $value) {
            $result[$key]['payment'] = $this->getPaymentDetails($value['student_id']);
        }
        return $result;
    }

    public function getPaymentDetails($student_id = '')
    {
        $this->db->select('IFNULL(SUM(amount), 0) as total_paid, IFNULL(SUM(discount), 0) as total_discount, IFNULL(SUM(fine), 0) as total_fine');
        $this->db->from('fee_allocation');
        $this->db->join('fee_payment_history', 'fee_payment_history.allocation_id = fee_allocation.id', 'left');
        $this->db->where('fee_allocation.student_id', $student_id);
        $this->db->where('fee_allocation.session_id', get_session_id());
        return $this->db->get()->row_array();
    }

    public function getStuPaymentHistory($classID = '', $SectionID = '', $paymentVia = '', $start = '', $end = '', $branchID = '', $onlyFine = false, $sessionID = null, $term = '')
    {
        $sessionID = $sessionID ?: get_session_id();
        $this->db->select('h.id as receipt_no,h.*,ft.name as type_name,e.student_id,e.roll,s.first_name,s.last_name,s.register_no,s.mobileno,c.name as class_name,se.name as section_name,pt.name as pay_via');
        $this->db->from('fee_payment_history as h');
        $this->db->join('fee_allocation as fa', 'fa.id = h.allocation_id', 'inner');
        $this->db->join('fees_type as ft', 'ft.id = h.type_id', 'left');
        $this->db->join('enroll as e', 'e.student_id = fa.student_id', 'inner');
        $this->db->join('student as s', 's.id = e.student_id', 'inner');
        $this->db->join('class as c', 'c.id = e.class_id', 'left');
        $this->db->join('section as se', 'se.id = e.section_id', 'left');
        $this->db->join('payment_types as pt', 'pt.id = h.pay_via', 'left');
        $this->db->where('fa.session_id', $sessionID);
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
        if (!empty($term)) {
            $termEsc = $this->db->escape($term . '%');
            $this->db->where("fa.group_id IN (SELECT id FROM fee_groups WHERE name LIKE {$termEsc})", null, false);
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

        if (moduleIsEnabled('transport') && $this->db->table_exists('transport_fee_details')) {
            $this->db->select('h.*,ff.month as type_name,e.student_id,e.roll,s.first_name,s.last_name,s.register_no,s.mobileno,c.name as class_name,se.name as section_name,pt.name as pay_via');
            $this->db->from('fee_payment_history as h');
            $this->db->join('transport_fee_details as fa', 'fa.id = h.transport_fee_details_id', 'inner');
            $this->db->join('transport_fee_fine as ff', 'ff.id = fa.transport_fee_fine_id', 'inner');
            $this->db->join('enroll as e', 'e.id = fa.enroll_id and e.session_id = ' . $this->db->escape($sessionID), 'inner');
            $this->db->join('student as s', 's.id = e.student_id', 'inner');
            $this->db->join('class as c', 'c.id = e.class_id', 'left');
            $this->db->join('section as se', 'se.id = e.section_id', 'left');
            $this->db->join('payment_types as pt', 'pt.id = h.pay_via', 'left');
            $this->db->where('ff.session_id', $sessionID);
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
            $result2 = $this->db->get()->result_array();
        } else {
            $result2 = [];
        }


        if (empty($result)) {
            $result_output = $result2;
        } elseif (empty($result2)) {
            $result_output = $result;
        } else {
            $result_output = array_merge($result, $result2);
        }


        return $result_output;
    }

    public function getStuPaymentReport($classID = '', $sectionID = '', $enrollID = '', $typeID = '', $start = '', $end = '', $branchID = '', $sessionID = null)
    {
        $sessionID = $sessionID ?: get_session_id();
        if (!empty($typeID)) {
            $type = explode("|", $typeID);
            $group = $type[0];
            $type_id = $type[1];

        }
        if (empty($group) || $group != "transport" ) {
            $this->db->select('h.*,gd.due_date,ft.name as type_name,e.student_id,e.roll,s.first_name,s.last_name,s.register_no,pt.name as pay_via');
            $this->db->from('fee_payment_history as h');
            $this->db->join('fee_allocation as fa', 'fa.id = h.allocation_id', 'inner');
            $this->db->join('fees_type as ft', 'ft.id = h.type_id', 'left');
            $this->db->join('fee_groups_details as gd', 'gd.fee_groups_id = fa.group_id and gd.fee_type_id = h.type_id', 'left');
            $this->db->join('enroll as e', 'e.student_id = fa.student_id', 'inner');
            $this->db->join('student as s', 's.id = e.student_id', 'inner');
            $this->db->join('payment_types as pt', 'pt.id = h.pay_via', 'left');
            $this->db->where('fa.session_id', $sessionID);
                $this->db->where('h.date >=', $start);
            $this->db->where('h.date <=', $end);
            $this->db->where('e.branch_id', $branchID);
            $this->db->where('e.class_id', $classID);
            if (!empty($type_id)) {
                $this->db->where('h.type_id', $type_id);
            }
            if (!empty($enrollID)) {
                $this->db->where('e.id', $enrollID);
            }
            $this->db->where('e.section_id', $sectionID);
            $this->db->order_by('h.id', 'asc');
            $result1 = $this->db->get()->result_array();
        } else {
             $result1 = [];
        }

        if ((empty($group) || $group == "transport") && $this->db->table_exists('transport_fee_details')) {
            $this->db->select('h.*,ff.due_date,ff.month as type_name,e.student_id,e.roll,s.first_name,s.last_name,s.register_no,pt.name as pay_via');
            $this->db->from('fee_payment_history as h');
            $this->db->join('transport_fee_details as fa', 'fa.id = h.transport_fee_details_id', 'inner');
            $this->db->join('transport_fee_fine as ff', 'ff.id = fa.transport_fee_fine_id', 'inner');
            $this->db->join('enroll as e', 'e.id = fa.enroll_id and e.session_id = ' . $this->db->escape($sessionID), 'inner');
            $this->db->join('student as s', 's.id = e.student_id', 'inner');
            $this->db->join('payment_types as pt', 'pt.id = h.pay_via', 'left');
            $this->db->where('ff.session_id', $sessionID);
                $this->db->where('h.date >=', $start);
            $this->db->where('h.date <=', $end);
            $this->db->where('e.branch_id', $branchID);
            $this->db->where('e.class_id', $classID);
            if (!empty($enrollID)) {
                $this->db->where('e.id', $enrollID);
            }
            if (!empty($type_id)) {
                $this->db->where('fa.transport_fee_fine_id', $type_id);
            }
            $this->db->where('e.section_id', $sectionID);
            $this->db->order_by('h.id', 'asc');
            $result2 = $this->db->get()->result_array();
        } else {
            $result2 = [];
        }
        $result_value2 = array_merge($result1, $result2);
        return $result_value2;
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
            if ($feesType[0] == 'transport') {
                $remainAmount = $this->getTransportBalance($feesType[1]);
            } else {
                $remainAmount = $this->getBalance($feesType[0], $feesType[1]);
            }
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
    public function saveTransaction($data = array(), $payment_historyID = '')
    {
        $branchID = $this->application_model->get_branch_id();
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

    /**
     * Carry forward outstanding fees from a previous session into the new session.
     * Creates one fee_allocation per original fee group that has an outstanding balance,
     * so the invoice in the new session shows a per-term breakdown instead of one lump sum.
     *
     * @param array $data  Keys: branch_id, session_id, student_id (new enroll_id), due_date, breakdown
     *                     breakdown: [ ['group_name' => str, 'outstanding' => float], ... ]
     *                     (Falls back to single "Due Record" lump sum when breakdown is absent.)
     */
    public function carryForwardDue($data = array())
    {
        $branchID  = $data['branch_id'];
        $sessionID = $data['session_id'];
        $enrollID  = $data['student_id'];
        $due_date  = $data['due_date'];

        $breakdown = $data['breakdown'] ?? null;

        // Legacy / lump-sum fallback
        if (empty($breakdown)) {
            $breakdown = [['group_name' => 'Previous Session Balance', 'outstanding' => $data['prev_due'] ?? 0]];
        }

        $this->_carryForwardBreakdown($branchID, $sessionID, $enrollID, $due_date, $breakdown);
    }

    /**
     * Internal: create one "Prev Balance: {term}" fee_group + fee_allocation per breakdown item.
     * Idempotent — updates existing allocations if re-run.
     */
    private function _carryForwardBreakdown($branchID, $sessionID, $enrollID, $due_date, array $breakdown)
    {
        $feeTypeID = $this->_ensureSystemFeeType($branchID);

        foreach ($breakdown as $item) {
            $amount = round((float)($item['outstanding'] ?? 0), 2);
            if ($amount <= 0) continue;

            // Strip nested "Prev Balance: " so repeated promotions don't stack the prefix
            $originalName = preg_replace('/^Prev Balance:\s*/i', '', $item['group_name']);
            $groupName    = 'Prev Balance: ' . $originalName;

            // Ensure fee_groups row
            $groupRow = ['name' => $groupName, 'branch_id' => $branchID, 'session_id' => $sessionID, 'system' => 1];
            $groupExists = $this->checkExistsData('fee_groups', $groupRow);
            if (!$groupExists) {
                $this->db->insert('fee_groups', $groupRow);
                $groupID = $this->db->insert_id();
            } else {
                $groupID = $groupExists->id;
            }

            // Ensure fee_groups_details row (amount=0; balance lives in fee_allocation.prev_due)
            $gdRow = ['fee_groups_id' => $groupID, 'fee_type_id' => $feeTypeID];
            if (!$this->checkExistsData('fee_groups_details', $gdRow)) {
                $gdRow['amount']   = 0;
                $gdRow['due_date'] = $due_date;
                $this->db->insert('fee_groups_details', $gdRow);
            }

            // Create or update fee_allocation
            $allocRow = ['student_id' => $enrollID, 'group_id' => $groupID, 'branch_id' => $branchID, 'session_id' => $sessionID];
            $allocExists = $this->checkExistsData('fee_allocation', $allocRow);
            if ($allocExists) {
                $this->db->where('id', $allocExists->id)->update('fee_allocation', ['prev_due' => $amount]);
            } else {
                $allocRow['prev_due'] = $amount;
                $this->db->insert('fee_allocation', $allocRow);
            }
        }
    }

    /**
     * Ensure the "Previous Session Balance" system fee type exists for this branch.
     * Returns its id.
     */
    private function _ensureSystemFeeType($branchID)
    {
        $typeRow = ['name' => 'Previous Session Balance', 'branch_id' => $branchID, 'system' => 1];
        $exists  = $this->checkExistsData('fees_type', $typeRow);
        if (!$exists) {
            $typeRow['fee_code'] = 'previous-balance';
            $this->db->insert('fees_type', $typeRow);
            return $this->db->insert_id();
        }
        return $exists->id;
    }

    public function checkExistsData($table = '', $data = array())
    {
        $this->db->where($data);
        $query = $this->db->get($table);
        if ($query->num_rows() > 0) {
            return $query->row();
        } else {
            return false;
        }
    }

    public function getStudentTransportFees($enroll_id = '', $stoppage_point_id = '')
    {
        if (!empty($enroll_id) && !empty($stoppage_point_id)) {
            $this->db->select("transport_fee_details.*,transport_fee_fine.month,transport_fee_fine.due_date,transport_stoppage_point.route_fare");
            $this->db->from('transport_fee_details');
            $this->db->join('transport_fee_fine', 'transport_fee_fine.id = transport_fee_details.transport_fee_fine_id', 'inner');
            $this->db->join('transport_stoppage_point', 'transport_stoppage_point.id = transport_fee_details.stoppage_point_id', 'inner');
            $this->db->where('transport_fee_details.enroll_id', $enroll_id);
            $this->db->where('transport_fee_details.stoppage_point_id', $stoppage_point_id);
            $this->db->order_by('transport_fee_details.id', 'asc');
            $result = $this->db->get()->result();
            return $result;
        }
        return [];
    }

    public function getStudentTransportFeeDeposit($transport_fee_details_id)
    {
        $sqlDeposit = "SELECT IFNULL(SUM(`amount`), '0.00') as `total_amount`, IFNULL(SUM(`discount`), '0.00') as `total_discount`, IFNULL(SUM(`fine`), '0.00') as `total_fine` FROM `fee_payment_history` WHERE `transport_fee_details_id` = " . $this->db->escape($transport_fee_details_id);
        return $this->db->query($sqlDeposit)->row_array();
    }

    public function transportFeeFineCalculation($fee_details_id = '')
    {
        $this->db->select('ff.*,fd.stoppage_point_id,ts.route_fare');
        $this->db->from('transport_fee_fine as ff');
        $this->db->join('transport_fee_details as fd', 'ff.id = fd.transport_fee_fine_id', 'inner');
        $this->db->join('transport_stoppage_point as ts', 'ts.id = fd.stoppage_point_id', 'inner');
        $this->db->where('fd.id', $fee_details_id);
        $getDB = $this->db->get()->row_array();
        if (is_array($getDB) && count($getDB)) {
            $dueDate = $getDB['due_date'];
            if ((strtotime($dueDate) < strtotime(date('Y-m-d'))) && !empty($getDB['fine_type'])) {
                $feeAmount = $getDB['route_fare'];
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

    public function getTransportBalance($fee_details_id)
    {
        $this->db->select("transport_fee_details.stoppage_point_id,transport_stoppage_point.route_fare");
        $this->db->from('transport_fee_details');
        $this->db->join('transport_stoppage_point', 'transport_stoppage_point.id = transport_fee_details.stoppage_point_id', 'inner');
        $this->db->where('transport_fee_details.id', $fee_details_id);
        $result = $this->db->get()->row();
        $route_fare = $result->route_fare;

        $this->db->select('IFNULL(sum(p.amount), 0) as total_amount,IFNULL(sum(p.discount), 0) as total_discount,IFNULL(sum(p.fine), 0) as total_fine');
        $this->db->from('fee_payment_history as p');
        $this->db->where('p.transport_fee_details_id', $fee_details_id);
        $paid = $this->db->get()->row_array();
        $balance = $route_fare - ($paid['total_amount'] + $paid['total_discount']);
        $total_fine = $paid['total_fine'];
        return array('balance' => $balance, 'fine' => $total_fine);
    }

    public function getTransportPaymentHistory($transport_fee_details_id)
    {
        $this->db->select('h.*,t.name,t.fee_code,pt.name as payvia');
        $this->db->from('fee_payment_history as h');
        $this->db->join('fees_type as t', 't.id = h.type_id', 'left');
        $this->db->join('payment_types as pt', 'pt.id = h.pay_via', 'left');
        $this->db->where('h.transport_fee_details_id', $transport_fee_details_id);
        $this->db->order_by('h.id', 'asc');
        return $this->db->get()->result_array();
    }

    /**
     * Session Outstanding Report — returns all students with an unpaid balance
     * for a given session/term/class/section.
     *
     * JOIN enroll ON student_id ONLY (no session_id condition) so promoted
     * students are not silently dropped from results.
     */
    public function getSessionOutstandingReport($branchID, $sessionID, $term = '', $classID = '', $sectionID = '')
    {
        $branchEsc  = $this->db->escape((int) $branchID);
        $sessionEsc = $this->db->escape((int) $sessionID);

        $termJoin = '';
        if (!empty($term)) {
            $termEsc  = $this->db->escape($term . '%');
            $termJoin = "INNER JOIN fee_groups fg_t ON fg_t.id = fa.group_id AND fg_t.name LIKE {$termEsc}";
        }

        $classWhere   = (!empty($classID))   ? "AND e.class_id   = " . $this->db->escape((int) $classID)   : '';
        $sectionWhere = (!empty($sectionID)) ? "AND e.section_id = " . $this->db->escape((int) $sectionID) : '';

        $sql = "
            SELECT
                s.id                                                 AS student_id,
                CONCAT(s.first_name, ' ', s.last_name)              AS student_name,
                s.register_no,
                c.name                                               AS class_name,
                sec.name                                             AS section_name,
                sy.school_year                                       AS session_label,
                CASE
                    WHEN fg.name LIKE '1ST TERM%'  THEN '1st Term'
                    WHEN fg.name LIKE '2ND TERM%'  THEN '2nd Term'
                    WHEN fg.name LIKE '3RD TERM%'  THEN '3rd Term'
                    ELSE 'Other'
                END                                                  AS term,
                IFNULL(fgd_sum.charged, 0)                          AS fee_charged,
                IFNULL(fa.prev_due, 0)                              AS carried_forward,
                IFNULL(fph_sum.paid,    0)                          AS total_paid,
                (IFNULL(fgd_sum.charged, 0) + IFNULL(fa.prev_due, 0)
                 - IFNULL(fph_sum.paid, 0))                        AS outstanding
            FROM fee_allocation fa
            {$termJoin}
            INNER JOIN enroll      e   ON e.id         = fa.student_id
            INNER JOIN student     s   ON s.id         = e.student_id
            INNER JOIN class       c   ON c.id         = e.class_id
            INNER JOIN section     sec ON sec.id       = e.section_id
            LEFT  JOIN fee_groups  fg  ON fg.id        = fa.group_id
            LEFT  JOIN schoolyear  sy  ON sy.id        = fa.session_id
            LEFT JOIN (
                SELECT fee_groups_id, SUM(amount) AS charged
                FROM   fee_groups_details
                GROUP  BY fee_groups_id
            ) fgd_sum ON fgd_sum.fee_groups_id = fa.group_id
            LEFT JOIN (
                SELECT allocation_id, SUM(amount + discount) AS paid
                FROM   fee_payment_history
                GROUP  BY allocation_id
            ) fph_sum ON fph_sum.allocation_id = fa.id
            WHERE fa.session_id = {$sessionEsc}
              AND e.branch_id   = {$branchEsc}
              {$classWhere}
              {$sectionWhere}
            HAVING outstanding > 0
            ORDER BY c.name, sec.name, student_name
        ";

        return $this->db->query($sql)->result_array();
    }

    /**
     * Section-wise Fees Summary — dual-date comparison report.
     * Returns per-section totals for two date checkpoints (col_a ≤ dateA, col_b ≤ dateB).
     */
    public function getSectionFeesSummary($sessionID, $branchID, $term = '', $dateA = '', $dateB = '', $includePrevDue = 0)
    {
        $sessionEsc = $this->db->escape((int) $sessionID);
        $branchEsc  = $this->db->escape((int) $branchID);
        $dateAEsc   = $this->db->escape($dateA);
        $dateBEsc   = $this->db->escape($dateB);
        $prevDue    = $includePrevDue ? '+ IFNULL(fa.prev_due,0)' : '';

        $termJoin = '';
        if (!empty($term)) {
            $termEsc  = $this->db->escape($term . '%');
            $termJoin = "INNER JOIN fee_groups fg_t ON fg_t.id = fa.group_id AND fg_t.name LIKE {$termEsc}";
        }

        $sql = "
            SELECT
                c.id     AS class_id,
                c.name   AS class_name,
                sec.name AS section_name,
                COUNT(DISTINCT e.id)                                                 AS total_enrolled,
                SUM(IFNULL(fgd.charged,0) {$prevDue})                                AS total_expected,
                IFNULL(SUM(CASE WHEN fph.pay_date <= {$dateAEsc} THEN fph.net ELSE 0 END), 0) AS paid_a,
                IFNULL(SUM(CASE WHEN fph.pay_date <= {$dateBEsc} THEN fph.net ELSE 0 END), 0) AS paid_b,
                SUM(IFNULL(fgd.charged,0) {$prevDue})
                  - IFNULL(SUM(CASE WHEN fph.pay_date <= {$dateAEsc} THEN fph.net ELSE 0 END),0) AS balance_a,
                SUM(IFNULL(fgd.charged,0) {$prevDue})
                  - IFNULL(SUM(CASE WHEN fph.pay_date <= {$dateBEsc} THEN fph.net ELSE 0 END),0) AS balance_b
            FROM fee_allocation fa
            {$termJoin}
            INNER JOIN enroll  e   ON e.id   = fa.student_id
            INNER JOIN student s   ON s.id   = e.student_id
            INNER JOIN class   c   ON c.id   = e.class_id
            INNER JOIN section sec ON sec.id = e.section_id
            LEFT JOIN (
                SELECT fee_groups_id, SUM(amount) AS charged
                FROM fee_groups_details
                GROUP BY fee_groups_id
            ) fgd ON fgd.fee_groups_id = fa.group_id
            LEFT JOIN (
                SELECT allocation_id, SUM(amount + discount) AS net, date AS pay_date
                FROM fee_payment_history
                GROUP BY allocation_id, date
            ) fph ON fph.allocation_id = fa.id
            WHERE fa.session_id = {$sessionEsc}
              AND e.branch_id   = {$branchEsc}
            GROUP BY c.id, sec.id
            ORDER BY c.name, sec.name
        ";

        return $this->db->query($sql)->result_array();
    }

    public function getStudentFeesSummary($classID, $sectionID, $branchID, $sessionID, $term = '')
    {
        $sessionEsc = $this->db->escape((int) $sessionID);
        $branchEsc  = $this->db->escape((int) $branchID);

        $termWhere = '';
        if (!empty($term)) {
            $termEsc   = $this->db->escape($term . '%');
            $termWhere = "AND fa.group_id IN (SELECT id FROM fee_groups WHERE name LIKE {$termEsc})";
        }

        $classWhere   = !empty($classID)   ? "AND e.class_id = "   . $this->db->escape((int) $classID)   : '';
        $sectionWhere = !empty($sectionID) ? "AND e.section_id = " . $this->db->escape((int) $sectionID) : '';

        $sql = "
            SELECT
                s.id                                               AS student_id,
                s.first_name, s.last_name, s.register_no,
                e.roll,
                sec.name                                           AS section_name,
                c.name                                             AS class_name,
                SUM(IFNULL(fgd_sum.charged, 0) + IFNULL(fa.prev_due, 0)) AS expected,
                IFNULL(SUM(fph_sum.paid), 0)                       AS net_paid,
                SUM(IFNULL(fgd_sum.charged, 0) + IFNULL(fa.prev_due, 0))
                    - IFNULL(SUM(fph_sum.paid), 0)                 AS balance
            FROM fee_allocation fa
            LEFT JOIN (
                SELECT fee_groups_id, SUM(amount) AS charged
                FROM   fee_groups_details
                GROUP  BY fee_groups_id
            ) fgd_sum ON fgd_sum.fee_groups_id = fa.group_id
            LEFT JOIN (
                SELECT allocation_id, SUM(amount + discount) AS paid
                FROM   fee_payment_history
                GROUP  BY allocation_id
            ) fph_sum ON fph_sum.allocation_id = fa.id
            INNER JOIN enroll   e   ON e.id   = fa.student_id
            INNER JOIN student  s   ON s.id   = e.student_id
            INNER JOIN class    c   ON c.id   = e.class_id
            INNER JOIN section  sec ON sec.id = e.section_id
            WHERE fa.session_id = {$sessionEsc}
              AND e.branch_id   = {$branchEsc}
              {$classWhere}
              {$sectionWhere}
              {$termWhere}
            GROUP BY e.id
            ORDER BY c.name, sec.name, e.roll
        ";

        return $this->db->query($sql)->result_array();
    }

    public function getClasswiseFeesSummary($sessionID, $branchID, $classID = '', $term = '')
    {
        $sessionEsc = $this->db->escape((int) $sessionID);
        $branchEsc  = $this->db->escape((int) $branchID);

        $termWhere  = '';
        if (!empty($term)) {
            $termEsc   = $this->db->escape($term . '%');
            $termWhere = "AND fa.group_id IN (SELECT id FROM fee_groups WHERE name LIKE {$termEsc})";
        }
        $classWhere = !empty($classID) ? "AND e.class_id = " . $this->db->escape((int) $classID) : '';

        $rows = $this->db->query("
            SELECT
                c.id   AS class_id,
                c.name AS class_name,
                stu.student_id,
                stu.expected,
                stu.collected
            FROM (
                SELECT
                    fa.student_id,
                    SUM(IFNULL(fgd.charged,0) + IFNULL(fa.prev_due,0)) AS expected,
                    IFNULL(SUM(fph.paid),0)                             AS collected
                FROM fee_allocation fa
                LEFT JOIN (
                    SELECT fee_groups_id, SUM(amount) AS charged
                    FROM   fee_groups_details GROUP BY fee_groups_id
                ) fgd ON fgd.fee_groups_id = fa.group_id
                LEFT JOIN (
                    SELECT allocation_id, SUM(amount+discount) AS paid
                    FROM   fee_payment_history GROUP BY allocation_id
                ) fph ON fph.allocation_id = fa.id
                WHERE fa.session_id = {$sessionEsc}
                  {$termWhere}
                GROUP BY fa.student_id
            ) stu
            INNER JOIN enroll e ON e.id = stu.student_id
            INNER JOIN class  c ON c.id = e.class_id
            WHERE e.branch_id = {$branchEsc}
              {$classWhere}
            ORDER BY c.name
        ")->result_array();

        // Aggregate by class in PHP to avoid nested GROUP BY issues
        $classes = [];
        foreach ($rows as $r) {
            $cid = $r['class_id'];
            if (!isset($classes[$cid])) {
                $classes[$cid] = [
                    'class_id'        => $cid,
                    'class_name'      => $r['class_name'],
                    'total_enrolled'  => 0,
                    'total_expected'  => 0,
                    'total_collected' => 0,
                    'students_paid'   => 0,
                    'students_not_paid' => 0,
                ];
            }
            $classes[$cid]['total_enrolled']++;
            $classes[$cid]['total_expected']  += $r['expected'];
            $classes[$cid]['total_collected'] += $r['collected'];
            if ($r['expected'] <= $r['collected']) {
                $classes[$cid]['students_paid']++;
            } else {
                $classes[$cid]['students_not_paid']++;
            }
        }
        foreach ($classes as &$cls) {
            $cls['total_outstanding'] = $cls['total_expected'] - $cls['total_collected'];
        }
        return array_values($classes);
    }

    public function getBranchFeesReport($sessionID, $outstandingOnly = false)
    {
        $sessionEsc = $this->db->escape((int) $sessionID);

        $rows = $this->db->query("
            SELECT
                e.branch_id,
                b.name AS branch_name,
                s.id   AS student_id,
                CONCAT(s.first_name,' ',s.last_name) AS student_name,
                s.register_no,
                c.name  AS class_name,
                sec.name AS section_name,
                SUM(CASE WHEN fg.name LIKE '1ST TERM%' THEN IFNULL(fgd.charged,0) ELSE 0 END) AS t1_charged,
                SUM(CASE WHEN fg.name LIKE '1ST TERM%' THEN IFNULL(fa.prev_due,0) ELSE 0 END) AS t1_prev_due,
                SUM(CASE WHEN fg.name LIKE '1ST TERM%' THEN IFNULL(fph.paid,0) ELSE 0 END)    AS t1_paid,
                SUM(CASE WHEN fg.name LIKE '2ND TERM%' THEN IFNULL(fgd.charged,0) ELSE 0 END) AS t2_charged,
                SUM(CASE WHEN fg.name LIKE '2ND TERM%' THEN IFNULL(fa.prev_due,0) ELSE 0 END) AS t2_prev_due,
                SUM(CASE WHEN fg.name LIKE '2ND TERM%' THEN IFNULL(fph.paid,0) ELSE 0 END)    AS t2_paid,
                SUM(CASE WHEN fg.name LIKE '3RD TERM%' THEN IFNULL(fgd.charged,0) ELSE 0 END) AS t3_charged,
                SUM(CASE WHEN fg.name LIKE '3RD TERM%' THEN IFNULL(fa.prev_due,0) ELSE 0 END) AS t3_prev_due,
                SUM(CASE WHEN fg.name LIKE '3RD TERM%' THEN IFNULL(fph.paid,0) ELSE 0 END)    AS t3_paid
            FROM fee_allocation fa
            INNER JOIN enroll  e   ON e.id   = fa.student_id
            INNER JOIN student s   ON s.id   = e.student_id
            INNER JOIN class   c   ON c.id   = e.class_id
            INNER JOIN section sec ON sec.id = e.section_id
            INNER JOIN branch  b   ON b.id   = e.branch_id
            LEFT  JOIN fee_groups fg ON fg.id = fa.group_id
            LEFT  JOIN (
                SELECT fee_groups_id, SUM(amount) AS charged
                FROM   fee_groups_details GROUP BY fee_groups_id
            ) fgd ON fgd.fee_groups_id = fa.group_id
            LEFT  JOIN (
                SELECT allocation_id, SUM(amount+discount) AS paid
                FROM   fee_payment_history GROUP BY allocation_id
            ) fph ON fph.allocation_id = fa.id
            WHERE fa.session_id = {$sessionEsc}
            GROUP BY e.id
            ORDER BY b.name, c.name, sec.name, student_name
        ")->result_array();

        // Compute per-row outstanding and group by branch
        $allRows = [];
        foreach ($rows as $r) {
            $r['t1_outstanding']    = ($r['t1_charged'] + $r['t1_prev_due']) - $r['t1_paid'];
            $r['t2_outstanding']    = ($r['t2_charged'] + $r['t2_prev_due']) - $r['t2_paid'];
            $r['t3_outstanding']    = ($r['t3_charged'] + $r['t3_prev_due']) - $r['t3_paid'];
            $r['grand_outstanding'] = $r['t1_outstanding'] + $r['t2_outstanding'] + $r['t3_outstanding'];

            if ($outstandingOnly && $r['grand_outstanding'] <= 0) {
                continue;
            }

            $bid = $r['branch_id'];
            if (!isset($allRows[$bid])) {
                $allRows[$bid] = [
                    'branch_name' => $r['branch_name'],
                    'students'    => [],
                    'totals'      => [
                        't1_charged' => 0, 't1_paid' => 0, 't1_outstanding' => 0,
                        't2_charged' => 0, 't2_paid' => 0, 't2_outstanding' => 0,
                        't3_charged' => 0, 't3_paid' => 0, 't3_outstanding' => 0,
                        'grand_outstanding' => 0,
                    ],
                ];
            }
            $allRows[$bid]['students'][] = $r;
            $t = &$allRows[$bid]['totals'];
            $t['t1_charged']     += $r['t1_charged'] + $r['t1_prev_due'];
            $t['t1_paid']        += $r['t1_paid'];
            $t['t1_outstanding'] += $r['t1_outstanding'];
            $t['t2_charged']     += $r['t2_charged'] + $r['t2_prev_due'];
            $t['t2_paid']        += $r['t2_paid'];
            $t['t2_outstanding'] += $r['t2_outstanding'];
            $t['t3_charged']     += $r['t3_charged'] + $r['t3_prev_due'];
            $t['t3_paid']        += $r['t3_paid'];
            $t['t3_outstanding'] += $r['t3_outstanding'];
            $t['grand_outstanding'] += $r['grand_outstanding'];
            unset($t);
        }
        return $allRows;
    }

    public function getDiscountRegister($branchID, $sessionID, $start, $end, $classID = '', $sectionID = '')
    {
        $sessionID = $sessionID ?: get_session_id();
        $this->db->select('h.id as receipt_no, h.date, h.discount, h.amount, h.remarks,
            ft.name as type_name,
            s.first_name, s.last_name, s.register_no,
            c.name as class_name, sec.name as section_name,
            CASE WHEN h.collect_by = "online" THEN "Online"
                 WHEN h.collect_by = "wallet" THEN "DVA Wallet"
                 ELSE CONCAT(st.first_name," ",st.last_name)
            END as collected_by');
        $this->db->from('fee_payment_history h');
        $this->db->join('fee_allocation fa', 'fa.id = h.allocation_id', 'inner');
        $this->db->join('fees_type ft', 'ft.id = h.type_id', 'left');
        $this->db->join('enroll e', 'e.student_id = fa.student_id', 'inner');
        $this->db->join('student s', 's.id = e.student_id', 'inner');
        $this->db->join('class c', 'c.id = e.class_id', 'left');
        $this->db->join('section sec', 'sec.id = e.section_id', 'left');
        $this->db->join('staff st', 'st.id = h.collect_by', 'left');
        $this->db->where('h.discount >', 0);
        $this->db->where('fa.session_id', $sessionID);
        $this->db->where('e.branch_id', $branchID);
        $this->db->where('h.date >=', $start);
        $this->db->where('h.date <=', $end);
        if (!empty($classID)) {
            $this->db->where('e.class_id', $classID);
        }
        if (!empty($sectionID)) {
            $this->db->where('e.section_id', $sectionID);
        }
        $this->db->order_by('h.date ASC, s.last_name ASC');
        return $this->db->get()->result_array();
    }

    public function getCashflowReport($branchID, $sessionID, $start, $end, $groupBy = 'day')
    {
        $sessionID = $sessionID ?: get_session_id();
        $dateFmt = ($groupBy === 'month') ? '%Y-%m' : '%Y-%m-%d';

        $this->db->select("
            DATE_FORMAT(h.date, '{$dateFmt}') AS period,
            SUM(CASE WHEN h.collect_by = 'online' THEN h.amount + h.fine - h.discount ELSE 0 END) AS online_total,
            SUM(CASE WHEN h.collect_by = 'wallet' THEN h.amount + h.fine - h.discount ELSE 0 END) AS dva_total,
            SUM(CASE WHEN h.collect_by NOT IN ('online','wallet') THEN h.amount + h.fine - h.discount ELSE 0 END) AS cash_total,
            SUM(h.amount + h.fine - h.discount) AS grand_total,
            COUNT(DISTINCT h.id) AS transaction_count
        ", false);
        $this->db->from('fee_payment_history h');
        $this->db->join('fee_allocation fa', 'fa.id = h.allocation_id', 'inner');
        $this->db->join('enroll e', 'e.student_id = fa.student_id', 'inner');
        $this->db->where('fa.session_id', $sessionID);
        $this->db->where('e.branch_id', $branchID);
        $this->db->where('h.date >=', $start);
        $this->db->where('h.date <=', $end);
        $this->db->group_by("DATE_FORMAT(h.date, '{$dateFmt}')");
        $this->db->order_by("period ASC");
        return $this->db->get()->result_array();
    }

    // ── Scholarship methods ───────────────────────────────────────────────────

    public function getScholarshipTypes($branchID = 0)
    {
        return $this->db->select('st.*')
            ->from('scholarship_types st')
            ->group_start()
                ->where('st.branch_id', 0)
                ->or_where('st.branch_id', (int)$branchID)
            ->group_end()
            ->order_by('st.name')
            ->get()->result_array();
    }

    public function getStudentScholarship($student_id, $session_id = null)
    {
        if ($session_id === null) {
            $session_id = get_session_id();
        }
        return $this->db->select('ss.*, st.name as scholarship_name')
            ->from('student_scholarship ss')
            ->join('scholarship_types st', 'st.id = ss.scholarship_type_id', 'left')
            ->where('ss.student_id', (int)$student_id)
            ->where('ss.session_id', (int)$session_id)
            ->get()->row_array();
    }

    public function assignScholarship($student_id, $type_id, $session_id, $branch_id, $notes = '')
    {
        $existing = $this->getStudentScholarship($student_id, $session_id);
        if ($existing) {
            $this->db->where('id', $existing['id'])
                ->update('student_scholarship', [
                    'scholarship_type_id' => (int)$type_id,
                    'notes'               => $notes,
                    'updated_at'          => date('Y-m-d H:i:s'),
                ]);
        } else {
            $this->db->insert('student_scholarship', [
                'student_id'          => (int)$student_id,
                'scholarship_type_id' => (int)$type_id,
                'session_id'          => (int)$session_id,
                'branch_id'           => (int)$branch_id,
                'notes'               => $notes,
            ]);
        }
    }

    public function removeScholarship($student_id, $session_id)
    {
        $this->db->where('student_id', (int)$student_id)
            ->where('session_id', (int)$session_id)
            ->delete('student_scholarship');
    }

    public function saveScholarshipType($data)
    {
        if (!empty($data['id'])) {
            $this->db->where('id', (int)$data['id'])->update('scholarship_types', [
                'name'        => $data['name'],
                'description' => $data['description'] ?? '',
                'branch_id'   => (int)$data['branch_id'],
            ]);
        } else {
            $this->db->insert('scholarship_types', [
                'name'        => $data['name'],
                'description' => $data['description'] ?? '',
                'branch_id'   => (int)$data['branch_id'],
            ]);
            return $this->db->insert_id();
        }
    }

    public function deleteScholarshipType($id)
    {
        $this->db->where('id', (int)$id)->delete('scholarship_types');
    }
}
