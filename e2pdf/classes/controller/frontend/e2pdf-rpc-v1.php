<?php

/**
 * File: /controller/frontend/e2pdf-rpc-v1.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Controller_Frontend_E2pdf_Rpc_V1 extends Helper_E2pdf_View {

    // zapier rpc service
    public function zapier($rpc) {
        if (is_a($rpc, 'Model_E2pdf_Rpc')) {
            switch ($rpc->get('action')) {
                case 'auth':
                    if ($rpc->get_arg('api_key') == get_option('e2pdf_zapier_api_key')) {
                        wp_send_json_success();
                    } else {
                        wp_send_json_error(null, 401);
                    }
                    break;
                default:
                    break;
            }
        }
    }

    // adobe rpc service
    public function adobe($rpc) {
        if (is_a($rpc, 'Model_E2pdf_Rpc')) {
            switch ($rpc->get('action')) {
                case 'auth':
                    if ($rpc->get_arg('api_key') == get_option('e2pdf_adobe_api_key')) {
                        if ($rpc->get_arg('code')) {
                            $this->redirect(
                                    $this->helper->get_url(
                                            [
                                                'page' => 'e2pdf-integrations',
                                                'action' => 'adobesign',
                                                'code' => $rpc->get_arg('code'),
                                                '_wpnonce' => wp_create_nonce('e2pdf_adobe'),
                                            ]
                                    )
                            );
                        }
                    }
                    break;
                default:
                    break;
            }
        }
    }

    // gdrive rpc service
    public function gdrive($rpc) {
        if (is_a($rpc, 'Model_E2pdf_Rpc')) {
            switch ($rpc->get('action')) {
                case 'auth':
                    if ($rpc->get_arg('api_key') == get_option('e2pdf_gdrive_api_key')) {
                        if ($rpc->get_arg('code')) {
                            (new Model_E2pdf_Gdrive())->get_token($rpc->get_arg('code'));
                            $this->redirect(
                                    $this->helper->get_url(
                                            [
                                                'page' => 'e2pdf-integrations',
                                                'action' => 'gdrive',
                                            ]
                                    )
                            );
                        }
                    } else {
                        wp_send_json_error(null, 401);
                    }
                    break;
                default:
                    break;
            }
        }
    }
}
