<?php

/**
 * File: /controller/frontend/e2pdf-download.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Controller_Frontend_E2pdf_Download extends Helper_E2pdf_View {

    // url: page=e2pdf-download&uid=$uid
    public function index_action() {
        global $wp_query;

        $uid = false;
        if ($this->get->get('uid')) {
            $uid = $this->get->get('uid');
        } elseif (get_query_var('uid')) {
            $uid = get_query_var('uid');
        }

        do_action('e2pdf_controller_frontend_e2pdf_pre_download', $this);

        $entry = new Model_E2pdf_Entry();
        if ($uid && $entry->load_by_uid($uid)) {

            $template = new Model_E2pdf_Template();
            if ($entry->get_data('file_id') || $entry->get_data('pdf') || $entry->get_data('attachment_id')) {
                $downloadable = false;
                if ($entry->get_data('file_id')) {
                    $stream = (new Model_E2pdf_Gdrive())->download($entry->get_data('file_id'));
                    $pdf = is_array($stream) && isset($stream['name']) ? $stream['name'] : '';
                    if ($pdf) {
                        $downloadable = true;
                    }
                } else {
                    $pdf = $entry->get_data('pdf') ? $entry->get_data('pdf') : get_attached_file($entry->get_data('attachment_id'));
                    $downloadable = file_exists($pdf) && $this->helper->load('filter')->is_downloadable($pdf);
                }

                $downloadable = apply_filters('e2pdf_pdf_downloadable', $downloadable, $uid, $entry->get('entry'));
                if ($downloadable) {
                    $disposition = 'attachment';
                    if ($entry->get_data('inline')) {
                        $disposition = 'inline';
                    }
                    $ext = pathinfo($pdf, PATHINFO_EXTENSION);
                    if ($entry->get_data('name')) {
                        $download_name = $entry->get_data('name');
                    } else {
                        $download_name = basename($pdf, '.' . $ext);
                    }
                    $download_name = apply_filters('e2pdf_controller_frontend_e2pdf_download_name', $download_name, $entry->get('uid'), $entry->get('entry'));
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    if ($disposition == 'inline' && !isset($_GET['v'])) {
                        if (get_option('e2pdf_download_inline_chrome_ios_fix', '0') == '1' && $this->helper->load('server')->isIOS() && $this->helper->load('server')->isChrome()) {
                            $url_data = [
                                'page' => 'e2pdf-download',
                                'uid' => $uid,
                                'download_name' => rawurlencode($download_name . '.' . (strtolower($ext) == 'jpg' ? 'jpg' : 'pdf')),
                                'v' => $this->helper->get('version'),
                            ];
                            $url = $this->helper->get_frontend_pdf_url(
                                    $url_data, false,
                                    [
                                        'e2pdf_model_shortcode_site_url',
                                        'e2pdf_model_shortcode_e2pdf_redirect_site_url',
                                    ]
                            );
                            // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
                            wp_redirect($url);
                            exit;
                        } elseif (get_option('e2pdf_download_inline_fallback_viewer', '0') == '1' && !$this->helper->load('server')->isViewerSupported() && strtolower($ext) == 'pdf') {
                            $url_data = [
                                'page' => 'e2pdf-download',
                                'uid' => $uid,
                                'v' => $this->helper->get('version'),
                            ];
                            $url = $this->helper->get_frontend_pdf_url(
                                    $url_data, false,
                                    [
                                        'e2pdf_model_shortcode_site_url',
                                        'e2pdf_model_shortcode_e2pdf_redirect_site_url',
                                    ]
                            );
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                            $file = urlencode($url);
                            $classes = [
                                'e2pdf-hide-print',
                                'e2pdf-hide-editor',
                                'e2pdf-hide-secondary-toolbar',
                            ];
                            $viewer_url = add_query_arg(
                                    [
                                        'class' => implode(';', $classes),
                                        'file' => $file,
                                    ],
                                    plugins_url('assets/pdf.js/web/viewer.html', $this->helper->get('plugin_file_path'))
                            );
                            // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
                            wp_redirect($viewer_url);
                            exit;
                        }
                    }

                    if ($entry->get_data('file_id')) {
                        $file = $stream['file'];
                    } else {
                        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                        $file = file_get_contents($pdf);
                    }
                    $this->download_response(strtolower($ext), $file, $download_name, $disposition);
                    if (has_action('e2pdf_controller_frontend_e2pdf_download_success')) {
                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                        do_action('e2pdf_controller_frontend_e2pdf_download_success', $uid, $entry->get('entry'), base64_encode($file));
                    }
                    exit;
                }
            } elseif ($entry->get_data('template_id') && ($entry->get_data('dataset') || $entry->get_data('dataset2')) && $template->load($entry->get_data('template_id'))) {

                $template->extension()->set('entry', $entry);

                if ($entry->get_data('dataset') !== false) {
                    $template->extension()->set('dataset', $entry->get_data('dataset'));
                }

                if ($entry->get_data('dataset2') !== false) {
                    $template->extension()->set('dataset2', $entry->get_data('dataset2'));
                }

                if ($entry->get_data('user_id') !== false) {
                    $template->extension()->set('user_id', $entry->get_data('user_id'));
                }

                if ($entry->get_data('wc_order_id') !== false) {
                    $template->extension()->set('wc_order_id', $entry->get_data('wc_order_id'));
                }

                if ($entry->get_data('wc_product_item_id') !== false) {
                    $template->extension()->set('wc_product_item_id', $entry->get_data('wc_product_item_id'));
                }

                if ($entry->get_data('storing_engine') !== false) {
                    $template->extension()->set('storing_engine', $entry->get_data('storing_engine'));
                }

                if ($entry->get_data('args') !== false) {
                    $template->extension()->set('args', $entry->get_data('args'));
                }

                $downloadable = apply_filters('e2pdf_pdf_downloadable', true, $uid, $entry->get('entry'));
                if ($downloadable && $template->extension()->verify()) {
                    if ($template->get('actions')) {
                        $model_e2pdf_action = new Model_E2pdf_Action();
                        $model_e2pdf_action->load($template->extension());
                        $actions = $model_e2pdf_action->process_global_actions($template->get('actions'));
                        foreach ($actions as $action) {
                            if (isset($action['action'])) {
                                switch (true) {
                                    case $action['action'] == 'disable_access_by_url' && isset($action['success']):
                                    case $action['action'] == 'enable_access_by_url' && !isset($action['success']):
                                        $error_message = '';
                                        if (!empty($action['error_message'])) {
                                            $error_message = $template->extension()->render($action['error_message']);
                                        }
                                        $error_message = $error_message ? $error_message : __('Access Denied!', 'e2pdf');
                                        if (isset($_SERVER['HTTP_X_E2PDF_REQUEST'])) {
                                            $response = [
                                                'error' => $error_message,
                                            ];
                                            $this->json_response_ajax($response, 403);
                                        } else {
                                            wp_die($error_message, '', ['exit' => true]);
                                        }
                                        break;
                                    case $action['action'] == 'redirect_access_by_url' && isset($action['success']):
                                        $redirect_url = '';
                                        if (!empty($action['redirect_url'])) {
                                            $redirect_url = $template->extension()->render($action['redirect_url']);
                                        }
                                        if ($redirect_url) {
                                            $redirect_url = apply_filters('e2pdf_download_redirect_access_by_url', esc_url_raw($redirect_url), $entry);
                                            if (isset($_SERVER['HTTP_X_E2PDF_REQUEST'])) {
                                                $response = [
                                                    'redirect_url' => $redirect_url,
                                                    'redirect_error_message' => !empty($action['redirect_error_message']) ? str_replace('%s', $redirect_url, $action['redirect_error_message']) : 'Access denied. Please, click <a href="' . $redirect_url . '" target="_blank">here</a> for more details...',
                                                ];
                                                $this->json_response_ajax($response, 303);
                                            } else {
                                                // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
                                                wp_redirect($redirect_url);
                                                exit;
                                            }
                                        }
                                        break;
                                    default:
                                        break;
                                }
                            }
                        }
                    }

                    if ($entry->get_data('flatten') !== false) {
                        $template->set('flatten', $entry->get_data('flatten'));
                    }

                    if ($entry->get_data('format') !== false) {
                        $template->set('format', $entry->get_data('format'));
                    }

                    if ($entry->get_data('password') !== false) {
                        $template->set('password', $entry->get_data('password'));
                    } else {
                        $template->set('password', $template->extension()->render($template->get('password')));
                    }

                    if ($entry->get_data('dpdf') !== false) {
                        $template->set('dpdf', $entry->get_data('dpdf'));
                    } else {
                        $template->set('dpdf', $template->extension()->render($template->get('dpdf')));
                    }

                    if ($entry->get_data('owner_password') !== false) {
                        $template->set('owner_password', $entry->get_data('owner_password'));
                    } else {
                        $template->set('owner_password', $template->extension()->render($template->get('owner_password')));
                    }

                    if ($entry->get_data('meta_title') !== false) {
                        $template->set('meta_title', $entry->get_data('meta_title'));
                    } else {
                        $template->set('meta_title', $template->extension()->render($template->get('meta_title')));
                    }

                    if ($entry->get_data('meta_subject') !== false) {
                        $template->set('meta_subject', $entry->get_data('meta_subject'));
                    } else {
                        $template->set('meta_subject', $template->extension()->render($template->get('meta_subject')));
                    }

                    if ($entry->get_data('meta_author') !== false) {
                        $template->set('meta_author', $entry->get_data('meta_author'));
                    } else {
                        $template->set('meta_author', $template->extension()->render($template->get('meta_author')));
                    }

                    if ($entry->get_data('meta_keywords') !== false) {
                        $template->set('meta_keywords', $entry->get_data('meta_keywords'));
                    } else {
                        $template->set('meta_keywords', $template->extension()->render($template->get('meta_keywords')));
                    }

                    if ($entry->get_data('name') !== false) {
                        $template->set('name', $entry->get_data('name'));
                    } else {
                        $template->set(
                                'name',
                                $template->extension()->render(
                                        $this->helper->load('translator')->pre_translate($template->get('name'), $template->get('ID'), 'name', 'template')
                                )
                        );
                    }
                    if (!$template->get('name')) {
                        $template->set(
                                'name',
                                $template->extension()->render(
                                        $this->helper->load('translator')->pre_translate($template->get('title'), $template->get('ID'), 'title', 'template')
                                )
                        );
                    }
                    if (!$template->get('name')) {
                        $template->set(__('(no title)', 'e2pdf'));
                    }

                    $disposition = 'attachment';
                    if ($entry->get_data('inline') !== false) {
                        if ($entry->get_data('inline')) {
                            $disposition = 'inline';
                        }
                    } elseif ($template->get('inline')) {
                        $disposition = 'inline';
                    }

                    /* Bug-fix with on the Chrome + iOS PDF inline download */
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    if ($disposition == 'inline' && !isset($_GET['v'])) {
                        if (get_option('e2pdf_download_inline_chrome_ios_fix', '0') == '1' && $this->helper->load('server')->isIOS() && $this->helper->load('server')->isChrome()) {
                            $download_name = apply_filters('e2pdf_controller_frontend_e2pdf_download_name', $template->get('name'), $entry->get('uid'), $entry->get('entry'));
                            $url_data = [
                                'page' => 'e2pdf-download',
                                'uid' => $uid,
                                'download_name' => rawurlencode($download_name . '.' . ($template->get('format') == 'jpg' ? 'jpg' : 'pdf')),
                                'v' => $this->helper->get('version'),
                            ];
                            $url = $this->helper->get_frontend_pdf_url(
                                    $url_data, false,
                                    [
                                        'e2pdf_model_shortcode_site_url',
                                        'e2pdf_model_shortcode_e2pdf_redirect_site_url',
                                    ]
                            );
                            // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
                            wp_redirect($url);
                            exit;
                        } elseif (get_option('e2pdf_download_inline_fallback_viewer', '0') == '1' && !$this->helper->load('server')->isViewerSupported() && $template->get('format') == 'pdf') {
                            $download_name = apply_filters('e2pdf_controller_frontend_e2pdf_download_name', $template->get('name'), $entry->get('uid'), $entry->get('entry'));
                            $url_data = [
                                'page' => 'e2pdf-download',
                                'uid' => $uid,
                                'v' => $this->helper->get('version'),
                            ];
                            $url = $this->helper->get_frontend_pdf_url(
                                    $url_data, false,
                                    [
                                        'e2pdf_model_shortcode_site_url',
                                        'e2pdf_model_shortcode_e2pdf_redirect_site_url',
                                    ]
                            );
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                            $file = urlencode($url);

                            $classes = [
                                'e2pdf-hide-print',
                                'e2pdf-hide-editor',
                                'e2pdf-hide-secondary-toolbar',
                            ];

                            $viewer_url = add_query_arg(
                                    [
                                        'class' => implode(';', $classes),
                                        'file' => $file,
                                    ],
                                    plugins_url('assets/pdf.js/web/viewer.html', $this->helper->get('plugin_file_path'))
                            );
                            // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
                            wp_redirect($viewer_url);
                            exit;
                        }
                    }

                    $template->fill();
                    $request = $template->render();

                    if (isset($request['error'])) {
                        wp_die($request['error']);
                    } elseif ($request['file'] === '') {
                        wp_die(__('Something went wrong!', 'e2pdf'));
                    } else {
                        $entry->set('pdf_num', $entry->get('pdf_num') + 1);
                        $entry->save();
                        $download_name = apply_filters('e2pdf_controller_frontend_e2pdf_download_name', $template->get('name'), $entry->get('uid'), $entry->get('entry'));
                        $this->download_response($template->get('format'), $request['file'], $download_name, $disposition);
                        if (has_action('e2pdf_controller_frontend_e2pdf_download_success')) {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                            do_action('e2pdf_controller_frontend_e2pdf_download_success', $uid, $entry->get('entry'), base64_encode($request['file']));
                        }
                        exit;
                    }
                }
            }
        }
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }
}
