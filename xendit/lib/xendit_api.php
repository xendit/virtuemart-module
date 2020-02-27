<?php

defined('_JEXEC') or die('Restricted access');

class XenditApi {

    function __construct ($method) {
        $this->server_domain = 'https://api.xendit.co';
        $this->tpi_server_domain = 'https://tpi.xendit.co';
        //print_r($method);echo "<br><br>";

        $this->environment = $method->shop_mode ? $method->shop_mode : 'test';

        if (($this->environment=='test' && (empty($method->xendit_gateway_public_api_key_dev) || empty($method->xendit_gateway_secret_api_key_dev)))
            ||
            ($this->environment!='test' && (empty($method->xendit_gateway_public_api_key) || empty($method->xendit_gateway_secret_api_key)))){
            $text = vmText::sprintf('VMPAYMENT_XENDIT_PARAMETER_REQUIRED');
            vmError($text, $text);
            
			return FALSE;
        }

        $this->secret_api_key = $this->environment=='test' ? $method->xendit_gateway_public_api_key_dev : $method->xendit_gateway_public_api_key;
        $this->public_api_key = $this->environment!='test' ? $method->xendit_gateway_public_api_key : $method->xendit_gateway_public_api;

    }

    private function getHeader() {
        return array(
            'Authorization' => 'Basic ' . base64_encode($this->secret_api_key . ':'),
            'content-type' => 'application/json',
            'x-plugin-name' => 'VIRTUEMART'
        );
    }

    /*******************************************************************************
        Virtual Accounts
     *******************************************************************************/
    function createInvoice ($body, $header) {
        $curl = curl_init();

        $end_point = $this->tpi_server_domain.'/payment/xendit/invoice';

        $payload = json_encode($body);
        $default_header = $this->getHeader();

        $args = array(
            'headers' => array_merge($default_header, $header),
            'body' => $payload,
        );
        $response = wp_remote_post( $end_point, $args );
        $jsonResponse = json_decode( $response['body'], true );
        return $jsonResponse;
    }

    function getInvoice ($invoice_id) {
        $curl = curl_init();

        $headers = array();
        $headers[] = 'Content-Type: application/json';

        $end_point = $this->tpi_server_domain.'/payment/xendit/invoice/'.$invoice_id;

        $args = array(
            'headers' => $this->getHeader()
        );
        $response = wp_remote_get( $end_point, $args );
        $jsonResponse = json_decode( $response['body'], true );
        return $jsonResponse;
    }

    function getInvoiceSettings() {
        $curl = curl_init();

        $headers = array();
        $headers[] = 'Content-Type: application/json';

        $end_point = $this->tpi_server_domain.'/payment/xendit/settings/invoice';

        $args = array(
            'headers' => $this->getHeader()
        );
        $response = wp_remote_get( $end_point, $args );

        if (empty($response['body'])) {
            return array();
        }

        $jsonResponse = json_decode( $response['body'], true );
        return $jsonResponse;
    }
}