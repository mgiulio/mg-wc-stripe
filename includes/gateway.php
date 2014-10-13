<?php
if (!defined('ABSPATH')) exit;

class mg_Gateway_Stripe extends WC_Payment_Gateway {
	private $version = '1.0-beta';
	private $path;
	private $url;
	private $publishable_key;
	private $secret_key;
	private $logger;
	private $use_sandbox;
	
    public function __construct() {	
		$this->setup_paths_and_urls();
		
		$this->supports[] = 'default_credit_card_form';
		
        $this->id = 'mg_stripe';
		$this->method_title = __('mg Stripe', 'mg_stripe');
		$this->method_description = __('Process credit cards with Stripe', 'mg_stripe');
        $this->has_fields      = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
		
		if ($this->get_option('logging') == 'yes')
			$logger = new WC_Logger();
        
		$this->use_sandbox = $this->get_option('sandbox') == 'yes';
		
		// API keys
        $test_secret_key = $this->get_option('test_secret_key');
        $test_pub_key = $this->get_option('test_pub_key');
		$live_secret_key = $this->get_option('live_secret_key');
        $live_pub_key = $this->get_option('live_pub_key');
		
		$this->publishable_key = $this->use_sandbox ? $test_pub_key : $live_pub_key;
        $this->secret_key = $this->use_sandbox ? $test_secret_key : $live_secret_key;
		
        add_action('woocommerce_update_options_payment_gateways_' . $this->id , array($this, 'process_admin_options'));
		add_action('woocommerce_credit_card_form_args', array($this, 'wc_cc_default_args'), 10, 2); 
		//add_action('woocommerce_credit_card_form_start', array($this, 'error_box'));
		add_action('woocommerce_credit_card_form_end', array($this, 'inject_js'));
		add_action('admin_notices', array($this, 'admin_notices'));
    }
	
	public function admin_notices() {
		$msgs = array();
		
		if (!$this->use_sandbox && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes')
            $msgs[] = sprintf(
				__('%s sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'mg_stripe'), 
				$this->method_title, 
				admin_url('admin.php?page=wc-settings&tab=checkout')
			);
			
		if (!empty($msgs)) {
			?>
			<ul class="error">
				<?php foreach ($msgs as $msg): ?>
					<li><?php echo $msg; ?></li>
				<?php endforeach; ?>
			</ul>
		<?php
		}
	}
	
	public function wc_cc_default_args($args, $gateway_id) {
		if ($gateway_id === $this->id)
			$args['fields_have_names'] = false;
			
		return $args;
	}
	
	public function error_box($gateway_id) {
		if ($gateway_id !== $this->id)
			return;
		?>
			<ol id="mg-stripe-errorbox"></ol>
		<?php
	}
	
	public function inject_js($gateway_id) {
		if ($gateway_id !== $this->id)
			return;
			
		wp_register_script(
			'stripe_js', 
			'https://js.stripe.com/v2/', 
			array(), 
			$this->version, 
			true
		);
		
		wp_enqueue_script(
			'mg_stripe', 
			$this->url['assets'] . 'js/striper.js', 
			array('jquery', 'stripe_js'), 
			$this->version, 
			true
		);
		
		wp_localize_script(
			'mg_stripe',
			'mgStripeCfg',
			array(
				'publishableKey' => $this->publishable_key,
				'gatewayId' => $this->id
			)
		);
	}

    public function init_form_fields() {
		$this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'mg_stripe'),
                'type'        => 'checkbox',
                'label'       => __('Enable Credit Card Payment', 'mg_stripe'),
                'default'     => 'no'
            ),
			'test_secret_key' => array(
                'title'       => __('Stripe API Test Secret key', 'mg_stripe'),
                'type'        => 'text',
                'default'     => ''
            ),
            'test_pub_key' => array(
                'title'       => __('Stripe API Test Publishable key', 'mg_stripe'),
                'type'        => 'text',
                'default'     => ''
            ),
            'live_secret_key' => array(
                'title'       => __('Stripe API Live Secret key', 'mg_stripe'),
                'type'        => 'text',
                'default'     => ''
            ),
            'live_pub_key' => array(
                'title'       => __('Stripe API Live Publishable key', 'mg_stripe'),
                'type'        => 'text',
                'default'     => ''
            ),
			'title' => array(
                'title'       => __('Title', 'mg_stripe'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'mg_stripe'),
                'default'     => __('Credit Card  with Stripe', 'mg_stripe')
            ),
			'sandbox' => array(
                'title'       => __('Testing', 'mg_stripe'),
                'type'        => 'checkbox',
                'label'       => __('Turn on testing with Stripe sandbox', 'mg_stripe'),
                'default'     => 'no'
            ),
			'logging' => array(
                'title'       => __('Logging', 'mg_stripe'),
                'type'        => 'checkbox',
                'label'       => __('Turn on logging to troubleshot problems', 'mg_stripe'),
                'default'     => 'no'
            )
       );
    }
	
	public function validate_enabled_field($key) {
		$prefix = $this->plugin_id . $this->id . '_';
		
		if (isset($_POST["{$prefix}sandbox"]))
			return 
				empty($_POST["{$prefix}test_pub_key"])
				||
				empty($_POST["{$prefix}test_secret_key"])
				?
				'no'
				: $this->validate_checkbox_field($key)
			;
		else 
			return 
				empty($_POST["{$prefix}live_pub_key"])
				||
				empty($_POST["{$prefix}live_secret_key"])
				?
				'no'
				: $this->validate_checkbox_field($key)
			;
	}
	
	public function process_payment($order_id) {
		$result = false;
		
		try {
			$token = isset( $_POST['stripeToken'] ) ? wc_clean( $_POST['stripeToken'] ) : '';
		
			if (empty($token))
				throw new Exception(__( 'Please make sure your card details have been entered correctly and that your browser supports JavaScript', 'mg_stripe'));
		
			$order = wc_get_order($order_id);
		
			$charge = $this->charge_user($order);
			$transaction_id = $charge['id'];
			
			$order->payment_complete($transaction_id);
			WC()->cart->empty_cart();
			$order->add_order_note(
				sprintf(
					"%s payment completed with Transaction Id of '%s'",
					$this->method_title,
					$transaction_id
				)
			);
			
			$result = true;
        } catch(Stripe_Error $e) {
			$body = $e->getJsonBody();
			$error  = $body['error'];
			$err_msg = __('Stripe error: ', 'mg_Stripe') . $error['message'];
			
			$order->add_order_note(
				sprintf(
					__("%s Credit Card Payment Failed with message: '%s'", 'mg_stripe'),
					$this->method_title,
					$err_msg
				)
			);
			
			$this->error($err_msgs);
		} catch(Exception $e) {
			$this->error($e->getMessage());
		}
			
		return $result ?
			array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            ) :
			array(
				'result' => 'fail',
				'redirect'=> ''
			)
		;
    }

	private function charge_user($order) {
		if (!class_exists('Stripe'))
			require_once $this->path['includes'] . 'lib/stripe-php/lib/Stripe.php';

		Stripe::setApiKey($this->secret_key);
		
		$currency = get_woocommerce_currency();
		$amount = $order->get_total();
		if (!is_zero_decimal_currency($currency))
			$amount *= 100;

		$charge = Stripe_Charge::create(array(
			'currency' => strtolower($currency),
			'amount' => $amount,
			'card' => $token,
			//'description' => sprintf("Charge for %s", $order->billing_email),
			'capture' => false
		));
    }
	
	private function error($msg) {
		if ($this->logger)
			$this->logger->add('mg_stripe', $msg);

		wc_add_notice($err_msg, 'error');
	}
	
	private function is_zero_decimal_currency($currency) {
		return in_array($currency, array(
			'BIF',
			'CLP',
			'DJF',
			'GNF',
			'JPY',
			'KMF',
			'KRW',
			'MGA',
			'PYG',
			'RWF',
			'VND',
			'VUV',
			'XAF',
			'XOF',
			'XPF'
		));
	}
  
	private function setup_paths_and_urls() {
		$this->path['plugin_file'] = trailingslashit(dirname(dirname(__FILE__))) . 'plugin.php';
		$this->path['plugin_dir'] = trailingslashit(plugin_dir_path($this->path['plugin_file']));
		$this->path['includes'] = $this->path['plugin_dir'] . 'includes/';
		
		$this->url['plugin_dir'] = trailingslashit(plugin_dir_url($this->path['plugin_file']));
		$this->url['assets'] = $this->url['plugin_dir'] . 'assets/';
	}
	
}
