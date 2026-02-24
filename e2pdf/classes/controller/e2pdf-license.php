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
            $model_e2pdf_api = new Model_E2pdf_Api();
            $model_e2pdf_api->set(
                    [
                        'action' => 'license/update',
                        'data' => [
                            'license_key' => isset($data['license_key']) ? trim($data['license_key']) : '',
                        ],
                    ]
            );
            $request = $model_e2pdf_api->request();
            if (isset($request['error'])) {
                $this->add_notification('error', $request['error']);
            } else {
                /* translators: %s: Action */
                $this->add_notification('update', sprintf(__('Success: %s', 'e2pdf'), __('License Key Updated', 'e2pdf')));
            }
            $response = [
                'redirect' => $this->helper->get_url(
                        [
                            'page' => 'e2pdf-license',
                        ]
                ),
            ];
        } else {
            $response['error'] = $this->message('wp_verify_nonce_error');
        }
        $this->json_response($response);
    }

    /**
     * Restore license key via ajax
     * action: wp_ajax_e2pdf_restore_license_key
     * function: e2pdf_restore_license_key
     * @return json
     */
    public function ajax_restore_license_key() {
        if (wp_verify_nonce($this->get->get('_wpnonce'), 'e2pdf_license')) {
            $model_e2pdf_api = new Model_E2pdf_Api();
            $model_e2pdf_api->set(
                    [
                        'action' => 'license/activation',
                        'data' => [],
                    ]
            );
            $request = $model_e2pdf_api->request();
            if (isset($request['error'])) {
                $this->add_notification('error', $request['error']);
            } elseif (isset($request['success']) && isset($request['activation_key'])) {
                $activation_key = $request['activation_key'];
                $file = ABSPATH . $activation_key . '.html';
                set_transient('e2pdf_activation_key', $activation_key, 600);
                if (!file_exists($file)) {
                    $this->helper->create_file($file, $activation_key);
                }
                $model_e2pdf_api = new Model_E2pdf_Api();
                $model_e2pdf_api->set(
                        [
                            'action' => 'license/request',
                            'data' => [],
                        ]
                );
                $request = $model_e2pdf_api->request();
                if (isset($request['success'])) {
                    /* translators: %s: Action */
                    $this->add_notification('update', sprintf(__('Success: %s', 'e2pdf'), __('License Key Restored', 'e2pdf')));
                } else {
                    /* translators: %1$s: Url, %2$s: Url   */
                    $this->add_notification('error', sprintf(__('Failed to Restore License Key. Contact Support at <a target="_blank" href="%1$s">%2$s</a>', 'e2pdf'), 'https://e2pdf.com/support/contact', 'https://e2pdf.com/support/contact'));
                }
                if (file_exists($file)) {
                    unlink($file);
                }
                delete_transient('e2pdf_activation_key');
            }
            $response = [
                'redirect' => $this->helper->get_url(
                        [
                            'page' => 'e2pdf-license',
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
            if (isset($request['error'])) {
                $this->add_notification('error', $request['error']);
            } else {
                /* translators: %s: Action */
                $this->add_notification('update', sprintf(__('Success: %s', 'e2pdf'), __('Deactivated', 'e2pdf')));
            }
            $response = [
                'redirect' => $this->helper->get_url(
                        [
                            'page' => 'e2pdf-license',
                        ]
                ),
            ];
        } else {
            $response['error'] = $this->message('wp_verify_nonce_error');
        }
        $this->json_response($response);
    }
}
