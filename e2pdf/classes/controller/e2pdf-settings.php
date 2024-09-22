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
                $reload = false;
                if (
                        get_option('e2pdf_debug', '0') != $this->post->get('e2pdf_debug') ||
                        get_option('e2pdf_recovery_mode_email', '') != $this->post->get('e2pdf_recovery_mode_email') ||
                        ($this->post->get('e2pdf_debug') && get_option('e2pdf_memory_time', '0') != $this->post->get('e2pdf_debug'))
                ) {
                    $reload = true;
                }

                $check_update = false;
                if (get_option('e2pdf_dev_update', '0') != $this->post->get('e2pdf_dev_update')) {
                    $check_update = true;
                }

                if ($this->post->get('e2pdf_api') && $this->post->get('e2pdf_api') != get_option('e2pdf_api', 'api.e2pdf.com')) {
                    update_option('e2pdf_cached_fonts', array());
                }

                if (get_option('e2pdf_user_email', '') != $this->post->get('e2pdf_user_email')) {
                    $model_e2pdf_api = new Model_E2pdf_Api();
                    $model_e2pdf_api->set(
                            array(
                                'action' => 'common/owner',
                                'data' => array(
                                    'email' => trim($this->post->get('e2pdf_user_email')),
                                ),
                            )
                    );
                    $request = $model_e2pdf_api->request();

                    if (isset($request['error'])) {
                        if ($request['error'] === 'incorrect_email') {
                            $request['error'] = __('E-mail address incorrect', 'e2pdf');
                        }
                        $this->post->set('e2pdf_user_email', get_option('e2pdf_user_email', ''));
                        $this->add_notification('error', $request['error']);
                    }
                }

                Model_E2pdf_Options::update_options('common_group', $this->post->get());
                $this->add_notification('update', __('Settings saved', 'e2pdf'));

                if ($this->post->get('e2pdf_cache_pdfs')) {
                    if (!wp_next_scheduled('e2pdf_cache_pdfs_cron')) {
                        wp_schedule_event(time(), 'hourly', 'e2pdf_cache_pdfs_cron');
                    }
                } else {
                    wp_clear_scheduled_hook('e2pdf_cache_pdfs_cron');
                    $this->helper->load('cache')->purge_pdfs_cache();
                }

                if ($check_update) {
                    delete_site_transient('update_plugins');
                }
                if ($reload) {
                    $this->redirect($this->helper->get_url(array('page' => 'e2pdf-settings')));
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
                    $this->add_notification('update', __('The cache has been purged successfully', 'e2pdf'));
                    $this->redirect(
                            $this->helper->get_url(
                                    array(
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                    )
                            )
                    );
                } elseif ($this->post->get('e2pdf_purge_fonts_cache')) {
                    $this->helper->load('cache')->purge_fonts_cache();
                    $this->add_notification('update', __('The cache has been purged successfully', 'e2pdf'));
                    $this->redirect(
                            $this->helper->get_url(
                                    array(
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                    )
                            )
                    );
                } elseif ($this->post->get('e2pdf_purge_pdfs_cache')) {
                    $this->helper->load('cache')->purge_pdfs_cache();
                    $this->add_notification('update', __('The cache has been purged successfully', 'e2pdf'));
                    $this->redirect(
                            $this->helper->get_url(
                                    array(
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                    )
                            )
                    );
                } elseif ($this->post->get('e2pdf_purge_cache')) {
                    $this->helper->load('cache')->purge_cache();
                    $this->add_notification('update', __('The cache has been purged successfully', 'e2pdf'));
                    $this->redirect(
                            $this->helper->get_url(
                                    array(
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                    )
                            )
                    );
                } elseif ($this->post->get('e2pdf_db_optimize')) {
                    $db_prefix = $wpdb->prefix;
                    $this->helper->load('db')->db_optimize($db_prefix);
                    $this->add_notification('update', __('The database has been optimized successfully', 'e2pdf'));
                    $this->redirect(
                            $this->helper->get_url(
                                    array(
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                    )
                            )
                    );
                } elseif ($this->post->get('e2pdf_recovery_mode_limit')) {
                    delete_option('recovery_mode_email_last_sent');
                    $this->add_notification('update', __('Recovery Mode Limit has been cleared successfully', 'e2pdf'));
                    $this->redirect(
                            $this->helper->get_url(
                                    array(
                                        'page' => 'e2pdf-settings',
                                        'action' => 'maintenance',
                                    )
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
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_settings')) {
                $font = $this->files->get('font');
                $name = $font['name'];
                $tmp = $font['tmp_name'];
                $filename = pathinfo($name, PATHINFO_FILENAME);
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $name = $filename . '.' . $extension;
                $fonts = $model_e2pdf_font->get_fonts();
                $font_name = false;
                $exist = false;
                if (in_array($extension, $model_e2pdf_font->get_allowed_extensions())) {
                    $font_name = $model_e2pdf_font->get_font_info(false, 4, $tmp);
                    if ($font_name) {
                        $exist = array_search($font_name, $fonts);
                    }
                }
                if (!$tmp) {
                    $this->add_notification('error', __('Please choose font to upload', 'e2pdf'));
                } elseif ($font['error']) {
                    $this->add_notification('error', $font['error']);
                } elseif (!in_array($extension, $model_e2pdf_font->get_allowed_extensions())) {
                    $this->add_notification('error', __('Incorrect file extension', 'e2pdf'));
                } elseif (!$font_name) {
                    $this->add_notification('error', __('Incompatible type', 'e2pdf'));
                } elseif (array_key_exists($name, $fonts)) {
                    $this->add_notification('error', __('Font with this name already exists', 'e2pdf'));
                } elseif ($exist) {
                    $this->add_notification('error', __('Font with this name already exists', 'e2pdf'));
                } elseif (move_uploaded_file($font['tmp_name'], $this->helper->get('fonts_dir') . $name)) {
                    if (file_exists($this->helper->get('fonts_dir') . $name)) {
                        $this->add_notification('update', __('Font uploaded successfully', 'e2pdf'));
                    } else {
                        $this->add_notification('error', __('Font upload failed', 'e2pdf'));
                    }
                }
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
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
     */
    public function adobesign_action() {
        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_settings')) {
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
                                    array(
                                        'page' => 'e2pdf-settings',
                                        'action' => 'adobesign',
                                    )
                            )
                    );
                } else {
                    $model_e2pdf_adobesign = new Model_E2pdf_AdobeSign();
                    $request = $model_e2pdf_adobesign->get_code();
                    if (isset($request['redirect'])) {
                        $this->redirect($request['redirect']);
                    }
                }
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        } elseif ($this->get->get('code') && get_option('e2pdf_adobesign_client_id', '') && get_option('e2pdf_adobesign_client_secret', '')) {
            update_option('e2pdf_adobesign_code', $this->get->get('code'));
            $model_e2pdf_adobesign = new Model_E2pdf_AdobeSign();
            $request = $model_e2pdf_adobesign->get_token();
            if (isset($request['error'])) {
                $this->add_notification('error', $request['error']);
            } else {
                $this->add_notification('update', __('App Authorized', 'e2pdf'));
            }
            $this->redirect(
                    $this->helper->get_url(
                            array(
                                'page' => 'e2pdf-settings',
                                'action' => 'adobesign',
                            )
                    )
            );
        } else {
            $model_e2pdf_adobesign = new Model_E2pdf_AdobeSign();
        }

        $this->view('options', Model_E2pdf_Options::get_options(false, array('adobesign_group')));
        $this->view('groups', $this->get_groups());
    }

    /**
     * @url admin.php?page=e2pdf-settings&action=docusign
     */
    public function docusign_action() {
        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_settings')) {
                Model_E2pdf_Options::update_options('docusign_group', $this->post->get());
                $this->add_notification('update', __('Settings saved', 'e2pdf'));
            } else {
                wp_die($this->message('wp_verify_nonce_error'));
            }
        }
        $this->view('options', Model_E2pdf_Options::get_options(false, array('docusign_group')));
        $this->view('groups', $this->get_groups());
    }

    /**
     * @url admin.php?page=e2pdf-settings&action=extension
     */
    public function extension_action() {
        $group_key = $this->get->get('group');
        if ($this->post->get('_wpnonce')) {
            if (wp_verify_nonce($this->post->get('_wpnonce'), 'e2pdf_settings')) {
                Model_E2pdf_Options::update_options($group_key, $this->post->get());
                $this->add_notification('update', __('Settings saved', 'e2pdf'));
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
                if (!$this->post->get('e2pdf_disabled_extensions')) {
                    update_option('e2pdf_disabled_extensions', array());
                } else {
                    update_option('e2pdf_disabled_extensions', array_filter($this->post->get('e2pdf_disabled_extensions')));
                }
                $this->add_notification('update', __('Settings saved', 'e2pdf'));
                $this->redirect(
                        $this->helper->get_url(
                                array(
                                    'page' => 'e2pdf-settings',
                                    'action' => 'extensions',
                                )
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
                $permissions = $this->post->get('permissions');
                $roles = wp_roles()->roles;
                $caps = $this->helper->get_caps();
                foreach ($permissions as $permission_key => $permission) {
                    $role = get_role($permission_key);
                    if ($role) {
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
                $this->add_notification('update', __('Settings saved', 'e2pdf'));
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
                $this->add_notification('update', __('Settings saved', 'e2pdf'));
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
        if (wp_verify_nonce($this->get->get('_wpnonce'), 'e2pdf_settings')) {
            $font = $this->post->get('data');
            $model_e2pdf_font = new Model_E2pdf_Font();
            $model_e2pdf_font->delete_font($font);
            $this->add_notification('update', __('Font removed successfully', 'e2pdf'));
            $response = array(
                'redirect' => $this->helper->get_url(
                        array(
                            'page' => 'e2pdf-settings',
                            'action' => 'fonts',
                        )
                ),
            );
        } else {
            $response['error'] = $this->message('wp_verify_nonce_error');
        }

        $this->json_response($response);
    }
}
