<?php

if (!defined('ABSPATH')) {
    exit;
}

class XenditApi {

    function __construct ($options) {
        $this->server_domain = 'https://api.xendit.co';
        $this->tpi_server_domain = 'https://tpi.xendit.co';

        $this->secret_api_key = $options['secret_api_key'];
        $this->public_api_key = $options['public_api_key'];
    }

    /*******************************************************************************
        Virtual Accounts
     *******************************************************************************/
    function createInvoice ($body, $header) {
        $curl = curl_init();

        $end_point = $this->tpi_server_domain.'/payment/xendit/invoice';

        $payload = json_encode($body);
        $default_header = array(
            'Authorization' => 'Basic ' . base64_encode($this->secret_api_key . ':'),
            'content-type' => 'application/json',
            'x-plugin-name' => 'WOOCOMMERCE'
        );

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
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->secret_api_key . ':'),
                'content-type' => 'application/json',
                'x-plugin-name' => 'WOOCOMMERCE'
            ),
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
          'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($this->secret_api_key . ':'),
            'content-type' => 'application/json'
          ),
        );
        $response = wp_remote_get( $end_point, $args );

        if (is_wp_error($response) || empty($response['body'])) {
            return array();
        }

        $jsonResponse = json_decode( $response['body'], true );
        return $jsonResponse;
    }

    function trackOrderCancellation($body) {
        $curl = curl_init();

        $end_point = $this->tpi_server_domain.'/payment/xendit/invoice/bulk-cancel';

        $payload = array(
            'invoice_data' => json_encode($body)
        );
        $default_header = array(
            'Authorization' => 'Basic ' . base64_encode($this->secret_api_key . ':'),
            'content-type' => 'application/json',
            'x-plugin-name' => 'WOOCOMMERCE'
        );

        $args = array(
            'headers' => $default_header,
            'body' => json_encode($payload)
        );
        $response = wp_remote_post( $end_point, $args );

        if (is_wp_error($response) || empty($response['body'])) {
            return array();
        }

        $jsonResponse = json_decode( $response['body'], true );
        return $jsonResponse;
    }


    /*******************************************************************************
        e-Wallet
     *******************************************************************************/
    function createEwalletPayment($body, $header) {
        $curl = curl_init();

        $end_point = $this->tpi_server_domain.'/payment/xendit/ewallets';

        $payload = json_encode($body);
        $default_header = array(
            'Authorization' => 'Basic ' . base64_encode($this->secret_api_key . ':'),
            'content-type' => 'application/json',
            'x-plugin-name' => 'WOOCOMMERCE'
        );

        $args = array(
            'headers' => array_merge($default_header, $header),
            'body' => $payload,
            'timeout' => 60
        );
        $response = wp_remote_post( $end_point, $args );
        $jsonResponse = json_decode( $response['body'], true );

        if (is_wp_error($response)) { //CURL error
            $jsonResponse['is_paid'] = 0;

            $status = $this->getEwalletStatus($body['ewallet_type'], $body['external_id']);
            if ('COMPLETED' == $status) {
                $jsonResponse['is_paid'] = 1;
            }
        }

        return $jsonResponse;
    }

    function getEwalletStatus($ewallet_type, $external_id) {
        $curl = curl_init();

        $end_point = $this->tpi_server_domain.'/payment/xendit/ewallets?ewallet_type='.$ewallet_type.'&external_id='.$external_id;

        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->secret_api_key . ':'),
                'content-type' => 'application/json'
            ),
        );

        $response = wp_remote_get( $end_point, $args );
        $jsonResponse = json_decode( $response['body'], true );

        if ($ewallet_type == 'DANA') {
            $jsonResponse['status'] = $jsonResponse['payment_status'];
        }

        $status_list = array("COMPLETED", "PAID", "SUCCESS_COMPLETED"); //OVO, DANA, LINKAJA
        if ( in_array($jsonResponse['status'], $status_list) ) {
            return "COMPLETED";
        }
        
        return $jsonResponse['status'];
    }

    
    /*******************************************************************************
        Credit Cards
     *******************************************************************************/
    /**
     * Send the request to Xendit's API
     *
     * @param array $request
     * @param string $api
     * @return array|WP_Error
     */
    function request($request, $api = 'charges', $method = 'POST', $options = array()) {
        WC_Xendit_PG_Logger::log("$method /{$api} request: " . print_r($request, true) . PHP_EOL);

        $end_point = $this->tpi_server_domain.'/payment/xendit/credit-card/' . $api;
        $headers = self::get_headers($options);

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'body' => apply_filters('woocommerce_xendit_request_body', $request, $api),
            'timeout' => 70,
            'user-agent' => 'WooCommerce ' . WC()->version
        );
        $response = wp_remote_post( $end_point, $args );

        if (is_wp_error($response) || empty($response['body'])) {
            WC_Xendit_PG_Logger::log('API Error Response: ' . print_r($response, true), WC_LogDNA_Level::ERROR, true);
            return new WP_Error('xendit_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-xendit'));
        }

        $parsed_response = json_decode($response['body']);

        // Handle response
        if (!empty($parsed_response->error)) {
            if (!empty($parsed_response->error->code)) {
                $code = $parsed_response->error->code;
            } else {
                $code = 'xendit_error';
            }
            WC_Xendit_PG_Logger::log('API Error Parsed Response: ' . $parsed_response, WC_LogDNA_Level::ERROR, true);
            return new WP_Error($code, $parsed_response->error->message);
        } else {
            return $parsed_response;
        }
    }
    
    /**
     * Get CC Setting
     * Note: the return will be array, but if value is boolean (true) json_decode will convert to "1" otherwise if value is boolean (false) json_decode will convert to ""
     * @return array|WP_Error
     */
    function getCCSettings() {
        $curl = curl_init();

        $headers = array();
        $headers[] = 'Content-Type: application/json';

        $end_point = $this->tpi_server_domain.'/payment/xendit/settings/credit-card';

        $args = array(
          'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($this->secret_api_key . ':'),
            'content-type' => 'application/json'
          ),
        );
        $response = wp_remote_get( $end_point, $args );

        if (is_wp_error($response) || empty($response['body'])) {
            return array();
        }

        $jsonResponse = json_decode( $response['body'], true );
        return $jsonResponse;
    }

    /**
     * Generates header for API request
     *
     * @since 1.2.3
     * @version 1.2.3
     */
    function get_headers($options) {
        WC_Xendit_PG_Logger::log("INFO: Building Request Header..");

        $should_use_public_key = isset($options['should_use_public_key']) ? $options['should_use_public_key'] : false;
        $auth = $should_use_public_key ? self::get_public_key() : self::get_secret_key();

        return apply_filters(
            'woocommerce_xendit_request_headers',
            array(
                'Authorization' => 'Basic ' . base64_encode($auth . ':'),
                'x-plugin-name' => 'WOOCOMMERCE',
                'x-plugin-store-name' => isset($options['store_name']) ? $options['store_name'] : get_option('blogname'),
                'x-api-version' => '2020-02-14'
            )
        );
    }

    /**
     * Get secret key.
     * @return string
     */
    function get_secret_key() {
        return $this->secret_api_key;
    }

    /**
     * Get public key.
     * @return string
     */
    function get_public_key() {
        return $this->public_api_key;
    }

    /**
     * Get credit card charge callback data
     * 
     * @param string $charge_id
     * @return array
     */
    function getCharge($charge_id) {
        $curl = curl_init();

        $headers = array();
        $headers[] = 'Content-Type: application/json';

        $end_point = $this->tpi_server_domain.'/payment/xendit/credit-card/charges/'.$charge_id;

        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->secret_api_key . ':'),
                'content-type' => 'application/json',
                'x-plugin-name' => 'WOOCOMMERCE'
            ),
        );
        $response = wp_remote_get( $end_point, $args );
        $jsonResponse = json_decode( $response['body'], true );
        return $jsonResponse;
    }

    /*******************************************************************************
        Cardless
     *******************************************************************************/
    /**
     * Initiate Kredivo payment through TPI service
     * @param array $body
     * @param array $header
     * @return array
     */
    function createCardlessPayment($body, $header) {
        $curl = curl_init();

        $end_point = $this->tpi_server_domain . '/payment/xendit/cardless-credit';

        $payload = json_encode($body);
        $default_header = array(
            'Authorization' => 'Basic ' . base64_encode($this->secret_api_key . ':'),
            'content-type' => 'application/json',
            'x-plugin-name' => 'WOOCOMMERCE'
        );

        $args = array(
            'headers' => array_merge($default_header, $header),
            'body' => $payload,
            'timeout' => 60
        );
        $response = wp_remote_post($end_point, $args);
        $jsonResponse = json_decode($response['body'], true);

        return $jsonResponse;
    }
}