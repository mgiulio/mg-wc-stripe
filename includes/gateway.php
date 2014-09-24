<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class mg_Gateway_Stripe extends WC_Payment_Gateway
{
	private $version = '1.0-beta';
	private $path;
	private $url;
	private $logger = null;
    protected $usesandboxapi              = true;
    protected $order                      = null;
    protected $transactionId              = null;
    protected $transactionErrorMessage    = null;
    protected $stripeTestApiKey           = '';
    protected $stripeLiveApiKey           = '';
    protected $publishable_key            = '';
	
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
        
		$this->usesandboxapi      = $this->get_option('sandbox') == 'yes';
		
		// API keys
        $this->testApiKey 		  = $this->get_option('test_api_key');
        $this->liveApiKey 		  = $this->get_option('live_api_key');
        $this->testPublishableKey = $this->get_option('test_publishable_key');
        $this->livePublishableKey = $this->get_option('live_publishable_key');
		
		$this->publishable_key    = $this->usesandboxapi ? $this->testPublishableKey : $this->livePublishableKey;
        $this->secret_key         = $this->usesandboxapi ? $this->testApiKey : $this->liveApiKey;
		
		if (empty($this->publishable_key) || empty($this->secret_key))
			$this->enabled = false;
        
        // tell WooCommerce to save options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id , array($this, 'process_admin_options'));
		
		add_action('woocommerce_credit_card_form_args', array($this, 'wc_cc_default_args'), 10, 2); 
		//add_action('woocommerce_credit_card_form_start', array($this, 'error_box'));
		add_action('woocommerce_credit_card_form_end', array($this, 'inject_js'));
		
		add_action('admin_notices', array($this, 'admin_notices'));
    }
	
	public function admin_notices() {
		$msgs = array();
		
		if (empty($this->publishable_key))
			$msgs[] = "The public key is missing";
		
		if (empty($this->secret_key))
			$msgs[] = "The secret key is missing";
			
		if (!$this->usesandboxapi && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes')
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

    public function init_form_fields()
    {
		$this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'mg_stripe'),
                'type'        => 'checkbox',
                'label'       => __('Enable Credit Card Payment', 'mg_stripe'),
                'default'     => 'no'
            ),
			'title' => array(
                'title'       => __('Title', 'mg_stripe'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'mg_stripe'),
                'default'     => __('Credit Card  with Stripe', 'mg_stripe')
            ),
			'test_api_key' => array(
                'title'       => __('Stripe API Test Secret key', 'mg_stripe'),
                'type'        => 'text',
                'default'     => ''
            ),
            'test_publishable_key' => array(
                'title'       => __('Stripe API Test Publishable key', 'mg_stripe'),
                'type'        => 'text',
                'default'     => ''
            ),
            'live_api_key' => array(
                'title'       => __('Stripe API Live Secret key', 'mg_stripe'),
                'type'        => 'text',
                'default'     => ''
            ),
            'live_publishable_key' => array(
                'title'       => __('Stripe API Live Publishable key', 'mg_stripe'),
                'type'        => 'text',
                'default'     => ''
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

	protected function send_to_stripe() {
		if (!class_exists('Stripe'))
			require_once $this->path['includes'] . 'lib/stripe-php/lib/Stripe.php';

		Stripe::setApiKey($this->secret_key);

		$data = $this->get_request_data();

		try {
			$charge = Stripe_Charge::create(array(
				'amount' => $data['amount'],
				'currency' => $data['currency'],
				'card' => $data['token'],
				'description' => $data['card']['name'],
				'capture' => false
			));
        
			$this->transactionId = $charge['id'];

			return true;

		} catch(Stripe_Error $e) {
			// The card has been declined, or other error
			$body = $e->getJsonBody();
			$err  = $body['error'];
		
			if ($this->logger)
				$this->logger->add('mg_stripe', 'Stripe Error:' . $err['message']);

			wc_add_notice(__('Payment error:', 'mg_stripe') . $err['message'], 'error');
        
			return false;
		}
    }

    public function process_payment($order_id)
    {
        global $woocommerce;
        $this->order        = new WC_Order($order_id);
        if ($this->send_to_stripe())
        {
          $this->complete_order();

            $result = array(
                'result' => 'success',
                'redirect' => $this->get_return_url($this->order)
            );
          return $result;
        }
        else
        {
          $this->mark_as_failed_payment();
          wc_add_notice(__('Transaction Error: Could not complete your payment', 'mg_stripe'), 'error');
        }
    }

    protected function mark_as_failed_payment()
    {
        $this->order->add_order_note(
            sprintf(
                "%s Credit Card Payment Failed with message: '%s'",
                $this->method_title,
                $this->transactionErrorMessage
            )
        );
    }

    protected function complete_order()
    {
        global $woocommerce;

        if ($this->order->status == 'completed')
            return;

        $this->order->payment_complete();
        $woocommerce->cart->empty_cart();

        $this->order->add_order_note(
            sprintf(
                "%s payment completed with Transaction Id of '%s'",
                $this->method_title,
                $this->transactionId
            )
        );
    }


  protected function get_request_data()
  {
    if ($this->order AND $this->order != null)
    {
        return array(
            "amount"      => (float)$this->order->get_total() * 100,
            "currency"    => strtolower(get_woocommerce_currency()),
            "token"       => isset($_POST['stripeToken']) ? $_POST['stripeToken'] : '',
            "description" => sprintf("Charge for %s", $this->order->billing_email),
            "card"        => array(
                "name"            => sprintf("%s %s", $this->order->billing_first_name, $this->order->billing_last_name),
                "address_line1"   => $this->order->billing_address_1,
                "address_line2"   => $this->order->billing_address_2,
                "address_zip"     => $this->order->billing_postcode,
                "address_state"   => $this->order->billing_state,
                "address_country" => $this->order->billing_country
            )
        );
    }
    return false;
  }
  
	private function setup_paths_and_urls() {
		$this->path['plugin_file'] = trailingslashit(dirname(dirname(__FILE__))) . 'plugin.php';
		$this->path['plugin_dir'] = trailingslashit(plugin_dir_path($this->path['plugin_file']));
		$this->path['includes'] = $this->path['plugin_dir'] . 'includes/';
		
		$this->url['plugin_dir'] = trailingslashit(plugin_dir_url($this->path['plugin_file']));
		$this->url['assets'] = $this->url['plugin_dir'] . 'assets/';
	}
	
}
