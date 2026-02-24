<?php

/**
 * File: /helper/e2pdf-helper.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Helper {

    protected static $instance;
    private $helper;

    const CHMOD_DIR = 0755;
    const CHMOD_FILE = 0644;

    public static function instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->set('upload_dir', $this->get_wp_upload_dir('basedir') . '/e2pdf/');
        $this->set('tmp_dir', $this->get('upload_dir') . 'tmp/');
        $this->set('cache_dir', $this->get('upload_dir') . 'tmp/cache/');
        $this->set('pdf_dir', $this->get('upload_dir') . 'pdf/');
        $this->set('fonts_dir', $this->get('upload_dir') . 'fonts/');
        $this->set('tpl_dir', $this->get('upload_dir') . 'tpl/');
        $this->set('viewer_dir', $this->get('upload_dir') . 'viewer/');
        $this->set('bulk_dir', $this->get('upload_dir') . 'bulks/');
        $this->set('wpcf7_dir', $this->get('upload_dir') . 'wpcf7/');
        if (defined('E2PDF_ROOT_FILE')) {
            $info = get_file_data(E2PDF_ROOT_FILE, ['version' => 'Version'], false);
            $this->set('version', $info['version']);
            $this->set('plugin_dir', plugin_dir_path(E2PDF_ROOT_FILE));
            $this->set('plugin_file_path', E2PDF_ROOT_FILE);
            $this->set('plugin', plugin_basename(E2PDF_ROOT_FILE));
            $this->set('slug', dirname(plugin_basename(E2PDF_ROOT_FILE)));
        }
        $this->set('cache', get_option('e2pdf_cache', '1'));
        $parse_args = wp_parse_args(home_url(add_query_arg(null, null)));
        $this->set('page', reset($parse_args));
        if (get_option('e2pdf_memory_time', '0')) {
            $this->set('memory_debug', memory_get_usage());
            $this->set('time_debug', microtime(true));
        }
    }

    // set
    public function set($key, $value) {
        if (!$this->helper) {
            $this->helper = new stdClass();
        }
        $this->helper->$key = $value;
    }

    // add
    public function add($key, $value) {
        if (!$this->helper) {
            $this->helper = new stdClass();
        }

        if (isset($this->helper->$key)) {
            if (is_array($this->helper->$key)) {
                array_push($this->helper->$key, $value);
            }
        } else {
            $this->helper->$key = [];
            array_push($this->helper->$key, $value);
        }
    }

    // deset
    public function deset($key) {
        if (isset($this->helper->$key)) {
            unset($this->helper->$key);
        }
    }

    // get
    public function get($key) {
        if (isset($this->helper->$key)) {
            return $this->helper->$key;
        } else {
            return '';
        }
    }

    // get url path
    public function get_url_path($url) {
        return plugins_url($url, $this->get('plugin_file_path'));
    }

    // get url
    public function get_url($data = [], $prefix = 'admin.php?') {
        $url = $prefix . http_build_query($data);
        return admin_url($url);
    }

    // delete dir
    public function delete_dir($dir) {
        if (!is_dir($dir)) {
            return;
        }
        $dir = trailingslashit($dir);
        $files = glob($dir . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->delete_dir($file);
            } else {
                unlink($file);
            }
        }
        if (file_exists($dir . '.htaccess')) {
            unlink($dir . '.htaccess');
        }
        rmdir($dir);
    }

    // create dir
    public function create_dir($dir = false, $recursive = false, $create_index = true, $create_htaccess = false) {
        if ($dir && !file_exists($dir)) {
            if (mkdir($dir, self::CHMOD_DIR, $recursive)) {
                if ($create_index) {
                    $index = $dir . 'index.php';
                    if (!file_exists($index)) {
                        $this->create_file($index, "<?php\n// Silence is golden.\n?>");
                    }
                }
                if ($create_htaccess) {
                    $htaccess = $dir . '.htaccess';
                    if (!file_exists($htaccess)) {
                        $this->create_file($htaccess, 'DENY FROM ALL');
                    }
                }
            }
        }
        return is_dir($dir);
    }

    // create file
    public function create_file($file = false, $content = '') {
        $dir = dirname($file);
        if (is_dir($dir) && is_writable($dir)) {
            if ($file && !file_exists($file)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
                if (file_put_contents($file, $content, LOCK_EX)) {
                    chmod($file, self::CHMOD_FILE);
                }
            }
        }
        return is_file($file);
    }

    // get wp upload dir
    public function get_wp_upload_dir($key = 'basedir') {

        $wp_upload_dir = wp_upload_dir();
        if (defined('E2PDF_UPLOADS')) {
            $siteurl = get_option('siteurl');
            $upload_path = trim(get_option('upload_path'));

            if (empty($upload_path) || 'wp-content/uploads' === $upload_path) {
                $dir = WP_CONTENT_DIR . '/uploads';
            } elseif (0 !== strpos($upload_path, ABSPATH)) {
                // $dir is absolute, $upload_path is (maybe) relative to ABSPATH.
                $dir = path_join(ABSPATH, $upload_path);
            } else {
                $dir = $upload_path;
            }

            $url = get_option('upload_url_path');
            if (!$url) {
                if (empty($upload_path) || ('wp-content/uploads' === $upload_path) || ($upload_path == $dir)) {
                    $url = WP_CONTENT_URL . '/uploads';
                } else {
                    $url = trailingslashit($siteurl) . $upload_path;
                }
            }

            if (!(is_multisite() && get_site_option('ms_files_rewriting'))) {
                $dir = ABSPATH . E2PDF_UPLOADS;
                $url = trailingslashit($siteurl) . E2PDF_UPLOADS;
            }

            if (is_multisite() && !(is_main_network() && is_main_site() && defined('MULTISITE'))) {
                if (!get_site_option('ms_files_rewriting')) {
                    if (defined('MULTISITE')) {
                        $ms_dir = '/sites/' . get_current_blog_id();
                    } else {
                        $ms_dir = '/' . get_current_blog_id();
                    }
                    $dir .= $ms_dir;
                    $url .= $ms_dir;
                } elseif (!ms_is_switched()) {
                    $dir = ABSPATH . E2PDF_UPLOADS;
                    $url = trailingslashit($siteurl) . 'files';
                }
            }

            $basedir = $dir;
            $baseurl = $url;
            $subdir = '';

            if (get_option('uploads_use_yearmonth_folders')) {
                $time = current_time('mysql');
                $y = substr($time, 0, 4);
                $m = substr($time, 5, 2);
                $subdir = "/$y/$m";
            }

            $dir .= $subdir;
            $url .= $subdir;

            if (!file_exists($basedir)) {
                $this->create_dir($basedir, true, false, false);
            }

            $wp_upload_dir = [
                'path' => $dir,
                'url' => $url,
                'subdir' => $subdir,
                'basedir' => $basedir,
                'baseurl' => $baseurl,
                'error' => false,
            ];
        }

        if ($key && isset($wp_upload_dir[$key])) {
            return $wp_upload_dir[$key];
        } else {
            return '';
        }
    }

    // get upload url
    public function get_upload_url($path = false) {
        if ($path) {
            return $this->get_wp_upload_dir('baseurl') . '/' . basename(untrailingslashit($this->get('upload_dir'))) . '/' . $path;
        } else {
            return $this->get_wp_upload_dir('baseurl') . '/' . basename(untrailingslashit($this->get('upload_dir')));
        }
    }

    // is multidimensional array
    public function is_multidimensional($a) {
        if (is_array($a)) {
            foreach ($a as $v) {
                if (is_array($v) || is_object($v)) {
                    return true;
                }
            }
        }
        return false;
    }

    // get caps
    public function get_caps() {
        $caps = [
            'e2pdf' => [
                'name' => __('Create PDF', 'e2pdf'),
                'cap' => 'e2pdf',
            ],
            'e2pdf_templates' => [
                'name' => __('Templates', 'e2pdf'),
                'cap' => 'e2pdf_templates',
            ],
            'e2pdf_settings' => [
                'name' => __('Settings', 'e2pdf'),
                'cap' => 'e2pdf_settings',
            ],
            'e2pdf_license' => [
                'name' => __('License', 'e2pdf'),
                'cap' => 'e2pdf_license',
            ],
            'e2pdf_debug' => [
                'name' => __('Debug', 'e2pdf'),
                'cap' => 'e2pdf_debug',
            ],
        ];
        return $caps;
    }

    // load
    public function load($helper) {
        $model = null;
        $class = 'Helper_E2pdf_' . ucfirst($helper);
        if (class_exists($class)) {
            if (!$this->get($class)) {
                $this->set($class, new $class());
            }
            $model = $this->get($class);
        }
        return $model;
    }

    // get frontend site url
    public function get_frontend_site_url() {
        if (function_exists('pll_home_url')) {
            return pll_home_url();
        }
        if (function_exists('icl_t') && !defined('POLYLANG_VERSION')) {
            return home_url('/');
        }
        return get_option('e2pdf_url_format', 'siteurl') === 'home' ? home_url('/') : site_url('/');
    }

    // get frontend pdf url
    public function get_frontend_pdf_url($url_data = [], $site_url = false, $filters = []) {

        if ($site_url === false) {
            $site_url = $this->get_frontend_site_url();
        }

        $site_url = apply_filters('e2pdf_helper_get_frontend_pdf_url_pre_site_url', $site_url);

        if (!empty($filters)) {
            foreach ($filters as $filter) {
                $site_url = apply_filters($filter, $site_url);
            }
        }

        $url_query = wp_parse_url($site_url, PHP_URL_QUERY);
        if ($url_query) {
            $site_url = str_replace('?' . $url_query, '', $site_url);
            $queries = explode('&', $url_query);
            foreach ($queries as $query) {
                $q = explode('=', $query);
                if (isset($q[0]) && isset($q[1])) {
                    $url_data[$q[0]] = $q[1];
                } elseif (isset($q[0])) {
                    $url_data[$q[0]] = '';
                }
            }
        }

        if (get_option('e2pdf_mod_rewrite', '0')) {
            $site_url = rtrim($site_url, '/') . '/' . get_option('e2pdf_mod_rewrite_url', 'e2pdf/%uid%/');
            if (isset($url_data['uid'])) {
                $site_url = str_replace('%uid%', $url_data['uid'], $site_url);
                unset($url_data['uid']);
            } else {
                $site_url = str_replace('%uid%', '', $site_url);
            }
            if (isset($url_data['page'])) {
                unset($url_data['page']);
            }
        }

        if (isset($url_data['download_name'])) {
            $site_url = trailingslashit($site_url) . $url_data['download_name'];
            unset($url_data['download_name']);
        }

        $site_url = apply_filters('e2pdf_helper_get_frontend_pdf_url_site_url', $site_url);
        $url_data = apply_filters('e2pdf_helper_get_frontend_pdf_url_url_data', $url_data);

        $url = add_query_arg($url_data, $site_url);

        return $this->load('translator')->translate_url($url);
    }

    // get frontend local pdf url
    public function get_frontend_local_pdf_url($pdf, $site_url = false, $filters = []) {
        if ($site_url === false) {
            $site_url = $this->get_frontend_site_url();
        }

        $site_url = apply_filters('e2pdf_helper_get_frontend_pdf_url_pre_site_url', $site_url);

        if (!empty($filters)) {
            foreach ($filters as $filter) {
                $site_url = apply_filters($filter, $site_url);
            }
        }
        return $site_url . str_replace(ABSPATH, '', $pdf);
    }

    // get site url
    public function get_site_url() {
        $site_url = false;
        if (class_exists('SitePress')) {
            $settings = get_option('icl_sitepress_settings');
            if (isset($settings['language_negotiation_type']) && $settings['language_negotiation_type'] == '2') {
                global $wpdb;
                $site_url = $wpdb->get_var($wpdb->prepare('SELECT option_value FROM `' . $wpdb->options . '` WHERE option_name = %s LIMIT 1', 'siteurl'));
            }
        }
        if (function_exists('pll_home_url')) {
            $settings = get_option('polylang');
            if (isset($settings['force_lang']) && ($settings['force_lang'] == '2' || $settings['force_lang'] == '3')) {
                global $wpdb;
                $site_url = $wpdb->get_var($wpdb->prepare('SELECT option_value FROM `' . $wpdb->options . '` WHERE option_name = %s LIMIT 1', 'siteurl'));
            }
        }
        if (!$site_url) {
            $site_url = site_url();
        }
        return $site_url;
    }

    // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodDoubleUnderscore
    public function __return_true() {
        return true;
    }

    // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodDoubleUnderscore
    public function __return_false() {
        return false;
    }

    // set time limit
    public function set_time_limit($timeout = 0) {
        $timeout = (int) $timeout;
        if (function_exists('set_time_limit') && is_callable('set_time_limit')) {
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            @set_time_limit($timeout);
        } elseif (function_exists('ini_set') && is_callable('ini_set')) {
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.max_execution_time_Blacklisted
            @ini_set('max_execution_time', $timeout);
        }
    }

    // shortcodes
    public function shortcodes() {
        return [
            'e2pdf-download',
            'e2pdf-view',
            'e2pdf-save',
            'e2pdf-zapier',
            'e2pdf-adobesign',
            'e2pdf-attachment',
            'e2pdf-vc-download',
            'e2pdf-vc-download-item',
            'e2pdf-vc-view',
            'e2pdf-vc-view-item',
            'e2pdf-format-number',
            'e2pdf-format-date',
            'e2pdf-format-output',
            'e2pdf-frm-field-value',
            'e2pdf-translate',
            'e2pdf-math',
            'e2pdf-content',
            'e2pdf-exclude',
            'e2pdf-filter',
            'e2pdf-page-number',
            'e2pdf-page-total',
            'e2pdf-user',
            'e2pdf-wp',
            'e2pdf-wp-term',
            'e2pdf-wp-posts',
            'e2pdf-wp-users',
            'e2pdf-wc-product',
            'e2pdf-wc-order',
            'e2pdf-wc-cart',
            'e2pdf-wc-customer',
            'e2pdf-foreach',
            'e2pdf-acf-repeater',
        ];
    }
}
