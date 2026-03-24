<?php
/**
* Plugin Name: Lunu Onramp for WooCommerce - Lunu Cryptocurrencies Payment Gateway Addon
* Plugin URI: https://lunu.io/plugins
* Description: Cryptocurrencies Payment Gateway plugin.
* Version: 2.0
* Author: Lunu Solutions GmbH https://lunu.io
* Author URI: https://lunu.io/plugins/
* Text Domain: lunu-pay
*/

add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

if (!defined('LUNUPAYMENT_F2C_SERVER_NAME')) {
    define('LUNUPAYMENT_F2C_SERVER_NAME', isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
}

if (!defined('LUNUPAYMENT_F2C_STATUS_PENDING')) {
    define('LUNUPAYMENT_F2C_STATUS_PENDING', 'pending');
}

if (!defined('LUNUPAYMENT_F2C_STATUS_PAID')) {
    define('LUNUPAYMENT_F2C_STATUS_PAID', 'paid');
}

if (!defined('LUNUPAYMENT_F2C_STATUS_FAILED')) {
    define('LUNUPAYMENT_F2C_STATUS_FAILED', 'failed');
}

if (!defined('LUNUPAYMENT_F2C_STATUS_EXPIRED')) {
    define('LUNUPAYMENT_F2C_STATUS_EXPIRED', 'expired');
}

if (!defined('LUNUPAYMENT_F2C_STATUS_CANCELED')) {
    define('LUNUPAYMENT_F2C_STATUS_CANCELED', 'canceled');
}

if (!defined('LUNUPAYMENT_F2C_STATUS_AWAITING_CONFIRMATION')) {
    define('LUNUPAYMENT_F2C_STATUS_AWAITING_CONFIRMATION', 'awaiting_payment_confirmation');
}

if (!defined('LUNUPAYMENT_F2C_WC_STATUS_AWAITING_CONFIRMATION_WP')) {
    define('LUNUPAYMENT_F2C_WC_STATUS_AWAITING_CONFIRMATION_WP', 'lunu-f2c-awaiting');
}

if (!defined('LUNUPAYMENT_F2C_WC_STATUS_AWAITING_CONFIRMATION')) {
    define('LUNUPAYMENT_F2C_WC_STATUS_AWAITING_CONFIRMATION', 'wc-lunu-f2c-awaiting');
}

if (!defined('LUNUPAYMENT_F2C_WC_STATUS_PROCESSING')) {
    define('LUNUPAYMENT_F2C_WC_STATUS_PROCESSING', 'wc-processing');
}

if (!defined('LUNUPAYMENT_F2C_WC_STATUS_CANCELED')) {
    define('LUNUPAYMENT_F2C_WC_STATUS_CANCELED', 'wc-cancelled');
}


if (!defined('ABSPATH')) exit; // Exit if accessed directly
if (
  !function_exists('lunupayment_f2c_wc_gateway_load')
  && !function_exists('lunupayment_f2c_wc_action_links')
) {

if (!defined('LUNUPAYMENT_F2C_WC')) {
	define('LUNUPAYMENT_F2C_WC', 'lunupayment-f2c-woocommerce');
}

if (!defined('LUNUPAYMENT_F2C_WC_AFFILIATE_KEY')) {
	DEFINE('LUNUPAYMENT_F2C_WC_AFFILIATE_KEY', 'lunupayment');
	add_action('plugins_loaded', 'lunupayment_f2c_wc_gateway_load', 20);
	add_filter('plugin_action_links', 'lunupayment_f2c_wc_action_links', 10, 2);
	add_filter('plugin_row_meta', 'lunupayment_f2c_wc_plugin_meta', 10, 2);
}


  function lunupayment_f2c_wc_new_order_statuses() {
	register_post_status(LUNUPAYMENT_F2C_WC_STATUS_AWAITING_CONFIRMATION, array(
	  'label' => 'Awaiting payment confirmation',
	  'public' => true,
	  'exclude_from_search' => false,
	  'show_in_admin_all_list' => true,
	  'show_in_admin_status_list' => true,
	  'label_count' => _n_noop('Awaiting payment confirmation', 'Awaiting payment confirmation', 'woocommerce')
	));
  }

  function lunupayment_f2c_wc_order_statuses($order_statuses) {
	$order_statuses = array_merge($order_statuses, array());
	$order_statuses[LUNUPAYMENT_F2C_WC_STATUS_AWAITING_CONFIRMATION] = 'Awaiting payment confirmation';
	return $order_statuses;
  }

  // New order status AFTER woo 2.2
  add_action('init', 'lunupayment_f2c_wc_new_order_statuses');
  add_filter('wc_order_statuses', 'lunupayment_f2c_wc_order_statuses');


  function lunu_f2c_payment_awaiting($order) {
	$order->payment_complete();
	$order->set_status(
	  LUNUPAYMENT_F2C_WC_STATUS_AWAITING_CONFIRMATION,
	  'Payment awaiting blockchain confirmation via Lunu service<br>'
	);
	$order->save();
  }

  function lunupayment_f2c_wc_action_links($links, $file) {
	static $this_plugin;

	if (!class_exists('WC_Payment_Gateway')) return $links;

	if (false === isset($this_plugin) || true === empty($this_plugin)) {
	  $this_plugin = plugin_basename(__FILE__);
	}

	if ($file == $this_plugin) {
	  $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=lunuf2cpayments') . '">' . __('Settings', LUNUPAYMENT_F2C_WC) . '</a>';
	  array_unshift($links, $settings_link);
	}

	return $links;
  }


  function lunupayment_f2c_wc_plugin_meta($links, $file) {

	if (
	  strpos($file, 'lunupayment-f2c-woocommerce.php') !== false
	  && class_exists('WC_Payment_Gateway')
	) {

	  // Set link for Reviews.
	  $new_links = array(
		'<a
		  style="color:#0073aa"
		  href="https://wordpress.org/support/plugin/lunupayment-woocommerce/reviews/?filter=5"
		  target="_blank"
		>
		  <span class="dashicons dashicons-thumbs-up"></span> ' . __('Vote!', LUNUPAYMENT_F2C_WC) . '
		</a>',
	  );

	  $links = array_merge($links, $new_links);
	}

	return $links;
  }


  function lunupayment_f2c_wc_gateway_load() {
	// WooCommerce required
	if (!class_exists('WC_Payment_Gateway') || class_exists('WC_Gateway_LunuF2CPayment')) return;

	add_filter('woocommerce_payment_gateways', 'lunupayment_f2c_wc_gateway_add');

	// add LunuPayment gateway
	function lunupayment_f2c_wc_gateway_add($methods) {
	  if (!in_array('WC_Gateway_LunuF2CPayment', $methods)) {
		$methods[] = 'WC_Gateway_LunuF2CPayment';
	  }
	  return $methods;
	}

	// Payment Gateway WC Class
	class WC_Gateway_LunuF2CPayment extends WC_Payment_Gateway {
	  private $api_secret = '';
	  private $merchant_redirect_url = '';
	  private $use_sandbox = true;
	  private $lunu_logs_enabled = false;

	  public function __construct() {

		$this->id = 'lunuf2cpayments';
		$this->method_title = __('Lunu Onramp Payment', LUNUPAYMENT_F2C_WC);
		$this->method_description = 'Fiat-to-Crypto payments via Lunu Onramp.';
		$this->has_fields = false;
		$this->supports = array('products');


		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		$this->lunupayment_settings();

		$this->icon = apply_filters('woocommerce_lunuf2cpayments_icon', plugins_url("/images/logo.svg", __FILE__));

		// Hooks
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_thankyou_lunuf2cpayments', array($this, 'cryptocoin_payment'));

		return true;
	  }

	  public function lunu_log($message, $val = null) {
		if (!$this->lunu_logs_enabled) {
		  return;
		}
		ob_start();
		echo json_encode($val);
		file_put_contents(
		  __DIR__ . '/logs/lunu_log.txt',
		  date('Y-m-d H:i:s') . ' ' . $message . ' ' . ob_get_clean() . PHP_EOL,
		  FILE_APPEND
		);
	  }

	  private function lunupayment_settings() {
		$this->enabled = trim($this->get_option('enabled'));
		$this->api_secret = trim($this->get_option('api_secret'));
		$this->use_sandbox = trim($this->get_option('use_sandbox')) === 'yes';
		$this->merchant_redirect_url = trim($this->get_option('merchant_redirect_url'));
		$this->lunu_logs_enabled = trim($this->get_option('lunu_logs_enabled')) === 'yes';

		if (!$this->title) {
		  $this->title = __('Lunu Onramp Payment', LUNUPAYMENT_F2C_WC);
		}
		return true;
	  }

	  private function get_api_secret() {
		return $this->api_secret;
	  }

	  private function get_client_ip() {
		$headers = array(
		  'HTTP_CF_CONNECTING_IP',
		  'HTTP_X_FORWARDED_FOR',
		  'HTTP_X_REAL_IP',
		);
		foreach ($headers as $header) {
		  if (!empty($_SERVER[$header])) {
			$ip = trim(explode(',', $_SERVER[$header])[0]);
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			  return $ip;
			}
		  }
		}
		return $_SERVER['REMOTE_ADDR'];
	  }

	  public function init_form_fields() {
		$this->form_fields = array(
		  'enabled' => array(
			'title' => __('Enable/Disable', LUNUPAYMENT_F2C_WC),
			'type' => 'checkbox',
			'default' => (LUNUPAYMENT_F2C_WC_AFFILIATE_KEY == 'lunupayment' ? 'yes' : 'no'),
			'label' => __("Enable Lunu Onramp Payments in WooCommerce", LUNUPAYMENT_F2C_WC)
		  ),
		  'api_secret' => array(
			'title' => __('API Token', LUNUPAYMENT_F2C_WC),
			'type' => 'text',
			'default' => ''
		  ),
		  'use_sandbox' => array(
			'title' => __('Use sandbox', LUNUPAYMENT_F2C_WC),
			'type' => 'checkbox',
			'default' => 'yes',
			'label' => __('When enabled, API calls go to the sandbox environment', LUNUPAYMENT_F2C_WC)
		  ),
		  'merchant_redirect_url' => array(
			'title' => __('Merchant Redirect URL', LUNUPAYMENT_F2C_WC),
			'type' => 'text',
			'default' => '',
			'description' => __('URL where the customer is redirected after the payment flow. Leave empty to use the default WooCommerce order confirmation page.', LUNUPAYMENT_F2C_WC),
		  ),
		  /* 'lunu_logs_enabled' => array(
			'title' => __('Enable logs', LUNUPAYMENT_F2C_WC),
			'type' => 'checkbox',
			'default' => 'no',
			'label' => ''
		  ) */
		);

		return true;
	  }

	  public function getUrlEndpoint() {
		if ($this->use_sandbox) {
		  return 'https://api.exchange.sandbox.lunupay.com';
		} else {
		  return 'https://api.exchange.lunupay.com';
		}
	  }

	  public function getWidgetUrlEndpoint() {
		if ($this->use_sandbox) {
		  return 'https://exchange.sandbox.lunupay.com';
		} else {
		  return 'https://exchange.lunupay.com';
		}
	  }

	  public function process_payment($order_id) {
		global $woocommerce;

		$this->lunu_log('process_payment', array(
		  'order_id' => $order_id,
		));

		$order = wc_get_order($order_id);
		$order->update_status('pending', __('Awaiting payment notification from Lunu', LUNUPAYMENT_F2C_WC));

		$orderData = $order->get_data();
		$currency = $order->get_currency();
		$amount = round(floatval($order->get_total()), 2);

		$merchant_redirect_url = !empty($this->merchant_redirect_url)
		  ? $this->merchant_redirect_url
		  : $this->get_return_url($order);

		$params = array(
		  'order_id'              => $order_id,
		  'email'                 => $orderData['billing']['email'],
		  'first_name'            => $orderData['billing']['first_name'],
		  'last_name'             => $orderData['billing']['last_name'],
		  'phone_number'          => $orderData['billing']['phone'],
		  'dob'                   => $order->get_meta('_billing_dob', true),
		  'amount'                => $amount,
		  'currency'              => $currency,
		  'merchant_redirect_url' => $merchant_redirect_url,
		  'country'               => $orderData['billing']['country'],
		  'state'                 => $orderData['billing']['state'],
		  'city'                  => $orderData['billing']['city'],
		  'postcode'              => $orderData['billing']['postcode'],
		  'address_1'             => $orderData['billing']['address_1'],
		  'address_2'             => $orderData['billing']['address_2'],
		);

		$result = $this->lunu_payment_init($params);

		if (!empty($result['error_message'])) {
		  wc_add_notice($result['error_message'], 'error');
		  return;
		}

		if (empty($result) || empty($result['redirect_url'])) {
		  wc_add_notice(__('Lunu Payment service is temporarily unavailable', LUNUPAYMENT_F2C_WC), 'error');
		  return;
		}

		if (!empty($result['customer_id'])) {
		  $order->update_meta_data('_lunuf2cpayment_customer_id', $result['customer_id']);
		}
		$order->update_meta_data('_lunuf2cpayment_status', LUNUPAYMENT_F2C_STATUS_PENDING);
		$order->update_meta_data('_lunuf2cpayment_redirect_url', $result['redirect_url']);
		$order->save();

		$woocommerce->cart->empty_cart();

		return array(
		  'result'   => 'success',
		  'redirect' => $result['redirect_url'],
		);
	  }

	  public function cryptocoin_payment($order_id) {

		$order = wc_get_order($order_id);

		$order_id = $order->get_id();
		$order_status = $order->get_status();

		if ($order_status == 'cancelled') {
		  echo '<br><h2>' . __('Information', LUNUPAYMENT_F2C_WC) . '</h2>' . PHP_EOL;

		  if (time() > strtotime($order->get_meta('_lunuf2cpayment_expires', true))) {
			echo "<div class='woocommerce-error'>"
			  . __('Order expired. If you have already paid order - communicate with Support.', LUNUPAYMENT_F2C_WC)
			  . "</div><br>";
		  } else {
			echo "<div class='woocommerce-error'>"
			  . __("This order's status is 'Cancelled' - it cannot be paid for. Please contact us if you need assistance.", LUNUPAYMENT_F2C_WC)
			  . "</div><br>";
		  }
		  return true;
		}

		$payment_status = $order->get_meta('_lunuf2cpayment_status', true);

		if ($payment_status === LUNUPAYMENT_F2C_STATUS_PAID || $order_status === 'processing') {
		  echo "<div class='woocommerce-message'>" . __("Payment received. Thank you!", LUNUPAYMENT_F2C_WC) . "</div><br>";
		  return true;
		}

		if ($payment_status === LUNUPAYMENT_F2C_STATUS_FAILED || $payment_status === LUNUPAYMENT_F2C_STATUS_EXPIRED) {
		  echo "<div class='woocommerce-error'>"
			. __('Payment failed or expired. Please try again or contact support.', LUNUPAYMENT_F2C_WC)
			. "</div><br>";
		  return true;
		}

		if ($payment_status === LUNUPAYMENT_F2C_STATUS_CANCELED) {
		  echo "<div class='woocommerce-error'>"
			. __("This order's status is 'Cancelled' - it cannot be paid for. Please contact us if you need assistance.", LUNUPAYMENT_F2C_WC)
			. "</div><br>";
		  return true;
		}

		if ($order_status === 'pending' || $payment_status === LUNUPAYMENT_F2C_STATUS_PENDING) {
		  echo "<div class='woocommerce-info'>"
			. __('Your payment is being processed. Please wait for confirmation.', LUNUPAYMENT_F2C_WC)
			. "</div><br>";
		  return true;
		}

		/* IFRAME WIDGET - commented out due to CSP frame-ancestors restriction on exchange.sandbox.lunupay.com
		if ($order_status === 'pending' || $payment_status === LUNUPAYMENT_F2C_STATUS_PENDING) {
		  $redirect_url = $order->get_meta('_lunuf2cpayment_redirect_url', true);

		  if (!empty($redirect_url)) {
			$widget_base_url = $this->getWidgetUrlEndpoint();
			echo "<script>
			  window.jQuery && jQuery(document).ready(function() {
				jQuery('.entry-title').text('" . esc_js(__('Pay Now', LUNUPAYMENT_F2C_WC)) . "');
				jQuery('.woocommerce-thankyou-order-received').remove();
			  });
			</script>
			<div id=\"payment-form\"></div><br><br>
			<script>
			(function(d, t) {
			  var n = d.getElementsByTagName(t)[0], s = d.createElement(t);
			  s.type = 'text/javascript';
			  s.charset = 'utf-8';
			  s.async = true;
			  s.src = '" . esc_url($widget_base_url) . "/iframe.js?t=' + 1 * new Date();
			  s.onload = function() {
				const widget = new window.Lunu.widgets.Replenishment({
				  baseUrl: '" . esc_js($redirect_url) . "',
				  params: {},
				  onClose() {
					widget.remove();
				  },
				});
			  };
			  n.parentNode.insertBefore(s, n);
			})(document, 'script');
			</script>";
		  } else {
			echo "<div class='woocommerce-info'>"
			  . __('Your payment is being processed. Please wait for confirmation.', LUNUPAYMENT_F2C_WC)
			  . "</div><br>";
		  }
		  return true;
		}
		*/

		if ($payment_status === LUNUPAYMENT_F2C_STATUS_AWAITING_CONFIRMATION) {
		  echo "<div class='woocommerce-info'>"
			. __('Payment awaiting blockchain confirmation. This may take a few minutes.', LUNUPAYMENT_F2C_WC)
			. "</div><br>";
		  return true;
		}

		return true;
	  }


	  // Lunu Cryptocurrencies Gateway - Instant Payment Notification
	  private function map_f2c_state($state) {
		$state = strtolower($state);

		if ($state === 'accepted') {
		  return LUNUPAYMENT_F2C_STATUS_PAID;
		}
		if ($state === 'pending' || $state === 'confirmation') {
		  return LUNUPAYMENT_F2C_STATUS_PENDING;
		}
		return LUNUPAYMENT_F2C_STATUS_FAILED;
	  }

	  private function extract_order_id_from_callback($callback_data) {
		if (!empty($callback_data['merchant_trx_id'])) {
		  return $callback_data['merchant_trx_id'];
		}
		if (!empty($callback_data['customer_external_id'])) {
		  return str_replace('customer_', '', $callback_data['customer_external_id']);
		}
		return null;
	  }

	  public function lunupayment_callback($callback_data) {

		$this->lunu_log('Payment callback', array(
		  'payment' => $callback_data
		));

		if (empty($callback_data) || !is_array($callback_data)) {
		  return false;
		}

		$shop_order_id = $this->extract_order_id_from_callback($callback_data);
		if (empty($shop_order_id)) {
		  $this->lunu_log('Callback rejected: no order ID found');
		  return false;
		}

		$order = wc_get_order($shop_order_id);
		if (!$order) {
		  $this->lunu_log('Callback rejected: order not found', array('order_id' => $shop_order_id));
		  return false;
		}

		$order_status = $order->get_status();
		if ($order_status !== 'pending' && $order_status !== LUNUPAYMENT_F2C_WC_STATUS_AWAITING_CONFIRMATION_WP) {
		  $this->lunu_log('Callback skipped: order already processed', array(
			'order_id' => $shop_order_id,
			'order_status' => $order_status,
		  ));
		  return false;
		}

		$existing_status = $order->get_meta('_lunuf2cpayment_status', true);
		if ($existing_status === LUNUPAYMENT_F2C_STATUS_PAID) {
		  return false;
		}

		$f2c_state = !empty($callback_data['payment_lifeflow']) ? $callback_data['payment_lifeflow'] : '';
		$payment_status = $this->map_f2c_state($f2c_state);

		$this->lunu_log('Callback status mapping', array(
		  'f2c_state' => $f2c_state,
		  'mapped_status' => $payment_status,
		));

		$payment_amount = floatval(!empty($callback_data['fiat_amount']) ? $callback_data['fiat_amount'] : 0);
		$order_amount = floatval($order->get_total());

		if ($payment_amount > 0 && $payment_amount !== $order_amount) {
		  $this->lunu_log('Payment amount mismatch', array(
			'payment_amount' => $payment_amount,
			'order_amount' => $order_amount,
		  ));
		  return false;
		}

		$order->update_meta_data('_lunuf2cpayment_id', $callback_data['id']);
		$order->update_meta_data('_lunuf2cpayment_status', $payment_status);

		if ($payment_status === LUNUPAYMENT_F2C_STATUS_PAID) {
		  $order->payment_complete();
		  $order->set_status(LUNUPAYMENT_F2C_WC_STATUS_PROCESSING, 'Payment Received via Lunu Onramp service<br/>');
		  $order->save();
		  return true;

		} elseif ($payment_status === LUNUPAYMENT_F2C_STATUS_PENDING) {
		  $order->save();
		  return true;

		} else {
		  $order->set_status(LUNUPAYMENT_F2C_WC_STATUS_CANCELED, 'Payment failed via Lunu Onramp service<br/>');
		  $order->save();
		  return true;
		}
	  }


	  public function lunu_payment_init($params = array()) {
		$data = array(
		  'customer' => array(
			'external_id'  => 'customer_' . $params['order_id'],
			'ip_address'   => $this->get_client_ip(),
			'email'        => $params['email'],
			'phone_number' => !empty($params['phone_number']) ? $params['phone_number'] : null,
			'dob'          => !empty($params['dob']) ? $params['dob'] : null,
			'first_name'   => $params['first_name'],
			'last_name'    => $params['last_name'],
		  ),
		  'payment_method'        => 'visa',
		  'merchant_redirect_url' => $params['merchant_redirect_url'],
		  'merchant_trx_id'       => (string) $params['order_id'],
		  'crypto_code'           => 'USDC',
		  'fiat_code'             => (string) $params['currency'],
		  'fiat_amount'           => (string) $params['amount'],
		  'address' => array(
			'country_code'  => $params['country'],
			'state'         => !empty($params['state']) ? $params['state'] : null,
			'city'          => $params['city'],
			'postal_code'   => $params['postcode'],
			'address_line1' => $params['address_1'],
			'address_line2' => !empty($params['address_2']) ? $params['address_2'] : null,
		  ),
		);

		$url = $this->getUrlEndpoint() . '/f2c/v1/payments/init';

		$options = array(
		  'method' => 'POST',
		  'headers' => array(
			'Authorization' => 'Bearer ' . $this->get_api_secret(),
			'Content-Type'  => 'application/json',
		  ),
		  'body' => json_encode($data),
		);

		$WP_Http = new WP_Http();
		$response = $WP_Http->request($url, $options);

		$log_data = $data;
		$log_data['customer']['email'] = '*******';
		$this->lunu_log('Payment init', array(
		  'url'      => $url,
		  'request'  => $log_data,
		  'response' => $response,
		));

		if (!is_wp_error($response) && isset($response['body'])) {
		  $body = json_decode($response['body'], true);

		  if (is_array($body) && is_array($body['response'])) {
			return $body['response'];
		  } elseif (!empty($body['error']['message'])) {
			return array('error_message' => $body['error']['message']);
		  }
		} elseif (is_wp_error($response)) {
		  $error_message = $response->get_error_message();
		  if (!empty($error_message)) {
			return array('error_message' => $error_message);
		  }
		}

		return false;
	  }


	  function lunu_payment_check($payment_id) {
		$url = $this->getUrlEndpoint() . '/f2c/v1/payments/' . $payment_id;

		$WP_Http = new WP_Http();
		$response = $WP_Http->request($url, array(
		  'method' => 'GET',
		  'headers' => array(
			'Authorization' => 'Bearer ' . $this->get_api_secret(),
		  ),
		));

		if (!is_wp_error($response) && isset($response['body'])) {
		  $data = json_decode($response['body'], true);
		  if (is_array($data) && is_array($data['response'])) {
			return $data['response'];
		  }
		}
		$this->lunu_log('Payment checking error', array(
		  'url' => $url,
		  'response' => $response,
		));
		return null;
	  }


	}
	}

  add_action('woocommerce_after_checkout_billing_form', function($checkout) {
	woocommerce_form_field('billing_dob', array(
	  'type'        => 'date',
	  'label'       => __('Date of Birth', LUNUPAYMENT_F2C_WC),
	  'required'    => true,
	  'class'       => array('form-row-wide'),
	  'custom_attributes' => array(
		'max' => date('Y-m-d'),
	  ),
	), $checkout->get_value('billing_dob'));
  });

  add_action('woocommerce_checkout_process', function() {
	if (empty($_POST['billing_dob'])) {
	  wc_add_notice(__('Date of Birth is a required field.', LUNUPAYMENT_F2C_WC), 'error');
	}
  });

  add_action('woocommerce_checkout_update_order_meta', function($order_id) {
	if (!empty($_POST['billing_dob'])) {
	  $order = wc_get_order($order_id);
	  $order->update_meta_data('_billing_dob', sanitize_text_field($_POST['billing_dob']));
	  $order->save();
	}
  });


}

if (!function_exists('lunu_f2c_payment_callback_notify')) {
  function lunu_f2c_payment_callback_notify(WP_REST_Request $request) {
	global $woocommerce;

	$callback_data = $request->get_json_params();
	if (empty($callback_data)) {
	  $callback_data = json_decode($request->get_body(), true);
	}

	$gateways = $woocommerce->payment_gateways->payment_gateways();

	if (!isset($gateways['lunuf2cpayments'])) return;

	$success = $gateways['lunuf2cpayments']->lunupayment_callback(
		$callback_data
	);

	return array(
	  'status' => $success ? 'accepted' : 'rejected'
	);
  }
}

if (!function_exists('lunu_f2c_permission_callback')) {
  function lunu_f2c_permission_callback() {
	return true;
  }
}

add_action('rest_api_init', function() {
  register_rest_route('lunu/f2c-payment/v1', '/notify', array(
	'methods' => 'POST',
	'callback' => 'lunu_f2c_payment_callback_notify',
	'permission_callback' => 'lunu_f2c_permission_callback'
  ));
});
