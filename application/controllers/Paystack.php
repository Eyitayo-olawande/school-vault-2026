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

        // Verify the Paystack webhook signature (HMAC-SHA512 over the raw body,
        // keyed with the branch's secret key) before trusting the payload.
        // See https://paystack.com/docs/payments/webhooks/#verifying-events
        $signature = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : '';
        if ($signature === '' || !$this->verify_paystack_signature($input, $signature)) {
            log_message('error', 'Paystack webhook: missing or invalid X-Paystack-Signature, request rejected');
            http_response_code(401);
            exit();
        }

        // Acknowledge immediately so Paystack does not retry while we do DB work.
        // On PHP-FPM (cPanel/Apache), fastcgi_finish_request() sends the response
        // and lets the script keep running. The flush() path covers Apache mod_php.
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment processed successfully']);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level()) { ob_end_flush(); }
            flush();
        }

        $event = json_decode($input);

        log_message('info', $input);

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
                log_message('info', 'Paystack webhook: unhandled event type ' . $event->event);
        }
    }

    private function verify_paystack_signature($payload, $signature) {
        // Multi-tenant: each branch has its own Paystack secret key, and the
        // webhook payload doesn't identify the branch up front, so check the
        // signature against every branch with Paystack enabled.
        $configs = $this->db->select('paystack_secret_key')
            ->where('paystack_status', 1)
            ->where('paystack_secret_key !=', '')
            ->get('payment_config')
            ->result_array();

        foreach ($configs as $config) {
            $expected = hash_hmac('sha512', $payload, $config['paystack_secret_key']);
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
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
        $email             = $event->data->customer->email;

        // Student DVA path (the common case)
        $student_wallet = $this->db->select('*')
            ->where('email_address', $email)
            ->from('student_wallet')
            ->get()->row_array();

        if (!empty($student_wallet)) {
            $this->distribute_student_wallet($student_wallet, $payment_reference);
            return;
        }

        // Family DVA path — Phase 6: parent email registered as DVA customer
        $parent_wallet = $this->db->select('*')
            ->where('email_address', $email)
            ->from('parent_wallet')
            ->get()->row_array();

        if (!empty($parent_wallet)) {
            $this->distribute_parent_wallet($parent_wallet, $payment_reference);
            return;
        }

        log_message('error', 'DVA save_transaction: no wallet (student or parent) for email=' . $email . ' ref=' . $payment_reference);
    }

    // Distribute a student's DVA wallet across their own fee allocations (proportionally).
    private function distribute_student_wallet($student_wallet, $payment_reference) {
        $wallet_id     = $student_wallet['id'];
        $wallet_amount = (float) $student_wallet['amount'];
        $email         = $student_wallet['email_address'];

        $student_data = $this->db->select('id')
            ->where('email', $email)
            ->from('student')
            ->get()->row_array();

        if (empty($student_data)) {
            log_message('error', 'DVA distribute_student_wallet: no student for email=' . $email . ' ref=' . $payment_reference);
            return;
        }

        // Idempotency: compound keys are {ref}_alloc{id}_type{id} — check prefix
        $already = $this->db->query(
            'SELECT COUNT(*) AS cnt FROM fee_payment_history WHERE gateway_reference LIKE CONCAT(?, "_alloc%")',
            [$payment_reference]
        )->row()->cnt;
        if ($already > 0) {
            log_message('info', 'DVA distribute_student_wallet: already applied ref=' . $payment_reference);
            return;
        }

        $fee_balances = $this->build_fee_balances((int) $student_data['id']);

        if (empty($fee_balances) || $wallet_amount < 1) {
            log_message('info', 'DVA distribute_student_wallet: nothing to apply ref=' . $payment_reference . ' (types=' . count($fee_balances) . ' wallet=' . $wallet_amount . ')');
            return;
        }

        $history_inserts = $this->build_proportional_inserts($fee_balances, $wallet_amount, $wallet_id, $payment_reference, false);
        $applied  = array_sum(array_column($history_inserts, 'amount'));
        $remaining = max(0, $wallet_amount - $applied);

        $this->db->trans_start();
        foreach ($history_inserts as $row) { $this->savePaymentData($row); }
        $this->update_student_wallet(['id' => $wallet_id, 'amount' => $remaining, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            log_message('error', 'DVA distribute_student_wallet: DB transaction FAILED ref=' . $payment_reference);
        } else {
            log_message('info', 'DVA distribute_student_wallet: committed ' . count($history_inserts) . ' rows, new_wallet=' . $remaining . ' ref=' . $payment_reference);
        }
    }

    // Phase 6: distribute a parent's DVA wallet proportionally across all children's fee allocations.
    private function distribute_parent_wallet($parent_wallet, $payment_reference) {
        $wallet_id     = $parent_wallet['id'];
        $wallet_amount = (float) $parent_wallet['amount'];
        $parent_id     = (int) $parent_wallet['parent_id'];

        // Idempotency: family rows use {ref}_parent{student_id}_alloc{id}_type{id}
        $already = $this->db->query(
            'SELECT COUNT(*) AS cnt FROM fee_payment_history WHERE gateway_reference LIKE CONCAT(?, "_parent%")',
            [$payment_reference]
        )->row()->cnt;
        if ($already > 0) {
            log_message('info', 'DVA distribute_parent_wallet: already applied ref=' . $payment_reference);
            return;
        }

        $children = $this->db->select('id')
            ->where('parent_id', $parent_id)
            ->get('student')->result_array();

        if (empty($children)) {
            log_message('error', 'DVA distribute_parent_wallet: no children for parent_id=' . $parent_id . ' ref=' . $payment_reference);
            return;
        }

        // Collect outstanding balances across all children
        $all_fee_balances = [];
        foreach ($children as $child) {
            $child_balances = $this->build_fee_balances((int) $child['id']);
            foreach ($child_balances as $b) {
                $b['student_id'] = (int) $child['id'];
                $all_fee_balances[] = $b;
            }
        }

        if (empty($all_fee_balances) || $wallet_amount < 1) {
            log_message('info', 'DVA distribute_parent_wallet: nothing to apply ref=' . $payment_reference . ' parent_id=' . $parent_id);
            return;
        }

        $history_inserts = $this->build_proportional_inserts($all_fee_balances, $wallet_amount, $wallet_id, $payment_reference, true);
        $applied   = array_sum(array_column($history_inserts, 'amount'));
        $remaining = max(0, $wallet_amount - $applied);

        $this->db->trans_start();
        foreach ($history_inserts as $row) { $this->savePaymentData($row); }
        $this->db->where('id', $wallet_id)->update('parent_wallet', ['amount' => $remaining, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            log_message('error', 'DVA distribute_parent_wallet: DB transaction FAILED ref=' . $payment_reference);
        } else {
            log_message('info', 'DVA distribute_parent_wallet: committed ' . count($history_inserts) . ' rows across ' . count($children) . ' children, new_wallet=' . $remaining . ' ref=' . $payment_reference);
        }
    }

    // Build an array of outstanding (allocation_id, type_id, balance) for a student in the current session.
    private function build_fee_balances($student_id) {
        // No session filter: a promoted student's old-session outstanding fees must
        // also receive DVA payments. session_id = current only would silently skip
        // prior-year balances after promotion.
        $allocations = $this->db
            ->where('student_id', $student_id)
            ->get('fee_allocation')->result_array();

        $fee_balances = [];
        foreach ($allocations as $alloc) {
            $allocation_id = $alloc['id'];
            $group_id      = $alloc['group_id'];
            $groupsDetails = $this->db->select('fee_type_id')
                ->where('fee_groups_id', $group_id)
                ->get('fee_groups_details')->result();
            foreach ($groupsDetails as $type) {
                $b       = $this->fees_model->getBalance($allocation_id, $type->fee_type_id);
                $balance = (float) $b['balance'];
                if ($balance <= 0) { continue; }
                $fee_balances[] = [
                    'allocation_id' => $allocation_id,
                    'type_id'       => $type->fee_type_id,
                    'balance'       => $balance,
                ];
            }
        }
        return $fee_balances;
    }

    // Phase 7: build fee_payment_history inserts using proportional distribution.
    // Proportional allocation ensures no single fee type is fully cleared while others remain
    // completely unpaid — each type receives a share proportional to its outstanding balance.
    private function build_proportional_inserts($fee_balances, $wallet_amount, $wallet_id, $payment_reference, $is_family = false) {
        $total_outstanding = array_sum(array_column($fee_balances, 'balance'));
        $to_distribute     = min($wallet_amount, $total_outstanding);
        $today             = date('Y-m-d');

        $history_inserts = [];
        foreach ($fee_balances as $item) {
            if ($to_distribute <= 0) { break; }
            $share = ($total_outstanding > 0) ? ($item['balance'] / $total_outstanding * $to_distribute) : 0;
            $apply = round(min($item['balance'], $share), 2);
            if ($apply < 0.01) { continue; }

            $ref_suffix = $is_family
                ? '_parent' . ($item['student_id'] ?? 0) . '_alloc' . $item['allocation_id'] . '_type' . $item['type_id']
                : '_alloc' . $item['allocation_id'] . '_type' . $item['type_id'];

            $history_inserts[] = [
                'allocation_id'     => $item['allocation_id'],
                'type_id'           => $item['type_id'],
                'collect_by'        => 'wallet',
                'amount'            => $apply,
                'discount'          => 0,
                'fine'              => 0,
                'pay_via'           => 99,
                'response_status'   => 'success',
                'remarks'           => 'Fees deposits online via DVA Wallet: ' . $wallet_id
                                       . ' alloc=' . $item['allocation_id']
                                       . ' ref=' . $payment_reference,
                'gateway_reference' => $payment_reference . $ref_suffix,
                'date'              => $today,
            ];
        }
        return $history_inserts;
    }

    private function save_payment_history($payment_reference) {
        $payment_transaction_data = $this->gettransactionbyreference($payment_reference);
        $arrayFeesData = array(
            'allocation_id'     => $payment_transaction_data->allocation_id,
            'type_id'           => $payment_transaction_data->type_id,
            'collect_by'        => $payment_transaction_data->collect_by,
            'amount'            => ($payment_transaction_data->amount / 100),
            'discount'          => 0,
            'fine'              => 0,
            'pay_via'           => 9,
            'gateway_reference' => $payment_reference,
            'response_status'   => $payment_transaction_data->response_status ?? 'success',
            'remarks'           => "Fees deposits online via Paystack Ref ID: " . $payment_reference,
            'date'              => date("Y-m-d"),
        );
        // Use gateway_reference (UNIQUE) for idempotency — no substring false-positives
        $recordCount = $this->db->where('gateway_reference', $payment_reference)->count_all_results('fee_payment_history');
        if ($recordCount < 1) {
            $this->db->insert('fee_payment_history', $arrayFeesData);
            // Remove the staging record now that it has been applied
            $this->db->where('pay_reference', $payment_reference)->delete('fee_payment_transaction');
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

        if ($paystack_log_id > 0) {
            $amount_naira = $amount / 100;
            $timestamp    = date('Y-m-d H:i:s');

            $student_data = $this->db->select('id')->where('email', $customer_email)->get('student')->row();

            // DVA school-format emails (firstname.lastname@school.com) won't match the
            // student's personal email stored in `student.email`. Fall back to the
            // Paystack customer_id → dedicated_virtual_account → student link.
            if (!$student_data) {
                $dva_row = $this->db->select('user_id')
                    ->where('customer_id', $customer_id)
                    ->get('dedicated_virtual_account')->row();
                if ($dva_row) {
                    $student_data = $this->db->select('id')
                        ->where('id', (int) $dva_row->user_id)
                        ->get('student')->row();
                    if ($student_data) {
                        log_message('info', 'DVA save_paystack_response: resolved email=' . $customer_email
                            . ' via DVA customer_id=' . $customer_id . ' to student_id=' . $student_data->id);

                        // Misattribution guard: if this email belongs to a DIFFERENT student, the
                        // paystack_logs entry is phantom — reject it rather than crediting the wrong wallet.
                        $email_owner = $this->db->select('id')
                            ->where('email', $customer_email)
                            ->where('id !=', (int) $student_data->id)
                            ->get('student')->row();
                        if ($email_owner) {
                            log_message('error', 'DVA save_paystack_response PHANTOM REJECTED: ref=' . $payment_reference
                                . ' customer_email=' . $customer_email . ' belongs to student_id=' . $email_owner->id
                                . ' not DVA owner student_id=' . $student_data->id . ' — skipping wallet credit');
                            $student_data = null;
                        }
                    }
                }
            }

            if ($student_data) {
                // Student DVA wallet (existing path)
                $walletData = array(
                    'student_id'                => $student_data->id,
                    'email_address'             => $customer_email,
                    'amount'                    => $amount_naira,
                    'payment_gateway'           => 'paystack',
                    'payment_gateway_reference' => $payment_reference,
                    'payment_channel'           => 'dedicated_nuban',
                    'update_count'              => 0,
                    'updated_at'                => $timestamp,
                );
                $result = $this->save_student_wallet($walletData);
            } else {
                // Phase 6: check if this is a parent DVA email
                $parent_data = $this->db->select('id')->where('email', $customer_email)->get('parent')->row();
                if (!$parent_data) {
                    // Same fallback for parent DVA email: customer_id → parent_wallet email
                    $dva_row = $dva_row ?? $this->db->select('user_id')
                        ->where('customer_id', $customer_id)
                        ->get('dedicated_virtual_account')->row();
                    if ($dva_row) {
                        $parent_data = $this->db->select('id')
                            ->where('id', (int) $dva_row->user_id)
                            ->get('parent')->row();
                    }
                }
                if ($parent_data) {
                    $walletData = array(
                        'parent_id'                 => $parent_data->id,
                        'email_address'             => $customer_email,
                        'amount'                    => $amount_naira,
                        'payment_gateway'           => 'paystack',
                        'payment_gateway_reference' => $payment_reference,
                        'payment_channel'           => 'dedicated_nuban',
                        'update_count'              => 0,
                        'updated_at'                => $timestamp,
                    );
                    $result = $this->save_parent_wallet($walletData);
                } else {
                    log_message('error', 'DVA save_paystack_response: no student or parent for email=' . $customer_email . ' ref=' . $payment_reference);
                }
            }
        }

        return $result;
    }

    private function save_student_wallet($walletData) {
        // Idempotency: bail out if this reference is already recorded (handles webhook retries).
        $alreadyExists = $this->db->query(
            'SELECT COUNT(*) AS cnt FROM student_wallet WHERE payment_gateway_reference LIKE CONCAT("%", ?, "%")',
            [$walletData['payment_gateway_reference']]
        )->row()->cnt;

        if ($alreadyExists > 0) {
            log_message('error', 'Student Wallet: reference ' . $walletData['payment_gateway_reference']
                . ' already recorded for ' . $walletData['email_address']);
            return false;
        }

        $existing = $this->db->select('id, payment_gateway_reference, update_count')
            ->where('email_address', $walletData['email_address'])
            ->get('student_wallet')->row();

        if ($existing) {
            // Atomic increment — avoids TOCTOU data loss under concurrent transfers.
            // amount = amount + $incoming instead of read-PHP-add-write.
            log_message('info', 'Student Wallet: adding ' . $walletData['amount'] . ' for ' . $walletData['email_address']);
            $response = $this->db->query(
                'UPDATE student_wallet
                 SET amount                    = amount + ?,
                     payment_gateway_reference = CONCAT(payment_gateway_reference, ",", ?),
                     update_count              = update_count + 1,
                     updated_at               = ?
                 WHERE id = ?',
                [
                    $walletData['amount'],
                    $walletData['payment_gateway_reference'],
                    $walletData['updated_at'],
                    $existing->id,
                ]
            );
        } else {
            $response = $this->db->insert('student_wallet', $walletData);
        }

        return $response;
    }

    private function save_parent_wallet($walletData) {
        // Idempotency: skip if this reference is already recorded
        $exists = $this->db->query(
            'SELECT COUNT(*) AS cnt FROM parent_wallet WHERE payment_gateway_reference LIKE CONCAT("%", ?, "%")',
            [$walletData['payment_gateway_reference']]
        )->row()->cnt;

        if ($exists > 0) {
            log_message('error', 'Parent Wallet Reference ' . $walletData['payment_gateway_reference'] . ' already recorded');
            return false;
        }

        $existing = $this->db->select('id, payment_gateway_reference, amount, update_count')
            ->where('email_address', $walletData['email_address'])
            ->get('parent_wallet')->row();

        if ($existing) {
            $this->db->where('id', $existing->id);
            $walletData['amount'] = $existing->amount + $walletData['amount'];
            $walletData['payment_gateway_reference'] = $existing->payment_gateway_reference . ',' . $walletData['payment_gateway_reference'];
            $walletData['update_count'] = $existing->update_count + 1;
            return $this->db->update('parent_wallet', $walletData);
        } else {
            return $this->db->insert('parent_wallet', $walletData);
        }
    }

    private function update_student_wallet($wallet_data) {
        $this->db->where('id', $wallet_data['id']);
        $this->db->update('student_wallet', $wallet_data);
    }

    // Admin endpoint: re-process DVA payments that landed in paystack_logs but were
    // never allocated (because the email lookup failed before the DVA fallback existed).
    // Only accessible to superadmin. Safe to run multiple times — idempotency guards
    // inside save_student_wallet and build_proportional_inserts prevent double-posting.
    public function reprocess_dva_backlog()
    {
        if (!is_superadmin_loggedin()) {
            show_error('Access denied', 403);
        }

        // Find paystack_logs rows whose reference is not yet in any student_wallet
        // payment_gateway_reference, meaning they were never turned into a wallet entry.
        $logs = $this->db->query("
            SELECT pl.id, pl.amount, pl.reference, pl.paid_date,
                   pl.customer_email, pl.customer_id,
                   dva.user_id AS student_id
            FROM paystack_logs pl
            INNER JOIN dedicated_virtual_account dva ON dva.customer_id = pl.customer_id
            WHERE NOT EXISTS (
                SELECT 1 FROM student_wallet sw
                WHERE sw.payment_gateway_reference LIKE CONCAT('%', pl.reference, '%')
            )
            ORDER BY pl.paid_date ASC
        ")->result_array();

        $processed = 0;
        $skipped   = 0;
        $errors    = [];
        $flagged   = [];

        foreach ($logs as $log) {
            $student_id     = (int) $log['student_id'];
            $amount_naira   = (float) $log['amount'] / 100;
            $reference      = $log['reference'];
            $customer_email = $log['customer_email'];

            $student = $this->db->select('id')->where('id', $student_id)->get('student')->row_array();
            if (empty($student)) {
                $errors[] = "ref=$reference: DVA user_id=$student_id not found in student table";
                $skipped++;
                continue;
            }

            // --- Misattribution guard ---
            // If the log's customer_email belongs to a DIFFERENT student or portal user,
            // the entry is phantom/misattributed — skip it and flag for manual review.
            $email_owner_student = $this->db->select('id')
                ->where('email', $customer_email)
                ->where('id !=', $student_id)
                ->get('student')->row_array();
            if ($email_owner_student) {
                $flagged[] = [
                    'ref'            => $reference,
                    'amount'         => $amount_naira,
                    'dva_student_id' => $student_id,
                    'reason'         => "customer_email $customer_email belongs to student_id=" . $email_owner_student['id'],
                ];
                $skipped++;
                continue;
            }

            $email_owner_user = $this->db->select('id')
                ->where('email', $customer_email)
                ->get('users')->row_array();
            if ($email_owner_user && (int) $email_owner_user['id'] !== $student_id) {
                $flagged[] = [
                    'ref'            => $reference,
                    'amount'         => $amount_naira,
                    'dva_student_id' => $student_id,
                    'reason'         => "customer_email $customer_email belongs to users.id=" . $email_owner_user['id'],
                ];
                $skipped++;
                continue;
            }

            $timestamp = date('Y-m-d H:i:s');
            $walletData = [
                'student_id'                => $student_id,
                'email_address'             => $customer_email,
                'payment_gateway'           => 'paystack',
                'payment_gateway_reference' => $reference,
                'payment_channel'           => 'dedicated_nuban',
                'amount'                    => $amount_naira,
                'update_count'              => 0,
                'updated_at'                => $timestamp,
            ];

            $wallet_saved = $this->save_student_wallet($walletData);
            if (!$wallet_saved) {
                $skipped++;
                continue;
            }

            // Reload wallet to get the freshly accumulated amount (may include prior credits)
            $student_wallet = $this->db->select('*')
                ->where('email_address', $customer_email)
                ->get('student_wallet')->row_array();

            if (!empty($student_wallet)) {
                $this->distribute_student_wallet($student_wallet, $reference);
                $processed++;
            } else {
                $errors[] = "ref=$reference: wallet not found after save for student_id=$student_id";
                $skipped++;
            }
        }

        $result = [
            'total_found'    => count($logs),
            'processed'      => $processed,
            'skipped'        => $skipped,
            'phantom_flagged' => $flagged,
            'errors'         => $errors,
        ];

        log_message('info', 'DVA reprocess_dva_backlog: ' . json_encode($result));

        if ($this->input->is_ajax_request()) {
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }

        echo '<pre>' . json_encode($result, JSON_PRETTY_PRINT) . '</pre>';
    }

    private function savePaymentData($data)
    {
        // insert in DB
        $result = $this->db->insert('fee_payment_history', $data);

        // In webhook context get_loggedin_branch_id() is null — fall back to
        // the branch_id on the fee_allocation row so vouchers are booked correctly.
        $branch_id = get_loggedin_branch_id();
        if ($branch_id === null && !empty($data['allocation_id'])) {
            $alloc = $this->db->select('branch_id')
                ->where('id', $data['allocation_id'])
                ->get('fee_allocation')->row_array();
            $branch_id = !empty($alloc) ? (int) $alloc['branch_id'] : null;
        }

        // transaction voucher save function
        $getSeeting = $this->fees_model->get('transactions_links', array('branch_id' => $branch_id), true);
        if ($getSeeting['status']) {
            $arrayTransaction = array(
                'account_id' => $getSeeting['deposit'],
                'amount' => $data['amount'] + $data['fine'],
                'date' => $data['date'],
            );
            $this->fees_model->saveTransaction($arrayTransaction, '', $branch_id);
        }

        return $result;
    }

}