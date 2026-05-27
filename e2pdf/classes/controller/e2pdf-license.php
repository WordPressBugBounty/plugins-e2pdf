<?php

/**
 * File: /controller/e2pdf-license.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Controller_E2pdf_License extends Helper_E2pdf_View {

    /**
     * @url admin.php?page=e2pdf-license
     */
    public function index_action() {
        $this->load_scripts();
        $this->load_styles();
        $this->view('license', $this->helper->get('license'));
    }

    /**
     * Load javascript on license page
     */
    public function load_scripts() {
        wp_enqueue_script('jquery-ui-dialog');
    }

    /**
     * Load style on license page
     */
    public function load_styles() {
        $version = get_option('e2pdf_debug', '0') === '1' ? strtotime('now') : $this->helper->get('version');
        wp_enqueue_style('css/e2pdf.jquery-ui', plugins_url('css/jquery-ui.css', $this->helper->get('plugin_file_path')), false, $version, false);
    }

    /**
     * Change license key via ajax
     * action: wp_ajax_e2pdf_license_key
     * function: e2pdf_license_key
     * @return json
     */
    public function ajax_change_license_key() {
        if (wp_verify_nonce($this->get->get('_wpnonce'), 'e2pdf_license')) {
            $data = $this->post->get('data');
            $this->helper->load('license')->change(isset($data['license_key']) ? $data['license_key'] : '');
            $response = [
                'redirect' => $this->helper->get_url(
                        [
                            'page' => 'e2pdf-license',
                            'notification' => isset($request['error']) ? $this->add_notification('error', $request['error']) : $this->add_notification('update', __('License Key Changed', 'e2pdf')),
                        ]
                ),
            ];
        } else {
            $response['error'] = $this->message('wp_verify_nonce_error');
        }
        $this->json_response($response);
    }

    public function ajax_deactivate_all_templates() {
        if (wp_verify_nonce($this->get->get('_wpnonce'), 'e2pdf_license')) {
            $model_e2pdf_api = new Model_E2pdf_Api();
            $model_e2pdf_api->set(
                    [
                        'action' => 'template/deactivateall',
                    ]
            );
            $request = $model_e2pdf_api->request();
            $response = [
                'redirect' => $this->helper->get_url(
                        [
                            'page' => 'e2pdf-license',
                            'notification' => isset($request['error']) ? $this->add_notification('error', $request['error']) : $this->add_notification('update', __('Templates Deactivated', 'e2pdf')),
                        ]
                ),
            ];
        } else {
            $response['error'] = $this->message('wp_verify_nonce_error');
        }
        $this->json_response($response);
    }
}
