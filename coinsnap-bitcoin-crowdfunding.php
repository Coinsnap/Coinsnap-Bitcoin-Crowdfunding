<?php
/*
 * Plugin Name:        Coinsnap Bitcoin Crowdfunding
 * Plugin URI:         https://coinsnap.io/wp-plugins/wp-bitcoin-crowdfunding/
 * Description:        Easy Bitcoin crowdfundings on a WordPress website
 * Version:            1.2.0
 * Author:             Coinsnap
 * Author URI:         https://coinsnap.io/
 * Text Domain:        coinsnap-bitcoin-crowdfunding
 * Domain Path:         /languages
 * Tested up to:        7.0
 * License:             GPL2
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Network:             true
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'COINSNAP_BITCOIN_CROWDFUNDING_REFERRAL_CODE' ) ) {
    define( 'COINSNAP_BITCOIN_CROWDFUNDING_REFERRAL_CODE', 'D2SXIW' );
}
if ( ! defined( 'COINSNAP_BITCOIN_CROWDFUNDING_VERSION' ) ) {
    define( 'COINSNAP_BITCOIN_CROWDFUNDING_VERSION', '1.2.0' );
}
if ( ! defined( 'COINSNAP_BITCOIN_CROWDFUNDING_PHP_VERSION' ) ) {
    define( 'COINSNAP_BITCOIN_CROWDFUNDING_PHP_VERSION', '8.0' );
}
if ( ! defined( 'COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_DIR' ) ) {
    define( 'COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL' ) ) {
    define( 'COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Load coinsnap-core shared library.
require_once COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_DIR . 'vendor/coinsnap-core.php';

// Load plugin-specific includes.
require_once COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_DIR . 'includes/class-coinsnap-bitcoin-crowdfunding-client.php';
require_once COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_DIR . 'includes/class-coinsnap-bitcoin-crowdfunding-list.php';
require_once COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_DIR . 'includes/class-coinsnap-bitcoin-crowdfunding-public-donors.php';
require_once COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_DIR . 'includes/class-coinsnap-bitcoin-crowdfunding-settings.php';
require_once COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_DIR . 'includes/class-coinsnap-bitcoin-crowdfunding-shortcode.php';
require_once COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_DIR . 'includes/class-coinsnap-bitcoin-crowdfunding-webhooks.php';
require_once COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_DIR . 'includes/class-coinsnap-bitcoin-crowdfunding-shoutouts-list.php';

/**
 * Return the shared PluginInstance for this plugin.
 */
function coinsnap_bitcoin_crowdfunding_plugin_instance(): CoinsnapCore\PluginInstance {
    static $inst;
    if ( null === $inst ) {
        $inst = new CoinsnapCore\PluginInstance( array(
            'plugin_name'              => 'Coinsnap Bitcoin Crowdfunding',
            'option_key'               => 'coinsnap_bitcoin_crowdfunding_options',
            'webhook_key'              => 'coinsnap_bitcoin_crowdfunding_webhook',
            'rest_namespace'           => 'coinsnap-bitcoin-crowdfunding/v1',
            'referral_code'            => COINSNAP_BITCOIN_CROWDFUNDING_REFERRAL_CODE,
            'text_domain'              => 'coinsnap-bitcoin-crowdfunding',
            'plugin_url'               => COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL,
            'plugin_dir'               => COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_DIR,
            'table_suffix'             => 'crowdfunding_payments',
            'log_dir_name'             => 'coinsnap-cf-logs',
            'log_file_name'            => 'coinsnap-cf.log',
            'menu_slug'                => 'coinsnap-bitcoin-crowdfunding',
            'plugin_icon_url'          => COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL . 'assets/images/icon.svg',
            'btcpay_callback_endpoint' => 'coinsnap-btcpay-cf-callback',
            'btcpay_app_name'          => 'CoinsnapBitcoinCrowdfunding',
            'help_links'               => array(
                array( 'url' => 'https://coinsnap.io/wp-plugins/wp-bitcoin-crowdfunding/', 'label' => 'Documentation' ),
                array( 'url' => 'https://coinsnap.io/en/support/', 'label' => 'Support' ),
                array( 'url' => 'https://app.coinsnap.io', 'label' => 'Coinsnap Dashboard' ),
            ),
        ) );
    }
    return $inst;
}

/**
 * One-time migration: rename legacy option keys to vendor-compatible names.
 */
function coinsnap_bitcoin_crowdfunding_migrate_settings() {
    $options = get_option( 'coinsnap_bitcoin_crowdfunding_options', array() );
    $updated = false;

    if ( isset( $options['provider'] ) && ! isset( $options['payment_provider'] ) ) {
        $options['payment_provider'] = $options['provider'];
        unset( $options['provider'] );
        $updated = true;
    }
    if ( isset( $options['btcpay_url'] ) && ! isset( $options['btcpay_host'] ) ) {
        $options['btcpay_host'] = $options['btcpay_url'];
        unset( $options['btcpay_url'] );
        $updated = true;
    }
    if ( $updated ) {
        update_option( 'coinsnap_bitcoin_crowdfunding_options', $options );
    }
}
add_action( 'init', 'coinsnap_bitcoin_crowdfunding_migrate_settings' );

register_activation_hook( __FILE__, 'coinsnap_bitcoin_crowdfunding_create_crowdfunding_payments_table' );
register_deactivation_hook( __FILE__, 'coinsnap_bitcoin_crowdfunding_deactivate' );

// Permalink structure fix.
add_filter( 'rest_url_prefix', 'coinsnap_bitcoin_crowdfunding_rest_url_prefix' );

function coinsnap_bitcoin_crowdfunding_rest_url_prefix( $prefix ) {
    return 'wp-json';
}

function coinsnap_bitcoin_crowdfunding_deactivate() {
    flush_rewrite_rules();
}

function coinsnap_bitcoin_crowdfunding_create_crowdfunding_payments_table() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'crowdfunding_payments';
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
    dbDelta( $sql );
}

class Coinsnap_Bitcoin_Crowdfunding {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'coinsnap_bitcoin_crowdfunding_enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'coinsnap_bitcoin_crowdfunding_enqueue_admin_styles' ) );

        // Amount check (public + logged-in).
        add_action( 'wp_ajax_coinsnap_bitcoin_crowdfunding_amount_check', array( $this, 'coinsnapAmountCheck' ) );
        add_action( 'wp_ajax_nopriv_coinsnap_bitcoin_crowdfunding_amount_check', array( $this, 'coinsnapAmountCheck' ) );

        // Delegate connection + BTCPay URL AJAX to vendor handlers.
        add_action( 'wp_ajax_coinsnap_bitcoin_crowdfunding_connection_handler', function () {
            CoinsnapCore\Admin\AjaxHandlers::handle_connection_check( coinsnap_bitcoin_crowdfunding_plugin_instance() );
        } );
        // Override vendor BTCPay URL handler to support ngrok / HTTPS / popup modes.
        // Priority 5 runs before the vendor lambda below (priority 10). Calling
        // wp_send_json_success() terminates execution so the vendor never fires.
        add_action(
            'wp_ajax_coinsnap_bitcoin_crowdfunding_btcpay_apiurl_handler',
            function () {
                $nonce = filter_input( INPUT_POST, 'apiNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
                if ( ! wp_verify_nonce( $nonce, 'coinsnap-ajax-nonce' ) ) {
                    wp_die( 'Unauthorized!', '', array( 'response' => 401 ) );
                }
                if ( ! current_user_can( 'manage_options' ) ) {
                    wp_send_json_error( 'Insufficient permissions.' );
                }

                $host = filter_var(
                    filter_input( INPUT_POST, 'host', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
                    FILTER_VALIDATE_URL
                );
                if ( false === $host || ( substr( $host, 0, 7 ) !== 'http://' && substr( $host, 0, 8 ) !== 'https://' ) ) {
                    wp_send_json_error( 'Error validating BTCPay Server URL.' );
                }

                $instance = coinsnap_bitcoin_crowdfunding_plugin_instance();
                $settings = CoinsnapCore\Admin\SettingsPage::get_settings_for( $instance );
                $endpoint = $instance->get( 'btcpay_callback_endpoint' );

                $popup_mode = false;
                if ( ! empty( $settings['ngrok_url'] ) ) {
                    $redirect_url = rtrim( $settings['ngrok_url'], '/' ) . '/?' . $endpoint;
                } elseif ( is_ssl() ) {
                    $redirect_url = home_url( '/?' . $endpoint );
                } else {
                    $redirect_url = home_url( '/?' . $endpoint . '&popup=1' );
                    $popup_mode   = true;
                }

                $permissions = array_merge(
                    CoinsnapCore\Auth\BTCPayAuthorizer::REQUIRED_PERMISSIONS,
                    CoinsnapCore\Auth\BTCPayAuthorizer::OPTIONAL_PERMISSIONS
                );

                try {
                    $url = CoinsnapCore\Auth\BTCPayAuthorizer::get_authorize_url(
                        $host,
                        $permissions,
                        $instance->get( 'btcpay_app_name', 'Coinsnap' ),
                        true,
                        true,
                        $redirect_url,
                        null
                    );

                    CoinsnapCore\Auth\BTCPayAuthorizer::update_settings(
                        $instance->option_key(),
                        array( 'btcpay_host' => $host )
                    );

                    wp_send_json_success( array(
                        'url'   => $url,
                        'popup' => $popup_mode,
                    ) );
                } catch ( \Throwable $e ) {
                    wp_send_json_error( 'Error processing request.' );
                }
            },
            5
        );

        // Vendor fallback (kept for reference; never reached because priority-5 handler above exits).
        add_action( 'wp_ajax_coinsnap_bitcoin_crowdfunding_btcpay_apiurl_handler', function () {
            CoinsnapCore\Admin\AjaxHandlers::handle_btcpay_url( coinsnap_bitcoin_crowdfunding_plugin_instance() );
        } );
        add_action( 'wp_ajax_coinsnap_bitcoin_crowdfunding_reregister_webhook', function () {
            CoinsnapCore\Admin\AjaxHandlers::handle_reregister_webhook( coinsnap_bitcoin_crowdfunding_plugin_instance() );
        } );

        // Register BTCPay OAuth callback via vendor.
        CoinsnapCore\Auth\BTCPayAuthorizer::register_callback( coinsnap_bitcoin_crowdfunding_plugin_instance() );

        // Intercept popup mode before the vendor handler fires at priority 10.
        // When &popup=1 is present we save credentials here and output the
        // postMessage page so the popup can relay the key to the opener.
        add_action(
            'template_redirect',
            function () {
                global $wp_query;

                $endpoint = coinsnap_bitcoin_crowdfunding_plugin_instance()->get( 'btcpay_callback_endpoint' );
                if ( ! isset( $wp_query->query_vars[ $endpoint ] ) ) {
                    return;
                }

                // Only intercept popup requests; normal HTTPS flow goes to vendor at priority 10.
                $popup = filter_input( INPUT_GET, 'popup', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
                if ( '1' !== $popup ) {
                    return;
                }

                if ( ! isset( $wp_query->query_vars[ $endpoint . '-nonce' ] )
                    || ! wp_verify_nonce( $wp_query->query_vars[ $endpoint . '-nonce' ], 'coinsnap-core-btcpay-nonce' ) ) {
                    return;
                }

                $inst         = coinsnap_bitcoin_crowdfunding_plugin_instance();
                $option_key   = $inst->option_key();
                $settings_url = admin_url( '/admin.php?page=' . $inst->get( 'menu_slug' ) );
                $form_data    = get_option( $option_key, array() );
                $btcpay_host  = is_array( $form_data ) ? ( $form_data['btcpay_host'] ?? '' ) : '';

                $btcpay_api_key = filter_input( INPUT_POST, 'apiKey', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
                if ( empty( $btcpay_api_key ) ) {
                    wp_safe_redirect( $settings_url );
                    exit();
                }

                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above via query_vars.
                $btcpay_perms = isset( $_POST['permissions'] ) && is_array( $_POST['permissions'] )
                    ? array_map( 'sanitize_text_field', wp_unslash( $_POST['permissions'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    : array();

                if ( empty( $btcpay_perms ) ) {
                    wp_safe_redirect( $settings_url );
                    exit();
                }

                $required = CoinsnapCore\Auth\BTCPayAuthorizer::REQUIRED_PERMISSIONS;
                $optional = CoinsnapCore\Auth\BTCPayAuthorizer::OPTIONAL_PERMISSIONS;

                $base_perms = array_reduce(
                    $btcpay_perms,
                    static function ( array $carry, string $p ) {
                        return array_merge( $carry, array( explode( ':', $p )[0] ) );
                    },
                    array()
                );
                $base_perms   = array_diff( $base_perms, $optional );
                $has_required = empty(
                    array_merge(
                        array_diff( $required, $base_perms ),
                        array_diff( $base_perms, $required )
                    )
                );

                $has_single_store = true;
                $store_id         = null;
                foreach ( $btcpay_perms as $perm ) {
                    $parts = explode( ':', $perm );
                    if ( 2 !== count( $parts ) ) {
                        wp_safe_redirect( $settings_url );
                        exit();
                    }
                    $received = $parts[1] ?? null;
                    if ( null === $received ) {
                        $has_single_store = false;
                    }
                    if ( $store_id === $received ) {
                        continue;
                    }
                    if ( null === $store_id ) {
                        $store_id = $received;
                        continue;
                    }
                    $has_single_store = false;
                }

                $clean_api_key      = sanitize_text_field( $btcpay_api_key );
                $store_id_from_perm = '';

                if ( $has_single_store && $has_required ) {
                    $store_id_from_perm = explode( ':', $btcpay_perms[0] )[1] ?? '';

                    if ( empty( $store_id_from_perm ) && ! empty( $btcpay_host ) ) {
                        $response = wp_remote_get(
                            rtrim( $btcpay_host, '/' ) . '/api/v1/stores',
                            array(
                                'headers' => array(
                                    'Authorization' => 'token ' . $clean_api_key,
                                    'Content-Type'  => 'application/json',
                                ),
                                'timeout' => 20,
                            )
                        );
                        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 300 ) {
                            $stores = json_decode( wp_remote_retrieve_body( $response ), true );
                            if ( is_array( $stores ) && ! empty( $stores[0]['id'] ) ) {
                                $store_id_from_perm = $stores[0]['id'];
                            }
                        }
                    }

                    CoinsnapCore\Auth\BTCPayAuthorizer::update_settings(
                        $option_key,
                        array(
                            'btcpay_api_key'   => $clean_api_key,
                            'btcpay_store_id'  => $store_id_from_perm,
                            'payment_provider' => 'btcpay',
                        )
                    );
                }

                // Output postMessage page: relays key + storeId to the opener then closes.
                $js_data     = wp_json_encode( array(
                    'type'    => 'coinsnap_cf_btcpay_auth',
                    'apiKey'  => $clean_api_key,
                    'storeId' => $store_id_from_perm,
                ) );
                $js_origin   = wp_json_encode( home_url() );
                $js_fallback = wp_json_encode( $settings_url );

                header( 'Content-Type: text/html; charset=utf-8' );
                echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Authorizing...</title>';
                echo '<script>(function(){';
                echo 'var d=' . $js_data . ';'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo 'if(window.opener&&!window.opener.closed){';
                echo 'window.opener.postMessage(d,' . $js_origin . ');'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo 'window.close();';
                echo '}else{window.location.href=' . $js_fallback . ';}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '})();</script></head><body></body></html>';
                exit();
            },
            5
        );
    }
    
    public function coinsnapAmountCheck() {
        $nonce = filter_input( INPUT_POST, 'apiNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( ! wp_verify_nonce( $nonce, 'coinsnap-ajax-nonce' ) ) {
            wp_die( 'Unauthorized!', '', array( 'response' => 401 ) );
        }

        $client   = new Coinsnap_Bitcoin_Crowdfunding_Client();
        $amount   = filter_input( INPUT_POST, 'apiAmount', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $currency = filter_input( INPUT_POST, 'apiCurrency', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        try {
            $provider = $this->getPaymentProvider();
            if ( $provider === 'btcpay' ) {
                try {
                    $storePaymentMethods = $client->getStorePaymentMethods( $this->getApiUrl(), $this->getApiKey(), $this->getStoreId() );
                    if ( $storePaymentMethods['code'] === 200 ) {
                        if ( ! $storePaymentMethods['result']['onchain'] && ! $storePaymentMethods['result']['lightning'] ) {
                            $errorMessage = __( 'No payment method is configured on BTCPay server', 'coinsnap-bitcoin-crowdfunding' );
                            $checkInvoice = array( 'result' => false, 'error' => esc_html( $errorMessage ) );
                        }
                    } else {
                        $errorMessage = __( 'Error store loading. Wrong or empty Store ID', 'coinsnap-bitcoin-crowdfunding' );
                        $checkInvoice = array( 'result' => false, 'error' => esc_html( $errorMessage ) );
                    }
                    if ( $storePaymentMethods['result']['onchain'] && ! $storePaymentMethods['result']['lightning'] ) {
                        $checkInvoice = $client->checkPaymentData( (float) $amount, strtoupper( $currency ), 'bitcoin' );
                    } elseif ( $storePaymentMethods['result']['lightning'] ) {
                        $checkInvoice = $client->checkPaymentData( (float) $amount, strtoupper( $currency ), 'lightning' );
                    }
                } catch ( \Throwable $e ) {
                    $errorMessage = __( 'API connection is not established', 'coinsnap-bitcoin-crowdfunding' );
                    $checkInvoice = array( 'result' => false, 'error' => esc_html( $errorMessage ) );
                }
            } else {
                $checkInvoice = $client->checkPaymentData( (float) $amount, strtoupper( $currency ) );
                if ( isset( $checkInvoice['error'] ) ) {
                    if ( $checkInvoice['error'] === 'currencyError' ) {
                        $checkInvoice['error'] = sprintf(
                            /* translators: 1: Currency */
                            __( 'Currency %1$s is not supported by Coinsnap', 'coinsnap-bitcoin-crowdfunding' ),
                            strtoupper( $currency )
                        );
                    } elseif ( $checkInvoice['error'] === 'amountError' ) {
                        $checkInvoice['error'] = sprintf(
                            /* translators: 1: Amount, 2: Currency */
                            __( 'Invoice amount cannot be less than %1$s %2$s', 'coinsnap-bitcoin-crowdfunding' ),
                            $checkInvoice['min_value'],
                            strtoupper( $currency )
                        );
                    }
                }
            }
        } catch ( \Throwable $e ) {
            $errorMessage = __( 'API connection is not established', 'coinsnap-bitcoin-crowdfunding' );
            $checkInvoice = array( 'result' => false, 'error' => esc_html( $errorMessage ) );
        }

        $this->sendJsonResponse( $checkInvoice );
    }

    public function sendJsonResponse( array $response ): void {
        echo wp_json_encode( $response );
        exit();
    }

    private function getPaymentProvider(): string {
        $inst    = coinsnap_bitcoin_crowdfunding_plugin_instance();
        $options = CoinsnapCore\Admin\SettingsPage::get_settings_for( $inst );
        return ( $options['payment_provider'] === 'btcpay' ) ? 'btcpay' : 'coinsnap';
    }

    private function getApiKey(): string {
        $inst    = coinsnap_bitcoin_crowdfunding_plugin_instance();
        $options = CoinsnapCore\Admin\SettingsPage::get_settings_for( $inst );
        return ( $this->getPaymentProvider() === 'btcpay' )
            ? ( $options['btcpay_api_key'] ?? '' )
            : ( $options['coinsnap_api_key'] ?? '' );
    }

    private function getStoreId(): string {
        $inst    = coinsnap_bitcoin_crowdfunding_plugin_instance();
        $options = CoinsnapCore\Admin\SettingsPage::get_settings_for( $inst );
        return ( $this->getPaymentProvider() === 'btcpay' )
            ? ( $options['btcpay_store_id'] ?? '' )
            : ( $options['coinsnap_store_id'] ?? '' );
    }

    public function getApiUrl(): string {
        $inst    = coinsnap_bitcoin_crowdfunding_plugin_instance();
        $options = CoinsnapCore\Admin\SettingsPage::get_settings_for( $inst );
        return ( $this->getPaymentProvider() === 'btcpay' )
            ? ( $options['btcpay_host'] ?? '' )
            : COINSNAP_SERVER_URL;
    }

    public function coinsnap_bitcoin_crowdfunding_enqueue_scripts() {
        global $post;

        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'coinsnap_bitcoin_crowdfunding' ) ) {
            return;
        }

        $inst    = coinsnap_bitcoin_crowdfunding_plugin_instance();
        $options = CoinsnapCore\Admin\SettingsPage::get_settings_for( $inst );

        wp_enqueue_style( 'coinsnap-bitcoin-crowdfunding-style', COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL . 'assets/css/style.css', array(), COINSNAP_BITCOIN_CROWDFUNDING_VERSION );
        wp_enqueue_style( 'coinsnap-bitcoin-crowdfunding-style-wide', COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL . 'assets/css/style-wide.css', array(), COINSNAP_BITCOIN_CROWDFUNDING_VERSION );
        wp_enqueue_style( 'coinsnap-bitcoin-crowdfunding-shoutouts', COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL . 'assets/css/shoutouts.css', array(), COINSNAP_BITCOIN_CROWDFUNDING_VERSION );

        wp_enqueue_script( 'coinsnap-bitcoin-crowdfunding-script', COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL . 'assets/js/crowdfunding.js', array( 'jquery' ), COINSNAP_BITCOIN_CROWDFUNDING_VERSION, true );
        wp_localize_script( 'coinsnap-bitcoin-crowdfunding-script', 'coinsnap_bitcoin_crowdfunding_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'coinsnap-ajax-nonce' ),
            'post'     => get_the_ID(),
        ) );

        wp_enqueue_script( 'coinsnap-bitcoin-crowdfunding-shared-script', COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL . 'assets/js/shared.js', array( 'jquery' ), COINSNAP_BITCOIN_CROWDFUNDING_VERSION, true );
        wp_localize_script( 'coinsnap-bitcoin-crowdfunding-shared-script', 'Coinsnap_Bitcoin_Crowdfunding_sharedData', array(
            'provider'        => $options['payment_provider'] ?? 'coinsnap',
            'coinsnapStoreId' => $options['coinsnap_store_id'] ?? '',
            'coinsnapApiKey'  => $options['coinsnap_api_key'] ?? '',
            'btcpayStoreId'   => $options['btcpay_store_id'] ?? '',
            'btcpayApiKey'    => $options['btcpay_api_key'] ?? '',
            'btcpayUrl'       => $options['btcpay_host'] ?? '',
            'redirectUrl'     => '',
            'nonce'           => wp_create_nonce( 'wp_rest' ),
        ) );

        wp_enqueue_script( 'coinsnap-bitcoin-crowdfunding-popup-script', COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL . 'assets/js/popup.js', array( 'jquery' ), COINSNAP_BITCOIN_CROWDFUNDING_VERSION, true );
    }

    public function coinsnap_bitcoin_crowdfunding_enqueue_admin_styles( $hook ) {
        $post_id   = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? '';
        $post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
            ?? ( ( ! empty( $post_id ) ) ? get_post_type( $post_id ) : '' );
        $page      = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? '';

        // Vendor admin UI on settings pages and CPT edit screens.
        if ( false !== strpos( $page, 'coinsnap-bitcoin-crowdfunding' ) || $post_type === 'coinsnap-cfs' || $post_type === 'coinsnap-cf-donors' ) {
            $inst = coinsnap_bitcoin_crowdfunding_plugin_instance();
            wp_register_style( 'coinsnap-core-admin', COINSNAP_CORE_PLUGIN_URL . 'assets/css/admin.css', array(), COINSNAP_CORE_VERSION );
            wp_register_script( 'coinsnap-core-admin', COINSNAP_CORE_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), COINSNAP_CORE_VERSION, true );
            wp_localize_script( 'coinsnap-core-admin', 'CoinsnapCoreAdmin', array(
                'option_key'        => $inst->option_key(),
                'ajax_url'          => admin_url( 'admin-ajax.php' ),
                'nonce'             => wp_create_nonce( 'coinsnap-ajax-nonce' ),
                'connection_action' => 'coinsnap_bitcoin_crowdfunding_connection_handler',
                'btcpay_action'     => 'coinsnap_bitcoin_crowdfunding_btcpay_apiurl_handler',
                'webhook_action'    => 'coinsnap_bitcoin_crowdfunding_reregister_webhook',
            ) );
            wp_enqueue_style( 'coinsnap-core-admin' );
            wp_enqueue_script( 'coinsnap-core-admin' );

            // Override the vendor's .csc-btn-generate click handler so it can open a
            // popup and relay the API key back via postMessage when running on HTTP.
            $popup_inline_js = <<<'JSCODE'
(function($) {
    $(document).ready(function() {
        $('.csc-btn-generate').off('click').on('click', function(e) {
            e.preventDefault();
            var $wrapper = $(this).closest('.csc-generate-key-wrapper, .csc-field-row, .bif-generate-key-wrapper');
            var host = $wrapper.find('input[type="url"]').val() || $(this).siblings('input[type="url"]').val();
            if (!host || host.indexOf('http') === -1) {
                alert('Please enter a valid URL including https:// for your BTCPay Server.');
                return;
            }
            try { new URL(host); } catch(err) {
                alert('Please enter a valid URL including https:// for your BTCPay Server.');
                return;
            }
            if (typeof CoinsnapCoreAdmin === 'undefined' || !CoinsnapCoreAdmin.ajax_url) return;
            $.post(CoinsnapCoreAdmin.ajax_url, {
                action: CoinsnapCoreAdmin.btcpay_action || 'coinsnap_core_btcpay_handler',
                host: host,
                apiNonce: CoinsnapCoreAdmin.nonce || ''
            }, function(response) {
                if (response.data && response.data.url) {
                    if (response.data.popup) {
                        var popup = window.open(response.data.url, 'btcpay_cf_auth', 'width=760,height=640,scrollbars=yes,resizable=yes');
                        var handler = function(event) {
                            if (event.origin !== window.location.origin) return;
                            if (!event.data || event.data.type !== 'coinsnap_cf_btcpay_auth') return;
                            window.removeEventListener('message', handler);
                            var $apiKey = $('input[name$="[btcpay_api_key]"]');
                            var $storeId = $('input[name$="[btcpay_store_id]"]');
                            if (event.data.apiKey && $apiKey.length) {
                                $apiKey.val(event.data.apiKey).css({'border-color': '#00a32a', 'box-shadow': '0 0 0 1px #00a32a'});
                            }
                            if (event.data.storeId && $storeId.length) {
                                $storeId.val(event.data.storeId).css({'border-color': '#00a32a', 'box-shadow': '0 0 0 1px #00a32a'});
                            }
                            if (popup && !popup.closed) { popup.close(); }
                        };
                        window.addEventListener('message', handler);
                    } else {
                        window.location = response.data.url;
                    }
                }
            }).fail(function() {
                alert('Error processing your request. Please verify your BTCPay Server URL.');
            });
        });
    });
})(jQuery);
JSCODE;
            wp_add_inline_script( 'coinsnap-core-admin', $popup_inline_js, 'after' );
        }

        // Plugin-specific admin CSS/JS on plugin pages and crowdfunding CPT.
        if ( false !== strpos( $page, 'coinsnap-bitcoin-crowdfunding' ) || $post_type === 'coinsnap-cfs' || $post_type === 'coinsnap-cf-donors' ) {
            $inst    = coinsnap_bitcoin_crowdfunding_plugin_instance();
            $options = CoinsnapCore\Admin\SettingsPage::get_settings_for( $inst );
            wp_enqueue_style( 'coinsnap-bitcoin-crowdfunding-admin-style', COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL . 'assets/css/admin-style.css', array(), COINSNAP_BITCOIN_CROWDFUNDING_VERSION );
            wp_enqueue_script( 'coinsnap-bitcoin-crowdfunding-admin-script', COINSNAP_BITCOIN_CROWDFUNDING_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), COINSNAP_BITCOIN_CROWDFUNDING_VERSION, true );
            wp_localize_script( 'coinsnap-bitcoin-crowdfunding-admin-script', 'coinsnap_bitcoin_crowdfunding_ajax', array(
                'ajax_url'        => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'coinsnap-ajax-nonce' ),
                'rest_nonce'      => wp_create_nonce( 'wp_rest' ),
                'post'            => $post_id,
                'provider'        => $options['payment_provider'] ?? 'coinsnap',
                'coinsnapStoreId' => $options['coinsnap_store_id'] ?? '',
                'coinsnapApiKey'  => $options['coinsnap_api_key'] ?? '',
                'btcpayStoreId'   => $options['btcpay_store_id'] ?? '',
                'btcpayApiKey'    => $options['btcpay_api_key'] ?? '',
                'btcpayUrl'       => $options['btcpay_host'] ?? '',
            ) );
        }
    }

    public function coinsnap_bitcoin_crowdfunding_verify_nonce( $nonce, $action ) {
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_die( esc_html__( 'Security check failed', 'coinsnap-bitcoin-crowdfunding' ) );
        }
    }
}
new Coinsnap_Bitcoin_Crowdfunding();
