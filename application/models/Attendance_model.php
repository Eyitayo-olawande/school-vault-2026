<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Attendance_model extends MY_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function getStudentAttendence($classID, $sectionID, $date, $branchID)
    {
        $sql = "SELECT `enroll`.`id` as `enroll_id`,`enroll`.`roll`,`student`.`first_name`,`student`.`last_name`,`student`.`id` as `student_id`,`student`.`register_no`,`student_attendance`.`id` as `att_id`,`student_attendance`.`status` as `att_status`,`student_attendance`.`remark` as `att_remark` FROM `enroll` LEFT JOIN `student` ON `student`.`id` = `enroll`.`student_id` LEFT JOIN `student_attendance` ON `student_attendance`.`enroll_id` = `enroll`.`id` AND `student_attendance`.`date` = " . $this->db->escape($date) . " AND `student_attendance`.`period_id` IS NULL WHERE `enroll`.`class_id` = " . $this->db->escape($classID) . " AND `enroll`.`section_id` = " . $this->db->escape($sectionID) . " AND `enroll`.`branch_id` = " . $this->db->escape($branchID) . " AND `enroll`.`session_id` = " . $this->db->escape(get_session_id());
        return $this->db->query($sql)->result_array();
    }

    public function getStaffAttendence($roleID, $date, $branchID)
    {
        $sql = "SELECT `staff`.*, `lc`.`role`, `sa`.`id` as `atten_id`, IFNULL(`sa`.`status`, 0) as `att_status`, `sa`.`remark` as `att_remark` FROM `staff` LEFT JOIN `login_credential` as `lc` ON `lc`.`user_id` = `staff`.`id` and `lc`.`role` != '6' and `lc`.`role` != '7' LEFT JOIN `staff_attendance` as `sa` ON `sa`.`staff_id` = `staff`.`id` and `sa`.`date` = " . $this->db->escape($date) . " WHERE `staff`.`branch_id` = " . $this->db->escape($branchID) . " AND `lc`.`role` = " . $this->db->escape($roleID) . " AND `lc`.`active` = '1' ORDER BY `staff`.`id` ASC";
        return $this->db->query($sql)->result_array();
    }

    public function getExamAttendence($classID, $sectionID, $examID, $subjectID, $branchID)
    {
        $sql = "SELECT enroll.student_id,enroll.roll,student.first_name,student.last_name,student.register_no,exam_attendance.id as `atten_id`,
        exam_attendance.status as `att_status`,exam_attendance.remark as `att_remark` FROM `enroll` LEFT JOIN student ON
        student.id = enroll.student_id LEFT JOIN exam_attendance ON exam_attendance.student_id = student.id AND exam_attendance.exam_id = " .
        $this->db->escape($examID) . " AND exam_attendance.subject_id = " . $this->db->escape($subjectID) .
        " WHERE enroll.class_id = " . $this->db->escape($classID) . " AND enroll.section_id = " . $this->db->escape($sectionID) .
        " AND enroll.branch_id = " . $this->db->escape($branchID) . " AND enroll.session_id = " . $this->db->escape(get_session_id());
        return $this->db->query($sql)->result_array();
    }

    public function getStudentList($branch_id, $class_id, $section_id)
    {
        $this->db->select('e.id as enroll_id,e.roll,s.first_name,s.last_name,s.register_no');
        $this->db->from('enroll as e');
        $this->db->join('student as s', 's.id = e.student_id', 'left');
        $this->db->where('e.class_id', $class_id);
        $this->db->where('e.section_id', $section_id);
        $this->db->where('e.branch_id', $branch_id);
        $this->db->where('e.session_id', get_session_id());
        return $this->db->get()->result_array();
    }

    // GET STAFF ALL DETAILS
    public function getStaffList($branch_id = '', $role_id = '', $active = 1)
    {
        $this->db->select('staff.*,login_credential.role as role_id, roles.name as role');
        $this->db->from('staff');
        $this->db->join('login_credential', 'login_credential.user_id = staff.id and login_credential.role != "6" and login_credential.role != "7"', 'inner');
        $this->db->join('roles', 'roles.id = login_credential.role', 'left');
        if (!empty($branch_id)) {
            $this->db->where('staff.branch_id', $branch_id);
        }
        $this->db->where('login_credential.role', $role_id);
        $this->db->where('login_credential.active', $active);
        $this->db->order_by('staff.id', 'ASC');
        return $this->db->get()->result_array();
    }

    public function getExamReport($data)
    {
        $sql = "SELECT ea.*, s.first_name, s.last_name, s.register_no, s.category_id, e.roll, sb.name as subject_name FROM exam_attendance as ea LEFT JOIN enroll as e ON e.student_id = ea.student_id LEFT JOIN student as s ON s.id = ea.student_id LEFT JOIN subject as sb ON sb.id = ea.subject_id WHERE ea.exam_id = " . $this->db->escape($data['exam_id']) . " AND ea.subject_id = " . $this->db->escape($data['subject_id']) . " AND ea.branch_id = " . $this->db->escape($data['branch_id']) . " AND e.class_id = " . $this->db->escape($data['class_id']) . " AND e.section_id = " . $this->db->escape($data['section_id']) . " AND e.session_id = " . $this->db->escape(get_session_id());
        return $this->db->query($sql)->result_array();
    }

    // Single query for one student/date — kept for backward compat; prefer getStudentMonthlyAttendanceBatch for report pages
    public function get_attendance_by_date($enrollID, $date)
    {
        $sql = "SELECT `student_attendance`.* FROM `student_attendance`
                WHERE `enroll_id` = " . $this->db->escape($enrollID) . "
                  AND `date` = " . $this->db->escape($date) . "
                  AND `period_id` IS NULL";
        return $this->db->query($sql)->row_array();
    }

    /**
     * Fetch all daily (non-period) attendance for an entire class/section in one month.
     * Returns a nested map: [ enroll_id => [ 'Y-m-d' => ['status'=>..., 'remark'=>...] ] ]
     * Eliminates the N+1 query pattern in the monthly report view.
     */
    public function getStudentMonthlyAttendanceBatch($branchID, $classID, $sectionID, $year, $month)
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $to   = sprintf('%04d-%02d-%02d', $year, $month, $days);

        $sql = "SELECT sa.enroll_id, sa.date, sa.status, sa.remark
                FROM student_attendance sa
                INNER JOIN enroll e ON e.id = sa.enroll_id
                WHERE e.class_id    = " . $this->db->escape($classID) . "
                  AND e.section_id  = " . $this->db->escape($sectionID) . "
                  AND e.branch_id   = " . $this->db->escape($branchID) . "
                  AND e.session_id  = " . $this->db->escape(get_session_id()) . "
                  AND sa.date BETWEEN " . $this->db->escape($from) . " AND " . $this->db->escape($to) . "
                  AND sa.period_id IS NULL";

        $rows = $this->db->query($sql)->result_array();
        $map  = [];
        foreach ($rows as $row) {
            $map[$row['enroll_id']][$row['date']] = [
                'status' => $row['status'],
                'remark' => $row['remark'],
            ];
        }
        return $map;
    }

    /**
     * Returns weekend dates for the current calendar year — used only in
     * server-side date validation. The report view uses day-of-week checks
     * inline instead of calling this method.
     * Performance note: kept for the validation callback. See check_weekendday
     * in the controller for an optimised alternative that avoids the 365-step loop.
     */
    public function getWeekendDaysSession($branch_id = '')
    {
        $date_from = strtotime(date("Y-01-01"));
        $date_to   = strtotime(date("Y-12-31"));
        $oneDay    = 86400;
        $allDays   = ['0' => 'Sunday','1' => 'Monday','2' => 'Tuesday','3' => 'Wednesday','4' => 'Thursday','5' => 'Friday','6' => 'Saturday'];

        $weekendDay    = $this->application_model->getWeekends($branch_id);
        $weekendArrays = explode(',', $weekendDay);
        $weekendDates  = [];
        for ($i = $date_from; $i <= $date_to; $i += $oneDay) {
            if ($weekendDay !== '') {
                foreach ($weekendArrays as $weekendValue) {
                    if ($weekendValue >= 0 && $weekendValue <= 6 && date('l', $i) === $allDays[$weekendValue]) {
                        $weekendDates[] = date('Y-m-d', $i);
                    }
                }
            }
        }
        return $weekendDates;
    }

    /**
     * Returns holiday dates as a PHP array (one entry per day in each holiday range).
     * This replaces the old string-based getHolidays() for any code that needs an actual array.
     */
    public function getHolidaysArray($branchID = '')
    {
        $this->db->where('branch_id', $branchID);
        $this->db->where('session_id', get_session_id());
        $this->db->where(['status' => 1, 'type' => 'holiday']);
        $this->db->order_by('start_date', 'asc');
        $holidays = $this->db->get('event')->result();

        $allHolidayList = [];
        foreach ($holidays as $holiday) {
            $from  = strtotime($holiday->start_date);
            $to    = strtotime($holiday->end_date);
            for ($i = $from; $i <= $to; $i += 86400) {
                $allHolidayList[] = date('Y-m-d', $i);
            }
        }
        return array_values(array_unique($allHolidayList));
    }

    /**
     * Original method retained for backward compat with the date-picker
     * JS that still receives a bare comma-string. Use getHolidaysArray() for PHP logic.
     */
    public function getHolidays($branchID = '')
    {
        $list = $this->getHolidaysArray($branchID);
        return empty($list) ? '' : implode('","', $list);
    }

    public function getDailyStudentReport($branchID = '', $date = '')
    {
        $sql = "SELECT class.name as `class_name`,section.name as `section_name`, SUM(CASE WHEN `status` = 'P' THEN 1 ELSE 0 END) AS 'present',SUM(CASE WHEN `status` = 'A' THEN 1 ELSE 0 END) AS 'absent',SUM(CASE WHEN `status` = 'L' THEN 1 ELSE 0 END) AS 'late',SUM(CASE WHEN `status` = 'HD' THEN 1 ELSE 0 END) AS 'half_day',SUM(CASE WHEN `status` = 'EA' THEN 1 ELSE 0 END) AS 'excused' FROM `student_attendance` JOIN `enroll` on student_attendance.enroll_id=enroll.id INNER JOIN `sections_allocation` on (enroll.class_id=sections_allocation.class_id and enroll.section_id=sections_allocation.section_id) inner join `class` on class.id=sections_allocation.class_id INNER JOIN `section` on section.id=sections_allocation.section_id WHERE `enroll`.`session_id`=" . $this->db->escape(get_session_id()) . " AND enroll.branch_id = " . $this->db->escape($branchID) . " AND student_attendance.date = " . $this->db->escape($date) . " AND student_attendance.period_id IS NULL GROUP BY sections_allocation.id ORDER BY sections_allocation.class_id";
        return $this->db->query($sql)->result();
    }

    /**
     * Returns students whose attendance percentage (for the current session/year)
     * is below the branch-configured threshold.
     * Percentage formula: (P + L + 0.5*HD) / (P + A + L + HD) * 100
     * EA (Excused Absent) is excluded from the denominator.
     */
    public function getAtRiskStudents($branchID, $threshold = 75)
    {
        $sql = "SELECT
                    s.id AS student_id,
                    s.first_name, s.last_name, s.register_no,
                    c.name AS class_name,
                    sec.name AS section_name,
                    SUM(CASE WHEN sa.status = 'P'  THEN 1   ELSE 0 END) AS cnt_present,
                    SUM(CASE WHEN sa.status = 'A'  THEN 1   ELSE 0 END) AS cnt_absent,
                    SUM(CASE WHEN sa.status = 'L'  THEN 1   ELSE 0 END) AS cnt_late,
                    SUM(CASE WHEN sa.status = 'HD' THEN 0.5 ELSE 0 END) AS cnt_half,
                    ROUND(
                        (SUM(CASE WHEN sa.status IN ('P','L') THEN 1 WHEN sa.status='HD' THEN 0.5 ELSE 0 END) /
                        NULLIF(SUM(CASE WHEN sa.status IN ('P','A','L') THEN 1 WHEN sa.status='HD' THEN 0.5 ELSE 0 END), 0)) * 100, 1
                    ) AS attendance_pct
                FROM student_attendance sa
                INNER JOIN enroll e   ON e.id = sa.enroll_id
                INNER JOIN student s  ON s.id = e.student_id
                INNER JOIN class c    ON c.id = e.class_id
                INNER JOIN section sec ON sec.id = e.section_id
                WHERE e.branch_id   = " . $this->db->escape($branchID) . "
                  AND e.session_id  = " . $this->db->escape(get_session_id()) . "
                  AND sa.period_id  IS NULL
                  AND YEAR(sa.date) = YEAR(CURDATE())
                GROUP BY e.id
                HAVING attendance_pct IS NOT NULL AND attendance_pct < " . (float)$threshold . "
                ORDER BY attendance_pct ASC, c.name, sec.name, s.first_name";
        return $this->db->query($sql)->result_array();
    }

    /**
     * Get timetable periods for a given class/section on a given day-of-week name.
     * Used by the period-aware attendance entry form.
     */
    public function getPeriodsForClassDay($classID, $sectionID, $dayName, $branchID)
    {
        return $this->db->select('tc.id, tc.time_start, tc.time_end, s.name as subject_name')
            ->from('timetable_class tc')
            ->join('subject s', 's.id = tc.subject_id', 'left')
            ->where('tc.class_id',   $classID)
            ->where('tc.section_id', $sectionID)
            ->where('tc.day',        $dayName)
            ->where('tc.branch_id',  $branchID)
            ->where('tc.break',      'false')
            ->order_by('tc.time_start', 'ASC')
            ->get('timetable_class')
            ->result_array();
    }

    /**
     * Fetch period-wise attendance for a class/section in one month.
     * Returns structured data: students[], subjects[], map[enroll_id][subject][date] = status
     */
    public function getStudentPeriodWiseReport($branchID, $classID, $sectionID, $year, $month)
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $to   = sprintf('%04d-%02d-%02d', $year, $month, $days);

        $sql = "SELECT sa.enroll_id, sa.date, sa.status,
                       s.first_name, s.last_name, e.roll,
                       tc.time_start, tc.time_end,
                       sub.name AS subject_name
                FROM student_attendance sa
                INNER JOIN enroll e     ON e.id  = sa.enroll_id
                INNER JOIN student s    ON s.id  = e.student_id
                INNER JOIN timetable_class tc ON tc.id = sa.period_id
                INNER JOIN subject sub  ON sub.id = tc.subject_id
                WHERE e.class_id    = " . $this->db->escape($classID) . "
                  AND e.section_id  = " . $this->db->escape($sectionID) . "
                  AND e.branch_id   = " . $this->db->escape($branchID) . "
                  AND e.session_id  = " . $this->db->escape(get_session_id()) . "
                  AND sa.date BETWEEN " . $this->db->escape($from) . " AND " . $this->db->escape($to) . "
                  AND sa.period_id IS NOT NULL
                ORDER BY e.roll, sa.date, tc.time_start";

        $rows     = $this->db->query($sql)->result_array();
        $students = [];
        $subjects = [];
        $map      = [];

        foreach ($rows as $row) {
            $eid     = $row['enroll_id'];
            $subject = $row['subject_name'];
            $date    = $row['date'];

            if (!isset($students[$eid])) {
                $students[$eid] = [
                    'name' => $row['first_name'] . ' ' . $row['last_name'],
                    'roll' => $row['roll'],
                ];
            }
            $subjects[$subject] = true;
            $map[$eid][$subject][$date] = $row['status'];
        }

        return [
            'students' => $students,
            'subjects' => array_keys($subjects),
            'map'      => $map,
            'days'     => $days,
        ];
    }
}
