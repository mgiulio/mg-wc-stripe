<?php
/*
Plugin Name: mg wc Stripe
Plugin URI: http://mgiulio.info/projects/mg-wc-stripe
Description: Stripe payment gateway for WooCommerce
Version: 1.0-beta
Author: Giulio 'mgiulio' Mainardi
Author URI: http://mgiulio.info
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class mg_wc_Stripe {

	public $text_domain = 'mg_stripe';

	public function __construct() {
		add_action('init', array($this, 'setup_i18n'));
		
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
		
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
		require_once 'includes/gateway.php';
		
		array_push($class_names, 'mg_Gateway_Stripe');
		
		return $class_names;
	}

	public function notice() {
		?>
		<div class="error"><?php echo __("mg Stripe gateway didn't register for missing requirements", $this->text_domain); ?></div>
		<?php
	}
	
	public function plugin_action_links($links) {
		$action_links = array(
			'settings' => '<a href="' . 
				admin_url('admin.php?page=wc-settings&tab=checkout&section=mg_gateway_stripe') . 
				'" title="' . 
				esc_attr(__('View Settings', 'striper')) . '">' . __('Settings', 'mg_stripe') . '</a>',
		);

		return array_merge( $action_links, $links);
	}
	
	public function setup_i18n() {
		load_plugin_textdomain($this->text_domain, false, plugin_basename(dirname(__FILE__)) . '/i18n/languages');
	}
		
}

$GLOBALS['mg_wc_stripe'] = new mg_wc_Stripe();
