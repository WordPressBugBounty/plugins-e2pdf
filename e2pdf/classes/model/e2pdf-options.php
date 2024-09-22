<?php

/**
 * E2pdf Options Helper
 * 
 * @copyright  Copyright 2017 https://e2pdf.com
 * @license    GPLv3
 * @version    1
 * @link       https://e2pdf.com
 * @since      0.00.01
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_Options extends Model_E2pdf_Model {

    /**
     * Get Options list
     * 
     * @param bool $all - Get all options
     * @param array $only_group - Array list of groups only to include
     * @param array $exclude - Array list of groups to exclude
     * 
     * @return array() - List of options
     */
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
                    default:
                        break;
                }
                $extensions_options[] = array(
                    'name' => $extension_title,
                    'key' => 'e2pdf_disabled_extensions[]',
                    'value' => is_array($disabled_extensions) && in_array($extension, $disabled_extensions) ? $extension : '',
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
                        )
                    ),
                );
            }
            if (isset($extensions_options['0'])) {
                $extensions_options['0']['header'] = __('Extensions', 'e2pdf');
            }
        }

        $options = array(
            'static_group' => array(
                'name' => __('Static', 'e2pdf'),
                'options' => array(
                    array(
                        'key' => 'e2pdf_version',
                        'value' => $helper->get('version')
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
                )
            ),
            'common_group' => array(
                'name' => __('Common', 'e2pdf'),
                'options' => array(
                    array(
                        'name' => __('E2Pdf Connected Account', 'e2pdf'),
                        'key' => 'e2pdf_user_email',
                        'default_value' => '',
                        'value' => get_option('e2pdf_user_email', ''),
                        'type' => 'text',
                        'placeholder' => __('E2Pdf Account E-mail', 'e2pdf')
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
                            'api4.e2pdf.com' => 'api4.e2pdf.com (EU)'
                        )
                    ),
                    array(
                        'name' => __('API Connection Timout', 'e2pdf'),
                        'key' => 'e2pdf_connection_timeout',
                        'value' => get_option('e2pdf_connection_timeout', '300'),
                        'default_value' => '300',
                        'type' => 'text',
                        'class' => 'e2pdf-numbers',
                        'placeholder' => '0'
                    ),
                    array(
                        'name' => __('PDF Processor', 'e2pdf'),
                        'key' => 'e2pdf_processor',
                        'value' => get_option('e2pdf_processor', '0'),
                        'default_value' => '0',
                        'type' => 'select',
                        'options' => get_option('e2pdf_debug', '0') ? array(
                    '0' => __('Default (Stable Version)', 'e2pdf'),
                    '2' => __('Backport (1.16.19 Version)', 'e2pdf'),
                    '1' => __('Release Candidate (Debug Mode)', 'e2pdf'),
                        ) : array(
                    '0' => __('Default (Stable Version)', 'e2pdf')
                        ),
                    ),
                    array(
                        'name' => __('Font Processor', 'e2pdf'),
                        'key' => 'e2pdf_font_processor',
                        'value' => get_option('e2pdf_font_processor', '0'),
                        'default_value' => '0',
                        'type' => 'hidden',
                        'options' => array(
                            __('Default (Stable Version)', 'e2pdf'),
                            __('Complex Fonts (Experimental)', 'e2pdf')
                        )
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
                        'name' => __('Url Format', 'e2pdf'),
                        'key' => 'e2pdf_url_format',
                        'value' => get_option('e2pdf_url_format', 'siteurl'),
                        'default_value' => 'siteurl',
                        'type' => 'select',
                        'options' => array(
                            'siteurl' => 'WordPress Address (URL)',
                            'home' => 'Site Address (URL)'
                        )
                    ),
                    array(
                        'name' => __('Url Rewrite', 'e2pdf'),
                        'key' => 'e2pdf_mod_rewrite',
                        'value' => get_option('e2pdf_mod_rewrite', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Url Rewrite', 'e2pdf'),
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
                        'placeholder' => __('PDFs Cache (BETA)', 'e2pdf'),
                    ),
                    array(
                        'name' => '',
                        'key' => 'e2pdf_cache_pdfs_ttl',
                        'default_value' => '',
                        'value' => max(10, (int) get_option('e2pdf_cache_pdfs_ttl', '180')),
                        'type' => 'text',
                        'placeholder' => '10',
                        'prefield' => __('TTL (sec)', 'e2pdf') . ':',
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
                        'name' => __('Images Timout', 'e2pdf'),
                        'key' => 'e2pdf_images_timeout',
                        'value' => get_option('e2pdf_images_timeout', '30'),
                        'default_value' => '30',
                        'type' => 'text',
                        'class' => 'e2pdf-numbers',
                        'placeholder' => '0'
                    ),
                    array(
                        'name' => __('Revisions Limit', 'e2pdf'),
                        'key' => 'e2pdf_revisions_limit',
                        'value' => get_option('e2pdf_revisions_limit', '3'),
                        'default_value' => '3',
                        'type' => 'text',
                        'class' => 'e2pdf-numbers',
                        'placeholder' => '0'
                    ),
                    array(
                        'name' => __('Bulk Export Interval', 'e2pdf'),
                        'key' => 'e2pdf_bulk_export_interval',
                        'value' => max(20, (int) get_option('e2pdf_bulk_export_interval', '20')),
                        'default_value' => '20',
                        'type' => 'text',
                        'class' => 'e2pdf-numbers',
                        'placeholder' => '20'
                    ),
                    array(
                        'name' => __('Debug Mode', 'e2pdf'),
                        'key' => 'e2pdf_debug',
                        'value' => get_option('e2pdf_debug', '0'),
                        'default_value' => '0',
                        'type' => 'checkbox',
                        'checkbox_value' => '1',
                        'placeholder' => __('Turn on Debug Mode', 'e2pdf'),
                    ),
                    array(
                        'name' => __('Recovery Mode E-mail', 'e2pdf'),
                        'key' => 'e2pdf_recovery_mode_email',
                        'value' => get_option('e2pdf_recovery_mode_email', ''),
                        'default_value' => '',
                        'type' => get_option('e2pdf_debug', '0') ? 'text' : 'hidden',
                        'placeholder' => __('Recovery Mode E-mail', 'e2pdf')
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
                )
            ),
            'maintenance_group' => array(
                'name' => __('Maintenance', 'e2pdf'),
                'action' => 'maintenance',
                'options' => array()
            ),
            'fonts_group' => array(
                'name' => __('Fonts', 'e2pdf'),
                'action' => 'fonts',
                'options' => array()
            ),
            'permissions_group' => array(
                'name' => __('Permissions', 'e2pdf'),
                'action' => 'permissions',
                'options' => array()
            ),
            'adobesign_group' => array(
                'name' => 'Adobe Sign',
                'action' => 'adobesign',
                'options' => array(
                    array(
                        'name' => 'Status',
                        'key' => 'e2pdf_adobesign_status',
                        'value' => get_option('e2pdf_adobesign_refresh_token') ? __('Authorized', 'e2pdf') : __('Not Authorized', 'e2pdf'),
                        'default_value' => '',
                        'type' => 'text',
                        'readonly' => 'readonly',
                    ),
                    array(
                        'name' => 'Client ID',
                        'key' => 'e2pdf_adobesign_client_id',
                        'value' => get_option('e2pdf_adobesign_client_id') === false ? '' : get_option('e2pdf_adobesign_client_id'),
                        'default_value' => '',
                        'type' => 'text',
                        'placeholder' => ''
                    ),
                    array(
                        'name' => 'Client Secret',
                        'key' => 'e2pdf_adobesign_client_secret',
                        'value' => get_option('e2pdf_adobesign_client_secret') === false ? '' : get_option('e2pdf_adobesign_client_secret'),
                        'default_value' => '',
                        'type' => 'text',
                        'placeholder' => ''
                    ),
                    array(
                        'name' => 'Region',
                        'key' => 'e2pdf_adobesign_region',
                        'value' => get_option('e2pdf_adobesign_region') === false ? '' : get_option('e2pdf_adobesign_region'),
                        'default_value' => '',
                        'type' => 'text',
                        'placeholder' => 'na2'
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
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
                            'account' => 'account'
                        )
                    ),
                )
            ),
            'extensions_group' => array(
                'name' => __('Extensions', 'e2pdf'),
                'action' => 'extensions',
                'options' => $extensions_options
            ),
        );

        if (class_exists('TRP_Translate_Press') || class_exists('WeglotWP\Services\Translate_Service_Weglot') || $all) {
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
                            '2' => __('Full Translation', 'e2pdf')
                        )
                    ),
            ));
        }

        $options = apply_filters('e2pdf_model_options_get_options_options', $options);

        if ($all) {
            $opt = array();
            foreach ($options as $group_key => $group) {
                foreach ($group['options'] as $option_key => $option) {
                    $opt[$option['key']] = isset($option['value']) ? $option['value'] : '';
                }
            }
            return $opt;
        } else {
            foreach ($options as $group_key => $group) {
                if ($exclude) {
                    if (in_array($group_key, $only_group)) {
                        unset($options[$group_key]);
                    }
                } else {
                    if (!in_array($group_key, $only_group)) {
                        unset($options[$group_key]);
                    }
                }
            }

            return $options;
        }
    }

    /**
     * Update Options list by group
     * 
     * @param string $group - Group data
     * @param array $data - Array list of options to set
     * 
     * @return bool - true
     */
    public static function update_options($group = '', $data = array()) {
        $options = self::get_options(false, array($group));
        foreach ($options as $group_key => $group) {
            foreach ($group['options'] as $option_key => $option_value) {
                if (array_key_exists($option_value['key'], $data)) {
                    if (isset($option_value['readonly']) && $option_value['readonly'] == 'readonly') {
                        
                    } else {
                        if ($option_value['key'] == 'e2pdf_mod_rewrite_url') {
                            if (false === strpos($data[$option_value['key']], '%uid%')) {
                                $data[$option_value['key']] = rtrim($data[$option_value['key']], '/') . '/%uid%/';
                            }
                            if (!trim(str_replace(array('/', '%uid%'), array('', ''), $data[$option_value['key']]))) {
                                $data[$option_value['key']] = 'e2pdf/%uid%/';
                            }
                            $data[$option_value['key']] = ltrim($data[$option_value['key']], '/');
                        }
                        update_option($option_value['key'], $data[$option_value['key']]);
                    }
                }
            }
        }
        return true;
    }
}
