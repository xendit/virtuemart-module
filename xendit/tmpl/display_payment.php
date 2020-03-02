<?php
defined ('_JEXEC') or die();

vmJsApi::css('xendit','plugins/vmpayment/xendit/xendit/assets/css/');
?>

<input type="radio" name="virtuemart_paymentmethod_id" class="xendit-pg-input"
       id="payment_id_<?php echo $viewData['plugin']->virtuemart_paymentmethod_id; ?>"
	   value="<?php echo $viewData['plugin']->virtuemart_paymentmethod_id; ?>" <?php echo $viewData ['checked']; ?>
	   xendit-payment-type="<?php echo $viewData['plugin']->xendit_gateway_payment_type; ?>">
<label for="payment_id_<?php echo $viewData['plugin']->virtuemart_paymentmethod_id; ?>">
    <span class="vmpayment" style="vertical-align: middle;">
        <?php if (!empty($viewData['payment_logo'])) { ?>
	        <span class="vmCartPaymentLogo"><?php echo $viewData ['payment_logo']; ?></span>
        <?php } ?>
	    <span class="vmpayment_name"><?php echo $viewData['plugin']->payment_name; ?></span>
	    <?php if (!empty($viewData['plugin']->payment_desc)) { ?>
		    <span class="vmpayment_description"><?php echo $viewData['plugin']->payment_desc; ?></span>
	    <?php } ?>
	    <?php if (!empty($viewData['payment_cost'])) { ?>
		    <span class="vmpayment_cost"><?php echo vmText::_ ('COM_VIRTUEMART_PLUGIN_COST_DISPLAY') . $viewData['payment_cost']; ?></span>
	    <?php } ?>
    </span>
</label>