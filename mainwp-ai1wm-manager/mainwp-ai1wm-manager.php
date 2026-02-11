<?php
/**
 * Plugin Name: MainWP AI1WM Backup Manager
 * Plugin URI:  https://github.com/kinou-p/mainwp-ai1wm-manager
 * Description: Manage All-in-One WP Migration backups on child sites directly from the MainWP Dashboard.
 * Version:     1.1.1
 * Author:      Alexandre Pommier
 * Author URI:  https://alexandre-pommier.com
 * License:     GPL-2.0+
 * Text Domain: mainwp-ai1wm-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MAINWP_AI1WM_MANAGER_VERSION', '1.1.1');
define('MAINWP_AI1WM_MANAGER_FILE', __FILE__);
define('MAINWP_AI1WM_MANAGER_DIR', plugin_dir_path(__FILE__));
define('MAINWP_AI1WM_MANAGER_URL', plugin_dir_url(__FILE__));

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

add_action('plugins_loaded', function () {
    if (mainwp_ai1wm_manager_check_mainwp()) {
        MainWP_AI1WM_Manager::get_instance();
    }

    // GitHub Autoupdate
    require_once plugin_dir_path(__FILE__) . 'class-github-updater.php';
    new MainWP_AI1WM_Github_Updater(
        __FILE__,
        'kinou-p/mainwp-ai1wm-manager',
        'mainwp-ai1wm-manager.zip'
    );
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
        add_action('wp_ajax_ai1wm_create_backup', array($this, 'ajax_create_backup'));
        add_action('wp_ajax_ai1wm_list_backups', array($this, 'ajax_list_backups'));
        add_action('wp_ajax_ai1wm_delete_backup', array($this, 'ajax_delete_backup'));
        add_action('wp_ajax_ai1wm_download_backup', array($this, 'ajax_download_backup'));
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
        ?>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet" />
        <style>
            :root {
                --ai-primary: #25f478;
                --ai-primary-hover: #20d669;
                --ai-secondary: #3b82f6;
                --ai-secondary-hover: #2563eb;
                --ai-danger: #ef4444;
                --ai-danger-hover: #dc2626;
                --ai-bg: #0f172a;
                --ai-surface: #1e293b;
                --ai-surface-hover: rgba(30, 41, 59, 0.8);
                --ai-glass: rgba(30, 41, 59, 0.6);
                --ai-glass-border: rgba(255, 255, 255, 0.08);
                --ai-text: #e2e8f0;
                --ai-text-muted: #94a3b8;
                --ai-text-dim: #64748b;
                --ai-radius: 12px;
                --ai-radius-sm: 8px;
                --ai-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
                --ai-neon: 0 0 10px rgba(37, 244, 120, 0.3), 0 0 20px rgba(37, 244, 120, 0.1);
            }

            /* Override WP admin background */
            #wpbody-content {
                background: var(--ai-bg) !important;
            }

            #wpbody {
                background: var(--ai-bg) !important;
            }

            .ai1wm-wrap {
                max-width: 1400px;
                margin: 0 auto;
                padding: 32px;
                font-family: 'Manrope', -apple-system, BlinkMacSystemFont, sans-serif;
                color: var(--ai-text);
                position: relative;
            }

            .ai1wm-wrap * {
                box-sizing: border-box;
            }

            /* Background glows */
            .ai1wm-wrap::before,
            .ai1wm-wrap::after {
                content: '';
                position: absolute;
                border-radius: 50%;
                pointer-events: none;
                z-index: 0;
            }

            .ai1wm-wrap::before {
                top: -80px;
                right: 10%;
                width: 500px;
                height: 500px;
                background: rgba(59, 130, 246, 0.08);
                filter: blur(100px);
            }

            .ai1wm-wrap::after {
                bottom: -60px;
                left: 5%;
                width: 400px;
                height: 400px;
                background: rgba(37, 244, 120, 0.05);
                filter: blur(80px);
            }

            /* Glass panel */
            .glass-panel {
                background: var(--ai-glass);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border: 1px solid var(--ai-glass-border);
                border-radius: var(--ai-radius);
            }

            /* ---- Header ---- */
            .ai1wm-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 28px;
                flex-wrap: wrap;
                gap: 16px;
                position: relative;
                z-index: 1;
            }

            .ai1wm-header-left {
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .ai1wm-header-icon {
                padding: 10px;
                background: linear-gradient(135deg, #1e293b, #0f172a);
                border-radius: var(--ai-radius);
                border: 1px solid rgba(71, 85, 105, 0.5);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            }

            .ai1wm-header-icon .material-icons-round {
                font-size: 28px;
                color: var(--ai-primary);
                filter: drop-shadow(0 0 8px rgba(37, 244, 120, 0.5));
            }

            .ai1wm-header h1 {
                font-size: 22px;
                font-weight: 700;
                color: #fff;
                letter-spacing: -0.3px;
                margin: 0;
                padding: 0;
            }

            .ai1wm-header .ai1wm-version {
                font-size: 12px;
                color: var(--ai-text-dim);
                margin-top: 2px;
            }

            /* ---- Toolbar ---- */
            .ai1wm-toolbar {
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
            }

            .ai1wm-selected-count {
                font-size: 12px;
                color: var(--ai-text-muted);
                font-weight: 500;
                padding: 6px 14px;
                border-radius: 9999px;
                background: rgba(15, 23, 42, 0.8);
                border: 1px solid rgba(71, 85, 105, 0.5);
            }

            .ai1wm-selected-count .count-num {
                color: var(--ai-primary);
                font-weight: 700;
                margin-right: 4px;
            }

            /* ---- Buttons ---- */
            .ai1wm-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 18px;
                border: none;
                border-radius: var(--ai-radius-sm);
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all .2s ease;
                line-height: 1.4;
                white-space: nowrap;
                font-family: 'Manrope', sans-serif;
            }

            .ai1wm-btn:disabled {
                opacity: .45;
                cursor: not-allowed;
            }

            .ai1wm-btn .material-icons-round {
                font-size: 18px;
            }

            .ai1wm-btn-primary {
                background: var(--ai-primary);
                color: #0f172a;
                box-shadow: 0 0 15px rgba(37, 244, 120, 0.3);
            }

            .ai1wm-btn-primary:hover:not(:disabled) {
                background: var(--ai-primary-hover);
                box-shadow: 0 0 25px rgba(37, 244, 120, 0.5);
            }

            .ai1wm-btn-secondary {
                background: var(--ai-secondary);
                color: #fff;
                box-shadow: 0 4px 15px rgba(59, 130, 246, 0.25);
            }

            .ai1wm-btn-secondary:hover:not(:disabled) {
                background: var(--ai-secondary-hover);
                box-shadow: 0 4px 20px rgba(59, 130, 246, 0.35);
            }

            .ai1wm-btn-danger {
                background: var(--ai-danger);
                color: #fff;
            }

            .ai1wm-btn-danger:hover:not(:disabled) {
                background: var(--ai-danger-hover);
            }

            .ai1wm-btn-sm {
                padding: 6px 12px;
                font-size: 12px;
                border-radius: 6px;
            }

            .ai1wm-btn-sm .material-icons-round {
                font-size: 16px;
            }

            .ai1wm-btn-outline {
                background: transparent;
                color: var(--ai-text-muted);
                border: 1px solid rgba(71, 85, 105, 0.6);
            }

            .ai1wm-btn-outline:hover:not(:disabled) {
                border-color: rgba(148, 163, 184, 0.5);
                color: #fff;
                background: rgba(30, 41, 59, 0.5);
            }

            .ai1wm-btn-ghost {
                background: rgba(30, 41, 59, 0.5);
                border: 1px solid rgba(71, 85, 105, 0.5);
                color: var(--ai-text-muted);
                padding: 8px;
            }

            .ai1wm-btn-ghost:hover:not(:disabled) {
                color: #fff;
                border-color: rgba(148, 163, 184, 0.5);
            }

            .ai1wm-btn-icon-primary {
                background: rgba(30, 41, 59, 0.5);
                border: 1px solid rgba(71, 85, 105, 0.5);
                color: var(--ai-primary);
                padding: 8px;
                border-radius: var(--ai-radius-sm);
            }

            .ai1wm-btn-icon-primary:hover:not(:disabled) {
                background: var(--ai-primary);
                color: #0f172a;
            }

            /* ---- Stats Cards ---- */
            .ai1wm-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 28px;
                position: relative;
                z-index: 1;
            }

            .ai1wm-stat-card {
                padding: 18px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .ai1wm-stat-card .stat-label {
                font-size: 11px;
                text-transform: uppercase;
                font-weight: 600;
                color: var(--ai-text-dim);
                letter-spacing: 0.5px;
            }

            .ai1wm-stat-card .stat-value {
                font-size: 24px;
                font-weight: 700;
                color: #fff;
                margin-top: 4px;
            }

            .ai1wm-stat-icon {
                width: 42px;
                height: 42px;
                border-radius: var(--ai-radius-sm);
                background: rgba(71, 85, 105, 0.3);
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--ai-text-muted);
            }

            .ai1wm-stat-icon.green {
                background: rgba(37, 244, 120, 0.1);
                color: var(--ai-primary);
                border: 1px solid rgba(37, 244, 120, 0.2);
            }

            /* ---- Table ---- */
            .ai1wm-table-wrap {
                position: relative;
                z-index: 1;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            }

            .ai1wm-table {
                width: 100%;
                border-collapse: collapse;
            }

            .ai1wm-table thead {
                background: rgba(15, 23, 42, 0.6);
            }

            .ai1wm-table th {
                padding: 14px 18px;
                text-align: left;
                font-weight: 600;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                color: var(--ai-text-dim);
                border-bottom: 1px solid var(--ai-glass-border);
            }

            .ai1wm-table td {
                padding: 14px 18px;
                vertical-align: middle;
                font-size: 13px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            }

            .ai1wm-table tbody tr {
                transition: background .15s ease;
            }

            .ai1wm-table tbody tr:hover {
                background: rgba(30, 41, 59, 0.4);
            }

            .ai1wm-table .cb-col {
                width: 50px;
                text-align: center;
            }

            .ai1wm-table .cb-col input[type="checkbox"] {
                width: 16px;
                height: 16px;
                cursor: pointer;
                accent-color: var(--ai-primary);
                border-radius: 4px;
            }

            .site-avatar {
                width: 34px;
                height: 34px;
                border-radius: 6px;
                flex-shrink: 0;
            }

            .site-info {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .site-name {
                font-weight: 700;
                color: #fff;
                font-size: 14px;
            }

            .site-status {
                font-size: 11px;
                color: var(--ai-text-dim);
                margin-top: 2px;
            }

            .site-url {
                color: var(--ai-text-dim);
                text-decoration: none;
                font-weight: 400;
                transition: color .15s;
            }

            .site-url:hover {
                color: var(--ai-secondary);
                text-decoration: underline;
            }

            .ai1wm-actions {
                display: flex;
                gap: 8px;
                justify-content: flex-end;
            }

            /* ---- Toggle Arrow ---- */
            .ai1wm-toggle {
                cursor: pointer;
                user-select: none;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                color: var(--ai-primary);
                font-weight: 700;
                font-size: 13px;
            }

            .ai1wm-toggle .material-icons-round {
                font-size: 20px;
                transition: transform .2s;
            }

            .ai1wm-toggle.open .material-icons-round {
                transform: rotate(180deg);
            }

            .ai1wm-backup-count {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: rgba(71, 85, 105, 0.4);
                color: #fff;
                font-size: 11px;
                font-weight: 700;
                min-width: 24px;
                height: 24px;
                border-radius: 9999px;
                padding: 0 8px;
                border: 1px solid rgba(71, 85, 105, 0.5);
            }

            /* ---- Dropdown backup list ---- */
            .ai1wm-backups-row td {
                padding: 0 !important;
                background: rgba(15, 23, 42, 0.3);
                border-bottom: 1px solid var(--ai-glass-border) !important;
            }

            .ai1wm-backups-container {
                padding: 0 18px 18px 18px;
            }

            .ai1wm-backups-container h4 {
                margin: 0 0 12px;
                font-size: 11px;
                font-weight: 700;
                color: var(--ai-text-dim);
                text-transform: uppercase;
                letter-spacing: 0.8px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .ai1wm-backups-inner {
                background: rgba(15, 23, 42, 0.8);
                border-radius: var(--ai-radius-sm);
                border: 1px solid rgba(255, 255, 255, 0.05);
                padding: 16px;
                margin-left: 46px;
            }

            .ai1wm-backups-list {
                width: 100%;
                border-collapse: collapse;
            }

            .ai1wm-backups-list th,
            .ai1wm-backups-list td {
                padding: 10px 14px;
                text-align: left;
                font-size: 12px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }

            .ai1wm-backups-list thead {
                background: rgba(71, 85, 105, 0.15);
                color: var(--ai-text-dim);
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                font-size: 10px;
            }

            .ai1wm-backups-list tbody tr {
                transition: background .15s;
            }

            .ai1wm-backups-list tbody tr:hover {
                background: rgba(30, 41, 59, 0.5);
            }

            .file-icon {
                color: #fb923c;
                vertical-align: middle;
                margin-right: 6px;
                font-size: 18px;
            }

            .ai1wm-btn-dl {
                background: rgba(59, 130, 246, 0.1);
                color: var(--ai-secondary);
                border: none;
                font-weight: 700;
            }

            .ai1wm-btn-dl:hover:not(:disabled) {
                background: var(--ai-secondary);
                color: #fff;
            }

            .ai1wm-btn-delete {
                background: transparent;
                color: var(--ai-text-dim);
                border: none;
                padding: 6px;
            }

            .ai1wm-btn-delete:hover:not(:disabled) {
                color: var(--ai-danger);
            }

            /* ---- Spinner ---- */
            .ai1wm-spinner {
                display: inline-block;
                width: 14px;
                height: 14px;
                border: 2px solid rgba(255, 255, 255, .2);
                border-top-color: var(--ai-primary);
                border-radius: 50%;
                animation: ai1wm-spin .6s linear infinite;
            }

            .ai1wm-spinner-dark {
                border-color: rgba(255, 255, 255, .1);
                border-top-color: var(--ai-primary);
            }

            @keyframes ai1wm-spin {
                to {
                    transform: rotate(360deg);
                }
            }

            /* ---- Notifications ---- */
            .ai1wm-notice {
                position: fixed;
                top: 40px;
                right: 24px;
                z-index: 100000;
                padding: 14px 20px;
                border-radius: var(--ai-radius);
                font-size: 13px;
                font-weight: 500;
                max-width: 420px;
                animation: ai1wm-slide-in .4s ease;
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border: 1px solid var(--ai-glass-border);
                font-family: 'Manrope', sans-serif;
            }

            .ai1wm-notice-success {
                background: rgba(37, 244, 120, 0.15);
                color: var(--ai-primary);
                border-left: 4px solid var(--ai-primary);
            }

            .ai1wm-notice-error {
                background: rgba(239, 68, 68, 0.15);
                color: #fca5a5;
                border-left: 4px solid var(--ai-danger);
            }

            .ai1wm-notice-info {
                background: rgba(59, 130, 246, 0.15);
                color: #93c5fd;
                border-left: 4px solid var(--ai-secondary);
            }

            @keyframes ai1wm-slide-in {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }

                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }

            /* ---- Progress bar for bulk ---- */
            .ai1wm-bulk-progress {
                display: none;
                margin-bottom: 20px;
                padding: 18px 22px;
                position: relative;
                z-index: 1;
            }

            .ai1wm-bulk-progress h4 {
                margin: 0 0 10px;
                font-size: 13px;
                font-weight: 600;
                color: #fff;
            }

            .ai1wm-progress-bar {
                width: 100%;
                height: 6px;
                background: rgba(71, 85, 105, 0.4);
                border-radius: 3px;
                overflow: hidden;
                margin-bottom: 6px;
            }

            .ai1wm-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, var(--ai-secondary), var(--ai-primary));
                border-radius: 3px;
                transition: width .3s ease;
                width: 0%;
                box-shadow: 0 0 10px rgba(37, 244, 120, 0.4);
            }

            .ai1wm-progress-text {
                font-size: 12px;
                color: var(--ai-text-dim);
            }

            /* ---- Empty ---- */
            .ai1wm-empty {
                text-align: center;
                padding: 60px 40px;
                color: var(--ai-text-dim);
                font-size: 14px;
                position: relative;
                z-index: 1;
            }

            .ai1wm-empty .material-icons-round {
                font-size: 52px;
                margin-bottom: 16px;
                display: block;
                color: var(--ai-text-dim);
                opacity: 0.5;
            }

            /* Row expanded state */
            .ai1wm-site-row.expanded {
                background: rgba(37, 244, 120, 0.03);
                border-left: 3px solid var(--ai-primary);
            }
        </style>

        <div class="ai1wm-wrap">

            <!-- Header -->
            <div class="ai1wm-header">
                <div class="ai1wm-header-left">
                    <div class="ai1wm-header-icon">
                        <span class="material-icons-round">cloud_sync</span>
                    </div>
                    <div>
                        <h1><?php esc_html_e('AI1WM Backup Manager', 'mainwp-ai1wm-manager'); ?></h1>
                        <div class="ai1wm-version">Version <?php echo esc_html(MAINWP_AI1WM_MANAGER_VERSION); ?></div>
                    </div>
                </div>

                <?php if (!empty($sites)): ?>
                    <div class="ai1wm-toolbar">
                        <span class="ai1wm-selected-count" id="ai1wm-selected-count">
                            <span class="count-num">0</span> sélectionné(s)
                        </span>
                        <button class="ai1wm-btn ai1wm-btn-outline" id="ai1wm-refresh-all">
                            <span class="material-icons-round">refresh</span>
                            Rafraîchir tout
                        </button>
                        <button class="ai1wm-btn ai1wm-btn-secondary" id="ai1wm-bulk-download" disabled>
                            <span class="material-icons-round">download</span>
                            Télécharger dernière
                        </button>
                        <button class="ai1wm-btn ai1wm-btn-primary" id="ai1wm-bulk-backup" disabled>
                            <span class="material-icons-round">backup</span>
                            Backup sélection
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stats Cards -->
            <?php if (!empty($sites)): ?>
                <div class="ai1wm-stats">
                    <div class="ai1wm-stat-card glass-panel">
                        <div>
                            <div class="stat-label">Total Sites</div>
                            <div class="stat-value" id="ai1wm-stat-sites"><?php echo count($sites); ?></div>
                        </div>
                        <div class="ai1wm-stat-icon">
                            <span class="material-icons-round">language</span>
                        </div>
                    </div>
                    <div class="ai1wm-stat-card glass-panel">
                        <div>
                            <div class="stat-label">Total Backups</div>
                            <div class="stat-value" id="ai1wm-stat-backups">–</div>
                        </div>
                        <div class="ai1wm-stat-icon">
                            <span class="material-icons-round">inventory_2</span>
                        </div>
                    </div>
                    <div class="ai1wm-stat-card glass-panel">
                        <div>
                            <div class="stat-label">Dernier Backup</div>
                            <div class="stat-value" id="ai1wm-stat-last" style="font-size:16px;">–</div>
                        </div>
                        <div class="ai1wm-stat-icon green">
                            <span class="material-icons-round">schedule</span>
                        </div>
                    </div>
                    <div class="ai1wm-stat-card glass-panel">
                        <div>
                            <div class="stat-label">Erreurs</div>
                            <div class="stat-value" id="ai1wm-stat-errors">0</div>
                        </div>
                        <div class="ai1wm-stat-icon green">
                            <span class="material-icons-round">check_circle</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Bulk Progress -->
            <div class="ai1wm-bulk-progress glass-panel" id="ai1wm-bulk-progress">
                <h4 id="ai1wm-bulk-title">Opération en cours…</h4>
                <div class="ai1wm-progress-bar">
                    <div class="ai1wm-progress-fill" id="ai1wm-progress-fill"></div>
                </div>
                <span class="ai1wm-progress-text" id="ai1wm-progress-text">0 / 0</span>
            </div>

            <?php if (empty($sites)): ?>
                <div class="ai1wm-empty glass-panel">
                    <span class="material-icons-round">cloud_off</span>
                    <?php esc_html_e('Aucun site enfant trouvé.', 'mainwp-ai1wm-manager'); ?>
                </div>
            <?php else: ?>
                <div class="ai1wm-table-wrap glass-panel">
                    <table class="ai1wm-table" id="ai1wm-sites-table">
                        <thead>
                            <tr>
                                <th class="cb-col"><input type="checkbox" id="ai1wm-select-all" title="Tout sélectionner"></th>
                                <th><?php esc_html_e('Site Web', 'mainwp-ai1wm-manager'); ?></th>
                                <th><?php esc_html_e('URL', 'mainwp-ai1wm-manager'); ?></th>
                                <th style="text-align:center;"><?php esc_html_e('Backups', 'mainwp-ai1wm-manager'); ?></th>
                                <th style="width:180px;text-align:right;"><?php esc_html_e('Actions', 'mainwp-ai1wm-manager'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sites as $idx => $site):
                                $favicon_url = 'https://www.google.com/s2/favicons?domain=' . urlencode($site['url']) . '&sz=64';
                                ?>
                                <tr data-site-id="<?php echo esc_attr($site['id']); ?>" class="ai1wm-site-row">
                                    <td class="cb-col">
                                        <input type="checkbox" class="ai1wm-site-cb" value="<?php echo esc_attr($site['id']); ?>">
                                    </td>
                                    <td>
                                        <div class="site-info">
                                            <div class="site-avatar">
                                                <img src="<?php echo esc_url($favicon_url); ?>" alt=""
                                                    style="width:100%;height:100%;object-fit:cover;border-radius:6px;">
                                            </div>
                                            <div>
                                                <div class="site-name"><?php echo esc_html($site['name']); ?></div>
                                                <div class="site-status">Connecté</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($site['url']); ?>" class="site-url" target="_blank">
                                            <?php echo esc_html(preg_replace('#^https?://#', '', rtrim($site['url'], '/'))); ?>
                                        </a>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="ai1wm-toggle" data-site-id="<?php echo esc_attr($site['id']); ?>">
                                            <span class="ai1wm-backup-count"
                                                data-site-id="<?php echo esc_attr($site['id']); ?>">–</span>
                                            <span class="material-icons-round">expand_more</span>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="ai1wm-actions">
                                            <?php if (!empty($site['is_installed'])): ?>
                                                <button class="ai1wm-btn ai1wm-btn-icon-primary ai1wm-btn-create"
                                                    data-site-id="<?php echo esc_attr($site['id']); ?>" title="Créer un backup">
                                                    <span class="material-icons-round">backup</span>
                                                </button>
                                                <button class="ai1wm-btn ai1wm-btn-ghost ai1wm-btn-list"
                                                    data-site-id="<?php echo esc_attr($site['id']); ?>" title="Rafraîchir les backups">
                                                    <span class="material-icons-round">refresh</span>
                                                </button>
                                            <?php else: ?>
                                                <button class="ai1wm-btn ai1wm-btn-secondary ai1wm-install-child"
                                                    data-site-id="<?php echo esc_attr($site['id']); ?>" title="Installer le plugin enfant">
                                                    <span class="material-icons-round">download</span> Installer
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="ai1wm-backups-row" data-site-id="<?php echo esc_attr($site['id']); ?>" style="display:none;">
                                    <td colspan="5">
                                        <div class="ai1wm-backups-container">
                                            <div class="ai1wm-backups-inner">
                                                <h4>
                                                    <span class="material-icons-round" style="font-size:16px;">folder_open</span>
                                                    <?php printf(esc_html__('Fichiers .wpress — %s', 'mainwp-ai1wm-manager'), esc_html($site['name'])); ?>
                                                </h4>
                                                <div class="ai1wm-backups-content">
                                                    <span class="ai1wm-spinner ai1wm-spinner-dark"></span> Chargement…
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <script>
            (function ($) {
                'use strict';

                var nonce = '<?php echo esc_js(wp_create_nonce('ai1wm_manager_nonce')); ?>';
                var backupsCache = {}; // site_id -> backups array



                /* ==== Notifications ==== */
                function notify(msg, type) {
                    type = type || 'info';
                    var $el = $('<div class="ai1wm-notice ai1wm-notice-' + type + '">' + msg + '</div>');
                    $('body').append($el);
                    setTimeout(function () { $el.fadeOut(300, function () { $el.remove(); }); }, 4000);
                }

                /* ==== Button Loading ==== */
                function btnLoading($btn, on) {
                    if (on) {
                        $btn.data('orig-html', $btn.html()).prop('disabled', true)
                            .html('<span class="ai1wm-spinner"></span> ...');
                    } else {
                        $btn.prop('disabled', false).html($btn.data('orig-html'));
                    }
                }

                /* ==== Format Size ==== */
                function fmtSize(b) {
                    if (!b) return '0 B';
                    var u = ['B', 'KB', 'MB', 'GB'], i = Math.floor(Math.log(b) / Math.log(1024));
                    return (b / Math.pow(1024, i)).toFixed(1) + ' ' + u[i];
                }

                /* ==== Escape HTML ==== */
                function esc(s) { return $('<span>').text(s).html(); }

                /* ==== Render Backups Dropdown ==== */
                function renderBackups(siteId, backups) {
                    backupsCache[siteId] = backups;
                    var $row = $('.ai1wm-backups-row[data-site-id="' + siteId + '"]');
                    var $content = $row.find('.ai1wm-backups-content');
                    var $count = $('.ai1wm-backup-count[data-site-id="' + siteId + '"]');

                    $count.text(backups ? backups.length : 0);

                    if (!backups || backups.length === 0) {
                        $content.html('<p style="color:var(--ai-text-dim);font-style:italic;margin:0;">Aucune sauvegarde sur ce site.</p>');
                        return;
                    }

                    var h = '<table class="ai1wm-backups-list"><thead><tr>' +
                        '<th>Fichier</th><th>Date</th><th>Taille</th><th style="width:140px;text-align:right;">Actions</th>' +
                        '</tr></thead><tbody>';

                    $.each(backups, function (i, b) {
                        h += '<tr>' +
                            '<td><span class="material-icons-round file-icon">description</span>' + esc(b.name) + '</td>' +
                            '<td style="color:var(--ai-text-muted);">' + esc(b.date) + '</td>' +
                            '<td style="color:var(--ai-text-muted);">' + fmtSize(b.size) + '</td>' +
                            '<td style="text-align:right;">' +
                            '<button class="ai1wm-btn ai1wm-btn-dl ai1wm-btn-sm" data-site-id="' + siteId + '" data-file="' + esc(b.name) + '"><span class="material-icons-round" style="font-size:16px;">download</span> DL</button> ' +
                            '<button class="ai1wm-btn ai1wm-btn-delete ai1wm-btn-sm" data-site-id="' + siteId + '" data-file="' + esc(b.name) + '"><span class="material-icons-round" style="font-size:16px;">delete_outline</span></button>' +
                            '</td></tr>';
                    });
                    h += '</tbody></table>';
                    $content.html(h);

                    // Update stats
                    updateStats();
                }

                /* ==== Checkbox Selection ==== */
                function getSelected() {
                    var ids = [];
                    $('.ai1wm-site-cb:checked').each(function () { ids.push($(this).val()); });
                    return ids;
                }
                function updateSelectionUI() {
                    var n = getSelected().length;
                    $('#ai1wm-selected-count .count-num').text(n);
                    $('#ai1wm-bulk-backup, #ai1wm-bulk-download').prop('disabled', n === 0);
                }

                /* ==== Update Stats Cards ==== */
                function updateStats() {
                    var totalBackups = 0;
                    var latestDate = '';
                    for (var sid in backupsCache) {
                        var arr = backupsCache[sid];
                        if (arr && arr.length) {
                            totalBackups += arr.length;
                            if (arr[0].date && arr[0].date > latestDate) latestDate = arr[0].date;
                        }
                    }
                    $('#ai1wm-stat-backups').text(totalBackups || '–');
                    $('#ai1wm-stat-last').text(latestDate || '–');
                }
                $(document).on('change', '.ai1wm-site-cb', updateSelectionUI);
                $(document).on('change', '#ai1wm-select-all', function () {
                    $('.ai1wm-site-cb').prop('checked', $(this).is(':checked'));
                    updateSelectionUI();
                });

                /* ==== Toggle Dropdown ==== */
                $(document).on('click', '.ai1wm-toggle', function () {
                    var $t = $(this);
                    var siteId = $t.data('site-id');
                    var $row = $('.ai1wm-backups-row[data-site-id="' + siteId + '"]');

                    if ($t.hasClass('open')) {
                        $t.removeClass('open');
                        $row.slideUp(150);
                    } else {
                        $t.addClass('open');
                        $row.slideDown(150);
                        // Auto-load if not cached
                        if (!backupsCache[siteId]) {
                            loadBackups(siteId);
                        }
                    }
                });

                /* ==== AJAX: List Backups ==== */
                function loadBackups(siteId, callback) {
                    var $content = $('.ai1wm-backups-row[data-site-id="' + siteId + '"] .ai1wm-backups-content');
                    $content.html('<span class="ai1wm-spinner ai1wm-spinner-dark"></span> Chargement…');

                    $.post(ajaxurl, {
                        action: 'ai1wm_list_backups',
                        site_id: siteId,
                        _nonce: nonce
                    }, function (res) {
                        if (res.success) {
                            renderBackups(siteId, res.data);
                        } else {
                            if (res.data === 'PLUGIN_MISSING') {
                                // Close accordion
                                var $toggle = $('.ai1wm-toggle[data-site-id="' + siteId + '"]');
                                $toggle.removeClass('open');
                                $('.ai1wm-backups-row[data-site-id="' + siteId + '"]').slideUp(150);

                                // Show install button
                                var $actions = $('.ai1wm-btn-create[data-site-id="' + siteId + '"]').closest('.ai1wm-actions');
                                $actions.html(
                                    '<button class="ai1wm-btn ai1wm-btn-secondary ai1wm-install-child" ' +
                                    'data-site-id="' + siteId + '" title="Installer le plugin enfant">' +
                                    '<span class="material-icons-round">download</span> Installer' +
                                    '</button>'
                                );
                                notify('Plugin enfant non détecté. Bouton d\'installation affiché.', 'warning');
                            } else {
                                $content.html('<p style="color:var(--ai-danger);">Erreur : ' + esc(res.data || 'Inconnue') + '</p>');
                            }
                        }
                        if (callback) callback(res.success);
                    }).fail(function () {
                        $content.html('<p style="color:var(--ai-danger);">Erreur réseau.</p>');
                        if (callback) callback(false);
                    });
                }

                $(document).on('click', '.ai1wm-btn-list', function (e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var siteId = $btn.data('site-id');
                    btnLoading($btn, true);
                    loadBackups(siteId, function () {
                        btnLoading($btn, false);
                        // Open the dropdown
                        var $toggle = $('.ai1wm-toggle[data-site-id="' + siteId + '"]');
                        if (!$toggle.hasClass('open')) $toggle.trigger('click');
                    });
                });

                /* ==== AJAX: Create Backup ==== */
                $(document).on('click', '.ai1wm-btn-create', function (e) {
                    e.preventDefault();
                    var $btn = $(this), siteId = $btn.data('site-id');
                    if (!confirm('Lancer une nouvelle sauvegarde sur ce site ?')) return;
                    btnLoading($btn, true);

                    $.post(ajaxurl, {
                        action: 'ai1wm_create_backup',
                        site_id: siteId,
                        _nonce: nonce
                    }, function (res) {
                        btnLoading($btn, false);
                        if (res.success) {
                            notify('✅ Sauvegarde lancée !', 'success');
                            delete backupsCache[siteId];
                            loadBackups(siteId);
                        } else {
                            notify('❌ ' + (res.data || 'Erreur'), 'error');
                        }
                    }).fail(function () {
                        btnLoading($btn, false);
                        notify('❌ Erreur réseau.', 'error');
                    });
                });

                /* ==== AJAX: Delete Backup ==== */
                $(document).on('click', '.ai1wm-btn-delete', function (e) {
                    e.preventDefault();
                    var $btn = $(this), siteId = $btn.data('site-id'), file = $btn.data('file');
                    if (!confirm('Supprimer « ' + file + ' » ?')) return;
                    btnLoading($btn, true);

                    $.post(ajaxurl, {
                        action: 'ai1wm_delete_backup',
                        site_id: siteId,
                        file_name: file,
                        _nonce: nonce
                    }, function (res) {
                        btnLoading($btn, false);
                        if (res.success) {
                            notify('✅ Fichier supprimé !', 'success');
                            delete backupsCache[siteId];
                            loadBackups(siteId);
                        } else {
                            notify('❌ ' + (res.data || 'Erreur'), 'error');
                        }
                    }).fail(function () {
                        btnLoading($btn, false);
                        notify('❌ Erreur réseau.', 'error');
                    });
                });

                /* ==== AJAX: Download Backup ==== */
                $(document).on('click', '.ai1wm-btn-dl', function (e) {
                    e.preventDefault();
                    var $btn = $(this), siteId = $btn.data('site-id'), file = $btn.data('file');
                    btnLoading($btn, true);

                    $.post(ajaxurl, {
                        action: 'ai1wm_download_backup',
                        site_id: siteId,
                        file_name: file,
                        _nonce: nonce
                    }, function (res) {
                        btnLoading($btn, false);
                        if (res.success && res.data && res.data.url) {
                            // Open download in new tab
                            var a = document.createElement('a');
                            a.href = res.data.url;
                            a.download = file;
                            a.target = '_blank';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            notify('✅ Téléchargement lancé !', 'success');
                        } else {
                            notify('❌ ' + (res.data || 'Téléchargement impossible'), 'error');
                        }
                    }).fail(function () {
                        btnLoading($btn, false);
                        notify('❌ Erreur réseau.', 'error');
                    });
                });

                /* ==== Bulk: Backup Selected ==== */
                $('#ai1wm-bulk-backup').on('click', function () {
                    var ids = getSelected();
                    if (!ids.length) return;
                    if (!confirm('Lancer une sauvegarde sur ' + ids.length + ' site(s) ?')) return;

                    var $prog = $('#ai1wm-bulk-progress').show();
                    $('#ai1wm-bulk-title').text('Création de backups…');
                    var done = 0, total = ids.length, ok = 0;

                    function updateProgress() {
                        done++;
                        var pct = Math.round((done / total) * 100);
                        $('#ai1wm-progress-fill').css('width', pct + '%');
                        $('#ai1wm-progress-text').text(done + ' / ' + total);
                        if (done >= total) {
                            setTimeout(function () { $prog.slideUp(200); }, 2000);
                            notify('✅ Backup terminé : ' + ok + '/' + total + ' réussi(s).', ok === total ? 'success' : 'error');
                        }
                    }

                    $.each(ids, function (i, siteId) {
                        $.post(ajaxurl, {
                            action: 'ai1wm_create_backup',
                            site_id: siteId,
                            _nonce: nonce
                        }, function (res) {
                            if (res.success) ok++;
                            delete backupsCache[siteId];
                            updateProgress();
                        }).fail(function () { updateProgress(); });
                    });
                });

                /* ==== Bulk: Download Latest from Selected ==== */
                $('#ai1wm-bulk-download').on('click', function () {
                    var ids = getSelected();
                    if (!ids.length) return;

                    var $prog = $('#ai1wm-bulk-progress').show();
                    $('#ai1wm-bulk-title').text('Téléchargement des dernières backups…');
                    var done = 0, total = ids.length, ok = 0;

                    function updateProgress() {
                        done++;
                        var pct = Math.round((done / total) * 100);
                        $('#ai1wm-progress-fill').css('width', pct + '%');
                        $('#ai1wm-progress-text').text(done + ' / ' + total);
                        if (done >= total) {
                            setTimeout(function () { $prog.slideUp(200); }, 2000);
                            notify('✅ Téléchargement : ' + ok + '/' + total + ' lancé(s).', ok === total ? 'success' : 'info');
                        }
                    }

                    function doDownload(siteId) {
                        // First get the list to find the latest backup
                        $.post(ajaxurl, {
                            action: 'ai1wm_list_backups',
                            site_id: siteId,
                            _nonce: nonce
                        }, function (res) {
                            if (res.success && res.data && res.data.length > 0) {
                                var latest = res.data[0]; // Already sorted desc
                                renderBackups(siteId, res.data);
                                // Now download it
                                $.post(ajaxurl, {
                                    action: 'ai1wm_download_backup',
                                    site_id: siteId,
                                    file_name: latest.name,
                                    _nonce: nonce
                                }, function (dlRes) {
                                    if (dlRes.success && dlRes.data && dlRes.data.url) {
                                        var a = document.createElement('a');
                                        a.href = dlRes.data.url;
                                        a.download = latest.name;
                                        a.target = '_blank';
                                        document.body.appendChild(a);
                                        a.click();
                                        document.body.removeChild(a);
                                        ok++;
                                    }
                                    updateProgress();
                                }).fail(function () { updateProgress(); });
                            } else {
                                updateProgress();
                            }
                        }).fail(function () { updateProgress(); });
                    }

                    $.each(ids, function (i, siteId) {
                        // Stagger downloads by 500ms to avoid overwhelming the browser
                        setTimeout(function () { doDownload(siteId); }, i * 500);
                    });
                });

                /* ==== Refresh All ==== */
                $('#ai1wm-refresh-all').on('click', function () {
                    var $btn = $(this);
                    btnLoading($btn, true);
                    backupsCache = {};
                    var total = $('.ai1wm-site-row').length, done = 0;

                    $('.ai1wm-site-row').each(function () {
                        var siteId = $(this).data('site-id');
                        loadBackups(siteId, function () {
                            done++;
                            if (done >= total) {
                                btnLoading($btn, false);
                                notify('✅ Toutes les listes rafraîchies.', 'success');
                            }
                        });
                    });
                });

            })(jQuery);
        </script>
        <?php
    }

    /* ---------------------------------------------------------------
     *  HELPER: Get child sites
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

    /* ---------------------------------------------------------------
     *  HELPER: Send to child site
     * ------------------------------------------------------------- */

    private function send_to_child($site_id, $action, $params = array())
    {
        $post_data = array_merge(
            array('action' => $action),
            $params
        );

        try {
            $result = null;

            // Method 1: Use MainWP_Connect directly (most reliable for non-licensed extensions).
            if (class_exists('\MainWP\Dashboard\MainWP_DB') && class_exists('\MainWP\Dashboard\MainWP_Connect')) {
                $website = \MainWP\Dashboard\MainWP_DB::instance()->get_website_by_id($site_id);
                if ($website) {
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
                $website = MainWP_DB::instance()->get_website_by_id($site_id);
                if ($website) {
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
                $result = apply_filters(
                    'mainwp_fetchurlauthed',
                    MAINWP_AI1WM_MANAGER_FILE,
                    '',
                    $site_id,
                    'extra_execution',
                    $post_data
                );
            }
        } catch (\Exception $e) {
            return array('error' => 'MainWP request failed: ' . $e->getMessage());
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AI1WM Manager] send_to_child(' . $action . ') result: ' . gettype($result) . ' = ' . wp_json_encode($result));
        }

        return $result;
    }

    /* ---------------------------------------------------------------
     *  HELPER: Handle child response
     * ------------------------------------------------------------- */

    private function handle_child_response($result, $success_msg = 'OK', $success_default = null)
    {
        if (is_string($result) && !empty($result)) {
            $decoded = json_decode($result, true);
            if (is_array($decoded)) {
                $result = $decoded;
            }
        }

        if (is_array($result)) {
            if (isset($result['error']) && !empty($result['error'])) {
                wp_send_json_error($result['error']);
                return;
            }
            if (isset($result['success']) && $result['success']) {
                wp_send_json_success(isset($result['data']) ? $result['data'] : $success_default);
                return;
            }
            if (isset($result['result']) && 'ok' === $result['result']) {
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
                wp_send_json_error($arr['error']);
                return;
            }
            if (!empty($arr)) {
                wp_send_json_success($arr);
                return;
            }
        }

        $debug = ' (type: ' . gettype($result) . ', val: ' . wp_json_encode($result) . ')';
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
        $result = $this->send_to_child($site_id, 'ai1wm_create_backup');
        $this->handle_child_response($result, 'Sauvegarde créée.', 'Sauvegarde créée.');
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
        $result = $this->send_to_child($site_id, 'ai1wm_list_backups');
        $this->handle_child_response($result, 'OK', array());
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
        $result = $this->send_to_child($site_id, 'ai1wm_delete_backup', array('file_name' => $file_name));
        $this->handle_child_response($result, 'Fichier supprimé.', 'Fichier supprimé.');
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
        $result = $this->send_to_child($site_id, 'ai1wm_download_backup', array('file_name' => $file_name));
        $this->handle_child_response($result, 'URL récupérée.', null);
    }

    public function ajax_install_child()
    {
        check_ajax_referer('ai1wm_manager_nonce', '_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes.');
        }

        $site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : 0;
        if (!$site_id) {
            wp_send_json_error('ID de site invalide.');
        }

        // 1. Get latest release asset URL from GitHub
        $github_repo = 'kinou-p/mainwp-ai1wm-manager';
        $asset_name = 'mainwp-ai1wm-manager-child.zip';
        $download_url = '';

        $response = wp_remote_get("https://api.github.com/repos/{$github_repo}/releases/latest");
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            wp_send_json_error('Impossible de récupérer les infos de release GitHub.');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if ($asset['name'] === $asset_name) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        if (empty($download_url)) {
            wp_send_json_error('Actif introuvable dans la dernière release.');
        }

        // 2. Install on child site via MainWP
        // remove 'action' as it is defined by the function name in fetch_url_authed
        $post_data = array(
            'type' => 'plugin',
            'url' => $download_url,
            'name' => 'mainwp-ai1wm-manager-child/mainwp-ai1wm-manager-child.php', // Helpful for activation
            'activate' => 'yes',
            'overwrite' => 'yes'
        );

        try {
            $result = null;

            // Use MainWP_Connect to perform standard actions
            if (class_exists('\MainWP\Dashboard\MainWP_DB') && class_exists('\MainWP\Dashboard\MainWP_Connect')) {
                $website = \MainWP\Dashboard\MainWP_DB::instance()->get_website_by_id($site_id);
                if ($website) {
                    $result = \MainWP\Dashboard\MainWP_Connect::fetch_url_authed(
                        $website,
                        'install_plugintheme',
                        $post_data
                    );
                } else {
                    wp_send_json_error('Site non trouvé.');
                }
            } else {
                wp_send_json_error('MainWP Dashboard classes non trouvées.');
            }

            // Handle response - MainWP usually returns {status: SUCCESS} or similar for installs
            if (is_array($result) && isset($result['status']) && $result['status'] === 'SUCCESS') {
                wp_send_json_success('Plugin installé et activé.');
            } elseif (is_string($result) && strtoupper($result) === 'SUCCESS') {
                wp_send_json_success('Plugin installé.');
            } elseif (isset($result['error'])) {
                wp_send_json_error('Erreur installation: ' . $result['error']);
            } else {
                // Optimistic success if no error
                wp_send_json_success('Commande envoyée. Vérifiez le site enfant.');
            }

        } catch (\Exception $e) {
            wp_send_json_error('Exception MainWP: ' . $e->getMessage());
        }
    }
}
