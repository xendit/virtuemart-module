<?php
defined ('_JEXEC') or die();

vmJsApi::css('xendit','plugins/vmpayment/xendit/xendit/assets/css/');
vmJsApi::addJScript('https://cdnjs.cloudflare.com/ajax/libs/jquery.payment/3.0.0/jquery.payment.min.js');
vmJsApi::addJScript('https://js.xendit.co/v1/xendit.min.js');
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

<div id="xendit-cc-form" style="<?php if(!$viewData['cc_selected']) echo 'display:none;'; ?> padding:10px 0px 10px 18px;">
	<div>
		<label for="xendit_gateway_card_number">Card number <span class="required">*</span></label><br>
		<input type="text" name="xendit_gateway_card_number" id="xendit_gateway_card_number" class="xenditcc cc-number" 
			   pattern="\d*" maxlength="19" x-autocompletetype="cc-number" placeholder="•••• •••• •••• ••••">
	</div>
	<div style="float:left; margin-right:15px;">
		<label for="xendit_gateway_card_expiry">Expiry <span class="required">*</span></label><br>
		<input type="text" name="xendit_gateway_card_expiry" id="xendit_gateway_card_expiry" class="xenditcc cc-expiry" 
			   pattern="\d*" maxlength="7" placeholder="MM/YY">
	</div>
	<div style="float:left;">
		<label for="xendit_gateway_card_code">Security code <span class="required">*</span></label><br>
		<input type="password" name="xendit_gateway_card_code" id="xendit_gateway_card_code" class="xenditcc cc-code" 
			   pattern="\d*" maxlength="4" placeholder="CVC">
	</div>

	<!-- new line for next payment method -->
	<br clear="all" />

	<!-- Hidden inputs -->
	<input type='hidden' id='year' name='year' value=''>
	<input type='hidden' id='month' name='month' value=''>
	<input type='hidden' id='card_cvn' name='card_cvn' value=''>
	<input type='hidden' id='xendit_token' name='xendit_token' value=''>
	<input type='hidden' id='masked_card_number' name='masked_card_number' value=''>
	<input type='hidden' id='xendit_should_3ds' name='xendit_should_3ds' value=''>
</div>
<script>
	var useClick = false;
	
	function xenditClickCC() {
		useClick = true;
	}
		
	jQuery(document).ready(function ($){
		// Set up formatting for Credit Card fields
		$('#xendit-cc-form .cc-number').payment('formatCardNumber');
		$('#xendit-cc-form .cc-expiry').payment('formatCardExpiry');
		$('#xendit-cc-form .cc-code').payment('formatCardCVC');

		// Hide or show custom cc form
		$('.xendit-pg-input').change(function() {
			var paymentType = $("input[name=virtuemart_paymentmethod_id]:checked").attr('xendit-payment-type');
			if (paymentType == 'CC') {
				$('#xendit-cc-form').show();
			}
			else {
				$('#xendit-cc-form').hide();
			}
		});

		// Card validation on submit
		var flag = false;
		
		$('#checkoutFormSubmit').attr('onclick','xenditClickCC()');
		
		$('#checkoutForm').submit(function(event) {
			if (useClick){
				useClick = false;
				if (!flag) {
					event.preventDefault();
					
					var xendit_form = $('#checkoutForm');

					var paymentType = $("input[name=virtuemart_paymentmethod_id]:checked").attr('xendit-payment-type');
					if (paymentType == 'CC') {
						var cardNumber = $('#xendit_gateway_card_number').val().replace(/\s/g, '');
						var cardExpiry = $('#xendit_gateway_card_expiry').payment('cardExpiryVal');
						var cardCode = $('#xendit_gateway_card_code').val();

						var data = {
							"card_number"   	: cardNumber,
							"card_exp_month"	: String(cardExpiry.month).length === 1 ? '0' + String(cardExpiry.month) : String(cardExpiry.month),
							"card_exp_year" 	: String(cardExpiry.year),
							"card_cvn"      	: cardCode,
							"is_multiple_use"	: true
						};

						Xendit.setPublishableKey('<?php echo $viewData['public_key']; ?>');

						$('#year').val(data.card_exp_year);
						$('#month').val(data.card_exp_month);
						$('#card_cvn').val(data.card_cvn);
						
						Xendit.card.createToken(data, function(tokenErr, tokenResponse) { // on tokenization response
							if (tokenErr) { // how to display VM error style in here?
								alert(tokenErr.error_code + ": " + tokenErr.message);

								Virtuemart.stopVmLoading();
								jQuery("#checkoutFormSubmit").attr("disabled", false);
								return;
							}
							var token_id = tokenResponse.id;

							$('#xendit_token').val(token_id);
							$('#masked_card_number').val(tokenResponse.masked_card_number);

							var tokenData = {
								"token_id": token_id
							};

							var can_use_dynamic_3ds = '<?php echo $viewData['cc_settings']['can_use_dynamic_3ds']; ?>';
							if (can_use_dynamic_3ds === "1") {
								Xendit.card.threeDSRecommendation(tokenData, function(threeDSErr, threeDSResponse) {
									flag = true;
									
									if (threeDSErr) {
										$('#xendit_should_3ds').val(true);
										xendit_form.submit();
										return;
									}

									$('#xendit_should_3ds').val(threeDSResponse.should_3ds);
									xendit_form.submit();

									return;
								});
							} else {
								flag = true;
								xendit_form.submit();
							}
							
							// Prevent form submitting
							return false;
						});
					}
					else {
						flag = true;
						xendit_form.submit();
					}
				}
			}
		});
		
		
	});
</script>