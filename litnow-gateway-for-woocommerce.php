<?php
/*
 * Plugin Name: LitNow Payment Gateway for WooCommerce
 * Description: Pay your order in multiples instalments
 * Author: litpay Techhub
 * Author URI: http://litnow.vn
 * Version: 1.0.0
 *
 */

/*
 * Check if woocomerce exists
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    /*
     * The class itself, please note that it is inside plugins_loaded action hook
     */
    add_filter('woocommerce_payment_gateways', 'LPFWC_add_gateway_class');
    add_action('plugins_loaded', 'LPFWC_init_gateway_class');

} else {

    exit('Vui lòng cài đặt và kích hoạt Plugin `WooCommerce` [Please Install and Activate Woocommerce Plugin. ]');
}

function LPFWC_add_gateway_class($gateways)
{
    $gateways[] = 'LPFWC_Litnow_Gateway'; // class name is here
    return $gateways;
}

require_once plugin_dir_path(__FILE__) . 'inc/wc-litnow-api-payment.php';
include plugin_dir_path(__FILE__) . 'inc/lang.php';

add_filter('woocommerce_gateway_icon', function ($icon, $id) {
    if ($id === 'litnow') {
        return '<img src="'.plugins_url('assets', __FILE__ ).'/lit_logo.png" style="height: 100%; ">';
    } else {
        return $icon;
    }
}, 10, 2);

function LPFWC_init_gateway_class()
{

    class LPFWC_Litnow_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {
            global $lang;
            $this->id = 'litnow'; // payment gateway plugin ID
            $this->icon = apply_filters('litnow_icon', plugins_url('assets/lit_logo.png', __FILE__)); //Icon to be displayed
            $this->has_fields = true;
            $this->method_title = 'LIT Gateway';
            $this->method_description = $lang['method_description']; // will be displayed on the options page

            // Method with all the options fields
            $this->LPFWCinit_form_fields();

            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->private_key = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('private_key');
            $this->publishable_key = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('publishable_key');

            $this->authorizedCurrency = ['VND'];


            $this->supports = array(
                'products'
            );

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            add_action('woocommerce_receipt_wc_receipt_litnow', array($this, 'wc_receipt_litnow'));  //displays message after visitor has sent the payment form

            $this->litnowDomain = 'https://api.litnow.vn';
            $this->authorizationBearer = 'eyJhbGciOiJIUzI1NiJ9.MTA.vruqwJGeyR8VaiBbn1XvI4kYP5BWWsLf5QScPTaagYI';
            $this->apiInitPurchase = $this->litnowDomain . '/me/Pr';
            $this->notifyUrl = get_option('siteurl') . '/wp-json/wc/v1/litnow';

            $this->litnowConfigUrl = $this->litnowDomain . '/me/co/' . $this->get_option('merchant_key').'/'. hash_hmac("sha256", $this->get_option('merchant_key'), $this->get_option('private_key'));
            $this->litnowPurchaseInstallmentDetailUrl = $this->litnowDomain . '/me/prin/' . $this->get_option('merchant_key');
            add_filter('woocommerce_order_button_html', array($this, 'LPFWC_custom_button_html'));
            $this->MAX_PURCHASE_AMOUNT = 0;
            $this->MIN_PURCHASE_AMOUNT = 0;
            $this->ADMIN_FEE_AMOUNT = 0;
            $this->ADMIN_FEE_TYPE = 0;
            $this->ADMIN_FEE_CONSTANT = null;

            if ($this->private_key != null) {
                $this->LPFWCgetPaymentConfig();
            }
        }

        public function LPFWC_custom_button_html($button_html)
        {
            global $lang;

            if ($this->LPFWCisPurchaseAmountValid()) {
                $button_html = str_replace('Place order', $lang['proceed'], $button_html);
            } else {

                $button_html = str_replace('Place order', $lang['proceed'], $button_html);
                $button_html = str_replace('>', 'disabled>', $button_html);
            }
            return $button_html;
        }

        protected function LPFWCgetPaymentConfig()
        {
            $args = array(
                'method' => 'GET',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept' => '*/*',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                    'Authorization' => $this->authorizationBearer
                )
            );


            $response = wp_remote_get($this->litnowConfigUrl, $args);
            if( $response['body'] !== null ){
                 $body = json_decode($response['body'], true);
  
            } else {
                return;
            }

            $this->MIN_PURCHASE_AMOUNT = isset($body['MIN_PURCHASE_AMOUNT']) ? $body['MIN_PURCHASE_AMOUNT'] : 0;
            $this->MAX_PURCHASE_AMOUNT = isset($body['MAX_PURCHASE_AMOUNT']) ? $body['MAX_PURCHASE_AMOUNT'] : 0;
            $this->ADMIN_FEE_AMOUNT = isset($body['ADMIN_FEE_AMOUNT']) ? $body['ADMIN_FEE_AMOUNT'] : 0;
            $this->ADMIN_FEE_TYPE = isset($body['ADMIN_FEE_TYPE']) ? $body['ADMIN_FEE_TYPE'] : 0;
            $this->ADMIN_FEE_CONSTANT = isset($body['ADMIN_FEE_CONSTANT']) ? $body['ADMIN_FEE_CONSTANT'] : 0;
        }

        protected function LPFWCgetPurchaseInstallmentDetail($amount)
        {
            $args = array(
                'method' => 'GET',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept' => '*/*',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                    'Authorization' => $this->authorizationBearer
                )
            );


            $response = wp_remote_get($this->litnowPurchaseInstallmentDetailUrl.'/'.$amount, $args);
            $body = json_decode($response['body'], true);

            return $body;
        }


        public function LPFWCisPurchaseAmountValid()
        {
            global $woocommerce;
            $cartTotal = (int)$woocommerce->cart->total;
            if ($cartTotal >= $this->MIN_PURCHASE_AMOUNT && $cartTotal <= $this->MAX_PURCHASE_AMOUNT) {
                return true;
            }

            return false;
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function LPFWCinit_form_fields()
        {
            global $lang;
            $this->form_fields = array(
                'enabled' => array(
                    'title' => $lang['enabled_title'],
                    'label' => $lang['enabled_label'],
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => $lang['title_title'],
                    'type' => 'text',
                    'description' => $lang['title_description'],
                    'default' => 'LIT',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' =>  $lang['description_title'],
                    'type' => 'textarea',
                    'description' =>  $lang['description_description'],
                    'default' => 'Mua Ngay. Trả chậm. Không lãi suất',
                ),
                'merchant_key' => array(
                    'title' => 'Live Merchant Key',
                    'type' => 'text'
                ),
                'private_key' => array(
                    'title' => 'Live Private Key',
                    'type' => 'password'
                )
            );
        }


        public function payment_fields()
        {
    
            global $lang;
            global $woocommerce;
            $currency = get_woocommerce_currency();
            if (!in_array($currency, $this->authorizedCurrency)) {
                echo '<div class="">
            
                    <p>'. esc_html($lang['currency_not_support']) .'</p>
                        
                </div>';
                return;
            }

            if ($this->LPFWCisPurchaseAmountValid()) {

                $purchaseInstallments = $this->LPFWCgetPurchaseInstallmentDetail(intval($woocommerce->cart->total));

                echo '<div class="">
             <table style="width:100%">
                  <tr>
                    <th>'. esc_html($lang['due_date']) .'</th>
                    <th>'. esc_html($lang['amount']) .'</th>
                  </tr>
                  ';

                foreach ($purchaseInstallments as $installment)
                {
                    $dueDate = new DateTime($installment['dueDate']);

                    echo '<tr>
                    <td>' . date_format($dueDate, get_option('date_format')) . '</td>
                    <th>' . wc_price($installment['amount']) . '</th> 
                  </tr>';
                }

                echo '</table>
                </div>';
                do_action('woocommerce_credit_card_form_end', $this->id);


            } else {
                echo '<div class="">
            
                    <p>'. esc_html($lang['the_total'])  .' ' . wc_price($this->MIN_PURCHASE_AMOUNT) . $lang['and'] . wc_price($this->MAX_PURCHASE_AMOUNT) . esc_html($lang['to_use']) .'</p>
                        
                </div>';
            }


        }

        /*
         * Custom CSS and JS
         */
        public function payment_scripts()
        {


        }

        /*
          * Fields validation
         */
        public function validate_fields()
        {


        }

        /*
         * Encrypt the body with the private key
         */
        protected function LPFWCgetSignature($body)
        {
            return hash_hmac("sha256", http_build_query($body), $this->get_option('private_key'));
        }

        /*
         * Processing the payments
         */
        public function process_payment($order_id)
        {
            global $lang;
            global $woocommerce;

            // Get order detail
            $order = wc_get_order($order_id);
            $body = array(
                'amount' => intval($woocommerce->cart->total),
                'deliveryDate' => date('Y-m-d'),
                'requestId' => uniqid(),
                'orderId' => $order_id,
                'notifyUrl' => $this->notifyUrl,
                'returnUrl' => $this->get_return_url($order),
                'clientId' => $this->get_option('merchant_key'),
                'description' => get_option('siteurl') . ' - OrderId : #' . $order_id
            );

            $body['signature'] = $this->LPFWCgetSignature($body);

            $body = wp_json_encode($body);


            $args = array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept' => '*/*',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                    'Authorization' => $this->authorizationBearer
                ),
                'body' => $body
            );


            $response = wp_remote_post($this->apiInitPurchase, $args);

            if (!is_wp_error($response)) {
                // Update status order => processing
                $order = new WC_Order($order_id);
                $order->update_status('processing');
                $body = json_decode($response['body'], true);
                if (wp_remote_retrieve_response_code($response) === 200 && isset($body['payUrl'])) {
                    return array(
                        'result' => 'success',
                        'redirect' => $body['payUrl']
                    );

                } else {
                    wc_add_notice($lang['try_again'], 'error');
                    return;
                }

            } else {
                wc_add_notice($lang['connection_error'], 'error');
                return;
            }

        }

    }
}
