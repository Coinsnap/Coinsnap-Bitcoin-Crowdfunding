<?php
if (!defined('ABSPATH')){ exit; }

class Coinsnap_Bitcoin_Crowdfunding_Webhooks {

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_webhook_endpoint']);
        add_action('rest_api_init', [$this, 'register_poll_check_endpoint']);
        add_action('rest_api_init', [$this, 'register_poll_results_endpoint']);
        add_action('rest_api_init', [$this, 'register_check_payment_endpoint']);
        add_action('rest_api_init', [$this, 'register_get_wh_secret_endpoint']);
    }

    public function register_poll_results_endpoint()
    {
        register_rest_route('crowdfunding/v1', '/voting_results/(?P<poll_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_results'],
            'permission_callback' => '__return_true', // TODO: Add proper permissions later
            'args' => [
                'poll_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ]
            ]
        ]);
    }

    public function register_get_wh_secret_endpoint()
    {
        register_rest_route('crowdfunding/v1', '/get-wh-secret', [
            'methods' => 'GET',
            'callback' => [$this, 'get_wh_secret'],
            'permission_callback' => '__return_true', // TODO: Add proper permissions later
        ]);
    }

    function get_wh_secret()
    {
        return $this->get_webhook_secret();
    }

    public function register_poll_check_endpoint()
    {
        register_rest_route('crowdfunding/v1', '/payment-status-long-poll/(?P<payment_id>[a-zA-Z0-9]+)/(?P<poll_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_payment_status_long_poll'],
            'permission_callback' => '__return_true', // TODO: Add proper permissions later
            'args' => [
                'payment_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return !empty($param);
                    }
                ],
                'poll_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ]
            ]
        ]);
    }

    public function register_check_payment_endpoint()
    {
        register_rest_route('crowdfunding/v1', '/check-payment-status/(?P<payment_id>[a-zA-Z0-9]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_check_payment_status'],
            'permission_callback' => '__return_true', // TODO: Add proper permissions later
            'args' => [
                'payment_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return !empty($param);
                    }
                ]
            ]
        ]);
    }

    function get_results($request)
    {
        $poll_id = $request['poll_id'];

        global $wpdb;
        $tab_name = "{$wpdb->prefix}crowdfunding_payments";
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE status = 'completed' AND poll_id = %d",
            $tab_name, $poll_id
        ));

        return ['results' => $results];
    }

    function get_payment_status_long_poll($request)
    {
        $payment_id = $request['payment_id'];
        $poll_id = $request['poll_id'];
        $start_time = time();
        $timeout = 5;

        while (time() - $start_time < $timeout) {
            global $wpdb;
            $table_name = $wpdb->prefix."crowdfunding_payments";
            
            $status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM %i WHERE payment_id = %s",$table_name,$payment_id
            ));
            if ($status === 'completed') {
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM %i WHERE status = 'completed' AND poll_id = %d",$table_name,$poll_id
                ));

                return ['status' => 'completed', 'results' => $results];
            }
            sleep(1);
        }
        // Timeout
        return ['status' => 'pending'];
    }

    function get_check_payment_status($request)
    {
        $payment_id = $request['payment_id'];
        $start_time = time();
        $timeout = 5;

        while (time() - $start_time < $timeout) {
            global $wpdb;
            $status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}crowdfunding_payments WHERE payment_id = %s",
                $payment_id
            ));
            if ($status === 'completed') {
                return ['status' => 'completed'];
            }
            sleep(1);
        }
        // Timeout
        return ['status' => 'pending'];
    }


    private function get_webhook_secret()
    {
        $option_name = 'coinsnap_webhook_secret';
        $secret = get_option($option_name);

        if (!$secret) {
            $secret = bin2hex(random_bytes(16));
            add_option($option_name, $secret, '', false);
        }

        return $secret;
    }

    public function register_webhook_endpoint()
    {
        register_rest_route('coinsnap-bitcoin-crowdfunding/v1', '/webhook', [
            'methods'  => ['POST'],
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => [$this, 'verify_webhook_request']
        ]);
    }

    function verify_webhook_request($request)
    {
        $secret = $this->get_webhook_secret();

        $coinsnap_sig = $request->get_header('X-Coinsnap-Sig');
        $btcpay_sig = $request->get_header('btcpay_sig');
        $signature_header = !empty($coinsnap_sig) ? $coinsnap_sig : $btcpay_sig;
        if (empty($signature_header)) {
            return false;
        }

        $payload = $request->get_body();

        $computed_signature = hash_hmac('sha256', $payload, $secret);
        $computed_signature = 'sha256=' . $computed_signature; // Prefix the computed_signature with 'sha256='
        if (!hash_equals($computed_signature, $signature_header)) {
            return false;
        }
        return true;
    }

    public function handle_webhook(WP_REST_Request $request)
    {
        $payload_data = $request->get_json_params();

        if (isset($payload_data['type']) && ($payload_data['type'] === 'Settled' || $payload_data['type'] === 'InvoiceSettled')) {
            //error_log('Webhook received: ' . json_encode($payload_data));
            // Crowdfunding
            if (isset($payload_data['metadata']['type']) && $payload_data['metadata']['type'] == "Bitcoin Crowdfunding") {
                global $wpdb;
                $invoiceId = $payload_data['invoiceId'];
                $crowdfunding_id = $payload_data['metadata']['crowdfunding_id'];
                $amount = $payload_data['metadata']['amount'];
                $name = $payload_data['metadata']['name'];
                $message = $payload_data['metadata']['message'];

                $wpdb->insert(
                    "{$wpdb->prefix}crowdfunding_payments",
                    [
                        'payment_id' => $invoiceId,
                        'crowdfunding_id' => $crowdfunding_id,
                        'amount' => $amount,
                        'status'     => 'completed',
                        'name' => !empty($name) ? $name : '',
                        'message' => !empty($message) ? $message : ''
                    ],
                    [
                        '%s',
                        '%s',
                        '%d',
                        '%s',
                        '%s',
                        '%s'
                    ]
                );
                // In page QR
            }
            if (isset($payload_data['metadata']['modal'])) {
                // Public donor
                if (isset($payload_data['metadata']['publicDonor']) && $payload_data['metadata']['publicDonor'] == '1') {

                    $name = $payload_data['metadata']['donorName'];
                    $email = $payload_data['metadata']['donorEmail'];
                    $address = $payload_data['metadata']['donorAddress'];
                    $message = $payload_data['metadata']['donorMessage'];
                    $opt_out = $payload_data['metadata']['donorOptOut'];
                    $custom = $payload_data['metadata']['donorCustom'];
                    $type = $payload_data['metadata']['formType'];
                    $amount = $payload_data['metadata']['amount'];
                    $shoutoutName = $payload_data['metadata']['name'];
                    $shoutoutMessage = $payload_data['metadata']['message'];
                    $message = !empty($message) ? $message : $shoutoutMessage;
                    if(empty($name)){
                        if(!empty($shoutoutName) || !empty($shoutoutMessage)){
                            $name = empty($shoutoutName) ? 'Shoutout from Anonymous' : 'Shoutout from ' . $shoutoutName;
                        } else {
                            $name = 'Anonymous';
                        }
                    }
                    
                    $opt_out_value = filter_var($opt_out, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
                    $post_data = array(
                        'post_title'    => $name,
                        'post_status'   => 'publish',
                        'post_type'     => 'coinsnap-cf-donors',
                        'post_content'  => $message
                    );

                    $post_id = wp_insert_post($post_data);

                    if ($post_id) {
                        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_donor_name', sanitize_text_field($name));
                        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_amount', sanitize_text_field($amount));
                        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_message', sanitize_text_field($message));
                        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_form_type', sanitize_text_field($type));
                        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_dont_show', $opt_out_value);
                        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_email', sanitize_email($email));
                        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_address', sanitize_text_field($address));
                        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_payment_id', sanitize_text_field($invoiceId));
                        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_custom_field', sanitize_text_field($custom));
                    }
                }
            }
        }

        return new WP_REST_Response('Webhook type not handled.', 200);
    }
}
new Coinsnap_Bitcoin_Crowdfunding_Webhooks();
