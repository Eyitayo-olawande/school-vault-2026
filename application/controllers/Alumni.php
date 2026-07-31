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

        $sessionID = $this->input->get('session_id') ?: null;

        $sessions = $this->db->order_by('id', 'desc')->get('schoolyear')->result_array();

        $this->db->select(
            'e.id AS enroll_id, e.student_id, e.session_id, e.class_id, e.section_id,
             e.roll, e.is_alumni,
             s.first_name, s.last_name, s.register_no, s.photo, s.email, s.mobileno,
             s.birthday, s.admission_date,
             c.name AS class_name, sec.name AS section_name,
             sy.school_year,
             p.father_name AS guardian_name,
             ac.cleared_at, ac.issued_at,
             ac.cleared_by, ac.issued_by'
        );
        $this->db->from('enroll e');
        $this->db->join('student s',        'e.student_id = s.id',   'inner');
        $this->db->join('class c',          'e.class_id   = c.id',   'left');
        $this->db->join('section sec',      'e.section_id = sec.id', 'left');
        $this->db->join('schoolyear sy',    'e.session_id = sy.id',  'left');
        $this->db->join('parent p',         's.parent_id  = p.id',   'left');
        $this->db->join('alumni_clearance ac', 'ac.student_id = e.student_id', 'left');
        $this->db->where('e.is_alumni', 1);
        $this->db->where("(e.leave_type = 'graduated' OR e.leave_type IS NULL)", null, false);
        if (!is_superadmin_loggedin()) {
            $this->db->where('e.branch_id', get_loggedin_branch_id());
        }
        if (!empty($sessionID)) {
            $this->db->where('e.session_id', (int)$sessionID);
        }
        $this->db->order_by('sy.id DESC, s.first_name ASC');
        $alumni = $this->db->get()->result_array();

        // Students who left before completing — separate list, no certificate gating
        $this->db->select(
            'e.student_id, e.session_id, e.class_id, e.section_id,
             s.first_name, s.last_name, s.register_no, s.photo, s.mobileno,
             c.name AS class_name, sec.name AS section_name,
             sy.school_year, p.father_name AS guardian_name'
        );
        $this->db->from('enroll e');
        $this->db->join('student s',     'e.student_id = s.id',   'inner');
        $this->db->join('class c',       'e.class_id   = c.id',   'left');
        $this->db->join('section sec',   'e.section_id = sec.id', 'left');
        $this->db->join('schoolyear sy', 'e.session_id = sy.id',  'left');
        $this->db->join('parent p',      's.parent_id  = p.id',   'left');
        $this->db->where('e.is_alumni',   1);
        $this->db->where('e.leave_type',  'left');
        if (!is_superadmin_loggedin()) {
            $this->db->where('e.branch_id', get_loggedin_branch_id());
        }
        if (!empty($sessionID)) {
            $this->db->where('e.session_id', (int)$sessionID);
        }
        $this->db->order_by('sy.id DESC, s.first_name ASC');
        $leftStudents = $this->db->get()->result_array();

        // Batch-compute outstanding balance across ALL sessions per student
        $balanceMap = [];
        if (!empty($alumni)) {
            $ids = implode(',', array_map('intval', array_column($alumni, 'student_id')));
            $rows = $this->db->query("
                SELECT fa.student_id,
                    GREATEST(0,
                        IFNULL(SUM(IFNULL(fgd_agg.fgd_total, 0) + IFNULL(fa.prev_due, 0)), 0)
                        - IFNULL(SUM(IFNULL(fph_agg.paid_total, 0)), 0)
                    ) AS outstanding
                FROM fee_allocation fa
                LEFT JOIN (
                    SELECT fee_groups_id, SUM(amount) AS fgd_total
                    FROM fee_groups_details GROUP BY fee_groups_id
                ) fgd_agg ON fgd_agg.fee_groups_id = fa.group_id
                LEFT JOIN (
                    SELECT allocation_id, SUM(amount + discount) AS paid_total
                    FROM fee_payment_history GROUP BY allocation_id
                ) fph_agg ON fph_agg.allocation_id = fa.id
                WHERE fa.student_id IN ($ids)
                GROUP BY fa.student_id
            ")->result_array();
            $balanceMap = array_column($rows, 'outstanding', 'student_id');
        }

        foreach ($alumni as &$row) {
            $row['outstanding'] = (float)($balanceMap[$row['student_id']] ?? 0);
        }
        unset($row);

        $this->data['alumni']           = $alumni;
        $this->data['left_students']    = $leftStudents;
        $this->data['sessions']         = $sessions;
        $this->data['selected_session'] = $sessionID;
        $this->data['title']            = translate('manage_alumni');
        $this->data['sub_page']         = 'alumni/index';
        $this->data['main_menu']        = 'alumni';
        $this->load->view('layout/index', $this->data);
    }

    // Mark fees as cleared — only allowed when outstanding balance = 0
    public function mark_cleared()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        if (!get_permission('manage_alumni', 'is_add')) { ajax_access_denied(); }

        $studentID = (int)$this->input->post('student_id');
        if (!$studentID) {
            echo json_encode(['status' => 'fail', 'error' => 'Missing student_id']);
            return;
        }

        // Verify zero outstanding across all sessions
        $row = $this->db->query("
            SELECT GREATEST(0,
                IFNULL(SUM(IFNULL(fgd_agg.fgd_total,0) + IFNULL(fa.prev_due,0)),0)
                - IFNULL(SUM(IFNULL(fph_agg.paid_total,0)),0)
            ) AS outstanding
            FROM fee_allocation fa
            LEFT JOIN (
                SELECT fee_groups_id, SUM(amount) AS fgd_total
                FROM fee_groups_details GROUP BY fee_groups_id
            ) fgd_agg ON fgd_agg.fee_groups_id = fa.group_id
            LEFT JOIN (
                SELECT allocation_id, SUM(amount+discount) AS paid_total
                FROM fee_payment_history GROUP BY allocation_id
            ) fph_agg ON fph_agg.allocation_id = fa.id
            WHERE fa.student_id = $studentID
        ")->row_array();

        if (!empty($row) && (float)$row['outstanding'] > 0) {
            echo json_encode(['status' => 'fail', 'error' => 'Student still has outstanding balance of ' . number_format($row['outstanding'], 2)]);
            return;
        }

        $staffID = get_loggedin_id();
        $existing = $this->db->where('student_id', $studentID)->get('alumni_clearance')->row_array();
        if ($existing) {
            $this->db->where('student_id', $studentID)
                     ->update('alumni_clearance', ['cleared_by' => $staffID, 'cleared_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->db->insert('alumni_clearance', [
                'student_id' => $studentID,
                'cleared_by' => $staffID,
                'cleared_at' => date('Y-m-d H:i:s'),
            ]);
        }
        echo json_encode(['status' => 'success']);
    }

    // Mark certificate as physically issued — only after fee clearance
    public function mark_issued()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        if (!get_permission('manage_alumni', 'is_add')) { ajax_access_denied(); }

        $studentID = (int)$this->input->post('student_id');
        if (!$studentID) {
            echo json_encode(['status' => 'fail', 'error' => 'Missing student_id']);
            return;
        }

        $clearance = $this->db->where('student_id', $studentID)
                              ->where('cleared_at IS NOT NULL', null, false)
                              ->get('alumni_clearance')->row_array();

        if (empty($clearance)) {
            echo json_encode(['status' => 'fail', 'error' => 'Fees must be cleared before issuing certificate']);
            return;
        }

        $staffID = get_loggedin_id();
        $this->db->where('student_id', $studentID)
                 ->update('alumni_clearance', ['issued_by' => $staffID, 'issued_at' => date('Y-m-d H:i:s')]);

        echo json_encode(['status' => 'success']);
    }

    public function event()
    {
        if (!get_permission('alumni_events', 'is_view')) {
            access_denied();
        }
        $this->data['title']    = translate('alumni_events');
        $this->data['sub_page'] = 'alumni/events';
        $this->data['main_menu']= 'alumni';
        $this->load->view('layout/index', $this->data);
    }
}
