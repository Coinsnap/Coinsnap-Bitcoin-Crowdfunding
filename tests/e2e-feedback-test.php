<?php
/**
 * E2E feedback test, run inside a WordPress with the plugin active:
 *   wp eval-file wp-content/plugins/coinsnap-bitcoin-crowdfunding/tests/e2e-feedback-test.php
 *
 * Checks: the invoice answer carries the on-chain BIP21 data and the checkout link; exchange
 * rates are cached and a CoinGecko error doesn't block SATS amounts; the amount check reads its
 * input without PHP notices and turns error keys into messages; the status check confirms a
 * paid pledge from the API when no webhook arrives.
 * The API is faked with pre_http_request; nothing leaves the site. The plugin's settings and
 * webhook secret are replaced through pre_option filters only (never written). The rate cache
 * is saved and restored, and everything the test creates is deleted at the end.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['t_fail']     = 0;
$GLOBALS['t_invoices'] = array();
$GLOBALS['t_calls']    = array( 'rates' => 0, 'invoice_get' => 0 );
$GLOBALS['t_rates']    = 200;
$GLOBALS['t_provider'] = 'coinsnap';
function t_ok( $name, $cond ) {
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $name . "\n";
	$GLOBALS['t_fail'] += $cond ? 0 : 1;
}
function t_resp( $code, $body ) {
	return array( 'response' => array( 'code' => $code, 'message' => '' ), 'body' => wp_json_encode( $body ), 'headers' => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( array() ), 'cookies' => array() );
}

// Settings and webhook secret for this run only.
add_filter( 'pre_option_coinsnap_bitcoin_crowdfunding_options', function () {
	return array(
		'provider' => $GLOBALS['t_provider'], 'coinsnap_api_key' => 'test-key', 'coinsnap_store_id' => 'STORE1',
		'btcpay_url' => 'https://btc.test', 'btcpay_api_key' => 'test-key', 'btcpay_store_id' => 'STORE2',
	);
} );
add_filter( 'pre_option_coinsnap_bitcoin_crowdfunding_webhook_secrets', function () {
	return array( 'coinsnap:STORE1' => array( 'secret' => 'test-secret-coinsnap', 'webhook_id' => 'wh' ), 'btcpay:STORE2' => array( 'secret' => 'test-secret-btcpay', 'webhook_id' => 'wh' ) );
} );

// Save the rate cache; restore it and delete what the test made, whatever happens.
$t_rate_keys = array( 'option' => 'coinsnap_bitcoin_crowdfunding_rates_last', 'transients' => array( 'coinsnap_bitcoin_crowdfunding_rates', 'coinsnap_bitcoin_crowdfunding_rates_failed' ) );
$t_saved     = array( 'option' => get_option( $t_rate_keys['option'], null ) );
foreach ( $t_rate_keys['transients'] as $k ) {
	$t_saved[ $k ] = get_transient( $k );
}
$GLOBALS['t_created'] = array( 'posts' => array(), 'invoices' => array() );
register_shutdown_function( function () use ( $t_rate_keys, $t_saved ) {
	global $wpdb;
	null === $t_saved['option'] ? delete_option( $t_rate_keys['option'] ) : update_option( $t_rate_keys['option'], $t_saved['option'], false );
	foreach ( $t_rate_keys['transients'] as $k ) {
		false === $t_saved[ $k ] ? delete_transient( $k ) : set_transient( $k, $t_saved[ $k ], MINUTE_IN_SECONDS );
	}
	foreach ( $GLOBALS['t_created']['invoices'] as $inv ) {
		$wpdb->delete( $wpdb->prefix . 'crowdfunding_payments', array( 'payment_id' => $inv ) );
		delete_transient( 'coinsnap_bitcoin_crowdfunding_poll_' . md5( $inv ) );
		foreach ( get_posts( array( 'post_type' => 'coinsnap-cf-donors', 'post_status' => 'any', 'meta_key' => '_coinsnap_bitcoin_crowdfunding_payment_id', 'meta_value' => $inv, 'fields' => 'ids' ) ) as $p ) {
			wp_delete_post( $p, true );
		}
	}
	foreach ( $GLOBALS['t_created']['posts'] as $p ) {
		wp_delete_post( $p, true );
	}
} );

// Fake API: CoinGecko, create invoice, get invoice, BTCPay payment methods.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$method = strtoupper( $args['method'] ?? 'GET' );
	if ( false !== strpos( $url, 'api.coingecko.com' ) ) {
		$GLOBALS['t_calls']['rates']++;
		return 200 === $GLOBALS['t_rates']
			? t_resp( 200, array( 'rates' => array( 'btc' => array( 'value' => 1 ), 'eur' => array( 'value' => 50000 ), 'sats' => array( 'value' => 100000000 ) ) ) )
			: t_resp( $GLOBALS['t_rates'], array( 'status' => array( 'error_code' => 429 ) ) );
	}
	if ( preg_match( '#/payment-methods$#', $url ) && false === strpos( $url, '/invoices/' ) ) {
		return t_resp( 200, array( array( 'enabled' => true, 'paymentMethodId' => 'BTC-CHAIN' ) ) );
	}
	if ( 'POST' === $method && preg_match( '#/stores/[^/]+/invoices$#', $url ) ) {
		$body = json_decode( $args['body'], true );
		if ( 123 === ( $body['amount'] ?? 0 ) ) {
			return t_resp( 400, array( 'message' => 'Amount is below the on-chain dust threshold. <b>Enable Lightning</b> on this store to accept smaller amounts.' ) );
		}
		$id   = 'inv' . wp_generate_password( 12, false, false );
		$GLOBALS['t_invoices'][ $id ] = array(
			'id' => $id, 'status' => 'New', 'additionalStatus' => 'None', 'amount' => (string) $body['amount'], 'currency' => $body['currency'],
			'metadata' => $body['metadata'] ?? array(), 'createdAt' => time(), 'checkoutLink' => 'https://pay.test/i/' . $id,
			'lightningInvoice' => '', 'onchainAddress' => 'bc1qtestaddress',
			'bip21' => 'bitcoin:bc1qtestaddress?amount=' . rtrim( rtrim( sprintf( '%.8F', $body['amount'] / 100000000 ), '0' ), '.' ),
			'qrCodes' => array( 'lightningQR' => '', 'bitcoinQR' => 'data:image/png;base64,QkFSRQ==', 'bip21QR' => 'data:image/png;base64,QklQMjE=' ),
		);
		if ( 0 === strpos( $url, 'https://btc.test' ) ) { // BTCPay: no address or QR codes in the invoice itself.
			unset( $GLOBALS['t_invoices'][ $id ]['bip21'], $GLOBALS['t_invoices'][ $id ]['qrCodes'], $GLOBALS['t_invoices'][ $id ]['onchainAddress'], $GLOBALS['t_invoices'][ $id ]['lightningInvoice'] );
		}
		return t_resp( 200, $GLOBALS['t_invoices'][ $id ] );
	}
	if ( preg_match( '#/invoices/([A-Za-z0-9]+)/payment-methods$#', $url ) ) {
		return t_resp( 200, array( array( 'paymentMethodId' => 'BTC-CHAIN', 'destination' => 'bc1qbtcpayaddress', 'paymentLink' => 'bitcoin:bc1qbtcpayaddress?amount=0.00005' ) ) );
	}
	if ( 'GET' === $method && preg_match( '#/invoices/([A-Za-z0-9]+)$#', $url, $m ) ) {
		$GLOBALS['t_calls']['invoice_get']++;
		return isset( $GLOBALS['t_invoices'][ $m[1] ] ) ? t_resp( 200, $GLOBALS['t_invoices'][ $m[1] ] ) : t_resp( 400, array( 'message' => 'not found' ) );
	}
	return new WP_Error( 'http_request_failed', 'offline test' );
}, 10, 3 );

global $wpdb;
$table = $wpdb->prefix . 'crowdfunding_payments';

$cf_id = wp_insert_post( array( 'post_type' => 'coinsnap-cfs', 'post_status' => 'publish', 'post_title' => 'E2E feedback test' ) );
$GLOBALS['t_created']['posts'][] = $cf_id;
foreach ( array( 'active' => '1', 'collect_donor_info' => '1', 'shoutout' => '1', 'first_name_visibility' => 'optional' ) as $k => $v ) {
	update_post_meta( $cf_id, '_coinsnap_bitcoin_crowdfunding_' . $k, $v );
}
function t_invoice( $cf_id, $sats ) {
	$req = new WP_REST_Request( 'POST', '/coinsnap-bitcoin-crowdfunding/v1/invoice' );
	$req->set_header( 'Content-Type', 'application/json' );
	$req->set_body( wp_json_encode( array( 'crowdfunding_id' => $cf_id, 'amount' => $sats, 'first_name' => 'Jane', 'public_name' => 'Jane' ) ) );
	$res = rest_do_request( $req );
	$data = $res->get_data();
	if ( ! empty( $data['id'] ) ) {
		$GLOBALS['t_created']['invoices'][] = $data['id'];
	}
	return array( $res->get_status(), is_array( $data ) ? $data : array() );
}
function t_status( $inv ) {
	$started = microtime( true );
	$res     = rest_do_request( new WP_REST_Request( 'GET', '/crowdfunding/v1/check-payment-status/' . $inv ) );
	return array( $res->get_data()['status'] ?? '', microtime( true ) - $started );
}
function t_donor_posts( $inv ) {
	return count( get_posts( array( 'post_type' => 'coinsnap-cf-donors', 'post_status' => 'any', 'meta_key' => '_coinsnap_bitcoin_crowdfunding_payment_id', 'meta_value' => $inv, 'fields' => 'ids' ) ) );
}

/* 1. The invoice answer has what an on-chain-only store needs. */
foreach ( array( 'coinsnap', 'btcpay' ) as $provider ) {
	$GLOBALS['t_provider'] = $provider;
	list( $code, $data ) = t_invoice( $cf_id, 5000 );
	$expected = 'btcpay' === $provider ? 'bitcoin:bc1qbtcpayaddress?amount=0.00005' : 'bitcoin:bc1qtestaddress?amount=0.00005';
	t_ok( "$provider: invoice created", 200 === $code && ! empty( $data['id'] ) );
	t_ok( "$provider: bip21 with amount passed to the browser", $expected === ( $data['bip21'] ?? '' ) );
	t_ok( "$provider: on-chain address passed to the browser", '' !== ( $data['onchainAddress'] ?? '' ) );
	t_ok( "$provider: checkout link passed to the browser", 0 === strpos( (string) ( $data['checkoutLink'] ?? '' ), 'https://pay.test/i/' ) );
	if ( 'coinsnap' === $provider ) {
		t_ok( 'coinsnap: BIP21 QR passed to the browser', 'data:image/png;base64,QklQMjE=' === ( $data['qrCodes']['bip21QR'] ?? '' ) );
	}
	t_ok( "$provider: no API key in the answer", false === strpos( wp_json_encode( $data ), 'test-key' ) );
}
$GLOBALS['t_provider'] = 'coinsnap';

// The provider refuses the amount (e.g. dust on a store without Lightning): its reason is shown, as text.
list( $code, $data ) = t_invoice( $cf_id, 123 );
t_ok( 'refused invoice: 400 with the provider\'s reason', 400 === $code && false !== strpos( (string) ( $data['message'] ?? '' ), 'dust threshold' ) );
t_ok( 'refused invoice: no HTML from the provider', false === strpos( (string) ( $data['message'] ?? '' ), '<b>' ) );

/* 2. Exchange rates: cached, last good value on error, no rate needed for SATS. */
delete_option( 'coinsnap_bitcoin_crowdfunding_rates_last' );
delete_transient( 'coinsnap_bitcoin_crowdfunding_rates' );
delete_transient( 'coinsnap_bitcoin_crowdfunding_rates_failed' );
$client = new Coinsnap_Bitcoin_Crowdfunding_Client();
$GLOBALS['t_rates'] = 200;
$GLOBALS['t_calls']['rates'] = 0;
$a = $client->loadExchangeRates();
$b = $client->loadExchangeRates();
t_ok( 'rates: second page load uses the cache', ! empty( $a['result'] ) && ! empty( $b['result'] ) && 1 === $GLOBALS['t_calls']['rates'] );

delete_transient( 'coinsnap_bitcoin_crowdfunding_rates' ); // cache expired
$GLOBALS['t_rates'] = 429;
$c = $client->loadExchangeRates();
t_ok( 'rates: on 429 the last good rates are used', ! empty( $c['result'] ) && 50000 == ( $c['data']['eur']['value'] ?? 0 ) );
$client->loadExchangeRates();
t_ok( 'rates: after a 429 CoinGecko is not asked again right away', 2 === $GLOBALS['t_calls']['rates'] );

delete_option( 'coinsnap_bitcoin_crowdfunding_rates_last' );
delete_transient( 'coinsnap_bitcoin_crowdfunding_rates' );
$d = $client->loadExchangeRates();
$client->loadExchangeRates();
t_ok( 'rates: no rates at all gives an error key', empty( $d['result'] ) && 'ratesLoadingError' === ( $d['error'] ?? '' ) );
t_ok( 'rates: failure is cached for a minute', 3 === $GLOBALS['t_calls']['rates'] );

$sats = $client->checkPaymentData( 1000.0, 'SATS' );
t_ok( 'SATS amount accepted without rates (Coinsnap)', ! empty( $sats['result'] ) );
$ln = $client->checkPaymentData( 1000.0, 'SATS', 'lightning' );
$oc = $client->checkPaymentData( 1000.0, 'SATS', 'bitcoin' );
t_ok( 'SATS amount accepted without rates (BTCPay Lightning and on-chain)', ! empty( $ln['result'] ) && ! empty( $oc['result'] ) );
$eur = $client->checkPaymentData( 10.0, 'EUR' );
t_ok( 'EUR amount without rates is refused', empty( $eur['result'] ) && 'ratesLoadingError' === ( $eur['error'] ?? '' ) );

/* 3. Amount check: messages for error keys, no notices while reading the POST. */
$plugin = ( new ReflectionClass( 'Coinsnap_Bitcoin_Crowdfunding' ) )->newInstanceWithoutConstructor();
if ( method_exists( 'Coinsnap_Bitcoin_Crowdfunding', 'amountCheckMessage' ) ) {
	$msg = new ReflectionMethod( 'Coinsnap_Bitcoin_Crowdfunding', 'amountCheckMessage' );
	$msg->setAccessible( true );
	$text = $msg->invoke( $plugin, $eur, 'EUR' );
	t_ok( 'ratesLoadingError shown as a sentence', '' !== $text && false === strpos( $text, 'ratesLoadingError' ) );
	t_ok( 'success has no error message', '' === $msg->invoke( $plugin, array( 'result' => true, 'rate' => 1 ), 'SATS' ) );
	t_ok( 'amountError shows the minimum', false !== strpos( $msg->invoke( $plugin, array( 'result' => false, 'error' => 'amountError', 'min_value' => 0.01 ), 'EUR' ), '0.01 EUR' ) );
} else {
	t_ok( 'error keys are turned into messages', false );
}

$notices = array();
set_error_handler( function ( $no, $str, $file ) use ( &$notices ) {
	if ( false !== strpos( $file, 'coinsnap-bitcoin-crowdfunding' ) ) {
		$notices[] = $str;
	}
	return true;
} );
$die = function () {
	return function () {
		throw new RuntimeException( 'wp_die' );
	};
};
add_filter( 'wp_die_handler', $die );
add_filter( 'wp_die_ajax_handler', $die );
try {
	$plugin->coinsnapAmountCheck(); // no nonce in CLI: stops at the nonce check
} catch ( RuntimeException $e ) {
	// expected
}
restore_error_handler();
remove_filter( 'wp_die_handler', $die );
remove_filter( 'wp_die_ajax_handler', $die );
t_ok( 'amount check reads its input without PHP notices', array() === $notices );
if ( $notices ) {
	echo '     ' . implode( "\n     ", array_unique( $notices ) ) . "\n";
}

/* 4. Status check: confirmed from the API when no webhook arrives. */
$GLOBALS['t_rates'] = 200;
list( , $p1 ) = t_invoice( $cf_id, 6000 );
$inv = (string) ( $p1['id'] ?? '' );
$GLOBALS['t_invoices'][ $inv ]['status'] = 'Settled';
list( $st, $secs ) = t_status( $inv );
$row = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE payment_id = %s", $inv ) );
t_ok( 'status: Settled invoice without webhook is confirmed', 'completed' === $st && 'completed' === $row && $secs < 3 );
t_ok( 'status: donor post created once', 1 === t_donor_posts( $inv ) );
delete_transient( 'coinsnap_bitcoin_crowdfunding_poll_' . md5( $inv ) );
list( $st ) = t_status( $inv );
t_ok( 'status: asking again creates nothing twice', 'completed' === $st && 1 === t_donor_posts( $inv ) );

// Wrong amount at the provider: not counted.
list( , $p2 ) = t_invoice( $cf_id, 7000 );
$inv2 = (string) ( $p2['id'] ?? '' );
$GLOBALS['t_invoices'][ $inv2 ]['status'] = 'Settled';
$GLOBALS['t_invoices'][ $inv2 ]['amount'] = '1';
list( $st ) = t_status( $inv2 );
t_ok( 'status: Settled with a different amount stays pending', 'pending' === $st && 0 === t_donor_posts( $inv2 ) );

// Throttle: one API call per invoice every 10 seconds.
list( , $p3 ) = t_invoice( $cf_id, 8000 );
$inv3 = (string) ( $p3['id'] ?? '' );
$GLOBALS['t_calls']['invoice_get'] = 0;
t_status( $inv3 ); // New: pending
$GLOBALS['t_invoices'][ $inv3 ]['status'] = 'Settled';
list( $st ) = t_status( $inv3 );
t_ok( 'status: API asked at most once per 10 seconds', 'pending' === $st && 1 === $GLOBALS['t_calls']['invoice_get'] );
delete_transient( 'coinsnap_bitcoin_crowdfunding_poll_' . md5( $inv3 ) );
list( $st ) = t_status( $inv3 );
t_ok( 'status: confirmed on the next allowed check', 'completed' === $st );

// Unknown invoice: no API call.
$GLOBALS['t_calls']['invoice_get'] = 0;
list( $st ) = t_status( 'notOurs123' );
t_ok( 'status: invoice without a row is not looked up', 'pending' === $st && 0 === $GLOBALS['t_calls']['invoice_get'] );

// The webhook still works after the shared code moved.
list( , $p4 ) = t_invoice( $cf_id, 9000 );
$inv4 = (string) ( $p4['id'] ?? '' );
$GLOBALS['t_invoices'][ $inv4 ]['status'] = 'Settled';
$body = wp_json_encode( array( 'invoiceId' => $inv4, 'type' => 'Settled' ) );
$wh   = new WP_REST_Request( 'POST', '/coinsnap-bitcoin-crowdfunding/v1/webhook' );
$wh->set_body( $body );
$ts = time();
$wh->set_header( 'X-Coinsnap-Signature', 't=' . $ts . ',v1=' . hash_hmac( 'sha256', $ts . '.' . $body, 'test-secret-coinsnap' ) );
$whr = rest_do_request( $wh );
t_ok( 'webhook: signed Settled webhook still counts the donation once', 200 === $whr->get_status() && 1 === t_donor_posts( $inv4 )
	&& 'completed' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE payment_id = %s", $inv4 ) ) );
$bad = new WP_REST_Request( 'POST', '/coinsnap-bitcoin-crowdfunding/v1/webhook' );
$bad->set_body( $body );
$bad->set_header( 'X-Coinsnap-Signature', 't=' . $ts . ',v1=' . str_repeat( '0', 64 ) );
t_ok( 'webhook: bad signature still refused', 401 === rest_do_request( $bad )->get_status() || 403 === rest_do_request( $bad )->get_status() );

echo $GLOBALS['t_fail'] ? $GLOBALS['t_fail'] . " failed\n" : "all passed\n";
if ( $GLOBALS['t_fail'] ) {
	exit( 1 );
}
