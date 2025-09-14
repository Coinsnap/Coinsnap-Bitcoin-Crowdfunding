<?php
if (!defined('ABSPATH')) {
    exit;
}

class Coinsnap_Bitcoin_Crowdfunding_Public_Donors
{
    public function __construct()
    {
        add_action('init', [$this, 'register_public_donors_post_type']);
        add_action('init', [$this, 'register_custom_meta_fields']);
        add_action('add_meta_boxes', [$this, 'add_public_donors_metaboxes']);
        add_action('save_post', [$this, 'save_public_donors_meta'], 10, 2);
        add_filter('manage_coinsnap-cf-donors_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_coinsnap-cf-donors_posts_custom_column', [$this, 'populate_custom_columns'], 10, 2);
    }

    public function register_public_donors_post_type()
    {
        register_post_type('coinsnap-cf-donors', [
            'labels' => [
                'name'               => __('Donor Information', 'coinsnap-bitcoin-crowdfunding'),
                'singular_name'      => __('Donor Information', 'coinsnap-bitcoin-crowdfunding'),
                'menu_name'          => __('Donor Information', 'coinsnap-bitcoin-crowdfunding'),
                'add_new'            => __('Add New', 'coinsnap-bitcoin-crowdfunding'),
                'add_new_item'       => __('Add New Donor', 'coinsnap-bitcoin-crowdfunding'),
                'edit_item'          => __('Edit Donor', 'coinsnap-bitcoin-crowdfunding'),
                'new_item'           => __('New Donor', 'coinsnap-bitcoin-crowdfunding'),
                'view_item'          => __('View Donor', 'coinsnap-bitcoin-crowdfunding'),
                'search_items'       => __('Search Donors', 'coinsnap-bitcoin-crowdfunding'),
                'not_found'          => __('No donors found', 'coinsnap-bitcoin-crowdfunding'),
                'not_found_in_trash' => __('No donors found in Trash', 'coinsnap-bitcoin-crowdfunding'),
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'coinsnap-cf-donors'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => ['title'],
            'show_in_rest'       => true
        ]);
    }

    public function register_custom_meta_fields()
    {
        register_meta('post', '_coinsnap_bitcoin_crowdfunding_donor_name', [
            'object_subtype' => 'coinsnap-cf-donors',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_amount', [
            'object_subtype' => 'coinsnap-cf-donors',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_message', [
            'object_subtype' => 'coinsnap-cf-donors',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_form_type', [
            'object_subtype' => 'coinsnap-cf-donors',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_email', [
            'object_subtype' => 'coinsnap-cf-donors',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_address', [
            'object_subtype' => 'coinsnap-cf-donors',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_payment_id', [
            'object_subtype' => 'coinsnap-cf-donors',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_crowdfunding_custom_field', [
            'object_subtype' => 'coinsnap-cf-donors',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
    }

    public function add_public_donors_metaboxes()
    {
        add_meta_box(
            'coinsnap_bitcoin_crowdfunding_public_donors_details',
            'Donor Details',
            [$this, 'render_public_donors_metabox'],
            'coinsnap-cf-donors',
            'normal',
            'high'
        );
    }

    public function render_public_donors_metabox($post)
    {
        wp_nonce_field('coinsnap_bitcoin_crowdfunding_public_donors_nonce', 'coinsnap_bitcoin_crowdfunding_public_donors_nonce');

        $name = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_donor_name', true);
        $amount = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_amount', true);
        $message = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_message', true);
        $form_type = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_form_type', true);
        $email = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_email', true);
        $address = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_address', true);
        $payment_id = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_payment_id', true);
        $custom_field = get_post_meta($post->ID, '_coinsnap_bitcoin_crowdfunding_custom_field', true);
?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_donor_name"><?php echo esc_html__('Name', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="text" id="coinsnap_bitcoin_crowdfunding_donor_name" name="coinsnap_bitcoin_crowdfunding_donor_name" class="regular-text" value="<?php echo esc_attr($name); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_amount"><?php echo esc_html__('Amount', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="text" id="coinsnap_bitcoin_crowdfunding_amount" name="coinsnap_bitcoin_crowdfunding_amount" class="regular-text" value="<?php echo esc_attr($amount); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_message"><?php echo esc_html__('Message', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <textarea id="coinsnap_bitcoin_crowdfunding_message" name="coinsnap_bitcoin_crowdfunding_message" class="regular-text" rows="3" readonly><?php echo esc_textarea($message); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_form_type"><?php echo esc_html__('Form Type', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="text" id="coinsnap_bitcoin_crowdfunding_form_type" name="coinsnap_bitcoin_crowdfunding_form_type" class="regular-text" value="<?php echo esc_attr($form_type); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_email"><?php echo esc_html__('Email', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="email" id="coinsnap_bitcoin_crowdfunding_email" name="coinsnap_bitcoin_crowdfunding_email" class="regular-text" value="<?php echo esc_attr($email); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_address"><?php echo esc_html__('Address', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="text" id="coinsnap_bitcoin_crowdfunding_address" name="coinsnap_bitcoin_crowdfunding_address" class="regular-text" value="<?php echo esc_attr($address); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_payment_id"><?php echo esc_html__('Payment ID', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="text" id="coinsnap_bitcoin_crowdfunding_payment_id" name="coinsnap_bitcoin_crowdfunding_payment_id" class="regular-text" value="<?php echo esc_attr($payment_id); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_custom_field"><?php echo esc_html__('Custom Field', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="text" id="coinsnap_bitcoin_crowdfunding_custom_field" name="coinsnap_bitcoin_crowdfunding_custom_field" class="regular-text" value="<?php echo esc_attr($custom_field); ?>" readonly>
                </td>
            </tr>
        </table>
<?php
    }

    public function save_public_donors_meta($post_id, $post)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){ return;}

        if (!current_user_can('edit_post', $post_id)){ return;}

        if ($post->post_type !== 'coinsnap-cf-donors'){ return;}
        
        $nonce = (null !== filter_input(INPUT_POST,'coinsnap_bitcoin_crowdfunding_public_donors_nonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS))? filter_input(INPUT_POST,'coinsnap_bitcoin_crowdfunding_public_donors_nonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';

        if (!wp_verify_nonce($nonce, 'coinsnap_bitcoin_crowdfunding_public_donors_nonce')){
            return;
        }

        $fields = [
            'coinsnap_bitcoin_crowdfunding_donor_name' => 'text',
            'coinsnap_bitcoin_crowdfunding_amount' => 'text',
            'coinsnap_bitcoin_crowdfunding_message' => 'text',
            'coinsnap_bitcoin_crowdfunding_form_type' => 'text',
            'coinsnap_bitcoin_crowdfunding_email' => 'text',
            'coinsnap_bitcoin_crowdfunding_address' => 'text',
            'coinsnap_bitcoin_crowdfunding_payment_id' => 'text',
            'coinsnap_bitcoin_crowdfunding_custom_field' => 'text',
        ];

        foreach ($fields as $field => $type) {
            if ($type === 'boolean') {
                $value = (filter_input(INPUT_POST,$field,FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null) ? '1' : '';
            } else {
                $value = (filter_input(INPUT_POST,$field,FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null)? sanitize_text_field(filter_input(INPUT_POST,$field,FILTER_SANITIZE_FULL_SPECIAL_CHARS)) : '';
            }
            update_post_meta($post_id, '_' . $field, $value);
        }
    }

    public function add_custom_columns($columns)
    {
        return [
            'cb' => $columns['cb'],
            'title' => $columns['title'],
            'name' => __('Name', 'coinsnap-bitcoin-crowdfunding'),
            'email' => __('Email', 'coinsnap-bitcoin-crowdfunding'),
            'amount' => __('Amount', 'coinsnap-bitcoin-crowdfunding'),
            'message' => __('Message', 'coinsnap-bitcoin-crowdfunding'),
            'address' => __('Address', 'coinsnap-bitcoin-crowdfunding'),
            'payment_id' => __('Payment ID', 'coinsnap-bitcoin-crowdfunding'),
            'form_type' => __('Form Type', 'coinsnap-bitcoin-crowdfunding'),
            'custom_field' => __('Custom Field', 'coinsnap-bitcoin-crowdfunding')
        ];
    }

    public function populate_custom_columns($column, $post_id)
    {
        switch ($column) {
            case 'name':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_donor_name', true));
                break;
            case 'amount':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_amount', true));
                break;
            case 'message':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_message', true));
                break;
            case 'form_type':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_form_type', true));
                break;
            case 'email':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_email', true));
                break;
            case 'address':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_address', true));
                break;
            case 'payment_id':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_payment_id', true));
                break;
            case 'custom_field':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_crowdfunding_custom_field', true));
                break;
        }
    }
}

new Coinsnap_Bitcoin_Crowdfunding_Public_Donors();
