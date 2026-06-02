<?php
if (!defined('ABSPATH')) {
    exit;
}

class Coinsnap_Bitcoin_Crowdfunding_Metabox
{
    public function __construct()
    {
        add_action('init', [$this, 'register_crowdfundings_post_type']);
        add_action('init', [$this, 'register_custom_meta_fields']);
        add_action('add_meta_boxes', [$this, 'add_crowdfundings_metaboxes']);
        add_action('save_post', [$this, 'save_crowdfundings_meta'], 10, 2);
        add_filter('manage_coinsnap-cfs_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_coinsnap-cfs_posts_custom_column', [$this, 'populate_custom_columns'], 10, 2);
    }

    public function register_crowdfundings_post_type()
    {
        register_post_type('coinsnap-cfs', [
            'labels' => [
                'name'               => __('Crowdfundings','coinsnap-bitcoin-crowdfunding'),
                'singular_name'      => __('Crowdfunding','coinsnap-bitcoin-crowdfunding'),
                'menu_name'          => __('Crowdfundings','coinsnap-bitcoin-crowdfunding'),
                'add_new'            => __('Add New','coinsnap-bitcoin-crowdfunding'),
                'add_new_item'       => __('Add New Crowdfunding','coinsnap-bitcoin-crowdfunding'),
                'edit_item'          => __('Edit Crowdfunding','coinsnap-bitcoin-crowdfunding'),
                'new_item'           => __('New Crowdfunding','coinsnap-bitcoin-crowdfunding'),
                'view_item'          => __('View Crowdfunding','coinsnap-bitcoin-crowdfunding'),
                'search_items'       => __('Search Crowdfundings','coinsnap-bitcoin-crowdfunding'),
                'not_found'          => __('No crowdfundings found','coinsnap-bitcoin-crowdfunding'),
                'not_found_in_trash' => __('No crowdfundings found in Trash','coinsnap-bitcoin-crowdfunding'),
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'coinsnap-cfs'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => ['title'],
            'show_in_rest'       => true
        ]);
    }

    public function register_custom_meta_fields()
    {
        register_meta('post', '_coinsnap_bitcoin_crowdfunding_description', [
            'object_subtype' => 'coinsnap-cfs',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_amount', [
            'object_subtype' => 'coinsnap-cfs',
            'type' => 'number',
            'single' => true,
            'show_in_rest' => true,
            'description' => 'Amount in satoshis',
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_thank_you_message', [
            'object_subtype' => 'coinsnap-cfs',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_active', [
            'object_subtype' => 'coinsnap-cfs',
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_collect_donor_info', [
            'object_subtype' => 'coinsnap-cfs',
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_default_value_1', [
            'object_subtype' => 'coinsnap-cfs',
            'type' => 'number',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_default_value_2', [
            'object_subtype' => 'coinsnap-cfs',
            'type' => 'number',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_default_value_3', [
            'object_subtype' => 'coinsnap-cfs',
            'type' => 'number',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_custom_field_name', [
            'object_subtype' => 'coinsnap-cfs',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_default_currency', [
            'object_subtype' => 'coinsnap-cfs',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_shoutout', [
            'object_subtype' => 'coinsnap-cfs',
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
        ]);
    }

    public function add_crowdfundings_metaboxes()
    {
        add_meta_box(
            'coinsnap_bitcoin_crowdfunding_details',
            'Crowdfundings Details',
            [$this, 'render_crowdfundings_metabox'],
            'coinsnap-cfs',
            'normal',
            'high'
        );
    }

    public function render_crowdfundings_metabox($post)
    {
        wp_nonce_field('coinsnap_bitcoin_crowdfunding_nonce', 'coinsnap_bitcoin_crowdfunding_nonce');
        $client = new Coinsnap_Bitcoin_Crowdfunding_Client();

        $description = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_description', true);
        $amount = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_amount', true);
        $thank_you_message = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_thank_you_message', true);
        $active = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_active', true);
        $collect_donor_info = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_collect_donor_info', true);
        $default_value_1 = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_default_value_1', true);
        $default_value_2 = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_default_value_2', true);
        $default_value_3 = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_default_value_3', true);
        $custom_field_name = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_custom_field_name', true);
        $default_currency = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_default_currency', true) ?: 'sats';
        $shoutout = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_shoutout', true);
        $donor_fields = [
            'first_name' => __('First Name', 'coinsnap-bitcoin-crowdfunding'),
            'last_name' => __('Last Name', 'coinsnap-bitcoin-crowdfunding'),
            'email' => __('Email', 'coinsnap-bitcoin-crowdfunding'),
            'address' => __('Address', 'coinsnap-bitcoin-crowdfunding'),
            'custom_field' => __('Custom Field', 'coinsnap-bitcoin-crowdfunding'),
        ];

        $field_values = [];
        foreach ($donor_fields as $field => $label) {
            $field_values[$field] = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_' . $field, true);
        }
        
        $coinsnapCurrencies = $client->getCurrencies();
        if ( empty( $currency ) ) { $currency = 'EUR'; }

?>
        <div class="coinsnap-core-admin">

            <div class="csc-connection-badge" id="csc-connection-badge">
                <span class="csc-status-dot"></span>
                <span class="csc-connection-text"><?php esc_html_e( 'Checking...', 'coinsnap-bitcoin-crowdfunding' ); ?></span>
            </div>

            <!-- General -->
            <div class="csc-card">
                <div class="csc-card-header">
                    <h2><?php esc_html_e( 'General', 'coinsnap-bitcoin-crowdfunding' ); ?></h2>
                </div>
                <div class="csc-card-body">

                    <div class="csc-field-row">
                        <label>
                            <input type="checkbox" name="coinsnap_bitcoin_crowdfunding_active" value="1" <?php checked( $active, '1' ); ?>>
                            <?php esc_html_e( 'Active', 'coinsnap-bitcoin-crowdfunding' ); ?>
                        </label>
                        <p class="csc-field-description"><?php esc_html_e( 'Enable this crowdfunding campaign on your site.', 'coinsnap-bitcoin-crowdfunding' ); ?></p>
                    </div>

                    <div class="csc-field-row">
                        <label for="coinsnap_bitcoin_crowdfunding_description"><?php esc_html_e( 'Description', 'coinsnap-bitcoin-crowdfunding' ); ?></label>
                        <textarea
                            id="coinsnap_bitcoin_crowdfunding_description"
                            name="coinsnap_bitcoin_crowdfunding_description"
                            rows="3"
                            required><?php echo esc_textarea( $description ); ?></textarea>
                    </div>

                    <div class="csc-field-row">
                        <label for="coinsnap_bitcoin_crowdfunding_amount"><?php esc_html_e( 'Goal Amount (in satoshis)', 'coinsnap-bitcoin-crowdfunding' ); ?></label>
                        <input
                            type="number"
                            id="coinsnap_bitcoin_crowdfunding_amount"
                            name="coinsnap_bitcoin_crowdfunding_amount"
                            required
                            value="<?php echo esc_attr( $amount ); ?>"
                            min="0"
                            step="1">
                    </div>

                    <div class="csc-field-row">
                        <label for="coinsnap_bitcoin_crowdfunding_thank_you_message"><?php esc_html_e( 'Thank You Message', 'coinsnap-bitcoin-crowdfunding' ); ?></label>
                        <textarea
                            id="coinsnap_bitcoin_crowdfunding_thank_you_message"
                            name="coinsnap_bitcoin_crowdfunding_thank_you_message"
                            rows="3"
                            required><?php echo esc_textarea( $thank_you_message ); ?></textarea>
                    </div>

                </div>
            </div>

            <!-- Display -->
            <div class="csc-card">
                <div class="csc-card-header">
                    <h2><?php esc_html_e( 'Display', 'coinsnap-bitcoin-crowdfunding' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Configure the currency and preset amounts shown to donors.', 'coinsnap-bitcoin-crowdfunding' ); ?></p>
                </div>
                <div class="csc-card-body">

                    <div class="csc-field-row">
                        <label for="coinsnap_bitcoin_crowdfunding_default_currency"><?php esc_html_e( 'Default Currency', 'coinsnap-bitcoin-crowdfunding' ); ?></label>
                        <select id="coinsnap_bitcoin_crowdfunding_default_currency" name="coinsnap_bitcoin_crowdfunding_default_currency">
                            <?php for ( $j = 0; $j < count( $coinsnapCurrencies ); $j++ ) :
                                echo '<option value="' . esc_attr( $coinsnapCurrencies[ $j ] ) . '" ' . selected( $default_currency, $coinsnapCurrencies[ $j ], false ) . '>' . esc_html( $coinsnapCurrencies[ $j ] ) . '</option>';
                            endfor; ?>
                        </select>
                    </div>

                    <div class="csc-field-row">
                        <label for="coinsnap_bitcoin_crowdfunding_default_value_1">
                            <?php echo esc_html__( 'Default Value 1', 'coinsnap-bitcoin-crowdfunding' ) . ' ' . esc_html( $default_currency ); ?>
                        </label>
                        <input type="number" id="coinsnap_bitcoin_crowdfunding_default_value_1" name="coinsnap_bitcoin_crowdfunding_default_value_1" class="coinsnap_bitcoin_crowdfunding_amount" value="<?php echo esc_attr( $default_value_1 ); ?>" min="0" step="1">
                    </div>

                    <div class="csc-field-row">
                        <label for="coinsnap_bitcoin_crowdfunding_default_value_2">
                            <?php echo esc_html__( 'Default Value 2', 'coinsnap-bitcoin-crowdfunding' ) . ' ' . esc_html( $default_currency ); ?>
                        </label>
                        <input type="number" id="coinsnap_bitcoin_crowdfunding_default_value_2" name="coinsnap_bitcoin_crowdfunding_default_value_2" class="coinsnap_bitcoin_crowdfunding_amount" value="<?php echo esc_attr( $default_value_2 ); ?>" min="0" step="1">
                    </div>

                    <div class="csc-field-row">
                        <label for="coinsnap_bitcoin_crowdfunding_default_value_3">
                            <?php echo esc_html__( 'Default Value 3', 'coinsnap-bitcoin-crowdfunding' ) . ' ' . esc_html( $default_currency ); ?>
                        </label>
                        <input type="number" id="coinsnap_bitcoin_crowdfunding_default_value_3" name="coinsnap_bitcoin_crowdfunding_default_value_3" class="coinsnap_bitcoin_crowdfunding_amount" value="<?php echo esc_attr( $default_value_3 ); ?>" min="0" step="1">
                    </div>

                </div>
            </div>

            <!-- Shortcode -->
            <div class="csc-card csc-card--compact">
                <div class="csc-card-header">
                    <h2><?php esc_html_e( 'Shortcode', 'coinsnap-bitcoin-crowdfunding' ); ?></h2>
                </div>
                <div class="csc-card-body">
                    <div class="csc-field-row">
                        <label for="shortcode"><?php esc_html_e( 'Embed this shortcode in any page or post:', 'coinsnap-bitcoin-crowdfunding' ); ?></label>
                        <input type="text" id="shortcode" name="shortcode" readonly value='[coinsnap_bitcoin_crowdfunding id="<?php echo esc_attr( $post->ID ); ?>"]'>
                    </div>
                </div>
            </div>

            <!-- Shoutout -->
            <div class="csc-card">
                <div class="csc-card-header">
                    <h2><?php esc_html_e( 'Shoutout', 'coinsnap-bitcoin-crowdfunding' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Display a public list of donors on your page.', 'coinsnap-bitcoin-crowdfunding' ); ?></p>
                </div>
                <div class="csc-card-body">

                    <div class="csc-field-row">
                        <label>
                            <input type="checkbox" name="coinsnap_bitcoin_crowdfunding_shoutout" value="1" <?php checked( $shoutout, '1' ); ?>>
                            <?php esc_html_e( 'Enable shoutout list', 'coinsnap-bitcoin-crowdfunding' ); ?>
                        </label>
                    </div>

                    <div id="shoutout-shortcode-row" class="csc-field-row">
                        <label for="shoutout-shortcode"><?php esc_html_e( 'Shoutout Shortcode', 'coinsnap-bitcoin-crowdfunding' ); ?></label>
                        <input type="text" id="shoutout-shortcode" name="shoutout-shortcode" readonly value='[coinsnap_bitcoin_crowdfunding_shoutout id="<?php echo esc_attr( $post->ID ); ?>"]'>
                    </div>

                </div>
            </div>

            <!-- Donor Information -->
            <div class="csc-card">
                <div class="csc-card-header">
                    <h2><?php esc_html_e( 'Donor Information', 'coinsnap-bitcoin-crowdfunding' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Configure which donor details to collect at checkout.', 'coinsnap-bitcoin-crowdfunding' ); ?></p>
                </div>
                <div class="csc-card-body">

                    <div class="csc-field-row">
                        <label>
                            <input type="checkbox" name="coinsnap_bitcoin_crowdfunding_collect_donor_info" value="1" <?php checked( $collect_donor_info, '1' ); ?>>
                            <?php esc_html_e( 'Collect Donor Info', 'coinsnap-bitcoin-crowdfunding' ); ?>
                        </label>
                    </div>

                    <div id="donor-info-fields">
                        <?php foreach ( $donor_fields as $field => $label ) :
                            $visibility_value = get_post_meta( $post->ID, '_coinsnap_bitcoin_crowdfunding_' . $field . '_visibility', true ) ?: 'optional';
                        ?>
                        <div class="csc-field-row">
                            <label for="coinsnap_bitcoin_crowdfunding_<?php echo esc_attr( $field ); ?>_visibility"><?php echo esc_html( $label ); ?></label>
                            <select id="coinsnap_bitcoin_crowdfunding_<?php echo esc_attr( $field ); ?>_visibility" name="coinsnap_bitcoin_crowdfunding_<?php echo esc_attr( $field ); ?>_visibility">
                                <option value="mandatory" <?php selected( $visibility_value, 'mandatory' ); ?>><?php esc_html_e( 'Mandatory', 'coinsnap-bitcoin-crowdfunding' ); ?></option>
                                <option value="optional" <?php selected( $visibility_value, 'optional' ); ?>><?php esc_html_e( 'Optional', 'coinsnap-bitcoin-crowdfunding' ); ?></option>
                                <option value="hidden" <?php selected( $visibility_value, 'hidden' ); ?>><?php esc_html_e( 'Hidden', 'coinsnap-bitcoin-crowdfunding' ); ?></option>
                            </select>
                        </div>
                        <?php endforeach; ?>

                        <div class="csc-field-row">
                            <label for="coinsnap_bitcoin_crowdfunding_custom_field_name"><?php esc_html_e( 'Custom Field Name', 'coinsnap-bitcoin-crowdfunding' ); ?></label>
                            <input type="text" id="coinsnap_bitcoin_crowdfunding_custom_field_name" name="coinsnap_bitcoin_crowdfunding_custom_field_name" value="<?php echo esc_attr( $custom_field_name ); ?>">
                        </div>
                    </div>

                </div>
            </div>

        </div>
<?php
    }

    public function save_crowdfundings_meta($post_id, $post)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $expected_nonce = 'wp_rest';
            $nonce = (null !== filter_input(INPUT_SERVER,'HTTP_X_WP_NONCE',FILTER_SANITIZE_FULL_SPECIAL_CHARS))? sanitize_text_field(filter_input(INPUT_SERVER,'HTTP_X_WP_NONCE',FILTER_SANITIZE_FULL_SPECIAL_CHARS)) : '';
        } else {
            $expected_nonce = 'coinsnap_bitcoin_crowdfunding_nonce';
            $nonce = filter_input(INPUT_POST, 'coinsnap_bitcoin_crowdfunding_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        if (empty($nonce) || !wp_verify_nonce($nonce, $expected_nonce)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if ($post->post_type !== 'coinsnap-cfs') {
            return;
        }

        $fields = [
            'coinsnap_bitcoin_crowdfunding_description' => 'text',
            'coinsnap_bitcoin_crowdfunding_amount'      => 'number',
            'coinsnap_bitcoin_crowdfunding_thank_you_message' => 'text',
            'coinsnap_bitcoin_crowdfunding_active'      => 'boolean',
            'coinsnap_bitcoin_crowdfunding_collect_donor_info' => 'boolean',
            'coinsnap_bitcoin_crowdfunding_default_value_1' => 'number',
            'coinsnap_bitcoin_crowdfunding_default_value_2' => 'number',
            'coinsnap_bitcoin_crowdfunding_default_value_3' => 'number',
            'coinsnap_bitcoin_crowdfunding_custom_field_name' => 'text',
            'coinsnap_bitcoin_crowdfunding_default_currency' => 'text',
            'coinsnap_bitcoin_crowdfunding_shoutout' => 'boolean',
        ];

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            $required_fields = [
                'coinsnap_bitcoin_crowdfunding_description' => __('Description', 'coinsnap-bitcoin-crowdfunding'),
                'coinsnap_bitcoin_crowdfunding_amount'      => __('Goal Amount', 'coinsnap-bitcoin-crowdfunding'),
            ];

            foreach ($required_fields as $field => $label) {
                $value = isset( $_POST[ $field ] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST[ $field ] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
                if ('' === $value) {
                    wp_die(sprintf(
                        /* translators: 1: Human-readable field label */
                        esc_html__('Error: %1$s is required.', 'coinsnap-bitcoin-crowdfunding'),
                        esc_html($label)
                    ));
                }
            }
        } else {
            $json_body = file_get_contents('php://input');
            $data = json_decode($json_body, true);

            if (isset($data['meta']) && is_array($data['meta'])) {
                $required_meta_fields = [
                    '_coinsnap_bitcoin_crowdfunding_description',
                    '_coinsnap_bitcoin_crowdfunding_amount'
                ];

                foreach ($required_meta_fields as $field) {
                    if (empty($data['meta'][$field])) {
                        return new WP_Error('missing_required_field', "Error: $field is required.", ['status' => 400]);
                    }
                }
            }
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $json_body = file_get_contents('php://input');
            $data = json_decode($json_body, true);

            if (isset($data['meta']) && is_array($data['meta'])) {
                foreach ($fields as $field => $type) {
                    $json_key = '_' . $field;
                    if (isset($data['meta'][$json_key])) {
                        $value = $data['meta'][$json_key];
                        if ($type === 'boolean') {
                            $value = (bool)$value;
                        } elseif ($type === 'number') {
                            $value = floatval($value);
                        } else {
                            $value = sanitize_text_field($value);
                        }
                        update_post_meta($post_id, $json_key, $value);
                    }
                }
            }
            return;
        }

        foreach ($fields as $field => $type) {
            if ($type === 'boolean') {
                $value = (filter_input(INPUT_POST,$field,FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null) ? '1' : '';
                update_post_meta($post_id, '_' . $field, $value);
            } else {
                if (filter_input(INPUT_POST,$field,FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null){
                    $value = filter_input(INPUT_POST,$field,FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    if ($type === 'number') {
                        $value = floatval($value);
                    } else {
                        $value = sanitize_text_field($value);
                    }
                    update_post_meta($post_id, '_' . $field, $value);
                }
            }
        }

        $donor_fields = ['first_name', 'last_name', 'email', 'address', 'custom_field'];
        foreach ($donor_fields as $field) {
            $visibility_field = 'coinsnap_bitcoin_crowdfunding_' . $field . '_visibility';
            if (isset($_POST[$visibility_field])) {
                $value = sanitize_text_field(filter_input(INPUT_POST,$visibility_field,FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                update_post_meta($post_id, '_' . $visibility_field, $value);
            }
        }
    }

    public function add_custom_columns($columns)
    {

        $new_columns = [
            'cb' => $columns['cb'],
            'title' => $columns['title'],
            'shortcode' => 'Shortcode',
            'amount' => 'Amount (satoshis)',
            'thank_you_message' => 'Thank You Message',
            'active' => 'Active'
        ];

        return $new_columns;
    }

    public function populate_custom_columns($column, $post_id)
    {
        switch ($column) {
            case 'description':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_description', true) ?: '');
                break;
            case 'amount':
                $amount = get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_amount', true);
                echo esc_html($amount ?: '0');
                break;
            case 'thank_you_message':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_thank_you_message', true) ?: '');
                break;
            case 'active':
                echo get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_active', true) ? '✓' : '✗';
                break;
            case 'shortcode':
                echo '<code>[coinsnap_bitcoin_crowdfunding id="' . esc_attr( $post_id ) . '"]</code>';
                break;
        }
    }
}

new Coinsnap_Bitcoin_Crowdfunding_Metabox();
