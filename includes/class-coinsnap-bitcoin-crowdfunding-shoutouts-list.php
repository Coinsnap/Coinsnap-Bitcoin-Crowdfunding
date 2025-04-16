<?php
if (! defined('ABSPATH')) {
    exit;
}

class Coinsnap_Bitcoin_Crowdfunding_Shoutouts_List
{
    public function __construct()
    {
        add_shortcode('coinsnap_bitcoin_crowdfunding_shoutout', [$this, 'coinsnap_bitcoin_crowdfunding_shoutouts_render_shortcode']);
    }

    function coinsnap_bitcoin_crowdfunding_shoutouts_render_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'coinsnap_bitcoin_crowdfunding_shoutout');

        $crowdfunding_id = intval($atts['id']);
        // Check if crowdfunding_id is valid and post exists
        if (!$crowdfunding_id || get_post_type($crowdfunding_id) !== 'bitcoin-cfs') {
            return '<p>Invalid or missing crowdfunding ID.</p>';
        }

        $options_general = get_option('coinsnap_bitcoin_crowdfunding_options');

        $theme_class = $options_general['theme'] === 'dark' ? 'coinsnap-bitcoin-crowdfunding-dark-theme' : 'coinsnap-bitcoin-crowdfunding-light-theme';
        $active = get_post_meta($crowdfunding_id, '_coinsnap_bitcoin_crowdfundings_shoutout', true);
        error_log('Active: ' . $active);
        global $wpdb;
        $table_name = $wpdb->prefix . 'crowdfunding_payments';

        // Fetch payments with public name and message
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name 
            WHERE name IS NOT NULL 
            AND message IS NOT NULL 
            AND name != '' 
            AND crowdfunding_id = $crowdfunding_id
            ORDER BY created_at DESC",
            ARRAY_A
        );
        $shoutouts = array();

        if (!empty($results)) {
            foreach ($results as $row) {
                $shoutouts[] = array(
                    'date'    => $row['created_at'],
                    'name'    => $row['name'],
                    'amount'  => $row['amount'] . ' sats',
                    'message' => $row['message']
                );
            }
        }

        ob_start();
?>
        <div class="shoutouts-list-container">
            <div id="coinsnap-bitcoin-crowdfunding-shoutouts-wrapper">

                <?php
                if ($active) {
                    if (empty($shoutouts)) {
                        $this->render_empty_donation_row($theme_class);
                    } else {
                        foreach ($shoutouts as $shoutout) {
                            $this->render_donation_row($shoutout, $theme_class);
                        }
                    }
                } else {
                ?>
                    <div class="coinsnap-bitcoin-crowdfunding-form <?php echo esc_attr($theme_class); ?>">
                        <div class="shoutout-form-wrapper"
                            style="display: flex;justify-content: center; flex-direction: column; align-items: center; margin: 0">
                            <h3>Shoutouts List</h3>
                            <h4 style="text-align: center;">This form is not active</h4>
                        </div>
                    </div>
                <?php
                }
                ?>

            </div>
        </div>

    <?php

        return ob_get_clean();
    }

    private function render_empty_donation_row($theme)
    {

        $highlight = false;
        $name = "No Shoutouts Available";
        $message = "There are no shoutouts yet. This is just an example of how they will be displayed once there are some available.";
        $amount = "0 sats";
        $daysAgo = "Today";
    ?>
        <div class="coinsnap-bitcoin-crowdfunding-shoutout <?php echo esc_attr($theme); ?> <?php echo $highlight ? 'highlight-shoutout' : ''; ?>">
            <div class="coinsnap-bitcoin-crowdfunding-shoutout-top">
                <?php echo esc_html($name); ?>
                <div class="coinsnap-bitcoin-crowdfunding-shoutout-top-right">
                    <div class="coinsnap-bitcoin-crowdfunding-shoutout-top-right-amount <?php echo $highlight ? 'highlight' : ''; ?>"> <?php echo esc_html($amount); ?></div>
                    <div class="coinsnap-bitcoin-crowdfunding-shoutout-top-right-days"> <?php echo esc_html($daysAgo); ?></div>

                </div>
            </div>
            <div class="coinsnap-bitcoin-crowdfunding-shoutout-bottom">
                <?php echo esc_html($message); ?>
            </div>

        </div>
    <?php
    }

    private function render_donation_row($donation, $theme)
    {
        $name = $donation['name'];
        $amount = $donation['amount'];
        $message = $donation['message'];
        $highlightAmount = '21000';
        $highlight = (int)$amount >= (int)$highlightAmount;
        $date =  $donation['date'];
        $donationDate = new DateTime($date);
        $now = new DateTime();
        $interval = $donationDate->diff($now);
        if ($interval->days === 0) {
            $daysAgo = 'Today';
        } elseif ($interval->days === 1) {
            $daysAgo = '1 day ago';
        } else {
            $daysAgo = $interval->days . ' days ago';
        }

    ?>
        <div class="coinsnap-bitcoin-crowdfunding-shoutout <?php echo esc_attr($theme); ?> <?php echo $highlight ? 'highlight-shoutout' : ''; ?>">
            <div class="coinsnap-bitcoin-crowdfunding-shoutout-top">
                <?php echo esc_html($name); ?>
                <div class="coinsnap-bitcoin-crowdfunding-shoutout-top-right">
                    <div class="coinsnap-bitcoin-crowdfunding-shoutout-top-right-amount <?php echo $highlight ? 'highlight' : ''; ?>"> <?php echo esc_html($amount); ?></div>
                    <div class="coinsnap-bitcoin-crowdfunding-shoutout-top-right-days"> <?php echo esc_html($daysAgo); ?></div>

                </div>
            </div>
            <div class="coinsnap-bitcoin-crowdfunding-shoutout-bottom">
                <?php echo esc_html($message); ?>
            </div>
        </div>
<?php

    }
}

new Coinsnap_Bitcoin_Crowdfunding_Shoutouts_List();
