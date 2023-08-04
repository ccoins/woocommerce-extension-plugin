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

            $this->domain = 'custom_payment';

            $this->id                 = 'custom';
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
                    'title'       => __( 'CCoins API Token', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'The API Token from CCoins', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
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

        public function payment_fields(){

            if ( $description = $this->get_description() ) {
                echo wpautop( wptexturize( $description ) );
            }

            if ( $this->instructions ) {
               $instructions = $this->instructions;
            }

            ?>
                <button id='sendRequestButton' style="background-color: #7ABF2E; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; display: inline-flex; align-items: center;">
                    <img style="width: 24px; height: 24px; margin-right: 10px;" src="https://ccoins-logos-public.s3.amazonaws.com/icon.png" alt="Icon">
                        <b>Pay with crypto</b>
                </button>
                <script>
                    jQuery(function($) {
                        function tableToJson() {
                            var table = document.querySelector('.woocommerce-checkout-review-order-table');
                            var data = '';
                            
                            var tbody = table.querySelector('tbody');
                            var rows = tbody.querySelectorAll('tr');
                            
                            rows.forEach(function(row) {
                                var product_name = row.querySelector('.product-name').textContent.trim();
                                var product_total = row.querySelector('.product-total').textContent.trim();

                                productLine = product_name.replaceAll('/t', '') + ': ' + product_total;
                                data += productLine + '\n';
            
                            });
                            
                            var tfoot = table.querySelector('tfoot');
                            var total_price = tfoot.querySelector('.order-total .woocommerce-Price-amount').textContent.trim();
                            
                            // Match the price pattern (digits with optional decimal point and more digits)
                            var pattern = /\d+(\.\d+)?/;

                            // Perform the regular expression match
                            var matches = total_price.match(pattern);

                            if (matches) {
                                total_price = parseFloat(matches[0]);
                            } else {
                                console.log("Price not found in the string.");
                            }

                            return {
                                'description': data,
                                'total_price': total_price
                            };
                        }

                        $('#sendRequestButton').on('click', function() {
                            var jsonData = tableToJson();
                            const body = {
                                first_name: document.getElementById('billing_first_name').value,
                                last_name: document.getElementById('billing_last_name').value,
                                email: document.getElementById('billing_email').value,
                                fiat_amount: jsonData['total_price'],
                                description: jsonData['description']
                            };

                            var auth_token = '<?php echo $instructions; ?>';
                            var headers =  { 'Authorization': 'Basic ' + auth_token };

                            // Send POST request with JSON data as body
                            $.ajax({
                                url: 'https://staging.ccoins.io/external/ecommerce/redirect',
                                type: 'POST',
                                contentType: 'application/json',
                                headers: headers,
                                data: JSON.stringify(body),
                                success: function(response) {
                                    // Handle success response
                                    window.open(response.redirect_url, '_blank');
                                },
                                error: function(error) {
                                    // Handle error response
                                    console.error('Error sending POST request:', error);
                                }
                            });
                        });
                });
                </script>
            <?php
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

            // Set order status
            // $order->update_status( $status, __( 'Checkout with custom payment. ', $this->domain ) );

            // or call the Payment complete
            // $order->payment_complete();

            // Reduce stock levels
            // $order->reduce_order_stock();

            // Remove cart
            // WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
            );
        }
    }
}

add_filter( 'woocommerce_payment_gateways', 'add_custom_gateway_class' );
function add_custom_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_Custom'; 
    return $methods;
}

add_action('woocommerce_checkout_process', 'process_custom_payment');
function process_custom_payment(){

    if($_POST['payment_method'] != 'custom')
        return;

    // if( !isset($_POST['mobile']) || empty($_POST['mobile']) )
    //     wc_add_notice( __( 'Please add your mobile number', $this->domain ), 'error' );


    // if( !isset($_POST['transaction']) || empty($_POST['transaction']) )
    //     wc_add_notice( __( 'Please add your transaction ID', $this->domain ), 'error' );

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