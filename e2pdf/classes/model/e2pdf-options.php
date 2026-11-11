<?php

/**
 * File: /model/e2pdf-options.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_Options extends Model_E2pdf_Model {

    // get options
    public static function get_options($all = true, $only_group = array(), $exclude = false) {
        $helper = Helper_E2pdf_Helper::instance();
        $extensions_options = array();
        if ($all) {
            $extensions_options[] = array(
                'key' => 'e2pdf_disabled_extensions',
                'value' => get_option('e2pdf_disabled_extensions', array()),
            );
        } else {
            $model_e2pdf_extension = new Model_E2pdf_Extension();
            $extensions = $model_e2pdf_extension->extensions(false);
            $disabled_extensions = get_option('e2pdf_disabled_extensions', array());
            foreach ($extensions as $extension) {
                $extension_title = ucfirst($extension);
                switch ($extension) {
                    case 'caldera':
                        $extension_title = 'Caldera Forms';
                        break;
                    case 'calc':
                        $extension_title = 'Cost Calculator Builder';
                        break;
                    case 'divi':
                        $extension_title = 'Divi Contact Forms';
                        break;
                    case 'elementor':
                        $extension_title = 'Elementor Forms';
                        break;
                    case 'fluent':
                        $extension_title = 'Fluent Forms';
                        break;
                    case 'formidable':
                        $extension_title = 'Formidable Forms';
                        break;
                    case 'forminator':
                        $extension_title = 'Forminator Forms';
                        break;
                    case 'gravity':
                        $extension_title = 'Gravity Forms';
                        break;
                    case 'ninja':
                        $extension_title = 'Ninja Forms';
                        break;
                    case 'woocommerce':
                        $extension_title = 'WooCommerce';
                        break;
                    // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
                    case 'wordpress':
                        $extension_title = 'WordPress';
                        break;
                    case 'wpcf7':
                        $extension_title = 'Contact Form 7';
                        break;
                    case 'wpforms':
                        $extension_title = 'WPForms';
                        break;
                    case 'jetformbuilder':
                        $extension_title = 'JetFormBuilder';
                        break;
                    case 'sureforms':
                        $extension_title = 'SureForms';
                        break;
                    default:
                        break;
                }
                $extensions_options[] = array(
                    'name' => $extension_title,
                    'key' => 'e2pdf_disabled_extensions[]',
                    'value' => is_array($disabled_extensions) && in_array($extension, $disabled_extensions, true) ? $extension : '',
                    'default_value' => '',
                    'type' => 'select',
                    'options' => array(
                        array(
                            'key' => '',
                            'value' => __('Enabled', 'e2pdf'),
                        ),
                        array(
                            'key' => $extension,
                            'value' => __('Disabled', 'e2pdf'),
                        ),
                    ),
                );
            }
            if (isset($extensions_options['0'])) {
                $extensions_options['0']['header'] = __('Extensions', 'e2pdf');
            }
        }

        $mcp_users = array(
            '' => __('--- Select ---', 'e2pdf')
        );
        if (get_option('e2pdf_mcp_user_id')) {
            $mcp_user = get_userdata(get_option('e2pdf_mcp_user_id'));
            if ($mcp_user) {
                $mcp_users[$mcp_user->ID] = $mcp_user->user_login;
            }
        }
        if (in_array('mcp_group', $only_group) && current_user_can('manage_options')) {
            foreach (array('manage_options', 'e2pdf', 'e2pdf_templates') as $cap) {
                $users = get_users(array(
                    'capability' => $cap,
                    'fields' => array('ID', 'user_login'),
                ));
                foreach ($users as $user) {
                    $mcp_users[$user->ID] = $user->user_login;
                }
            }
        }

        $processors = array(
            '0' => 'Default (Stable Version)',
        );
        if (get_option('e2pdf_debug', '0')) {
            $processors = array(
                '0' => 'Default (Stable Version)',
                '2' => 'Backport (1.16.19 Version)',
                '1' => 'Release Candidate (Debug Mode)',
            );
        }

        $options = array(
            'static_group' => array(
                'name' => '',
                'options' => array(
                    array(
                        'key' => 'e2pdf_version',
                        'value' => $helper->get('version'),
                    ),
                    array(
                        'key' => 'e2pdf_license',
                        'value' => get_option('e2pdf_license', false),
                    ),
                    array(
                        'key' => 'e2pdf_email',
                        'value' => get_option('e2pdf_email', ''),
                    ),
                    array(
                        'key' => 'e2pdf_templates_screen_per_page',
                        'value' => get_option('e2pdf_templates_screen_per_page', '20'),
                    ),
                    array(
                        'key' => 'e2pdf_cached_fonts',
                        'value' => get_option('e2pdf_cached_fonts', array()),
                    ),
                    array(
                        'key' => 'e2pdf_nonce_key',
                        'value' => get_option('e2pdf_nonce_key', wp_generate_password('64')),
                    ),
                ),
            ),
            'common_group' => array(
                'name' => __('Common', 'e2pdf'),
                'options' => array(
                    array(
                        'name' => __('E2Pdf.com Account E-mail Address', 'e2pdf'),
                        'key' => 'e2pdf_user_email',
                        'default_value' => '',
                        'value' => get_option('e2pdf_user_email', ''),
                        'type' => 'text',
                        'placeholder' => __('E2Pdf.com Account E-mail Address', 'e2pdf'),
                    ),
                    array(
                        'name' => __('API Server', 'e2pdf'),
                        'key' => 'e2pdf_api_custom',
                        'value' => apply_filters('e2pdf_api', get_option('e2pdf_api', 'api.e2pdf.com')),
                        'default_value' => 'api.e2pdf.com',
                        'type' => get_option('e2pdf_api', 'api.e2pdf.com') !== apply_filters('e2pdf_api', get_option('e2pdf_api', 'api.e2pdf.com')) ? 'text' : 'hidden',
                        'readonly' => 'readonly',
                    ),
                    array(
                        'name' => __('API Server', 'e2pdf'),
                        'key' => 'e2pdf_api',
                        'value' => get_option('e2pdf_api', 'api.e2pdf.com'),
                        'default_value' => 'api.e2pdf.com',
                        'type' => get_option('e2pdf_api', 'api.e2pdf.com') !== apply_filters('e2pdf_api', get_option('e2pdf_api', 'api.e2pdf.com')) ? 'hidden' : 'select',
                        'options' => array(
                            'api.e2pdf.com' => 'api.e2pdf.com (US, Cloudflare)',
                            'api2.e2pdf.com' => 'api2.e2pdf.com (US)',
                            'api3.e2pdf.com' => 'api3.e2pdf.com (EU, Cloudflare)',
                            'api4.e2pdf.com' => 'api4.e2pdf.com (EU)',
                        ),
                    ),
                    array(
                        'name' => '',
                        'key' => 'e2pdf_ssl_verify',
                        'value' => get_option('e2pdf_ssl_verify', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Verify SSL Certificate', 'e2pdf'),
                    ),
                    array(
                        'name' => __('API Transfer', 'e2pdf'),
                        'key' => 'e2pdf_api_protocol',
                        'value' => get_option('e2pdf_api_protocol', '0'),
                        'default_value' => '0',
                        'type' => 'select',
                        'options' => array(
                            '0' => 'Base64 Encoding',
                            '1' => 'Raw Encoding',
                        ),
                    ),
                    array(
                        'name' => __('API Connection Timout (sec)', 'e2pdf'),
                        'key' => 'e2pdf_connection_timeout',
                        'value' => get_option('e2pdf_connection_timeout', '300'),
                        'default_value' => '300',
                        'type' => 'text',
                        'class' => 'e2pdf-numbers',
                        'placeholder' => '0',
                    ),
                    array(
                        'name' => __('PDF Processor', 'e2pdf'),
                        'key' => 'e2pdf_processor',
                        'value' => get_option('e2pdf_processor', '0'),
                        'default_value' => '0',
                        'type' => 'select',
                        'options' => $processors,
                    ),
                    array(
                        'name' => __('Font Processor', 'e2pdf'),
                        'key' => 'e2pdf_font_processor',
                        'value' => get_option('e2pdf_font_processor', '0'),
                        'default_value' => '0',
                        'type' => 'hidden',
                        'options' => array(
                            'Plain Fonts',
                            'Complex Fonts',
                        ),
                    ),
                    array(
                        'name' => __('Release Candidate Builds', 'e2pdf'),
                        'key' => 'e2pdf_dev_update',
                        'value' => get_option('e2pdf_dev_update', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Update from E2Pdf.com', 'e2pdf'),
                    ),
                    array(
                        'name' => __('URL Format', 'e2pdf'),
                        'key' => 'e2pdf_url_format',
                        'value' => get_option('e2pdf_url_format', 'auto'),
                        'default_value' => 'auto',
                        'type' => 'select',
                        'options' => array(
                            'auto' => 'Auto',
                            'siteurl' => 'WordPress Address (URL)',
                            'home' => 'Site Address (URL)',
                        ),
                    ),
                    array(
                        'name' => __('URL Rewrite', 'e2pdf'),
                        'key' => 'e2pdf_mod_rewrite',
                        'value' => get_option('e2pdf_mod_rewrite', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('URL Rewrite', 'e2pdf'),
                    ),
                    array(
                        'name' => '',
                        'key' => 'e2pdf_mod_rewrite_url',
                        'default_value' => '',
                        'value' => get_option('e2pdf_mod_rewrite_url', 'e2pdf/%uid%/'),
                        'type' => 'text',
                        'class' => 'e2pdf-mod-rewrite-url',
                        'placeholder' => 'e2pdf/%uid%/',
                        'prefield' => rtrim($helper->get_frontend_site_url(), '/') . '/',
                    ),
                    array(
                        'name' => __('Cache', 'e2pdf'),
                        'key' => 'e2pdf_cache',
                        'value' => get_option('e2pdf_cache', '1'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Objects Cache', 'e2pdf'),
                    ),
                    array(
                        'name' => '',
                        'key' => 'e2pdf_cache_fonts',
                        'value' => get_option('e2pdf_cache_fonts', '1'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Fonts Cache', 'e2pdf'),
                    ),
                    array(
                        'name' => '',
                        'key' => 'e2pdf_cache_pdfs',
                        'value' => get_option('e2pdf_cache_pdfs', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('PDFs Cache', 'e2pdf'),
                    ),
                    array(
                        'name' => '',
                        'key' => 'e2pdf_cache_pdfs_ttl',
                        'default_value' => '',
                        'value' => max(10, (int) get_option('e2pdf_cache_pdfs_ttl', '180')),
                        'type' => 'text',
                        'class' => 'e2pdf-numbers',
                        'placeholder' => '10',
                        'prefield' => __('TTL (sec)', 'e2pdf') . ':',
                    ),
                    array(
                        'name' => __('PDF Ajax Loader', 'e2pdf'),
                        'key' => 'e2pdf_download_loader',
                        'value' => get_option('e2pdf_download_loader', '0'),
                        'default_value' => '0',
                        'type' => 'select',
                        'options' => array(
                            '0' => __('Disabled', 'e2pdf'),
                            '1' => __('Enabled', 'e2pdf'),
                        ),
                    ),
                    array(
                        'name' => __('Fallback PDF Viewer', 'e2pdf'),
                        'key' => 'e2pdf_download_inline_fallback_viewer',
                        'value' => get_option('e2pdf_download_inline_fallback_viewer', '0'),
                        'default_value' => '0',
                        'type' => 'select',
                        'options' => array(
                            '0' => __('Disabled', 'e2pdf'),
                            '1' => 'PDF.js',
                        ),
                    ),
                    array(
                        'name' => '',
                        'key' => 'e2pdf_download_inline_chrome_ios_fix',
                        'value' => get_option('e2pdf_download_inline_chrome_ios_fix', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Fix Chrome iOS Download Name (might be not compatible with certain setups)', 'e2pdf'),
                    ),
                    array(
                        'name' => __('Hide Warnings', 'e2pdf'),
                        'key' => 'e2pdf_hide_warnings',
                        'value' => get_option('e2pdf_hide_warnings', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Hide Warnings', 'e2pdf'),
                    ),
                    array(
                        'name' => __('Local Images', 'e2pdf'),
                        'key' => 'e2pdf_images_remote_request',
                        'value' => get_option('e2pdf_images_remote_request', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Load via Remote Request', 'e2pdf'),
                    ),
                    array(
                        'name' => __('Images Timout (sec)', 'e2pdf'),
                        'key' => 'e2pdf_images_timeout',
                        'value' => get_option('e2pdf_images_timeout', '30'),
                        'default_value' => '30',
                        'type' => 'text',
                        'class' => 'e2pdf-numbers',
                        'placeholder' => '0',
                    ),
                    array(
                        'name' => __('Bulk Export Interval (sec)', 'e2pdf'),
                        'key' => 'e2pdf_bulk_export_interval',
                        'value' => max(10, (int) get_option('e2pdf_bulk_export_interval', '10')),
                        'default_value' => '10',
                        'type' => 'text',
                        'class' => 'e2pdf-numbers',
                        'placeholder' => '10',
                    ),
                    array(
                        'name' => __('Undo Limit', 'e2pdf'),
                        'key' => 'e2pdf_undo_limit',
                        'value' => get_option('e2pdf_undo_limit', '20'),
                        'default_value' => '20',
                        'type' => 'text',
                        'class' => 'e2pdf-numbers',
                        'placeholder' => '0',
                    ),
                    array(
                        'name' => __('Revisions Limit', 'e2pdf'),
                        'key' => 'e2pdf_revisions_limit',
                        'value' => get_option('e2pdf_revisions_limit', '3'),
                        'default_value' => '3',
                        'type' => 'text',
                        'class' => 'e2pdf-numbers',
                        'placeholder' => '0',
                    ),
                    array(
                        'name' => __('Uninstall', 'e2pdf'),
                        'key' => 'e2pdf_uninstall_data',
                        'value' => get_option('e2pdf_uninstall_data', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Remove All Data on Plugin Uninstall', 'e2pdf'),
                        'cap' => 'manage_options',
                    ),
                    array(
                        'name' => __('Debug Mode', 'e2pdf'),
                        'key' => 'e2pdf_debug',
                        'value' => get_option('e2pdf_debug', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Enable Debug Mode', 'e2pdf'),
                    ),
                    array(
                        'name' => __('Recovery Mode E-mail', 'e2pdf'),
                        'key' => 'e2pdf_recovery_mode_email',
                        'value' => get_option('e2pdf_recovery_mode_email', ''),
                        'default_value' => '',
                        'type' => get_option('e2pdf_debug', '0') ? 'text' : 'hidden',
                        'placeholder' => __('Recovery Mode E-mail', 'e2pdf'),
                    ),
                    array(
                        'name' => __('Memory/Time Usage', 'e2pdf'),
                        'key' => 'e2pdf_memory_time',
                        'value' => get_option('e2pdf_memory_time', '0'),
                        'default_value' => '0',
                        'type' => get_option('e2pdf_debug', '0') ? 'checkbox' : 'hidden',
                        'checkbox_value' => '1',
                        'placeholder' => __('Show Memory/Time Usage', 'e2pdf'),
                    ),
                ),
            ),
            'maintenance_group' => array(
                'name' => __('Maintenance', 'e2pdf'),
                'action' => 'maintenance',
                'options' => array(),
            ),
            'fonts_group' => array(
                'name' => __('Fonts', 'e2pdf'),
                'action' => 'fonts',
                'options' => array(),
            ),
            'permissions_group' => array(
                'name' => __('Permissions', 'e2pdf'),
                'action' => 'permissions',
                'options' => array(
                    array(
                        'name' => __('Shortcodes', 'e2pdf'),
                        'key' => 'e2pdf_access_check',
                        'value' => get_option('e2pdf_access_check', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Access Check', 'e2pdf'),
                        'cap' => 'manage_options',
                    ),
                    array(
                        'name' => __('Writable Folders', 'e2pdf'),
                        'key' => 'e2pdf_write_dirs',
                        'value' => get_option('e2pdf_write_dirs', ''),
                        'default_value' => '',
                        'type' => 'textarea',
                        'placeholder' => __('One folder per line, relative or absolute, e.g. wp-content/uploads/invoices', 'e2pdf'),
                        /* translators: 1: Attribute 2: Shortcode 3: WordPress root path */
                        'description' => sprintf(__('For %1$s in %2$s. Relative paths start from the WordPress root: %3$s', 'e2pdf'), '<strong>dir</strong>', '[e2pdf-save]', '<strong>' . esc_html(ABSPATH) . '</strong>'),
                    ),
                    array(
                        'name' => __('Readable Folders', 'e2pdf'),
                        'key' => 'e2pdf_read_dirs',
                        'value' => get_option('e2pdf_read_dirs', ''),
                        'default_value' => '',
                        'type' => 'textarea',
                        'placeholder' => __('One folder per line, relative or absolute, e.g. wp-content/uploads/documents', 'e2pdf'),
                        /* translators: 1: Attribute 2: Attribute 3: WordPress root path */
                        'description' => sprintf(__('For %1$s and %2$s. Relative paths start from the WordPress root: %3$s', 'e2pdf'), '<strong>pdf</strong>', '<strong>dpdf</strong>', '<strong>' . esc_html(ABSPATH) . '</strong>'),
                    ),
                    array(
                        'name' => __('Allowed URLs', 'e2pdf'),
                        'key' => 'e2pdf_allowed_urls',
                        'value' => get_option('e2pdf_allowed_urls', ''),
                        'default_value' => '',
                        'type' => 'textarea',
                        'placeholder' => __('One URL per line, e.g. https://cdn.example.com/pdf/', 'e2pdf'),
                        /* translators: 1: Attribute 2: Attribute 3: Attribute */
                        'description' => sprintf(__('For %1$s, %2$s and %3$s.', 'e2pdf'), '<strong>pdf</strong>', '<strong>site_url</strong>', '<strong>viewer</strong>'),
                    ),
                ),
            ),
            'zapier_group' => array(
                'name' => 'Zapier',
                'options' => array(
                    array(
                        'name' => 'Site URL',
                        'value' => $helper->get_site_url(),
                        'default_value' => '',
                        'type' => 'text',
                        'readonly' => 'readonly',
                        'class' => 'e2pdf-copy-field',
                    ),
                    array(
                        'name' => 'API Key',
                        'key' => 'e2pdf_zapier_api_key',
                        'value' => get_option('e2pdf_zapier_api_key'),
                        'default_value' => '',
                        'type' => 'text',
                        'readonly' => 'readonly',
                        'class' => 'e2pdf-copy-field',
                        'cap' => 'manage_options',
                    ),
                    array(
                        'name' => '',
                        'key' => 'e2pdf_zapier_regenerate_api_key',
                        'value' => '0',
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Regenerate API Key', 'e2pdf'),
                        'cap' => 'manage_options',
                    ),
                    array(
                        'name' => __('Webhooks', 'e2pdf'),
                        'key' => 'e2pdf_zapier_webhooks',
                        'value' => get_option('e2pdf_zapier_webhooks', ''),
                        'default_value' => '',
                        'type' => 'textarea',
                        'placeholder' => __('Allowed Zapier webhook URLs, one per line', 'e2pdf'),
                    ),
                ),
            ),
            'mcp_group' => array(
                'name' => 'AI MCP',
                'action' => 'mcp',
                'options' => array(
                    array(
                        'name' => __('MCP Server', 'e2pdf'),
                        'key' => 'e2pdf_mcp',
                        'value' => get_option('e2pdf_mcp', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Enabled', 'e2pdf'),
                        'cap' => 'manage_options',
                    ),
                    array(
                        'name' => __('Connection Name', 'e2pdf'),
                        'key' => 'e2pdf_mcp_connection_name',
                        'value' => 'E2Pdf (' . wp_parse_url(site_url(), PHP_URL_HOST) . ')',
                        'default_value' => '',
                        'type' => 'text',
                        'readonly' => 'readonly',
                        'class' => 'e2pdf-copy-field',
                        'cap' => 'manage_options',
                    ),
                    array(
                        'name' => __('Server URL', 'e2pdf'),
                        'key' => 'e2pdf_mcp_api_url',
                        'value' => site_url('/e2pdf-rpc/v1/mcp/' . get_option('e2pdf_mcp_api_url', '')),
                        'default_value' => '',
                        'type' => 'text',
                        'readonly' => 'readonly',
                        'class' => 'e2pdf-copy-field',
                        'cap' => 'manage_options',
                    ),
                    array(
                        'name' => 'Authorization',
                        'key' => 'e2pdf_mcp_api_key',
                        'value' => 'Bearer ' . get_option('e2pdf_mcp_api_key', ''),
                        'default_value' => '',
                        'type' => 'text',
                        'readonly' => 'readonly',
                        'class' => 'e2pdf-copy-field',
                        'cap' => 'manage_options',
                    ),
                    array(
                        'name' => '',
                        'key' => 'e2pdf_mcp_regenerate_api_key',
                        'value' => '0',
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Regenerate API Key', 'e2pdf'),
                        'cap' => 'manage_options',
                    ),
                    array(
                        'name' => __('Run As User', 'e2pdf'),
                        'key' => 'e2pdf_mcp_user_id',
                        'value' => get_option('e2pdf_mcp_user_id', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => $mcp_users,
                        'cap' => 'manage_options',
                    ),
                ),
            ),
            'gdrive_group' => array(
                'name' => 'Google Drive',
                'action' => 'gdrive',
                'cap' => 'manage_options',
                'options' => array(
                    array(
                        'name' => 'Redirect URI',
                        'value' =>
                        site_url('/e2pdf-rpc/v1/gdrive/auth?api_key=' . get_option('e2pdf_gdrive_api_key')),
                        'default_value' => '',
                        'type' => 'text',
                        'readonly' => 'readonly',
                        'class' => 'e2pdf-copy-field',
                    ),
                    array(
                        'name' => 'Status',
                        'key' => 'e2pdf_gdrive_status',
                        'value' => get_option('e2pdf_gdrive_refresh_token') ? __('Authorized', 'e2pdf') : __('Not Authorized', 'e2pdf'),
                        'default_value' => '',
                        'type' => 'text',
                        'readonly' => 'readonly',
                    ),
                    array(
                        'name' => 'Refresh Token',
                        'key' => 'e2pdf_gdrive_refresh_token',
                        'value' => get_option('e2pdf_gdrive_refresh_token', ''),
                        'default_value' => '',
                        'type' => 'text',
                        'placeholder' => '',
                        'type' => get_option('e2pdf_debug', '0') ? 'text' : 'hidden',
                        'readonly' => 'readonly',
                    ),
                    array(
                        'name' => 'Access Token',
                        'key' => 'e2pdf_gdrive_access_token',
                        'value' => get_transient('e2pdf_gdrive_access_token') === false ? '' : get_transient('e2pdf_gdrive_access_token'),
                        'default_value' => '',
                        'type' => get_option('e2pdf_debug', '0') ? 'text' : 'hidden',
                        'placeholder' => '',
                        'readonly' => 'readonly',
                    ),
                    array(
                        'header' => 'Configuration',
                        'name' => 'Client ID',
                        'key' => 'e2pdf_gdrive_client_id',
                        'value' => get_option('e2pdf_gdrive_client_id', ''),
                        'default_value' => '',
                        'type' => 'text',
                        'placeholder' => '',
                    ),
                    array(
                        'name' => 'Client Secret',
                        'key' => 'e2pdf_gdrive_client_secret',
                        'value' => get_option('e2pdf_gdrive_client_secret', ''),
                        'default_value' => '',
                        'type' => 'password',
                        'placeholder' => '',
                    ),
                )
            ),
            'adobesign_group' => array(
                'name' => 'Adobe Sign',
                'action' => 'adobesign',
                'cap' => 'manage_options',
                'options' => array(
                    array(
                        'name' => 'Redirect URI',
                        'value' =>
                        get_option('e2pdf_adobe_api_version') == '1' ? site_url('/e2pdf-rpc/v1/adobe/auth?api_key=' . get_option('e2pdf_adobe_api_key')) : $helper->get_url(
                                array(
                                    'page' => 'e2pdf-settings',
                                    'action' => 'adobesign',
                                )
                        ),
                        'default_value' => '',
                        'type' => 'text',
                        'readonly' => 'readonly',
                        'class' => 'e2pdf-copy-field',
                    ),
                    array(
                        'name' => 'API Version',
                        'value' => get_option('e2pdf_adobe_api_version'),
                        'default_value' => '',
                        'type' => 'hidden',
                    ),
                    array(
                        'name' => 'Status',
                        'key' => 'e2pdf_adobesign_status',
                        'value' => get_option('e2pdf_adobesign_refresh_token') ? __('Authorized', 'e2pdf') : __('Not Authorized', 'e2pdf'),
                        'default_value' => '',
                        'type' => 'text',
                        'readonly' => 'readonly',
                    ),
                    array(
                        'header' => 'Configuration',
                        'name' => 'Client ID',
                        'key' => 'e2pdf_adobesign_client_id',
                        'value' => get_option('e2pdf_adobesign_client_id') === false ? '' : get_option('e2pdf_adobesign_client_id'),
                        'default_value' => '',
                        'type' => 'text',
                        'placeholder' => '',
                    ),
                    array(
                        'name' => 'Client Secret',
                        'key' => 'e2pdf_adobesign_client_secret',
                        'value' => get_option('e2pdf_adobesign_client_secret') === false ? '' : get_option('e2pdf_adobesign_client_secret'),
                        'default_value' => '',
                        'type' => 'password',
                        'placeholder' => '',
                    ),
                    array(
                        'name' => 'Region',
                        'key' => 'e2pdf_adobesign_region',
                        'value' => get_option('e2pdf_adobesign_region') === false ? '' : get_option('e2pdf_adobesign_region'),
                        'default_value' => '',
                        'type' => 'text',
                        'placeholder' => 'na2',
                    ),
                    array(
                        'name' => 'Code',
                        'key' => 'e2pdf_adobesign_code',
                        'value' => get_option('e2pdf_adobesign_code') === false ? '' : get_option('e2pdf_adobesign_code'),
                        'default_value' => '',
                        'type' => get_option('e2pdf_debug', '0') ? 'text' : 'hidden',
                        'placeholder' => '',
                        'readonly' => 'readonly',
                    ),
                    array(
                        'name' => 'Web Access Point',
                        'key' => 'e2pdf_adobesign_web_access_point',
                        'value' => get_option('e2pdf_adobesign_web_access_point') === false ? '' : get_option('e2pdf_adobesign_web_access_point'),
                        'default_value' => '',
                        'type' => get_option('e2pdf_debug', '0') ? 'text' : 'hidden',
                        'placeholder' => '',
                        'readonly' => 'readonly',
                    ),
                    array(
                        'name' => 'Api Access Point',
                        'key' => 'e2pdf_adobesign_api_access_point',
                        'value' => get_option('e2pdf_adobesign_api_access_point') === false ? '' : get_option('e2pdf_adobesign_api_access_point'),
                        'default_value' => '',
                        'type' => get_option('e2pdf_debug', '0') ? 'text' : 'hidden',
                        'placeholder' => '',
                        'readonly' => 'readonly',
                    ),
                    array(
                        'name' => 'Refresh Token',
                        'key' => 'e2pdf_adobesign_refresh_token',
                        'value' => get_option('e2pdf_adobesign_refresh_token') === false ? '' : get_option('e2pdf_adobesign_refresh_token'),
                        'default_value' => '',
                        'type' => 'text',
                        'placeholder' => '',
                        'type' => get_option('e2pdf_debug', '0') ? 'text' : 'hidden',
                        'readonly' => 'readonly',
                    ),
                    array(
                        'name' => 'Access Token',
                        'key' => 'e2pdf_adobesign_access_token',
                        'value' => get_transient('e2pdf_adobesign_access_token') === false ? '' : get_transient('e2pdf_adobesign_access_token'),
                        'default_value' => '',
                        'type' => get_option('e2pdf_debug', '0') ? 'text' : 'hidden',
                        'placeholder' => '',
                        'readonly' => 'readonly',
                    ),
                    array(
                        'header' => 'Scopes',
                        'name' => 'user_read',
                        'key' => 'e2pdf_adobesign_scope_user_read',
                        'value' => get_option('e2pdf_adobesign_scope_user_read', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'user_write',
                        'key' => 'e2pdf_adobesign_scope_user_write',
                        'value' => get_option('e2pdf_adobesign_scope_user_write', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'user_login',
                        'key' => 'e2pdf_adobesign_scope_user_login',
                        'value' => get_option('e2pdf_adobesign_scope_user_login', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'agreement_read',
                        'key' => 'e2pdf_adobesign_scope_agreement_read',
                        'value' => get_option('e2pdf_adobesign_scope_agreement_read', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'agreement_write',
                        'key' => 'e2pdf_adobesign_scope_agreement_write',
                        'value' => get_option('e2pdf_adobesign_scope_agreement_write', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'agreement_send',
                        'key' => 'e2pdf_adobesign_scope_agreement_send',
                        'value' => get_option('e2pdf_adobesign_scope_agreement_send', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'widget_read',
                        'key' => 'e2pdf_adobesign_scope_widget_read',
                        'value' => get_option('e2pdf_adobesign_scope_widget_read', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'widget_write',
                        'key' => 'e2pdf_adobesign_scope_widget_write',
                        'value' => get_option('e2pdf_adobesign_scope_widget_write', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'library_read',
                        'key' => 'e2pdf_adobesign_scope_library_read',
                        'value' => get_option('e2pdf_adobesign_scope_library_read', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'library_write',
                        'key' => 'e2pdf_adobesign_scope_library_write',
                        'value' => get_option('e2pdf_adobesign_scope_library_write', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'workflow_read',
                        'key' => 'e2pdf_adobesign_scope_workflow_read',
                        'value' => get_option('e2pdf_adobesign_scope_workflow_read', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'workflow_write',
                        'key' => 'e2pdf_adobesign_scope_workflow_write',
                        'value' => get_option('e2pdf_adobesign_scope_workflow_write', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'webhook_read',
                        'key' => 'e2pdf_adobesign_scope_webhook_read',
                        'value' => get_option('e2pdf_adobesign_scope_webhook_read', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'webhook_write',
                        'key' => 'e2pdf_adobesign_scope_webhook_write',
                        'value' => get_option('e2pdf_adobesign_scope_webhook_write', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                    array(
                        'name' => 'webhook_retention',
                        'key' => 'e2pdf_adobesign_scope_webhook_retention',
                        'value' => get_option('e2pdf_adobesign_scope_webhook_retention', ''),
                        'default_value' => '',
                        'type' => 'select',
                        'options' => array(
                            '' => 'disabled',
                            'self' => 'self',
                            'group' => 'group',
                            'account' => 'account',
                        ),
                    ),
                ),
            ),
            'extensions_group' => array(
                'name' => __('Extensions', 'e2pdf'),
                'action' => 'extensions',
                'options' => $extensions_options,
            ),
        );

        if (
                class_exists('TRP_Translate_Press') ||
                class_exists('WeglotWP\Services\Translate_Service_Weglot') ||
                (function_exists('icl_register_string') && !defined('POLYLANG_VERSION')) ||
                defined('POLYLANG_VERSION') ||
                $all
        ) {
            $options['translation_group'] = array(
                'name' => __('Translation', 'e2pdf'),
                'action' => 'translation',
                'options' => array(
                    array(
                        'name' => __('PDF Translation', 'e2pdf'),
                        'key' => 'e2pdf_pdf_translation',
                        'value' => get_option('e2pdf_pdf_translation', '2'),
                        'default_value' => '2',
                        'type' => 'select',
                        'options' => array(
                            '0' => __('No Translation', 'e2pdf'),
                            '1' => __('Partial Translation', 'e2pdf'),
                            '2' => __('Full Translation', 'e2pdf'),
                        ),
                    ),
                ),
            );
        }

        $options = apply_filters('e2pdf_model_options_get_options_options', $options);

        if ($all) {
            $opt = array();
            foreach ($options as $group_key => $group) {
                foreach ($group['options'] as $option_key => $option) {
                    if (isset($option['key'])) {
                        $opt[$option['key']] = isset($option['value']) ? $option['value'] : '';
                    }
                }
            }
            return $opt;
        } else {
            foreach ($options as $group_key => $group) {
                if ($exclude) {
                    if (in_array($group_key, $only_group, true)) {
                        unset($options[$group_key]);
                    }
                } else {
                    if (!in_array($group_key, $only_group, true)) {
                        unset($options[$group_key]);
                    }
                }
            }

            // skip groups which require capability the current user does not have
            foreach ($options as $group_key => $group) {
                if (isset($group['cap']) && !current_user_can($group['cap'])) {
                    unset($options[$group_key]);
                }
            }

            // skip options which require capability the current user does not have
            foreach ($options as $group_key => $group) {
                foreach ($group['options'] as $option_key => $option) {
                    if (isset($option['cap']) && !current_user_can($option['cap'])) {
                        unset($options[$group_key]['options'][$option_key]);
                    }
                }
                if (!empty($group['options']) && empty($options[$group_key]['options'])) {
                    unset($options[$group_key]);
                }
            }

            return $options;
        }
    }

    // update options
    public static function update_options($group = '', $data = array()) {
        $helper = Helper_E2pdf_Helper::instance();
        $result = array(
            'reload' => false,
            'error' => '',
        );

        if ('permissions_group' === $group && isset($data['permissions']) && is_array($data['permissions']) && current_user_can('manage_options')) {
            $caps = $helper->get_caps();
            foreach ($data['permissions'] as $role_key => $permission) {
                $role = get_role($role_key);
                if ($role && is_array($permission)) {
                    foreach ($permission as $cap_key => $cap) {
                        if (isset($caps[$cap_key])) {
                            if ($cap) {
                                $role->add_cap($cap_key);
                            } else {
                                $role->remove_cap($cap_key);
                            }
                        }
                    }
                }
            }
        }

        if ('extensions_group' === $group && isset($data['e2pdf_disabled_extensions']) && is_array($data['e2pdf_disabled_extensions'])) {
            $disabled_extensions = array_map('sanitize_text_field', wp_unslash($data['e2pdf_disabled_extensions']));
            update_option('e2pdf_disabled_extensions', array_values(array_filter($disabled_extensions, 'strlen')));
            return $result;
        }

        $options = self::get_options(false, array($group));
        foreach ($options as $group_key => $group_options) {
            foreach ($group_options['options'] as $option_key => $option_value) {
                if (!isset($option_value['key'])) {
                    continue;
                }
                $option = $option_value['key'];
                if (array_key_exists($option, $data)) {
                    if (isset($option_value['readonly']) && $option_value['readonly'] == 'readonly') { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
                    } else {
                        switch ($option) {
                            case 'e2pdf_mod_rewrite_url':
                                if (false === strpos($data['e2pdf_mod_rewrite_url'], '%uid%')) {
                                    $data['e2pdf_mod_rewrite_url'] = rtrim($data['e2pdf_mod_rewrite_url'], '/') . '/%uid%/';
                                }
                                if (!trim(str_replace(array('/', '%uid%'), array('', ''), $data['e2pdf_mod_rewrite_url']))) {
                                    $data['e2pdf_mod_rewrite_url'] = 'e2pdf/%uid%/';
                                }
                                $data['e2pdf_mod_rewrite_url'] = ltrim($data['e2pdf_mod_rewrite_url'], '/');
                                break;
                            case 'e2pdf_zapier_regenerate_api_key':
                                if (current_user_can('manage_options') && $data['e2pdf_zapier_regenerate_api_key']) {
                                    update_option('e2pdf_zapier_api_key', strtoupper(wordwrap(wp_generate_password('24', false), 4, '-', true)));
                                }
                                $data['e2pdf_zapier_regenerate_api_key'] = '0';
                                break;
                            case 'e2pdf_mcp_regenerate_api_key':
                                if (current_user_can('manage_options') && $data['e2pdf_mcp_regenerate_api_key']) {
                                    update_option('e2pdf_mcp_api_key', wp_generate_password('64', false));
                                }
                                $data['e2pdf_mcp_regenerate_api_key'] = '0';
                                break;
                            case 'e2pdf_mcp_user_id':
                                if (current_user_can('manage_options')) {
                                    $user = get_userdata((int) $data['e2pdf_mcp_user_id']);
                                    $data['e2pdf_mcp_user_id'] = $user ? $user->ID : '';
                                } else {
                                    $data['e2pdf_mcp_user_id'] = get_option('e2pdf_mcp_user_id', '');
                                }
                                break;
                            case 'e2pdf_write_dirs':
                                $abspath = wp_normalize_path(ABSPATH);
                                $dirs = array();
                                foreach (explode("\n", $data['e2pdf_write_dirs']) as $dir) {
                                    $dir = trim($dir);
                                    if (strlen(wp_normalize_path($dir)) > strlen($abspath) && strpos(wp_normalize_path($dir), $abspath) === 0) {
                                        $dir = substr(wp_normalize_path($dir), strlen($abspath));
                                    }
                                    $dirs[] = $dir;
                                }
                                $data['e2pdf_write_dirs'] = implode("\n", $dirs);
                                break;
                            case 'e2pdf_read_dirs':
                                $abspath = wp_normalize_path(ABSPATH);
                                $dirs = array();
                                foreach (explode("\n", $data['e2pdf_read_dirs']) as $dir) {
                                    $dir = trim($dir);
                                    if (strlen(wp_normalize_path($dir)) > strlen($abspath) && strpos(wp_normalize_path($dir), $abspath) === 0) {
                                        $dir = substr(wp_normalize_path($dir), strlen($abspath));
                                    }
                                    $dirs[] = $dir;
                                }
                                $data['e2pdf_read_dirs'] = implode("\n", $dirs);
                                break;
                            case 'e2pdf_user_email':
                                if (get_option('e2pdf_user_email', '') != $data['e2pdf_user_email']) {
                                    $model_e2pdf_api = new Model_E2pdf_Api();
                                    $model_e2pdf_api->set(
                                            array(
                                                'action' => 'common/owner',
                                                'data' => array(
                                                    'email' => trim($data['e2pdf_user_email']),
                                                ),
                                            )
                                    );
                                    $request = $model_e2pdf_api->request();

                                    if (isset($request['error'])) {
                                        if (in_array($request['error'], array('incorrect_email', 'The E2Pdf account e-mail has not been found'), true)) {
                                            /* translators: %s: Registration URL */
                                            $request['error'] = sprintf(__('An account with that E-mail address was not found at E2Pdf.com. You can register at %s', 'e2pdf'), 'https://e2pdf.com/register');
                                        }
                                        $data['e2pdf_user_email'] = get_option('e2pdf_user_email', '');
                                        $result['error'] = $request['error'];
                                    }
                                }
                                break;
                            case 'e2pdf_debug':
                                if (get_option('e2pdf_debug', '0') != $data['e2pdf_debug'] || ($data['e2pdf_debug'] && get_option('e2pdf_memory_time', '0') != $data['e2pdf_debug'])) {
                                    $result['reload'] = true;
                                }
                                break;
                            case 'e2pdf_recovery_mode_email':
                                if (get_option('e2pdf_recovery_mode_email', '') != $data['e2pdf_recovery_mode_email']) {
                                    $result['reload'] = true;
                                }
                                break;
                            case 'e2pdf_dev_update':
                                if (get_option('e2pdf_dev_update', '0') != $data['e2pdf_dev_update']) {
                                    delete_site_transient('update_plugins');
                                }
                                break;
                            case 'e2pdf_api':
                                if ($data['e2pdf_api'] && $data['e2pdf_api'] != get_option('e2pdf_api', 'api.e2pdf.com')) {
                                    update_option('e2pdf_cached_fonts', array());
                                }
                                break;
                            case 'e2pdf_cache':
                                if (get_option('e2pdf_cache', '1') !== $data['e2pdf_cache']) {
                                    $helper->load('cache')->purge_objects_cache();
                                }
                                break;
                            case 'e2pdf_cache_fonts':
                                if (get_option('e2pdf_cache_fonts', '1') !== $data['e2pdf_cache_fonts']) {
                                    $helper->load('cache')->purge_fonts_cache();
                                }
                                break;
                            case 'e2pdf_cache_pdfs':
                                if (get_option('e2pdf_cache_pdfs', '0') !== $data['e2pdf_cache_pdfs']) {
                                    $helper->load('cache')->purge_pdfs_cache();
                                }
                                if ($data['e2pdf_cache_pdfs']) {
                                    if (!wp_next_scheduled('e2pdf_cache_pdfs_cron')) {
                                        wp_schedule_event(time(), 'hourly', 'e2pdf_cache_pdfs_cron');
                                    }
                                } else {
                                    wp_clear_scheduled_hook('e2pdf_cache_pdfs_cron');
                                    $helper->load('cache')->purge_pdfs_cache();
                                }
                                break;
                            default:
                                break;
                        }

                        $type = isset($option_value['type']) ? $option_value['type'] : '';
                        switch ($type) {
                            case 'textarea':
                                update_option($option, sanitize_textarea_field(wp_unslash($data[$option])));
                                break;
                            case 'checkbox_list':
                                update_option($option, is_array($data[$option]) ? array_map('sanitize_text_field', wp_unslash($data[$option])) : array());
                                break;
                            default:
                                update_option($option, sanitize_text_field(wp_unslash($data[$option])));
                                break;
                        }
                    }
                }
            }
        }

        return $result;
    }
}
