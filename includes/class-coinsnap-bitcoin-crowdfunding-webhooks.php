<?php
if (!defined('ABSPATH')){ exit; }

class Coinsnap_Bitcoin_Crowdfunding_Webhooks {

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_webhook_endpoint']);
        add_action('rest_api_init', [$this, 'register_invoice_endpoint']);
        add_action('rest_api_init', [$this, 'register_check_payment_endpoint']);
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'crowdfunding_payments';
    }

    /* ---------------------------------------------------------------------
     * Public: payment status. Open on purpose, returns paid yes/no only.
     * ------------------------------------------------------------------- */

    public function register_check_payment_endpoint()
    {
        register_rest_route('crowdfunding/v1', '/check-payment-status/(?P<payment_id>[A-Za-z0-9_-]{1,64})', [
            'methods' => 'GET',
            'callback' => [$this, 'get_check_payment_status'],
            'permission_callback' => '__return_true', // public: returns only the status of one invoice
        ]);
    }

    function get_check_payment_status($request)
    {
        global $wpdb;
        $payment_id = (string) $request['payment_id'];
        $start_time = time();
        $timeout = 5;

        while (time() - $start_time < $timeout) {
            $status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM %i WHERE payment_id = %s",
                $this->table(), $payment_id
            ));
            if ($status === 'completed') {
                return ['status' => 'completed'];
            }
            sleep(1);
        }
        return ['status' => 'pending'];
    }

    /* ---------------------------------------------------------------------
     * Public: start a donation. The browser sends the crowdfunding ID, the
     * amount in sats and the donor's form fields; the server creates the invoice.
     * ------------------------------------------------------------------- */

    public function register_invoice_endpoint()
    {
        register_rest_route('coinsnap-bitcoin-crowdfunding/v1', '/invoice', [
            'methods' => 'POST',
            'callback' => [$this, 'create_invoice'],
            'permission_callback' => '__return_true', // public: visitors donate without an account
        ]);
    }

    private function field(array $data, string $key, int $max = 255): string
    {
        $value = $data[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }
        return mb_substr(sanitize_text_field((string) $value), 0, $max);
    }

    public function create_invoice(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            return new WP_Error('bad_request', 'Bad request', ['status' => 400]);
        }

        $crowdfunding_id = (int) ($data['crowdfunding_id'] ?? 0);
        if ($crowdfunding_id <= 0 || get_post_type($crowdfunding_id) !== 'coinsnap-cfs' || get_post_status($crowdfunding_id) !== 'publish'
            || !get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfunding_active', true)) {
            return new WP_Error('not_found', 'Not found', ['status' => 404]);
        }

        $amount = $data['amount'] ?? null;
        $sats = (is_int($amount) || (is_string($amount) && ctype_digit($amount))) ? (int) $amount : 0;
        if (is_float($amount) && floor($amount) === $amount) {
            $sats = (int) $amount;
        }
        if ($sats < 1 || $sats > 2100000000000000) {
            return new WP_Error('invalid_amount', __('Invalid amount', 'coinsnap-bitcoin-crowdfunding'), ['status' => 400]);
        }

        $cfg = Coinsnap_Bitcoin_Crowdfunding_Security::config();
        if (!Coinsnap_Bitcoin_Crowdfunding_Security::is_configured($cfg)) {
            return new WP_Error('unavailable', 'Unavailable', ['status' => 503]);
        }

        $min = $this->minimum_sats($cfg);
        if ($min === null) {
            return new WP_Error('unavailable', 'Unavailable', ['status' => 503]);
        }
        if ($sats < $min) {
            return new WP_Error('invalid_amount', sprintf(
                /* translators: 1: minimum amount in sats */
                __('Invoice amount cannot be less than %1$s SATS', 'coinsnap-bitcoin-crowdfunding'), $min), ['status' => 400]);
        }

        // Donor fields are accepted only when this crowdfunding collects them.
        $collect = (bool) get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfunding_collect_donor_info', true);
        $shoutout = (bool) get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfunding_shoutout', true);
        $donor = [];
        if ($collect) {
            $visibility = [
                'first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email',
                'street' => 'address', 'house_number' => 'address', 'postal' => 'address', 'town' => 'address', 'country' => 'address',
                'custom' => 'custom_field',
            ];
            foreach ($visibility as $key => $metaKey) {
                $mode = (string) get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfunding_' . $metaKey . '_visibility', true);
                if ($mode === '' || $mode === 'hidden') {
                    continue;
                }
                $value = $key === 'email' ? sanitize_email((string) ($data['email'] ?? '')) : $this->field($data, $key);
                if ($mode === 'mandatory' && $value === '') {
                    return new WP_Error('missing_field', __('Please fill in the required fields', 'coinsnap-bitcoin-crowdfunding'), ['status' => 400]);
                }
                $donor[$key] = $value;
            }
        }
        $public_name = $shoutout ? $this->field($data, 'public_name') : '';
        $public_message = $shoutout ? $this->field($data, 'public_message', 511) : '';

        $title = get_the_title($crowdfunding_id);
        $metadata = [
            'type'            => 'Bitcoin Crowdfunding',
            'crowdfunding_id' => (string) $crowdfunding_id,
            'crowdfundName'   => $title,
            'orderNumber'     => 'Donated for Crowdfunding',
        ];

        $invoice = Coinsnap_Bitcoin_Crowdfunding_Security::create_invoice($cfg, $sats, $metadata, (string) ($donor['email'] ?? ''));
        if ($invoice === null) {
            return new WP_Error('unavailable', 'Unavailable', ['status' => 503]);
        }

        global $wpdb;
        $wpdb->insert($this->table(), [
            'payment_id'      => $invoice['id'],
            'crowdfunding_id' => (string) $crowdfunding_id,
            'amount'          => $sats,
            'currency'        => 'SATS',
            'name'            => $public_name,
            'message'         => $public_message,
            'donor_data'      => wp_json_encode(['public_donor' => ($collect || $shoutout) ? 1 : 0, 'donor' => $donor]),
            'status'          => 'pending',
        ], ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']);

        return $invoice;
    }

    /** Minimum donation in sats for the configured provider, or null if it can't be checked. */
    private function minimum_sats(array $cfg): ?int
    {
        if ($cfg['provider'] !== 'btcpay') {
            return 1;
        }
        $client = new Coinsnap_Bitcoin_Crowdfunding_Client();
        $methods = $client->getStorePaymentMethods($cfg['url'], $cfg['api_key'], $cfg['store_id']);
        if (($methods['code'] ?? 0) !== 200) {
            return null;
        }
        $kind = !empty($methods['result']['lightning']) ? 'lightning' : (!empty($methods['result']['onchain']) ? 'bitcoin' : '');
        if ($kind === '') {
            return null;
        }
        $check = $client->checkPaymentData(0, 'SATS', $kind, 'calculation');
        if (empty($check['result'])) {
            return null;
        }
        return max(1, (int) ceil((float) $check['min_value']));
    }

    /* ---------------------------------------------------------------------
     * Webhook
     * ------------------------------------------------------------------- */

    public function register_webhook_endpoint()
    {
        register_rest_route('coinsnap-bitcoin-crowdfunding/v1', '/webhook', [
            'methods'  => ['POST'],
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => ['Coinsnap_Bitcoin_Crowdfunding_Security', 'check_webhook_signature'],
        ]);
    }

    public function handle_webhook(WP_REST_Request $request)
    {
        $data = json_decode($request->get_body(), true);
        $invoiceId = (is_array($data) && is_string($data['invoiceId'] ?? null)) ? $data['invoiceId'] : '';
        if ($invoiceId === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $invoiceId)) {
            return new WP_REST_Response(null, 200);
        }
        $claimsPaid = in_array($data['type'] ?? '', ['Settled', 'InvoiceSettled'], true);

        $cfg = Coinsnap_Bitcoin_Crowdfunding_Security::config();
        try {
            $invoice = Coinsnap_Bitcoin_Crowdfunding_Security::fetch_invoice($cfg, $invoiceId);
        } catch (RuntimeException $e) {
            Coinsnap_Bitcoin_Crowdfunding_Security::log('info', 'retry: API unavailable', $invoiceId);
            return new WP_REST_Response(null, 503);
        }
        if ($invoice === null || ($invoice['id'] ?? '') !== $invoiceId) {
            Coinsnap_Bitcoin_Crowdfunding_Security::log('info', 'unknown invoice', $invoiceId);
            return new WP_REST_Response(null, 200);
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE payment_id = %s", $this->table(), $invoiceId), ARRAY_A);
        if (!$row) {
            $row = $this->legacy_row($invoice);
        }
        $metaId = (string) ($invoice['metadata']['crowdfunding_id'] ?? '');
        if (!$row || $metaId !== (string) $row['crowdfunding_id']) {
            Coinsnap_Bitcoin_Crowdfunding_Security::log('info', 'not ours', $invoiceId);
            return new WP_REST_Response(null, 200);
        }

        if (!Coinsnap_Bitcoin_Crowdfunding_Security::invoice_pays($invoice, (int) $row['amount'], (string) $row['currency'], $cfg['provider'] === 'btcpay')) {
            $forged = $claimsPaid && ($invoice['additionalStatus'] ?? '') !== 'Marked';
            Coinsnap_Bitcoin_Crowdfunding_Security::log($forged ? 'security' : 'info',
                $forged ? 'forged status' : 'status ' . preg_replace('/[^A-Za-z]/', '', (string) ($invoice['status'] ?? '?')), $invoiceId);
            return new WP_REST_Response(null, 200);
        }

        // Act once, and only move pending -> completed.
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE %i SET status = 'completed' WHERE payment_id = %s AND status = 'pending'",
            $this->table(), $invoiceId
        ));
        if ($updated !== 1) {
            Coinsnap_Bitcoin_Crowdfunding_Security::log('info', 'already processed', $invoiceId);
            return new WP_REST_Response(null, 200);
        }

        $this->create_donor_post($row, $invoiceId);
        return new WP_REST_Response(null, 200);
    }

    /**
     * Invoices created by the browser before this version have no row. They are
     * accepted only during a short window after the upgrade (6.7), with values
     * read from the API's answer.
     */
    private function legacy_row(array $invoice): ?array
    {
        $upgradedAt = (int) get_option(Coinsnap_Bitcoin_Crowdfunding_Security::UPGRADED_AT, 0);
        if ($upgradedAt <= 0 || time() > $upgradedAt + Coinsnap_Bitcoin_Crowdfunding_Security::LEGACY_WINDOW) {
            return null;
        }
        $meta = is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : [];
        $created = (int) ($invoice['createdAt'] ?? $invoice['createdTime'] ?? 0);
        $cfId = (int) ($meta['crowdfunding_id'] ?? 0);
        if (($meta['type'] ?? '') !== 'Bitcoin Crowdfunding' || $cfId <= 0 || get_post_type($cfId) !== 'coinsnap-cfs'
            || $created <= 0 || $created > $upgradedAt) {
            return null;
        }
        $paid = (string) ($invoice['amount'] ?? '');
        if (strtoupper((string) ($invoice['currency'] ?? '')) !== 'SATS' || !preg_match('/^\d+(?:\.0+)?$/', $paid) || (int) $paid <= 0) {
            return null;
        }

        $donor = [];
        if (!empty($meta['publicDonor']) && (string) $meta['publicDonor'] === '1') {
            $donor = [
                'legacy_name'    => (string) ($meta['donorName'] ?? ''),
                'email'          => (string) ($meta['donorEmail'] ?? ''),
                'legacy_address' => (string) ($meta['donorAddress'] ?? ''),
                'legacy_custom'  => (string) ($meta['donorCustom'] ?? ''),
            ];
        }

        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO %i (payment_id, crowdfunding_id, amount, currency, name, message, donor_data, status) VALUES (%s, %s, %d, 'SATS', %s, %s, %s, 'pending')",
            $this->table(), (string) $invoice['id'], (string) $cfId, (int) $paid,
            mb_substr(sanitize_text_field((string) ($meta['name'] ?? '')), 0, 255),
            mb_substr(sanitize_text_field((string) ($meta['message'] ?? '')), 0, 511),
            wp_json_encode(['public_donor' => $donor ? 1 : 0, 'donor' => $donor])
        ));
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE payment_id = %s", $this->table(), (string) $invoice['id']), ARRAY_A);
    }

    /** Donor details come from the row the server stored, never from the webhook body. */
    private function create_donor_post(array $row, string $invoiceId): void
    {
        $stored = json_decode((string) ($row['donor_data'] ?? ''), true);
        if (!is_array($stored) || empty($stored['public_donor'])) {
            return;
        }
        $d = is_array($stored['donor'] ?? null) ? $stored['donor'] : [];

        $name = trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
        if ($name === '') {
            $name = (string) ($d['legacy_name'] ?? '');
        }
        $shoutoutName = (string) ($row['name'] ?? '');
        $message = (string) ($row['message'] ?? '');
        if (trim($name) === '') {
            $name = ($shoutoutName !== '' || $message !== '')
                ? ($shoutoutName === '' ? 'Shoutout from Anonymous' : 'Shoutout from ' . $shoutoutName)
                : 'Anonymous';
        }

        $address = (string) ($d['legacy_address'] ?? '');
        if ($address === '' && (($d['street'] ?? '') . ($d['town'] ?? '') . ($d['country'] ?? '')) !== '') {
            $address = sprintf('%s %s, %s %s, %s', $d['street'] ?? '', $d['house_number'] ?? '', $d['postal'] ?? '', $d['town'] ?? '', $d['country'] ?? '');
        }
        $custom = (string) ($d['legacy_custom'] ?? '');
        if ($custom === '' && ($d['custom'] ?? '') !== '') {
            $customName = (string) get_post_meta((int) $row['crowdfunding_id'], '_coinsnap_bitcoin_crowdfunding_custom_field_name', true);
            $custom = $customName !== '' ? $customName . ': ' . $d['custom'] : $d['custom'];
        }

        $post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($name),
            'post_status'  => 'publish',
            'post_type'    => 'coinsnap-cf-donors',
            'post_content' => sanitize_textarea_field($message),
        ]);
        if (!$post_id || is_wp_error($post_id)) {
            return;
        }
        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_donor_name', sanitize_text_field($name));
        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_amount', (string) (int) $row['amount']);
        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_message', sanitize_text_field($message));
        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_form_type', 'Bitcoin Crowdfunding');
        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_dont_show', '0');
        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_email', sanitize_email((string) ($d['email'] ?? '')));
        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_address', sanitize_text_field($address));
        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_payment_id', sanitize_text_field($invoiceId));
        update_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_custom_field', sanitize_text_field($custom));
    }
}
new Coinsnap_Bitcoin_Crowdfunding_Webhooks();
