<?php
/**
 * Plugin Name: WooCommerce Nimiq Pay Gateway
 * Plugin URI: https://nimiqpay.com
 * Description: Accept payments with Nimiq using nimiqpay.com checkout
 * Author: Brad Risse
 * Author URI: https://nimiqpay.com
 * Version: 1.0.0
 * Text Domain: wc-gateway-nimiqpay
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2018 Brad Risse
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-NimiqPay
 * @author    Brad Risse
 * @category  Admin
 * @copyright Copyright: (c) 2018 Brad Risse
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * Accept payments with Nimiq using nimiqpay.com checkout
 */
 
 
defined( 'ABSPATH' ) or exit;
// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

function toCents($amount) {
    return (int)floor($amount * 100);
}

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_nimiqpay_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_NimiqPay';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_nimiqpay_add_to_gateways' );
/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_nimiqpay_gateway_plugin_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nimiqpay_gateway' ) . '">' . __( 'Configure', 'wc-gateway-nimiqpay' ) . '</a>'
	);
	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_nimiqpay_gateway_plugin_links' );
/**
 * Offline Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Nimiq_Pay
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */
add_action( 'plugins_loaded', 'wc_nimiqpay_gateway_init', 11 );
function wc_nimiqpay_gateway_init() {
	class WC_Gateway_NimiqPay extends WC_Payment_Gateway {
		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'nimiqpay_gateway';
			$this->icon               = apply_filters('woocommerce_offline_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Nimiq Pay', 'wc-gateway-nimiqpay' );
            $this->method_description = __( 'Allows Nimiq payments.', 'wc-gateway-nimiqpay' );
            $this->order_button_text  = __( 'nimiqpay', 'wc-gateway-nimiqpay' );
		  
			// Load the settings.
			$this->init_form_fields();
            $this->init_settings();
            
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            
            add_filter('woocommerce_order_button_html',array( $this, 'display_nimiqpay_button_html' ),1);

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }
        
        public function display_nimiqpay_button_html($value) {

            $total = (string)round($this->get_order_total(), 2);
            $currency = strtolower(get_woocommerce_currency());

            ?>
                <style>
                    .nimiqpay-standard-button, .nimiqpay-nimiq-button {
                        display: none;
                    }

                    #nimiq-button {
                        text-align: center;
                    }

                    .woocommerce-checkout-review-order {
                        transition: height 0.5s ease-in-out;
                    }
                </style>

                <div class="nimiqpay-standard-button">
                    <?= $value ?>
                </div>

                <div class="nimiqpay-nimiq-button">
                    <div id="nimiq-button"></div>
                </div>


                <script>
                    (function() {
                        var $ = jQuery;

                        function loadNimiqPayScript(callback) {
                            if (window.nimiqpay) {
                                return callback(window.nimiqpay);
                            } 

                            if (window.nimiqpayLoadCallbacks) {
                                return window.nimiqpayLoadCallbacks.push(callback);
                            }

                            window.nimiqpayLoadCallbacks = [ callback ];

                            var script = document.createElement('script');
                            script.src = 'https://nimiqpay.com/button/nimiqpay.js';
                            script.onload = function() {
                                window.nimiqpayLoadCallbacks.forEach(function(cb) {
                                    cb(window.nimiqpay);
                                });

                                delete window.nimiqpayLoadCallbacks;
                            };

                            document.body.appendChild(script);
                        }

                        function isNimiqPayButtonRendered() {
                            return Boolean(document.querySelector('#nimiq-button').children.length);
                        }

                        function validateForm() {
                            var checkout_form = $( 'form.checkout' );

                            checkout_form.one('checkout_place_order', function(event) {
                                return false;
                            });

                            checkout_form.submit();

                            return new nimiqpay.Promise(function(resolve) {
                                setTimeout(resolve, 400);
                            }).then(function() {

                                if (document.querySelector('.woocommerce-invalid')) {
                                    checkout_form.submit();
                                    return false;
                                }

                                return true;
                            });
                        }

                        function renderNimiqPayButton() {

                            if (isNimiqPayButtonRendered()) {
                                return;
                            }

                            nimiqpay.Button.render({

                                style: {
                                    size: 'responsive'
                                },

                                payment: {
                                    storePublicKey: '<?= $this->settings['destination'] ?>',
                                    currency:    '<?= $currency ?>',
                                    amount:      '<?= $total ?>'
                                },

                                onClick: function() {
                                    return validateForm();
                                },

                                onPayment: function(data) {
                                    var checkout_form = $( 'form.checkout' );
                                    checkout_form.append('<input type="hidden" name="nimiqpay_token" value="' + data.token + '">');
                                    checkout_form.submit();
                                }

                            }, '#nimiq-button');
                        }

                        function toggleNimiqPayButton() {

                            var isNimiqPay = jQuery('.nimiqpay-standard-button input').val() === 'nimiqpay' || 
                                              jQuery('.nimiqpay-standard-button button').html() === 'nimiqpay' ||
                                              jQuery('.nimiqpay-standard-button button').val() === 'nimiqpay' ||
                                              jQuery('input[type=radio][name=payment_method]:checked').val() === 'nimiqpay_gateway';

                            if (isNimiqPay) {
                                if (!isNimiqPayButtonRendered()) {
                                    loadNimiqPayScript(function(nimiqpay) {
                                        renderNimiqPayButton();
                                    });
                                }

                                jQuery('.nimiqpay-standard-button').hide();
                                jQuery('.nimiqpay-nimiq-button').show();
                            } else {
                                jQuery('.nimiqpay-nimiq-button').hide();
                                jQuery('.nimiqpay-standard-button').show();
                            }
                        }

                        loadNimiqPayScript(function() {
                            setTimeout(function() {
                                renderNimiqPayButton();
                                toggleNimiqPayButton();
                            }, 500);
                        });

                        jQuery('body').on('click', function() {
                            setTimeout(function() {
                                toggleNimiqPayButton();
                            }, 10);
                        });

                        toggleNimiqPayButton();
                    })();
                </script>
            <?php
        }
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_nimiqpay_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-nimiqpay' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Nimiq Payments via nimiqpay.com', 'wc-gateway-nimiqpay' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-nimiqpay' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-nimiqpay' ),
					'default'     => __( 'Nimiq Payment', 'wc-gateway-nimiqpay' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-nimiqpay' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-nimiqpay' ),
					'default'     => __( 'Pay with Nimiq via nimiqpay.com.', 'wc-gateway-nimiqpay' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-nimiqpay' ),
					'type'        => 'textarea',
					'description' => __( 'Pay with Nimiq via nimiqpay.com.', 'wc-gateway-nimiqpay' ),
					'default'     => '',
					'desc_tip'    => true,
                ),
                
                'destination' => array(
					'title'       => __( 'Nimiq Pay Store Public Key', 'wc-gateway-nimiqpay' ),
					'type'        => 'text',
					'description' => __( 'Public key associated with Nimiq Pay', 'wc-gateway-nimiqpay' ),
					'default'     => __( '', 'wc-gateway-nimiqpay' ),
					'desc_tip'    => true,
				),
			) );
        }
        
		public function process_payment($order_id) {

            $order = wc_get_order( $order_id );

            $request       = wp_remote_get( esc_url_raw ( 'https://nimiqpay.com/api/session/' . $_POST['nimiqpay_token'] . '/verify' ) );
            $response_code = wp_remote_retrieve_response_code( $request );

            $total = (string)round($order->get_total(), 2);
            $currency = strtolower(get_woocommerce_currency());

            $error = '';

            if ( 200 != $response_code ) {
                    $error = ('Incorrect response code from API: ' . esc_url_raw ( 'https://nimiqpay.com/api/session/' . $_POST['nimiqpay_token'] . '/verify' ) . ' (' . $response_code . ')');
            } 
            else {
                $transaction = json_decode( wp_remote_retrieve_body( $request ) );

                if ($transaction->destination !== $this->settings['destination']) {
                    $error = ('Incorrect destination: ' . $transaction->destination . ' expected ' . $this->settings['destination']);
                } else if ($transaction->amount !== $total) {
                    $error = ('Incorrect amount: ' . var_export($transaction->amount, true) . ' expected ' . var_export($total, true));
                } else if ($transaction->currency !== $currency) {
                    $error = ('Incorrect currency: ' . $transaction->currency . ' expected ' . $currency);
                }
            }

            if ($error) {
                $order->update_status('failed', $error);

                wc_add_notice($error, 'error'); 

                return array(
                    'result' => 'failure'
                );
            }
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status('processing');
			
			// Reduce stock levels
			$order->reduce_order_stock();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
	
  } // end \WC_Gateway_NimiqPay class
}