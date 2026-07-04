<?php
// defined('BASEPATH') OR exit('No direct script access allowed');

class Paystack extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // $this->load->model('feespayment_model');
        // $this->load->model('userrole_model');
        // $this->load->model('fees_model');
        // $this->load->library('paypal_payment');
        // $this->load->library('stripe_payment');
        // $this->load->library('razorpay_payment');
        // $this->load->library('sslcommerz');
        // $this->load->library('midtrans_payment');
        // $this->load->library('paytm_kit_lib');
        $this->load->model('Feepaymenttransaction_model');
        $this->load->model('fees_model');
    }

    public function webhook() {
        // Retrieve the request's body and parse it as JSON
        $input = @file_get_contents("php://input");
        $event = json_decode($input);

        //echo json_decode($event);
        log_message('info', $input);

        // // Verify the webhook signature
        // $stripeSignature = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        // $endpoint_secret = 'your_webhook_secret'; // You can find this in the Stripe Dashboard

        // try {
        //     \Stripe\Stripe::setApiKey('your_stripe_secret_key');
        //     $event = \Stripe\Webhook::constructEvent(
        //         $input, $stripeSignature, $endpoint_secret
        //     );
        // } catch(\UnexpectedValueException $e) {
        //     // Invalid payload
        //     http_response_code(400);
        //     exit();
        // } catch(\Stripe\Exception\SignatureVerificationException $e) {
        //     // Invalid signature
        //     http_response_code(400);
        //     exit();
        // }

        // Handle the event
        switch ($event->event) {
            case 'charge.success':
                // $paymentIntent = $event->data->object; // contains a StripePaymentIntent
                // payment info create transaction
                $payment_reference = $event->data->reference;
                $amount = $event->data->amount;
                $paid_at = $event->data->paid_at;
                $fulldate = substr($paid_at,0,10) . ' '.substr($paid_at,11,8);
                $ip_address = $event->data->ip_address;
                $channel = $event->data->channel;
                $sender_bank = $event->data->authorization->sender_bank;
                $sender_name = $event->data->authorization->sender_name;
                $arrayFees = array(
                    'amount' => $amount,
                    'pay_via' => 9,
                    'collect_by' => 'online',
                    'date' => date("Y-m-d"),
                    'paid_date' => $fulldate,
                    'pay_reference' => $payment_reference,
                    'response_data' => $input,
                    'response_status' => 'success',
                    'ip_address' => $ip_address,
                    'channel' => $channel,
                    'sender_bank' => $sender_bank,
                    'sender_name' => $sender_name,
                );
                // Handle the successful payment here for these two channels
                // channel: bank_transfer && channel: bank
                if ($channel == 'bank_transfer' || $channel == 'bank') {
                   $this->update($payment_reference, $arrayFees);
                   $this->save_payment_history($payment_reference); 
                }
                else {
                    // save transaction details to
                    // paystack_logs table and wallet table for the channel
                    // channel: dedicated_nuban
                    if ($channel == 'dedicated_nuban') {
                        $result = $this->save_paystack_response($event->data);

                        //Apply Wallet Entry against Allocations if successful
                        if ($result) {
                            $this->save_transaction($event);
                        }
                    }
                }
            
                break;
            case 'customeridentification.failed':
                // $paymentIntent = $event->data->object; // contains a StripePaymentIntent
                // Handle the failed payment here
                // payment info create transaction
                $customer_id = '';
                $arrayFees = array(
                    'allocation_id' => 0,
                    'type_id' => 0,
                    'amount' => 90000,
                    'pay_via' => 9,
                    'collect_by' => 'online',
                    'date' => date("Y-m-d"),
                    'pay_reference' => $customer_id,
                    'response_data' => $input,
                    'response_status' => 'failed',
                );
                $this->create($arrayFees);
                break;
            case 'customeridentification.success':
                break;
            case 'dedicatedaccount.assign.success':
                // account creation info response
                $customer_id = $event->data->customer->id;
                $email = $event->data->customer->email;
                $customer_code = $event->data->customer->customer_code;
                $dedicated_account_bank = $event->data->dedicated_account->bank->name;
                $dedicated_account_bank_id = $event->data->dedicated_account->bank->id;
                $account_name = $event->data->dedicated_account->account_name;
                $account_number = $event->data->dedicated_account->account_number;
                $assigned_status = $event->data->dedicated_account->assigned;
                $currency = $event->data->dedicated_account->currency;
                $active = $event->data->dedicated_account->active;
                $account_id = $event->data->dedicated_account->id;
                $created_at = $event->data->dedicated_account->created_at;
                $updated_at = $event->data->dedicated_account->updated_at;
                $assignee_type = $event->data->dedicated_account->assignment->assignee_type;
                $expired = $event->data->dedicated_account->assignment->expired;
                $account_type = $event->data->dedicated_account->assignment->account_type;
                $assigned_at = $event->data->dedicated_account->assignment->assigned_at;
                $expired_at = is_null($event->data->dedicated_account->assignment->expired_at) ? "" : $event->data->dedicated_account->assignment->expired_at;
                $assignment_expires_at = "";
                $raw_response = json_encode($event->data);

                $customer_data = array(
                    'customer_id' => $customer_id,
                    'email' => $email,
                    'customer_code' => $customer_code,
                    'dedicated_account_bank' => $dedicated_account_bank,
                    'dedicated_account_bank_id' => $dedicated_account_bank_id,
                    'account_name' => $account_name,
                    'account_number' => $account_number,
                    'assigned_status' => $assigned_status,
                    'currency' => $currency,
                    'active' => $active,
                    'account_id' => $account_id,
                    'created_at' => $created_at,
                    'updated_at' => $updated_at,
                    'assignee_type' => $assignee_type,
                    'expired' => $expired,
                    'account_type' => $account_type,
                    'assigned_at' => $assigned_at,
                    'expired_at' => $expired_at,
                    'assignment_expires_at' => $assignment_expires_at,
                    'raw_response' => $raw_response,
                );
                
                $this->save_dva_data($customer_data);
                break;
            case 'dedicatedaccount.assign.failed':
                break;
            case 'charge.testing':
                $this->save_transaction($event);
                break;
            default:
                echo 'Received unknown event type ' . $event->event;
        }

        $response = ['status' => 'success', 'message' => 'Payment processed successfully'];
        http_response_code(200); // Respond with a 200 status code to acknowledge receipt of the event
        echo $input;
    }

    public function create($paymentData) {
        // Example data to insert
        $this->Feepaymenttransaction_model->insert_transaction($paymentData);
    }

    public function update($payment_reference, $paymentData) {
        // Example data to insert
        $this->Feepaymenttransaction_model->update_transaction_by_reference($payment_reference, $paymentData);
    }

    public function gettransactionbyreference($payment_reference) {
        // Example data to insert
        return $this->Feepaymenttransaction_model->get_transaction_by_reference($payment_reference);
    }

    private function save_transaction($event) {
        $payment_reference = $event->data->reference;
        $amount = $event->data->amount;
        $paid_at = $event->data->paid_at;
        $fulldate = substr($paid_at,0,10) . ' '.substr($paid_at,11,8);
        $ip_address = $event->data->ip_address;
        $channel = $event->data->channel;
        $sender_bank = $event->data->authorization->sender_bank;
        $sender_name = $event->data->authorization->sender_name;
        $email = $event->data->customer->email;

        $student_wallet = $this->db->select('*')
            ->where('email_address', $email)
            ->from('student_wallet')
            ->get()->row_array();
        $wallet_amount = $student_wallet['amount'];
        $sessionID = get_session_id();
        log_message('info', 'Wallet Amount: '.$wallet_amount);
        $student_data = $this->db->select('*')
            ->where('email', $email)
            ->from('student')
            ->get()->row_array();
        $student_id = $student_data['id'];
        $this->db->where('student_id', $student_id);
        $this->db->where('session_id', $sessionID); //Changed on 2026-04-14 by Hafeez
        //$this->db->order_by('session_id', 'desc'); //Changed on 2026-04-14 by Hafeez
        $result = $this->db->get('fee_allocation')->result_array();
        log_message('info', $this->db->last_query()); //Changed on 2026-04-14 by Hafeez
        // echo json_encode($result);
        $fee_balances = [];
        foreach ($result as $key => $value) {
            $allocation_id = $value['id'];
            $group_id = $value['group_id'];
            $groupsDetails = $this->db->select('fee_type_id')->where('fee_groups_id', $group_id)->get('fee_groups_details')->result();
            foreach ($groupsDetails as $k => $type) {
                //$fine = $this->fees_model->feeFineCalculation($allocation_id, $type->fee_type_id);
                $b = $this->fees_model->getBalance($allocation_id, $type->fee_type_id);
                $total_balance = $b['balance'];
                //$total_fine += abs($fine - $b['fine']);
                $fee_type_id = $type->fee_type_id;
            }
            $balance = array(
                'allocation_id' => $allocation_id,
                'type_id' => $fee_type_id,
                'balance' => $total_balance
            );
            array_push($fee_balances, $balance);
        }
        echo json_encode($fee_balances);

        // Apply Wallet Amount Against Student Balances
        $counter = 1;
        foreach ($fee_balances as $value) {
            //Skip entry when balance is Zero
            if ($value['balance'] <= 0) {
                continue;
            }
            if ($value['balance'] <= $wallet_amount) {
                $wallet_amount -= $value['balance'];
                log_message('info', 'Deduction 1: '.$counter.' - Owed Balance : '.$value['balance'].' | New Wallet Amount : '.$wallet_amount);

                // Save payment data into fee_payment_history
                $arrayFees = array(
                    'allocation_id' => $value['allocation_id'],
                    'type_id' => $value['type_id'],
                    'collect_by' => "",
                    'amount' => $value['balance'],
                    'discount' => 0,
                    'fine' => 0,
                    'pay_via' => 99,
                    'collect_by' => 'wallet',
                    'remarks' => "Fees deposits online via DVA Wallet: " . $student_wallet['id']. ' for allocation '.$value['allocation_id'].' at '.date('Y-m-d H:i:s:u'),
                    'date' => date("Y-m-d"),
                );
                $result = $this->savePaymentData($arrayFees);

                // Update Student Wallet only after successful entry
                if ($result) {
                    $timestamp = date('Y-m-d H:i:s');
                    $walletData = array(
                        'id' => $student_wallet['id'],
                        'amount' => $wallet_amount,
                        'updated_at' => $timestamp,
                    );
                    $this->update_student_wallet($walletData);  
                }

                $counter+=1;
                
            } else {
                // Apply whatever amount is in the wallet to the available allocations
                if ($wallet_amount >=1) {
                   // Save payment data into fee_payment_history
                    $arrayFees = array(
                        'allocation_id' => $value['allocation_id'],
                        'type_id' => $value['type_id'],
                        'collect_by' => "",
                        'amount' => $wallet_amount,
                        'discount' => 0,
                        'fine' => 0,
                        'pay_via' => 99,
                        'collect_by' => 'wallet',
                        'remarks' => "Fees deposits online via DVA Wallet: " . $student_wallet['id']. ' for allocation '.$value['allocation_id'].' at '.date('Y-m-d H:i:s:u'),
                        'date' => date("Y-m-d"),
                    );
                    
                    $result = $this->savePaymentData($arrayFees);

                    // Update Student Wallet only after successful entry
                    if ($result) {
                        $timestamp = date('Y-m-d H:i:s');
                        $walletData = array(
                            'id' => $student_wallet['id'],
                            'amount' => 0,
                            'updated_at' => $timestamp,
                        );
                        $this->update_student_wallet($walletData);

                        // Set Wallet Amount to Zero
                        $wallet_amount = 0;
                    }
                    
                }
            }
        }
    }

    private function save_payment_history($payment_reference) {
        $payment_transaction_data = $this->gettransactionbyreference($payment_reference);
        $arrayFeesData = array(
            'allocation_id' => $payment_transaction_data->allocation_id,
            'type_id' => $payment_transaction_data->type_id,
            'collect_by' => $payment_transaction_data->collect_by,
            'amount' => ($payment_transaction_data->amount / 100),
            'discount' => 0,
            'fine' => 0,
            'pay_via' => 9,
            'remarks' => "Fees deposits online via Paystack Ref ID: " . $payment_reference,
            'date' => date("Y-m-d"),
        );
        $this->db->select('*'); 
        $this->db->from('fee_payment_history'); 
        $this->db->like('remarks', $payment_reference); 
        $recordCount = $this->db->count_all_results();
        // $recordCount = $this->db->like('remarks', $payment_reference)->count_all_results('fee_payment_history');
        if ($recordCount < 1) {
            $this->db->insert('fee_payment_history', $arrayFeesData);
        }
        
    }

    private function save_dva_data($dva_data) {
        $student_data = $this->db->select('*')
            ->where('email', $dva_data['email'])
            ->from('student')
            ->get()->row_array();

        $dedicated_virtual_account_data = array(
            'user_id' => $student_data['id'],
            'customer_id' => $dva_data['customer_id'],
            'customer_code' => $dva_data['customer_code'],
            'dedicated_account_bank' => $dva_data['dedicated_account_bank'],
            'dedicated_account_bank_id' => $dva_data['dedicated_account_bank_id'],
            'account_name' => $dva_data['account_name'],
            'account_number' => $dva_data['account_number'],
            'assigned_status' => $dva_data['assigned_status'],
            'currency' => $dva_data['currency'],
            'active' => $dva_data['active'],
            'account_id' => $dva_data['account_id'],
            'created_at' => $dva_data['created_at'],
            'updated_at' => $dva_data['updated_at'],
            'assignee_type' => $dva_data['assignee_type'],
            'expired' => $dva_data['expired'],
            'account_type' => $dva_data['account_type'],
            'assigned_at' => $dva_data['assigned_at'],
            'expired_at' => $dva_data['expired_at'],
            'assignment_expires_at' => $dva_data['assignment_expires_at'],
            'raw_response' => $dva_data['raw_response'],
        );
        
        $this->db->select('*'); 
        $this->db->from('dedicated_virtual_account'); 
        $this->db->like('account_number', $dva_data['account_number']); 
        $recordCount = $this->db->count_all_results();
        // $recordCount = $this->db->like('remarks', $payment_reference)->count_all_results('fee_payment_history');
        if ($recordCount < 1) {
            $this->db->insert('dedicated_virtual_account', $dedicated_virtual_account_data);
        }
        
    }

    private function handle_successful_payment($paymentData) {
        // Implement your logic to handle successful payments
        // For example, update the order status in your database
        // $orderId = $paymentIntent->metadata->order_id; // Assuming you have an order_id in the metadata
        // Update order status in the database
        // $this->order_model->update_status($orderId, 'paid');
        $this->Feepaymenttransaction_model->insert_transaction($paymentData);
    }

    private function handle_failed_payment($paymentData) {
        // Implement your logic to handle failed payments
        // For example, update the order status in your database
        // $orderId = $paymentIntent->metadata->order_id; // Assuming you have an order_id in the metadata
        $payment_reference = $paymentData->event->customer_id;
        // Update order status in the database
        $this->Feepaymenttransaction_model->update_transaction($payment_reference, $data);
    }

    private function save_paystack_response($paymentData) {
        // Log all the event.success details to the paystack_logs table
        $payment_reference = $paymentData->reference;
        $amount = $paymentData->amount;
        $paid_at = $paymentData->paid_at;
        $fulldate = substr($paid_at,0,10) . ' '.substr($paid_at,11,8);
        $channel = $paymentData->channel;
        $sender_bank = $paymentData->authorization->sender_bank;
        $sender_name = $paymentData->authorization->sender_name;
        $raw_response = json_encode($paymentData);
        $status = $paymentData->status;
        $customer_email = $paymentData->customer->email;
        $customer_id = $paymentData->customer->id;
        $authorization_code = $paymentData->authorization->authorization_code;
        $authorization_card_type = $paymentData->authorization->card_type;
        $authorization_bank = $paymentData->authorization->bank;
        $authorization_sender_name = $paymentData->authorization->sender_name;
        $authorization_narration = $paymentData->authorization->narration;
        $paystackData = array(
            'amount' => $amount,
            'json_data' => $raw_response,
            'paid_date' => $fulldate,
            'reference' => $payment_reference,
            'status' => 'success',
            'customer_email' => $customer_email,
            'customer_id' => $customer_id,
            'authorization_code' => $authorization_code,
            'authorization_card_type' => $authorization_card_type,
            'authorization_bank' => $authorization_bank,
            'authorization_sender_name' => $authorization_sender_name,
            'authorization_narration' => $authorization_narration,
        );
        $this->db->insert('paystack_logs', $paystackData);
        $paystack_log_id = $this->db->insert_id();

        $result = false;

        // Save details to Wallet if entry succeeds
        if($paystack_log_id > 0) {
            $query = $this->db->select('id')->where('email', $customer_email)->get('student');
            $student_data = $query->row(); // Returns a single row object
            $timestamp = date('Y-m-d H:i:s');
            $walletData = array(
                'student_id' => $student_data->id,
                'email_address' => $customer_email,
                'amount' => ($amount / 100),
                'payment_gateway' => 'paystack',
                'payment_gateway_reference' => $payment_reference,
                'payment_channel' => 'dedicated_nuban',
                'update_count' => 0,
                'updated_at' => $timestamp,
            );
            $result = $this->save_student_wallet($walletData);
            return $result;
        }

        return $result;
    }

    private function save_student_wallet($walletData) {
        $response = false;
        $query = $this->db->select('id, payment_gateway_reference, email_address')->like('payment_gateway_reference', $walletData['payment_gateway_reference'])->get('student_wallet');
        $student_wallet_data = $query->row(); // Returns a single row object
        if(is_null($student_wallet_data)) {
            // check student already exist and update amount
            $queryEmail = $this->db->select('id, payment_gateway_reference, email_address, amount, update_count')->where('email_address', $walletData['email_address'])->get('student_wallet');
            $existing_student_wallet_data = $queryEmail->row(); // Returns a single row object
            if($existing_student_wallet_data) {
                $this->db->where('id', $existing_student_wallet_data->id);
                log_message('info', 'Student Wallet Amount '.$existing_student_wallet_data->amount.' for '.$walletData['email_address']);
                $walletData['amount'] = $existing_student_wallet_data->amount + $walletData['amount'];
                $walletData['payment_gateway_reference'] = $existing_student_wallet_data->payment_gateway_reference.','.$walletData['payment_gateway_reference'];
                $walletData['update_count'] = $existing_student_wallet_data->update_count + 1;
                $response = $this->db->update('student_wallet', $walletData);
            } else {
                $response = $this->db->insert('student_wallet', $walletData);
            }
            // $paystack_log_id = $this->db->insert_id();
        } else {
            log_message('error','Payment Gateway Reference '.$student_wallet_data->payment_gateway_reference.' for '.$walletData['email_address'].' already applied');
            // $this->db->where('id', $student_wallet_data->id);
            // $walletData['payment_gateway_reference'] = $student_wallet_data->payment_gateway_reference.','.$walletData['payment_gateway_reference'];
            // $this->db->update('student_wallet', $walletData);
        }

        return $response;
    }

    private function update_student_wallet($wallet_data) {
        $this->db->where('id', $wallet_data['id']);
        $this->db->update('student_wallet', $wallet_data);
    }

    private function savePaymentData($data)
    {
        // insert in DB
        $result = $this->db->insert('fee_payment_history', $data);

        // transaction voucher save function
        $getSeeting = $this->fees_model->get('transactions_links', array('branch_id' => get_loggedin_branch_id()), true);
        if ($getSeeting['status']) {
            $arrayTransaction = array(
                'account_id' => $getSeeting['deposit'],
                'amount' => $data['amount'] + $data['fine'],
                'date' => $data['date'],
            );
            $this->fees_model->saveTransaction($arrayTransaction);
        }

        return $result;
    }

}