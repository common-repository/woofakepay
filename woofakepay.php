<?php
/**
 * Plugin Name: WooFakePay
 * Plugin URI: http://changeset.hr/portfolio/woofakepay-ipn-payment-gateway-for-developers
 * Description: Test Payment Gateway for WooCommerce That Always Pays Off
 * Version: 1.0
 * Author: Fran Hrzenjak
 * Author URI: http://changeset.hr/
 * License: GPL2
 */


add_action('plugins_loaded', 'woocommerce_tocka_fakepay_init', 0);

function woocommerce_tocka_fakepay_init() {

    if(!class_exists('WC_Payment_Gateway')) return;

    class WC_Tocka_FakePay extends WC_Payment_Gateway {
        public function __construct( $fake=FALSE ){
            $this -> id = 'fakepay';
            $this -> method_title = 'FakePay';
            $this -> has_fields = FALSE;

            $this -> init_form_fields();
            $this -> init_settings();

            $this -> title = $this -> settings['title'];
            $this -> description = $this -> settings['description'];
            $this -> fake_url = str_replace( 'https:', 'http:', add_query_arg( array( 'wc-api' => 'WC_Tocka_FakePay_FakeRemote' ), home_url( '/' ) ) );

            add_action( 'init', array( $this, 'check_fakepay_response' ) );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_receipt_fakepay', array( $this, 'receipt_page' ) );

            // Payment listener/API hook
            add_action( 'woocommerce_api_wc_tocka_fakepay', array( $this, 'check_ipn_response' ) );
            new WC_Tocka_FakePay_FakeRemote();
            // $callback_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Tocka_FakePay', home_url( '/' ) ) );
            // $callback_url = "http://www.example.com/?wc-api=WC_Tocka_FakePay";
            // $callback_url = "http://www.example.com/wc-api/WC_Tocka_FakePay/";  // alternative

            if ( !$this->is_valid_for_use() ) $this->enabled = false;
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '<' ) ) $this->enabled = false;
        }

        /**
         * Check if this gateway should be forced disabled for some reason
         *
         * @access public
         * @return bool
         */
        function is_valid_for_use() {
            return true;
        }


        /**
         * Initialise Settings Form Fields
         *
         * Add an array of fields to be displayed
         * on the gateway's settings screen.
         *
         * @since 1.0.0
         * @access public
         * @return string
         */
        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woofakepay'),
                    'type' => 'checkbox',
                    'label' => __('Enable FakePay Payment Module.', 'woofakepay'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'woofakepay'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woofakepay'),
                    'default' => __('FakePay', 'woofakepay')),
                'description' => array(
                    'title' => __('Description:', 'woofakepay'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woofakepay'),
                    'default' => __('Fake IPN Payment Gateway. It always pays off...', 'woofakepay')),
            );
        }


        /**
         * Admin Options
         *
         * Setup the gateway settings screen.
         * Override this in your gateway.
         *
         * @since 1.0.0
         * @access public
         * @return void
         */
        public function admin_options(){
            echo '<h3>'.__('FakePay Payment Gateway', 'woofakepay').'</h3>';
            echo '<p>'.__('FakePay service description FakePay service description FakePay service description.').'</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this -> generate_settings_html();
            echo '</table>';

        }


        /**
         * Process Payment
         *
         * @access public
         * @param int $order_id Id of the order that's going to be processed
         * @return array
         */
        function process_payment($order_id){
            global $woocommerce;
            $order = new WC_Order( $order_id );
            $order->payment_complete();
            //$woocommerce->cart->empty_cart();  // Remove cart
            $woocommerce->session->fakepay_order_id = $order_id;
            return array(
                'result' => 'success',
                'redirect' => $this->fake_url,
            );
        }


        /**
         * Check IPN Response
         *
         * Check for valid FakePay server callback
         *
         * @since 1.0.0
         * @access public
         * @return void
         **/
        function check_ipn_response() {
            global $woocommerce;
            $order_id = $woocommerce->session->fakepay_order_id;  //@TODO pass order_id via $_GET
            $order = new WC_Order( $order_id );
            $order->add_order_note( __( 'FakePay IPN payment completed', 'woocommerce' ) );
            $order->payment_complete();
            wp_redirect( get_permalink( woocommerce_get_page_id( 'thanks' ) ) );
        }
    }


    class WC_Tocka_FakePay_FakeRemote {
        public function __construct() {
            $this -> ipn_url = str_replace( 'https:', 'http:', add_query_arg( array( 'wc-api' => 'WC_Tocka_FakePay' ), home_url( '/' ) ) );
            add_action( 'woocommerce_api_wc_tocka_fakepay_fakeremote', array( $this, 'fake_pg_page' ) );

        }

        public function fake_pg_page() {
            ob_end_clean();
            get_header();
            ?>
                <div style="text-align: center;">
                    <h1>WooFakePay Payment Gateway.</h1>
                    <h2>Behold and go away!</h2>
                    <p>Payment has been made, yeah right, and you are being redirected back to the store, please wait...</p>
                </div>
                <script type="text/javascript">
                    jQuery(function($){
                        setTimeout(function(){
                            window.location = '<?php echo $this->ipn_url; ?>';
                        }, 2000);
                    });
                </script>
            <?php
            get_footer();
            ob_start();
        }
    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_tocka_fakepay_gateway( $methods ) {
        $methods[] = 'WC_Tocka_FakePay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_tocka_fakepay_gateway' );
}
