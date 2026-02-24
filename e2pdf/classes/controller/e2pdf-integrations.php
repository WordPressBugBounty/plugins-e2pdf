<?php

/**
 * File: /controller/e2pdf-integrations.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Controller_E2pdf_Integrations extends Helper_E2pdf_View {

    // url: admin.php?page=e2pdf-integrations
    public function index_action() {
        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_integrations')) {
                /* translators: %s: Action */
                $this->add_notification('update', sprintf(__('Success: %s', 'e2pdf'), __('Settings Saved', 'e2pdf')));
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        }
        $this->view('options', Model_E2pdf_Options::get_options(false, ['zapier_group']));
        $this->view('groups', $this->get_groups());
    }

    // url: admin.php?page=e2pdf-integrations&action=adobesign
    public function adobesign_action() {
        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_integrations')) {
                Model_E2pdf_Options::update_options('adobesign_group', $this->post->get());
                if (!get_option('e2pdf_adobesign_client_id', '') || !get_option('e2pdf_adobesign_client_secret', '') || !get_option('e2pdf_adobesign_region', '')) {
                    set_transient('e2pdf_adobesign_access_token', false);
                    update_option('e2pdf_adobesign_code', false);
                    update_option('e2pdf_adobesign_api_access_point', false);
                    update_option('e2pdf_adobesign_web_access_point', false);
                    update_option('e2pdf_adobesign_refresh_token', false);
                    $this->add_notification('update', __('Not Authorized', 'e2pdf'));
                    $this->redirect(
                            $this->helper->get_url(
                                    [
                                        'page' => 'e2pdf-integrations',
                                        'action' => 'adobesign',
                                    ]
                            )
                    );
                } else {
                    $request = (new Model_E2pdf_AdobeSign())->get_code();
                    if (isset($request['redirect'])) {
                        $this->redirect($request['redirect']);
                    }
                }
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        } elseif ($this->get->get('code') && $this->get->get('_wpnonce') && get_option('e2pdf_adobesign_client_id', '') && get_option('e2pdf_adobesign_client_secret', '')) {
            if (wp_verify_nonce($this->get->get('_wpnonce'), 'e2pdf_adobe')) {
                update_option('e2pdf_adobesign_code', sanitize_text_field(wp_unslash($this->get->get('code'))));
                $request = (new Model_E2pdf_AdobeSign())->get_token();
                if (isset($request['error'])) {
                    $this->add_notification('error', $request['error']);
                } else {
                    $this->add_notification('update', __('App Authorized', 'e2pdf'));
                }
                $this->redirect(
                        $this->helper->get_url(
                                [
                                    'page' => 'e2pdf-integrations',
                                    'action' => 'adobesign',
                                ]
                        )
                );
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        } else {
            new Model_E2pdf_AdobeSign();
        }

        $this->view('options', Model_E2pdf_Options::get_options(false, ['adobesign_group']));
        $this->view('groups', $this->get_groups());
    }

    // url: admin.php?page=e2pdf-integrations&action=gdrive
    public function gdrive_action() {
        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_integrations')) {
                if (
                        get_option('e2pdf_gdrive_client_id', '') !== $this->post->get('e2pdf_gdrive_client_id') ||
                        get_option('e2pdf_gdrive_client_secret', '') !== $this->post->get('e2pdf_gdrive_client_secret')
                ) {
                    Model_E2pdf_Options::update_options('gdrive_group', $this->post->get());
                    if (!get_option('e2pdf_gdrive_client_id', '') || !get_option('e2pdf_gdrive_client_secret', '')) {
                        $this->add_notification('update', __('Not Authorized', 'e2pdf'));
                        $this->redirect(
                                $this->helper->get_url(
                                        [
                                            'page' => 'e2pdf-integrations',
                                            'action' => 'gdrive',
                                        ]
                                )
                        );
                    } else {
                        $request = (new Model_E2pdf_Gdrive())->get_code();
                        if (isset($request['redirect'])) {
                            $this->redirect($request['redirect']);
                        }
                    }
                }
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        } else {
            new Model_E2pdf_Gdrive();
        }
        $this->view('options', Model_E2pdf_Options::get_options(false, ['gdrive_group']));
        $this->view('groups', $this->get_groups());
    }

    // get integration option groups
    public function get_groups() {
        $groups = Model_E2pdf_Options::get_options(
                false,
                [
                    'zapier_group',
                    'adobesign_group',
                    'gdrive_group',
                ],
                false
        );

        return $groups;
    }
}
