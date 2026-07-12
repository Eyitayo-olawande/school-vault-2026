<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Dashboard_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getMonthlyBookIssued($id = '')
    {
        $this->db->select('id');
        $this->db->from('leave_application');
        $this->db->where("start_date BETWEEN DATE_SUB(CURDATE() ,INTERVAL 1 MONTH) AND CURDATE() AND status = '2' AND role_id = '7' AND user_id = " . $this->db->escape($id));
        return $this->db->get()->num_rows();
    }

    public function getStaffCounter($role = '', $branchID = '')
    {
        $this->db->select('COUNT(staff.id) as snumber');
        $this->db->from('staff');
        $this->db->join('login_credential', 'login_credential.user_id = staff.id', 'inner');
        $this->db->where_not_in('login_credential.role', 1);
        if (!empty($role)) {
            $this->db->where('login_credential.role', $role);
        } else {
            $this->db->where_not_in('login_credential.role', array(1, 3, 6, 7));
        }
        if (!empty($branchID)) {
            $this->db->where('staff.branch_id', $branchID);
        }
        return $this->db->get()->row_array();
    }

    public function getMonthlyPayment($id = '')
    {
        $this->db->select('IFNULL(sum(h.amount),0) as amount');
        $this->db->from('fee_allocation as fa');
        $this->db->join('fee_payment_history as h', 'h.allocation_id = fa.id', 'left');
        $this->db->where("h.date BETWEEN DATE_SUB(CURDATE(),INTERVAL 1 MONTH) AND CURDATE() AND fa.student_id = " . $this->db->escape($id) . " AND fa.session_id = " . $this->db->escape(get_session_id()));
        return $this->db->get()->row()->amount;
    }

    /* annual academic fees summary charts */
    public function annualFeessummaryCharts($branchID = '', $studentID = '')
    {
        $total_fee = array();
        $total_paid = array();
        $total_due = array();
        $year = date("Y");
        for ($month = 1; $month <= 12; $month++) {
            $sql = "SELECT `fa`.`id` as `allocation_id`,`gd`.`fee_type_id`,`gd`.`amount` FROM `fee_allocation` as `fa` INNER JOIN `fee_groups_details` as `gd` ON `gd`.`fee_groups_id` = `fa`.`group_id` WHERE MONTH(`gd`.`due_date`) = " . $this->db->escape($month) . " AND YEAR(`gd`.`due_date`) = '$year' AND `fa`.`session_id` = " . $this->db->escape(get_session_id());
            if (!empty($branchID)) {
                $sql .= " AND `fa`.`branch_id` = " . $this->db->escape($branchID);
            }
            if (!empty($studentID)) {
                $sql .= " AND `fa`.`student_id` = " . $this->db->escape($studentID);
            }
            $total_amount = 0;
            $totalpaid = 0;
            $total_discount = 0;
            $result = $this->db->query($sql)->result();
            foreach ($result as $row) {
                $total_amount += $row->amount;
                $sql = "SELECT SUM(`h`.`amount`) AS `total_paid`, SUM(`h`.`discount`) AS `total_discount` FROM `fee_payment_history` as `h` WHERE `h`.`allocation_id` = " . $this->db->escape($row->allocation_id) . " AND  `h`.`type_id` = " . $this->db->escape($row->fee_type_id);
                $r = $this->db->query($sql)->row();
                $totalpaid += $r->total_paid;
                $total_discount += $r->total_discount;
            }
            $total_fee[] = floatval($total_amount);
            $total_paid[] = floatval($totalpaid);
            $total_due[] = floatval($total_amount - ($totalpaid + $total_discount));
        };
        return array(
            'total_fee' => $total_fee,
            'total_paid' => $total_paid,
            'total_due' => $total_due,
        );
    }

    /* student annual attendance charts */
    public function getStudentAttendance($studentID = '')
    {
        $total_present = array_fill(0, 12, 0);
        $total_absent  = array_fill(0, 12, 0);
        $total_late    = array_fill(0, 12, 0);

        $enroll = $this->db->select('id')
            ->where(['student_id' => $studentID, 'session_id' => get_session_id()])
            ->get('enroll')->row();
        if (empty($enroll)) {
            return ['total_present' => $total_present, 'total_absent' => $total_absent, 'total_late' => $total_late];
        }

        // Single aggregation query replacing the previous 36-query loop
        $rows = $this->db->query(
            "SELECT MONTH(date) AS m, status, COUNT(*) AS cnt
             FROM student_attendance
             WHERE enroll_id = " . $this->db->escape($enroll->id) . "
               AND YEAR(date) = YEAR(CURDATE())
               AND status IN ('P','A','L')
               AND period_id IS NULL
             GROUP BY MONTH(date), status"
        )->result_array();

        foreach ($rows as $row) {
            $idx = (int)$row['m'] - 1;
            if ($row['status'] === 'P') $total_present[$idx] = (int)$row['cnt'];
            if ($row['status'] === 'A') $total_absent[$idx]  = (int)$row['cnt'];
            if ($row['status'] === 'L') $total_late[$idx]    = (int)$row['cnt'];
        }

        return [
            'total_present' => $total_present,
            'total_absent'  => $total_absent,
            'total_late'    => $total_late,
        ];
    }

    public function get_monthly_attachments($id = '')
    {
        $branchID = get_loggedin_branch_id();
        $classID = $this->db->select('class_id')->where('student_id', $id)->get('enroll')->row()->class_id;
        $this->db->select('id');
        $this->db->from('attachments');
        $this->db->where("date BETWEEN DATE_SUB(CURDATE() ,INTERVAL 1 MONTH) AND CURDATE() AND (class_id = " . $this->db->escape($classID) . " OR class_id = 'unfiltered') AND branch_id = " . $this->db->escape($branchID));
        return $this->db->get()->num_rows();
    }

    /* 7-day rolling attendance chart — 2 GROUP BY queries instead of 14 */
    public function getWeekendAttendance($branchID = '')
    {
        $days         = [];
        $employee_att = [];
        $student_att  = [];
        $dates        = [];

        $now      = new DateTime('6 days ago');
        $interval = new DateInterval('P1D');
        $period   = new DatePeriod($now, $interval, 6);
        foreach ($period as $day) {
            $days[]  = $day->format('d-M');
            $dates[] = $day->format('Y-m-d');
        }

        $placeholders = implode(',', array_map([$this->db, 'escape'], $dates));
        $branchWhere  = !empty($branchID) ? ' AND branch_id = ' . $this->db->escape($branchID) : '';

        $sRows = $this->db->query(
            "SELECT DATE(date) AS day, COUNT(*) AS cnt
             FROM student_attendance
             WHERE date IN ($placeholders) AND status IN ('P','L')
             $branchWhere
             GROUP BY DATE(date)"
        )->result_array();
        $sMap = array_column($sRows, 'cnt', 'day');

        $eRows = $this->db->query(
            "SELECT DATE(date) AS day, COUNT(*) AS cnt
             FROM staff_attendance
             WHERE date IN ($placeholders) AND status IN ('P','L')
             $branchWhere
             GROUP BY DATE(date)"
        )->result_array();
        $eMap = array_column($eRows, 'cnt', 'day');

        foreach ($dates as $d) {
            $student_att[]  = ['y' => (int)($sMap[$d] ?? 0)];
            $employee_att[] = ['y' => (int)($eMap[$d] ?? 0)];
        }

        return [
            'days'         => $days,
            'employee_att' => $employee_att,
            'student_att'  => $student_att,
        ];
    }

    /* monthly academic cash book transaction charts */
    public function getIncomeVsExpense($branchID = '')
    {
        $query = "SELECT IFNULL(SUM(dr),0) as dr, IFNULL(SUM(cr),0) as cr FROM transactions WHERE month(DATE) = MONTH(now()) AND year(DATE) = YEAR(now())";
        if (!empty($branchID)) {
            $query .= " AND branch_id = " . $this->db->escape($branchID);
        }
        $r = $this->db->query($query)->row_array();
        return array(['name' => translate("expense"), 'value' => $r['dr']], ['name' => translate("income"), 'value' => $r['cr']]);
    }

    /* total academic students strength classes divided into charts */
    public function getStudentByClass($branchID = '')
    {
        $this->db->select('IFNULL(COUNT(e.student_id), 0) as total_student, c.name as class_name');
        $this->db->from('enroll as e');
        $this->db->join('class as c', 'c.id = e.class_id', 'inner');
        $this->db->group_by('e.class_id');
        if (!empty($branchID)) {
            $this->db->where('e.branch_id', $branchID);
        }

        $query = $this->db->get();
        $data = array();
        if ($query->num_rows() > 0) {
            $students = $query->result();
            foreach ($students as $row) {
                $data[] = ['value' => floatval($row->total_student), 'name' => $row->class_name];
            }
        } else {
            $data[] = ['value' => 0, 'name' => translate('not_found_anything')];
        }
        return $data;
    }

    public function get_total_student($branchID = '')
    {
        $sessionID = get_session_id();
        $this->db->select('IFNULL(COUNT(enroll.id), 0) as total_student');
        $this->db->from('enroll');
        $this->db->join('student', 'student.id = enroll.student_id', 'inner');
        $this->db->where('enroll.session_id', $sessionID);
        if (!empty($branchID)) {
            $this->db->where('enroll.branch_id', $branchID);
        }
        return $this->db->get()->row()->total_student;
    }

    public function getMonthlyAdmission($branchID = '')
    {
        $this->db->select('s.id');
        $this->db->from('student as s');
        $this->db->join('enroll as e', 'e.student_id = s.id', 'inner');
        $this->db->where('s.admission_date BETWEEN DATE_SUB(CURDATE() ,INTERVAL 1 MONTH) AND CURDATE()');
        if (!empty($branchID)) {
            $this->db->where('e.branch_id', $branchID);
        }
        return $this->db->get()->num_rows();
    }

    public function getVoucher($branchID = '')
    {
        $this->db->select('id');
        if (!empty($branchID)) {
            $this->db->where('branch_id', $branchID);
        }
        $this->db->where('date BETWEEN DATE_SUB(CURDATE() ,INTERVAL 1 MONTH) AND CURDATE()');
        return $this->db->get('transactions')->num_rows();
    }

    public function get_transport_route($branchID = '')
    {
        if (!empty($branchID)) {
            $this->db->where('branch_id', $branchID);
        }
        return $this->db->get('transport_route')->num_rows();
    }


    public function languageShortCodes($lang='')
    {
        $codes = array (
          'english' => 'en',
          'bengali' => 'bn',
          'arabic' => 'ar',
          'french' => 'fr',
          'hindi' => 'hi',
          'indonesian' => 'id',
          'italian' => 'it',
          'japanese' => 'ja',
          'korean' => 'ko',
          'portuguese' => 'pt',
          'thai' => 'th',
          'turkish' => 'tr',
          'urdu' => 'ur',
          'chinese' => 'zh',
          'afrikaans' => 'af',
          'german' => 'de',
          'nepali' => 'ne',
          'russian' => 'ru',
          'danish' => 'da',
          'armenian' => 'hy',
          'georgian' => 'ka',
          'marathi' => 'mr',
          'malay' => 'ms',
          'tamil' => 'ta',
          'telugu' => 'te',
          'swedish' => 'sv',
          'dutch' => 'nl',
          'greek' => 'el',
          'spanish' => 'es',
          'punjabi' => 'pa',
        );
        return empty($codes[$lang]) ? '' : $codes[$lang];
    }

    /**
     * Summary of today's attendance across all classes.
     * Returns overall totals + per-class breakdown in one query.
     */
    public function getTodayAttendanceSummary($branchID = '')
    {
        $today     = date('Y-m-d');
        $sessionID = get_session_id();

        $branchCond = !empty($branchID) ? ' AND e.branch_id = ' . $this->db->escape($branchID) : '';

        $sql = "SELECT
                    c.name  AS class_name,
                    sec.name AS section_name,
                    COUNT(DISTINCT e.id) AS enrolled,
                    COUNT(DISTINCT sa.id) AS marked,
                    SUM(CASE WHEN sa.status IN ('P','L') THEN 1
                             WHEN sa.status = 'HD'       THEN 0.5
                             ELSE 0 END) AS present_count
                FROM enroll e
                INNER JOIN class c      ON c.id   = e.class_id
                INNER JOIN section sec  ON sec.id  = e.section_id
                LEFT JOIN student_attendance sa
                    ON sa.enroll_id = e.id
                   AND sa.date      = " . $this->db->escape($today) . "
                   AND sa.period_id IS NULL
                WHERE e.session_id = " . $this->db->escape($sessionID) . "
                $branchCond
                GROUP BY e.class_id, e.section_id
                ORDER BY c.name, sec.name";

        $rows = $this->db->query($sql)->result_array();

        $totalEnrolled = 0;
        $totalMarked   = 0;
        $totalPresent  = 0.0;

        foreach ($rows as &$row) {
            $totalEnrolled += (int)$row['enrolled'];
            $totalMarked   += (int)$row['marked'];
            $totalPresent  += (float)$row['present_count'];
            $row['pct'] = (int)$row['marked'] > 0
                ? round(((float)$row['present_count'] / (int)$row['marked']) * 100)
                : null;
        }
        unset($row);

        return [
            'by_class'       => $rows,
            'total_enrolled' => $totalEnrolled,
            'total_marked'   => $totalMarked,
            'total_present'  => $totalPresent,
            'overall_pct'    => $totalMarked > 0
                ? round(($totalPresent / $totalMarked) * 100)
                : null,
        ];
    }
}
