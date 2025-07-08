<?php
/*
Plugin Name: Bitcoin Crowdfunding
Description: Easy Bitcoin crowdfundings on a WordPress website
Version: 1.0
Author: Coinsnap
*/

if (!defined('ABSPATH')) {
    exit;
}

// Plugin settings
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-crowdfunding-list.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-crowdfunding-public-donors.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-crowdfunding-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-crowdfunding-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-crowdfunding-webhooks.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-crowdfunding-shoutouts-list.php';

register_activation_hook(__FILE__, 'coinsnap_bitcoin_crowdfunding_create_crowdfunding_payments_table');
register_deactivation_hook(__FILE__, 'coinsnap_bitcoin_crowdfunding_deactivate');

// Permalink structure fix
add_filter('rest_url_prefix', 'coinsnap_bitcoin_crowdfunding_rest_url_prefix');

function coinsnap_bitcoin_crowdfunding_rest_url_prefix($prefix)
{
    return 'wp-json';
}

function coinsnap_bitcoin_crowdfunding_deactivate()
{
    flush_rewrite_rules();
}

function coinsnap_bitcoin_crowdfunding_create_crowdfunding_payments_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'crowdfunding_payments';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payment_id VARCHAR(255) NOT NULL,
        crowdfunding_id VARCHAR(255) NOT NULL,
        amount INT(20) NOT NULL,
        name VARCHAR(255),
        message VARCHAR(511),
        status VARCHAR(50) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

class Coinsnap_Bitcoin_Crowdfunding
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'coinsnap_bitcoin_crowdfunding_enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'coinsnap_bitcoin_crowdfunding_enqueue_admin_styles']);
        add_action('wp_ajax_coinsnap_bitcoin_crowdfunding_btcpay_apiurl_handler', [$this, 'btcpayApiUrlHandler']);
    }
    
    function btcpayApiUrlHandler(){
            $_nonce = filter_input(INPUT_POST,'apiNonce',FILTER_SANITIZE_STRING);
            if ( !wp_verify_nonce( $_nonce, 'coinsnap-ajax-nonce' ) ) {
                wp_die('Unauthorized!', '', ['response' => 401]);
            }

            if ( current_user_can( 'manage_options' ) ) {
                $host = filter_var(filter_input(INPUT_POST,'host',FILTER_SANITIZE_STRING), FILTER_VALIDATE_URL);

                if ($host === false || (substr( $host, 0, 7 ) !== "http://" && substr( $host, 0, 8 ) !== "https://")) {
                    wp_send_json_error("Error validating BTCPayServer URL.");
                }

                $permissions = array_merge([
                    'btcpay.store.canviewinvoices',
                    'btcpay.store.cancreateinvoice',
                    'btcpay.store.canviewstoresettings',
                    'btcpay.store.canmodifyinvoices'
                ],
                [
                    'btcpay.store.cancreatenonapprovedpullpayments',
                    'btcpay.store.webhooks.canmodifywebhooks',
                ]);

                try {
                    // Create the redirect url to BTCPay instance.
                    $url = $this->getAuthorizeUrl(
                        $host,
                        $permissions,
                        'CoinsnapBitcoinCrowdfunding',
                        true,
                        true,
                        home_url('?crowdfunding-btcpay-settings-callback'),
                        null
                    );

                    // Store the host to options before we leave the site.
                    coinsnap_settings_update('coinsnap_bitcoin_crowdfunding_options',['btcpay_url' => $host]);

                    // Return the redirect url.
                    wp_send_json_success(['url' => $url]);
                }

                catch (\Throwable $e) {

                }
            }
            wp_send_json_error("Error processing Ajax request.");
    }

    function coinsnap_bitcoin_crowdfunding_enqueue_scripts()
    {
        wp_enqueue_style('coinsnap-bitcoin-crowdfunding-style', plugin_dir_url(__FILE__) . 'styles/style.css', [], '1.0.0');
        wp_enqueue_style('coinsnap-bitcoin-crowdfunding-style-wide', plugin_dir_url(__FILE__) . 'styles/style-wide.css', [], '1.0.0');
        wp_enqueue_style('coinsnap-bitcoin-crowdfunding-shoutouts', plugin_dir_url(__FILE__) . 'styles/shoutouts.css', [], '1.0.0');

        wp_enqueue_script('coinsnap-bitcoin-crowdfunding-script', plugin_dir_url(__FILE__) . 'js/crowdfunding.js', ['jquery'], '1.0.0', true);
        $provider_defaults = [
            'provider' => 'coinsnap',
            'coinsnap_store_id' => '',
            'coinsnap_api_key' => '',
            'btcpay_store_id' => '',
            'btcpay_api_key' => '',
            'btcpay_url' => '',
        ];
        $provider_options = array_merge($provider_defaults, (array) get_option('coinsnap_bitcoin_crowdfunding_options', []));

        // Define defaults for forms options
        $forms_defaults = [
            'redirect_url' => home_url(),
        ];

        // Localize script for sharedData
        wp_enqueue_script('coinsnap-bitcoin-crowdfunding-shared-script', plugin_dir_url(__FILE__) . 'js/shared.js', ['jquery'], '1.0.0', true);
        wp_localize_script('coinsnap-bitcoin-crowdfunding-shared-script', 'sharedData', [
            'provider' => $provider_options['provider'],
            'coinsnapStoreId' => $provider_options['coinsnap_store_id'],
            'coinsnapApiKey' => $provider_options['coinsnap_api_key'],
            'btcpayStoreId' => $provider_options['btcpay_store_id'],
            'btcpayApiKey' => $provider_options['btcpay_api_key'],
            'btcpayUrl' => $provider_options['btcpay_url'],
            'redirectUrl' => $forms_defaults['redirect_url'],
            'nonce' => wp_create_nonce('wp_rest')
        ]);

        wp_enqueue_script('coinsnap-bitcoin-crowdfunding-popup-script', plugin_dir_url(__FILE__) . 'js/popup.js', ['jquery'], '1.0.0', true);
    }

    function coinsnap_bitcoin_crowdfunding_enqueue_admin_styles($hook)
    {
        if ($hook === 'bitcoin-crowdfunding_page_coinsnap-bitcoin-crowdfunding-donation-list') {
<<<<<<< Updated upstream
<<<<<<< Updated upstream
<<<<<<< Updated upstream
            wp_enqueue_style('coinsnap-bitcoin-crowdfunding-admin-style', plugin_dir_url(__FILE__) . 'styles/admin-style.css', [], '1.0.0');
        } else if ($hook === 'toplevel_page_coinsnap_bitcoin_crowdfunding') {
            wp_enqueue_style('coinsnap-bitcoin-crowdfunding-admin-style', plugin_dir_url(__FILE__) . 'styles/admin-style.css', [], '1.0.0');
            $options = get_option('coinsnap_bitcoin_crowdfunding_options', []);
            $ngrok_url = isset($options['ngrok_url']) ? $options['ngrok_url'] : '';
            wp_enqueue_script('coinsnap-bitcoin-crowdfunding-admin-script', plugin_dir_url(__FILE__) . 'js/admin.js', ['jquery'], '1.0.0', true);
            wp_localize_script('coinsnap-bitcoin-crowdfunding-admin-script', 'adminData', ['ngrokUrl' => $ngrok_url]);
=======
=======
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
            wp_enqueue_style('coinsnap-bitcoin-crowdfunding-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', [], COINSNAP_BITCOIN_CROWDFUNDING_VERSION);
        }
        else {
            wp_enqueue_style('coinsnap-bitcoin-crowdfunding-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', [], COINSNAP_BITCOIN_CROWDFUNDING_VERSION);
            $options = get_option('coinsnap_bitcoin_crowdfunding_options', []);
            wp_enqueue_script('coinsnap-bitcoin-crowdfunding-admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], COINSNAP_BITCOIN_CROWDFUNDING_VERSION, true);
            wp_localize_script('coinsnap-bitcoin-crowdfunding-admin-script', 'coinsnap_bitcoin_crowdfunding_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'  => wp_create_nonce( 'coinsnap-ajax-nonce' ),
            ));
            
>>>>>>> Stashed changes
        }
    }

    function coinsnap_bitcoin_crowdfunding_verify_nonce($nonce, $action)
    {
        if (!wp_verify_nonce($nonce, $action)) {
            wp_die(__('Security check failed', 'coinsnap_bitcoin_crowdfunding'));
        }
    }
    
    public function getAuthorizeUrl(string $baseUrl, array $permissions, ?string $applicationName, ?bool $strict, ?bool $selectiveStores, ?string $redirectToUrlAfterCreation, ?string $applicationIdentifier): string
    {
        $url = rtrim($baseUrl, '/') . '/api-keys/authorize';

        $params = [];
        $params['permissions'] = $permissions;
        $params['applicationName'] = $applicationName;
        $params['strict'] = $strict;
        $params['selectiveStores'] = $selectiveStores;
        $params['redirect'] = $redirectToUrlAfterCreation;
        $params['applicationIdentifier'] = $applicationIdentifier;

        // Take out NULL values
        $params = array_filter($params, function ($value) {
            return $value !== null;
        });

        $queryParams = [];

        foreach ($params as $param => $value) {
            if ($value === true) {
                $value = 'true';
            }
            if ($value === false) {
                $value = 'false';
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === true) {
                        $item = 'true';
                    }
                    if ($item === false) {
                        $item = 'false';
                    }
                    $queryParams[] = $param . '=' . urlencode((string)$item);
                }
            } else {
                $queryParams[] = $param . '=' . urlencode((string)$value);
            }
        }

        $queryParams = implode("&", $queryParams);
        $url .= '?' . $queryParams;
        return $url;
    }
}
new Coinsnap_Bitcoin_Crowdfunding();

add_action('init', function() {
    // Setting up and handling custom endpoint for api key redirect from BTCPay Server.
    add_rewrite_endpoint('crowdfunding-btcpay-settings-callback', EP_ROOT);
});

// To be able to use the endpoint without appended url segments we need to do this.
add_filter('request', function($vars) {
    if (isset($vars['crowdfunding-btcpay-settings-callback'])) {
        $vars['crowdfunding-btcpay-settings-callback'] = true;
    }
    return $vars;
});

if(!function_exists('coinsnap_settings_update')){
    function coinsnap_settings_update($option,$data){
        
        $form_data = get_option($option, []);
        
        foreach($data as $key => $value){
            $form_data[$key] = $value;
        }
        
        update_option($option,$form_data);
    }
}

if(!function_exists('remoteRequest')){
    function remoteRequest(string $method,string $url,array $headers = [],string $body = ''){

        $wpRemoteArgs = ['body' => $body, 'method' => $method, 'timeout' => 5, 'headers' => $headers];
        $response = wp_remote_request($url,$wpRemoteArgs);

        if(is_wp_error($response) ) {
            $errorMessage = $response->get_error_message();
            $errorCode = $response->get_error_code();
            return array('error' => ['code' => (int)esc_html($errorCode), 'message' => esc_html($errorMessage)]);
        }
        elseif(is_array($response)) {
            $status = $response['response']['code'];
            $responseHeaders = wp_remote_retrieve_headers($response)->getAll();
            $responseBody = json_decode($response['body'],true);
            return array('status' => $status, 'body' => $responseBody, 'headers' => $responseHeaders);
        }
    }
}

// Adding template redirect handling for crowdfunding-btcpay-settings-callback.
add_action( 'template_redirect', function(){
    
    global $wp_query;
            
    // Only continue on a crowdfunding-btcpay-settings-callback request.    
    if (!isset( $wp_query->query_vars['crowdfunding-btcpay-settings-callback'])) {
        return;
    }

    $CoinsnapBTCPaySettingsUrl = admin_url('/admin.php?page=coinsnap-bitcoin-crowdfunding');

            $rawData = file_get_contents('php://input');
            $form_data = get_option('coinsnap_bitcoin_crowdfunding_options', []);

            $btcpay_server_url = $form_data['btcpay_url'];
            $btcpay_api_key  = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $request_url = $btcpay_server_url.'/api/v1/stores';
            $request_headers = ['Accept' => 'application/json','Content-Type' => 'application/json','Authorization' => 'token '.$btcpay_api_key];
            $getstores = remoteRequest('GET',$request_url,$request_headers);
            
            if(!isset($getstores['error'])){
                if (count($getstores['body']) < 1) {
                    $messageAbort = __('Error on verifiying redirected API Key with stored BTCPay Server url. Aborting API wizard. Please try again or continue with manual setup.', 'coinsnap-bitcoin-crowdfunding');
                    //$notice->addNotice('error', $messageAbort);
                    wp_redirect($CoinsnapBTCPaySettingsUrl);
                }
            }
                        
            // Data does get submitted with url-encoded payload, so parse $_POST here.
            if (!empty($_POST) || wp_verify_nonce(filter_input(INPUT_POST,'wp_nonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS),'-1')) {
                $data['apiKey'] = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? null;
                $permissions = (isset($_POST['permissions']) && is_array($_POST['permissions']))? $_POST['permissions'] : null;
                if (isset($permissions)) {
                    foreach ($permissions as $key => $value) {
                        $data['permissions'][$key] = sanitize_text_field($permissions[$key] ?? null);
                    }
                }
            }
    
            if (isset($data['apiKey']) && isset($data['permissions'])) {

                $REQUIRED_PERMISSIONS = [
                    'btcpay.store.canviewinvoices',
                    'btcpay.store.cancreateinvoice',
                    'btcpay.store.canviewstoresettings',
                    'btcpay.store.canmodifyinvoices'
                ];
                $OPTIONAL_PERMISSIONS = [
                    'btcpay.store.cancreatenonapprovedpullpayments',
                    'btcpay.store.webhooks.canmodifywebhooks',
                ];
                
                $btcpay_server_permissions = $data['permissions'];
                
                $permissions = array_reduce($btcpay_server_permissions, static function (array $carry, string $permission) {
			return array_merge($carry, [explode(':', $permission)[0]]);
		}, []);

		// Remove optional permissions so that only required ones are left.
		$permissions = array_diff($permissions, $OPTIONAL_PERMISSIONS);

		$hasRequiredPermissions = (empty(array_merge(array_diff($REQUIRED_PERMISSIONS, $permissions), array_diff($permissions, $REQUIRED_PERMISSIONS))))? true : false;
                
                $hasSingleStore = true;
                $storeId = null;
		foreach ($btcpay_server_permissions as $perms) {
                    if (2 !== count($exploded = explode(':', $perms))) { return false; }
                    if (null === ($receivedStoreId = $exploded[1])) { $hasSingleStore = false; }
                    if ($storeId === $receivedStoreId) { continue; }
                    if (null === $storeId) { $storeId = $receivedStoreId; continue; }
                    $hasSingleStore = false;
		}
                
                if ($hasSingleStore && $hasRequiredPermissions) {

                    coinsnap_settings_update('coinsnap_bitcoin_crowdfunding_options',
                        [
                        'btcpay_api_key' => $data['apiKey'],
                        'btcpay_store_id' => explode(':', $btcpay_server_permissions[0])[1],
                        'provider' => 'btcpay'
                        ]);
                    
                    wp_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
                else {
                    //$notice->addNotice('error', __('Please make sure you only select one store on the BTCPay API authorization page.', 'coinsnap-bitcoin-crowdfunding'));
                    wp_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
            }

    //$notice->addNotice('error', __('Error processing the data from Coinsnap. Please try again.', 'coinsnap-bitcoin-crowdfunding'));
    wp_redirect($CoinsnapBTCPaySettingsUrl);
    exit();
});
