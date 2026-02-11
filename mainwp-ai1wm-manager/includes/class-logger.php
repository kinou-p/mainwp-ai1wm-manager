<?php

if (!defined('ABSPATH')) {
    exit;
}

class MainWP_AI1WM_Logger
{
    private static $instance = null;
    private $option_name = 'mainwp_ai1wm_logs';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_ajax_ai1wm_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_ai1wm_clear_logs', array($this, 'ajax_clear_logs'));
    }

    public static function log($message, $type = 'info', $site_name = '')
    {
        $instance = self::get_instance();
        $logs = get_option($instance->option_name, array());
        if (!is_array($logs)) {
            $logs = array();
        }

        $new_log = array(
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'type' => $type,
            'site_name' => $site_name
        );

        // Prepend new log
        array_unshift($logs, $new_log);

        // Keep last 100 logs to prevent DB bloat
        if (count($logs) > 100) {
            $logs = array_slice($logs, 0, 100);
        }

        update_option($instance->option_name, $logs);
    }

    public function ajax_get_logs()
    {
        check_ajax_referer('ai1wm_manager_nonce', '_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes.');
        }

        $logs = get_option($this->option_name, array());
        wp_send_json_success($logs);
    }

    public function ajax_clear_logs()
    {
        check_ajax_referer('ai1wm_manager_nonce', '_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes.');
        }

        update_option($this->option_name, array());
        wp_send_json_success();
    }
}
