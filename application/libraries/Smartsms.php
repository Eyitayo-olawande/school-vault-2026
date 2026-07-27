<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Smartsms
{
    private $CI;
    private $token;
    private $sender_name;

    public function __construct()
    {
        $this->CI =& get_instance();
        $branchID = $this->CI->application_model->get_branch_id();
        $cred = $this->CI->db->where(['sms_api_id' => 10, 'branch_id' => $branchID])
                             ->get('sms_credential')->row_array();
        $this->token       = $cred['field_one'] ?? '';
        $this->sender_name = $cred['field_two'] ?? '';
    }

    public function send($to, $message)
    {
        if (empty($this->token)) {
            return false;
        }
        $to = preg_replace('/^0/', '234', ltrim($to, '+'));

        $params = http_build_query([
            'token'      => $this->token,
            'sender'     => $this->sender_name,
            'to'         => $to,
            'message'    => $message,
            'type'       => 0,
            'routing'    => 3,
        ]);

        $ch = curl_init('https://smartsmssolutions.com/api/json.php?' . $params);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
