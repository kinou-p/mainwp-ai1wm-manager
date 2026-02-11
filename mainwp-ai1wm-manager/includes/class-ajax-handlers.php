<?php

if (!defined('ABSPATH')) {
    exit;
}

class MainWP_AI1WM_Ajax_Handlers
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
        add_action('wp_ajax_ai1wm_create_backup', array($this, 'ajax_create_backup'));
        add_action('wp_ajax_ai1wm_list_backups', array($this, 'ajax_list_backups'));
        add_action('wp_ajax_ai1wm_delete_backup', array($this, 'ajax_delete_backup'));
        add_action('wp_ajax_ai1wm_download_backup', array($this, 'ajax_download_backup'));
    }

    /* ---------------------------------------------------------------
     *  HELPER: Handle child response
     * ------------------------------------------------------------- */
    private function handle_child_response($result, $success_msg = 'OK', $success_default = null, $site_id = 0)
    {
        $site_name = '';
        if ($site_id) {
            // Attempt to get site name for logging
             if (class_exists('\MainWP\Dashboard\MainWP_DB')) {
                $w = \MainWP\Dashboard\MainWP_DB::instance()->get_website_by_id($site_id);
                if ($w) $site_name = $w->name;
             }
        }

        if (is_string($result) && !empty($result)) {
            $decoded = json_decode($result, true);
            if (is_array($decoded)) {
                $result = $decoded;
            }
        }

        if (is_array($result)) {
            if (isset($result['error']) && !empty($result['error'])) {
                if (class_exists('MainWP_AI1WM_Logger')) {
                    MainWP_AI1WM_Logger::log('Error: ' . $result['error'], 'error', $site_name);
                }
                wp_send_json_error($result['error']);
                return;
            }
            if (isset($result['success']) && $result['success']) {
                if (class_exists('MainWP_AI1WM_Logger') && !empty($success_msg) && $success_msg !== 'OK') {
                     MainWP_AI1WM_Logger::log($success_msg, 'success', $site_name);
                }
                wp_send_json_success(isset($result['data']) ? $result['data'] : $success_default);
                return;
            }
            if (isset($result['result']) && 'ok' === $result['result']) {
                if (class_exists('MainWP_AI1WM_Logger') && !empty($success_msg) && $success_msg !== 'OK') {
                     MainWP_AI1WM_Logger::log($success_msg, 'success', $site_name);
                }
                wp_send_json_success(isset($result['data']) ? $result['data'] : $success_default);
                return;
            }
            if (!empty($result) && !isset($result['error'])) {
                wp_send_json_success($result);
                return;
            }
        }

        if (is_object($result)) {
            $arr = (array) $result;
            if (isset($arr['error'])) {
                if (class_exists('MainWP_AI1WM_Logger')) {
                    MainWP_AI1WM_Logger::log('Error: ' . $arr['error'], 'error', $site_name);
                }
                wp_send_json_error($arr['error']);
                return;
            }
            if (!empty($arr)) {
                wp_send_json_success($arr);
                return;
            }
        }

        $debug = ' (type: ' . gettype($result) . ', val: ' . wp_json_encode($result) . ')';
        if (class_exists('MainWP_AI1WM_Logger')) {
            MainWP_AI1WM_Logger::log('Unexpected response' . $debug, 'error', $site_name);
        }
        wp_send_json_error('Réponse inattendue du site enfant.' . $debug);
    }

    /* ---------------------------------------------------------------
     *  AJAX Handlers
     * ------------------------------------------------------------- */

    public function ajax_create_backup()
    {
        check_ajax_referer('ai1wm_manager_nonce', '_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes.');
        }
        $site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : 0;
        if (!$site_id) {
            wp_send_json_error('ID de site invalide.');
        }
        // Call main class method
        $result = MainWP_AI1WM_Manager::get_instance()->send_to_child($site_id, 'ai1wm_create_backup');
        
        $this->handle_child_response($result, 'Sauvegarde créée avec succès.', 'Sauvegarde créée.', $site_id);
    }

    public function ajax_list_backups()
    {
        check_ajax_referer('ai1wm_manager_nonce', '_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes.');
        }
        $site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : 0;
        if (!$site_id) {
            wp_send_json_error('ID de site invalide.');
        }
        // Call main class method
        $result = MainWP_AI1WM_Manager::get_instance()->send_to_child($site_id, 'ai1wm_list_backups');

        // Do not log success for listing to avoid spam
        $this->handle_child_response($result, 'OK', array(), $site_id);
    }

    public function ajax_delete_backup()
    {
        check_ajax_referer('ai1wm_manager_nonce', '_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes.');
        }
        $site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : 0;
        $file_name = isset($_POST['file_name']) ? sanitize_file_name($_POST['file_name']) : '';
        if (!$site_id || empty($file_name)) {
            wp_send_json_error('Paramètres manquants.');
        }
        // Call main class method
        $result = MainWP_AI1WM_Manager::get_instance()->send_to_child($site_id, 'ai1wm_delete_backup', array('file_name' => $file_name));

        $this->handle_child_response($result, 'Fichier supprimé : ' . $file_name, 'Fichier supprimé.', $site_id);
    }

    public function ajax_download_backup()
    {
        check_ajax_referer('ai1wm_manager_nonce', '_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes.');
        }
        $site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : 0;
        $file_name = isset($_POST['file_name']) ? sanitize_file_name($_POST['file_name']) : '';
        if (!$site_id || empty($file_name)) {
            wp_send_json_error('Paramètres manquants.');
        }
        // Call main class method
        $result = MainWP_AI1WM_Manager::get_instance()->send_to_child($site_id, 'ai1wm_download_backup', array('file_name' => $file_name));

        $this->handle_child_response($result, 'Lien de téléchargement généré pour : ' . $file_name, null, $site_id);
    }
}
