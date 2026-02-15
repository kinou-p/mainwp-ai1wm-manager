<?php
/**
 * Plugin Name: MainWP AI1WM Manager - Child
 * Plugin URI:  https://github.com/kinou-p/mainwp-ai1wm-manager
 * Description: Child site companion for MainWP AI1WM Backup Manager. Handles backup requests from the Dashboard.
 * Version:     0.2.4
 * Author:      Alexandre Pommier
 * Author URI:  https://alexandre-pommier.com
 * License:     GPL-2.0+
 * Text Domain: mainwp-ai1wm-manager-child
 */

if (!defined('ABSPATH')) {
    exit;
}

// GitHub Autoupdate
add_action('plugins_loaded', function () {
    require_once plugin_dir_path(__FILE__) . 'class-github-updater.php';
    new MainWP_AI1WM_Github_Updater(
        __FILE__,
        'kinou-p/mainwp-ai1wm-manager',
        'mainwp-ai1wm-manager-child.zip'
        );
});

/**
 * Hook into MainWP Child's extra execution to handle custom actions.
 *
 * When the Dashboard sends a request via `mainwp_fetchurlauthed` with
 * $what = 'extra_execution', the MainWP Child plugin calls its
 * `extra_execution()` callable, which fires `do_action('mainwp_child_extra_execution')`.
 * We intercept it here.
 *
 * IMPORTANT: All responses MUST use MainWP_Helper::write() — NOT wp_send_json().
 * MainWP Child uses its own response format (base64 + JSON), so wp_send_json()
 * breaks the communication protocol.
 */
add_action('mainwp_child_extra_execution', 'ai1wm_child_handle_extra_execution');

function ai1wm_child_handle_extra_execution($information = array())
{
    // MainWP Child passes $_POST data. Read the action from $_POST.
    // phpcs:disable WordPress.Security.NonceVerification
    $action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';
    // phpcs:enable

    if (empty($action)) {
        return; // Not our request — let other hooks handle it.
    }

    switch ($action) {
        case 'ai1wm_create_backup':
            ai1wm_child_create_backup();
            break;

        case 'ai1wm_list_backups':
            ai1wm_child_list_backups();
            break;

        case 'ai1wm_delete_backup':
            // phpcs:disable WordPress.Security.NonceVerification
            $file_name = isset($_POST['file_name']) ? sanitize_file_name(wp_unslash($_POST['file_name'])) : '';
            // phpcs:enable
            ai1wm_child_delete_backup($file_name);
            break;

        case 'ai1wm_download_backup':
            // phpcs:disable WordPress.Security.NonceVerification
            $file_name = isset($_POST['file_name']) ? sanitize_file_name(wp_unslash($_POST['file_name'])) : '';
            // phpcs:enable
            ai1wm_child_download_backup($file_name);
            break;

        default:
            return; // Not our action.
    }
}

/* =================================================================
 *  Helper: Respond via MainWP protocol
 * ================================================================= */

/**
 * Write response using MainWP_Helper::write() if available,
 * otherwise fall back to wp_send_json.
 *
 * @param array $data Response data.
 */
function ai1wm_child_respond($data)
{
    if (class_exists('\MainWP\Child\MainWP_Helper')) {
        \MainWP\Child\MainWP_Helper::write($data);
    }
    elseif (class_exists('MainWP_Helper')) {
        MainWP_Helper::write($data);
    }
    else {
        // Fallback — should not happen if MainWP Child is active.
        wp_send_json($data);
    }
// MainWP_Helper::write() calls die() internally, so we won't reach here.
}

/**
 * Write error using MainWP_Helper::instance()->error() if available.
 *
 * @param string $message Error message.
 */
function ai1wm_child_respond_error($message)
{
    if (class_exists('\MainWP\Child\MainWP_Helper')) {
        \MainWP\Child\MainWP_Helper::instance()->error($message);
    }
    elseif (class_exists('MainWP_Helper') && method_exists('MainWP_Helper', 'instance')) {
        MainWP_Helper::instance()->error($message);
    }
    else {
        wp_send_json(array('error' => $message));
    }
}

/* =================================================================
 *  AI1WM Backup Directory
 * ================================================================= */

function ai1wm_child_get_backups_dir()
{
    // If AI1WM defines its own constant, prefer it.
    if (defined('AI1WM_BACKUPS_PATH')) {
        $dir = trailingslashit(AI1WM_BACKUPS_PATH);
    }
    else {
        $content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
        $dir = trailingslashit($content_dir . DIRECTORY_SEPARATOR . 'ai1wm-backups');
    }

    // Ensure .htaccess exists for security
    ai1wm_child_ensure_htaccess($dir);

    return $dir;
}

/**
 * Ensure .htaccess protection exists in backups directory.
 */
function ai1wm_child_ensure_htaccess($dir)
{
    if (!is_dir($dir)) {
        return; // Directory doesn't exist yet
    }

    $htaccess_file = $dir . '.htaccess';

    // Check if .htaccess already exists and has the right content
    if (file_exists($htaccess_file)) {
        $content = file_get_contents($htaccess_file);
        if (strpos($content, 'Order deny,allow') !== false || strpos($content, 'Require all denied') !== false) {
            return; // Already protected
        }
    }

    // Create or update .htaccess
    $htaccess_content = "# Protect AI1WM Backups\n";
    $htaccess_content .= "# Generated by MainWP AI1WM Manager\n\n";
    $htaccess_content .= "# Apache 2.4+\n";
    $htaccess_content .= "<IfModule mod_authz_core.c>\n";
    $htaccess_content .= "    Require all denied\n";
    $htaccess_content .= "</IfModule>\n\n";
    $htaccess_content .= "# Apache 2.2\n";
    $htaccess_content .= "<IfModule !mod_authz_core.c>\n";
    $htaccess_content .= "    Order deny,allow\n";
    $htaccess_content .= "    Deny from all\n";
    $htaccess_content .= "</IfModule>\n";

    @file_put_contents($htaccess_file, $htaccess_content);
}

/* =================================================================
 *  ACTION: Create Backup
 * ================================================================= */

function ai1wm_child_create_backup()
{
    // Check that All-in-One WP Migration is active
    if (!defined('AI1WM_PLUGIN_NAME') && !class_exists('Ai1wm_Main_Controller')) {
        ai1wm_child_respond_error('All-in-One WP Migration is not active on this site.');
        return;
    }

    // Check that wp_remote_post is available
    if (!function_exists('wp_remote_post')) {
        ai1wm_child_respond_error('wp_remote_post function not available.');
        return;
    }

    // Get AI1WM secret key for authentication
    $secret_key = get_option('ai1wm_secret_key', '');

    // Build the export options as AI1WM expects them
    $export_options = array(
        'action' => 'ai1wm_export',
        'secret_key' => $secret_key,
        'options' => wp_json_encode(array('action' => 'export')),
    );

    // Make internal request to AI1WM's AJAX handler
    $admin_url = admin_url('admin-ajax.php');
    $response = wp_remote_post($admin_url, array(
        'body' => $export_options,
        'timeout' => 300,
        'cookies' => $_COOKIE,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        ai1wm_child_respond_error('Request failed: ' . $response->get_error_message());
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode($body, true);

    // AI1WM returns a status file that tracks the export progress
    if (isset($data['status']) || isset($data['progress'])) {
        ai1wm_child_respond(array(
            'success' => true,
            'data' => 'Backup export started.',
        ));
        return;
    }

    // Check if we got HTTP 200 (even empty body can mean success for async operations)
    if ($code === 200) {
        ai1wm_child_respond(array(
            'success' => true,
            'data' => 'Backup export initiated (async).',
        ));
        return;
    }

    // If we get here, something went wrong
    $error_msg = 'Backup creation failed. HTTP ' . $code;
    if (!empty($body)) {
        $error_msg .= '. Response: ' . substr($body, 0, 200);
    }
    ai1wm_child_respond_error($error_msg);
}

/* =================================================================
 *  ACTION: List Backups
 * ================================================================= */

function ai1wm_child_list_backups()
{
    $backups_dir = ai1wm_child_get_backups_dir();

    if (!is_dir($backups_dir)) {
        ai1wm_child_respond(array(
            'success' => true,
            'data' => array(),
        ));
        return;
    }

    $files = glob($backups_dir . '*.wpress');
    $backups = array();

    if (is_array($files)) {
        foreach ($files as $file) {
            $backups[] = array(
                'name' => basename($file),
                'size' => filesize($file),
                'date' => wp_date('Y-m-d H:i:s', filemtime($file)),
            );
        }

        usort($backups, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
    }

    ai1wm_child_respond(array(
        'success' => true,
        'data' => $backups,
    ));
}

/* =================================================================
 *  ACTION: Delete Backup
 * ================================================================= */

function ai1wm_child_delete_backup($file_name)
{
    if (empty($file_name)) {
        ai1wm_child_respond_error('No file name provided.');
        return;
    }

    $file_name = sanitize_file_name($file_name);

    if (pathinfo($file_name, PATHINFO_EXTENSION) !== 'wpress') {
        ai1wm_child_respond_error('Invalid file type. Only .wpress files can be deleted.');
        return;
    }

    $backups_dir = ai1wm_child_get_backups_dir();
    $file_path = $backups_dir . $file_name;

    $real_backups = realpath($backups_dir);
    $real_file = realpath($file_path);

    if (false === $real_file || false === $real_backups) {
        ai1wm_child_respond_error('File not found.');
        return;
    }

    if (0 !== strpos($real_file, $real_backups)) {
        ai1wm_child_respond_error('Invalid file path (path traversal detected).');
        return;
    }

    if (!file_exists($real_file)) {
        ai1wm_child_respond_error('File does not exist.');
        return;
    }

    wp_delete_file($real_file);

    if (!file_exists($real_file)) {
        ai1wm_child_respond(array(
            'success' => true,
            'data' => 'File "' . $file_name . '" has been deleted.',
        ));
    }
    else {
        ai1wm_child_respond_error('Failed to delete the file. Check file permissions.');
    }
}

/* =================================================================
 *  ACTION: Download Backup (return URL)
 * ================================================================= */

function ai1wm_child_download_backup($file_name)
{
    if (empty($file_name)) {
        ai1wm_child_respond_error('No file name provided.');
        return;
    }

    $file_name = sanitize_file_name($file_name);

    if (pathinfo($file_name, PATHINFO_EXTENSION) !== 'wpress') {
        ai1wm_child_respond_error('Invalid file type.');
        return;
    }

    $backups_dir = ai1wm_child_get_backups_dir();
    $file_path = $backups_dir . $file_name;

    if (!file_exists($file_path)) {
        ai1wm_child_respond_error('File not found.');
        return;
    }

    // Generate a secure temporary download token (valid for 30 minutes)
    $token = wp_generate_password(32, false);
    $expiry = time() + 1800; // 30 minutes

    // Store token in transient
    set_transient('ai1wm_dl_token_' . $token, array(
        'file' => $file_name,
        'expiry' => $expiry,
    ), 1800);

    // Build secure download URL using admin-ajax.php
    $download_url = add_query_arg(array(
        'action' => 'ai1wm_child_secure_download',
        'token' => $token,
        'file' => urlencode($file_name),
    ), admin_url('admin-ajax.php'));

    ai1wm_child_respond(array(
        'success' => true,
        'data' => array(
            'url' => $download_url,
            'name' => $file_name,
            'size' => filesize($file_path),
            'expires' => $expiry,
        ),
    ));
}

/* =================================================================
 *  Secure Download Handler (prevents direct file access)
 * ================================================================= */
add_action('wp_ajax_ai1wm_child_secure_download', 'ai1wm_child_secure_download_handler');
add_action('wp_ajax_nopriv_ai1wm_child_secure_download', 'ai1wm_child_secure_download_handler');

function ai1wm_child_secure_download_handler()
{
    // phpcs:disable WordPress.Security.NonceVerification
    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
    $file_name = isset($_GET['file']) ? sanitize_file_name(wp_unslash($_GET['file'])) : '';
    // phpcs:enable

    if (empty($token) || empty($file_name)) {
        wp_die('Invalid download request.', 'Error', array('response' => 403));
    }

    // Verify token
    $token_data = get_transient('ai1wm_dl_token_' . $token);
    if (!$token_data || $token_data['file'] !== $file_name) {
        wp_die('Download link expired or invalid.', 'Error', array('response' => 403));
    }

    // Check if token is expired
    if (isset($token_data['expiry']) && time() > $token_data['expiry']) {
        delete_transient('ai1wm_dl_token_' . $token);
        wp_die('Download link expired.', 'Error', array('response' => 403));
    }

    // Don't delete token - allow retries within expiry window (30 minutes)
    // Token will be automatically deleted by WordPress when it expires

    // Verify file
    $backups_dir = ai1wm_child_get_backups_dir();
    $file_path = $backups_dir . $file_name;

    $real_backups = realpath($backups_dir);
    $real_file = realpath($file_path);

    if (false === $real_file || false === $real_backups || 0 !== strpos($real_file, $real_backups)) {
        wp_die('Invalid file path.', 'Error', array('response' => 403));
    }

    if (!file_exists($real_file)) {
        wp_die('File not found.', 'Error', array('response' => 404));
    }

    // Stream the file
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($real_file) . '"');
    header('Content-Length: ' . filesize($real_file));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Use readfile for large files
    @ini_set('memory_limit', '-1');
    readfile($real_file);
    exit;
}