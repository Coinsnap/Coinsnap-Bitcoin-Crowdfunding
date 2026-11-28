<?php
/**
 * Donations page test, run inside a WordPress with the plugin active:
 *   wp eval-file wp-content/plugins/coinsnap-bitcoin-crowdfunding/tests/donation-list-test.php
 *
 * The Donations page lists the store's settled invoices whose metadata type is
 * "Bitcoin Crowdfunding". It used to also require metadata.referralCode = D19833, which the
 * plugin has not sent since 1.2.0 (the referral code is a top-level invoice field and the API
 * does not copy it into the metadata), so the page was always empty.
 * The API is faked with pre_http_request, with list rows shaped like Coinsnap's; nothing leaves
 * the site. Settings are replaced through a pre_option filter only (never written).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['t_fail'] = 0;
$GLOBALS['t_list'] = array( 200, array() );
function t_ok( $name, $cond ) {
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $name . "\n";
	$GLOBALS['t_fail'] += $cond ? 0 : 1;
}
function t_row( $id, $status, $type, $created ) {
	// What this plugin's invoices look like in GET /stores/{id}/invoices: metadata as sent.
	return array(
		'id' => $id, 'storeId' => 'STORE1', 'status' => $status, 'amount' => 1500, 'currency' => 'SATS', 'orderId' => 'CRFN_x',
		'metadata' => array( 'type' => $type, 'crowdfunding_id' => '7', 'crowdfundName' => 'Save the bees', 'orderNumber' => 'Donated for Crowdfunding', 'orderId' => 'CRFN_x' ),
		'createdAt' => $created,
	);
}
function t_page() {
	ob_start();
	( new Coinsnap_Bitcoin_Crowdfunding_Donation_List() )->render_donation_page();
	return (string) ob_get_clean();
}

add_filter( 'pre_option_coinsnap_bitcoin_crowdfunding_options', function () {
	return array( 'provider' => 'coinsnap', 'coinsnap_api_key' => 'test-key', 'coinsnap_store_id' => 'STORE1', 'btcpay_url' => '', 'btcpay_store_id' => '', 'btcpay_api_key' => '' );
} );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$code = 'https://app.coinsnap.io/api/v1/stores/STORE1/invoices' === $url ? $GLOBALS['t_list'][0] : 404;
	$body = 200 === $code || 404 === $GLOBALS['t_list'][0] ? $GLOBALS['t_list'][1] : array();
	return array( 'response' => array( 'code' => $code, 'message' => '' ), 'body' => wp_json_encode( $body ), 'headers' => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( array() ), 'cookies' => array() );
}, 10, 3 );

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) ( $admins[0] ?? 0 ) );
t_ok( 'an administrator is available for the page', current_user_can( 'manage_options' ) );

$GLOBALS['t_list'] = array( 200, array(
	t_row( 'INVpaidNEW', 'Settled', 'Bitcoin Crowdfunding', 1795000200 ),
	t_row( 'INVpaidOLD', 'Settled', 'Bitcoin Crowdfunding', 1795000100 ),
	t_row( 'INVunpaid', 'New', 'Bitcoin Crowdfunding', 1795000300 ),
	t_row( 'INVexpired', 'Expired', 'Bitcoin Crowdfunding', 1795000400 ),
	t_row( 'INVotherPlugin', 'Settled', 'Bitcoin Donation', 1795000500 ),
	array( 'id' => 'INVnoMeta', 'status' => 'Settled', 'amount' => 5, 'currency' => 'SATS', 'metadata' => array(), 'createdAt' => 1795000600 ),
) );
$html = t_page();
t_ok( 'settled crowdfunding invoices are listed', false !== strpos( $html, 'INVpaidNEW' ) && false !== strpos( $html, 'INVpaidOLD' ) );
t_ok( 'newest first', false !== strpos( $html, 'INVpaidNEW' ) && strpos( $html, 'INVpaidNEW' ) < strpos( $html, 'INVpaidOLD' ) );
t_ok( 'the row shows the amount and the crowdfunding name', false !== strpos( $html, '1,500 sats' ) && false !== strpos( $html, 'Save the bees' ) );
t_ok( 'unpaid and expired invoices are not listed', false === strpos( $html, 'INVunpaid' ) && false === strpos( $html, 'INVexpired' ) );
t_ok( "another plugin's invoices and invoices without metadata are not listed", false === strpos( $html, 'INVotherPlugin' ) && false === strpos( $html, 'INVnoMeta' ) );

// A store without invoices: Coinsnap answers 404 {"message":"No invoices found."}.
$GLOBALS['t_list'] = array( 404, array( 'message' => 'No invoices found.' ) );
t_ok( 'empty store shows "No donations found."', false !== strpos( t_page(), 'No donations found.' ) );

echo $GLOBALS['t_fail'] ? $GLOBALS['t_fail'] . " failed\n" : "all passed\n";
if ( $GLOBALS['t_fail'] ) {
	exit( 1 );
}
