jQuery(function($) {
		 
	Stripe.setPublishableKey(mgStripeCfg.publishableKey);
		
    var 
		log = !mgStripeCfg.logging || !console ? function() {} : function() { console.log.apply(console, ['mg WC Stripe: '].concat(Array.prototype.slice.call(arguments, 0))); },
		checkoutForm = $('form.checkout'),
		stripeTokenHiddenInput = $('<input type="hidden" name="stripe_token">')//,
		errorBox = (function() {
			var 
				box = $('<ol id="mg-stripe-errorbox"></ol>')
			;
			return {
				hide: function() {
					box.empty().detach();
				},
				push: function(errMsg) {
					box.append('<li>' + errMsg + '</li>');
					return this;
				},
				show: function() {
					box.prependTo('#' + mgStripeCfg.gatewayId + '-cc-form');
				},
				errors: function() {
					return box.children().length > 0;
				}
			};
		})()
	;

	checkoutForm.on(
		'checkout_place_order_' + mgStripeCfg.gatewayId, 
		getStripeToken
	);
	$('body').on(
		'click', '#place_order, form.checkout input:submit', 
		function() { /* Make sure there's not an old token on the form*/ stripeTokenHiddenInput.detach(); }
	);
	
	function getStripeToken() {
		errorBox.hide();

		// Pass if we have a token
		if ( checkoutForm.find('[name=stripe_token]').length > 0) {
			log('Token found');
			return true;
		}
		
		blockUI();
			
		var cardNumber = $('#' + mgStripeCfg.gatewayId + '-card-number').val();
		if (!$.payment.validateCardNumber(cardNumber))
			errorBox.push('Invalid credit card number');
		
		var 
			expiryString = $('#' + mgStripeCfg.gatewayId + '-card-expiry').val(),
			expiryDate = $.payment.cardExpiryVal(expiryString)
		;
		if (!$.payment.validateCardExpiry(expiryDate.month, expiryDate.year))
			errorBox.push('Invalid credit card expiry date');
			
		var cvc = $('#' + mgStripeCfg.gatewayId + '-card-cvc').val();
		if (cvc.length > 0)
			if (!$.payment.validateCardCVC(cvc))
				errorBox.push('Invalid credit card CVC');
		
		if (errorBox.errors()) {
			errorBox.show();
			unblockUI();
			return false;
		}
		
		var tokenCreationArgs = {
			number: cardNumber, 
			exp_month: expiryDate.month,
			exp_year: expiryDate.year
		};
		if (cvc.length > 0)
			tokenCreationArgs.cvc = cvc;
		
		log('Asking Stripe for a token...');
		Stripe.card.createToken(tokenCreationArgs, stripeResponseHandler);
		
		return false;
    }
	
	function stripeResponseHandler(status, response) {
		log('...Stripe replied with status: ' + status + ' and response : ', response);
		
		unblockUI();

		if (status == 200) {
			checkoutForm
				.append(stripeTokenHiddenInput.val(response.id))
				.submit()
			;
		} else
			errorBox.push(response.error.message).show();
	}
	
	function blockUI() {
		checkoutForm.block({
			message: null,
			overlayCSS: {
				background: '#fff url(' + woocommerce_params.ajax_loader_url + ') no-repeat center',
				backgroundSize: '16px 16px',
				opacity: .6
			}
		});
	}
	
	function unblockUI() {
		checkoutForm.unblock();
	}

});
 