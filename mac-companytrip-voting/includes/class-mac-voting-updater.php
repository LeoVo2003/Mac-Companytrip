<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Voting_Updater {
    const CACHE_KEY = 'mac_voting_github_release';
    const CACHE_TTL = 21600;

    public static function init(): void {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 10, 3);
        add_filter('auto_update_plugin', array(__CLASS__, 'enable_auto_update'), 10, 2);
        add_filter('http_request_args', array(__CLASS__, 'auth_download'), 10, 2);
        add_filter('plugin_action_links_' . plugin_basename(MAC_VOTING_FILE), array(__CLASS__, 'action_links'));
        add_action('admin_init', array(__CLASS__, 'maybe_force_check'));
        add_action('admin_post_mac_voting_save_repo', array(__CLASS__, 'save_repo'));
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_cache'), 10, 2);
    }

    public static function repo(): string {
        $from_option = self::normalize_repo((string) get_option('mac_voting_github_repo', ''));
        if ($from_option !== '') {
            return $from_option;
        }
        $from_constant = defined('MAC_VOTING_GITHUB_REPO') ? self::normalize_repo((string) MAC_VOTING_GITHUB_REPO) : '';
        return $from_constant;
    }

    public static function normalize_repo(string $repo): string {
        $repo = trim($repo);
        $repo = preg_replace('#^https?://github\.com/#i', '', $repo);
        $repo = preg_replace('#\.git$#i', '', $repo);
        $repo = trim($repo, '/');
        if ($repo === '' || strpos($repo, 'YOUR_GITHUB') !== false) {
            return '';
        }
        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
            return '';
        }
        return $repo;
    }

    public static function inject_update($transient) {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $plugin = plugin_basename(MAC_VOTING_FILE);
        $remote = self::latest_release();
        if (!$remote) {
            return $transient;
        }

        $item = (object) array(
            'slug'        => dirname($plugin),
            'plugin'      => $plugin,
            'new_version' => $remote['version'],
            'url'         => $remote['url'],
            'package'     => $remote['package'],
            'tested'      => $remote['tested'],
            'requires'    => '6.0',
            'requires_php'=> '7.4',
        );

        if (version_compare($remote['version'], MAC_VOTING_VERSION, '>')) {
            $transient->response[$plugin] = $item;
            if (isset($transient->no_update[$plugin])) {
                unset($transient->no_update[$plugin]);
            }
        } else {
            $transient->no_update[$plugin] = $item;
        }

        return $transient;
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args) || empty($args->slug)) {
            return $result;
        }

        $slug = dirname(plugin_basename(MAC_VOTING_FILE));
        if ($args->slug !== $slug) {
            return $result;
        }

        $remote = self::latest_release();
        if (!$remote) {
            return $result;
        }

        return (object) array(
            'name'          => 'MAC Company Trip Voting',
            'slug'          => $slug,
            'version'       => $remote['version'],
            'author'        => '<a href="https://macmarketing.vn/">MAC Marketing</a>',
            'homepage'      => $remote['url'],
            'requires'      => '6.0',
            'requires_php'  => '7.4',
            'tested'        => $remote['tested'],
            'download_link' => $remote['package'],
            'sections'      => array(
                'description' => 'Hệ thống chấm điểm văn nghệ Company Trip. Bản mới được lấy tự động từ GitHub Releases.',
                'changelog'   => $remote['changelog'],
            ),
        );
    }

    public static function enable_auto_update($update, $item) {
        if (is_object($item) && isset($item->plugin) && $item->plugin === plugin_basename(MAC_VOTING_FILE)) {
            return true;
        }
        return $update;
    }

    public static function auth_download($args, $url) {
        if (!is_array($args) || !is_string($url) || !defined('MAC_VOTING_GITHUB_TOKEN') || MAC_VOTING_GITHUB_TOKEN === '') {
            return $args;
        }

        $remote = get_transient(self::CACHE_KEY);
        $package = is_array($remote) && !empty($remote['package']) ? (string) $remote['package'] : '';
        if ($package === '' || strpos($url, $package) !== 0) {
            return $args;
        }

        if (!isset($args['headers']) || !is_array($args['headers'])) {
            $args['headers'] = array();
        }
        $args['headers']['Authorization'] = 'Bearer ' . MAC_VOTING_GITHUB_TOKEN;
        $args['headers']['Accept'] = 'application/octet-stream';
        return $args;
    }

    public static function action_links(array $links): array {
        $url = wp_nonce_url(
            admin_url('plugins.php?mac_voting_check_update=1'),
            'mac_voting_check_update'
        );
        $links[] = '<a href="' . esc_url($url) . '">Kiểm tra cập nhật</a>';
        return $links;
    }

    public static function maybe_force_check(): void {
        if (!isset($_GET['mac_voting_check_update']) || !current_user_can('update_plugins')) {
            return;
        }
        check_admin_referer('mac_voting_check_update');
        self::clear_cache();
        delete_site_transient('update_plugins');
        wp_update_plugins();
        wp_safe_redirect(add_query_arg('mac_voting_checked', '1', admin_url('plugins.php')));
        exit;
    }

    public static function save_repo(): void {
        if (!current_user_can('update_plugins')) {
            wp_die('Không có quyền.');
        }
        check_admin_referer('mac_voting_save_repo');
        $repo = isset($_POST['mac_voting_github_repo']) ? sanitize_text_field(wp_unslash($_POST['mac_voting_github_repo'])) : '';
        $normalized = self::normalize_repo($repo);
        if ($normalized === '') {
            wp_safe_redirect(add_query_arg('mac_voting_repo_error', '1', admin_url('plugins.php')));
            exit;
        }
        update_option('mac_voting_github_repo', $normalized, false);
        self::clear_cache();
        delete_site_transient('update_plugins');
        wp_safe_redirect(add_query_arg('mac_voting_repo_saved', '1', admin_url('plugins.php')));
        exit;
    }

    public static function admin_notice(): void {
        if (!current_user_can('update_plugins')) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'plugins') {
            return;
        }
        if (isset($_GET['mac_voting_repo_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Đã lưu GitHub repo. WordPress sẽ tự cập nhật plugin từ Releases.</p></div>';
        }
        if (isset($_GET['mac_voting_repo_error'])) {
            echo '<div class="notice notice-error"><p>Repo không hợp lệ. Dùng dạng <code>username/ten-repo</code> hoặc URL GitHub.</p></div>';
        }
        if (isset($_GET['mac_voting_checked'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Đã kiểm tra cập nhật MAC Company Trip Voting từ GitHub.</p></div>';
        }
        if (self::repo() !== '') {
            return;
        }
        $action = esc_url(admin_url('admin-post.php'));
        echo '<div class="notice notice-warning"><p><strong>MAC Company Trip Voting:</strong> Nhập GitHub repo public để WordPress tự cập nhật, không cần upload zip lại.</p>';
        echo '<form method="post" action="' . $action . '" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 4px;">';
        echo '<input type="hidden" name="action" value="mac_voting_save_repo" />';
        wp_nonce_field('mac_voting_save_repo');
        echo '<input type="text" class="regular-text" name="mac_voting_github_repo" placeholder="username/mac-companytrip-voting" />';
        echo '<button type="submit" class="button button-primary">Lưu repo</button>';
        echo '</form></div>';
    }

    public static function clear_cache($upgrader = null, $options = null): void {
        delete_transient(self::CACHE_KEY);
    }

    private static function latest_release() {
        $repo = self::repo();
        if ($repo === '') {
            return false;
        }

        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached) && !empty($cached['version']) && !empty($cached['package'])) {
            return $cached;
        }

        $headers = array(
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'MAC-CompanyTrip-Voting',
        );
        if (defined('MAC_VOTING_GITHUB_TOKEN') && MAC_VOTING_GITHUB_TOKEN !== '') {
            $headers['Authorization'] = 'Bearer ' . MAC_VOTING_GITHUB_TOKEN;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . $repo . '/releases/latest',
            array(
                'timeout' => 12,
                'headers' => $headers,
            )
        );
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['tag_name']) || empty($body['assets']) || !is_array($body['assets'])) {
            return false;
        }

        $version = ltrim((string) $body['tag_name'], 'vV');
        $package = '';
        foreach ($body['assets'] as $asset) {
            $name = isset($asset['name']) ? (string) $asset['name'] : '';
            if (preg_match('/^mac-companytrip-voting-v[\d.]+\.zip$/', $name) && !empty($asset['browser_download_url'])) {
                $package = (string) $asset['browser_download_url'];
                break;
            }
        }
        if ($version === '' || $package === '') {
            return false;
        }

        $changelog = '';
        if (!empty($body['body'])) {
            $changelog = nl2br(esc_html((string) $body['body']));
        }

        $data = array(
            'version'   => $version,
            'package'   => $package,
            'url'       => !empty($body['html_url']) ? (string) $body['html_url'] : 'https://github.com/' . $repo,
            'tested'    => '6.6',
            'changelog' => $changelog,
        );
        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);
        return $data;
    }
}
