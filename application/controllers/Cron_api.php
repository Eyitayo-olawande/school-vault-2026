<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom school management system
 * @version : 5.0
 * @developed by : RamomCoder
 * @support : ramomcoder@yahoo.com
 * @author url : http://codecanyon.net/user/RamomCoder
 * @filename : Cron_api.php
 * @copyright : Reserved RamomCoder Team
 */

class Cron_api extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('fees_model');
        $this->load->model('sms_model');
        $this->load->model('sendsmsmail_model');
        $this->api_key = $this->data['global_config']['cron_secret_key'];
    }

    public function index()
    {
        if (!is_loggedin() || !get_permission('cron_job', 'is_view')) {
            access_denied();
        }

        if ($_POST) {
            if (!get_permission('cron_job', 'is_edit')) {
                access_denied();
            }
            $this->db->where('id', 1);
            $this->db->update('global_settings', array('cron_secret_key' => generate_encryption_key()));
            set_alert('success', "Successfully Created The New Secret Key.");
            redirect(current_url());
        }

        $this->data['title'] = translate('cron_job');
        $this->data['sub_page'] = 'cron_api/index';
        $this->data['main_menu'] = 'settings';
        $this->load->view('layout/index', $this->data);
    }

    public function send_smsemail_command($api_key = '')
    {
        if (empty($api_key) || $this->api_key !== $api_key) {
            echo "API Key is required or API Key does not match.";
            exit();
        }

        $sql = "SELECT * FROM bulk_sms_email WHERE posting_status = 1 AND schedule_time < NOW() ORDER BY schedule_time ASC";
        $bulkArray = $this->db->query($sql)->result_array();
        foreach ($bulkArray as $key => $row) {
            $this->db->where('id', $row['id']);
            $this->db->update('bulk_sms_email', array('posting_status' => 0));
            $sCount = 0;
            $usersList = json_decode($row['additional'], true);
            foreach ($usersList as $key => $user) {
                if ($row['message_type'] == 1) {
                    $response = $this->sendsmsmail_model->sendSMS($user['mobileno'], $row['message'], $user['name'], $user['email'], $row['sms_gateway']);
                } else {
                    $response = $this->sendsmsmail_model->sendEmail($user['email'], $row['message'], $user['name'], $user['mobileno'], $row['email_subject']);
                }
                if ($response == true) {
                    $sCount++;
                }
            }
            $this->db->where('id', $row['id']);
            $this->db->update('bulk_sms_email', array('additional' => "", 'successfully_sent' => $sCount, 'posting_status' => 2));
        }
    }

    public function homework_command($api_key = '')
    {
        if (empty($api_key) || $this->api_key !== $api_key) {
            echo "API Key is required or API Key does not match.";
            exit();
        }
        $sql = "SELECT * FROM homework WHERE status = 1 AND date(schedule_date) = CURDATE() ORDER BY schedule_date ASC";
        $homeworkArray = $this->db->query($sql)->result_array();
        foreach ($homeworkArray as $key => $row) {
            $this->db->where('id', $row['id']);
            $this->db->update('homework', array('status' => 0));
            //send homework sms notification
            if ($row['sms_notification'] == 1) {
                $stuList = $this->application_model->getStudentListByClassSection($row['class_id'], $row['section_id'], $row['branch_id']);
                foreach ($stuList as $stuRow) {
                    $stuRow['date_of_homework'] = $row['date_of_homework'];
                    $stuRow['date_of_submission'] = $row['date_of_submission'];
                    $stuRow['subject_id'] = $row['subject_id'];
                    $this->sms_model->sendHomework($stuRow);
                }
            }
        }
    }

    public function fees_reminder_command($api_key = '')
    {
        if (empty($api_key) || $this->api_key !== $api_key) {
            echo "API Key is required or API Key does not match.";
            exit();
        }
        $feesArray = $this->db->get('fees_reminder')->result_array();
        foreach ($feesArray as $key => $row) {
            $studentList = array();
            $days = $row['days'];
            if ($row['frequency'] == 'before') {
                $date = date('Y-m-d', strtotime("+ $days days"));
            } elseif ($row['frequency'] == 'after') {
                $date = date('Y-m-d', strtotime("- $days days"));
            }
            $getFeeTypes = $this->fees_model->getFeeReminderByDate($date, $row['branch_id']);
            foreach ($getFeeTypes as $type_key => $type_value) {
                $getStuDetails = $this->fees_model->getStudentsListReminder($type_value['fee_groups_id'], $type_value['fee_type_id']);
                foreach ($getStuDetails as $stu_key => $stu_value) {
                    $stu_value['due_date'] = _d($type_value['due_date']);
                    $stu_value['type_name'] = $type_value['name'];
                    $stu_value['total_amount'] = (float) $type_value['amount'];
                    $stu_value['balance_amount'] = (float) ($type_value['amount'] - ($stu_value['payment']['total_paid'] + $stu_value['payment']['total_discount']));
                    unset($stu_value['payment']);
                    if ($stu_value['balance_amount'] > 0) {
                        $studentList[] = $stu_value;
                    }
                }
            }
            foreach ($studentList as $stuRow) {
                $this->sms_model->feeReminder($stuRow, $row);
            }
        }
    }

    /**
     * DVA Resumption Reminders — run DAILY.
     * Sends SMS on: 14 days before resumption, 7 days before, and the Friday before resumption.
     *
     * Cron: daily at 8am
     *   curl {base_url}cron_api/dva_resumption_reminder_command/{key}
     */
    public function dva_resumption_reminder_command($api_key = '')
    {
        if (empty($api_key) || $this->api_key !== $api_key) {
            echo "API Key is required or API Key does not match.";
            exit();
        }

        $today     = date('Y-m-d');
        $todayDow  = date('N'); // 1=Mon … 7=Sun
        $branches  = $this->db->get('branch')->result_array();

        foreach ($branches as $branch) {
            $branchID  = $branch['id'];
            $sessionID = $this->_getActiveSession($branchID);
            if (!$sessionID) continue;

            // Fetch all term_dates for this branch/session that have a resumption_date
            $termDates = $this->db->where(['branch_id' => $branchID, 'session_id' => $sessionID])
                ->where('resumption_date IS NOT NULL', null, false)
                ->get('term_dates')->result_array();

            foreach ($termDates as $td) {
                $resumption = $td['resumption_date'];
                $daysUntil  = (int) floor((strtotime($resumption) - strtotime($today)) / 86400);

                $templateID = null;
                if ($daysUntil == 14) {
                    $templateID = 13; // dva_resumption_14d
                } elseif ($daysUntil == 7) {
                    $templateID = 14; // dva_resumption_7d
                } elseif ($daysUntil > 0 && $daysUntil <= 3 && $todayDow == 5) {
                    // Friday within 3 days of resumption (weekend-before send)
                    $templateID = 15; // dva_resumption_weekend
                }

                if (!$templateID) continue;

                // Get all students with DVA in this branch/session with outstanding balance
                $students = $this->_getDvaStudentsWithBalance($branchID, $sessionID);
                foreach ($students as $stu) {
                    $vars = [
                        'guardian_name'  => $stu['guardian_name'],
                        'child_name'     => $stu['child_name'],
                        'resumption_date'=> date('d M Y', strtotime($resumption)),
                        'balance'        => number_format($stu['balance'], 2),
                        'dva_account'    => $stu['account_number'],
                        'dva_bank'       => $stu['bank_name'],
                        'term'           => $td['term'],
                    ];
                    $this->sms_model->dvaSendReminder($branchID, $templateID, $vars, $stu['parent_mobile']);
                }
            }
        }
        echo "DVA resumption reminders processed.\n";
    }

    /**
     * DVA Exam Reminders — run DAILY.
     * Sends SMS on: 7 days before mid-term break, and 10 days before exam start.
     *
     * Cron: daily at 8am
     *   curl {base_url}cron_api/dva_exam_reminder_command/{key}
     */
    public function dva_exam_reminder_command($api_key = '')
    {
        if (empty($api_key) || $this->api_key !== $api_key) {
            echo "API Key is required or API Key does not match.";
            exit();
        }

        $today    = date('Y-m-d');
        $branches = $this->db->get('branch')->result_array();

        foreach ($branches as $branch) {
            $branchID  = $branch['id'];
            $sessionID = $this->_getActiveSession($branchID);
            if (!$sessionID) continue;

            $termDates = $this->db->where(['branch_id' => $branchID, 'session_id' => $sessionID])
                ->get('term_dates')->result_array();

            foreach ($termDates as $td) {
                $sends = [];

                if (!empty($td['midterm_date'])) {
                    $daysUntilMidterm = (int) floor((strtotime($td['midterm_date']) - strtotime($today)) / 86400);
                    if ($daysUntilMidterm == 7) {
                        $sends[] = ['template' => 16, 'date_key' => 'midterm_date', 'date_val' => $td['midterm_date']];
                    }
                }
                if (!empty($td['exam_start_date'])) {
                    $daysUntilExam = (int) floor((strtotime($td['exam_start_date']) - strtotime($today)) / 86400);
                    if ($daysUntilExam == 10) {
                        $sends[] = ['template' => 17, 'date_key' => 'exam_start_date', 'date_val' => $td['exam_start_date']];
                    }
                }

                if (empty($sends)) continue;

                $students = $this->_getDvaStudentsWithBalance($branchID, $sessionID);
                foreach ($sends as $send) {
                    foreach ($students as $stu) {
                        $vars = [
                            'guardian_name' => $stu['guardian_name'],
                            'child_name'    => $stu['child_name'],
                            'balance'       => number_format($stu['balance'], 2),
                            'dva_account'   => $stu['account_number'],
                            'dva_bank'      => $stu['bank_name'],
                            'term'          => $td['term'],
                            'midterm_date'  => !empty($td['midterm_date'])    ? date('d M Y', strtotime($td['midterm_date']))    : '',
                            'exam_start_date' => !empty($td['exam_start_date']) ? date('d M Y', strtotime($td['exam_start_date'])) : '',
                        ];
                        $this->sms_model->dvaSendReminder($branchID, $send['template'], $vars, $stu['parent_mobile']);
                    }
                }
            }
        }
        echo "DVA exam reminders processed.\n";
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function _getActiveSession($branchID)
    {
        // Active session is a global setting (schoolyear has no branch_id or is_active column)
        $row = $this->db->select('session_id')->where('id', 1)->get('global_settings')->row_array();
        return $row['session_id'] ?? null;
    }

    /**
     * Returns students with DVA accounts that have outstanding fee balances.
     * Returns array of rows with: guardian_name, child_name, parent_mobile, account_number, bank_name, balance
     */
    private function _getDvaStudentsWithBalance($branchID, $sessionID)
    {
        $sql = "
            SELECT
                s.id        AS student_id,
                CONCAT(s.first_name,' ',s.last_name) AS child_name,
                COALESCE(p.father_name, p.guardian_name, 'Parent') AS guardian_name,
                p.mobileno  AS parent_mobile,
                dva.account_number,
                dva.dedicated_account_bank AS bank_name,
                e.id        AS enroll_id,
                COALESCE(SUM(fg.amount), 0) - COALESCE(SUM(fph.paid_amount + fph.discount_amount), 0) AS balance
            FROM enroll e
            INNER JOIN student s       ON s.id = e.student_id
            INNER JOIN dedicated_virtual_account dva ON dva.user_id = s.id
            LEFT  JOIN parent p        ON p.id = s.parent_id
            INNER JOIN fee_allocation fa ON fa.student_id = e.id AND fa.session_id = e.session_id
            INNER JOIN fee_groups fg   ON fg.id = fa.group_id
            LEFT  JOIN fee_payment_history fph ON fph.fee_allocation_id = fa.id
            WHERE e.branch_id = ?
              AND e.session_id = ?
              AND dva.account_number IS NOT NULL
              AND p.mobileno IS NOT NULL AND p.mobileno != ''
            GROUP BY e.id
            HAVING balance > 0
        ";
        return $this->db->query($sql, [$branchID, $sessionID])->result_array();
    }
}
