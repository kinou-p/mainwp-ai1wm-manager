<?php
/**
 * Plugin Name: MainWP AI1WM Manager - Child
 * Plugin URI:  https://github.com/your-repo/mainwp-ai1wm-manager
 * Description: Child site companion for MainWP AI1WM Backup Manager. Handles backup requests from the Dashboard.
 * Version:     1.0.1
 * Author:      Alexandre Pommier
 * Author URI:  https://alexandre-pommier.com
 * License:     GPL-2.0+
 * Text Domain: mainwp-ai1wm-manager-child
 */

if (!defined('ABSPATH')) {
    exit;
}

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
    } elseif (class_exists('MainWP_Helper')) {
        MainWP_Helper::write($data);
    } else {
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
    } elseif (class_exists('MainWP_Helper') && method_exists('MainWP_Helper', 'instance')) {
        MainWP_Helper::instance()->error($message);
    } else {
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
        return trailingslashit(AI1WM_BACKUPS_PATH);
    }

    $content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
    return trailingslashit($content_dir . DIRECTORY_SEPARATOR . 'ai1wm-backups');
}

/* =================================================================
 *  ACTION: Create Backup
 * ================================================================= */

function ai1wm_child_create_backup()
{
    // Check that All-in-One WP Migration is active.
    if (!defined('AI1WM_PLUGIN_NAME') && !class_exists('Ai1wm_Main_Controller')) {
        ai1wm_child_respond_error('All-in-One WP Migration is not active on this site.');
        return;
    }

    // Method 1: Try WP-CLI if exec() is available.
    if (ai1wm_child_can_exec()) {
        $wp_path = ABSPATH;
        $command = sprintf(
            'cd %s && wp ai1wm backup --allow-root 2>&1',
            escapeshellarg(rtrim($wp_path, '/'))
        );
        $output = array();
        $code = 0;
        exec($command, $output, $code);

        if (0 === $code) {
            ai1wm_child_respond(array(
                'success' => true,
                'data' => 'Backup created via WP-CLI.',
            ));
            return;
        }
    }

    // Method 2: Trigger via AI1WM internal export controller.
    if (class_exists('Ai1wm_Export_Controller') && method_exists('Ai1wm_Export_Controller', 'export')) {
        try {
            $params = array();
            Ai1wm_Export_Controller::export($params);

            ai1wm_child_respond(array(
                'success' => true,
                'data' => 'Backup export started via AI1WM API.',
            ));
            return;
        } catch (\Exception $e) {
            // Fall through to next method.
        }
    }

    // Method 3: Trigger via AI1WM AJAX action if registered.
    if (has_action('wp_ajax_ai1wm_export')) {
        $_REQUEST['options'] = wp_json_encode(array());
        do_action('wp_ajax_ai1wm_export');

        ai1wm_child_respond(array(
            'success' => true,
            'data' => 'Backup export triggered via AJAX action.',
        ));
        return;
    }

    ai1wm_child_respond_error('Unable to trigger AI1WM backup. Neither WP-CLI nor AI1WM API is available.');
}

function ai1wm_child_can_exec()
{
    $disabled = explode(',', ini_get('disable_functions'));
    $disabled = array_map('trim', $disabled);
    return function_exists('exec') && !in_array('exec', $disabled, true);
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
    } else {
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

    // Build a direct URL to the file.
    $content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
    $content_url = defined('WP_CONTENT_URL') ? WP_CONTENT_URL : site_url('/wp-content');

    // Compute relative path from content dir to the backup file.
    $relative = str_replace($content_dir, '', $backups_dir);
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $url = trailingslashit($content_url) . $relative . $file_name;

    ai1wm_child_respond(array(
        'success' => true,
        'data' => array(
            'url' => $url,
            'name' => $file_name,
            'size' => filesize($file_path),
        ),
    ));
}

