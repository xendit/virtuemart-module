<?php
defined ('_JEXEC') or die();

vmJsApi::css('xendit','plugins/vmpayment/xendit/xendit/assets/css/');
vmJsApi::addJScript('https://cdnjs.cloudflare.com/ajax/libs/jquery.payment/3.0.0/jquery.payment.min.js');
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

<?php if ($viewData['plugin']->xendit_gateway_payment_type == 'CC') { ?>
<div id="cc-form" style="<?php if(!$viewData['cc_selected']) echo 'display:none;'; ?> padding:10px 0px 10px 18px;">
	<div>
		<label for="xendit_gateway_card_number">Card number <span class="required">*</span></label><br>
		<input type="text" name="xendit_gateway_card_number" id="xendit_gateway_card_number" class="cc-number" 
			   pattern="\d*" maxlength="19" x-autocompletetype="cc-number" placeholder="•••• •••• •••• ••••">
	</div>
	<div style="float:left; margin-right:15px;">
		<label for="xendit_gateway_card_expiry">Expiry <span class="required">*</span></label><br>
		<input type="text" name="xendit_gateway_card_expiry" id="xendit_gateway_card_expiry" class="cc-expiry" 
			   pattern="\d*" maxlength="7" placeholder="MM/YY">
	</div>
	<div style="float:left;">
		<label for="xendit_gateway_card_code">Card code <span class="required">*</span></label><br>
		<input type="text" name="xendit_gateway_card_code" id="xendit_gateway_card_code" class="cc-code" 
			   pattern="\d*" maxlength="4" placeholder="CVC">
	</div>
</div>
<script>
	jQuery(document).ready(function ($){
		// Set up formatting for Credit Card fields
		$('#cc-form .cc-number').payment('formatCardNumber');
		$('#cc-form .cc-expiry').payment('formatCardExpiry');
		$('#cc-form .cc-code').payment('formatCardCVC');
	});
</script>
<?php } ?>

<script>
	jQuery(document).ready(function ($){
		$('.xendit-pg-input').change(function() {
			var paymentType = $("input[name=virtuemart_paymentmethod_id]:checked").attr('xendit-payment-type');
			if (paymentType == 'CC') {
				$('#cc-form').show();
			}
			else {
				$('#cc-form').hide();
			}
		});
	});
</script>