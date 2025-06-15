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
                'name'               => 'Donor Information',
                'singular_name'      => 'Donor Information',
                'menu_name'          => 'Donor Information',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Donor',
                'edit_item'          => 'Edit Donor',
                'new_item'           => 'New Donor',
                'view_item'          => 'View Donor',
                'search_items'       => 'Search Donors',
                'not_found'          => 'No donors found',
                'not_found_in_trash' => 'No donors found in Trash',
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
                    <label for="coinsnap_bitcoin_crowdfunding_donor_name"><?php echo esc_html_e('Name', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="text" id="coinsnap_bitcoin_crowdfunding_donor_name" name="coinsnap_bitcoin_crowdfunding_donor_name" class="regular-text" value="<?php echo esc_attr($name); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_amount"><?php echo esc_html_e('Amount', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="text" id="coinsnap_bitcoin_crowdfunding_amount" name="coinsnap_bitcoin_crowdfunding_amount" class="regular-text" value="<?php echo esc_attr($amount); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_message"><?php echo esc_html_e('Message', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <textarea id="coinsnap_bitcoin_crowdfunding_message" name="coinsnap_bitcoin_crowdfunding_message" class="regular-text" rows="3" readonly><?php echo esc_textarea($message); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_form_type"><?php echo esc_html_e('Form Type', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="text" id="coinsnap_bitcoin_crowdfunding_form_type" name="coinsnap_bitcoin_crowdfunding_form_type" class="regular-text" value="<?php echo esc_attr($form_type); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_email"><?php echo esc_html_e('Email', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="email" id="coinsnap_bitcoin_crowdfunding_email" name="coinsnap_bitcoin_crowdfunding_email" class="regular-text" value="<?php echo esc_attr($email); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_address"><?php echo esc_html_e('Address', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="text" id="coinsnap_bitcoin_crowdfunding_address" name="coinsnap_bitcoin_crowdfunding_address" class="regular-text" value="<?php echo esc_attr($address); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_payment_id"><?php echo esc_html_e('Payment ID', 'coinsnap-bitcoin-crowdfunding') ?></label>
                </th>
                <td>
                    <input type="text" id="coinsnap_bitcoin_crowdfunding_payment_id" name="coinsnap_bitcoin_crowdfunding_payment_id" class="regular-text" value="<?php echo esc_attr($payment_id); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_crowdfunding_custom_field"><?php echo esc_html_e('Custom Field', 'coinsnap-bitcoin-crowdfunding') ?></label>
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
            'name' => 'Name',
            'email' => 'Email',
            'amount' => 'Amount',
            'message' => 'Message',
            'address' => 'Address',
            'payment_id' => 'Payment ID',
            'form_type' => 'Form Type',
            'custom_field' => 'Custom Field'
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
