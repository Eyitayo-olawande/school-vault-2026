<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Attendance extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('attendance_model');
        $this->load->model('subject_model');
        $this->load->model('sms_model');
        if (!moduleIsEnabled('attendance')) {
            access_denied();
        }
    }

    public function index()
    {
        if (get_permission('student_attendance', 'is_add')) {
            redirect(base_url('attendance/dashboard'), 'refresh');
        } elseif (get_loggedin_id()) {
            redirect(base_url('dashboard'), 'refresh');
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function dashboard()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $date     = date('Y-m-d');
        $this->data['stats']       = $this->attendance_model->getDashboardStats($branchID, $date);
        $this->data['class_cards'] = $this->attendance_model->getDashboardClassCards($branchID, $date);
        $this->data['today']       = $date;
        $this->data['branch_id']   = $branchID;
        $this->data['title']       = 'Attendance Management';
        $this->data['sub_page']    = 'attendance/dashboard';
        $this->data['main_menu']   = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    public function bulk_action()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        if (isset($_POST['apply'])) {
            $classID      = $this->input->post('class_id');
            $sectionID    = $this->input->post('section_id');
            $date         = $this->input->post('date');
            $status       = $this->input->post('bulk_status');
            $skipExisting = (bool)$this->input->post('skip_existing');
            $remark       = $this->input->post('remark');
            $actorID      = get_loggedin_id();

            $students = $this->attendance_model->getStudentAttendence($classID, $sectionID, $date, $branchID);
            $this->db->trans_start();
            $count = 0;
            foreach ($students as $st) {
                if ($skipExisting && !empty($st['att_id'])) {
                    continue;
                }
                if (empty($st['att_id'])) {
                    $this->db->insert('student_attendance', [
                        'enroll_id'  => $st['enroll_id'],
                        'status'     => $status,
                        'remark'     => $remark,
                        'date'       => $date,
                        'branch_id'  => $branchID,
                        'created_by' => $actorID,
                    ]);
                } else {
                    $this->db->where('id', $st['att_id'])->update('student_attendance', [
                        'status'     => $status,
                        'remark'     => $remark,
                        'updated_by' => $actorID,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                $count++;
            }
            $this->db->trans_complete();
            if ($this->db->trans_status() !== false) {
                set_alert('success', "$count student(s) marked as {$status}.");
            } else {
                set_alert('error', translate('something_went_wrong_please_try_again'));
            }
            redirect(current_url());
        }

        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'Bulk Attendance';
        $this->data['sub_page']  = 'attendance/bulk_action';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    public function fire_register()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $date     = date('Y-m-d');
        $this->data['students']  = $this->attendance_model->getFireRegister($branchID, $date);
        $this->data['today']     = $date;
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'Fire Register';
        $this->data['sub_page']  = 'attendance/fire_register';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    public function student_entry()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('class_id',   translate('class'),   'required');
            $this->form_validation->set_rules('section_id', translate('section'), 'required');
            $this->form_validation->set_rules('date', translate('date'), 'trim|required|callback_check_backdate_limit|callback_check_weekendday|callback_check_holiday|callback_get_valid_date');
            if ($this->form_validation->run() == true) {
                $classID   = $this->input->post('class_id');
                $sectionID = $this->input->post('section_id');
                $date      = $this->input->post('date');
                $this->data['date']         = $date;
                $this->data['attendencelist'] = $this->attendance_model->getStudentAttendence($classID, $sectionID, $date, $branchID);

                // Load timetable periods for the selected day so the view can show an optional period picker
                $dayName = date('l', strtotime($date));
                $this->data['periods'] = $this->attendance_model->getPeriodsForClassDay($classID, $sectionID, $dayName, $branchID);
            }
        }
        $this->data['getWeekends']  = $this->application_model->getWeekends($branchID);
        $this->data['getHolidays']  = $this->attendance_model->getHolidays($branchID);

        if (isset($_POST['save'])) {
            $attendance = $this->input->post('attendance');
            $date       = $this->input->post('date');
            $periodID   = $this->input->post('period_id') ?: null;
            $actorID    = get_loggedin_id();

            $this->db->trans_start();
            foreach ($attendance as $key => $value) {
                $attStatus  = $value['status'] ?? '';
                $studentID  = $value['student_id'];
                $arrayAttendance = [
                    'enroll_id'  => $value['enroll_id'],
                    'period_id'  => $periodID,
                    'status'     => $attStatus,
                    'remark'     => $value['remark'],
                    'date'       => $date,
                    'branch_id'  => $branchID,
                ];

                if (empty($value['attendance_id'])) {
                    $arrayAttendance['created_by'] = $actorID;
                    $this->db->insert('student_attendance', $arrayAttendance);
                } else {
                    $this->db->where('id', $value['attendance_id']);
                    $this->db->where('branch_id', $branchID);
                    $this->db->update('student_attendance', [
                        'status'     => $attStatus,
                        'remark'     => $value['remark'],
                        'updated_by' => $actorID,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                // Only Absent notifies, via template 3 (attendance).
                //
                // Late and Half Day previously mapped to 11 and 12. There is no template 11, so
                // Late silently never sent; template 12 is dva_fee_allocation, so marking a
                // student Half Day sent their parent a fee-allocation message. Give Late and
                // Half Day their own templates before adding them back here.
                $smsTemplateMap = ['A' => 3];
                if (isset($smsTemplateMap[$attStatus])) {
                    $arrayAttendance['student_id'] = $studentID;
                    $this->_queue_attendance_sms($arrayAttendance, $smsTemplateMap[$attStatus]);
                }
            }
            $this->db->trans_complete();
            if ($this->db->trans_status() === false) {
                set_alert('error', translate('something_went_wrong_please_try_again'));
            } else {
                set_alert('success', translate('information_has_been_updated_successfully'));
            }
            redirect(current_url());
        }

        $this->data['branch_id'] = $branchID;
        $this->data['title']     = translate('student_attendance');
        $this->data['sub_page']  = 'attendance/student_entries';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    public function getWeekendsHolidays()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            ajax_access_denied();
        }
        if ($_POST) {
            $branchID    = $this->input->post('branch_id');
            $getWeekends = $this->application_model->getWeekends($branchID);
            // Return holidays as a proper array — no manual JSON string construction
            $holidaysArr = $this->attendance_model->getHolidaysArray($branchID);
            echo json_encode(['getWeekends' => $getWeekends, 'getHolidays' => $holidaysArr]);
        }
    }

    /** AJAX: return timetable periods for a given class/section/date (for period-aware entry). */
    public function get_periods()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            ajax_access_denied();
        }
        if ($_POST) {
            $branchID  = $this->application_model->get_branch_id();
            $classID   = (int)$this->input->post('class_id');
            $sectionID = (int)$this->input->post('section_id');
            $date      = $this->input->post('date');
            $dayName   = date('l', strtotime($date));
            $periods   = $this->attendance_model->getPeriodsForClassDay($classID, $sectionID, $dayName, $branchID);
            echo json_encode($periods);
        }
    }

    public function employees_entry()
    {
        if (!get_permission('employee_attendance', 'is_add')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('staff_role', translate('role'), 'required');
            $this->form_validation->set_rules('date', translate('date'), 'trim|required|callback_check_backdate_limit|callback_check_weekendday|callback_check_holiday|callback_get_valid_date');
            if ($this->form_validation->run() == true) {
                $roleID = $this->input->post('staff_role');
                $date   = $this->input->post('date');
                $this->data['date']           = $date;
                $this->data['attendencelist'] = $this->attendance_model->getStaffAttendence($roleID, $date, $branchID);
            }
        }
        $this->data['getWeekends'] = $this->application_model->getWeekends($branchID);

        if (isset($_POST['save'])) {
            $attendance = $this->input->post('attendance');
            $date       = $this->input->post('date');
            $actorID    = get_loggedin_id();

            $this->db->trans_start();
            foreach ($attendance as $key => $value) {
                $attStatus = $value['status'] ?? '';
                $arrayAttendance = [
                    'staff_id'  => $value['staff_id'],
                    'status'    => $attStatus,
                    'remark'    => $value['remark'],
                    'date'      => $date,
                    'branch_id' => $branchID,
                ];

                if (empty($value['attendance_id'])) {
                    $arrayAttendance['created_by'] = $actorID;
                    $this->db->insert('staff_attendance', $arrayAttendance);
                } else {
                    $this->db->where('id', $value['attendance_id']);
                    $this->db->where('branch_id', $branchID);
                    $this->db->update('staff_attendance', [
                        'status'     => $attStatus,
                        'remark'     => $value['remark'],
                        'updated_by' => $actorID,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            $this->db->trans_complete();
            if ($this->db->trans_status() === false) {
                set_alert('error', translate('something_went_wrong_please_try_again'));
            } else {
                set_alert('success', translate('information_has_been_updated_successfully'));
            }
            redirect(current_url());
        }

        $this->data['branch_id'] = $branchID;
        $this->data['title']     = translate('employee_attendance');
        $this->data['sub_page']  = 'attendance/employees_entries';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    public function exam_entry()
    {
        if (!get_permission('exam_attendance', 'is_add')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('exam_id',    translate('exam'),    'required');
            $this->form_validation->set_rules('class_id',   translate('class'),   'required');
            $this->form_validation->set_rules('section_id', translate('section'), 'required');
            $this->form_validation->set_rules('subject_id', translate('subject'), 'required');

            if ($this->form_validation->run() == true) {
                $classID   = $this->input->post('class_id');
                $sectionID = $this->input->post('section_id');
                $examID    = $this->input->post('exam_id');
                $subjectID = $this->input->post('subject_id');
                $this->data['class_id']       = $classID;
                $this->data['section_id']     = $sectionID;
                $this->data['exam_id']        = $examID;
                $this->data['subject_id']     = $subjectID;
                $this->data['attendencelist'] = $this->attendance_model->getExamAttendence($classID, $sectionID, $examID, $subjectID, $branchID);
            }
        }

        if (isset($_POST['save'])) {
            $attendance = $this->input->post('attendance');
            $subjectID  = $this->input->post('subject_id');
            $examID     = $this->input->post('exam_id');
            $actorID    = get_loggedin_id();

            $this->db->trans_start();
            foreach ($attendance as $key => $value) {
                $attStatus = $value['status'] ?? '';
                $arrayAttendance = [
                    'student_id' => $value['student_id'],
                    'status'     => $attStatus,
                    'remark'     => $value['remark'],
                    'exam_id'    => $examID,
                    'subject_id' => $subjectID,
                    'branch_id'  => $branchID,
                ];
                if (empty($value['attendance_id'])) {
                    $arrayAttendance['created_by'] = $actorID;
                    $this->db->insert('exam_attendance', $arrayAttendance);
                } else {
                    $this->db->where('id', $value['attendance_id']);
                    $this->db->where('branch_id', $branchID);
                    $this->db->update('exam_attendance', [
                        'status'     => $attStatus,
                        'remark'     => $value['remark'],
                        'updated_by' => $actorID,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                if ($attStatus == 'A') {
                    $arrayAttendance['student_id'] = $value['student_id'];
                    $this->_queue_attendance_sms($arrayAttendance, 4);
                }
            }
            $this->db->trans_complete();
            if ($this->db->trans_status() === false) {
                set_alert('error', translate('something_went_wrong_please_try_again'));
            } else {
                set_alert('success', translate('information_has_been_updated_successfully'));
            }
            redirect(current_url());
        }

        $this->data['branch_id'] = $branchID;
        $this->data['title']     = translate('exam_attendance');
        $this->data['sub_page']  = 'attendance/exam_entries';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    public function studentwise_report()
    {
        if (!get_permission('student_attendance_report', 'is_view')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if ($_POST) {
            $this->data['class_id']   = $this->input->post('class_id');
            $this->data['section_id'] = $this->input->post('section_id');
            $this->data['month']      = date('m', strtotime($this->input->post('timestamp')));
            $this->data['year']       = date('Y', strtotime($this->input->post('timestamp')));
            $this->data['days']       = cal_days_in_month(CAL_GREGORIAN, $this->data['month'], $this->data['year']);
            $this->data['studentlist'] = $this->attendance_model->getStudentList($branchID, $this->data['class_id'], $this->data['section_id']);
            // Batch-load all attendance for the month — eliminates N+1 queries
            $this->data['attendance_map'] = $this->attendance_model->getStudentMonthlyAttendanceBatch(
                $branchID, $this->data['class_id'], $this->data['section_id'],
                $this->data['year'], $this->data['month']
            );
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = translate('student_attendance');
        $this->data['sub_page']  = 'attendance/student_report';
        $this->data['main_menu'] = 'attendance_report';
        $this->load->view('layout/index', $this->data);
    }

    public function student_classreport()
    {
        if (!get_permission('student_attendance_report', 'is_view')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if ($_POST) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('date', translate('date'), 'trim|required|callback_get_valid_date');
            if ($this->form_validation->run() == true) {
                $this->data['date']           = $this->input->post('date');
                $this->data['attendancelist'] = $this->attendance_model->getDailyStudentReport($branchID, $this->data['date']);
            }
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = translate('student') . ' ' . translate('daily_reports');
        $this->data['sub_page']  = 'attendance/student_classreport';
        $this->data['main_menu'] = 'attendance_report';
        $this->load->view('layout/index', $this->data);
    }

    public function employeewise_report()
    {
        if (!get_permission('employee_attendance_report', 'is_view')) {
            access_denied();
        }

        if ($_POST) {
            $this->data['branch_id'] = $this->application_model->get_branch_id();
            $this->data['role_id']   = $this->input->post('staff_role');
            $this->data['month']     = date('m', strtotime($this->input->post('timestamp')));
            $this->data['year']      = date('Y', strtotime($this->input->post('timestamp')));
            $this->data['days']      = cal_days_in_month(CAL_GREGORIAN, $this->data['month'], $this->data['year']);
            $this->data['stafflist'] = $this->attendance_model->getStaffList($this->data['branch_id'], $this->data['role_id']);
        }
        $this->data['title']     = translate('employee_attendance');
        $this->data['sub_page']  = 'attendance/employees_report';
        $this->data['main_menu'] = 'attendance_report';
        $this->load->view('layout/index', $this->data);
    }

    public function examwise_report()
    {
        if (!get_permission('exam_attendance_report', 'is_view')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if ($_POST) {
            $this->data['class_id']   = $this->input->post('class_id');
            $this->data['section_id'] = $this->input->post('section_id');
            $this->data['exam_id']    = $this->input->post('exam_id');
            $this->data['subject_id'] = $this->input->post('subject_id');
            $this->data['branch_id']  = $this->application_model->get_branch_id();
            $this->data['examreport'] = $this->attendance_model->getExamReport($this->data);
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = translate('exam_attendance');
        $this->data['sub_page']  = 'attendance/exam_report';
        $this->data['main_menu'] = 'attendance_report';
        $this->load->view('layout/index', $this->data);
    }

    /** Students below the branch attendance threshold. */
    public function at_risk_report()
    {
        if (!get_permission('student_attendance_report', 'is_view')) {
            access_denied();
        }

        $branchID  = $this->application_model->get_branch_id();
        $branchRow = $this->db->select('attendance_threshold')->where('id', $branchID)->get('branch')->row();
        $threshold = $branchRow ? (int)$branchRow->attendance_threshold : 75;

        $this->data['at_risk']   = $this->attendance_model->getAtRiskStudents($branchID, $threshold);
        $this->data['threshold'] = $threshold;
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'At-Risk Attendance Report';
        $this->data['sub_page']  = 'attendance/at_risk_report';
        $this->data['main_menu'] = 'attendance_report';
        $this->load->view('layout/index', $this->data);
    }

    /**
     * Admin-triggered or cron-triggered SMS queue flush.
     * Can be called via cron: GET /attendance/process_sms_queue?key=<cron_secret_key>
     */
    public function process_sms_queue()
    {
        $cronKey = $this->db->select('cron_secret_key')->get('global_settings')->row()->cron_secret_key ?? '';
        $isAdmin = get_loggedin_id() && is_superadmin_loggedin();
        $isCron  = !empty($cronKey) && $this->input->get('key') === $cronKey;

        if (!$isAdmin && !$isCron) {
            access_denied();
        }

        $this->load->model('sms_model');
        $branchID = is_superadmin_loggedin() ? null : $this->application_model->get_branch_id();
        $result   = $this->sms_model->flush_queue($branchID, 100);

        if ($isCron && !$isAdmin) {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }

        set_alert('success', "SMS queue processed — sent: {$result['sent']}, failed: {$result['failed']}");
        redirect($_SERVER['HTTP_REFERER'] ?? base_url('dashboard'));
    }

    // ── Validation callbacks ──────────────────────────────────────────────────

    public function get_valid_date($date)
    {
        $presentDate = date('Y-m-d');
        $dateNorm    = date('Y-m-d', strtotime($date));
        if ($dateNorm > $presentDate) {
            $this->form_validation->set_message('get_valid_date', 'Please enter a valid date (not in the future).');
            return false;
        }
        return true;
    }

    /**
     * Rejects dates older than the branch-configured backdating window (default 7 days).
     * Superadmins bypass this check.
     */
    public function check_backdate_limit($date)
    {
        if (is_superadmin_loggedin()) {
            return true;
        }
        $branchID  = $this->application_model->get_branch_id();
        $branchRow = $this->db->select('attendance_backdate_days')->where('id', $branchID)->get('branch')->row();
        $maxDays   = $branchRow ? (int)$branchRow->attendance_backdate_days : 7;
        $cutoff    = date('Y-m-d', strtotime("-{$maxDays} days"));
        if (date('Y-m-d', strtotime($date)) < $cutoff) {
            $this->form_validation->set_message('check_backdate_limit', "Attendance can only be entered within the last {$maxDays} day(s). Contact a superadmin to edit older records.");
            return false;
        }
        return true;
    }

    public function check_holiday($date)
    {
        $branchID        = $this->application_model->get_branch_id();
        $holidayDates    = $this->attendance_model->getHolidaysArray($branchID);
        if (!empty($holidayDates) && in_array($date, $holidayDates, true)) {
            $this->form_validation->set_message('check_holiday', 'You have selected a holiday.');
            return false;
        }
        return true;
    }

    /**
     * Validates that the submitted date is not a weekend.
     * Uses a day-of-week check against the branch's weekend setting instead of
     * iterating over all 365 days of the year.
     */
    public function check_weekendday($date)
    {
        $branchID       = $this->application_model->get_branch_id();
        $weekendsRaw    = $this->application_model->getWeekends($branchID);
        if (empty($weekendsRaw)) {
            return true;
        }
        $weekendNums = array_map('intval', explode(',', $weekendsRaw));
        $dayOfWeek   = (int)date('w', strtotime($date));
        if (in_array($dayOfWeek, $weekendNums, true)) {
            $this->form_validation->set_message('check_weekendday', 'You have selected a weekend date.');
            return false;
        }
        return true;
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    /** Period-wise attendance report (requires period_id to have been captured during entry). */
    public function period_report()
    {
        if (!get_permission('student_attendance_report', 'is_view')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if ($_POST) {
            $classID   = $this->input->post('class_id');
            $sectionID = $this->input->post('section_id');
            $month     = date('m', strtotime($this->input->post('timestamp')));
            $year      = date('Y', strtotime($this->input->post('timestamp')));

            $this->data['class_id']   = $classID;
            $this->data['section_id'] = $sectionID;
            $this->data['month']      = $month;
            $this->data['year']       = $year;
            $this->data['report']     = $this->attendance_model->getStudentPeriodWiseReport(
                $branchID, $classID, $sectionID, $year, $month
            );
        }

        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'Period-wise Attendance Report';
        $this->data['sub_page']  = 'attendance/period_report';
        $this->data['main_menu'] = 'attendance_report';
        $this->load->view('layout/index', $this->data);
    }

    /** Flush today's queued SMS notifications for the current branch. */
    public function notify_today()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        $result   = $this->sms_model->flush_queue($branchID, 200);
        set_alert('success', "SMS queue flushed — sent: {$result['sent']}, failed: {$result['failed']}.");
        redirect($_SERVER['HTTP_REFERER'] ?? base_url('attendance/student_entry'));
    }

    /**
     * Build the SMS payload from student details and queue it — never sends inline.
     */
    private function _queue_attendance_sms(array $data, int $templateID)
    {
        $branchID = $this->application_model->get_branch_id();
        $sms_api  = $this->application_model->smsServiceProvider($branchID);
        if ($sms_api === 'disabled') {
            return;
        }
        $template = $this->db->get_where('sms_template_details', ['template_id' => $templateID, 'branch_id' => $branchID])->row_array();
        if (empty($template) || ($template['notify_student'] != 1 && $template['notify_parent'] != 1)) {
            return;
        }

        $student = $this->application_model->getstudentdetails($data['student_id']);
        if (empty($student)) {
            return;
        }

        $text = str_replace(
            ['{name}', '{register_no}', '{admission_date}', '{class}', '{section}', '{roll}'],
            [$student['first_name'] . ' ' . $student['last_name'], $student['register_no'], $student['admission_date'], $student['class_name'], $student['section_name'], $student['roll']],
            $template['template_body']
        );

        if ($template['notify_student'] == 1 && !empty($student['mobileno'])) {
            $this->sms_model->queue_sms($student['mobileno'], $text, $template['dlt_template_id'], $branchID);
        }

        if ($template['notify_parent'] == 1 && !empty($student['parent_id'])) {
            $parent = $this->db->select('mobileno')->where('id', $student['parent_id'])->get('parent')->row_array();
            if (!empty($parent['mobileno'])) {
                $this->sms_model->queue_sms($parent['mobileno'], $text, $template['dlt_template_id'], $branchID);
            }
        }
    }
}
