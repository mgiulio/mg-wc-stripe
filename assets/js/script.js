jQuery(function($) {
		 
	Stripe.setPublishableKey(mgStripeCfg.publishableKey);
		
    var
		tokenInfo = null,
		checkoutForm = $('form.checkout'),
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
		})(),
		log = !mgStripeCfg.logging || !console ? function() {} : function() { console.log.apply(console, ['mg WC Stripe:'].concat(Array.prototype.slice.call(arguments, 0))); }
	;

	checkoutForm.on(
		'checkout_place_order_' + mgStripeCfg.gatewayId, 
		getStripeToken
	);
	
	function tokenNeeded() {
		if (tokenInfo == null)
			return true;
			
		try {
			var tokenArgs = createTokenArgs();
			return tokenInfo.tokenArgs !== JSON.stringify(tokenArgs);
		}
		catch(e) {
			return true;
		}
	}
	
	function createTokenArgs() {
		var errors = [];
		
		var cardNumber = $('#' + mgStripeCfg.gatewayId + '-card-number').val();
		if (!$.payment.validateCardNumber(cardNumber))
			errors.push('Invalid credit card number');
		
		var 
			expiryString = $('#' + mgStripeCfg.gatewayId + '-card-expiry').val(),
			expiryDate = $.payment.cardExpiryVal(expiryString)
		;
		if (!$.payment.validateCardExpiry(expiryDate.month, expiryDate.year))
			errors.push('Invalid credit card expiry date');
			
		var cvc = $('#' + mgStripeCfg.gatewayId + '-card-cvc').val();
		if (cvc.length > 0)
			if (!$.payment.validateCardCVC(cvc))
				errors.push('Invalid credit card CVC');
	
		if (errors.length > 0) {
			var ex = new Error();
			ex.errors = errors;
			throw ex;
		}
		
		var tokenArgs = {
			number: cardNumber, 
			exp_month: expiryDate.month,
			exp_year: expiryDate.year
		};
		if (cvc.length > 0)
			tokenArgs.cvc = cvc;

		return tokenArgs;
	}
	
	function getStripeToken() {
		errorBox.hide();

		if (!tokenNeeded()) {
			log('We have an updated token:', tokenInfo);
			
			// Make sure it is in the checkout form
			var input;
			if ((input = $('input[name=stripe_token]')).length > 0)
				input.val(tokenInfo.token);
			else
				$('<input name="stripe_token">').val(tokenInfo.token).appendTo(checkoutForm);
			
			return true;
		}
		
		blockUI();
		
		try {
			tokenArgs = createTokenArgs();
		}
		catch (e) {
			for (var i = 0; i < e.errors.length; i++)
				errorBox.push(e.errors[i]);
			errorBox.show();
			
			unblockUI();
			
			return false;
		}
		
		log('Asking Stripe for a token for card: ', tokenArgs);
		Stripe.card.createToken(tokenArgs, stripeResponseHandler.bind(null, JSON.stringify(tokenArgs)));
		
		return false;
    }
	
	function stripeResponseHandler(tokenArgs, status, response) {
		log('Stripe replied with status ' + status + ' and response ', response);
		
		unblockUI();

		if (status == 200) {
			tokenInfo = {
				token: response.id,
				tokenArgs: tokenArgs
			};
			log('Token created:', tokenInfo);
			
			checkoutForm.submit();
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
 