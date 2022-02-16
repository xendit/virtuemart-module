<?php

defined('_JEXEC') or die('Restricted access');

/**
 * @mainpage
 * Base class for Xendit REST API
 * This class implements basic http authentication and JSON parse to PHP array
 * for the response
 *
 * Requires libcurl and openssl
 *
 * Copyright (c) 2020 Xendit
 *
 * Released under the GNU General Public License (Version 3)
 * [https://www.gnu.org/licenses/gpl-3.0.html]
 *
 * $Date: 2020-03-03 14:08:47 +0700 (Tue, 3 Mar 2020) $
 * @author Xendit https://xendit.co (thirdpartyintegrations@xendit.co)
 *
 */

class XenditApi {
    const METHOD_POST = 'POST';
    const METHOD_GET = 'GET';

    function __construct ($method) {
        $this->server_domain = 'https://api.xendit.co';
        $this->tpi_server_domain = 'https://tpi.xendit.co';

        $this->environment = $method->shop_mode ? $method->shop_mode : 'test';
        $this->payment_type = $method->xendit_gateway_payment_type;

        if ($this->payment_type) { // identified as xendit payment
            if (($this->environment=='test' && (empty($method->xendit_gateway_public_api_key_test) || empty($method->xendit_gateway_secret_api_key_test)))
            ||
            ($this->environment!='test' && (empty($method->xendit_gateway_public_api_key) || empty($method->xendit_gateway_secret_api_key)))){
                vmError(vmText::sprintf('VMPAYMENT_XENDIT_PARAMETER_REQUIRED'));

                return FALSE;
            }
        }

        $this->secret_api_key = $this->environment=='test' ? $method->xendit_gateway_secret_api_key_test : $method->xendit_gateway_secret_api_key;
        $this->public_api_key = $this->environment=='test' ? $method->xendit_gateway_public_api_key_test : $method->xendit_gateway_public_api_key;
    }

    function getHeader() {
        $site_config = JFactory::getConfig();
        $store_name = $site_config->get('sitename');

        return array(
            'content-type: application/json',
            'x-plugin-name: VIRTUEMART',
            'x-plugin-version: 1.2.1',
            'x-plugin-store-name: ' . $store_name
        );
    }

    function getPublicKey() {
        return $this->public_api_key;
    }

    /*******************************************************************************
        Virtual Accounts
     *******************************************************************************/
    function createInvoice ($body, $header) {
        $endpoint = $this->tpi_server_domain.'/payment/xendit/invoice';
        $default_header = $this->getHeader();
        $header = array_merge($header, $default_header);

        // return $header;

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

    /*******************************************************************************
        Credit Card
     *******************************************************************************/

    function createCharge($body) {
        $endpoint = $this->tpi_server_domain.'/payment/xendit/credit-card/charges';
        $default_header = $this->getHeader();

        $json_response = $this->_sendRequest($endpoint, self::METHOD_POST, $body, $default_header);

        return $json_response;
    }
    
     function createHosted3DS($body, $header) {
        $endpoint = $this->tpi_server_domain.'/payment/xendit/credit-card/hosted-3ds';
        $default_header = $this->getHeader();
        $header = array_merge($header, $default_header);
        $options = array(
            'should_use_public_key' => true
        );

        $json_response = $this->_sendRequest($endpoint, self::METHOD_POST, $body, $header, $options);

        return $json_response;
    }

    /**
     * Get CC Setting
     * Note: the return will be array, but if value is boolean (true) json_decode will convert to "1" otherwise if value is boolean (false) json_decode will convert to ""
     */
    function getCCSettings() {
        $endpoint = $this->tpi_server_domain.'/payment/xendit/settings/credit-card';
        $default_header = $this->getHeader();
        $body = [];

        $json_response = $this->_sendRequest($endpoint, self::METHOD_GET, $body, $default_header);

        return $json_response;
    }

    function getCharge($charge_id='') {
        $endPoint = $this->tpi_server_domain.'/payment/xendit/credit-card/charges/'.$charge_id;
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
	function _sendRequest($endpoint, $method, $body = array(), $header = array(), $options = array()) {
        $ch = curl_init();
        
        $should_use_public_key = isset($options['should_use_public_key']) ? $options['should_use_public_key'] : false;
        $api_key = $should_use_public_key ? $this->public_api_key : $this->secret_api_key;

        $curl_options = array(
            CURLOPT_URL => $endpoint,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $api_key . ':'
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