<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('MainWP_AI1WM_Github_Updater')) {

    class MainWP_AI1WM_Github_Updater
    {
        private $file;
        private $plugin;
        private $basename;
        private $active;
        private $username;
        private $repository;
        private $authorize_token;
        private $github_response;
        private $asset_name;

        public function __construct($file, $repository, $asset_name, $access_token = '')
        {
            $this->file = $file;
            $this->username = explode('/', $repository)[0];
            $this->repository = explode('/', $repository)[1];
            $this->asset_name = $asset_name;
            $this->authorize_token = $access_token;

            $this->plugin = get_plugin_data($this->file);
            $this->basename = plugin_basename($this->file);
            $this->active = is_plugin_active($this->basename);

            add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
            add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
            add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        }

        private function get_repository_info()
        {
            if (is_null($this->github_response)) {
                // Check cache first (1 hour expiration)
                $cache_key = 'github_updater_' . md5($this->username . $this->repository);
                $cached = get_transient($cache_key);

                if (false !== $cached && is_array($cached)) {
                    $this->github_response = $cached;
                    return $this->github_response;
                }

                $args = array(
                    'timeout' => 10,
                    'sslverify' => true,
                );
                $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->username, $this->repository);

                if ($this->authorize_token) {
                    $args['headers']['Authorization'] = "token {$this->authorize_token}";
                }

                $response = wp_remote_get($request_uri, $args);

                if (is_wp_error($response)) {
                    error_log('GitHub Updater Error: ' . $response->get_error_message());
                    return false;
                }

                $response_code = wp_remote_retrieve_response_code($response);
                if (200 !== $response_code) {
                    error_log('GitHub Updater HTTP Error: ' . $response_code);
                    return false;
                }

                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('GitHub Updater JSON Error: ' . json_last_error_msg());
                    return false;
                }

                $this->github_response = $data;

                // Cache for 1 hour
                set_transient($cache_key, $data, HOUR_IN_SECONDS);
            }

            return $this->github_response;
        }

        public function modify_transient($transient)
        {
            if (property_exists($transient, 'checked')) {
                if ($checked = $transient->checked) {
                    // CRITICAL FIX: Actually fetch repository info
                    $github_data = $this->get_repository_info();

                    if (!$github_data || empty($github_data['tag_name'])) {
                        return $transient; // No update available or GitHub error
                    }

                    $this->plugin = get_plugin_data($this->file); // Update plugin data

                    // Normalize version numbers (remove 'v' prefix if present)
                    $github_version = ltrim($github_data['tag_name'], 'v');
                    $current_version = isset($checked[$this->basename]) ? $checked[$this->basename] : '0';

                    $out_of_date = version_compare($github_version, $current_version, 'gt');

                    if ($out_of_date) {
                        $new_files = $github_data['zipball_url']; // Default fall back
                        $slug = current(explode('/', $this->basename));

                        $plugin = array(
                            'url' => $this->plugin['PluginURI'],
                            'slug' => $slug,
                            'package' => $new_files,
                            'new_version' => $github_version // Use normalized version
                        );

                        // Find specific asset
                        if (!empty($github_data['assets'])) {
                            foreach ($github_data['assets'] as $asset) {
                                if ($asset['name'] === $this->asset_name) {
                                    $plugin['package'] = $asset['browser_download_url'];
                                    break;
                                }
                            }
                        }

                        $transient->response[$this->basename] = (object)$plugin;
                    }
                }
            }
            return $transient;
        }

        public function plugin_popup($result, $action, $args)
        {
            if (!empty($args->slug)) {
                if ($args->slug == current(explode('/', $this->basename))) {
                    $this->get_repository_info();
                    $plugin = array(
                        'name' => $this->plugin['Name'],
                        'slug' => $this->basename,
                        'version' => $this->github_response['tag_name'],
                        'author' => $this->plugin['AuthorName'],
                        'author_profile' => $this->plugin['AuthorURI'],
                        'last_updated' => $this->github_response['published_at'],
                        'homepage' => $this->plugin['PluginURI'],
                        'short_description' => $this->plugin['Description'],
                        'sections' => array(
                            'Description' => $this->plugin['Description'],
                            'Updates' => $this->github_response['body'],
                        ),
                        'download_link' => $this->github_response['zipball_url']
                    );

                    // Find specific asset again for popup download link
                    if (!empty($this->github_response['assets'])) {
                        foreach ($this->github_response['assets'] as $asset) {
                            if ($asset['name'] === $this->asset_name) {
                                $plugin['download_link'] = $asset['browser_download_url'];
                                break;
                            }
                        }
                    }

                    return (object)$plugin;
                }
            }
            return $result;
        }

        public function after_install($response, $hook_extra, $result)
        {
            // Important: Only run for this specific plugin
            if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
                return $result;
            }

            // Only run for this plugin
            global $wp_filesystem;

            $install_directory = plugin_dir_path($this->file);

            // Remove old plugin directory first
            if ($wp_filesystem->exists($install_directory)) {
                $wp_filesystem->delete($install_directory, true);
            }

            // Move new version to correct location
            if ($wp_filesystem->move($result['destination'], $install_directory)) {
                $result['destination'] = $install_directory;

                // Reactivate if it was active
                if ($this->active) {
                    activate_plugin($this->basename);
                }
            }
            else {
                // Log error if move failed
                error_log('GitHub Updater: Failed to move plugin to ' . $install_directory);
            }

            return $result;
        }
    }
}