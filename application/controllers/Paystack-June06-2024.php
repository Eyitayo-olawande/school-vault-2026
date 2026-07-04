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
                // Handle the successful payment here
                $this->update($payment_reference, $arrayFees);

                $this->save_payment_history($payment_reference);
            
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
            // Add other event types here as needed
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
        $this->db->insert('fee_payment_history', $arrayFeesData);
        
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

}