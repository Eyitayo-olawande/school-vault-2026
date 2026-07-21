<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pickup extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('pickup_model');
    }

    public function index()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();

        if ($_POST) {
            $id        = (int)$this->input->post('person_id');
            $studentID = (int)$this->input->post('student_id');
            $name      = $this->input->post('name');
            $rel       = $this->input->post('relationship');
            $phone     = $this->input->post('phone');
            $idNum     = $this->input->post('id_number');
            $active    = $this->input->post('is_active') ? 1 : 0;

            if (empty($name) || empty($studentID)) {
                set_alert('error', 'Student and name are required.');
            } else {
                $this->pickup_model->savePerson([
                    'id'           => $id ?: null,
                    'student_id'   => $studentID,
                    'name'         => $name,
                    'relationship' => $rel,
                    'phone'        => $phone,
                    'id_number'    => $idNum,
                    'is_active'    => $active,
                    'branch_id'    => $branchID,
                ]);
                set_alert('success', $id ? 'Authorized person updated.' : 'Authorized person added.');
            }
            redirect(current_url());
        }

        $counts = $this->pickup_model->getDashboardCounts($branchID);
        $this->data['persons']    = $this->pickup_model->getAuthorizedPersons($branchID);
        $this->data['total']      = $counts['total'];
        $this->data['active']     = $counts['active'];
        $this->data['today_pk']   = $counts['todayPk'];
        $this->data['branch_id']  = $branchID;
        $this->data['title']      = 'Authorized Pickups';
        $this->data['sub_page']   = 'pickup/index';
        $this->data['main_menu']  = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    public function delete($id)
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $this->pickup_model->deletePerson($id, $branchID);
        set_alert('success', 'Person removed.');
        redirect(base_url('pickup'));
    }

    public function records()
    {
        if (!get_permission('student_attendance_report', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $date     = $this->input->get('date') ?: date('Y-m-d');
        $this->data['records']   = $this->pickup_model->getPickupRecords($branchID, $date);
        $this->data['date']      = $date;
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'Pickup Records';
        $this->data['sub_page']  = 'pickup/records';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    public function scan()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'Pickup QR Scanner';
        $this->data['sub_page']  = 'pickup/scan';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    /** AJAX: verify QR token and log the pickup. */
    public function verify_qr()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
            return;
        }
        $token    = $this->input->post('token');
        $branchID = $this->application_model->get_branch_id();
        $person   = $this->pickup_model->getPersonByToken($token);

        if (empty($person)) {
            echo json_encode(['status' => 'error', 'message' => 'QR code not recognized or person is inactive.']);
            return;
        }
        if ((int)$person['branch_id'] !== (int)$branchID && !is_superadmin_loggedin()) {
            echo json_encode(['status' => 'error', 'message' => 'This QR code is from a different branch.']);
            return;
        }

        $this->pickup_model->recordPickup(
            $person['id'],
            $person['student_id'],
            $branchID,
            get_loggedin_id()
        );

        echo json_encode([
            'status'       => 'success',
            'person_name'  => $person['name'],
            'relationship' => $person['relationship'],
            'student_name' => $person['student_name'],
            'class_name'   => $person['class_name'],
            'section_name' => $person['section_name'],
            'register_no'  => $person['register_no'],
            'time'         => date('H:i'),
        ]);
    }

    /** Printable QR card for an authorized pickup person. */
    public function qr_card($id = 0)
    {
        if (!get_permission('student_attendance', 'is_add')) {
            access_denied();
        }
        $id       = (int)$id;
        $branchID = $this->application_model->get_branch_id();

        $person = $this->db
            ->select('app.*, s.first_name, s.last_name, s.register_no, s.photo as student_photo,
                      e.class_id, e.section_id, c.name as class_name, sec.name as section_name')
            ->from('authorized_pickup_person app')
            ->join('student s', 's.id = app.student_id')
            ->join('enroll e', 'e.student_id = s.id')
            ->join('class c', 'c.id = e.class_id')
            ->join('section sec', 'sec.id = e.section_id')
            ->where('app.id', $id)
            ->where('app.branch_id', $branchID)
            ->get()->row_array();

        if (empty($person)) {
            show_404();
        }

        $this->load->library('Ciqrcode');
        ob_start();
        $this->ciqrcode->generate([
            'data'     => $person['qr_token'],
            'savename' => null,
            'level'    => 'L',
            'size'     => 7,
        ]);
        $imgBytes = ob_get_clean();

        $this->data['person']    = $person;
        $this->data['qr_data_uri'] = 'data:image/png;base64,' . base64_encode($imgBytes);
        $this->data['branch_id'] = $branchID;
        $this->data['title']     = 'Pickup QR Card';
        $this->data['sub_page']  = 'pickup/qr_card';
        $this->data['main_menu'] = 'attendance';
        $this->load->view('layout/index', $this->data);
    }

    /** AJAX: get students for branch (for add-person form dropdown). */
    public function get_students()
    {
        if (!get_permission('student_attendance', 'is_add')) {
            echo '<option value="">Access denied</option>';
            return;
        }
        $branchID = is_superadmin_loggedin()
            ? (int)$this->input->post('branch_id')
            : $this->application_model->get_branch_id();

        $students = $this->db->select('s.id, CONCAT(s.first_name," ",s.last_name," (",s.register_no,")") as label')
            ->from('student s')
            ->join('enroll e', 'e.student_id = s.id', 'inner')
            ->where('e.branch_id', $branchID)
            ->order_by('s.first_name')
            ->get()->result_array();

        echo '<option value="">— Select Student —</option>';
        foreach ($students as $st) {
            echo '<option value="' . $st['id'] . '">' . html_escape($st['label']) . '</option>';
        }
    }
}
