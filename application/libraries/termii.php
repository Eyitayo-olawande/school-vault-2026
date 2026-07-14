<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Termii
{
    private $CI;
    private $api_key;
    private $sender_id;
    private $channel;
    private $wa_sender_id; // WhatsApp business number registered with Termii

    public function __construct()
    {
        $this->CI =& get_instance();
        $branchID = $this->CI->application_model->get_branch_id();
        $cred = $this->CI->db->where(['sms_api_id' => 9, 'branch_id' => $branchID])
                             ->get('sms_credential')->row_array();
        $this->api_key      = $cred['field_one']  ?? '';
        $this->sender_id    = $cred['field_two']  ?? '';
        $this->channel      = $cred['field_three'] ?? 'generic'; // generic or dnd
        $this->wa_sender_id = $cred['field_four'] ?? ''; // WhatsApp sender number
    }

    public function send($to, $message)
    {
        if (empty($this->api_key)) {
            return false;
        }
        return $this->_dispatch($to, $message, $this->channel);
    }

    // Send via WhatsApp channel. Requires a WhatsApp Business number in field_four of sms_credential.
    public function sendWhatsApp($to, $message)
    {
        if (empty($this->api_key) || empty($this->wa_sender_id)) {
            return false;
        }
        return $this->_dispatch($to, $message, 'whatsapp', $this->wa_sender_id);
    }

    private function _dispatch($to, $message, $channel, $sender = null)
    {
        $to = preg_replace('/^0/', '234', ltrim($to, '+'));

        $payload = json_encode([
            'api_key' => $this->api_key,
            'to'      => $to,
            'from'    => $sender ?? $this->sender_id,
            'sms'     => $message,
            'type'    => 'plain',
            'channel' => $channel,
        ]);

        $ch = curl_init('https://api.ng.termii.com/api/sms/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
