<?php
/*
Plugin Name: Terms and Conditions Acceptence
Description: Displays a list of customers who have agreed to the terms and conditions and stores the terms acceptance status for individual orders.
Plugin URI: https://github.com/shaif-uddin/woo-cmmerce-term-and-condition-acceptance
Version: 1.0
Author: Shaif Uddin Ahamed
Author URI: https://github.com/shaif-uddin
*/

add_action('admin_menu', 'terms_and_conditions_report_menu');

function terms_and_conditions_report_menu() {
    add_submenu_page(
        'woocommerce',
        'Terms and Conditions Report',
        'Terms Report',
        'manage_options',
        'terms_and_conditions_report',
        'display_terms_and_conditions_report'
    );
}

// Display the report page
function display_terms_and_conditions_report() {
    ?>
    <div class="wrap">
        <h2>Terms and Conditions Report</h2>

        <table class="widefat">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Name</th>  
                    <th>Email</th>
                    <th>Terms Accepted</th>
                </tr>
            </thead>
            <tbody>
                <?php
                global $wpdb;
                // Use the correct meta key and join it with the post ID
                $results = $wpdb->get_results("SELECT p.ID as order_id, pm.meta_value as order_number, pm2.meta_value as accepted FROM {$wpdb->prefix}posts p INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id INNER JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id WHERE pm.meta_key = '_order_number' AND pm2.meta_key = '_terms_accepted'");

                foreach ($results as $result) {
                    $order = wc_get_order($result->order_id);
                    // Get the user first name and last name from the user meta data
                    // Get billing details to retrieve first name and last name
    $billing_first_name = $order->get_billing_first_name();
    $billing_last_name = $order->get_billing_last_name();

    // Combine first name and last name to get the user's full name
    $user_full_name = $billing_first_name . ' ' . $billing_last_name;
                    // $first_name = get_user_meta($result->order_id, 'first_name', true);
                    // $last_name = get_user_meta($result->order_id, 'last_name', true);
                    $user_email = get_post_meta($result->order_id, '_user_email_accepted_terms', true);
                    $accepted = $result->accepted; // Use the meta value from the query result

                    echo '<tr>';
                    echo '<td>' . esc_html($result->order_number) . '</td>'; // Use the meta value from the query result
                    echo '<td>' . esc_html($user_full_name) . '</td>';
                    echo '<td>' . esc_html($user_email) . '</td>';
                    echo '<td>' . esc_html(($accepted === 'yes') ? 'Yes' : 'No') . '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}


// Store the terms and conditions acceptance, order number, user name, and user email in the database with specific meta keys
function store_terms_and_conditions($order_id) {
    $accepted = isset($_POST['terms']) ? 'yes' : 'no';
    update_post_meta($order_id, '_terms_accepted', $accepted);

    // Get the order information
    $order = wc_get_order($order_id);
    $order_number = $order ? $order->get_order_number() : '';

    // Get billing details to retrieve first name and last name
    $billing_first_name = $order->get_billing_first_name();
    $billing_last_name = $order->get_billing_last_name();

    // Combine first name and last name to get the user's full name
    $user_full_name = $billing_first_name . ' ' . $billing_last_name;

    // Get the user email from billing details
    $user_email = $order->get_billing_email();

    // Update order meta with terms acceptance, order number, user name, and user email
    update_post_meta($order_id, '_order_number', $order_number);
    update_post_meta($order_id, '_user_full_name', $user_full_name);
    update_post_meta($order_id, '_user_email_accepted_terms', $user_email);
}
add_action('woocommerce_checkout_update_order_meta', 'store_terms_and_conditions');

// Display the terms acceptance status, order number, user name, and user email on the "edit order" page
function display_terms_and_conditions_status($order) {
    $accepted = get_post_meta($order->get_id(), '_terms_accepted', true);

    echo '<p><strong>Terms Accepted:</strong> ' . ($accepted === 'yes' ? 'Yes' : 'No') . '</p>';
}
add_action('woocommerce_admin_order_data_after_billing_address', 'display_terms_and_conditions_status');