<?php

/**
 * E2pdf Frontend Download Controller
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

class Controller_Frontend_E2pdf_Download extends Helper_E2pdf_View {

    /**
     * Frontend download action
     * 
     * @url page=e2pdf-download&uid=$uid
     */
    public function index_action() {
        global $wp_query;

        $name = '';
        $uid = false;

        if ($this->get->get('uid')) {
            $uid = $this->get->get('uid');
        } elseif (get_query_var('uid')) {
            $uid = get_query_var('uid');
        }

        $entry = new Model_E2pdf_Entry();
        if ($uid && $entry->load_by_uid($uid)) {
            $template = new Model_E2pdf_Template();
            if ($entry->get_data('pdf')) {
                if (file_exists($entry->get_data('pdf')) && $this->helper->load('filter')->is_downloadable($entry->get_data('pdf'))) {
                    $disposition = 'attachment';
                    if ($entry->get_data('inline')) {
                        $disposition = 'inline';
                    }

                    $ext = pathinfo($entry->get_data('pdf'), PATHINFO_EXTENSION);
                    if ($entry->get_data('name')) {
                        $name = $entry->get_data('name');
                    } else {
                        $name = basename($entry->get_data('pdf'), "." . $ext);
                    }

                    $file = base64_encode(file_get_contents($entry->get_data('pdf')));
                    $name = apply_filters('e2pdf_controller_frontend_e2pdf_download_name', $name, $uid, $entry->get('entry'));
                    $this->download_response(strtolower($ext), $file, $name, $disposition);
                    do_action('e2pdf_controller_frontend_e2pdf_download_success', $uid, $entry->get('entry'), $file);
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

                if ($template->extension()->verify()) {

                    if ($template->get('actions')) {

                        $model_e2pdf_action = new Model_E2pdf_Action();
                        $model_e2pdf_action->load($template->extension());
                        $actions = $model_e2pdf_action->process_global_actions($template->get('actions'));

                        foreach ($actions as $action) {
                            if (isset($action['action']) &&
                                    (
                                    ($action['action'] == 'access_by_url' && !isset($action['success'])) ||
                                    ($action['action'] == 'restrict_access_by_url' && isset($action['success']))
                                    )
                            ) {
                                $error_message = __('Access Denied!', 'e2pdf');
                                if (isset($action['error_message']) && $action['error_message']) {
                                    $error_message = $template->extension()->render($action['error_message']);
                                }
                                wp_die($error_message, '', array('exit' => true));
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
                        $template->set('name', $template->extension()->render($template->get('name')));
                    }

                    $disposition = 'attachment';
                    if ($entry->get_data('inline') !== false) {
                        if ($entry->get_data('inline')) {
                            $disposition = 'inline';
                        }
                    } elseif ($template->get('inline')) {
                        $disposition = 'inline';
                    }

                    $template->fill();
                    $request = $template->render();

                    if (isset($request['error'])) {
                        wp_die($request['error']);
                    } elseif ($request['file'] === '') {
                        wp_die(__('Something went wrong', 'e2pdf'));
                    } else {
                        $entry->set('pdf_num', $entry->get('pdf_num') + 1);
                        $entry->save();

                        if ($template->get('name')) {
                            $name = $template->get('name');
                        } else {
                            $name = $template->extension()->render($template->get_name());
                        }

                        $file = $request['file'];
                        $name = apply_filters('e2pdf_controller_frontend_e2pdf_download_name', $name, $uid, $entry->get('entry'));
                        $this->download_response($template->get('format'), $file, $name, $disposition);
                        do_action('e2pdf_controller_frontend_e2pdf_download_success', $uid, $entry->get('entry'), $file);
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
