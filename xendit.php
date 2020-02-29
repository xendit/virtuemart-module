<?php

defined('_JEXEC') or die('Direct Access is not allowed.');

/**
 * @package    VirtueMart
 * @subpackage Plugins  - Elements
 * @package VirtueMart
 * @subpackage
 * @author Xendit
 * @link https://xendit.co
 * @copyright Copyright (c) 2020 Xendit. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */
if (!class_exists('vmPSPlugin')) {
	require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

class plgVmpaymentXendit extends vmPSPlugin {

	function __construct(& $subject, $config) {

		parent::__construct($subject, $config);

		$this->_loggable = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);

		// Xendit custom parameters
        $this->defaultMinimumAmount = 10000;
		$this->defaultMaximumAmount = 1000000000;
		$this->defaultCCMaximumAmount = 200000000;
	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {

		if ($res = $this->selectedThisByJPluginId($jplugin_id)) {
			$virtuemart_paymentmethod_id = vRequest::getInt('virtuemart_paymentmethod_id');
			$method = $this->getPluginMethod($virtuemart_paymentmethod_id);
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

		$SQLfields = array(
			'id'                          => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(255)',
			'payment_order_total'         => 'decimal(15,4) NOT NULL DEFAULT \'0.0000\'',
			'payment_currency'            => 'varchar(50)',
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
     * Can be used to show/hide the payment plugin on specific conditions.
     * 
     * @param array  $cart        cart details
     * @param object $method      method data
     * @param object $cart_prices cart prices object
     */
    function checkConditions($cart, $method, $cart_prices) {

        $total_price = $cart_prices['salesPrice'] + $cart_prices['salesPriceShipment'];

        if ($total_price < $this->defaultMinimumAmount) {
            return FALSE;
		}
		if ($method->xendit_gateway_payment_type == 'CC' && $total_price > $this->defaultCCMaximumAmount) {
			return FALSE;
		} else if ($total_price > $this->defaultMaximumAmount) {
			return FALSE;
		}

		return TRUE;
    }
	
    /**
     * Trigerred when end user click the "Confirm Purchase" button.
     */
	function plgVmConfirmedOrder($cart, $order) {

		if (!($this->_currentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}
        if (!class_exists('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}
		
		$xenditInterface = $this->_loadXenditInterface();
		$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		vmLanguage::loadJLang('com_virtuemart', TRUE);
		vmLanguage::loadJLang('com_virtuemart_orders', TRUE);

		$this->getPaymentCurrency($this->_currentMethod, $order['details']['BT']->payment_currency_id);
		$email_currency = $this->getEmailCurrency($this->_currentMethod);

		$order_number = $order['details']['BT']->order_number;

		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $this->_currentMethod->payment_currency);
		/**
		 * AMOUNT VALIDATION
		 * $totalInPaymentCurrency returns an array of 'value & 'display'
		 * 'value' is unformatted & may contain decimals as a result of currency conversion, tax, etc
		 * We'll reject if 'value' is not an integer
		 */
		$order_amount = $totalInPaymentCurrency['value'];
		if ((int)$order_amount != $totalInPaymentCurrency['value']) {
            vmError(vmText::sprintf('VMPAYMENT_XENDIT_INVALID_AMOUNT'));
			$this->redirectToCart();
            return;
		}

        $dbValues['virtuemart_order_id'] = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
		$dbValues['order_number'] = $order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['payment_name'] = $this->_currentMethod->payment_name;
		$dbValues['payment_order_total'] = $order_amount;
		$dbValues['payment_currency'] = $this->_currentMethod->currency_id;

		$address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

        $site_config = JFactory::getConfig();
        $store_name = $site_config->get('sitename');
		$ext_id_store_name = substr(preg_replace("/[^a-z0-9]/mi", "", $store_name), 0, 20);
		
		$paymentType = $this->_currentMethod->xendit_gateway_payment_type;

		// Differentiate between VA & CC payment methods
		if ($paymentType == 'CC') {
			// TODO: handle CC payment
			
			return;
		}
		else {
			$invoice_data = array(
				'external_id' => "virtuemart-xendit-$ext_id_store_name-$order_number",
				'amount' => (int)$order_amount,
				'payer_email' => !empty($address->email) ? $address->email : 'virtuemartNoReply@xendit.co',
				'description' => "Payment for Order #{$order_number} at $store_name",
				'client_type' => 'INTEGRATION',
				'success_redirect_url' => self::getSuccessUrl($order),
				'failure_redirect_url' => self::getCancelUrl($order),
				'platform_callback_url' => self::getNotificationUrl($order)
			);
			$invoice_header = array(
				'x-plugin-method: ' . $paymentType,
				'x-plugin-store-name: ' . $store_name
			);

			try {
				$invoice_response = $xenditInterface->createInvoice($invoice_data, $invoice_header);
	
				if (isset($invoice_response['error_code'])) {
					$xendit_error = $this->getXenditErrorMessage($invoice_response);
					vmError(vmText::sprintf('VMPAYMENT_XENDIT_ERROR_FROM', $xendit_error['title'], $xendit_error['message']));
					$this->redirectToCart();
					return;
				}
		
				$dbValues['xendit_invoice_id'] = $invoice_response['id'];
				$dbValues['xendit_invoice_url'] = $invoice_response['invoice_url'];
				$dbValues['xendit_status'] = $invoice_response['status'];
				$this->storePSPluginInternalData ($dbValues);
		
				$modelOrder = VmModel::getModel ('orders');
				$order['order_status'] = $this->getNewStatus ($this->_currentMethod);
				$order['customer_notified'] = 1;
				$order['comments'] = 'Checkout using Xendit';
				$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);
		
				$mainframe = JFactory::getApplication();
				$mainframe->redirect($invoice_response['invoice_url'] . '#' . $paymentType);
			} catch (Exception $e) {
				vmError(vmText::sprintf('VMPAYMENT_XENDIT_ERROR_FROM', $e->getMessage(), $e->getMessage()));
				$this->redirectToCart();
				return;
			}
		}
    }
    
    /**
     * Redirect to cart in case of error
     */
    function redirectToCart ($msg = NULL) {
		$app = JFactory::getApplication();
		$app->redirect(self::getCancelUrl(), $msg);
	}

	/**
     * Keep backwards compatibility
     * a new parameter has been added in the xml file
     */
	function getNewStatus ($method) {
        return 'U';
	}

    /**
     * Check if we support the payment currency used by this order.
	 * Call on checkout page load if Xendit PG is default option.
     */
	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}

		$currencyCode = shopFunctions::getCurrencyByID($this->_currentMethod->currency_id, 'currency_code_3');
        if ($currencyCode !== 'IDR') {
            $text = vmText::sprintf('VMPAYMENT_XENDIT_UNSUPPORTED_CURRENCY');
            vmError($text, $text);
            
			return FALSE;
        }

		return TRUE;
	}

    /**
     * Display order information after redirected from Xendit PG. Assign HTML to vRequest.
     * 
     * @return boolean
     */
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

		// the payment itself should send the parameter needed.
		$virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);
		$order_number = vRequest::getString('on', 0);

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod ->payment_element)) {
			return NULL;
		}

		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
			return NULL;
		}
		if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
			return '';
		}
		$orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($virtuemart_order_id);
        vmLanguage::loadJLang('com_virtuemart_orders', TRUE);
		if (!class_exists('CurrencyDisplay')) {
			require(VMPATH_ADMIN . DS . 'helpers' . DS . 'currencydisplay.php');
		}

		if (!class_exists('VirtueMartCart')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
		}

		vmLanguage::loadJLang('com_virtuemart_orders',TRUE);

		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$order['details']['BT']->order_currency);
		$cart = VirtueMartCart::getCart();
		$currencyDisplay = CurrencyDisplay::getInstance($cart->pricesCurrency);

		$payment = end($payments);

		$pluginName = $this->renderPluginName($method, $where = 'post_payment');
		$html = $this->renderByLayout('post_payment', array(
            'order' => $order,
            'paymentInfos' => $payment,
            'pluginName' => $pluginName,
            'displayTotalInPaymentCurrency' => $totalInPaymentCurrency['display']
        ));
		vRequest::setVar ('html', $html);

		$cart = VirtueMartCart::getCart();
		$cart->emptyCart();

		return TRUE;
	}

	/**
	 * Callback
	 * This event is fired by Offline Payment. It can be used to validate the payment data as entered by the user.
	 * Return:
	 * @link: index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&xendit_mode=[xendit_mode]
	 * xendit_mode:
	 * 		- xendit_invoice_callback
	 * 		- xendit_cc_callback (not support yet)
	 * 
	 * @author Xendit
	 * 
	 */
	function plgVmOnPaymentNotification() {
		
		if (isset($_REQUEST['xendit_mode'])) {
			if (!class_exists('VirtueMartModelOrders')) {
				require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
			}
		
			if ($_REQUEST['xendit_mode'] == 'xendit_invoice_callback') {
				
				if (($_SERVER["REQUEST_METHOD"] === "POST")) {
					$data = file_get_contents("php://input");
					$response = json_decode($data);
					
					$this->validateInvoicePayment($response);
				}
				
			}
		}
		
		die('done');
	}
	
	public function validateInvoicePayment($response) {
		$orderId = $response->external_id;
        
        if ($orderId) {
			if (!class_exists('XenditApi')) {
				require(VMPATH_ROOT . DS.'plugins'.DS.'vmpayment'.DS.'xendit'.DS.'xendit'.DS.'lib'.DS.'xendit_api.php');
			}
			
            $explodedExtId = explode("-", $orderId);
            $order_number = end($explodedExtId);

			$order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
			$orderModel = VmModel::getModel('orders');
			
			$order = $orderModel->getOrder($order_id);
			
			if (!($payments = $this->getDatasByOrderId($order_id))) {
				return FALSE;
			}
			
			$method = $this->getVmPluginMethod($payments[0]->virtuemart_paymentmethod_id);

			$xenditInterface = new XenditApi($method);
			
			$invoice = $xenditInterface->getInvoice($response->id);
			
            if (isset($invoice['error_code'])) {
                header('HTTP/1.1 400 Invalid Invoice Data Received');
                exit;
			}

            if ('PAID' == $invoice['status'] || 'SETTLED' == $invoice['status']) {
				$modelOrder = VmModel::getModel('orders');
				$order = array();

				$order['order_status'] = 'C';
				$notes = json_encode(
						array(
							'invoice_id' => $invoice['id'],
							'status' => $invoice['status'],
							'payment_method' => $invoice['payment_method'],
							'paid_amount' => $invoice['paid_amount'],
						)
					);
					
				$order['comments'] = vmText::_('Xendit Payment successful, Response: '. "{$notes}");
				$order['customer_notified'] = 1;
				
				$modelOrder->updateStatusForOneOrder($order_id, $order, false);
		
                die('SUCCESS');
            } else {
				$order['order_status'] = 'X';
				$notes = json_encode(
						array(
							'invoice_id' => $invoice['id'],
							'status' => $invoice['status'],
							'payment_method' => $invoice['payment_method'],
							'paid_amount' => $invoice['paid_amount'],
						)
					);
					
				$order['comments'] = vmText::_('Xendit Payment successful, Response: '. "{$notes}");
				$order['customer_notified'] = 1;

				$modelOrder->updateStatusForOneOrder($order_id, $order, false);
				
                header('HTTP/1.1 400 Invalid Data Received');
                exit;
            }
        } else {
            header('HTTP/1.1 400 Invalid Data Received');
            exit;
        }
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
		if (!($payments = $this->getXenditInternalData($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		$payment = $payments[0];
		
		$html = '<table class="adminlist table" >' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('Date', $payment->created_on);
		$html .= $this->getHtmlRowBE('Payment Name', $payment->payment_name);
		$html .= $this->getHtmlRowBE('Total', number_format($payment->payment_order_total) . " " . shopFunctions::getCurrencyByID($payment->payment_currency, 'currency_code_3'));
		
		if ($payment->xendit_invoice_id) {
			$html .= $this->getHtmlRowBE('Xendit Invoice ID', '<a href="'.$payment->xendit_invoice_url.'" target="blank">' . $payment->xendit_invoice_id . '</a>');
		}
		if ($payment->xendit_charge_id) {
			$html .= $this->getHtmlRowBE('Xendit Charge ID', $payment->xendit_charge_id);
		}

		$html .= '</table>' . "\n";

		return $html;
	}

	/**
	 * @param int $virtuemart_order_id
	 * @param string $order_number
	 * @return mixed|string
	 */
	private function getXenditInternalData($virtuemart_order_id, $order_number = '') {
		if (empty($order_number)) {
			$orderModel = VmModel::getModel('orders');
			$order_number = $orderModel->getOrderNumber($virtuemart_order_id);
		}
		$db = JFactory::getDBO();
		$q = 'SELECT * FROM `' . $this->_tablename . '` WHERE ';
		$q .= " `order_number` = '" . $order_number . "'";

		$db->setQuery($q);
		if (!($payments = $db->loadObjectList())) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		return $payments;
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
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not valid
	 *
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg) {
		
		return $this->OnSelectCheck($cart);
	}

	/**
	 * This event is fired to display the plugin methods in the cart (edit shipment/payment) for example
	 *
	 * @param object $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		$htmla = array();
		vmLanguage::loadJLang('com_virtuemart');
		$currency = CurrencyDisplay::getInstance();
		
		$this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id);

		$isCCSelected = false;
		if ($this->_currentMethod->xendit_gateway_payment_type == 'CC') {
			$isCCSelected = true;
		}

		foreach ($this->methods as $method) {
			if ($this->checkConditions($cart, $method, $cart->cartPrices)) {
				$cartPrices = $cart->cartPrices;
				$methodSalesPrice = $this->calculateSalesPrice($cart, $method, $cartPrices);

				$logo = $this->displayLogos($method->payment_logos);
				$payment_cost = '';
				if ($methodSalesPrice) {
					$payment_cost = $currency->priceDisplay($methodSalesPrice);
				}
				if ($selected == $method->virtuemart_paymentmethod_id) {
					$checked = 'checked="checked"';
				} else {
					$checked = '';
				}

				$publicKey = '';
				if ($method->xendit_gateway_payment_type) { // identified as xendit payment
					$this->_currentMethod = $method;
					$xenditInterface = $this->_loadXenditInterface();
					$publicKey = $xenditInterface->getPublicKey();

					// get CC settings
					$ccSettings = array();
					if ($method->xendit_gateway_payment_type == 'CC') {
						try {
							$ccSettings = $xenditInterface->getCCSettings();
						} catch (Exception $e) {
							$ccSettings = array();
							vmError(vmText::sprintf('VMPAYMENT_XENDIT_ERROR_FROM', $e->getMessage(), $e->getMessage()));
						}
					}
				}
				
				if ($method->xendit_gateway_payment_type == 'CC') {
					$html = $this->renderByLayout('cc_payment', 
					array(
						'plugin' => $method,
						'checked' => $checked,
						'payment_logo' => $logo,
						'payment_cost' => $payment_cost,
						'public_key' => $publicKey,
						'cc_selected' => $isCCSelected,
						'cc_settings' => $ccSettings
					));
				}
				else {
					$html = $this->renderByLayout('display_payment', 
					array(
						'plugin' => $method,
						'checked' => $checked,
						'payment_logo' => $logo,
						'payment_cost' => $payment_cost
					));
				}
				$htmla[] = $html;
			}
		}
		if (!empty($htmla)) {
			$htmlIn[] = $htmla;
		}

		return TRUE;
	}

	/*
	* Calculate the price (value, tax_id) of the selected method
	* Price calculation is done on checkbox selection of payment method at cart view
	* Without this function, our payment plugin will not be selectable on the cart view
	*
	* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
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
	 *
	public function plgVmOnCheckoutCheckDataPayment (VirtueMartCart $cart) {

		if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
		}
		
		if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
			return FALSE;
		}
		
		$xenditInterface = $this->_loadXenditInterface();

		return true;
	}
	*/

	/**
	 * This method is fired when showing when printing an Order
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

	/*********************/
	/* Private functions */
	/*********************/
	private function _loadXenditInterface() {
		/**
		 * We need this class to:
		 * 1. Validate order (check if API key is set, currency is supported, amount is valid)
		 * 2. Order handler (change order status, create invoice, redirect to invoice URL)
		 */
		if (!class_exists('XenditApi')) {
			require(VMPATH_ROOT . DS.'plugins'.DS.'vmpayment'.DS.'xendit'.DS.'xendit'.DS.'lib'.DS.'xendit_api.php');
		}

		$xenditInterface = new XenditApi($this->_currentMethod);

		return $xenditInterface;
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
    
    /*********************/
	/* Static functions */
    /*********************/
    
    /**
     * Return URL to thank you page
     * @param order $order
     * @return string
     */
    static function getSuccessUrl ($order) {
		return JURI::root()."index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=" . $order['details']['BT']->virtuemart_paymentmethod_id . '&on=' . $order['details']['BT']->order_number . "&Itemid=" . vRequest::getInt('Itemid'). '&lang='.vRequest::getCmd('lang','');
	}

    /**
     * Return URL to cart
     * @return string
     */
	static function getCancelUrl () {
		return  JURI::root().'index.php?option=com_virtuemart&view=cart&Itemid=' . vRequest::getInt('Itemid').'&lang='.vRequest::getCmd('lang','');
	}
    
    /**
     * Return callback URL. Still not final.
     * @param order $order
     * @return string
     */
	static function getNotificationUrl ($order) {
		return JURI::root()  .  "index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm=" . $order['details']['BT']->virtuemart_paymentmethod_id . '&on=' . $order['details']['BT']->order_number . '&lang='.vRequest::getCmd('lang','');
    }

    /**
     * Map Xendit error message. Return error title and message.
     * @param array $response
     * @return array
     */
	static function getXenditErrorMessage ($response) {
        switch ($response['error_code']) {
            case 'INVALID_API_KEY':
            case 'REQUEST_FORBIDDEN_ERROR':
                return array(
                    'title' => 'Invalid API Key',
                    'message' => 'Your merchant using wrong Xendit credential, please inform your merchant so your merchant can change it to the correct one'
                );
            default:
            return array(
                'title' => $response['error_code'],
                'message' => $response['message']
            );
        }
	}
}