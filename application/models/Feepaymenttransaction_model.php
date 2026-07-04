<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Feepaymenttransaction_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        // Load the database library
        $this->load->database();
    }

    // Function to get all transactions
    public function get_transactions() {
        $query = $this->db->get('fee_payment_transaction');
        return $query->result();
    }

    // Function to get a transaction by ID
    public function get_transaction_by_id($id) {
        $query = $this->db->get_where('fee_payment_transaction', array('id' => $id));
        return $query->row();
    }

    // Function to get a transaction by ID
    public function get_transaction_by_reference($pay_reference) {
        $query = $this->db->get_where('fee_payment_transaction', array('pay_reference' => $pay_reference));
        return $query->row();
    }

    // Function to insert a new transaction
    public function insert_transaction($data) {
        return $this->db->insert('fee_payment_transaction', $data);
    }

    // Function to update an existing transaction
    public function update_transaction($id, $data) {
        $this->db->where('id', $id);
        return $this->db->update('fee_payment_transaction', $data);
    }

    // Function to update an existing transaction
    public function update_transaction_by_reference($pay_reference, $data) {
        $this->db->where('pay_reference', $pay_reference);
        return $this->db->update('fee_payment_transaction', $data);
    }

    // Function to delete a transaction
    public function delete_transaction($id) {
        $this->db->where('id', $id);
        return $this->db->delete('fee_payment_transaction');
    }
}