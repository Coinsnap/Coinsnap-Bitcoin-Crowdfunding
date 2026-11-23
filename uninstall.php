<?php
if (!defined('ABSPATH')){ exit; }
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

// Remove only what this plugin created. Never touch a name another Coinsnap
// plugin may use, such as the shared coinsnap_webhook_secret (6.4).
global $wpdb;
$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'crowdfunding_payments'));

foreach ([
    'coinsnap_bitcoin_crowdfunding_options',
    'coinsnap_bitcoin_crowdfunding_webhook_secrets',
    'coinsnap_bitcoin_crowdfunding_db_version',
    'coinsnap_bitcoin_crowdfunding_security_upgraded_at',
    'coinsnap_bitcoin_crowdfunding_rates_last',
] as $coinsnap_bitcoin_crowdfunding_option) {
    delete_option($coinsnap_bitcoin_crowdfunding_option);
}
delete_transient('coinsnap_bitcoin_crowdfunding_btcpay_state');
delete_transient('coinsnap_bitcoin_crowdfunding_rates');
delete_transient('coinsnap_bitcoin_crowdfunding_rates_failed');
