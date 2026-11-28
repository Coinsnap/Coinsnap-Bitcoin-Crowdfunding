<?php
/**
 * Webhook registration test, run inside a WordPress with the plugin active:
 *   wp eval-file wp-content/plugins/coinsnap-bitcoin-crowdfunding/tests/webhook-register-test.php
 *
 * Coinsnap answers GET /stores/{id}/webhooks with 400 {"message":"No webhooks found."} when the
 * store has no webhook yet (a new store). That is an empty list: the plugin must go on and create
 * its webhook. Real API errors (401, 5xx, network) must still never create one.
 * The API is faked with pre_http_request; nothing leaves the site. Settings and the webhook
 * secrets are replaced through option filters only (never written).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['t_fail']    = 0;
$GLOBALS['t_secrets'] = array();
$GLOBALS['t_list']    = array( 400, array( 'message' => 'No webhooks found.' ) );
$GLOBALS['t_calls']   = array();
function t_ok( $name, $cond ) {
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $name . "\n";
	$GLOBALS['t_fail'] += $cond ? 0 : 1;
}
function t_resp( $code, $body ) {
	return array( 'response' => array( 'code' => $code, 'message' => '' ), 'body' => wp_json_encode( $body ), 'headers' => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( array() ), 'cookies' => array() );
}

add_filter( 'pre_option_coinsnap_bitcoin_crowdfunding_options', function () {
	return array( 'provider' => 'coinsnap', 'coinsnap_api_key' => 'test-key', 'coinsnap_store_id' => 'STORE1' );
} );
// Secrets stay in memory: reads come from the global, writes go to it and never reach the database.
add_filter( 'pre_option_coinsnap_bitcoin_crowdfunding_webhook_secrets', function () {
	return $GLOBALS['t_secrets'];
} );
add_filter( 'pre_update_option_coinsnap_bitcoin_crowdfunding_webhook_secrets', function ( $value, $old ) {
	$GLOBALS['t_secrets'] = $value;
	return $old;
}, 10, 2 );

// Fake Coinsnap webhook API, shaped like routes/api/webhook.js.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( 0 !== strpos( $url, 'https://app.coinsnap.io/api/v1/stores/STORE1/webhooks' ) ) {
		return t_resp( 404, array() );
	}
	$method                = strtoupper( $args['method'] ?? 'GET' );
	$body                  = isset( $args['body'] ) ? json_decode( (string) $args['body'], true ) : null;
	$GLOBALS['t_calls'][]  = array( $method, substr( $url, strlen( 'https://app.coinsnap.io/api/v1/stores/STORE1' ) ), $body );
	if ( 'GET' === $method ) {
		return t_resp( $GLOBALS['t_list'][0], $GLOBALS['t_list'][1] );
	}
	if ( 'POST' === $method ) {
		return t_resp( 200, array( 'id' => '101', 'secret' => $body['secret'], 'url' => $body['url'], 'events' => $body['events'] ) );
	}
	return t_resp( 200, array( 'id' => '55', 'enabled' => 1, 'url' => $body['url'], 'events' => $body['events'] ) );
}, 10, 3 );

$sec = 'Coinsnap_Bitcoin_Crowdfunding_Security';
$url = $sec::webhook_url();
$writes = function () {
	return array_values( array_filter( $GLOBALS['t_calls'], function ( $c ) { return 'GET' !== $c[0]; } ) );
};

// 1. New store: Coinsnap says 400 "No webhooks found." -> create the webhook.
$ok = $sec::register_webhook();
$w  = $writes();
t_ok( 'new store (400 No webhooks found.): webhook is registered', true === $ok );
t_ok( 'new store: one POST /webhooks with this site URL and the Settled event',
	1 === count( $w ) && 'POST' === $w[0][0] && '/webhooks' === $w[0][1] && $url === ( $w[0][2]['url'] ?? '' ) && array( 'Settled' ) === ( $w[0][2]['events'] ?? null ) );
t_ok( 'new store: the secret sent is the secret stored, status reads connected',
	1 === count( $w ) && '' !== $sec::get_secret( $sec::config() ) && $sec::get_secret( $sec::config() ) === ( $w[0][2]['secret'] ?? null ) );

// 2. API errors are still not "no webhook".
foreach ( array( array( 401, array( 'message' => 'Unauthorized' ) ), array( 500, array( 'error' => 'x' ) ), array( 429, array( 'message' => 'Too many' ) ), array( 200, 'not a list' ) ) as $case ) {
	$GLOBALS['t_secrets'] = array();
	$GLOBALS['t_calls']   = array();
	$GLOBALS['t_list']    = $case;
	$ok                   = $sec::register_webhook();
	t_ok( 'list answers ' . $case[0] . ( 200 === $case[0] ? ' without a list' : '' ) . ': nothing created, not connected',
		false === $ok && array() === $writes() && '' === $sec::get_secret( $sec::config() ) );
}

// 3. The webhook is already there: update it, don't create a second one.
$GLOBALS['t_secrets'] = array();
$GLOBALS['t_calls']   = array();
$GLOBALS['t_list']    = array( 200, array( array( 'id' => '55', 'enabled' => 1, 'url' => $url, 'events' => array( 'Settled' ) ) ) );
$ok                   = $sec::register_webhook();
$w                    = $writes();
t_ok( 'existing webhook: updated with PUT /webhooks/55, no POST', true === $ok && 1 === count( $w ) && 'PUT' === $w[0][0] && '/webhooks/55' === $w[0][1] );

echo $GLOBALS['t_fail'] ? $GLOBALS['t_fail'] . " failed\n" : "all passed\n";
if ( $GLOBALS['t_fail'] ) {
	exit( 1 );
}
