<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WC_LITNOW_PAYMENT_API')) {
    class WC_LITNOW_PAYMENT_API
    {
        private static $instance;

        public static function instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
                self::$instance->hooks();
            }

            return self::$instance;
        }

        public function hooks()
        {

            add_action('rest_api_init', array($this, 'wc_rest_payment_endpoints'));
        }

        public function wc_rest_payment_endpoints()
        {
            /**
             * Handle Payment Method request.
             */
            register_rest_route('wc/v1', 'litnow', array(
                'methods' => 'POST',
                'callback' => array($this, 'wc_rest_payment_endpoint_handler'),
                'permission_callback' => function() { return 'true'; }

            ));
        }

        public function wc_rest_payment_endpoint_handler($request = null)
        {
            $response = array();
            $parameters = $request->get_params();
            $signature = sanitize_text_field($parameters['signature']);
            $order_id = sanitize_text_field($parameters['orderId']);
            $errorCode = intval($parameters['errorCode']);

            $error = new WP_Error();
            $litnoSettings = get_option('woocommerce_litnow_settings');

            if (empty($signature)) {
                $error->add(400, __("Signature 'signature' is required.", 'wc-rest-payment'), array('status' => 400));
                return $error;
            } else if (!isset($litnowSettings['private_key']) || $signature !== $litnowSettings['private_key']) {
                $error->add(400, __("Signature is missing or not matching", 'wc-rest-payment'), array('status' => 400));
                return $error;
            }

            if (empty($order_id)) {
                $error->add(401, __("Order ID 'order_id' is required.", 'wc-rest-payment'), array('status' => 400));
                return $error;
            } else if (wc_get_order($order_id) == false) {
                $error->add(402, __("Order ID 'order_id' is invalid. Order does not exist.", 'wc-rest-payment'), array('status' => 400));
                return $error;
            } else if (wc_get_order($order_id)->get_status() !== 'pending') {
                $error->add(403, __("Order status is NOT 'pending', meaning order had already received payment. Multiple payment to the same order is not allowed. ", 'wc-rest-payment'), array('status' => 400));
                return $error;
            }

            if (empty($signature)) {
                $error->add(400, __("Signature 'signature' is required.", 'wc-rest-payment'), array('status' => 400));
                return $error;
            }


            if ($errorCode === 0) {
                $response['code'] = 200;
                $response['message'] = __("Your Payment was Successful", "wc-rest-payment");

                $order = wc_get_order($order_id);
                $order = new WC_Order($order_id);
                $order->update_status('processing');
                $order->save();
                $order->payment_complete($order);
            } else {
                $response['code'] = 405;
                $response['message'] = __("An error occured please try again", "wc-rest-payment");
            }

            return new WP_REST_Response($response, 123);
        }


    }

    $WC_LITNOW_PAYMENT_API = new WC_LITNOW_PAYMENT_API();
    $WC_LITNOW_PAYMENT_API->instance();

} // End if class_exists check