<?php

defined('_JEXEC') or die('Restricted access');
/**
 * @mainpage
 * Base class for XENDIT Api
 * This class implements basic http authentication and a JSON-parser
 * for parsing response messages
 *
 * Requires libcurl and openssl
 *
 * Copyright (c) 2020 XENDIT
 *
 * Released under the GNU General Public License (Version 2)
 * [http://www.gnu.org/licenses/gpl-2.0.html]
 *
 * $Date: 2020-02-26 17:15:47 +0700 $
 * @version XenditLib 1.0.0  $Id: xenditLib.php 5773 2012-11-23 16:15:47Z dehn $
 * @author XENDIT AG https://www.xendit.co (thirdpartyintegrations@xendit.co)
 *
 */

if(!defined('XENDITLIB_VERSION')) {
	define('XENDITLIB_VERSION','1.0.0');
}

class XenditApi {

    function __construct ($options) {
        $this->server_domain = 'https://api.xendit.co';
        $this->tpi_server_domain = 'https://tpi.xendit.co';

        $this->secret_api_key = $options['secret_api_key'];
        $this->public_api_key = $options['public_api_key'];
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

?>