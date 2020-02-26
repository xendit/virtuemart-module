<?php
/**
 * This is file for handle callback
 * @link baseURL() /plugins/vmpayment/xendit/xendit_callback.php?xendit_mode=[xendit_mode]
 * xendit_mode:
 *      - xendit_invoice_callback
 *      - xendit_ewallet_callback (not support yet)
 *      - xendit_cardless_callback (not support yet)
 *      - xendit_cc_callback (not support yet)
 */

$ch = curl_init();

$end_point = 'https://tpi.xendit.co/payment/xendit/invoice';

$data = array(
    'external_id' => 'demo_1475801962607',
    'amount' => 230000,
    'payer_email' => 'candra@xendit.co',
    'description' => 'Jalan jalan'
);

$post_string = json_encode($data);

curl_setopt($ch, CURLOPT_URL, $end_point);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_TIMEOUT, 45);
curl_setopt($ch, CURLOPT_POST, 1);

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Basic ' . base64_encode('x:'),
    'content-type: application/json',
    'x-plugin-name: VIRTUEMART'
));

$response = curl_exec($ch);

// var_dump(json_decode(json_encode(curl_getinfo($ch))));

curl_close($ch);

var_dump($response);
