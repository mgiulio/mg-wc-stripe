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

class mg_wc_Stripe {

	public function __construct() {
		add_action('plugins_loaded', array($this, 'check_requirements'), 0);
	}
	
	public function check_requirements() {
		if (!(
			class_exists('WC_Payment_Gateway') && 
			version_compare(WC_VERSION, '2.1', '>='
		))) {
			add_action('admin_notices', array($this, 'notice'));
			return;
		}
			
		add_filter('woocommerce_payment_gateways', array($this, 'register_gateway'));
	}
	
	public function register_gateway($class_names) {
		require_once 'includes/stripe-gateway.php';
		
		array_push($class_names, 'Striper');
		
		return $class_names;
	}

	public function notice() {
		?>
		<div class="error">Striper gateway didn't register for missing requirements</div>
		<?php
	}

	/*
	public function plugin_action_links( $links ) {
			$action_links = array(
				'settings'	=>	'<a href="' . admin_url( 'admin.php?page=wc-settings' ) . '" title="' . esc_attr( __( 'View WooCommerce Settings', 'woocommerce' ) ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>',
			);

			return array_merge( $action_links, $links );
		}
		
	dtrigger_error(WC_PLUGIN_BASENAME);
			add_filter('plugin_action_links_' . 'striper/plugin.php', array($this, 'plugin_action_links'));
	*/
	
}

new mg_wc_Stripe();
