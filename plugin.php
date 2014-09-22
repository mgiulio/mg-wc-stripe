<?php
/*
Plugin Name: Striper (Gateway using Stripe.js)
Plugin URI: http://blog.seanvoss.com/product/striper
Description: Provides a Credit Card Payment Gateway through Stripe for woo-commerece.
Version: 0.30
Author: Sean Voss
Author URI: https://blog.seanvoss.com/
License : https://blog.seanvoss.com/product/striper
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action('plugins_loaded', 'striper_check_requirements', 0);

function striper_check_requirements() {
	if (!(
		class_exists('WC_Payment_Gateway') && 
		version_compare(WC_VERSION, '2.1', '>='
	))) {
		add_action('admin_notices', 'striper_notice');
		return;
	}
		
	add_filter('woocommerce_payment_gateways', 'striper_register_gateway');
}

function striper_notice() {
	?>
	<div class="error">Striper gateway didn't register for missing requirements</div>
	<?php
}

function striper_register_gateway($class_names) {
	require_once 'includes/stripe-gateway.php';
	
	array_push($class_names, 'Striper');
    
	return $class_names;
}
