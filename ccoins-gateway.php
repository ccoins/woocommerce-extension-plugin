<?php
/*
Plugin Name: CCoins Payment Gateway
Description: CCoins payment gateway example
Author: Bautista Blacker
Author URI: https://coins.io
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Custom Payment Gateway.
 *
 * Provides a Custom Payment Gateway, mainly for testing purposes.
 */
add_action('plugins_loaded', 'init_custom_gateway_class');
function init_custom_gateway_class(){

    class WC_Gateway_Custom extends WC_Payment_Gateway {

        public $domain;

        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->domain = 'ccoins_payment_gateway';

            $this->id                 = 'ccoins';
            $this->icon               = apply_filters('woocommerce_custom_gateway_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'CCoins', $this->domain );
            $this->method_description = __( 'CCoins payment gateway', $this->domain );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->order_status = $this->get_option( 'order_status', 'completed' );
            $this->instructions = $this->settings['instructions'];

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', $this->domain ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Custom Payment', $this->domain ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __( 'Title', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', $this->domain ),
                    'default'     => __( 'Custom Payment', $this->domain ),
                    'desc_tip'    => true,
                ),
                'order_status' => array(
                    'title'       => __( 'Order Status', $this->domain ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __( 'Choose whether status you wish after checkout.', $this->domain ),
                    'default'     => 'wc-completed',
                    'desc_tip'    => true,
                    'options'     => wc_get_order_statuses()
                ),
                'description' => array(
                    'title'       => __( 'Description', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', $this->domain ),
                    'default'     => __('Payment Information', $this->domain),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'api_key'        => array(
                    'title'       => __( 'API Key', 'ccoins' ),
                    'type'        => 'text',
                    'default'     => '',
                    'description' => sprintf(
                        // translators: Description field for API on settings page. Includes external link.
                        __(
                            'You can manage your API keys within the CCoins Web CCUSD Settings page, available here: %s',
                            'ccoins'
                        ),
                        esc_url( 'https://ccoins.io' )
                    ),
				)
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions )
                echo wpautop( wptexturize( $this->instructions ) );
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if ( $this->instructions && ! $sent_to_admin && 'custom' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {

            $order = wc_get_order( $order_id );

            $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;

            // Create description for charge based on order's products. Ex: 1 x Product1, 2 x Product2
            try {
                $order_items = array_map( function( $item ) {
                    return $item['quantity'] . ' x ' . $item['name'];
                }, $order->get_items() );

                $description = mb_substr( implode( ', ', $order_items ), 0, 200 );
            } catch ( Exception $e ) {
                $description = null;
            }

            $order_data = array(
                'order_id'  => $order->get_id(),
                'order_key' => $order->get_order_key(),
                'source' => 'woocommerce',
                'description' => $description,
                'return_url' => $this->get_return_url($order)
            );

            // Create a new charge.
            $metadata = array(
                'email' => $order->billing_email,
                'first_name' => $order->billing_first_name,
                'last_name' => $order->billing_last_name,
                'fiat_amount' => $order->get_total(),
                'data' => $order_data
            );

            $ch = curl_init();
            $url = 'https://staging.ccoins.io/external/ecommerce/redirect';
            $api_key = $this->get_option( 'api_key' );
            $jsonData = json_encode($metadata);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData),
                'Authorization: Barear ' . $api_key,
                'X-CSRF-Token: ' . $api_key
            ));

            $response = curl_exec($ch);

            if ($response) {
                $responseData = json_decode($response, true);
            
                if ($responseData) {
                    $redirect_url = $responseData['redirect_url'];
                } else {
                    echo 'Invalid JSON received';
                }
            } else {
                echo 'No response received';
            }

            // Set order status
            $order->update_status( $status, __( 'Checkout with custom payment. ', $this->domain ) );
            $order->save();

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $redirect_url
            );
        }
    }
}

add_filter( 'woocommerce_payment_gateways', 'add_custom_gateway_class' );
function add_custom_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_Custom'; 
    return $methods;
}

/**
 * Update the order meta with field value
 */
add_action( 'woocommerce_checkout_update_order_meta', 'custom_payment_update_order_meta' );
function custom_payment_update_order_meta( $order_id ) {

    if($_POST['payment_method'] != 'custom')
        return;

    update_post_meta( $order_id, 'mobile', $_POST['mobile'] );
    update_post_meta( $order_id, 'transaction', $_POST['transaction'] );
}

/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'custom_checkout_field_display_admin_order_meta', 10, 1 );
function custom_checkout_field_display_admin_order_meta($order){
    $method = get_post_meta( $order->id, '_payment_method', true );
    if($method != 'custom')
        return;

    $mobile = get_post_meta( $order->id, 'mobile', true );
    $transaction = get_post_meta( $order->id, 'transaction', true );

    echo '<p><strong>'.__( 'Mobile Number' ).':</strong> ' . $mobile . '</p>';
    echo '<p><strong>'.__( 'Transaction ID').':</strong> ' . $transaction . '</p>';
}