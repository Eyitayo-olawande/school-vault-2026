<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class Domain_model extends CI_Model
{
    // ── Trait management ────────────────────────────────────────────

    public function getTraits($type, $branchID)
    {
        $table = $type === 'psychomotor' ? 'psychomotor_domain_trait' : 'affective_domain_trait';
        return $this->db->where('branch_id', $branchID)->order_by('sort_order', 'ASC')->get($table)->result_array();
    }

    public function saveTrait($type, $post, $branchID)
    {
        $table = $type === 'psychomotor' ? 'psychomotor_domain_trait' : 'affective_domain_trait';
        $data  = ['name' => $post['name'], 'sort_order' => (int)($post['sort_order'] ?? 0), 'branch_id' => $branchID];
        if (!empty($post['trait_id'])) {
            $this->db->where('id', $post['trait_id'])->where('branch_id', $branchID)->update($table, $data);
        } else {
            $this->db->insert($table, $data);
        }
    }

    public function deleteTrait($type, $traitID, $branchID)
    {
        $table = $type === 'psychomotor' ? 'psychomotor_domain_trait' : 'affective_domain_trait';
        $this->db->where('id', $traitID)->where('branch_id', $branchID)->delete($table);
    }

    // ── Rating entry ────────────────────────────────────────────────

    public function getRatings($type, $examID, $branchID, $classID = null, $sectionID = null)
    {
        $dTable = $type === 'psychomotor' ? 'psychomotor_domain' : 'affective_domain';
        $tTable = $type === 'psychomotor' ? 'psychomotor_domain_trait' : 'affective_domain_trait';

        $this->db->select("d.enroll_id, d.trait_id, d.rating, t.name as trait_name");
        $this->db->from("$dTable d");
        $this->db->join("$tTable t", "t.id = d.trait_id");
        $this->db->where('d.exam_id', $examID);
        $this->db->where('d.branch_id', $branchID);
        if ($classID)   $this->db->join('enroll e', 'e.id = d.enroll_id')->where('e.class_id', $classID);
        if ($sectionID) $this->db->where('e.section_id', $sectionID);
        $rows = $this->db->get()->result_array();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['enroll_id']][$r['trait_id']] = $r['rating'];
        }
        return $map;
    }

    public function saveRatings($type, $examID, $branchID, $ratings, $enteredBy)
    {
        $dTable = $type === 'psychomotor' ? 'psychomotor_domain' : 'affective_domain';
        foreach ($ratings as $enrollID => $traits) {
            foreach ($traits as $traitID => $rating) {
                $this->db->where(['enroll_id' => $enrollID, 'exam_id' => $examID, 'trait_id' => $traitID])->delete($dTable);
                if ($rating > 0) {
                    $this->db->insert($dTable, [
                        'enroll_id'  => $enrollID,
                        'exam_id'    => $examID,
                        'trait_id'   => $traitID,
                        'rating'     => min(5, max(1, (int)$rating)),
                        'branch_id'  => $branchID,
                        'entered_by' => $enteredBy,
                    ]);
                }
            }
        }
    }

    // ── Report card integration ─────────────────────────────────────

    public function getStudentRatings($type, $enrollID, $examID)
    {
        $dTable = $type === 'psychomotor' ? 'psychomotor_domain' : 'affective_domain';
        $tTable = $type === 'psychomotor' ? 'psychomotor_domain_trait' : 'affective_domain_trait';
        $this->db->select("t.name as trait_name, d.rating");
        $this->db->from("$dTable d");
        $this->db->join("$tTable t", "t.id = d.trait_id");
        $this->db->where('d.enroll_id', $enrollID);
        $this->db->where('d.exam_id', $examID);
        $this->db->order_by('t.sort_order', 'ASC');
        return $this->db->get()->result_array();
    }

    public function getStudentRatingsMultiExam($type, $enrollID, array $examIDs)
    {
        if (empty($examIDs)) return [];
        $dTable = $type === 'psychomotor' ? 'psychomotor_domain' : 'affective_domain';
        $tTable = $type === 'psychomotor' ? 'psychomotor_domain_trait' : 'affective_domain_trait';
        $this->db->select("t.name as trait_name, ROUND(AVG(d.rating),0) as rating");
        $this->db->from("$dTable d");
        $this->db->join("$tTable t", "t.id = d.trait_id");
        $this->db->where('d.enroll_id', $enrollID);
        $this->db->where_in('d.exam_id', $examIDs);
        $this->db->group_by('d.trait_id');
        $this->db->order_by('t.sort_order', 'ASC');
        return $this->db->get()->result_array();
    }

    public static function ratingLabel($r)
    {
        $map = [1 => 'Very Poor', 2 => 'Poor', 3 => 'Fair', 4 => 'Good', 5 => 'Excellent'];
        return $map[(int)$r] ?? '—';
    }
}
