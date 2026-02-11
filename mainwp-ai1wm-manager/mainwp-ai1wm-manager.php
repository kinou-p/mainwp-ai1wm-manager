<?php
/**
 * Plugin Name: MainWP AI1WM Backup Manager
 * Plugin URI:  https://github.com/kinou-p/mainwp-ai1wm-manager
 * Description: Manage All-in-One WP Migration backups on child sites directly from the MainWP Dashboard.
 * Version:     1.1.3
 * Author:      Alexandre Pommier
 * Author URI:  https://alexandre-pommier.com
 * License:     GPL-2.0+
 * Text Domain: mainwp-ai1wm-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MAINWP_AI1WM_MANAGER_VERSION', '1.1.3');
define('MAINWP_AI1WM_MANAGER_FILE', __FILE__);
define('MAINWP_AI1WM_MANAGER_DIR', plugin_dir_path(__FILE__));
define('MAINWP_AI1WM_MANAGER_URL', plugin_dir_url(__FILE__));

// Check compatibility
function mainwp_ai1wm_manager_check_mainwp()
{
    if (!class_exists('MainWP\Dashboard\MainWP_System') && !defined('MAINWP_PLUGIN_FILE')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('MainWP AI1WM Backup Manager requires MainWP Dashboard to be installed and activated.', 'mainwp-ai1wm-manager');
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

// Includes
require_once dirname(__FILE__) . '/includes/class-logger.php';
require_once dirname(__FILE__) . '/includes/class-ajax-handlers.php';

add_action('plugins_loaded', function () {
    if (mainwp_ai1wm_manager_check_mainwp()) {
        MainWP_AI1WM_Manager::get_instance();
    }

    // GitHub Autoupdate
    if (file_exists(plugin_dir_path(__FILE__) . 'class-github-updater.php')) {
        require_once plugin_dir_path(__FILE__) . 'class-github-updater.php';
        if (class_exists('MainWP_AI1WM_Github_Updater')) {
            new MainWP_AI1WM_Github_Updater(
                __FILE__,
                'kinou-p/mainwp-ai1wm-manager',
                'mainwp-ai1wm-manager.zip'
            );
        }
    }
});

class MainWP_AI1WM_Manager
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'), 200);

        // Initialize sub-components
        MainWP_AI1WM_Logger::get_instance();
        MainWP_AI1WM_Ajax_Handlers::get_instance();
    }

    public function register_menu()
    {
        add_submenu_page(
            'mainwp_tab',
            __('AI1WM Backup Manager', 'mainwp-ai1wm-manager'),
            __('AI1WM Backups', 'mainwp-ai1wm-manager'),
            'manage_options',
            'mainwp-ai1wm-manager',
            array($this, 'render_page')
        );
    }

    /* ---------------------------------------------------------------
     *  ADMIN PAGE
     * ------------------------------------------------------------- */

    public function render_page()
    {
        $sites = $this->get_child_sites();

        // Enqueue Assets
        wp_enqueue_style(
            'ai1wm-dashboard-css',
            MAINWP_AI1WM_MANAGER_URL . 'assets/css/dashboard.css',
            array(),
            MAINWP_AI1WM_MANAGER_VERSION
        );

        // Enqueue Fonts (Google Fonts)
        wp_enqueue_style(
            'ai1wm-fonts',
            'https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&display=swap',
            array(),
            null
        );
        wp_enqueue_style(
            'ai1wm-icons',
            'https://fonts.googleapis.com/icon?family=Material+Icons+Round',
            array(),
            null
        );

        wp_enqueue_script(
            'ai1wm-dashboard-js',
            MAINWP_AI1WM_MANAGER_URL . 'assets/js/dashboard.js',
            array('jquery'),
            MAINWP_AI1WM_MANAGER_VERSION,
            true
        );

        wp_localize_script('ai1wm-dashboard-js', 'ai1wm_vars', array(
            'nonce' => wp_create_nonce('ai1wm_manager_nonce')
        ));

        // Include Template
        include dirname(__FILE__) . '/templates/dashboard.php';
    }

    /* ---------------------------------------------------------------
     *  HELPER: Send to child site
     * ------------------------------------------------------------- */

    public function send_to_child($site_id, $action, $params = array())
    {
        $post_data = array_merge(
            array('action' => $action),
            $params
        );

        $method = 'Unknown';

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AI1WM Manager] send_to_child: ' . $action . ' for site ' . $site_id);
        }

        try {
            $result = null;

            // Method 1: Use MainWP_Connect directly (most reliable for non-licensed extensions).
            if (class_exists('\MainWP\Dashboard\MainWP_DB') && class_exists('\MainWP\Dashboard\MainWP_Connect')) {
                $method = 'Method 1 (MainWP_Connect)';
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('[AI1WM Manager] Using ' . $method);
                
                $website = \MainWP\Dashboard\MainWP_DB::instance()->get_website_by_id($site_id);
                if ($website) {
                    // Do not pass file path - it causes NOMAINWP error for non-registered extensions
                    // MainWP will skip extension verification if no file is provided
                    $result = \MainWP\Dashboard\MainWP_Connect::fetch_url_authed(
                        $website,
                        'extra_execution',
                        $post_data
                    );
                } else {
                    return array('error' => 'Site ID ' . $site_id . ' not found in MainWP database.');
                }
            }
            // Method 2: Legacy class names (older MainWP versions).
            elseif (class_exists('MainWP_DB') && class_exists('MainWP_Connect')) {
                $method = 'Method 2 (Legacy)';
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('[AI1WM Manager] Using ' . $method);

                $website = MainWP_DB::instance()->get_website_by_id($site_id);
                if ($website) {
                    // Do not pass file path - it causes NOMAINWP error for non-registered extensions
                    $result = MainWP_Connect::fetch_url_authed(
                        $website,
                        'extra_execution',
                        $post_data
                    );
                } else {
                    return array('error' => 'Site ID ' . $site_id . ' not found in MainWP database (legacy).');
                }
            }
            // Method 3: Fallback to apply_filters.
            else {
                $method = 'Method 3 (apply_filters)';
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('[AI1WM Manager] Using ' . $method);

                // Do not pass file path - causes NOMAINWP for non-registered extensions
                // The filter expects: (key, function, website_id, what, post_data)
                $result = apply_filters(
                    'mainwp_fetchurlauthed',
                    '',  // Empty key instead of file path
                    '',
                    $site_id,
                    'extra_execution',
                    $post_data
                );
            }
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            error_log('[AI1WM Manager] Exception for site ID ' . $site_id . ': ' . $error_message);
            
            // Helpful context for "NOMAINWP"
            if (strpos($error_message, 'NOMAINWP') !== false) {
                 $error_message .= ' (MainWP Dashboard verification failed. Ensure the extension is properly registered or the file path is correct.)';
            }
            
            return array('error' => 'MainWP Request Failed: ' . $error_message . ' [Action: ' . $action . '] [Method: ' . $method . ']');
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AI1WM Manager] Result type: ' . gettype($result));
        }

        return $result;
    }

    /* ---------------------------------------------------------------
     *  HELPER: Get child sites with AI1WM installed (via tag or all)
     * ------------------------------------------------------------- */

    private function get_child_sites()
    {
        global $wpdb;
        $sites = array();
        $table = $wpdb->prefix . 'mainwp_wp';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return $sites;
        }

        $results = $wpdb->get_results(
            "SELECT id, name, url FROM {$table} ORDER BY name ASC",
            ARRAY_A
        );

        if ($results) {
            foreach ($results as $row) {
                $sites[] = array(
                    'id' => intval($row['id']),
                    'name' => $row['name'],
                    'url' => $row['url']
                );
            }
        }
        return $sites;
    }
}
