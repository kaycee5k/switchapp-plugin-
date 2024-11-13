<?php
/*
Plugin Name: SwitchApp Payment Gateway
Plugin URI: https://example.com
Description: A standalone WordPress plugin to integrate SwitchApp Payment API for event ticket payments with client-side payment using the public keys.
Version: 1.7.2
Author: Kaycee Onyia
Author URI: https://example.com
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Enqueue Scripts
function switchapp_payment_gateway_enqueue_scripts() {
    wp_enqueue_script('switchapp-inline-js', 'https://inline.switchappgo.com/v1/switchapp-inline.js', array(), null, true);
    wp_enqueue_script('switchapp-payment-script', plugins_url('/js/switchapp-payment.js', __FILE__), array('jquery', 'switchapp-inline-js'), null, true);

    // Pass AJAX URL and nonce to JavaScript
    $is_test_mode = get_option('switchapp_payment_gateway_test_mode') === 'yes';
    $public_key = $is_test_mode ? get_option('switchapp_payment_gateway_test_public_key') : get_option('switchapp_payment_gateway_live_public_key');
    wp_localize_script('switchapp-payment-script', 'switchappConfig', array(
        'publicKey' => $public_key,
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('switchapp_nonce'),
    ));
}
add_action('wp_enqueue_scripts', 'switchapp_payment_gateway_enqueue_scripts');

// Custom CSS for Payment Form
function switchapp_payment_gateway_enqueue_styles() {
    wp_enqueue_style('switchapp-payment-style', plugins_url('/css/switchapp-payment-style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'switchapp_payment_gateway_enqueue_styles');

// Settings Page for API Keys and Test Mode
function switchapp_payment_gateway_settings_menu() {
    add_menu_page(
        'SwitchApp Payment Gateway',
        'Payment Settings',
        'manage_options',
        'switchapp-payment-gateway',
        'switchapp_payment_gateway_settings_page',
        'dashicons-admin-generic'
    );
}
add_action('admin_menu', 'switchapp_payment_gateway_settings_menu');

function switchapp_payment_gateway_settings_page() {
    ?>
    <div class="wrap">
        <h1>SwitchApp Payment Gateway Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('switchapp_payment_gateway_options_group');
            do_settings_sections('switchapp_payment_gateway');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function switchapp_payment_gateway_register_settings() {
    register_setting('switchapp_payment_gateway_options_group', 'switchapp_payment_gateway_test_mode');
    register_setting('switchapp_payment_gateway_options_group', 'switchapp_payment_gateway_test_public_key');
    register_setting('switchapp_payment_gateway_options_group', 'switchapp_payment_gateway_live_public_key');
    
    register_setting('switchapp_payment_gateway_options_group', 'switchapp_ticket_limit_student');
    register_setting('switchapp_payment_gateway_options_group', 'switchapp_ticket_limit_regular');
    register_setting('switchapp_payment_gateway_options_group', 'switchapp_ticket_limit_vip');

    add_settings_section('switchapp_payment_gateway_main', 'Main Settings', null, 'switchapp_payment_gateway');

    add_settings_field('switchapp_payment_gateway_test_mode', 'Enable Test Mode', 'switchapp_payment_gateway_test_mode_callback', 'switchapp_payment_gateway', 'switchapp_payment_gateway_main');
    add_settings_field('switchapp_payment_gateway_test_public_key', 'Test Public Key', 'switchapp_payment_gateway_test_public_key_callback', 'switchapp_payment_gateway', 'switchapp_payment_gateway_main');
    add_settings_field('switchapp_payment_gateway_live_public_key', 'Live Public Key', 'switchapp_payment_gateway_live_public_key_callback', 'switchapp_payment_gateway', 'switchapp_payment_gateway_main');

    // Ticket Limits
    add_settings_field('switchapp_ticket_limit_student', 'Student Ticket Limit', 'switchapp_ticket_limit_student_callback', 'switchapp_payment_gateway', 'switchapp_payment_gateway_main');
    add_settings_field('switchapp_ticket_limit_regular', 'Regular Ticket Limit', 'switchapp_ticket_limit_regular_callback', 'switchapp_payment_gateway', 'switchapp_payment_gateway_main');
    add_settings_field('switchapp_ticket_limit_vip', 'VIP Ticket Limit', 'switchapp_ticket_limit_vip_callback', 'switchapp_payment_gateway', 'switchapp_payment_gateway_main');
}
add_action('admin_init', 'switchapp_payment_gateway_register_settings');

// Callback functions for setting fields
function switchapp_payment_gateway_test_mode_callback() {
    $test_mode = get_option('switchapp_payment_gateway_test_mode', 'no');
    echo '<input type="checkbox" name="switchapp_payment_gateway_test_mode" value="yes" ' . checked($test_mode, 'yes', false) . '> Enable Test Mode';
}

function switchapp_payment_gateway_test_public_key_callback() {
    $public_key = esc_attr(get_option('switchapp_payment_gateway_test_public_key'));
    echo '<input type="text" name="switchapp_payment_gateway_test_public_key" value="' . $public_key . '" />';
}

function switchapp_payment_gateway_live_public_key_callback() {
    $public_key = esc_attr(get_option('switchapp_payment_gateway_live_public_key'));
    echo '<input type="text" name="switchapp_payment_gateway_live_public_key" value="' . $public_key . '" />';
}

// Ticket Limit Fields
function switchapp_ticket_limit_student_callback() {
    $limit = get_option('switchapp_ticket_limit_student', 200);
    echo '<input type="number" name="switchapp_ticket_limit_student" value="' . esc_attr($limit) . '" min="1">';
}

function switchapp_ticket_limit_regular_callback() {
    $limit = get_option('switchapp_ticket_limit_regular', 300);
    echo '<input type="number" name="switchapp_ticket_limit_regular" value="' . esc_attr($limit) . '" min="1">';
}

function switchapp_ticket_limit_vip_callback() {
    $limit = get_option('switchapp_ticket_limit_vip', 50);
    echo '<input type="number" name="switchapp_ticket_limit_vip" value="' . esc_attr($limit) . '" min="1">';
}

// Payment Form Shortcode
function switchapp_payment_gateway_payment_form_shortcode() {
    ob_start();
    ?>
    <form id="payment-form" class="switchapp-payment-form">
        <div class="form-row">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" name="first_name" id="switchapp-first-name" placeholder="First Name" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" name="last_name" id="switchapp-last-name" placeholder="Last Name" required>
            </div>
        </div>

        <label for="email">Email:</label>
        <input type="email" name="email" id="switchapp-email" placeholder="Email@example.com" required><br>

        <label for="phone">Phone Number:</label>
        <input type="tel" name="phone" id="switchapp-phone" placeholder="080-0800-0000" required><br>

        <label for="organization">School/Organization Name:</label>
        <input type="text" name="organization" id="switchapp-organization" required><br>

        <label for="ticket-category">Ticket Category:</label>
        <select id="ticket-category" name="ticket_category" required>
            <option value="student" data-price="5000">Student - N5,000</option>
            <option value="regular" data-price="10000">Regular - N10,000</option>
            <option value="vip" data-price="20000">VIP - N20,000</option>
        </select><br>

        <label for="quantity">Number of Tickets:</label>
        <br>
        <div class="quantity-container">
            <button type="button" id="quantity-decrease" class="decrease-btn">-</button>
            <input type="text" id="ticket-quantity" name="ticket_quantity" value="1" readonly>
            <button type="button" id="quantity-increase" class="increase-btn">+</button>
        </div>
        <br>

        <label for="amount">Total Amount:</label>
        <input type="text" id="ticket-amount" value="5000" readonly><br>
        
        <div id="remaining-tickets" style="color:red; display:none;"></div>
        
        <button type="button" id="pay-now-button">Pay Now</button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('switchapp_payment_form', 'switchapp_payment_gateway_payment_form_shortcode');

// Create custom table on activation
register_activation_hook(__FILE__, 'switchapp_create_payments_table');
function switchapp_create_payments_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'switchapp_payments';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(50) NOT NULL,
        last_name varchar(50) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) NOT NULL,
        organization varchar(100),
        amount decimal(10,2) NOT NULL,
        ticket_type varchar(50) NOT NULL,
        quantity smallint NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Handle AJAX request to save payment details
add_action('wp_ajax_save_payment_details', 'switchapp_save_payment_details');
add_action('wp_ajax_nopriv_save_payment_details', 'switchapp_save_payment_details');

function switchapp_save_payment_details() {
    check_ajax_referer('switchapp_nonce', 'security');

    $ticket_type = sanitize_text_field($_POST['ticket_type']);
    $quantity = intval($_POST['quantity']);
    
    // Get ticket limit for selected type
    $limit_option = 'switchapp_ticket_limit_' . strtolower($ticket_type);
    $ticket_limit = get_option($limit_option, 100);

    global $wpdb;
    $table_name = $wpdb->prefix . 'switchapp_payments';

    // Calculate tickets sold
    $tickets_sold = $wpdb->get_var($wpdb->prepare("SELECT SUM(quantity) FROM $table_name WHERE ticket_type = %s", $ticket_type));
    $remaining_tickets = $ticket_limit - $tickets_sold;

    if ($remaining_tickets < $quantity) {
        wp_send_json_error('Not enough tickets available for this type.');
        wp_die();
    }

    // Save purchase details
    $data = array(
        'first_name' => sanitize_text_field($_POST['first_name']),
        'last_name' => sanitize_text_field($_POST['last_name']),
        'email' => sanitize_email($_POST['email']),
        'phone' => sanitize_text_field($_POST['phone']),
        'organization' => sanitize_text_field($_POST['organization']),
        'amount' => floatval($_POST['amount']),
        'ticket_type' => $ticket_type,
        'quantity' => $quantity,
        'created_at' => current_time('mysql'),
    );

    $result = $wpdb->insert($table_name, $data);

    if ($result) {
        wp_send_json_success('Payment details saved successfully.');
    } else {
        wp_send_json_error('Failed to save payment details.');
    }

    wp_die();
}

// AJAX to get remaining tickets for a specific type
add_action('wp_ajax_get_remaining_tickets', 'switchapp_get_remaining_tickets');
add_action('wp_ajax_nopriv_get_remaining_tickets', 'switchapp_get_remaining_tickets');

function switchapp_get_remaining_tickets() {
    check_ajax_referer('switchapp_nonce', 'security');

    $ticket_type = sanitize_text_field($_POST['ticket_type']);
    $limit_option = 'switchapp_ticket_limit_' . strtolower($ticket_type);
    $ticket_limit = get_option($limit_option, 100);

    global $wpdb;
    $table_name = $wpdb->prefix . 'switchapp_payments';
    $tickets_sold = $wpdb->get_var($wpdb->prepare("SELECT SUM(quantity) FROM $table_name WHERE ticket_type = %s", $ticket_type));
    $remaining_tickets = $ticket_limit - $tickets_sold;

    if ($remaining_tickets <= 10) {
        wp_send_json_success(array('remaining_tickets' => $remaining_tickets));
    } else {
        wp_send_json_success(array('remaining_tickets' => null));
    }

    wp_die();
}
