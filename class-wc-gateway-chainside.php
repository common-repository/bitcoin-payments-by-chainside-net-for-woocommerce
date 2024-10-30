<?php
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

/**
 * Plugin Name: Chainside Bitcoin Payments
 * Plugin URI: https://www.chainside.net/
 * Description: Accept Bitcoin payments easily in your WooCommerce store
 * Version: 1.0.0
 */
load_plugin_textdomain('chainside-gateway', false, dirname(plugin_basename(__FILE__)).'/languages/');
add_action('plugins_loaded', 'chainside_gateway_load', 0);
add_action('init', 'create_chainside_post_type');

function create_chainside_post_type()
{
    register_post_type('chainside_payment',
        array(
            'labels' => array(
                'name' => __('Chainside Bitcoin Payments', 'chainside_gateway'),
                'singular_name' => __('Chainside Bitcoin Payments', 'chainside_gateway')
            ),
            'public' => true,
            'has_archive' => false,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_in_menu' => false,
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => false,
            'show_in_rest' => false,
            'hierarchical' => false,
            'supports' => array('title'),
        )
    );
    flush_rewrite_rules();
}

function chainside_gateway_load()
{
    if (!class_exists('WC_Payment_Gateway')) {
        // oops!
        return;
    }

    /**
     * Add the gateway to WooCommerce.
     */
    add_filter('woocommerce_payment_gateways', 'woocommerce_chainside_add_gateway');

    function woocommerce_chainside_add_gateway($methods)
    {
        define('WC_CHAINSIDE_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
        if (!in_array('WC_Gateway_Chainside', $methods, true)) {
            $methods[] = WC_Gateway_Chainside::get_instance();
        }

        return $methods;
    }

    /**
     * Chainside Bitcoin Payments
     *
     * @class          WC_Gateway_Chainside
     * @extends        WC_Payment_Gateway
     * @version        1.0
     * @package        WooCommerce/Classes/Payment
     */
    class WC_Gateway_Chainside extends WC_Payment_Gateway
    {
        const API_DOMAIN = 'https://api.webpos.chainside.net';
        const API_DOMAIN_TEST = 'https://api.sandbox.webpos.chainside.net';

        /**
         * @var WC_Gateway_Chainside The reference the *Singleton* instance of this class
         */
        private static $instance;

        public static $log;

        /**
         * Returns the *Singleton* instance of this class.
         *
         * @return WC_Gateway_Chainside The *Singleton* instance.
         */
        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Private clone method to prevent cloning of the instance of the
         * *Singleton* instance.
         *
         * @return void
         */
        private function __clone()
        {
        }

        /**
         * Private unserialize method to prevent unserializing of the *Singleton*
         * instance.
         *
         * @return void
         */
        private function __wakeup()
        {
        }

        protected function __construct()
        {
            $this->id = 'chainside';
            $this->enabled = $this->get_option('enabled');
            $this->has_fields = false;
            $this->method_title = __('Chainside Bitcoin Payments', 'chainside_gateway');
            $method_description =
                __('Accept Bitcoin payments easily in your Magento store. To start configuring the plugin, first go to business.chainside.net and create a webPOS.', 'chainside_gateway') . '<br><br><span>' .
                __('Please set the callback URLS in your webPOS as follows:', 'chainside_gateway') . '<br>' .
                __('Destination URL for confirmation pending need to be set like', 'chainside_gateway') . '<b><i>https://domain.com/webpos/pending/</i></b><br>' .
                __('Destination URL in case of cancellation need to be set like', 'chainside_gateway') . '<b><i>https://domain.com/webpos/cancel/</i></b><br>' .
                __('URL where callbacks will be sent need to be set like', 'chainside_gateway'). '<b><i>https://domain.com/webpos/send/</i></b></span>';

            $this->method_description = __('Accept Bitcoin payments easily in your WooCommerce store', 'chainside-gateway');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            add_action('admin_notices', array($this, 'process_admin_notices'));
            add_action('plugins_loaded', array($this, 'init'));
        }

        public function init()
        {
            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            // Actions
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'thankyou_page'));
            add_filter('woocommerce_thankyou_order_received_text', array($this, 'order_received_text'), 10, 2);

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
            add_filter('plugin_action_links_chainside-crypto-payment-gateway-for-woocommerce/class-wc-gateway-chainside.php', array($this, 'plugin_action_links'));

            // Payment listener/API hook
            add_action('woocommerce_api_wc_gateway_chainside', array($this, 'check_ipn_response'));

            //Disable gateway if chainside are disabled
            add_filter('woocommerce_available_payment_gateways', array($this, 'available_gateways'));

            self::plugin_activation();
        }

        public function get_icon()
        {
            $icons = $this->payment_icons();

            $icons_str = '';
            if ($this->get_option('show_logo') == 'yes') {
                $icons_str .= isset($icons['chainside']) ? $icons['chainside'] : '';
            }

            return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
        }

        public function payment_icons()
        {
            return apply_filters(
                'wc_chainside_payment_icons',
                array(
                    'chainside' => '<img src="' . WC_CHAINSIDE_PLUGIN_URL . '/assets/images/32_bitcoin@1x.png" class="chainside-icon" alt="'.__('Chainside Bitcoin Payments', 'chainside-gateway') . '" />',
                )
            );
        }

        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields()
        {
            $this->form_fields = array(

                'info' => array(
                    'title' => '',
                    'type' => 'hidden',
                    'description' =>
                        __('Accept Bitcoin payments easily in your Magento store. To start configuring the plugin, first go to business.chainside.net and create a webPOS.', 'chainside-gateway') . '<br><br><span>' .
                        __('Please set the callback URLS in your webPOS as follows:', 'chainside-gateway') . '<br>' .
                        __('Destination URL for confirmation pending need to be set like', 'chainside-gateway') . '<b><i>https://domain.com/webpos/pending/</i></b><br>' .
                        __('Destination URL in case of cancellation need to be set like', 'chainside-gateway') . '<b><i>https://domain.com/webpos/cancel/</i></b><br>' .
                        __('URL where callbacks will be sent need to be set like', 'chainside-gateway'). '<b><i>https://domain.com/webpos/send/</i></b></span>',
                    'default' => '1'
                ),

                'enabled' => array(
                    'title' => __('Enable/Disable', 'chainside-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable Bitcoin Payments', 'chainside-gateway'),
                    'default' => 'yes'
                ),

                'title' => array(
                    'title' => __('Title', 'chainside-gateway'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'chainside-gateway'),
                    'default' => __('Bitcoin Payments by Chainside', 'chainside-gateway'),
                    'desc_tip' => true,
                ),

                'description' => array(
                    'title' => __('Description', 'chainside-gateway'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'chainside-gateway'),
                    'default' => __('Pay with Bitcoin', 'chainside-gateway')
                ),
                'show_logo' => array(
                    'title' => __('Show Bitcoin logo on frontend', 'chainside-gateway'),
                    'type' => 'checkbox',
                    'default' => 'yes'
                ),

                'api_public' => array(
                    'title' => __('Client ID', 'chainside-gateway'),
                    'type' => 'text',
                    'description' => __('You can find the Client ID in the details of your webPOS', 'chainside-gateway'),
                ),

                'api_secret' => array(
                    'title' => __('Secret', 'chainside-gateway'),
                    'type' => 'password',
                    'description' => __('You can find the Secret in the details of your webPOS', 'chainside-gateway'),
                ),

                'confirmation_requests' => array(
                    'title' => __('Confirmation requests', 'chainside-gateway'),
                    'type' => 'select',
                    'options' => array(
                        '1' => __('1 confirmation (medium security level, high speed)', 'chainside-gateway'),
                        '3' => __('3 confirmation (high security level, average speed)', 'chainside-gateway'),
                        '6' => __('6 confirmation (maximum security level, low speed)', 'chainside-gateway'),
                    ),
                ),

                'sandbox' => array(
                    'title' => __('Sandbox Mode', 'chainside-gateway'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
            );

        }

        function thankyou_page($order_id)
        {
            return '';
        }

        function order_received_text($str, $order_id)
        {
            $order = wc_get_order($order_id);
            $status = $order->get_status();
            if ( isset( $_GET['?payment_id'] ) ) {
                $payment_id = wc_clean( wp_unslash( $_GET['?payment_id'] ) ); // WPCS: input var ok, CSRF ok.
                $order->set_transaction_id($payment_id);
                $order->save();
            }
            /** @noinspection SuspiciousAssignmentsInspection */
            $str = '';

            if ($status == 'pending') {
                $str .= '<p>' . __('Waiting for payment confirmation.', 'chainside-gateway') . '</p>';
                $str .= '<p>' . __('Once your payment is confirmed, your order will be processed automatically.', 'chainside-gateway') . '</p>';
            }

            return $str;
        }

        /**
         * Adds plugin action links
         *
         */
        public function plugin_action_links($links)
        {
            $setting_link = $this->get_setting_link();

            $plugin_links = array(
                '<a href="' . esc_url($setting_link) . '">' . __('Settings', 'chainside-gateway') . '</a>',
                '<a href="' . esc_url("https://www.chainside.net/") . '" target="_blank">' . __('Support', 'chainside-gateway') . '</a>',
            );

            return array_merge($plugin_links, $links);
        }

        /**
         * Get setting link.
         * @return string Setting link
         */
        public function get_setting_link()
        {
            $use_id_as_section = function_exists('WC') ? version_compare(WC()->version, '2.6', '>=') : false;

            $section_slug = $use_id_as_section ? 'chainside' : strtolower('WC_Gateway_Chainside');

            return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $section_slug);
        }

        public function available_gateways($available_gateways)
        {
            if (isset($available_gateways['chainside']) && !$this->is_available()) {
                unset($available_gateways['chainside']);
            }
            return $available_gateways;
        }

        public function is_available()
        {
            $is_available = false;
            if (WC()->cart && 0 < $this->get_order_total()) {
                $is_available = ('yes' === $this->enabled);
            }
            return $is_available;
        }

        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $payment = $this->payment($order);

            if (isset($payment['redirect_url'])) {
                $redirect = $payment['redirect_url'];
            } elseif (isset($payment['message'])) {
                wc_add_notice('Payment error: ' . $payment['message'], 'error');
                return;
            } else {
                wc_add_notice('Payment error', 'error');
                self::log($payment);
                return;
            }
            return array(
                'result' => 'success',
                'redirect' => $redirect
            );

        }

        public static function plugin_activation()
        {
            $self = self::get_instance();

            $self->settings['payment_page_id'] = null;
            update_option($self->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $self->id, $self->settings));

            $page_id = $self->get_option('payment_page_id');

            if ($page_id && get_post_type($page_id) != 'chainside_payment') {
                wp_delete_post($page_id);
            }

            if (!$page_id) {
                add_action('init', function () use ($self) {
                    $guid = home_url('/chainside_payment/chainside');

                    $page_id = $self::get_post_id_from_guid($guid);
                    if (!$page_id) {
                        $page_data = array(
                            'post_status' => 'publish',
                            'post_type' => 'chainside_payment',
                            'post_title' => 'chainside',
                            'post_content' => '[chainside_payment_widget]',
                            'comment_status' => 'closed',
                            'guid' => $guid,
                        );
                        $page_id = wp_insert_post($page_data);
                    }

                    $self->settings['payment_page_id'] = $page_id;
                    update_option($self->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $self->id, $self->settings));
                });
            }
        }

        public static function plugin_deactivation()
        {
            $post_id = self::get_instance()->get_option('payment_page_id');
            if ($post_id) {
                wp_delete_post($post_id, true);
            }
        }

        public static function get_post_id_from_guid($guid)
        {
            global $wpdb;
            return $wpdb->get_var($wpdb->prepare("SELECT id FROM $wpdb->posts WHERE guid=%s", $guid));

        }

        public static function log($log)
        {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }

        protected function getAccessToken()
        {
            $accessToken = false;
            $domain = $this->getApiDomain();
            $route = '/token';
            $url = $domain . $route;
            $client_id = $this->get_option('api_public');
            $client_secret = $this->get_option('api_secret');

            $headers = array(
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Api-Version' => 'v1'
            );
            $body = array(
                "grant_type" => "client_credentials",
                "scope" => "*"
            );
            $args = array(
                'headers' => $headers,
                'method' => 'POST',
                'body' => wp_json_encode($body, 0, 512),
                'redirection' => 0,
                'compress' => true,
                'timeout' => 30,
            );

            $response = wp_remote_request($url, $args);
            $responseBody = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($responseBody['access_token'])) {
                $accessToken = $responseBody['access_token'];
            }
            return $accessToken;
        }

        protected function payment($order)
        {
            $store = get_site_url();
            $url = $this->getApiDomain();
            $route = '/payment-order';
            $urlCurl = $url . $route;
            $orderId = $order->get_id();
            $orderGrandTotal = $order->get_total();
            $orderGrandTotal = number_format($orderGrandTotal, 2, '.', '');
            $accessToken = $this->getAccessToken();
            $confirmationRequests = $this->get_option('confirmation_requests');
            $headers = array(
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Api-Version' => 'v1'
            );
            $callback_url = $this->getCallBackUrl($orderId);
            $body = [
                'amount' => $orderGrandTotal,
                'cancel_url' => $store . '/checkout',
                'callback_url' => $store . $callback_url,
                'continue_url' => $order->get_checkout_order_received_url() . '&',
                'details' => 'details',
                'reference' => (string)$orderId,
                'required_confirmations' => (int)$confirmationRequests
            ];

            $args = array(
                'headers' => $headers,
                'method' => 'POST',
                'body' => wp_json_encode($body, 0, 512),
                'redirection' => 0,
                'compress' => true,
                'timeout' => 30,
            );

            $response = wp_remote_request($urlCurl, $args);
            $responseBody = json_decode(wp_remote_retrieve_body($response), true);

            return $responseBody;
        }

        protected function getCallBackUrl($orderId)
        {
            $baseCallback = '/wc-api/wc_gateway_chainside?token=';
            $token = $this->getToken();
            $this->saveTokenToOrder($orderId, $token);
            return $baseCallback . $token;
        }

        protected function getToken($length = 15)
        {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return MD5($randomString);
        }

        protected function saveTokenToOrder($orderId, $token)
        {
            update_post_meta($orderId, 'token', $token);
        }

        /**
         * Check for IPN Response
         * @access public
         * @return void
         */
        function check_ipn_response()
        {
            $res = false;
            $request_body = file_get_contents('php://input');
            $params = json_decode( wp_unslash( wc_clean( $request_body ) ), true);
            $validateParams = $this->validateParams($params);
            if (!$validateParams) {
                return;
            }
            $order_id = intval($params['object']['reference']);
            $order = wc_get_order($order_id);
            $event = $params['event'];

            if ($order) {
                /* payment.overpaid - overpaid */
                if ($event == 'payment.overpaid') {
                    $res = $this->paymentOverpaid($order, $params);
                }
                /* payment.overpaid - overpaid */

                /* payment.completed - completed */
                if ($event == 'payment.completed') {
                    $res = $this->paymentCompleted($order, $params);
                }
                /* payment.completed - completed */

                /* payment.expired - cancel */
                if ($event == 'payment.expired' || $event == 'payment.cancelled') {
                    $res = $this->paymentCancelled($order, $params);
                }
                /* payment.expired - cancel */
            }

            if ($res) {
                wp_send_json( array( 'code' => 200 ) );
            } else {
                wp_die('Chainside IPN Request Failure', 'Chainside IPN', array('response' => 500));
            }

        }

        /**
         * Get the transaction URL.
         *
         * @param WC_Order $order Order object.
         * @return string
         */
        public function get_transaction_url($order)
        {
            if ($this->get_option('sandbox') == 'yes') {
                $this->view_transaction_url = 'https://sandbox.checkout.chainside.net/%s';
            } else {
                $this->view_transaction_url = 'https://checkout.chainside.net/%s';
            }
            return parent::get_transaction_url($order);
        }

        public function process_admin_notices()
        {
            //check API keys
            if (!$this->get_option('api_public') || !$this->get_option('api_secret')) {
                echo '<div class="error"><p>' .
                    __('Please set your API keys in', 'chainside-gateway') .
                    sprintf('<a href="%1$s">'. __('Chainside Settings', 'chainside-gateway')  . '</a>', $this->get_setting_link()) . '</p></div>';
            }
        }

        protected function paymentCompleted($order, $params)
        {
            $orderTotal = (float)$order->get_total();
            $paid = (float)$params['object']['state']['paid']['fiat'];
            $unpaid = (float)$params['object']['state']['unpaid']['fiat'];
            $transactionId = $params['object']['uuid'];
            $reference = $params['object']['reference'];

            if (
                $reference == $order->get_id() &&
                $transactionId == $order->get_transaction_id() &&
                !$unpaid &&
                $paid == $orderTotal
            ) {
                $order->update_status('processing', __('Order paid', 'chainside-gateway'));
                return true;
            } else {
                return false;
            }
        }

        protected function paymentCancelled($order, $params)
        {
            $transactionId = $params['object']['uuid'];
            $reference = $params['object']['reference'];
            if (
                $reference == $order->get_id() &&
                $transactionId == $order->get_transaction_id()
            ) {
                $order->update_status('cancelled', __('Pay order cancelled', 'chainside-gateway'));
                return true;
            } else {
                return false;
            }
        }

        protected function paymentOverpaid($order, $params)
        {
            $status = isset($params['object']['state']['status']) ? $params['object']['state']['status'] : '';
            $fiat = isset($params['object']['state']['paid']['fiat']) ? $params['object']['state']['paid']['fiat'] : false;
            $crypto = isset($params['object']['state']['paid']['crypto']) ? $params['object']['state']['paid']['crypto'] : false;
            $btcAmount = isset($params['object']['btc_amount']) ? $params['object']['btc_amount'] : false;
            $currencyName = isset($params['object']['currency']['name']) ? $params['object']['currency']['name'] : false;

            if ($status == 'paid' && $fiat && $crypto && $btcAmount) {
                if ($crypto > $btcAmount) {
                    $amount = ($crypto - $btcAmount) / 100000000;

                    if ($amount > 0) {
                        $amount = sprintf('%.8f', $amount);

                        $btcAmount = sprintf('%.8f', $btcAmount / 100000000);

                        $note = sprintf(
                            __('Received "%s BTC" more than expected. Total of "%s BTC" or "%s %s".', 'chainside-gateway'),
                            $amount,
                            $btcAmount,
                            $fiat,
                            $currencyName);
                        $order->add_order_note($note, 1, false);
                    }
                }
            }
            return true;
        }

        protected function validateParams($params)
        {
            self::log($params);
            $valid = false;
            $paymentsStatus = [
                "payment.completed",
                "payment.dispute.start",
                "payment.overpaid",
                "payment.cancelled",
                "payment.dispute.end",
                "payment.expired",
                "payment.chargeback",
            ];

            $event = isset($params['event']) ? $params['event'] : '';
            $objectType = isset($params['object_type']) ? $params['object_type'] : '';
            $objectData = isset($params['object']) ? $params['object'] : array();
            $reference = isset($objectData['reference']) ? $objectData['reference'] : false;
            $token = get_post_meta( $reference, 'token', true );

            if (
                in_array($event, $paymentsStatus) &&
                $objectType == 'payment_order' &&
                $reference &&
                $token === wc_clean( wp_unslash( $_GET['token'] ) ) // WPCS: input var ok, CSRF ok.
            ) {
                $valid = true;
            }

            return $valid;
        }

        protected function getApiDomain()
        {
            if ($this->get_option('sandbox') == 'yes') {
                return self::API_DOMAIN_TEST;
            }
            return self::API_DOMAIN;
        }
    }

    class WC_Chainside extends WC_Gateway_Chainside
    {
        public function __construct()
        {
            _deprecated_function('WC_Chainside', '1.4', 'WC_Gateway_Chainside');
            parent::__construct();
        }
    }

    $GLOBALS['wc_chainside'] = WC_Gateway_Chainside::get_instance();

    register_activation_hook(__FILE__, function () {
    });
    register_deactivation_hook(__FILE__, array('WC_Gateway_Chainside', 'plugin_deactivation'));
}