<?php
/**
 * Script to create dummy members, orders, and CSV for Paid Memberships Pro
 *
 * This script creates:
 * - 10 dummy members with random names, emails, and assigns them to membership level 1
 * - Creates a successful order for each member
 * - Marks the first 2 members as deceased (sets user meta 'pmpro_deceased' to '1')
 * - Generates a CSV file 'dummy_transactions.csv' with transaction data for the first 5 members
 *
 * To run this script:
 * 1. Via WP-CLI: wp eval-file create_dummy_data.php
 * 2. Or include it in a theme/plugin and access a page that runs it
 * 3. Or run it directly in a PHP file within WordPress context
 *
 * Note: Assumes membership level 1 exists. Adjust as needed.
 */

// Allow direct access for execution
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname(__FILE__) . '/../../../' );
    define( 'PMPRO_DIR', dirname(__FILE__) . '/' );
    require_once ABSPATH . 'wp-load.php';
}

// Include PMPro functions if not already loaded
if ( ! function_exists( 'pmpro_changeMembershipLevel' ) ) {
    require_once PMPRO_DIR . '/includes/functions.php';
}

// Function to generate random data
function generate_random_name() {
    $first_names = ['John', 'Jane', 'Bob', 'Alice', 'Charlie', 'Diana', 'Eve', 'Frank', 'Grace', 'Henry'];
    $last_names = ['Doe', 'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez'];
    return $first_names[array_rand($first_names)] . ' ' . $last_names[array_rand($last_names)];
}

function generate_random_email($name) {
    $name_parts = explode(' ', $name);
    $email = strtolower($name_parts[0] . '.' . $name_parts[1] . rand(1, 100) . '@example.com');
    return $email;
}

// Create 10 dummy members
$created_users = [];
for ($i = 1; $i <= 10; $i++) {
    $name = generate_random_name();
    $email = generate_random_email($name);
    $username = sanitize_user(strtolower(str_replace(' ', '', $name)) . $i);

    $user_data = [
        'user_login' => $username,
        'user_pass' => wp_generate_password(),
        'user_email' => $email,
        'first_name' => explode(' ', $name)[0],
        'last_name' => explode(' ', $name)[1],
        'display_name' => $name,
        'role' => 'subscriber'
    ];

    $user_id = wp_insert_user($user_data);
    if (!is_wp_error($user_id)) {
        // Assign membership level (assuming level 1 exists)
        pmpro_changeMembershipLevel(1, $user_id);

        // Create a dummy order for this user
        $order = new MemberOrder();
        $order->user_id = $user_id;
        $order->membership_id = 1;
        $order->InitialPayment = 10.00;
        $order->PaymentAmount = 10.00;
        $order->billing = new stdClass();
        $order->billing->name = $name;
        $order->billing->street = '123 Dummy St';
        $order->billing->city = 'Dummy City';
        $order->billing->state = 'CA';
        $order->billing->zip = '12345';
        $order->billing->country = 'US';
        $order->billing->phone = '555-1234';
        $order->gateway = 'check';
        $order->status = 'success';
        $order->saveOrder();

        // Set bank transaction ID for check payments
        $order->payment_transaction_id = 'BANK' . strtoupper(substr(md5($order->id), 0, 8));
        $order->saveOrder();

        $created_users[] = [
            'user_id' => $user_id,
            'name' => $name,
            'email' => $email,
            'order_id' => $order->id,
            'transaction_id' => $order->code // Using code as transaction ID
        ];

        echo "Created user: $name (ID: $user_id, Order ID: {$order->id})\n";
    } else {
        echo "Error creating user: " . $user_id->get_error_message() . "\n";
    }
}

// Mark 2 members as deceased (set user meta)
if (count($created_users) >= 2) {
    for ($i = 0; $i < 2; $i++) {
        $user_id = $created_users[$i]['user_id'];
        update_user_meta($user_id, 'pmpro_deceased', '1');
        echo "Marked user ID $user_id as deceased\n";
    }
}

// Create CSV with 5 members' transaction ID data
$csv_data = [];
$csv_users = array_slice($created_users, 0, 5); // First 5 users
foreach ($csv_users as $user) {
    $csv_data[] = [
        'User ID' => $user['user_id'],
        'Name' => $user['name'],
        'Email' => $user['email'],
        'Transaction ID' => $user['transaction_id'],
        'Order ID' => $user['order_id']
    ];
}

// Write CSV file
$csv_file = PMPRO_DIR . '/dummy_transactions.csv';
$fp = fopen($csv_file, 'w');
if ($fp) {
    fputcsv($fp, array_keys($csv_data[0]));
    foreach ($csv_data as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
    echo "CSV file created: $csv_file\n";
} else {
    echo "Error creating CSV file\n";
}

echo "Dummy data creation completed!\n";
?>