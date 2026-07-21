<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pickup_model extends CI_Model
{
    public function getAuthorizedPersons($branchID, $studentID = null)
    {
        $this->db->select('app.*, CONCAT(s.first_name," ",s.last_name) as student_name, s.register_no, c.name as class_name, sec.name as section_name');
        $this->db->from('authorized_pickup_person app');
        $this->db->join('student s', 's.id = app.student_id', 'left');
        $this->db->join('enroll e', 'e.student_id = app.student_id', 'left');
        $this->db->join('class c', 'c.id = e.class_id', 'left');
        $this->db->join('section sec', 'sec.id = e.section_id', 'left');
        $this->db->where('app.branch_id', $branchID);
        if ($studentID) {
            $this->db->where('app.student_id', $studentID);
        }
        $this->db->order_by('student_name, app.name');
        return $this->db->get()->result_array();
    }

    public function getPersonByToken($token)
    {
        return $this->db->select('app.*, CONCAT(s.first_name," ",s.last_name) as student_name, s.register_no, c.name as class_name, sec.name as section_name')
            ->from('authorized_pickup_person app')
            ->join('student s', 's.id = app.student_id', 'left')
            ->join('enroll e', 'e.student_id = app.student_id', 'left')
            ->join('class c', 'c.id = e.class_id', 'left')
            ->join('section sec', 'sec.id = e.section_id', 'left')
            ->where('app.qr_token', $token)
            ->where('app.is_active', 1)
            ->get()->row_array();
    }

    public function getDashboardCounts($branchID)
    {
        $today = date('Y-m-d');
        $total   = (int)$this->db->where('branch_id', $branchID)->count_all_results('authorized_pickup_person');
        $active  = (int)$this->db->where(['branch_id' => $branchID, 'is_active' => 1])->count_all_results('authorized_pickup_person');
        $todayPk = (int)$this->db->where(['branch_id' => $branchID, 'pickup_date' => $today])->count_all_results('pickup_record');
        return compact('total', 'active', 'todayPk');
    }

    public function getPickupRecords($branchID, $date = null)
    {
        $this->db->select('pr.*, app.name as person_name, app.relationship, CONCAT(s.first_name," ",s.last_name) as student_name, s.register_no, c.name as class_name, sec.name as section_name');
        $this->db->from('pickup_record pr');
        $this->db->join('authorized_pickup_person app', 'app.id = pr.pickup_person_id', 'left');
        $this->db->join('student s', 's.id = pr.student_id', 'left');
        $this->db->join('enroll e', 'e.student_id = pr.student_id', 'left');
        $this->db->join('class c', 'c.id = e.class_id', 'left');
        $this->db->join('section sec', 'sec.id = e.section_id', 'left');
        $this->db->where('pr.branch_id', $branchID);
        if ($date) {
            $this->db->where('pr.pickup_date', $date);
        }
        $this->db->order_by('pr.created_at', 'DESC');
        return $this->db->get()->result_array();
    }

    public function savePerson(array $data)
    {
        if (empty($data['id'])) {
            $data['qr_token'] = bin2hex(random_bytes(16));
            $this->db->insert('authorized_pickup_person', $data);
            return $this->db->insert_id();
        }
        $id = $data['id'];
        unset($data['id'], $data['qr_token']);
        $this->db->where('id', $id)->update('authorized_pickup_person', $data);
        return $id;
    }

    public function deletePerson($id, $branchID)
    {
        $this->db->where(['id' => $id, 'branch_id' => $branchID])->delete('authorized_pickup_person');
    }

    public function recordPickup($personID, $studentID, $branchID, $scannedBy, $notes = '')
    {
        return $this->db->insert('pickup_record', [
            'pickup_person_id' => $personID,
            'student_id'       => $studentID,
            'pickup_date'      => date('Y-m-d'),
            'pickup_time'      => date('H:i:s'),
            'scanned_by'       => $scannedBy,
            'notes'            => $notes,
            'branch_id'        => $branchID,
        ]);
    }
}
