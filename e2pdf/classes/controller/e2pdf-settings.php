<?php

/**
 * E2pdf Settings Controller
 * @copyright  Copyright 2017 https://e2pdf.com
 * @license    GPLv3
 * @version    1
 * @link       https://e2pdf.com
 * @since      0.00.01
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Controller_E2pdf_Settings extends Helper_E2pdf_View {

    /**
     * @url admin.php?page=e2pdf-settings
     */
    public function index_action() {
        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_settings')) {
                $result = Model_E2pdf_Options::update_options('common_group', $this->post->get());
                $notifications = array();
                if ($result['error']) {
                    $notifications[] = $this->add_notification('error', $result['error']);
                }
                $notifications[] = $this->add_notification('update', __('Settings Saved', 'e2pdf'));
                if ($result['reload']) {
                    $this->redirect(
                            $this->helper->get_url(
                                    array(
                                        'page' => 'e2pdf-settings',
                                        'notification' => implode(',', $notifications),
                                    )
                            )
                    );
                }
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        }

        $this->view('options', Model_E2pdf_Options::get_options(false, array('common_group')));
        $this->view('groups', $this->get_groups());
    }

    /**
     * @url admin.php?page=e2pdf-settings&action=maintenance
     */
    public function maintenance_action() {
        global $wpdb;
        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_settings')) {
                if ($this->post->get('e2pdf_purge_objects_cache')) {
                    $this->helper->load('cache')->purge_objects_cache();
                    $this->redirect(
                            $this->helper->get_url(
                                    [
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                        'notification' => $this->add_notification('update', __('Success', 'e2pdf')),
                                    ]
                            )
                    );
                } elseif ($this->post->get('e2pdf_purge_fonts_cache')) {
                    $this->helper->load('cache')->purge_fonts_cache();
                    $this->redirect(
                            $this->helper->get_url(
                                    [
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                        'notification' => $this->add_notification('update', __('Success', 'e2pdf')),
                                    ]
                            )
                    );
                } elseif ($this->post->get('e2pdf_purge_pdfs_cache')) {
                    $this->helper->load('cache')->purge_pdfs_cache();
                    $this->redirect(
                            $this->helper->get_url(
                                    [
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                        'notification' => $this->add_notification('update', __('Success', 'e2pdf')),
                                    ]
                            )
                    );
                } elseif ($this->post->get('e2pdf_purge_cache')) {
                    $this->helper->load('cache')->purge_cache();
                    $this->redirect(
                            $this->helper->get_url(
                                    [
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                        'notification' => $this->add_notification('update', __('Success', 'e2pdf')),
                                    ]
                            )
                    );
                } elseif ($this->post->get('e2pdf_db_optimize')) {
                    $db_prefix = $wpdb->prefix;
                    $this->helper->load('db')->db_optimize($db_prefix);
                    $this->redirect(
                            $this->helper->get_url(
                                    [
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                        'notification' => $this->add_notification('update', __('Success', 'e2pdf')),
                                    ]
                            )
                    );
                } elseif ($this->post->get('e2pdf_recovery_mode_limit')) {
                    delete_option('recovery_mode_email_last_sent');
                    $this->redirect(
                            $this->helper->get_url(
                                    [
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                        'notification' => $this->add_notification('update', __('Success', 'e2pdf')),
                                    ]
                            )
                    );
                }
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        }

        $this->view('groups', $this->get_groups());
    }

    /**
     * @url admin.php?page=e2pdf-settings&action=fonts
     */
    public function fonts_action() {
        $model_e2pdf_font = new Model_E2pdf_Font();
        if ($this->post->get('_wpnonce')) {

            if (!wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_settings')) {
                wp_die($this->message('wp_verify_nonce_error'));
            }

            $file = $this->helper->load('files')->upload($this->files->get('font'), $model_e2pdf_font->get_allowed_extensions());
            if (isset($file['error'])) {
                $this->add_notification('error', $file['error']);
            } else {
                $font_name = false;
                $exist = false;
                $fonts = $model_e2pdf_font->get_fonts();
                $font_name = $model_e2pdf_font->get_font_info(false, 4, $file['tmp_name']);
                $name = pathinfo($file['name'], PATHINFO_FILENAME) . '.' . $file['ext'];
                if ($font_name) {
                    $exist = array_search($font_name, $fonts);
                }
                if (!$font_name) {
                    $this->add_notification('error', __('Invalid Type', 'e2pdf'));
                } elseif (array_key_exists($name, $fonts) || $exist) {
                    $this->add_notification('error', __('A Font with this name already exists', 'e2pdf'));
                } else {
                    if (!move_uploaded_file($file['tmp_name'], $this->helper->get('fonts_dir') . $name)) {
                        $this->add_notification('error', __('Failed to move uploaded file', 'e2pdf'));
                    } else {
                        $this->add_notification('update', __('Font Uploaded', 'e2pdf'));
                    }
                }
            }
        }

        $fonts = $model_e2pdf_font->get_fonts();

        $this->view('fonts', $fonts);
        $this->view('allowed_extensions', $model_e2pdf_font->get_allowed_extensions());
        $this->view('cached_fonts', get_option('e2pdf_cached_fonts', array()));
        $this->view('groups', $this->get_groups());
        $this->view('upload_max_filesize', $this->helper->load('files')->get_upload_max_filesize());
    }

    /**
     * @url admin.php?page=e2pdf-settings&action=adobesign
     * Backward Compatibility
     */
    public function adobesign_action() {
        $redirect_url = [
            'page' => 'e2pdf-integrations',
            'action' => 'adobesign',
        ];
        if ($this->get->get('code') && get_option('e2pdf_adobesign_client_id', '') && get_option('e2pdf_adobesign_client_secret', '')) {
            $redirect_url['code'] = $this->get->get('code');
            $redirect_url['_wpnonce'] = wp_create_nonce('e2pdf_adobe');
        }
        $this->redirect(
                $this->helper->get_url(
                        $redirect_url
                )
        );
    }

    /**
     * @url admin.php?page=e2pdf-settings&action=extension
     */
    public function extension_action() {
        $group_key = $this->get->get('group');
        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_settings')) {
                Model_E2pdf_Options::update_options($group_key, $this->post->get());
                $this->add_notification('update', __('Settings Saved', 'e2pdf'));
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        }

        $this->view('options', Model_E2pdf_Options::get_options(false, array($group_key)));
        $this->view('groups', $this->get_groups());
    }

    /**
     * @url admin.php?page=e2pdf-settings&action=extensions
     */
    public function extensions_action() {

        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_settings')) {
                Model_E2pdf_Options::update_options('extensions_group', $this->post->get());
                $this->redirect(
                        $this->helper->get_url(
                                [
                                    'page' => 'e2pdf-settings',
                                    'action' => 'extensions',
                                    'notification' => $this->add_notification('update', __('Settings Saved', 'e2pdf')),
                                ]
                        )
                );
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        }
        $this->view('options', Model_E2pdf_Options::get_options(false, array('extensions_group')));
        $this->view('groups', $this->get_groups());
    }

    /**
     * @url admin.php?page=e2pdf-settings&action=permissions
     */
    public function permissions_action() {
        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_settings')) {
                Model_E2pdf_Options::update_options('permissions_group', $this->post->get());
                $this->add_notification('update', __('Settings Saved', 'e2pdf'));
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        }

        $roles = wp_roles()->roles;
        foreach ($roles as $role_key => $role) {
            if (isset($role['capabilities']['manage_options']) && $role['capabilities']['manage_options']) {
                unset($roles[$role_key]);
            }
        }

        $this->view('options', Model_E2pdf_Options::get_options(false, array('permissions_group')));
        $this->view('groups', $this->get_groups());
        $this->view('roles', $roles);
        $this->view('caps', $this->helper->get_caps());
    }

    /**
     * @url admin.php?page=e2pdf-settings&action=extensions
     */
    public function translation_action() {
        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_settings')) {
                Model_E2pdf_Options::update_options('translation_group', $this->post->get());
                $this->add_notification('update', __('Settings Saved', 'e2pdf'));
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        }
        $this->view('options', Model_E2pdf_Options::get_options(false, array('translation_group')));
        $this->view('groups', $this->get_groups());
    }

    /**
     * Get options list
     * @return array() - Options list
     */
    public function get_groups() {
        $groups = Model_E2pdf_Options::get_options(
                false,
                array(
                    'static_group',
                    'adobesign_group',
                    'gdrive_group',
                    'zapier_group',
                    'mcp_group',
                ),
                true
        );

        return $groups;
    }

    /**
     * Remove font via ajax
     * action: wp_ajax_e2pdf_delete_font
     * function: e2pdf_delete_font
     * @return json
     */
    public function ajax_delete_font() {
        if (wp_verify_nonce($this->get->get('_wpnonce'), 'e2pdf_settings') && (current_user_can('manage_options') || current_user_can('e2pdf_settings'))) {
            $font = $this->post->get('data');
            $model_e2pdf_font = new Model_E2pdf_Font();
            $model_e2pdf_font->delete_font($font);
            $response = array(
                'redirect' => $this->helper->get_url(
                        array(
                            'page' => 'e2pdf-settings',
                            'action' => 'fonts',
                            'notification' => $this->add_notification('update', __('Font Deleted', 'e2pdf')),
                        )
                ),
            );
        } else {
            $response['error'] = $this->message('wp_verify_nonce_error');
        }

        $this->json_response($response);
    }
}
