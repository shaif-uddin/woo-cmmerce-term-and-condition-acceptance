<?php
/*
Plugin Name: Terms and Conditions Acceptence
Description: Displays a list of customers who have agreed to the terms and conditions and stores the terms acceptance status for individual orders.
Plugin URI: Your Plugin URI
Version: 1.0
Author: Shaif Uddin Ahamed
Author URI: Your Author URI
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
<!-- Add an export button with an id attribute -->
<button id="export-button">Export to Excel</button>
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
     <!-- Add some JavaScript code to handle the export button click -->
     <script>
        // Get the export button element by id
        var exportButton = document.getElementById("export-button");

        // Add a click event listener to the export button
        exportButton.addEventListener("click", function() {
            // Send an AJAX request to the server with the action parameter set to export_report
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "<?php echo admin_url('admin-ajax.php'); ?>");
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.responseType = "blob"; // Set the response type to blob to handle binary data
            xhr.onload = function() {
                // If the request is successful, create a URL from the response blob and trigger a download
                if (xhr.status === 200) {
                    var url = window.URL.createObjectURL(xhr.response);
                    var a = document.createElement("a");
                    a.href = url;
                    a.download = "report.xlsx"; // Set the file name for the download
                    a.click();
                    window.URL.revokeObjectURL(url); // Revoke the URL after the download
                } else {
                    // If the request fails, alert the error message
                    alert(xhr.responseText);
                }
            };
            xhr.send("action=export_report");
        });
    </script>
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

// Add a custom AJAX action to handle the export request
add_action("wp_ajax_export_report", "export_report");

// Define the export_report function
function export_report() {
    // Check if the user has the manage_options capability
    if (current_user_can("manage_options")) {
        // Get the report data from the database using the new query
        global $wpdb;
        $results = $wpdb->get_results("SELECT p.ID as order_id, pm.meta_value as order_number, pm2.meta_value as accepted FROM {$wpdb->prefix}posts p INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id INNER JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id WHERE pm.meta_key = '_order_number' AND pm2.meta_key = '_terms_accepted'");

        // Create an array to store the CSV rows
        $csv_rows = array();

        // Add the column names as the first row
        $csv_rows[] = array("Order ID", "First Name", "Last Name", "Email", "Terms Accepted");

        // Loop through the results and add each row to the array
        foreach ($results as $result) {
            // Get the user first name and last name from the billing details
            $order = wc_get_order($result->order_id);
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            $user_email = get_post_meta($result->order_id, '_user_email_accepted_terms', true);
            $accepted = $result->accepted; // Use the meta value from the query result

            // Set the cell values for each column using the new field names
            $csv_rows[] = array(
                $result->order_number, // Use the meta value from the query result
                $first_name,
                $last_name,
                $user_email,
                ($accepted === 'yes') ? 'Yes' : 'No'
            );
        }

        // Create a temporary file in memory
        $temp_file = fopen("php://memory", "w");

        // Write the CSV rows to the file
        foreach ($csv_rows as $csv_row) {
            fputcsv($temp_file, $csv_row);
        }

        // Rewind the file pointer to the beginning
        fseek($temp_file, 0);

        // Set the headers to force download the file
        header("Content-Type: application/csv");
        header("Content-Disposition: attachment; filename=report.csv");
        header("Cache-Control: max-age=0");

        // Send the file to the browser
        fpassthru($temp_file);

        // Close the file
        fclose($temp_file);

        // Exit the script
        exit;
    } else {
        // If the user does not have the capability, return an error message
        wp_die("You do not have permission to export the report.");
    }
}