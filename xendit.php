<?php

defined('_JEXEC') or die('Direct Access is not allowed.');

/**
 *
 * @package    VirtueMart
 * @subpackage Plugins  - Elements
 * @package VirtueMart
 * @subpackage
 * @author Xendit
 * @link https://virtuemart.net
 * @copyright Copyright (c) 2004 - 2018 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id$
 *
 * http://www1.xendit.com/telechargements/ManuelIntegrationXendit_V5.08_FR.pdf
 * Pour accéder au Back-office commerçant: https://preprod-admin.xendit.com
 */
if (!class_exists('vmPSPlugin')) {
	require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

/**
 * We need this class to:
 * 1. Validate order (check if API key is set, currency is supported, amount is valid)
 * 2. Order handler (change order status, create invoice, redirect to invoice URL)
 * 3. 
 */

class plgVmpaymentXendit extends vmPSPlugin {

	// instance of class

	function __construct(& $subject, $config) {

		parent::__construct($subject, $config);

		$this->_loggable = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
	}
	
	/**
	 * We must reimplement this triggers for joomla 1.7
	 */

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {

		if ($res = $this->selectedThisByJPluginId($jplugin_id)) {

			$virtuemart_paymentmethod_id = vRequest::getInt('virtuemart_paymentmethod_id');
			$method = $this->getPluginMethod($virtuemart_paymentmethod_id);
			vmdebug('plgVmOnStoreInstallPaymentPluginTable', $method, $virtuemart_paymentmethod_id);

			if (!extension_loaded('curl')) {
				vmError(vmText::sprintf('VMPAYMENT_' . $this->_name . '_CONF_MANDATORY_PHP_EXTENSION', 'curl'));
			}
			if (!extension_loaded('openssl')) {
				vmError(vmText::sprintf('VMPAYMENT_' . $this->_name . '_CONF_MANDATORY_PHP_EXTENSION', 'openssl'));
			}
		}

		return $this->onStoreInstallPluginTable($jplugin_id);
	}
	
	/**
	 * Used for storing values of payment plugins configuration in database table. 
	 */
	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
        
		return $this->setOnTablePluginParams($name, $id, $table);
	}

    /**
     * Create SQL table for Xendit transaction
     */
	protected function getVmPluginCreateTableSQL() {

		return $this->createTableSQL('Payment Xendit Table');
	}

    /**
     * Define SQL field for Xendit transaction table
     */
	function getTableSQLFields() {

        // TODO: need to research which fields are important.
        // From Xendit, I think we need to store invoice ID, charge ID (later), xendit_status, xendit_url?
		$SQLfields = array(
			'id'                          => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => ' char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_fee'                 => 'decimal(10,2)',
			'xendit_status'               => 'varchar(50)',
			'xendit_invoice_id'           => 'varchar(255)',
			'xendit_invoice_url'          => 'varchar(255)',
			'xendit_charge_id'            => 'varchar(255)'
		);
		return $SQLfields;
	}
	
	/**
	 * This function is called whenever you try to update the configuration of the payment plugin.
	 */
	function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
    }
    
    /**
	 * This function is first called when you finally setup the configuration of payment plugin and redirect to the cart view on store.
     * In case you have set your payment plugin as the default payment method by VirtueMart’s Configuration, this function is used.
	 * 
     * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found, virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {

		return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }
    
    /**
     * Check if the payment conditions are fulfilled for the payment method.
     * Can be used to show/hide the payment plugin on some specific conditions.
     * 
     * @param array  $cart        cart details
     * @param object $method      method data
     * @param object $cart_prices cart prices object
     */
    function checkConditions($cart, $method, $cart_prices) {
        //TODO: check currency, min & max amount
        if ($cart_prices['billTotal'] < 10000) {
            return false;
        }
    }
	
    /**
     * Trigerred when end user click the "Confirm Purchase" button.
     * 
     * Can we redirect to invoice URL here?
     */
	function plgVmConfirmedOrder($cart, $order) {

		if (!($this->_currentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}
		$xenditInterface = $this->_loadXenditInterface($this);
		$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');
		$xenditInterface->confirmedOrder($cart, $order);

        return;
	}


    /**
     * Check if we support the payment currency used by this order.
     */
	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency($method);
		$paymentCurrencyId = $method->payment_currency;
		return TRUE;
	}


	function plgVmOnPaymentResponseReceived(&$html) {

		if (!class_exists('VirtueMartCart')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists('shopFunctionsF')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}


		vmLanguage::loadJLang('com_virtuemart_orders', TRUE);

		$virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return NULL;
		}
		$xendit_data = vRequest::getGet();

		$this->debugLog('"<pre>plgVmOnPaymentResponseReceived :' . var_export($xendit_data, true) . "</pre>", 'debug');
		$xenditInterface = $this->_loadXenditInterface($this);
		$html = $xenditInterface->paymentResponseReceived($xendit_data);
		vRequest::setVar('display_title', false);
		vRequest::setVar('html', $html);
		return true;
	}

	/**
	 * plgVmOnPaymentNotification() - This event is fired by Offline Payment. It can be used to validate the payment data as entered by the user.
	 * Return:
     *
	 * @author Valerie Isaksen
	 */

	function plgVmOnPaymentNotification() {

		if (!class_exists('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}
		$xendit_data = $_POST;

		$virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);
		$this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id);
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return;
		}
		$xendit_data_log=$xendit_data;
		unset($xendit_data_log['K']);
		$this->debugLog(var_export($xendit_data_log, true), 'plgVmOnPaymentNotification', 'debug', false);
		$xenditInterface = $this->_loadXenditInterface($this);
		if (!$xenditInterface->isXenditResponseValid( $xendit_data, true, false)) {
			return FALSE;
		}
		$order_number = $xenditInterface->getOrderNumber($xendit_data['R']);
		if (empty($order_number)) {
			$this->debugLog($order_number, 'getOrderNumber not correct' . $xendit_data['R'], 'debug', false);
			return FALSE;
		}
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
			return FALSE;
		}

		if (!($payments = $this->getPluginDatasByOrderId($virtuemart_order_id))) {
			$this->debugLog('no payments found', 'getDatasByOrderId', 'debug', false);
			return FALSE;
		}

		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($virtuemart_order_id);
		$extra_comment = "";
		if (count($payments) == 1) {
			// NOTIFY not received
			$order_history = $xenditInterface->updateOrderStatus($xendit_data, $order, $payments);
			if (isset($order_history['extra_comment'])) {
				$extra_comment = $order_history['extra_comment'];
			}
		}

		if (!empty($payments[0]->paybox_custom)) {
			$this->emptyCart($payments[0]->paybox_custom, $order['details']['BT']->order_number);
			$this->setEmptyCartDone($payments[0]);
		}
		return TRUE;
	}
	/**
	 * @param $xendit_data
	 * @return bool
	 */

	function paymentNotification ($xendit_data) {


		if (!$this->isXenditResponseValid( $xendit_data, true, false)) {
			return FALSE;
		}
		$order_number = $this->getOrderNumber($xendit_data['R']);
		if (empty($order_number)) {
			$this->plugin->debugLog($order_number, 'getOrderNumber not correct' . $xendit_data['R'], 'debug', false);
			return FALSE;
		}
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
			return FALSE;
		}

		if (!($payments = $this->plugin->getPluginDatasByOrderId($virtuemart_order_id))) {
			$this->plugin->debugLog('no payments found', 'getDatasByOrderId', 'debug', false);
			return FALSE;
		}

		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($virtuemart_order_id);
		$extra_comment = "";
		if (count($payments) == 1) {
			// NOTIFY not received
			$order_history = $this->updateOrderStatus($xendit_data, $order, $payments);
			if (isset($order_history['extra_comment'])) {
				$extra_comment = $order_history['extra_comment'];
			}
		}


		return $payments[0]->paybox_custom;
	}


	/**
	 * @param $firstPayment
	 */
	function setEmptyCartDone($firstPayment) {
		$firstPayment = (array)$firstPayment;
		$firstPayment['xendit_custom'] = NULL;
		$this->storePSPluginInternalData($firstPayment, $this->_tablepkey, true);
	}

	function storePSPluginInternalData($values, $primaryKey = 0, $preload = FALSE) {
		parent::storePSPluginInternalData($values, $primaryKey, $preload);
	}

	/**
	 * Get Method Datas for a given Payment ID
	 *
	 * @param int $virtuemart_order_id The order ID
	 * @return  $methodData
	 */
	function getPluginDatasByOrderId($virtuemart_order_id) {

		return $this->getDatasByOrderId($virtuemart_order_id);
	}

	/**
	 * Display stored payment data for an order
	 *
	 * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
	 */
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id) {

		if (!$this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
		}
		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}

		$xenditInterface = $this->_loadXenditInterface($this);
		$html = $xenditInterface->showOrderBEPayment($virtuemart_order_id);


		return $html;
	}

	function getHtmlHeaderBE() {

		return parent:: getHtmlHeaderBE();
	}

	/**
	 * @param plugin $method
	 * @return mixed|string
	 */
	function renderPluginName($method) {

		$logos = $method->payment_logos;
		$display_logos = '';
		if (!empty($logos)) {
			$display_logos = $this->displayLogos($logos) . ' ';
		}
		$payment_name = $method->payment_name;
		if (!class_exists('VirtueMartCart')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		$this->_currentMethod = $method;
		$extraInfo = $this->getExtraPluginNameInfo($method);

		$html = $this->renderByLayout('render_pluginname', array(
			'shop_mode' => $method->shop_mode,
			'virtuemart_paymentmethod_id' => $method->virtuemart_paymentmethod_id,
			'logo' => $display_logos,
			'payment_name' => $payment_name,
			'payment_description' => $method->payment_desc,
			'extraInfo' => $extraInfo,
		));
		$html = $this->rmspace($html);
		return $html;
	}

	private function getExtraPluginNameInfo($activeMethod) {

		$this->_method = $activeMethod;

		$xenditInterface = $this->_loadXenditInterface();
		$extraInfo = $xenditInterface->getExtraPluginNameInfo();

		return $extraInfo;

	}

	private function rmspace($buffer) {

		return preg_replace('~>\s*\n\s*<~', '><', $buffer);
	}

	function getCosts(VirtueMartCart $cart, $method, $cart_prices) {

		if (preg_match('/%$/', $method->cost_percent_total)) {
			$cost_percent_total = substr($method->cost_percent_total, 0, -1);
		} else {
			$cost_percent_total = $method->cost_percent_total;
		}
		return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
	}

	/**
	 * @param $virtuemart_paymentmethod_id
	 * @return bool
	 */
	function createRootFile($virtuemart_paymentmethod_id) {
		$created = false;
		$filename = $this->getXenditRootFileName($virtuemart_paymentmethod_id);
		if (!JFile::exists($filename)) {
			$content = '
				<?php
				/**
				* File used by the Xendit VirtueMart Payment plugin
				**/
				$get=filter_var_array($_GET, FILTER_SANITIZE_STRING);
				$_GET["option"]="com_virtuemart";
				$_GET["element"]="xendit";
				$_GET["pm"]=' . $virtuemart_paymentmethod_id . ';
				$_REQUEST["option"]="com_virtuemart";
				$_REQUEST["element"]="xendit";
				$_REQUEST["pm"]=' . $virtuemart_paymentmethod_id . ';
				if ($get["pbx"]=="ok") {
					$_GET["view"]="pluginresponse";
					$_GET["task"]="pluginresponsereceived";
					$_REQUEST["view"]="pluginresponse";
					$_REQUEST["task"]="pluginresponsereceived";
				} elseif ($get["pbx"]=="no") {
					$_GET["view"]="pluginresponse";
					$_GET["task"]="pluginnotification";
					$_GET["format"]="raw";
					$_GET["tmpl"]="component";
					$_REQUEST["view"]="pluginresponse";
					$_REQUEST["task"]="pluginnotification";
					$_REQUEST["format"]="raw";
					$_REQUEST["tmpl"]="component";
					} elseif ($get["pbx"]=="ko") {
					$_GET["view"]="pluginresponse";
					$_REQUEST["view"]="pluginresponse";
					$_REQUEST["view"]="pluginUserPaymentCancel";
				}
				include("index.php");
				';
			if (!JFile::write($filename, $content)) {
				$msg = 'Could not write in file  ' . $filename . ' to store xendit information. Check your file ' . $filename . ' permissions.';
				vmError($msg);
			}
			$created = true;
		}
		return $created;
	}

	function getXenditRootFileName($virtuemart_paymentmethod_id) {
		$filename = JPATH_SITE . '/' . $this->getXenditFileName($virtuemart_paymentmethod_id);
		return $filename;
	}

	function getXenditFileName($virtuemart_paymentmethod_id) {
		return 'vmpayment' . '_' . $virtuemart_paymentmethod_id . '.php';
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg) {

		return $this->OnSelectCheck($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	/*
* plgVmonSelectedCalculatePricePayment
* Calculate the price (value, tax_id) of the selected method
* It is called by the calculator
* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
* @author Valerie Isaksen
* @cart: VirtueMartCart the current cart
* @cart_prices: array the new cart prices
* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
*
*
*/

	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	public function plgVmOnCheckoutCheckDataPayment (VirtueMartCart $cart) {
	return NULL;
	}
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param string $order_number
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment($order_number, $method_id) {

		return $this->onShowOrderPrint($order_number, $method_id);
	}

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.

	public function plgVmOnUpdateOrderPayment(  $_formData) {
	return null;
	}
	 */
	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.

	public function plgVmOnUpdateOrderLine(  $_formData) {
	return null;
	}
	 */
	/**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise

	public function plgVmOnEditOrderLineBE(  $_orderId, $_lineId) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise

	public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	return null;
	}
	 */

	/**
	 * @param $response
	 * @param $order
	 * @return null|string
	 */
	function getResponseHTML($order, $xendit_data, $success, $extra_comment) {

		$payment_name = $this->renderPluginName($this->_currentMethod);
		vmLanguage::loadJLang('com_virtuemart_orders', TRUE);
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $order['details']['BT']->order_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$currency_numeric_code = $db->loadResult();
		$html = $this->renderByLayout('response', array(
			"success" => $success,
			"payment_name" => $payment_name,
			"transactionId" => $xendit_data['S'],
			"amount" => $xendit_data['M'] * 0.01,
			"extra_comment" => $extra_comment,
			"currency" => $currency_numeric_code,
			"order_number" => $order['details']['BT']->order_number,
			"order_pass" => $order['details']['BT']->order_pass,
		));
		return $html;


	}

	/*********************/
	/* Private functions */
	/*********************/
	private function _loadXenditInterface() {
		if (!class_exists('XenditHelperXendit')) {
			require(VMPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . $this->_name . DS . $this->_name . DS . 'helpers' . DS . 'xendit.php');
		}
		if ($this->_currentMethod->integration == 'recurring') {
			if (!class_exists('XenditHelperXenditRecurring')) {
				require(VMPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . $this->_name . DS . $this->_name . DS . 'helpers' . DS . 'recurring.php');
			}
			$xenditInterface = new XenditHelperXenditRecurring($this->_currentMethod, $this, $this->_name);
		} elseif ($this->_currentMethod->integration == 'subscribe') {
			if (!class_exists('XenditHelperXenditSubscribe')) {
				require(VMPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . $this->_name . DS . $this->_name . DS . 'helpers' . DS . 'subscribe.php');
			}
			$xenditInterface = new XenditHelperXenditSubscribe($this->_currentMethod, $this, $this->_name);
		} else {
			$xenditInterface = new XenditHelperXendit($this->_currentMethod, $this, $this->_name);
		}
		return $xenditInterface;
	}


	function getEmailCurrency(&$method) {

		if (!isset($method->email_currency)  or $method->email_currency == 'vendor') {
			$vendor_model = VmModel::getModel('vendor');
			$vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
			return $vendor->vendor_currency;
		} else {
			return $method->payment_currency; // either the vendor currency, either same currency as payment
		}
	}

	private function getKeyFileName() {

		return 'pubkey.pem';
	}

	function getTablename() {

		return $this->_tablename;
	}

	/**
	 * @param string $message
	 * @param string $title
	 * @param string $type
	 * @param bool $echo
	 * @param bool $doVmDebug
	 */
	public function debugLog($message, $title = '', $type = 'message', $echo = false, $doVmDebug = false) {

		if ($this->_currentMethod->debug) {
			$this->debug($message, $title, true);
		}

		if ($echo) {
			echo $message . '<br/>';
		}


		parent::debugLog($message, $title, $type, $doVmDebug);
	}

	public function debug($subject, $title = '', $echo = true) {

		$debug = '<div style="display:block; margin-bottom:5px; border:1px solid red; padding:5px; text-align:left; font-size:10px;white-space:nowrap; overflow:scroll;">';
		$debug .= ($title) ? '<br /><strong>' . $title . ':</strong><br />' : '';

		if (is_array($subject)) {
			$debug .= str_replace("=>", "&#8658;", str_replace("Array", "<font color=\"red\"><b>Array</b></font>", nl2br(str_replace(" ", " &nbsp; ", print_r($subject, true)))));
		} else {
			$debug .= str_replace("=>", "&#8658;", str_replace("Array", "<font color=\"red\"><b>Array</b></font>", print_r($subject, true)));

		}

		$debug .= '</div>';
		if ($echo) {
			echo $debug;
		} else {
			return $debug;
		}
	}

}

// No closing tag
