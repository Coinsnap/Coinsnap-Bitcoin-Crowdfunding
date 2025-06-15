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
                'name'               => 'Crowdfundings',
                'singular_name'      => 'Crowdfunding',
                'menu_name'          => 'Crowdfundings',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Crowdfunding',
                'edit_item'          => 'Edit Crowdfunding',
                'new_item'           => 'New Crowdfunding',
                'view_item'          => 'View Crowdfunding',
                'search_items'       => 'Search Crowdfundings',
                'not_found'          => 'No crowdfundings found',
                'not_found_in_trash' => 'No crowdfundings found in Trash',
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

?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php echo esc_html_e('Active', 'coinsnap-bitcoin-crowdfunding'); ?></th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="coinsnap_bitcoin_crowdfunding_active"
                            value="1"
                            <?php checked($active, '1'); ?>>
                        <?php echo esc_html_e('Enable', 'coinsnap-bitcoin-crowdfunding'); ?>
                    </label>
                    <br>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_description"><?php echo esc_html_e('Description', 'coinsnap-bitcoin-crowdfunding'); ?></label>
                </th>
                <td>
                    <textarea
                        id="coinsnap_bitcoin_crowdfunding_description"
                        name="coinsnap_bitcoin_crowdfunding_description"
                        class="regular-text"
                        rows="2"
                        required
                        style="width: 350px"><?php echo esc_textarea($description); ?></textarea>
                </td>
            </tr>
            <th scope="row">
                <label for="coinsnap_bitcoin_crowdfunding_amount"><?php echo esc_html_e('Goal Amount (in satoshis)', 'coinsnap-bitcoin-crowdfunding') ?></label>
            </th>
            <td>
                <input
                    type="number"
                    id="coinsnap_bitcoin_crowdfunding_amount"
                    name="coinsnap_bitcoin_crowdfunding_amount"
                    class="regular-text"
                    required
                    value="<?php echo esc_attr($amount); ?>"
                    min="0"
                    step="1">
            </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_thank_you_message"><?php echo esc_html_e('Thank You Message', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <textarea
                        id="coinsnap_bitcoin_crowdfunding_thank_you_message"
                        name="coinsnap_bitcoin_crowdfunding_thank_you_message"
                        class="regular-text"
                        rows="2"
                        required
                        style="width: 350px"><?php echo esc_textarea($thank_you_message); ?></textarea>

                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_default_currency"><?php echo esc_html_e('Default Currency', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <select
                        id="coinsnap_bitcoin_crowdfunding_default_currency"
                        name="coinsnap_bitcoin_crowdfunding_default_currency"
                        class="regular-text">
                        <option value="sats" <?php selected($default_currency, 'sats'); ?>>SATS</option>
                        <option value="EUR" <?php selected($default_currency, 'EUR'); ?>>EUR</option>
                        <option value="USD" <?php selected($default_currency, 'USD'); ?>>USD</option>
                        <option value="CAD" <?php selected($default_currency, 'CAD'); ?>>CAD</option>
                        <option value="JPY" <?php selected($default_currency, 'JPY'); ?>>JPY</option>
                        <option value="GBP" <?php selected($default_currency, 'GBP'); ?>>GBP</option>
                        <option value="CHF" <?php selected($default_currency, 'CHF'); ?>>CHF</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_default_value_1"><?php echo esc_html_e('Default Value 1 (sats)', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input
                        type="number"
                        id="coinsnap_bitcoin_crowdfunding_default_value_1"
                        name="coinsnap_bitcoin_crowdfunding_default_value_1"
                        class="regular-text"
                        value="<?php echo esc_attr($default_value_1); ?>"
                        min="0"
                        step="1">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_default_value_2"><?php echo esc_html_e('Default Value 2 (sats)', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input
                        type="number"
                        id="coinsnap_bitcoin_crowdfunding_default_value_2"
                        name="coinsnap_bitcoin_crowdfunding_default_value_2"
                        class="regular-text"
                        value="<?php echo esc_attr($default_value_2); ?>"
                        min="0"
                        step="1">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_default_value_3"><?php echo esc_html_e('Default Value 3 (sats)', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input
                        type="number"
                        id="coinsnap_bitcoin_crowdfunding_default_value_3"
                        name="coinsnap_bitcoin_crowdfunding_default_value_3"
                        class="regular-text"
                        value="<?php echo esc_attr($default_value_3); ?>"
                        min="0"
                        step="1">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="shortcode"><?php echo esc_html_e('Shortcode', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input
                        type="text"
                        id="shortcode"
                        name="shortcode"
                        class="regular-text"
                        readonly
                        value='[coinsnap_bitcoin_crowdfunding id="<?php echo esc_html($post->ID); ?>"]'>
                </td>
            </tr>
            <tr>
                <th scope="row">Shoutout</th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="coinsnap_bitcoin_crowdfunding_shoutout"
                            value="1"
                            <?php checked($shoutout, '1'); ?>>
                        Enable shoutout list
                    </label>
                    <br>
                </td>
            </tr>
            <tr id="shoutout-shortcode-row">
                <th scope="row">
                    <label for="shoutout-shortcode"><?php echo esc_html_e('Shoutout Shortcode', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input
                        type="text"
                        id="shoutout-shortcode"
                        name="shoutout-shortcode"
                        class="regular-text"
                        readonly
                        value='[coinsnap_bitcoin_crowdfunding_shoutout id="<?php echo esc_html($post->ID); ?>"]'>
                </td>
            </tr>
            <tr>
                <th scope="row">Collect Donor Info</th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="coinsnap_bitcoin_crowdfunding_collect_donor_info"
                            value="1"
                            <?php checked($collect_donor_info, '1'); ?>>
                        Enable
                    </label>
                    <br>
                </td>
            </tr>
        </table>

        <div id="donor-info-fields" style="margin-top: 20px;">
            <h3>Donor Information Fields</h3>
            <table class="form-table">
                <?php
                foreach ($donor_fields as $field => $label) {
                    $visibility_value = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_' . $field . '_visibility', true) ?: 'optional';
                ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <select name="coinsnap_bitcoin_crowdfunding_<?php echo esc_attr($field); ?>_visibility">
                                <option value="mandatory" <?php selected($visibility_value, 'mandatory'); ?>>Mandatory</option>
                                <option value="optional" <?php selected($visibility_value, 'optional'); ?>>Optional</option>
                                <option value="hidden" <?php selected($visibility_value, 'hidden'); ?>>Hidden</option>
                            </select>
                        </td>
                    </tr>
                <?php } ?>
                <tr>
                    <th scope="row">
                        <label for="coinsnap_bitcoin_crowdfunding_custom_field_name"><?php echo esc_html_e('Custom Field Name', 'coinsnap-bitcoin-crowdfunding') ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="coinsnap_bitcoin_crowdfunding_custom_field_name"
                            name="coinsnap_bitcoin_crowdfunding_custom_field_name"
                            class="regular-text"
                            value="<?php echo esc_attr($custom_field_name); ?>">
                    </td>
                </tr>

            </table>
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
                'coinsnap_bitcoin_crowdfunding_description',
                'coinsnap_bitcoin_crowdfunding_amount'
            ];

            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    wp_die(sprintf( 
                        /* translators: 1: Error: field is required */
                        esc_html__( 'Error: %1$s is required.', 'coinsnap-bitcoin-crowdfunding' ), esc_html($field)));
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
                echo '[bitcoin_crowdfunding id="' . esc_html($post_id) . '"]';
                break;
        }
    }
}

new Coinsnap_Bitcoin_Crowdfunding_Metabox();
