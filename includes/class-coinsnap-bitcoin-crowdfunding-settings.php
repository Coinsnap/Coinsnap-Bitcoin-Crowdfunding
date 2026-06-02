<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-coinsnap-bitcoin-crowdfunding-donation-list.php';

class Coinsnap_Bitcoin_Crowdfunding_Settings {

    private $donation_list;

    public function __construct() {
        $this->donation_list = new Coinsnap_Bitcoin_Crowdfunding_Donation_List();

        add_action( 'admin_menu', array( $this, 'coinsnap_bitcoin_crowdfunding_add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'coinsnap_bitcoin_crowdfunding_settings_init' ) );
        add_action( 'admin_notices', array( $this, 'maybe_show_setup_notice' ) );
    }

    public function coinsnap_bitcoin_crowdfunding_add_admin_menu() {
        $inst = coinsnap_bitcoin_crowdfunding_plugin_instance();

        add_menu_page(
            __( 'Bitcoin Crowdfunding', 'coinsnap-bitcoin-crowdfunding' ),
            __( 'Bitcoin Crowdfunding', 'coinsnap-bitcoin-crowdfunding' ),
            'manage_options',
            'coinsnap-bitcoin-crowdfunding',
            array( $this, 'coinsnap_bitcoin_crowdfunding_options_page' ),
            esc_url( $inst->get( 'plugin_icon_url' ) ),
            100
        );

        add_submenu_page(
            'coinsnap-bitcoin-crowdfunding',
            __( 'Settings', 'coinsnap-bitcoin-crowdfunding' ),
            __( 'Settings', 'coinsnap-bitcoin-crowdfunding' ),
            'manage_options',
            'coinsnap-bitcoin-crowdfunding',
            array( $this, 'coinsnap_bitcoin_crowdfunding_options_page' )
        );

        add_submenu_page(
            'coinsnap-bitcoin-crowdfunding',
            __( 'Transactions', 'coinsnap-bitcoin-crowdfunding' ),
            __( 'Transactions', 'coinsnap-bitcoin-crowdfunding' ),
            'manage_options',
            'coinsnap-bitcoin-crowdfunding-donation-list',
            array( $this->donation_list, 'render_donation_page' )
        );

        add_submenu_page(
            'coinsnap-bitcoin-crowdfunding',
            __( 'Crowdfundings', 'coinsnap-bitcoin-crowdfunding' ),
            __( 'Crowdfundings', 'coinsnap-bitcoin-crowdfunding' ),
            'manage_options',
            'edit.php?post_type=coinsnap-cfs'
        );

        add_submenu_page(
            'coinsnap-bitcoin-crowdfunding',
            __( 'Donor Information', 'coinsnap-bitcoin-crowdfunding' ),
            __( 'Donor Information', 'coinsnap-bitcoin-crowdfunding' ),
            'manage_options',
            'edit.php?post_type=coinsnap-cf-donors'
        );
    }

    public function coinsnap_bitcoin_crowdfunding_settings_init() {
        CoinsnapCore\Admin\SettingsPage::register_for( coinsnap_bitcoin_crowdfunding_plugin_instance() );
    }

    public function coinsnap_bitcoin_crowdfunding_options_page() {
        CoinsnapCore\Admin\SettingsPage::render_page_for( coinsnap_bitcoin_crowdfunding_plugin_instance() );
    }

    public function maybe_show_setup_notice() {
        CoinsnapCore\Admin\SettingsPage::maybe_show_setup_notice( coinsnap_bitcoin_crowdfunding_plugin_instance() );
    }
}
new Coinsnap_Bitcoin_Crowdfunding_Settings();