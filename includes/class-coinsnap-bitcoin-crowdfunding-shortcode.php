<?php
if (! defined('ABSPATH')) {
    exit;
}

class Coinsnap_Bitcoin_Crowdfunding_Shortcode
{
    public function __construct()
    {
        add_shortcode('coinsnap_bitcoin_crowdfunding', [$this, 'coinsnap_bitcoin_crowdfunding_render_shortcode']);
    }

    private function get_template($template_name, $args = [])
    {
        if ($args && is_array($args)) {
            extract($args);
        }

        $template = plugin_dir_path(__FILE__) . '../templates/' . $template_name . '.php';

        if (file_exists($template)) {
            include $template;
        }
    }


    function coinsnap_bitcoin_crowdfunding_render_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'coinsnap_bitcoin_crowdfunding');

        $crowdfunding_id = intval($atts['id']);
        // Check if crowdfunding_id is valid and post exists
        if (!$crowdfunding_id || get_post_type($crowdfunding_id) !== 'bitcoin-cfs') {
            return '<p>Invalid or missing poll ID.</p>';
        }
        $title = get_the_title($crowdfunding_id);
        $options_general = get_option('coinsnap_bitcoin_crowdfunding_options');
        $theme_class = $options_general['theme'] === 'dark' ? 'dark-theme' : 'light-theme';
        $description = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_description', true);
        $amount = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_amount', true);
        $public_donors = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_collect_donor_info', true);
        $first_name = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_first_name_visibility', true);
        $last_name = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_last_name_visibility', true);
        $email = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_email_visibility', true);
        $address = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_address_visibility', true);
        $custom = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_custom_field_visibility', true);
        $custom_name = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_custom_field_name', true);
        $default_value1 = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_default_value_1', true);
        $default_value2 = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_default_value_2', true);
        $default_value3 = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_default_value_3', true);
        $thank_you = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_thank_you_message', true);
        $shoutout = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_shoutout', true);
        $default_curreny = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_default_currency', true);

        global $wpdb;
        $table_name = $wpdb->prefix . 'crowdfunding_payments';
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} 
            WHERE crowdfunding_id = %d AND status = 'completed'",
                $crowdfunding_id
            )
        );
        $donations = count($results);
        $raised = 0;
        foreach ($results as $result) {
            $raised += $result->amount;
        }
        $percentage = $raised && $amount ? ($raised / $amount) * 100 : 0;
        $active = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_active', true);
        if (!$active) {
            ob_start();
?>
            <div class="coinsnap-bitcoin-crowdfunding-form-wrapper <?php echo esc_attr($theme_class); ?> narrow-form">
                <div class="coinsnap-bitcoin-crowdfunding-title-wrapper"
                    style="display: flex;justify-content: center; flex-direction: column; align-items: center; margin: 0">
                    <h3><?php echo esc_html($title); ?></h3>
                </div>
                <h4 style="text-align: center;">This form is not active</h4>

            </div>
        <?php
            return ob_get_clean();
        }

        ob_start();
        ?>
        <div id="bitcoin-crowdfunding-form" data-crowdfunding-id="<?php echo $crowdfunding_id; ?>" class="coinsnap-bitcoin-crowdfunding-form-wrapper <?php echo esc_attr($theme_class); ?> wide-form">

            <div class="crowdfunding-wrapper">
                <div class="crowdfunding-title-wrapper">
                    <h3>
                        <?php echo esc_html($title); ?>
                    </h3>
                    <h5>
                        <?php echo esc_html($description); ?>
                    </h5>
                </div>
                <div class="crowdfunding-top-info">
                    <div class="crowdfunding-info-field">
                        <div class="main-info"><?php echo esc_html($raised) ?> sats</div>
                        <div class="sub-info">Raised</div>
                    </div>
                    <div class="crowdfunding-info-field middle-field">
                        <div class="main-info"><?php echo esc_html($donations) ?></div>
                        <div class="sub-info">Donations</div>
                    </div>
                    <div class="crowdfunding-info-field">
                        <div class="main-info"><?php echo esc_html($amount) ?> sats</div>
                        <div class="sub-info">Goal</div>
                    </div>
                </div>
                <div class="crowdfunding-bottom-info">
                    <div class="crowdfunding-progress">
                        <span class="crowdfunding-progress-bar" style="width: <?php echo esc_attr($percentage); ?>%"></span>
                    </div>
                </div>
            </div>
            <?php
            if ($percentage < 100) {
            ?>
                <div class="crowdfunding-payment-form">
                    <div class="crowdfunding-pay-title-wrapper">
                        <h4 style="font-weight: normal;">Donate text description</h4>
                        <select id="coinsnap-bitcoin-crowdfunding-swap-crowdfunding" class="currency-swapper" data-default-currency="<?php echo $default_curreny; ?>">
                            <option value="sats">SATS</option>
                            <option value="EUR">EUR</option>
                            <option value="USD">USD</option>
                            <option value="CAD">CAD</option>
                            <option value="JPY">JPY</option>
                            <option value="GBP">GBP</option>
                            <option value="CHF">CHF</option>
                        </select>
                    </div>
                    <input type="text" id="coinsnap-bitcoin-crowdfunding-email" name="bitcoin-email" style="display: none;" aria-hidden="true">
                    <div class="crowdfunding-prefills">
                        <div class="crowdfunding-prefill" id="coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap1">
                            <div class="crowdfunding-prefill-amount">
                                <div data-default-value="<?php echo $default_value1; ?>" id="coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap1-primary"></div>
                            </div>
                            <div id="coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap1-secondary" class="crowdfunding-prefill-alt-amount"></div>
                        </div>
                        <div class="crowdfunding-prefill" id="coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap2">
                            <div class="crowdfunding-prefill-amount">
                                <div data-default-value="<?php echo $default_value2; ?>" id="coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap2-primary"></div>
                            </div>
                            <div id="coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap2-secondary" class="crowdfunding-prefill-alt-amount"></div>
                        </div>
                        <div class="crowdfunding-prefill" id="coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap3">
                            <div class="crowdfunding-prefill-amount">
                                <div data-default-value="<?php echo $default_value3; ?>" id="coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap3-primary"></div>
                            </div>
                            <div id="coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap3-secondary" class="crowdfunding-prefill-alt-amount"></div>
                        </div>
                    </div>
                    <label for="coinsnap-bitcoin-crowdfunding-amount-crowdfunding">Amount</label>
                    <div class="amount-wrapper">
                        <input class="crowdfunding-input-field" type="text" id="coinsnap-bitcoin-crowdfunding-amount-crowdfunding" placeholder="Enter custom amount">
                        <div class="secondary-amount">
                            <span id="coinsnap-bitcoin-crowdfunding-satoshi-crowdfunding"></span>
                        </div>
                    </div>
                    <button class="crowdfunding-pay" id="coinsnap-bitcoin-crowdfunding-pay-crowdfunding">Donate</button>

                </div>
            <?php
            } else {
            ?>
                <div class="crowdfunding-payment-form">
                    <div style="justify-content: center; padding-top: 36px" class="crowdfunding-pay-title-wrapper">
                        <h3>Goal is reached. Thank you for your support!</h3>
                    </div>
                </div>
            <?php
            } ?>

            <div id="coinsnap-bitcoin-crowdfunding-blur-overlay-crowdfunding" class="blur-overlay"></div>
            <?php
            $this->get_template('coinsnap-bitcoin-crowdfunding-modal', [
                'prefix' => 'coinsnap-bitcoin-crowdfunding-',
                'sufix' => '-crowdfunding',
                'first_name' => $public_donors ? $first_name : 'hidden',
                'last_name' => $public_donors ? $last_name : 'hidden',
                'email' => $public_donors ? $email : 'hidden',
                'address' => $public_donors ? $address : 'hidden',
                'public_donors' => $public_donors || $shoutout,
                'custom' => $public_donors ? $custom : 'hidden',
                'custom_name' => $public_donors ? $custom_name : 'hidden',
                'thank_you' => $thank_you,
                'shoutout' => $shoutout,
            ]);
            ?>
        </div>

<?php
        return ob_get_clean();
    }
}

new Coinsnap_Bitcoin_Crowdfunding_Shortcode();
