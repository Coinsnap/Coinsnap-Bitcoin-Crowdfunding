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

    function coinsnap_bitcoin_crowdfunding_enqueue_admin_styles($hook)
    {
        error_log('Enqueueing admin styles for hook: ' . $hook);
        if ($hook === 'bitcoin-crowdfunding_page_coinsnap-bitcoin-crowdfunding-donation-list') {
            wp_enqueue_style('coinsnap-bitcoin-crowdfunding-admin-style', plugin_dir_url(__FILE__) . 'styles/admin-style.css', [], '1.0.0');
        } else if ($hook === 'toplevel_page_coinsnap_bitcoin_crowdfunding') {
            wp_enqueue_style('coinsnap-bitcoin-crowdfunding-admin-style', plugin_dir_url(__FILE__) . 'styles/admin-style.css', [], '1.0.0');
            $secret = $this->get_webhook_secret();
            $options = get_option('coinsnap_bitcoin_crowdfunding_options', []);
            $ngrok_url = isset($options['ngrok_url']) ? $options['ngrok_url'] : '';
            wp_enqueue_script('coinsnap-bitcoin-crowdfunding-admin-script', plugin_dir_url(__FILE__) . 'js/admin.js', ['jquery'], '1.0.0', true);
            wp_localize_script('coinsnap-bitcoin-crowdfunding-admin-script', 'adminData', ['webhookSecret' => $secret, 'ngrokUrl' => $ngrok_url]);
        }
    }

    function coinsnap_bitcoin_crowdfunding_verify_nonce($nonce, $action)
    {
        if (!wp_verify_nonce($nonce, $action)) {
            wp_die(__('Security check failed', 'coinsnap_bitcoin_crowdfunding'));
        }
    }
}
new Coinsnap_Bitcoin_Crowdfunding();
