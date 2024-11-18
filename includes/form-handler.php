<?php
if (!defined('ABSPATH')) exit;

// Register AJAX actions for logged-in and guest users
add_action('wp_ajax_submit_form', 'handle_form_submission');
add_action('wp_ajax_nopriv_submit_form', 'handle_form_submission');

function handle_form_submission() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fsm_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
        exit;
    }

    // Check if the form data exists
    if (!isset($_POST) || empty($_POST)) {
        wp_send_json_error(['message' => 'No form data received.']);
        exit;
    }

    // Sanitize and collect form data
    $form_data = array_map('sanitize_text_field', $_POST);

    // Remove unnecessary fields
    unset($form_data['action'], $form_data['nonce']);

    // Handle signature (if exists)
    if (!empty($form_data['handtekening'])) {
        $signature_data = $form_data['handtekening'];
        unset($form_data['handtekening']); // Remove from direct storage

        // Decode and save the signature as a PNG file
        $decoded_signature = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $signature_data));

        if ($decoded_signature === false) {
            wp_send_json_error(['message' => 'Invalid signature data.']);
            exit;
        }

        $upload_dir = wp_upload_dir();
        $signature_path = $upload_dir['path'] . '/signature-' . time() . '.png';
        if (!file_put_contents($signature_path, $decoded_signature)) {
            wp_send_json_error(['message' => 'Failed to save signature file.']);
            exit;
        }

        // Save the signature URL to the form data
        $form_data['signature_file'] = str_replace($upload_dir['path'], $upload_dir['url'], $signature_path);
    }

    // Save the submission to the database
    global $wpdb;
    $table_name = $wpdb->prefix . 'form_submissions';
    $result = $wpdb->insert(
        $table_name,
        array('form_data' => json_encode($form_data))
    );

    // Handle database response
    if ($result) {
        wp_send_json_success(['message' => 'Form submitted successfully.']);
    } else {
        wp_send_json_error(['message' => 'Error saving submission to the database.']);
    }

    exit;
}