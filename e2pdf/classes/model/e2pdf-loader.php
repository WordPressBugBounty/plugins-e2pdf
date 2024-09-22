<?php

/**
 * E2Pdf Loader Helper
 * @copyright  Copyright 2017 https://e2pdf.com
 * @license    GPLv3
 * @version    1
 * @link       https://e2pdf.com
 * @since      0.00.01
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_Loader extends Model_E2pdf_Model {

    private $errors = array();
    private $e2pdf_admin_pages = array(
        'toplevel_page_e2pdf',
        'e2pdf_page_e2pdf-templates',
        'e2pdf_page_e2pdf-settings',
        'e2pdf_page_e2pdf-license',
        'e2pdf_page_e2pdf-debug',
    );

    /**
     * Main loader of actions / filters / hooks
     */
    public function load() {
        $this->load_translation();
        $this->load_actions();
        $this->load_filters();
        $this->load_extensions();
        $this->load_hooks();
        $this->load_ajax();
        $this->load_shortcodes();
    }

    /**
     * Load translation
     */
    public function load_translation() {
        load_plugin_textdomain('e2pdf', false, '/e2pdf/languages/');
    }

    /**
     * Load ajax
     */
    public function load_ajax() {
        if (is_admin()) {
            add_action('wp_ajax_e2pdf_save_form', array(new Controller_E2pdf_Templates(), 'ajax_save_form'));
            add_action('wp_ajax_e2pdf_auto', array(new Controller_E2pdf_Templates(), 'ajax_auto'));
            add_action('wp_ajax_e2pdf_upload', array(new Controller_E2pdf_Templates(), 'ajax_upload'));
            add_action('wp_ajax_e2pdf_reupload', array(new Controller_E2pdf_Templates(), 'ajax_reupload'));
            add_action('wp_ajax_e2pdf_extension', array(new Controller_E2pdf_Templates(), 'ajax_extension'));
            add_action('wp_ajax_e2pdf_activate_template', array(new Controller_E2pdf_Templates(), 'ajax_activate_template'));
            add_action('wp_ajax_e2pdf_deactivate_template', array(new Controller_E2pdf_Templates(), 'ajax_deactivate_template'));
            add_action('wp_ajax_e2pdf_visual_mapper', array(new Controller_E2pdf_Templates(), 'ajax_visual_mapper'));
            add_action('wp_ajax_e2pdf_get_styles', array(new Controller_E2pdf_Templates(), 'ajax_get_styles'));
            add_action('wp_ajax_e2pdf_email', array(new Controller_E2pdf_Templates(), 'ajax_email'));
            add_action('wp_ajax_e2pdf_license_key', array(new Controller_E2pdf_License(), 'ajax_change_license_key'));
            add_action('wp_ajax_e2pdf_restore_license_key', array(new Controller_E2pdf_License(), 'ajax_restore_license_key'));
            add_action('wp_ajax_e2pdf_deactivate_all_templates', array(new Controller_E2pdf_License(), 'ajax_deactivate_all_templates'));
            add_action('wp_ajax_e2pdf_templates', array(new Controller_E2pdf(), 'ajax_templates'));
            add_action('wp_ajax_e2pdf_dataset', array(new Controller_E2pdf(), 'ajax_dataset'));
            add_action('wp_ajax_e2pdf_delete_item', array(new Controller_E2pdf(), 'ajax_delete_item'));
            add_action('wp_ajax_e2pdf_delete_items', array(new Controller_E2pdf(), 'ajax_delete_items'));
            add_action('wp_ajax_e2pdf_delete_font', array(new Controller_E2pdf_Settings(), 'ajax_delete_font'));
            add_action('wp_ajax_e2pdf_bulk_create', array(new Controller_E2pdf(), 'ajax_bulk_create'));
            add_action('wp_ajax_e2pdf_bulk_action', array(new Controller_E2pdf(), 'ajax_bulk_action'));
            add_action('wp_ajax_e2pdf_bulk_progress', array(new Controller_E2pdf(), 'ajax_bulk_progress'));
        }
    }

    /**
     * Load actions
     */
    public function load_actions() {
        if (is_admin()) {
            add_action('wpmu_new_blog', array(&$this, 'action_wpmu_new_blog'), 10, 6);
            add_action('admin_menu', array(&$this, 'action_admin_menu'));
            add_action('admin_init', array(&$this, 'action_admin_init'));
            add_action('admin_enqueue_scripts', array(&$this, 'action_admin_enqueue_scripts'));
            add_action('current_screen', array(&$this, 'action_current_screen'));
            add_action('plugins_loaded', array(&$this, 'action_plugins_loaded'));
        }
        add_action('wp_enqueue_scripts', array(&$this, 'action_wp_enqueue_scripts'));
        add_action('wp', array(Helper_E2pdf_View::instance(), 'render_frontend_page'), 5);
        add_action('wp_loaded', array(&$this, 'action_wp_loaded'));
        add_action('init', array(&$this, 'action_init'), 0);
        add_action('e2pdf_bulk_export_cron', array(new Controller_E2pdf(), 'cron_bulk_export'));
        add_action('e2pdf_cache_pdfs_cron', array(&$this, 'cron_cache_pdfs'));
    }

    public function action_init() {

        if ($this->helper->get('page') == 'e2pdf-download') {
            /**
             * Comtatiability fix with Minify HTML
             * https://wordpress.org/plugins/minify-html-markup/
             */
            remove_action('init', 'teckel_init_minify_html', 1);

            /**
             * Comtatiability fix with Minimal Coming Soon – Coming Soon Page
             * https://wordpress.org/plugins/minimal-coming-soon-maintenance-mode/
             */
            remove_action('init', 'csmm_plugin_init');

            /**
             * Weglot Translate – Translate your WordPress website and go multilingual
             * https://wordpress.org/plugins/weglot/
             */
            add_filter('weglot_active_translation_before_treat_page', '__return_false');

            /**
             * Compatibility fix with UltimateMember Homepage Redirect
             * https://wordpress.org/plugins/ultimate-member/
             */
            if (class_exists('UM') && method_exists(UM(), 'access')) {
                remove_filter('the_posts', array(UM()->access(), 'filter_protected_posts'), 99);
            }
        }

        if (get_option('e2pdf_mod_rewrite', '0')) {
            global $wp_rewrite;

            $rewrite_url = rtrim(str_replace('%uid%', '([A-Za-z0-9]+)', get_option('e2pdf_mod_rewrite_url', 'e2pdf/%uid%/')), '/');

            if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] && preg_match('#' . $rewrite_url . '#', $_SERVER['REQUEST_URI'])) {
                /**
                 * Comtatiability fix with Minify HTML
                 * https://wordpress.org/plugins/minify-html-markup/
                 */
                remove_action('init', 'teckel_init_minify_html', 1);

                /**
                 * Comtatiability fix with Minimal Coming Soon – Coming Soon Page
                 * https://wordpress.org/plugins/minimal-coming-soon-maintenance-mode/
                 */
                remove_action('init', 'csmm_plugin_init');

                /**
                 * Weglot Translate – Translate your WordPress website and go multilingual
                 * https://wordpress.org/plugins/weglot/
                 */
                add_filter('weglot_active_translation_before_treat_page', '__return_false');
            }

            $rules = [
                '^' . $rewrite_url,
                '^' . $wp_rewrite->index . '/' . $rewrite_url,
            ];

            $rewrite_to = 'index.php?e2pdf=1&uid=$matches[1]';
            add_rewrite_rule($rules[0], $rewrite_to, 'top');
            add_rewrite_rule($rules[1], $rewrite_to, 'top');

            $rewrite_rules = get_option('rewrite_rules');
            foreach ($rules as $rule) {
                if (!isset($rewrite_rules[$rule])) {
                    flush_rewrite_rules(false);
                    break;
                }
            }
        }
    }

    public function cronjob() {
        $model_e2pdf_bulk = new Model_E2pdf_Bulk();
        $model_e2pdf_bulk->process();
    }

    public function cron_cache_pdfs() {
        $this->helper->load('cache')->purge_pdfs_cache_ttl();
    }

    public function load_filters() {
        if (get_option('e2pdf_dev_update', '0')) {
            add_filter('pre_set_site_transient_update_plugins', array(&$this, 'filter_pre_set_site_transient_update_plugins'));
        }

        /**
         * SiteGround Optimizer HTML Minify compatibility fix filter
         * https://wordpress.org/plugins/sg-cachepress/
         */
        add_filter('sgo_html_minify_exclude_urls', array(&$this, 'filter_sgo_html_minify_exclude_urls'));

        if (get_option('e2pdf_mod_rewrite', '0')) {
            add_filter('query_vars', array(&$this, 'filter_query_vars'));
        }
        add_filter('cron_schedules', array(&$this, 'filter_cron_schedules'));
    }

    public function action_plugins_loaded() {
        if (get_option('e2pdf_version') !== $this->helper->get('version')) {
            $this->activate();
        }
    }

    /**
     * Load extensions and its action/filters
     */
    public function load_extensions() {

        $model_e2pdf_extension = new Model_E2pdf_Extension();
        $extensions = $model_e2pdf_extension->extensions();
        if (!empty($extensions)) {
            foreach ($extensions as $extension => $extension_name) {
                $model_e2pdf_extension->load($extension);
                $model_e2pdf_extension->load_actions();
                $model_e2pdf_extension->load_filters();
                $model_e2pdf_extension->load_shortcodes();
            }
        }
    }

    /**
     * Load filters
     */
    public function load_shortcodes() {
        add_shortcode('e2pdf-download', array(new Model_E2pdf_Shortcode(), 'e2pdf_download'));
        add_shortcode('e2pdf-attachment', array(new Model_E2pdf_Shortcode(), 'e2pdf_attachment'));
        add_shortcode('e2pdf-adobesign', array(new Model_E2pdf_Shortcode(), 'e2pdf_adobesign'));
        add_shortcode('e2pdf-save', array(new Model_E2pdf_Shortcode(), 'e2pdf_save'));
        add_shortcode('e2pdf-view', array(new Model_E2pdf_Shortcode(), 'e2pdf_view'));
        add_shortcode('e2pdf-zapier', array(new Model_E2pdf_Shortcode(), 'e2pdf_zapier'));
        add_shortcode('e2pdf-format-number', array(new Model_E2pdf_Shortcode(), 'e2pdf_format_number'));
        add_shortcode('e2pdf-format-date', array(new Model_E2pdf_Shortcode(), 'e2pdf_format_date'));
        add_shortcode('e2pdf-format-output', array(new Model_E2pdf_Shortcode(), 'e2pdf_format_output'));
        add_shortcode('e2pdf-math', array(new Model_E2pdf_Shortcode(), 'e2pdf_math'));
        add_shortcode('e2pdf-user', array(new Model_E2pdf_Shortcode(), 'e2pdf_user'));
        add_shortcode('e2pdf-wp', array(new Model_E2pdf_Shortcode(), 'e2pdf_wp'));
        add_shortcode('e2pdf-wp-term', array(new Model_E2pdf_Shortcode(), 'e2pdf_wp_term'));
        add_shortcode('e2pdf-wp-posts', array(new Model_E2pdf_Shortcode(), 'e2pdf_wp_posts'));
        add_shortcode('e2pdf-content', array(new Model_E2pdf_Shortcode(), 'e2pdf_content'));
        add_shortcode('e2pdf-exclude', array(new Model_E2pdf_Shortcode(), 'e2pdf_exclude'));
        add_shortcode('e2pdf-filter', array(new Model_E2pdf_Shortcode(), 'e2pdf_filter'));
        add_shortcode('e2pdf-wc-product', array(new Model_E2pdf_Shortcode(), 'e2pdf_wc_product'));
        add_shortcode('e2pdf-wc-order', array(new Model_E2pdf_Shortcode(), 'e2pdf_wc_order'));
        add_shortcode('e2pdf-wc-cart', array(new Model_E2pdf_Shortcode(), 'e2pdf_wc_cart'));
        add_shortcode('e2pdf-wc-customer', array(new Model_E2pdf_Shortcode(), 'e2pdf_wc_customer'));
        add_shortcode('e2pdf-foreach', array(new Model_E2pdf_Shortcode(), 'e2pdf_foreach'));
        add_shortcode('e2pdf-page-number', array(new Model_E2pdf_Shortcode(), 'e2pdf_page_number'));
        add_shortcode('e2pdf-page-total', array(new Model_E2pdf_Shortcode(), 'e2pdf_page_total'));
        add_shortcode('e2pdf-acf-repeater', array(new Model_E2pdf_Shortcode(), 'e2pdf_acf_repeater'));
    }

    /**
     * Load hooks
     */
    public function load_hooks() {
        register_activation_hook($this->helper->get('plugin_file_path'), array(&$this, 'activate'));
        register_deactivation_hook($this->helper->get('plugin_file_path'), array(&$this, 'deactivate'));
        register_uninstall_hook($this->helper->get('plugin_file_path'), array('Model_E2pdf_Loader', 'uninstall'));
    }

    /**
     * URL Rewrite Tags Support
     */
    public function filter_query_vars($tags) {
        global $wp;
        if (!empty($_GET['e2pdf']) || strpos($wp->matched_query, 'e2pdf=1') === 0) {
            $tags[] = 'e2pdf';
            $tags[] = 'uid';
        }
        return $tags;
    }

    public function filter_cron_schedules($schedules) {
        $schedules['e2pdf_bulk_export_interval'] = array(
            'interval' => max(20, (int) get_option('e2pdf_bulk_export_interval', '20')),
            'display' => __('E2Pdf Bulk Export Interval', 'e2pdf'),
        );
        return $schedules;
    }

    /**
     * SiteGround Optimizer HTML Minify compatibility fix filter
     * https://wordpress.org/plugins/sg-cachepress/
     */
    public function filter_sgo_html_minify_exclude_urls($exclude_urls) {
        if (is_array($exclude_urls)) {
            $exclude_urls[] = '/?page=e2pdf-download&uid=*';
            if (get_option('e2pdf_mod_rewrite', '0')) {
                $exclude_urls[] = '/' . rtrim(str_replace('%uid%', '*', get_option('e2pdf_mod_rewrite_url', 'e2pdf/%uid%/')), '/') . '*';
            }
        }
        return $exclude_urls;
    }

    /**
     * Force Updates from E2Pdf.com
     */
    public function filter_pre_set_site_transient_update_plugins($transient) {

        if (!is_object($transient)) {
            return $transient;
        }

        $model_e2pdf_api = new Model_E2pdf_Api();
        $model_e2pdf_api->set(array(
            'action' => 'update/info',
        ));
        $request = $model_e2pdf_api->request();

        if (isset($request['package'])) {
            if (isset($transient->response[$this->helper->get('plugin')])) {
                $update_info = $transient->response[$this->helper->get('plugin')];
            } elseif (isset($transient->no_update[$this->helper->get('plugin')])) {
                $update_info = $transient->no_update[$this->helper->get('plugin')];
            } else {
                $update_info = (object) array(
                            'id' => $this->helper->get('plugin'),
                            'url' => 'https://e2pdf.com',
                            'slug' => $this->helper->get('slug'),
                            'plugin' => $this->helper->get('plugin'),
                            'package' => $request['package'],
                            'new_version' => $request['version'],
                            'tested' => $request['tested'],
                            'icons' => $request['icons'],
                            'banners' => $request['banners'],
                );
            }
            if (version_compare($this->helper->get('version'), $request['version'], '<')) {
                $update_info->package = $request['package'];
                $update_info->new_version = $request['version'];
                $update_info->tested = $request['tested'];
                $transient->response[$this->helper->get('plugin')] = $update_info;
                if (isset($transient->no_update[$this->helper->get('plugin')])) {
                    unset($transient->no_update[$this->helper->get('plugin')]);
                }
            }
        }

        return $transient;
    }

    /**
     * Load admin menu
     */
    public function action_admin_menu() {
        ob_start();
        $caps = $this->helper->get_caps();
        if (current_user_can('manage_options')) {
            foreach ($caps as $cap_key => $cap) {
                $caps[$cap_key]['cap'] = 'manage_options';
            }
        }

        add_menu_page('e2pdf', 'E2Pdf', $caps['e2pdf']['cap'], 'e2pdf', array(Helper_E2pdf_View::instance(), 'render_page'), $this->get_icon(), '26');
        add_submenu_page('e2pdf', __('Export', 'e2pdf'), __('Export', 'e2pdf'), $caps['e2pdf']['cap'], 'e2pdf', array(Helper_E2pdf_View::instance(), 'render_page'));
        add_submenu_page('e2pdf', __('Templates', 'e2pdf'), __('Templates', 'e2pdf'), $caps['e2pdf_templates']['cap'], 'e2pdf-templates', array(Helper_E2pdf_View::instance(), 'render_page'));
        add_submenu_page('e2pdf', __('Settings', 'e2pdf'), __('Settings', 'e2pdf'), $caps['e2pdf_settings']['cap'], 'e2pdf-settings', array(Helper_E2pdf_View::instance(), 'render_page'));
        add_submenu_page('e2pdf', __('License', 'e2pdf'), __('License', 'e2pdf'), $caps['e2pdf_license']['cap'], 'e2pdf-license', array(Helper_E2pdf_View::instance(), 'render_page'));
        if (get_option('e2pdf_debug', '0') === '1') {
            add_submenu_page('e2pdf', __('Debug', 'e2pdf'), __('Debug', 'e2pdf'), $caps['e2pdf_debug']['cap'], 'e2pdf-debug', array(Helper_E2pdf_View::instance(), 'render_page'));
        }
    }

    public function get_icon() {
        $icon = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjxzdmcKICAgd2lkdGg9IjIwIgogICBoZWlnaHQ9IjIwIgogICB2aWV3Qm94PSIwIDAgMjAgMjAiCiAgIGZpbGw9IiNhN2FhYWQiCiAgIHZlcnNpb249IjEuMSIKICAgaWQ9InN2ZzQ0OTM4OSIKICAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogICB4bWxuczpzdmc9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8ZGVmcwogICAgIGlkPSJkZWZzNDQ5MzkzIiAvPgogIDxnCiAgICAgaWQ9ImxheWVyMTUiPgogICAgPHBhdGgKICAgICAgIGlkPSJwYXRoMTExOTIxLTYtMi0xIgogICAgICAgc3R5bGU9ImRpc3BsYXk6aW5saW5lO2ZpbGw6I2E3YWFhZDtmaWxsLW9wYWNpdHk6MTtzdHJva2Utd2lkdGg6MjYuMDU1NiIKICAgICAgIGQ9Im0gMTIuNzgwNzA5LDEuMjU5NTMzIGMgLTEuMTA4NDA4LDAuMDM3MTg5IC0xLjY5MzAwMSwwLjY1MDgyODcgLTEuNzMzMjE2LDEuNzcwNjM0OCBoIDAuNzI5NTQ4IGMgMC4wNDE1MSwtMC42MzIwMTg2IDAuNDQxMjQ2LC0xLjA0ODU0NDQgMS4xMTM3NDgsLTEuMDQ4NTQ0NCAwLjY2NDE5OSwwIDEuMDk0MzIxLDAuMzMzNjk3MSAxLjA5NDMyMSwxLjA1NzE5MjIgMCwwLjY3MzU5ODggLTAuNDgxMjc0LDEuMTIxNjg1NyAtMS4wMjA5MzUsMS40Mzc2OTUgLTAuMTc0MzUzLDAuMTE2NDI0NCAtMC4zNjU5MTYsMC4yMjU0OTQ5IC0wLjU1Njg3NCwwLjM1MDIzNTQgLTAuNzE0MDE0LDAuNDU3MzgyIC0xLjQxMTEwNywxLjAzMDc4MzkgLTEuNDUyNjIsMi4zMTk3Njk0IGggMy44MjA0MTMgViA2LjM0ODc1NjggSCAxMi4wMzM4OTMgViA2LjIyMzM2NCBjIDAsLTAuMDE2NjMzIDAuMDA4NiwtMC4wNjczNTIgMC4wMDg2LC0wLjA3NTY2OCAwLjE0MTE0MywtMC4zMzI2NDE0IDAuMzk3OTUxLC0wLjU4MDg3ODIgMC42ODg1MzgsLTAuNzgwNDYzIDAuMjgyMjg1LC0wLjIwNzkwMDggMC42MDYxNTMsLTAuMzczNjYzIDAuOTIxNjQ3LC0wLjU4MTU2MzggMC42MDYwODEsLTAuMzkwODUzNyAxLjEyMjM4MiwtMC44NzQ4MjUzIDEuMTIyMzgyLC0xLjgzMTE2OTMgMCwtMS4xODA4NzY5IC0wLjc2MzQ2OSwtMS42OTQ5NjY2IC0xLjg4NDMwNiwtMS42OTQ5NjY2IC0wLjAzNjg0LDAgLTAuMDc0MzIsLTAuMDAxMiAtMC4xMTAwOCwwIHogTSA1Ljg2NTExMzksMS4zNDE2ODcgViAyLjIxNTExMzcgSCAxMC4wMDA2NTYgViAxLjM0MTY4NyBaIG0gMCwyLjQ5NDg4NzIgViA0LjY1MTYyODMgSCAxMC4wMDA2NTYgViAzLjgzNjU3NDIgWiBtIDAsMi40Mjc4NjY3IFYgNy4xNDY1MTU0IEggMTAuMDAwNjU2IFYgNi4yNjQ0NDA5IFogTSAzLjkwNTI2MzYsOS4zMjc5MjA0IEMgMS4zMDM1OTUsOS4zMjc5MjA0IDAuMjY0LDEwLjM2OTIwOSAwLjI2NCwxMi45NzUxMjUgdiAzLjEyODMzOCBjIDAsMi42MDU5MTMgMS4wMzk1OTUsMy42NDcyMDUgMy42NDEyNjM2LDMuNjQ3MjA1IGggMy4xMjMyNDE2IDUuOTY1ODg3OCAxLjUxNTIxNSAxLjYwODAyNyBjIDIuNjAxNjY5LDAgMy42NDEyNjQsLTEuMDQxMjkyIDMuNjQxMjY0LC0zLjY0NzIwNSB2IC0zLjEyODMzOCBjIDAsLTIuNjA1OTE2IC0xLjAzOTU5NSwtMy42NDcyMDQ2IC0zLjY0MTI2NCwtMy42NDcyMDQ2IEggMTQuNTA5NjA4IDEyLjk5NDM5MyA3LjAyODUwNTIgWiBNIDIuODc3ODUzMSwxMS43MDYwNjIgaCAyLjE5OTQzNTUgYyAxLjIzNjAzMjIsMCAyLjAxMTY1MjUsMC41MTk4MTYgMi4wMTE2NTI1LDEuODA3Mzg3IDAsMS4yNTQ1NTcgLTAuNzkyMTAwNywxLjkxNTQ4NiAtMi4wMTE2NTI1LDEuOTE1NDg2IEggMy43NjcxMjQ0IHYgMi4wMzg3MTYgSCAyLjg3Nzg1MzEgWiBtIDQuOTk0NTk2MywwIGggMi4zMDUxOTg2IGMgMS43MDU3MjUsMCAyLjQ0NzY1NSwxLjIyMjg5IDIuNDQ3NjU0LDIuODgxODc1IDJlLTYsMS42NTA3MzIgLTAuNzUwMTY5LDIuODc5NzE0IC0yLjQ0NzY1NCwyLjg3OTcxNCBIIDcuODcyNDQ5NCBaIG0gNS43OTEwNTU2LDAgaCA0LjA4ODA1NyB2IDAuODY2OTQxIGggLTMuMTgxNTE5IHYgMS42NjY4NjEgaCAyLjg5MjI5MSB2IDAuNzg0Nzg3IGggLTIuODkyMjkxIHYgMi40NDMgaCAtMC45MDY1MzggeiBtIC05Ljg5NjM4MDYsMC43NTg4NDQgdiAyLjIwNTE4NSBoIDEuMTIyMzgxMiBjIDAuNjgzOTM3OSwwIDEuMjUxODg2NywtMC4zMTMxOTIgMS4yNTE4ODY3LC0xLjEyMjA1IDAsLTAuODUwMTI3IC0wLjU3NjU4MjYsLTEuMDgzMTM1IC0xLjI2MDUyMDQsLTEuMDgzMTM1IHogbSA1LjAwMzIzLDAuMTA4MTAxIHYgNC4wMTkwNiBoIDEuMDQ2ODM2NCBjIDEuMzE4NDM0MiwwIDEuODY5MTk2MiwtMC42OTE3OTMgMS44NjkxOTYyLC0yLjAwNDEyNiAwLC0xLjMwNDA3NyAtMC41Njc2MzYsLTIuMDE0OTM0IC0xLjg3NzgyOTksLTIuMDE0OTM0IHoiIC8+CiAgPC9nPgogIDxnCiAgICAgaWQ9ImxheWVyMTYiIC8+Cjwvc3ZnPg==';
        return $icon;
    }

    /**
     * Setup settings page
     */
    public function action_admin_init() {
        register_setting('e2pdf-settings', 'e2pdf_debug');
    }

    /**
     * Load admin javascript
     * @param string $page - Current page
     */
    public function action_admin_enqueue_scripts($page) {
        if (get_option('e2pdf_debug') === '1') {
            $version = strtotime('now');
        } else {
            $version = $this->helper->get('version');
        }
        wp_register_style('e2pdf.backend.global', plugins_url('css/e2pdf.backend.global.css', $this->helper->get('plugin_file_path')), array(), $version);
        wp_enqueue_style('e2pdf.backend.global');
        if (!in_array($page, $this->e2pdf_admin_pages, false)) {
            return;
        }
        wp_enqueue_script(
                'js/e2pdf.backend', plugins_url('js/e2pdf.backend.js', $this->helper->get('plugin_file_path')), array('jquery'), $version, false
        );
        wp_localize_script('js/e2pdf.backend', 'e2pdf_lang', $this->get_js('lang'));
        wp_localize_script('js/e2pdf.backend', 'e2pdf_params', $this->get_js('params'));
        wp_register_style('e2pdf.backend', plugins_url('css/e2pdf.backend.css', $this->helper->get('plugin_file_path')), array(), $version);
        wp_enqueue_style('e2pdf.backend');
    }

    /**
     * Load javascript
     * @param string $page - Current page
     */
    public function action_wp_enqueue_scripts() {

        if (get_option('e2pdf_debug', '0') === '1') {
            $version = strtotime('now');
        } else {
            $version = $this->helper->get('version');
        }

        wp_register_script(
                'js/e2pdf.frontend', plugins_url('js/e2pdf.frontend.js', $this->helper->get('plugin_file_path')), array('jquery'), $version, false
        );
        wp_enqueue_script(
                'js/e2pdf.frontend'
        );
    }

    public function get_js($type) {
        $data = array();
        switch ($type) {
            case 'lang':
                $data = array(
                    'Are you sure want to remove page?' => __('Are you sure want to remove page?', 'e2pdf'),
                    'Are you sure want to remove font?' => __('Are you sure want to remove font?', 'e2pdf'),
                    'Saved Template will be overwritten! Are you sure want to continue?' => __('Saved Template will be overwritten! Are you sure want to continue?', 'e2pdf'),
                    'All pages will be removed! Are you sure want to continue?' => __('All pages will be removed! Are you sure want to continue?', 'e2pdf'),
                    'Adding new pages not available in "Uploaded PDF"' => __('Adding new pages not available in "Uploaded PDF"', 'e2pdf'),
                    'Dataset will be removed! Are you sure want to continue?' => __('Dataset will be removed! Are you sure want to continue?', 'e2pdf'),
                    'All datasets will be removed! Are you sure to continue?' => __('All datasets will be removed! Are you sure to continue?', 'e2pdf'),
                    'WARNING: Template has changes after last save! Changes will be lost!' => __('WARNING: Template has changes after last save! Changes will be lost!', 'e2pdf'),
                    'Element will be removed! Are you sure want to continue?' => __('Element will be removed! Are you sure want to continue?', 'e2pdf'),
                    'Elements will be removed! Are you sure want to continue?' => __('Elements will be removed! Are you sure want to continue?', 'e2pdf'),
                    'Action will be removed! Are you sure want to continue?' => __('Action will be removed! Are you sure want to continue?', 'e2pdf'),
                    'Condition will be removed! Are you sure want to continue?' => __('Condition will be removed! Are you sure want to continue?', 'e2pdf'),
                    'All Field Values will be overwritten! Are you sure want to continue?' => __('All Field Values will be overwritten! Are you sure want to continue?', 'e2pdf'),
                    'Website will be forced to use "FREE" License Key! Are you sure want to continue?' => __('Website will be forced to use "FREE" License Key! Are you sure want to continue?', 'e2pdf'),
                    'Not Available in Revision Edit Mode' => __('Not Available in Revision Edit Mode', 'e2pdf'),
                    'WYSIWYG Editor is disabled for this HTML Object' => __('WYSIWYG Editor is disabled for this HTML Object', 'e2pdf'),
                    'WYSIWYG can be applied only to HTML Object' => __('WYSIWYG can be applied only to HTML Object', 'e2pdf'),
                    'Only 1 page allowed with "FREE" license type' => __('Only 1 page allowed with "FREE" license type', 'e2pdf'),
                    'Last condition can\'t be removed' => __('Last condition can\'t be removed', 'e2pdf'),
                    'In Progress...' => __('In Progress...', 'e2pdf'),
                    'Delete' => __('Delete', 'e2pdf'),
                    'Properties' => __('Properties', 'e2pdf'),
                    'License Key' => __('License Key', 'e2pdf'),
                    'Empty PDF' => __('Empty PDF', 'e2pdf'),
                    'Upload PDF' => __('Upload PDF', 'e2pdf'),
                    'Auto PDF' => __('Auto PDF', 'e2pdf'),
                    'Create PDF' => __('Create PDF', 'e2pdf'),
                    'Extension' => __('Extension', 'e2pdf'),
                    'Size' => __('Size', 'e2pdf'),
                    'Properties' => __('Properties', 'e2pdf'),
                    'Enter link here' => __('Enter link here', 'e2pdf'),
                    'Border' => __('Border', 'e2pdf'),
                    'Background' => __('Background', 'e2pdf'),
                    'Left' => __('Left', 'e2pdf'),
                    'Right' => __('Right', 'e2pdf'),
                    'Top' => __('Top', 'e2pdf'),
                    'Center' => __('Center', 'e2pdf'),
                    'Bottom' => __('Bottom', 'e2pdf'),
                    'Justify' => __('Justify', 'e2pdf'),
                    'Border Color' => __('Border Color', 'e2pdf'),
                    'Border Radius' => __('Border Radius', 'e2pdf'),
                    'Line Height' => __('Line Height', 'e2pdf'),
                    'Width' => __('Width', 'e2pdf'),
                    'Height' => __('Height', 'e2pdf'),
                    'Value' => __('Value', 'e2pdf'),
                    'Font' => __('Font', 'e2pdf'),
                    'Options' => __('Options', 'e2pdf'),
                    'Option' => __('Option', 'e2pdf'),
                    'Group' => __('Group', 'e2pdf'),
                    'Type' => __('Type', 'e2pdf'),
                    'Scale' => __('Scale', 'e2pdf'),
                    'Width&Height' => __('Width&Height', 'e2pdf'),
                    'Width' => __('Width', 'e2pdf'),
                    'Height' => __('Height', 'e2pdf'),
                    'Choose Image' => __('Choose Image', 'e2pdf'),
                    'PDF Options' => __('PDF Options', 'e2pdf'),
                    'PDF Upload' => __('PDF Upload', 'e2pdf'),
                    'Global Actions' => __('Global Actions', 'e2pdf'),
                    'Item' => __('Item', 'e2pdf'),
                    'Resize' => __('Resize', 'e2pdf'),
                    'Copy' => __('Copy', 'e2pdf'),
                    'Cut' => __('Cut', 'e2pdf'),
                    'Paste' => __('Paste', 'e2pdf'),
                    'Paste in Place' => __('Paste in Place', 'e2pdf'),
                    'Apply' => __('Apply', 'e2pdf'),
                    'Dynamic Height' => __('Dynamic Height', 'e2pdf'),
                    'Multipage' => __('Multipage', 'e2pdf'),
                    'Text Align' => __('Text Align', 'e2pdf'),
                    'Read-only' => __('Read-only', 'e2pdf'),
                    'Multiline' => __('Multiline', 'e2pdf'),
                    'Required' => __('Required', 'e2pdf'),
                    'Size Preset' => __('Size Preset', 'e2pdf'),
                    'Page Options' => __('Page Options', 'e2pdf'),
                    'Direction' => __('Direction', 'e2pdf'),
                    'RTL' => __('RTL', 'e2pdf'),
                    'LTR' => __('LTR', 'e2pdf'),
                    'Hide' => __('Hide', 'e2pdf'),
                    'Unhide' => __('Unhide', 'e2pdf'),
                    'Password' => __('Password', 'e2pdf'),
                    'Map Field' => __('Map Field', 'e2pdf'),
                    'Insert Mapped' => __('Insert Mapped', 'e2pdf'),
                    'Parent' => __('Parent', 'e2pdf'),
                    '--- Select ---' => __('--- Select ---', 'e2pdf'),
                    'Activated' => __('Activated', 'e2pdf'),
                    'Not Activated' => __('Not Activated', 'e2pdf'),
                    'Page ID' => __('Page ID', 'e2pdf'),
                    'Page ID inside Upload PDF' => __('Page ID inside Upload PDF', 'e2pdf'),
                    'Render Fields from Upload PDF' => __('Render Fields from Upload PDF', 'e2pdf'),
                    'Delete created E2Pdf Fields' => __('Delete created E2Pdf Fields', 'e2pdf'),
                    'Keep Image Ratio' => __('Keep Image Ratio', 'e2pdf'),
                    'Keep Lower Size' => __('Keep Lower Size', 'e2pdf'),
                    'Lock Aspect Ratio' => __('Lock Aspect Ratio', 'e2pdf'),
                    'Fill Image' => __('Fill Image', 'e2pdf'),
                    'Page' => __('Page', 'e2pdf'),
                    'Resample' => __('Resample', 'e2pdf'),
                    'Disable Text to Image' => __('Disable Text to Image', 'e2pdf'),
                    'Confirmation Code' => __('Confirmation Code', 'e2pdf'),
                    'Code' => __('Code', 'e2pdf'),
                    'Visual Mapper' => __('Visual Mapper', 'e2pdf'),
                    'Auto' => __('Auto', 'e2pdf'),
                    'Actions' => __('Actions', 'e2pdf'),
                    'Save' => __('Save', 'e2pdf'),
                    'Horizontal Align' => __('Horizontal Align', 'e2pdf'),
                    'Vertical Align' => __('Vertical Align', 'e2pdf'),
                    'Middle' => __('Middle', 'e2pdf'),
                    'Apply If' => __('Apply If', 'e2pdf'),
                    'Action' => __('Action', 'e2pdf'),
                    'Property' => __('Property', 'e2pdf'),
                    'If' => __('If', 'e2pdf'),
                    'Condition' => __('Condition', 'e2pdf'),
                    'Any' => __('Any', 'e2pdf'),
                    'All' => __('All', 'e2pdf'),
                    'Order' => __('Order', 'e2pdf'),
                    'E-Signature' => __('E-Signature', 'e2pdf'),
                    'Contact' => __('Contact', 'e2pdf'),
                    'Location' => __('Location', 'e2pdf'),
                    'Reason' => __('Reason', 'e2pdf'),
                    'Placeholder' => __('Placeholder', 'e2pdf'),
                    'Length' => __('Length', 'e2pdf'),
                    'Comb' => __('Comb', 'e2pdf'),
                    'None' => __('None', 'e2pdf'),
                    'Highlight' => __('Highlight', 'e2pdf'),
                    'Invert' => __('Invert', 'e2pdf'),
                    'Outline' => __('Outline', 'e2pdf'),
                    'Push' => __('Push', 'e2pdf'),
                    'Title' => __('Title', 'e2pdf'),
                    'Status' => __('Status', 'e2pdf'),
                    'Add Action' => __('Add Action', 'e2pdf'),
                    'Shortcodes' => __('Shortcodes', 'e2pdf'),
                    'Labels' => __('Labels', 'e2pdf'),
                    'Field Values' => __('Field Values', 'e2pdf'),
                    'Field Names' => __('Field Names', 'e2pdf'),
                    'Field Name' => __('Field Name', 'e2pdf'),
                    'As Field Name' => __('As Field Name', 'e2pdf'),
                    'Confirm' => __('Confirm', 'e2pdf'),
                    'Cancel' => __('Cancel', 'e2pdf'),
                    'Hide (If Empty)' => __('Hide (If Empty)', 'e2pdf'),
                    'Hide Page (If Empty)' => __('Hide Page (If Empty)', 'e2pdf'),
                    'Replace Value' => __('Replace Value', 'e2pdf'),
                    'Auto-Close' => __('Auto-Close', 'e2pdf'),
                    'E2Pdf License Key' => __('E2Pdf License Key', 'e2pdf'),
                    'New Lines to BR' => __('New Lines to BR', 'e2pdf'),
                    'Disable WYSIWYG Editor' => __('Disable WYSIWYG Editor', 'e2pdf'),
                    'CSS Priority' => __('CSS Priority', 'e2pdf'),
                    'Enabling WYSIWYG can affect "HTML" Source' => __('Enabling WYSIWYG can affect "HTML" Source', 'e2pdf'),
                    'Hidden Fields' => __('Hidden Fields', 'e2pdf'),
                    'Allow PDF Access By URL' => __('Allow PDF Access By URL', 'e2pdf'),
                    'Restrict PDF Access By URL' => __('Restrict PDF Access By URL', 'e2pdf'),
                    'Process Shortcodes' => __('Process Shortcodes', 'e2pdf'),
                    'Restrict Process Shortcodes' => __('Restrict Process Shortcodes', 'e2pdf'),
                    'Error Message' => __('Error Message', 'e2pdf'),
                    'Element' => __('Element', 'e2pdf'),
                    'Elements' => __('Elements', 'e2pdf'),
                    'Position Top' => __('Position Top', 'e2pdf'),
                    'Position Left' => __('Position Left', 'e2pdf'),
                    'Padding Top' => __('Padding Top', 'e2pdf'),
                    'Padding Bottom' => __('Padding Bottom', 'e2pdf'),
                    'Padding Left' => __('Padding Left', 'e2pdf'),
                    'Padding Right' => __('Padding Right', 'e2pdf'),
                    'Margin Top' => __('Margin Top', 'e2pdf'),
                    'Margin Bottom' => __('Margin Bottom', 'e2pdf'),
                    'Margin Left' => __('Margin Left', 'e2pdf'),
                    'Margin Right' => __('Margin Right', 'e2pdf'),
                    'Border Color' => __('Border Color', 'e2pdf'),
                    'Border Top' => __('Border Top', 'e2pdf'),
                    'Border Bottom' => __('Border Bottom', 'e2pdf'),
                    'Border Left' => __('Border Left', 'e2pdf'),
                    'Border Right' => __('Border Right', 'e2pdf'),
                    'Field' => __('Field', 'e2pdf'),
                    'Style' => __('Style', 'e2pdf'),
                    'Lock & Hide' => __('Lock & Hide', 'e2pdf'),
                    'Font Color' => __('Font Color', 'e2pdf'),
                    'Font Size' => __('Font Size', 'e2pdf'),
                    'Text Align' => __('Text Align', 'e2pdf'),
                    'Rotation' => __('Rotation', 'e2pdf'),
                    'Preg Replace Pattern' => __('Preg Replace Pattern', 'e2pdf'),
                    'Preg Replace Replacement' => __('Preg Replace Replacement', 'e2pdf'),
                    'Preg Match All Pattern' => __('Preg Match All Pattern', 'e2pdf'),
                    'Preg Match All Output' => __('Preg Match All Output', 'e2pdf'),
                    'Preg Filters' => __('Preg Filters', 'e2pdf'),
                    'Char Spacing' => __('Char Spacing', 'e2pdf'),
                    'Color' => __('Color', 'e2pdf'),
                    'QR Code' => __('QR Code', 'e2pdf'),
                    'Barcode' => __('Barcode', 'e2pdf'),
                    'Format' => __('Format', 'e2pdf'),
                    'Precision' => __('Precision', 'e2pdf'),
                    'L - Smallest' => __('L - Smallest', 'e2pdf'),
                    'M - Medium' => __('M - Medium', 'e2pdf'),
                    'Q - High' => __('Q - High', 'e2pdf'),
                    'H - Best' => __('H - Best', 'e2pdf'),
                    'All Templates for this Website will be deactivated! Are you sure want to continue?' => __('All Templates for this Website will be deactivated! Are you sure want to continue?', 'e2pdf'),
                    'Pre-uploaded PDF will be removed from E2Pdf Template! Are you sure want to continue?' => __('Pre-uploaded PDF will be removed from E2Pdf Template! Are you sure want to continue?', 'e2pdf'),
                    'Quiet Zone Size' => __('Quiet Zone Size', 'e2pdf'),
                    'Hide Label' => __('Hide Label', 'e2pdf'),
                    'Lock' => __('Lock', 'e2pdf'),
                    'Unlock' => __('Unlock', 'e2pdf'),
                    'Opacity' => __('Opacity', 'e2pdf'),
                    'Auto Font Size' => __('Auto Font Size', 'e2pdf'),
                    'Max Filesize' => __('Max Filesize', 'e2pdf'),
                    'The bulk export task will be removed! Are you sure to continue?' => __('The bulk export task will be removed! Are you sure to continue?', 'e2pdf'),
                    'The bulk export task will be stopped! Are you sure to continue?' => __('The bulk export task will be stopped! Are you sure to continue?', 'e2pdf'),
                    'The bulk export task will be started! Are you sure to continue?' => __('The bulk export task will be started! Are you sure to continue?', 'e2pdf'),
                    'Search...' => __('Search...', 'e2pdf'),
                    'Show Element' => __('Show Element', 'e2pdf'),
                    'Hide Element' => __('Hide Element', 'e2pdf'),
                    'Show Page' => __('Show Page', 'e2pdf'),
                    'Hide Page' => __('Hide Page', 'e2pdf'),
                    'Change to' => __('Change to', 'e2pdf'),
                    'Merge' => __('Merge', 'e2pdf'),
                    'Change Property' => __('Change Property', 'e2pdf'),
                    'Format' => __('Format', 'e2pdf'),
                    'Insert Before' => __('Insert Before', 'e2pdf'),
                    'Insert After' => __('Insert After', 'e2pdf'),
                    'Full Replacement' => __('Full Replacement', 'e2pdf'),
                    'Search & Replace' => __('Search & Replace', 'e2pdf'),
                    'Contains' => __('Contains', 'e2pdf'),
                    'Not Contains' => __('Not Contains', 'e2pdf'),
                    'In Array' => __('In Array', 'e2pdf'),
                    'Array Key Exists' => __('Array Key Exists', 'e2pdf'),
                    'Else' => __('Else', 'e2pdf'),
                    'Append' => __('Append', 'e2pdf'),
                    'Vertical' => __('Vertical', 'e2pdf'),
                    'Horizontal' => __('Horizontal', 'e2pdf'),
                    'Palette' => __('Palette', 'e2pdf'),
                    'Line / Stroke Color' => __('Line / Stroke Color', 'e2pdf'),
                    'Graph' => __('Graph', 'e2pdf'),
                    'Markers' => __('Markers', 'e2pdf'),
                    'Grid' => __('Grid', 'e2pdf'),
                    'Axis' => __('Axis', 'e2pdf'),
                    'Legend' => __('Legend', 'e2pdf'),
                    'Structure' => __('Structure', 'e2pdf'),
                    'Space' => __('Space', 'e2pdf'),
                    'Position' => __('Position', 'e2pdf'),
                    'Vertical Label' => __('Vertical Label', 'e2pdf'),
                    'Horizontal Label' => __('Horizontal Label', 'e2pdf'),
                    'Key Separator' => __('Key Separator', 'e2pdf'),
                    'Array Separator' => __('Array Separator', 'e2pdf'),
                    'Sub Array Separator' => __('Sub Array Separator', 'e2pdf'),
                    'Key' => __('Key', 'e2pdf'),
                    'Axis Text' => __('Axis Text', 'e2pdf'),
                    'Legend Text' => __('Legend Text', 'e2pdf'),
                    'Label' => __('Label', 'e2pdf'),
                    'Area' => __('Area', 'e2pdf'),
                    'Structured Data' => __('Structured Data', 'e2pdf'),
                    'Axis Overlap' => __('Axis Overlap', 'e2pdf'),
                    'Grid Spacing' => __('Grid Spacing', 'e2pdf'),
                    'Grid Spacing V' => __('Grid Spacing V', 'e2pdf'),
                    'Grid Spacing H' => __('Grid Spacing H', 'e2pdf'),
                    'Grid Division V' => __('Grid Division V', 'e2pdf'),
                    'Grid Division H' => __('Grid Division H', 'e2pdf'),
                    'Axis Color' => __('Axis Color', 'e2pdf'),
                    'Grid Color' => __('Grid Color', 'e2pdf'),
                    'Bar Label Color' => __('Bar Label Color', 'e2pdf'),
                    'V Position' => __('V Position', 'e2pdf'),
                    'H Position' => __('H Position', 'e2pdf'),
                    'Bar Labels' => __('Bar Labels', 'e2pdf'),
                    'Marker' => __('Marker', 'e2pdf'),
                    'Grid Subdivision Color' => __('Grid Subdivision Color', 'e2pdf'),
                    'Sub Divisions' => __('Sub Divisions', 'e2pdf'),
                    'Axis V' => __('Axis V', 'e2pdf'),
                    'Text V' => __('Text V', 'e2pdf'),
                    'Axis H' => __('Axis H', 'e2pdf'),
                    'Text H' => __('Text H', 'e2pdf'),
                    'Min H' => __('Min H', 'e2pdf'),
                    'Max H' => __('Max H', 'e2pdf'),
                    'Enable' => __('Enable', 'e2pdf'),
                    'Min V' => __('Min V', 'e2pdf'),
                    'Max V' => __('Max V', 'e2pdf'),
                    'Fill Under' => __('Fill Under', 'e2pdf'),
                    'Reverse' => __('Reverse', 'e2pdf'),
                    'Sort' => __('Sort', 'e2pdf'),
                    'Percentage' => __('Percentage', 'e2pdf'),
                    'Legend Text Side' => __('Legend Text Side', 'e2pdf'),
                    'Columns' => __('Columns', 'e2pdf'),
                    'Padding X' => __('Padding X', 'e2pdf'),
                    'Padding Y' => __('Padding Y', 'e2pdf'),
                    'Stroke Color' => __('Stroke Color', 'e2pdf'),
                    'Stroke Width' => __('Stroke Width', 'e2pdf'),
                    'Bubble Scale' => __('Bubble Scale', 'e2pdf'),
                    'Units Y' => __('Units Y', 'e2pdf'),
                    'Units X' => __('Units X', 'e2pdf'),
                    'Increment' => __('Increment', 'e2pdf'),
                    'Stack Group' => __('Stack Group', 'e2pdf'),
                    'Line Dataset' => __('Line Dataset', 'e2pdf'),
                    'Project Angle' => __('Project Angle', 'e2pdf'),
                    'Open' => __('Open', 'e2pdf'),
                    'End' => __('End', 'e2pdf'),
                    'Outliers' => __('Outliers', 'e2pdf'),
                    'Wtop' => __('Wtop', 'e2pdf'),
                    'Wbottom' => __('Wbottom', 'e2pdf'),
                    'High' => __('High', 'e2pdf'),
                    'Low' => __('Low', 'e2pdf'),
                    'Legends' => __('Legends', 'e2pdf'),
                    'Colors' => __('Colors', 'e2pdf'),
                    'Line Curve' => __('Line Curve', 'e2pdf'),
                    'H Margin' => __('H Margin', 'e2pdf'),
                    'V Margin' => __('V Margin', 'e2pdf'),
                    'Units Label' => __('Units Label', 'e2pdf'),
                );
                break;
            case 'params':
                $nonces = array();
                $caps = $this->helper->get_caps();
                foreach ($caps as $cap) {
                    $nonces[$cap['cap']] = current_user_can('manage_options') || current_user_can($cap['cap']) ? wp_create_nonce($cap['cap']) : '';
                }
                $controller_e2pdf_templates = new Controller_E2pdf_Templates();
                $model_e2pdf_extension = new Model_E2pdf_Extension();
                $data = array(
                    'nonce' => $nonces,
                    'plugins_url' => plugins_url('', $this->helper->get('plugin_file_path')),
                    'upload_url' => $this->helper->get_upload_url(),
                    'license_type' => $this->helper->get('license')->get('type'),
                    'upload_max_filesize' => $this->helper->load('files')->get_upload_max_filesize(),
                    'extensions' => $model_e2pdf_extension->extensions(),
                    'template_sizes' => $controller_e2pdf_templates->get_sizes_list(),
                );
                break;
            default:
                break;
        }
        return $data;
    }

    /**
     * Check requirenments before activation
     */
    public function requirenments() {
        if (version_compare(PHP_VERSION, '5.3.3', '<')) {
            throw new Exception(
                            /* translators: %s: PHP Version */
                            sprintf(__('E2Pdf requires PHP version 5.3.3 or later. Your PHP version is %s', 'e2pdf'), PHP_VERSION)
            );
        }

        if (!function_exists('curl_version')) {
            throw new Exception(
                            __('CURL extension is not installed or not enabled in your PHP installation', 'e2pdf')
            );
        }
    }

    public function action_current_screen() {
        $current_screen = get_current_screen();
        if (!$current_screen || !in_array($current_screen->id, $this->e2pdf_admin_pages, false)) {
            return;
        }

        if ($current_screen->id == 'e2pdf_page_e2pdf-templates' && !isset($_GET['action'])) {
            $screen_option = array(
                'label' => __('Templates per page', 'e2pdf') . ':',
                'default' => get_option('e2pdf_templates_screen_per_page') ? get_option('e2pdf_templates_screen_per_page') : '20',
                'option' => 'e2pdf_templates_screen_per_page',
            );
            add_screen_option('per_page', $screen_option);
        }

        $this->helper->set('license', new Model_E2pdf_License());
        if ($this->helper->get('license')->get('error') === 'Site Url Not Found. Please try to "Reactivate" plugin.') {
            update_option('e2pdf_version', '1.00.00');
            $this->action_plugins_loaded();
            $this->helper->set('license', new Model_E2pdf_License());
        }
    }

    public function action_wp_loaded() {
        if (get_option('e2pdf_adobesign_refresh_token') && !get_transient('e2pdf_adobesign_refresh_token')) {
            new Model_E2pdf_AdobeSign();
        }
    }

    /**
     * On plugin activation
     */
    public function activate($network = false) {
        global $wpdb;

        try {
            $this->requirenments();
            if (is_multisite() && $network) {
                foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
                    $this->activate_site($blog_id);
                }
            } else {
                $this->activate_site();
            }
        } catch (Exception $e) {
            echo '<div style="line-height: 70px;">';
            echo $e->getMessage();
            echo '</div>';
            exit();
        }
    }

    /**
     * On plugin deactivation
     */
    public function deactivate($network = false) {
        global $wpdb;
        if (is_multisite() && $network) {
            foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
                $this->activate_site($blog_id);
            }
        } else {
            $this->deactivate_site();
        }
    }

    public function action_wpmu_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta) {
        if (is_plugin_active_for_network('e2pdf/e2pdf.php')) {
            $this->activate_site($blog_id);
        }
    }

    public function activate_site($blog_id = false) {
        global $wpdb;

        $db_prefix = $wpdb->prefix;

        if ($blog_id) {
            switch_to_blog($blog_id);
            $db_prefix = $wpdb->get_blog_prefix($blog_id);
            if (!is_main_site($blog_id)) {
                $this->helper->set('upload_dir', $this->helper->get_wp_upload_dir('basedir') . '/e2pdf/');
                $this->helper->set('tmp_dir', $this->helper->get('upload_dir') . 'tmp/');
                $this->helper->set('cache_dir', $this->helper->get('upload_dir') . 'tmp/cache/');
                $this->helper->set('pdf_dir', $this->helper->get('upload_dir') . 'pdf/');
                $this->helper->set('fonts_dir', $this->helper->get('upload_dir') . 'fonts/');
                $this->helper->set('tpl_dir', $this->helper->get('upload_dir') . 'tpl/');
                $this->helper->set('viewer_dir', $this->helper->get('upload_dir') . 'viewer/');
                $this->helper->set('bulk_dir', $this->helper->get('upload_dir') . 'bulks/');
                $this->helper->set('wpcf7_dir', $this->helper->get('upload_dir') . 'wpcf7/');
            }
        }

        $dirs = array(
            $this->helper->get('upload_dir'),
            $this->helper->get('tmp_dir'),
            $this->helper->get('cache_dir'),
            $this->helper->get('pdf_dir'),
            $this->helper->get('fonts_dir'),
            $this->helper->get('tpl_dir'),
            $this->helper->get('viewer_dir'),
            $this->helper->get('bulk_dir'),
            $this->helper->get('wpcf7_dir'),
        );

        if (!is_main_site($blog_id)) {
            array_unshift($dirs, $this->helper->get_wp_upload_dir('basedir'));
        }

        foreach ($dirs as $dir) {
            if ($this->helper->create_dir($dir)) {
                if ($dir == $this->helper->get('fonts_dir')) {
                    if (!file_exists($this->helper->get('fonts_dir') . 'NotoSans-Regular.ttf') || get_option('e2pdf_version') < '1.10.05') {
                        copy($this->helper->get('plugin_dir') . 'data/fonts/NotoSans-Regular.ttf', $this->helper->get('fonts_dir') . 'NotoSans-Regular.ttf');
                    }
                } elseif ($dir == $this->helper->get('viewer_dir')) {
                    if (!file_exists($this->helper->get('viewer_dir') . 'style.css')) {
                        $this->helper->create_file($this->helper->get('viewer_dir') . 'style.css');
                    }
                } elseif ($dir == $this->helper->get('bulk_dir') || $dir == $this->helper->get('cache_dir')) {
                    $htaccess = $dir . '.htaccess';
                    if (!file_exists($htaccess)) {
                        $this->helper->create_file($htaccess, "DENY FROM ALL");
                    }
                }
            } else {
                throw new Exception(
                                /* translators: %s: directory */
                                sprintf(__("Can't create folder %s", 'e2pdf'), $dir)
                );
            }
        }

        $this->helper->load('db')->db_init($db_prefix);

        if (get_option('e2pdf_version') !== $this->helper->get('version')) {
            update_option('e2pdf_version', $this->helper->get('version'));
        }

        if (get_option('e2pdf_nonce_key') === false) {
            if (function_exists('wp_generate_password')) {
                update_option('e2pdf_nonce_key', wp_generate_password('64'));
            }
        }

        if (class_exists('TRP_Translate_Press')) {
            if (get_option('e2pdf_pdf_translation') === false && get_option('e2pdf_translatepress_translation') !== false) {
                update_option('e2pdf_pdf_translation', get_option('e2pdf_translatepress_translation'));
            }
        }

        if (get_option('e2pdf_wc_invoice_template_id') !== false) {
            update_option('e2pdf_wc_my_orders_actions_template_id', get_option('e2pdf_wc_invoice_template_id'));
        }
        if (get_option('e2pdf_wc_invoice_statuses') !== false) {
            update_option('e2pdf_wc_my_orders_actions_template_id_status', get_option('e2pdf_wc_invoice_statuses'));
        }
        if (get_option('e2pdf_wc_checkout_template_id_order') !== false) {
            update_option('e2pdf_wc_checkout_template_id_priority', get_option('e2pdf_wc_checkout_template_id_order'));
        }
        if (get_option('e2pdf_wc_cart_template_id_order') !== false) {
            update_option('e2pdf_wc_cart_template_id_priority', get_option('e2pdf_wc_cart_template_id_order'));
        }
        if (get_option('e2pdf_version') && get_option('e2pdf_version') < '1.16.14' && get_option('e2pdf_wc_cart_template_id') && get_option('e2pdf_wc_cart_template_id_priority') === false) {
            update_option('e2pdf_wc_cart_template_id_priority', '99');
        }

        delete_option('e2pdf_developer');
        delete_option('e2pdf_developer_ips');
        delete_option('e2pdf_translatepress_translation');
        delete_option('e2pdf_wc_invoice_template_id');
        delete_option('e2pdf_wc_invoice_statuses');
        delete_option('e2pdf_wc_checkout_template_id_order');
        delete_option('e2pdf_wc_cart_template_id_order');

        $model_e2pdf_api = new Model_E2pdf_Api();
        $model_e2pdf_api->set(
                array(
                    'action' => 'common/activate',
                )
        );
        $model_e2pdf_api->request();

        $model_e2pdf_license = new Model_E2pdf_License();
        $model_e2pdf_license->load_templates();

        wp_clear_scheduled_hook('e2pdf_cronjob');

        if ($blog_id) {
            restore_current_blog();
            $this->helper->set('upload_dir', $this->helper->get_wp_upload_dir('basedir') . '/e2pdf/');
            $this->helper->set('tmp_dir', $this->helper->get('upload_dir') . 'tmp/');
            $this->helper->set('cache_dir', $this->helper->get('upload_dir') . 'tmp/cache/');
            $this->helper->set('pdf_dir', $this->helper->get('upload_dir') . 'pdf/');
            $this->helper->set('fonts_dir', $this->helper->get('upload_dir') . 'fonts/');
            $this->helper->set('tpl_dir', $this->helper->get('upload_dir') . 'tpl/');
            $this->helper->set('viewer_dir', $this->helper->get('upload_dir') . 'viewer/');
            $this->helper->set('bulk_dir', $this->helper->get('upload_dir') . 'bulks/');
            $this->helper->set('wpcf7_dir', $this->helper->get('upload_dir') . 'wpcf7/');
        }
    }

    public function deactivate_site($blog_id = false) {
        if ($blog_id) {
            switch_to_blog($blog_id);
        }
        wp_clear_scheduled_hook('e2pdf_bulk_export_cron');
        wp_clear_scheduled_hook('e2pdf_cache_pdfs_cron');
        if ($blog_id) {
            restore_current_blog();
        }
    }

    /**
     * On plugin uninstall
     */
    public static function uninstall() {
        global $wpdb;

        if (is_multisite()) {
            foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
                self::uninstall_site($blog_id);
            }
        } else {
            self::uninstall_site();
        }
    }

    public static function uninstall_site($blog_id = false) {
        global $wpdb;

        $db_prefix = $wpdb->prefix;
        $helper_e2pdf_helper = Helper_E2pdf_Helper::instance();

        if ($blog_id) {
            switch_to_blog($blog_id);
            $db_prefix = $wpdb->get_blog_prefix($blog_id);
            if (!is_main_site($blog_id)) {
                $helper_e2pdf_helper->set('upload_dir', $helper_e2pdf_helper->get_wp_upload_dir('basedir') . '/e2pdf/');
            }
        }

        wp_clear_scheduled_hook('e2pdf_bulk_export_cron');
        wp_clear_scheduled_hook('e2pdf_cache_pdfs_cron');

        $model_e2pdf_api = new Model_E2pdf_Api();
        $model_e2pdf_api->set(
                array(
                    'action' => 'common/uninstall',
                )
        );
        $model_e2pdf_api->request();

        $options = Model_E2pdf_Options::get_options();
        foreach ($options as $option_key => $option_value) {
            delete_option($option_key);
        }

        $wpdb->query('DROP TABLE IF EXISTS `' . $db_prefix . 'e2pdf_templates' . '`');
        $wpdb->query('DROP TABLE IF EXISTS `' . $db_prefix . 'e2pdf_entries' . '`');
        $wpdb->query('DROP TABLE IF EXISTS `' . $db_prefix . 'e2pdf_datasets' . '`');
        $wpdb->query('DROP TABLE IF EXISTS `' . $db_prefix . 'e2pdf_pages' . '`');
        $wpdb->query('DROP TABLE IF EXISTS `' . $db_prefix . 'e2pdf_elements' . '`');
        $wpdb->query('DROP TABLE IF EXISTS `' . $db_prefix . 'e2pdf_revisions' . '`');

        $wpdb->query($wpdb->prepare('DELETE FROM `' . $db_prefix . 'options' . '` WHERE option_name LIKE %s OR option_name LIKE %s', '_transient_e2pdf_%', '_transient_timeout_e2pdf_%'));

        $helper_e2pdf_helper->delete_dir($helper_e2pdf_helper->get('upload_dir'));

        $caps = $helper_e2pdf_helper->get_caps();
        $roles = wp_roles()->get_names();
        foreach ($roles as $role_key => $sub_role) {
            $role = get_role($role_key);
            foreach ($caps as $cap_key => $cap) {
                $role->remove_cap($cap_key);
            }
        }

        if ($blog_id) {
            restore_current_blog();
            $helper_e2pdf_helper->set('upload_dir', $helper_e2pdf_helper->get_wp_upload_dir('basedir') . '/e2pdf/');
        }
    }
}
