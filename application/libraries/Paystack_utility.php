<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');



class Paystack_utility {

    private $ci;
    public $api_config;

    function __construct() {
        $this->ci = & get_instance();
        $this->initialize();
    }

    public function initialize($branchID = '')
    {
        if (empty($branchID)) {
            $branchID = get_loggedin_branch_id();  
        }
        $this->api_config = $this->ci->db->select('paystack_secret_key,paystack_status')->where('branch_id', $branchID)->get('payment_config')->row_array();
        if (empty($this->api_config)) {
            $this->api_config = ['paystack_secret_key' => '', 'paystack_status' => ''];
        }
        log_message('info', json_encode($this->api_config));
    }

    public function payment($data) {
        // $sandbox = $this->api_config['paypal_sandbox'] == 1 ? TRUE : FALSE;
        // $gateway = Omnipay::create('PayPal_Express');
        // $gateway->setUsername($this->api_config['paypal_username']);
        // $gateway->setPassword($this->api_config['paypal_password']);
        // $gateway->setSignature($this->api_config['paypal_signature']);
        // $gateway->setTestMode($sandbox);
        // $response = $gateway->purchase($data)->send();
        // return $response;
    }

    public function success($data) {
        // $sandbox = $this->api_config['paypal_sandbox'] == 1 ? TRUE : FALSE;
        // $gateway = Omnipay::create('PayPal_Express');
        // $gateway->setUsername($this->api_config['paypal_username']);
        // $gateway->setPassword($this->api_config['paypal_password']);
        // $gateway->setSignature($this->api_config['paypal_signature']);
        // $gateway->setTestMode($sandbox);
        // $response = $gateway->completePurchase($data)->send();
        // return $response;
    }

     // paystack dva gateway script start
     public function paystack_get_dva($params)
     {
         // $config = $this->get_payment_config();
         $config = $this->api_config;
         log_message('info', 'paystack_get_dva params : '.$params['student_email']);

         if (!empty($params)) {
             if ($config['paystack_secret_key'] == "") {
                 set_alert('error', 'Paystack config not available');
                 redirect($_SERVER['HTTP_REFERER']);
             } else {
                 $result = array();
                 $ref = app_generate_hash();
                 $callback_url = base_url() . 'feespayment/verify_paystack_payment/' . $ref;
                 $postdata = array(
                    'email' => $params['student_email'], 
                    'first_name' => $params['firstname'], 
                    'middle_name' => $params['middlename'], 
                    'last_name' => $params['lastname'], 
                    'phone' => $params['phone'],
                    'preferred_bank' => $params['preferredbank'], 
                    'country' => $params['country'],
                );
                 $url = "https://api.paystack.co/dedicated_account/assign";
 
 
                 $ch = curl_init();
                 curl_setopt($ch, CURLOPT_URL, $url);
                 curl_setopt($ch, CURLOPT_POST, 1);
                 curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
                 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                 $headers = [
                     'Authorization: Bearer ' . $config['paystack_secret_key'],
                     'Content-Type: application/json',
                 ];
                 curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                 $request = curl_exec($ch);
                 curl_close($ch);
                 //
                 if ($request) {
                     $result = json_decode($request, true);
                 }
 
                 // $redir = $result['data']['authorization_url'];
                 // header("Location: " . $redir);
             }
         }
     }
}
?>