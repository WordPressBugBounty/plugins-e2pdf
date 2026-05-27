<?php

/**
 * File: /helper/e2pdf-license.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_License {

    private $helper;

    public function __construct() {
        $this->helper = Helper_E2pdf_Helper::instance();
    }

    // load license
    public function load($reload = false) {
        $license = new Model_E2pdf_License(
                (new Model_E2pdf_Api())
                        ->set(
                                [
                                    'action' => 'license/info',
                                    'reload' => $reload,
                                ]
                        )
                        ->request()
        );
        $this->helper->set('license', $license);
    }

    // activate license
    public function activate() {
        $request = (new Model_E2pdf_Api())
                ->set(
                        [
                            'action' => 'common/activate',
                        ]
                )
                ->request();
        if (!empty($request['license_key'])) {
            update_option('e2pdf_license', $request['license_key']);
        }
        if (!empty($request['e2pdf_api']) && get_option('e2pdf_api') === false) {
            if ($request['e2pdf_api'] === 'api3.e2pdf.com') {
                update_option('e2pdf_api', 'api3.e2pdf.com');
            } else {
                update_option('e2pdf_api', 'api.e2pdf.com');
            }
        }
        $this->load();
        $this->load_templates();
    }

    // reactivate license
    public function reactivate() {
        update_option('e2pdf_version', '1.00.00');
        (new Model_E2pdf_Loader())->action_plugins_loaded();
        $this->load(true);
        if ($this->helper->get('license')->get('error') === 'Site Url Not Found. Please try to "Reactivate" plugin.') {
            return false;
        }
        return true;
    }

    // restore license
    public function restore() {
        $restore_key = wp_generate_password(32, false, false);
        set_transient('e2pdf_restore_key', $restore_key, HOUR_IN_SECONDS);
        $request = (new Model_E2pdf_Api())
                ->set(
                        [
                            'action' => 'license/restore',
                            'data' => [
                                'restore_key' => $restore_key,
                            ],
                        ]
                )
                ->request();
        if (!empty($request['license_key'])) {
            update_option('e2pdf_license', $request['license_key']);
        }
        delete_transient('e2pdf_restore_key');
        $this->load(true);
        if ($this->helper->get('license')->get('error') === 'License Key does not match this site. Please correct License Key to continue usage.') {
            return false;
        }
        return true;
    }

    // change license
    public function change($license_key = '') {
        $request = (new Model_E2pdf_Api())
                ->set(
                        [
                            'action' => 'license/update',
                            'data' => [
                                'license_key' => trim($license_key),
                            ],
                        ]
                )
                ->request();
        if (!empty($request['license_key'])) {
            update_option('e2pdf_license', $request['license_key']);
        }
        $this->load();
    }

    // load templates
    public function load_templates() {
        global $wpdb;

        $condition = [
            'activated' => [
                'condition' => '=',
                'value' => '1',
                'type' => '%d',
            ],
            'uid' => [
                'condition' => '=',
                'value' => '',
                'type' => '%s',
            ],
        ];
        $where = $this->helper->load('db')->prepare_where($condition);
        $model_e2pdf_template = new Model_E2pdf_Template();

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $tpls = $wpdb->get_results($wpdb->prepare('SELECT `ID` FROM `' . $model_e2pdf_template->get_table() . '`' . $where['sql'] . '', $where['filter']));
        foreach ($tpls as $key => $tpl) {
            $template = new Model_E2pdf_Template();
            $template->load($tpl->ID, false);
            $template->activate();
        }
    }
}
