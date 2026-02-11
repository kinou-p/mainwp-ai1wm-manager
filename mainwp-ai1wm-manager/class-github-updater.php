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
                $args = array();
                $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->username, $this->repository);

                if ($this->authorize_token) {
                    $args['headers']['Authorization'] = "token {$this->authorize_token}";
                }

                $response = wp_remote_get($request_uri, $args);

                if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
                    return false;
                }

                $this->github_response = json_decode(wp_remote_retrieve_body($response), true);
            }

            return $this->github_response;
        }

        public function modify_transient($transient)
        {
            if (property_exists($transient, 'checked')) {
                if ($checked = $transient->checked) {
                    $this->plugin = get_plugin_data($this->file); // Update plugin data
                    $out_of_date = version_compare($this->github_response['tag_name'] ?? 0, $checked[$this->basename], 'gt');

                    if ($out_of_date) {
                        $new_files = $this->github_response['zipball_url']; // Default fall back
                        $slug = current(explode('/', $this->basename));

                        $plugin = array(
                            'url' => $this->plugin['PluginURI'],
                            'slug' => $slug,
                            'package' => $new_files,
                            'new_version' => $this->github_response['tag_name']
                        );

                        // Find specific asset
                        if (!empty($this->github_response['assets'])) {
                            foreach ($this->github_response['assets'] as $asset) {
                                if ($asset['name'] === $this->asset_name) {
                                    $plugin['package'] = $asset['browser_download_url'];
                                    break;
                                }
                            }
                        }

                        $transient->response[$this->basename] = (object) $plugin;
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

                    return (object) $plugin;
                }
            }
            return $result;
        }

        public function after_install($response, $hook_extra, $result)
        {
            // Only run for this plugin
            global $wp_filesystem;
            $install_directory = plugin_dir_path($this->file);
            $wp_filesystem->move($result['destination'], $install_directory);
            $result['destination'] = $install_directory;

            if ($this->active) {
                activate_plugin($this->basename);
            }

            return $result;
        }
    }
}
