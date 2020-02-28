<?php

defined('_JEXEC') or die('Restricted access');

class XenditApi {
    const METHOD_POST = 'POST';
    const METHOD_GET = 'GET';

    function __construct ($method) {
        $this->server_domain = 'https://api.xendit.co';
        $this->tpi_server_domain = 'https://tpi.xendit.co';

        $this->environment = $method->shop_mode ? $method->shop_mode : 'test';

        if (($this->environment=='test' && (empty($method->xendit_gateway_public_api_key_test) || empty($method->xendit_gateway_secret_api_key_test)))
            ||
            ($this->environment!='test' && (empty($method->xendit_gateway_public_api_key) || empty($method->xendit_gateway_secret_api_key)))){
            $text = vmText::sprintf('VMPAYMENT_XENDIT_PARAMETER_REQUIRED');
            vmError($text, $text);

			return FALSE;
        }

        $this->secret_api_key = $this->environment=='test' ? $method->xendit_gateway_secret_api_key_test : $method->xendit_gateway_secret_api_key;
        $this->public_api_key = $this->environment!='test' ? $method->xendit_gateway_public_api_key_test : $method->xendit_gateway_public_api_key;
        $this->payment_type = $method->xendit_gateway_payment_type;
    }

    function getHeader() {
        return array(
            'content-type: application/json',
            'x-plugin-name: VIRTUEMART'
        );
    }

    /*******************************************************************************
        Virtual Accounts
     *******************************************************************************/
    function createInvoice ($body, $header) {
        $curl = curl_init();

        $endpoint = $this->tpi_server_domain.'/payment/xendit/invoice';
        $default_header = $this->getHeader();
        $header = array_merge($header, $default_header);

        $json_response = $this->_sendRequest($endpoint, self::METHOD_POST, $body, $header);

        return $json_response;
    }

    function getInvoice($invoice_id='') {
        $endPoint = $this->tpi_server_domain.'/payment/xendit/invoice/'.$invoice_id;
        $default_header = $this->getHeader();
        $body = [];

        $json_response = $this->_sendRequest($endPoint, self::METHOD_GET, $body, $default_header);

        return $json_response;
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

    function getCharge($charge_id='') {
        $endPoint = $this->tpi_server_domain.'/payment/xendit/credit-card/'.$charge_id;
        $default_header = $this->getHeader();
        $body = [];

        $json_response = $this->_sendRequest($endPoint, self::METHOD_GET, $body, $default_header);

        return $json_response;
    }

    /**
	 * _sendRequest
	 * Posts the request to AuthorizeNet & returns response using curl
	 *
	 * @author Valerie Isaksen
	 * @param string $url
	 * @param string $content
	 *
	 */
	function _sendRequest($endpoint, $method, $body = array(), $header = array()) {
        $ch = curl_init();
    
        $curl_options = array(
            CURLOPT_URL => $endpoint,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->secret_api_key . ':'
        );

        if ($method === self::METHOD_POST) {
            $curl_options[CURLOPT_POST] = true;
            $curl_options[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        curl_setopt_array($ch, $curl_options);
        
        
        $response = curl_exec($ch);
        
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Xendit cURL Error, error code: ' . curl_error($ch), curl_errno($ch));
        }

        $json_response = json_decode($response, true);

        return $json_response;
	}
}

?>