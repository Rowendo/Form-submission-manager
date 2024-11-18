<?php
/*
Plugin Name: Form Submission Manager
Description: Manages form submissions, including signature pad functionality, CSV export, and deletion in the admin panel.
Version: 2.3
Author: Recruitmarketing
*/

if (!defined('ABSPATH')) exit;

// Create database table on activation
register_activation_hook(__FILE__, 'fsm_create_table');
function fsm_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'form_submissions';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        submission_date datetime DEFAULT CURRENT_TIMESTAMP,
        form_data longtext NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add admin menu
add_action('admin_menu', 'fsm_admin_menu');
function fsm_admin_menu() {
    add_menu_page(
        'Form Submissions',
        'Form Submissions',
        'edit_posts',
        'form-submissions',
        'fsm_admin_page',
        'dashicons-feedback',
        30
    );
}

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'enqueue_fsm_assets');
function enqueue_fsm_assets() {
    // Enqueue CSS
    wp_enqueue_style(
        'fsm-styles',
        plugin_dir_url(__FILE__) . 'css/fsm-styles.css',
        [],
        null
    );

    // Enqueue Signature Pad library
    wp_enqueue_script(
        'signature-pad',
        'https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js',
        [],
        null,
        true
    );

    // Enqueue custom initialization script for the signature pad
    wp_enqueue_script(
        'signature-pad-init',
        plugin_dir_url(__FILE__) . 'js/signature-pad-init.js',
        ['signature-pad'], // Depends on signature-pad
        null,
        true
    );

    // Enqueue form handler script for AJAX handling
    wp_enqueue_script(
        'form-handler',
        plugin_dir_url(__FILE__) . 'js/form-handler.js',
        ['jquery'], // Assuming jQuery is required for other functionality
        null,
        true
    );

    // Pass WordPress data to JavaScript
    wp_localize_script('form-handler', 'fsmData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('fsm_nonce'),
    ]);
}

// Handle form submission
add_action('init', 'fsm_handle_form_submission');
function fsm_handle_form_submission() {
    if (isset($_POST['fsm_submit_form'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        // Sanitize and process form data
        $form_data = array_map('sanitize_text_field', $_POST);

        // Process signature data
        if (!empty($_POST['handtekening'])) {
            $signature_data = $_POST['handtekening'];

            // Decode the Base64 string
            $decoded_signature = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $signature_data));

            // Save the signature as a PNG file
            $upload_dir = wp_upload_dir();
            $signature_path = $upload_dir['path'] . '/signature-' . time() . '.png';
            file_put_contents($signature_path, $decoded_signature);

            // Add the signature file URL to the form data
            $form_data['signature_file'] = str_replace($upload_dir['path'], $upload_dir['url'], $signature_path);
        }

        // Save form data to the database
        $wpdb->insert($table_name, ['form_data' => json_encode($form_data)]);

        // Redirect to avoid resubmission
        wp_redirect(add_query_arg('form_submitted', 'true', $_SERVER['REQUEST_URI']));
        exit;
    }
}

// Export CSV functionality
add_action('admin_post_fsm_export_to_csv', 'fsm_export_to_csv');
function fsm_export_to_csv() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized access');
    check_admin_referer('export_submissions');

    global $wpdb;
    $table_name = $wpdb->prefix . 'form_submissions';
    $submissions = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    if (!empty($submissions)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="form-submissions-' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');

        $headers = ['ID', 'Form Type', 'Submission Date', 'Signature File'];
        foreach ($submissions as $submission) {
            $data = json_decode($submission['form_data'], true);
            $headers = array_merge($headers, array_keys($data));
        }
        $headers = array_unique($headers);
        fputcsv($output, $headers);

        foreach ($submissions as $submission) {
            $data = json_decode($submission['form_data'], true);
            $row = [
                $submission['id'],
                $data['form_type'] ?? '',
                $submission['submission_date'],
                $data['signature_file'] ?? '',
            ];
            foreach (array_slice($headers, 4) as $header) {
                $row[] = $data[$header] ?? '';
            }
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    wp_redirect(admin_url('admin.php?page=form-submissions'));
    exit;
}

// Render the form with the signature pad
add_shortcode('fsm_form_person', 'fsm_render_form_person');
function fsm_render_form_person() {
    ob_start();
    ?>
    <div class="fsm-form-wrapper">
        <form method="post" action="">
            <input type="hidden" name="form_type" value="person">
            <h2>Doorlopende machtiging</h2>
            
            <div class="row">
                <div class="half-width">
                    <label>Naam incassant:</label>
                    <input type="text" name="naam_incassant" value="Ebbinge B.V." readonly>
                </div>
                <div class="half-width">
                    <label>Adres incassant:</label>
                    <input type="text" name="adres_incassant" value="Fred. Roeskestraat 115" readonly>
                </div>
            </div>

            <div class="row">
                <div class="half-width">
                    <label>Postcode incassant:</label>
                    <input type="text" name="postcode_incassant" value="1076 EE" readonly>
                </div>
                <div class="half-width">
                    <label>Woonplaats incassant:</label>
                    <input type="text" name="woonplaats_incassant" value="Amsterdam" readonly>
                </div>
            </div>

            <div class="row">
                <div class="half-width">
                    <label>Land incassant:</label>
                    <input type="text" name="land_incassant" value="Nederland" readonly>
                </div>
                <div class="half-width">
                    <label>Incassant ID:</label>
                    <input type="text" name="incassant_id" value="NL32ZZZ663344970000" readonly>
                </div>
            </div>

            <div class="row">
                <div class="half-width">
                    <label>Kenmerk machtiging:</label>
                    <input type="text" name="kenmerk_machtiging" value="Jaarlidmaatschap netwerk échte leiders" readonly>
                </div>
            </div>

            <div class="info-box">
                Door ondertekening van dit formulier geeft u toestemming aan <strong>Ebbinge B.V.</strong> om doorlopende incasso-opdrachten te sturen naar uw bank om een bedrag van uw rekening af te schrijven wegens het <em>jaarlidmaatschap netwerk échte leiders</em> en uw bank om doorlopend een bedrag van uw rekening af te schrijven overeenkomstig de opdracht van <strong>Ebbinge B.V.</strong> Als u het niet eens bent met deze afschrijving kunt u deze laten terugboeken. Neem hiervoor binnen acht weken na afschrijving contact op met uw bank. Vraag uw bank naar de voorwaarden.
            </div>

            <label>Naam:</label>
            <input type="text" name="naam" required>

            <label>Adres:</label>
            <input type="text" name="adres" required>

            <div class="row">
                <div class="half-width">
                    <label>Postcode:</label>
                    <input type="text" name="postcode" required>
                </div>
                <div class="half-width">
                    <label>Woonplaats:</label>
                    <input type="text" name="woonplaats" required>
                </div>
            </div>

            <label>Land:</label>
            <input type="text" name="land">

            <label>Rekeningnummer [IBAN]:</label>
            <input type="text" name="iban" required>

            <div class="row">
                <div class="half-width">
                    <label>Bank Identificatie [BIC]:</label>
                    <input type="text" name="bic">
                </div>
                <div class="half-width">
                    <label>Plaats en datum:</label>
                    <input type="text" name="plaats_datum" required>
                </div>
            </div>

    <label for="signature-pad">Handtekening:</label>
    <canvas id="signature-pad" width="400" height="200" style="border: 1px solid #B3CAAB;"></canvas>
    <button type="button" id="clear-signature">Wis handtekening</button>
    <input type="hidden" name="handtekening" id="handtekening-data">
            <button type="submit" name="fsm_submit_form">Verstuur</button>
        </form>
        <div class="asterisk">* Indien het land van de incassant en de geïncasseerde gelijk zijn, hoeft dit niet gevraagd of ingevuld te worden.</div>
        <div class="asterisk">** Geen verplicht veld bij Nederlands rekeningnummer</div>
    </div>
    <?php
    return ob_get_clean();
}

// Display submissions in the admin panel
function fsm_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'form_submissions';
    $submissions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submission_date DESC");

    ?>
    <div class="wrap">
        <h1>Form Submissions</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Form Type</th>
                    <th>Submission Date</th>
                    <th>Signature File</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $submission):
                    $data = json_decode($submission['form_data'], true); ?>
                    <tr>
                        <td><?php echo esc_html($submission['id']); ?></td>
                        <td><?php echo esc_html($data['form_type'] ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($submission['submission_date']); ?></td>
                        <td>
                            <?php if (!empty($data['signature_file'])): ?>
                                <a href="<?php echo esc_url($data['signature_file']); ?>" target="_blank">View Signature</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=fsm_delete_submission&submission_id=' . $submission['id']), 'delete_submission_' . $submission['id']); ?>" class="button button-secondary">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Handle deletion of submissions
add_action('admin_post_fsm_delete_submission', 'fsm_delete_submission');
function fsm_delete_submission() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized access');

    $submission_id = intval($_GET['submission_id']);
    check_admin_referer('delete_submission_' . $submission_id);

    global $wpdb;
    $table_name = $wpdb->prefix . 'form_submissions';
    $wpdb->delete($table_name, ['id' => $submission_id]);

    wp_redirect(admin_url('admin.php?page=form-submissions'));
    exit;
}