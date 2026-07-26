<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Qrcode_attendance extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('attendance_model');
        if (!moduleIsEnabled('attendance') || !$this->app_lib->isExistingAddon('qrcode')) {
            access_denied();
        }
    }

    public function camera_scan()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }
        $this->data['branch_id'] = $this->application_model->get_branch_id();
        $this->data['title']     = 'QR Attendance — Camera Scanner';
        $this->data['sub_page']  = 'qrcode_attendance/camera_scan';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    /** Generate and print QR code sheet for a class/section. */
    public function generate()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }

        // For superadmin, branch comes from POST (either the generate form or the
        // hidden form on the student list). For non-superadmin, use session branch.
        if (is_superadmin_loggedin()) {
            $branchID = (int)$this->input->post('branch_id') ?: null;
        } else {
            $branchID = get_loggedin_branch_id();
        }
        if ($_POST) {
            $classID   = (int)$this->input->post('class_id');
            $sectionID = (int)$this->input->post('section_id');
            $this->data['students']   = $this->_get_or_generate_tokens($classID, $sectionID, $branchID);
            $this->data['class_id']   = $classID;
            $this->data['section_id'] = $sectionID;
        }

        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'QR Attendance — Generate Class Sheet';
        $this->data['sub_page']  = 'qrcode_attendance/generate';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    /** Serve a single QR code image for a given student token. */
    public function qr_image($token = '')
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }

        $token = substr(preg_replace('/[^a-f0-9]/', '', strtolower($token)), 0, 64);
        if (empty($token)) {
            show_404();
        }

        // Verify token belongs to a student (superadmin sees all branches)
        $q = $this->db->select('s.id')
            ->from('student s')
            ->join('enroll e', 'e.student_id = s.id')
            ->where('s.qr_token', $token);
        if (!is_superadmin_loggedin()) {
            $q->where('e.branch_id', get_loggedin_branch_id());
        }
        if (!$q->get()->num_rows()) {
            show_404();
        }

        $this->load->library('Ciqrcode');
        ob_start();
        $this->ciqrcode->generate([
            'data'    => site_url('qrcode_attendance/scan/' . $token),
            'savename' => null,
            'level'   => 'L',
            'size'    => 6,
        ]);
        $imgData = ob_get_clean();

        header('Content-Type: image/png');
        header('Cache-Control: max-age=86400');
        echo $imgData;
        exit;
    }

    /**
     * Open a time-bounded QR scan session for a class/section/date.
     * Teacher calls this endpoint to start a scan window.
     */
    public function open_session()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            ajax_access_denied();
        }
        if (!$_POST) {
            show_404();
        }

        $branchID  = $this->application_model->get_branch_id();
        $classID   = (int)$this->input->post('class_id');
        $sectionID = (int)$this->input->post('section_id');
        $date      = $this->input->post('date');
        $windowMin = max(5, min(60, (int)($this->input->post('window_minutes') ?: 15)));

        $now   = date('Y-m-d H:i:s');
        $close = date('Y-m-d H:i:s', strtotime("+{$windowMin} minutes"));

        // Close any stale open session for same class-section-date
        $this->db->where([
            'branch_id'  => $branchID,
            'class_id'   => $classID,
            'section_id' => $sectionID,
            'date'       => $date,
            'status'     => 'open',
        ])->update('qr_attendance_sessions', ['status' => 'closed']);

        $this->db->insert('qr_attendance_sessions', [
            'branch_id'    => $branchID,
            'class_id'     => $classID,
            'section_id'   => $sectionID,
            'date'         => $date,
            'opened_by'    => get_loggedin_id(),
            'window_open'  => $now,
            'window_close' => $close,
            'status'       => 'open',
        ]);

        header('Content-Type: application/json');
        echo json_encode([
            'status'     => 'ok',
            'session_id' => $this->db->insert_id(),
            'closes_at'  => $close,
        ]);
        exit;
    }

    /**
     * QR scan landing — called when a student's QR is scanned.
     * Excluded from CSRF via config/config.php csrf_exclude_uris.
     * Requires the teacher to be logged in on the scanning device.
     */
    public function scan($token = '')
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }

        $token = substr(preg_replace('/[^a-f0-9]/', '', strtolower($token)), 0, 64);
        if (empty($token)) {
            show_404();
        }

        // Look up student by QR token
        $student = $this->db
            ->select('s.id, s.first_name, s.last_name, s.register_no,
                      e.id as enroll_id, e.class_id, e.section_id, e.branch_id')
            ->from('student s')
            ->join('enroll e', 'e.student_id = s.id')
            ->where('s.qr_token', $token)
            ->where('e.session_id', get_session_id())
            ->get()->row_array();

        if (empty($student)) {
            $this->data['error']    = 'Invalid or unrecognised QR code.';
            $this->data['title']    = 'QR Attendance';
            $this->data['sub_page'] = 'qrcode_attendance/scan_result';
            $this->data['main_menu'] = 'attendance';
            $this->load->view('layout/index', $this->data);
            return;
        }

        $branchID  = $student['branch_id'];
        $classID   = $student['class_id'];
        $sectionID = $student['section_id'];
        $enrollID  = $student['enroll_id'];

        // Find an open scan session for this class right now
        $qrSession = $this->db
            ->where('branch_id',      $branchID)
            ->where('class_id',       $classID)
            ->where('section_id',     $sectionID)
            ->where('status',         'open')
            ->where('window_open <=', date('Y-m-d H:i:s'))
            ->where('window_close >=',date('Y-m-d H:i:s'))
            ->get('qr_attendance_sessions')->row_array();

        if (empty($qrSession)) {
            $this->data['error']    = 'No active attendance session open for your class right now.';
            $this->data['student']  = $student;
            $this->data['title']    = 'QR Attendance';
            $this->data['sub_page'] = 'qrcode_attendance/scan_result';
            $this->data['main_menu'] = 'attendance';
            $this->load->view('layout/index', $this->data);
            return;
        }

        $date    = $qrSession['date'];
        $actorID = get_loggedin_id();

        // Check for existing attendance record (whole-day entry)
        $existing = $this->db
            ->where(['enroll_id' => $enrollID, 'date' => $date])
            ->where('period_id IS NULL', null, false)
            ->get('student_attendance')->row_array();

        if (!empty($existing) && ($existing['capture_method'] ?? '') === 'qr') {
            $this->data['info']     = 'Attendance already recorded via QR for today.';
            $this->data['student']  = $student;
            $this->data['date']     = $date;
            $this->data['title']    = 'QR Attendance';
            $this->data['sub_page'] = 'qrcode_attendance/scan_result';
            $this->data['main_menu'] = 'attendance';
            $this->load->view('layout/index', $this->data);
            return;
        }

        if (!empty($existing)) {
            $this->db->where('id', $existing['id'])->update('student_attendance', [
                'status'         => 'P',
                'capture_method' => 'qr',
                'updated_by'     => $actorID,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->db->insert('student_attendance', [
                'enroll_id'      => $enrollID,
                'date'           => $date,
                'status'         => 'P',
                'capture_method' => 'qr',
                'branch_id'      => $branchID,
                'created_by'     => $actorID,
            ]);
        }

        $this->data['success']  = true;
        $this->data['student']  = $student;
        $this->data['date']     = $date;
        $this->data['title']    = 'QR Attendance';
        $this->data['sub_page'] = 'qrcode_attendance/scan_result';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    /** Individual student QR card — printable. */
    public function student_qr($studentId = 0)
    {
        if (!get_permission('student', 'is_view')) {
            access_denied();
        }
        $studentId = (int)$studentId;
        if (!$studentId) {
            show_404();
        }

        $q = $this->db
            ->select('s.id as student_id, s.first_name, s.last_name, s.register_no, s.qr_token, s.photo,
                      e.roll, e.id as enroll_id, e.class_id, e.section_id, e.branch_id,
                      c.name as class_name, sec.name as section_name')
            ->from('student s')
            ->join('enroll e', 'e.student_id = s.id')
            ->join('class c', 'c.id = e.class_id')
            ->join('section sec', 'sec.id = e.section_id')
            ->where('s.id', $studentId);

        // Non-superadmins are scoped to their branch
        if (!is_superadmin_loggedin()) {
            $q->where('e.branch_id', get_loggedin_branch_id());
        }

        $student = $q->get()->row_array();

        if (empty($student)) {
            show_404();
        }

        $branchID = $student['branch_id'];

        if (empty($student['qr_token'])) {
            $token = bin2hex(random_bytes(32));
            $this->db->where('id', $studentId)->update('student', ['qr_token' => $token]);
            $student['qr_token'] = $token;
        }

        $this->data['student']   = $student;
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'Student QR Card';
        $this->data['sub_page']  = 'qrcode_attendance/student_qr';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    /** Generate or fetch QR tokens for all students in a class. */
    private function _get_or_generate_tokens($classID, $sectionID, $branchID)
    {
        $q = $this->db
            ->select('s.id as student_id, s.first_name, s.last_name, s.register_no, s.qr_token, e.roll, e.id as enroll_id')
            ->from('enroll e')
            ->join('student s', 's.id = e.student_id')
            ->where('e.class_id',   $classID)
            ->where('e.section_id', $sectionID)
            ->where('e.session_id', get_session_id());
        if ($branchID) {
            $q->where('e.branch_id', $branchID);
        }
        $rows = $q->order_by('e.roll')->get()->result_array();

        foreach ($rows as &$row) {
            if (empty($row['qr_token'])) {
                $token = bin2hex(random_bytes(32));
                $this->db->where('id', $row['student_id'])->update('student', ['qr_token' => $token]);
                $row['qr_token'] = $token;
            }
        }
        unset($row);
        return $rows;
    }
}
