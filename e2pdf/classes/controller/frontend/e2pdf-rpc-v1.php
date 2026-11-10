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
    public function zapier_action($rpc) {
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
    public function adobe_action($rpc) {
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
    public function gdrive_action($rpc) {
        if (is_a($rpc, 'Model_E2pdf_Rpc')) {
            switch ($rpc->get('action')) {
                case 'auth':
                    if ($rpc->get_arg('api_key') == get_option('e2pdf_gdrive_api_key')) {
                        if ($rpc->get_arg('code') || $rpc->get_arg('error')) {
                            if ($rpc->get_arg('code')) {
                                (new Model_E2pdf_Gdrive())->get_token($rpc->get_arg('code'));
                            }
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

    // mcp rpc service (streamable http transport, json responses)
    public function mcp_action($rpc) {
        if (is_a($rpc, 'Model_E2pdf_Rpc')) {
            $model_e2pdf_mcp = new Model_E2pdf_Mcp();
            $status = 200;
            $response = null;

            $api_url = sanitize_text_field(rawurldecode($rpc->get('action')));
            $mcp_api_url = (string) get_option('e2pdf_mcp_api_url', '');
            $api_key = $rpc->get_bearer_token();
            $mcp_api_key = (string) get_option('e2pdf_mcp_api_key', '');
            $user = get_userdata((int) get_option('e2pdf_mcp_user_id', '0'));

            if (!$mcp_api_url || !$api_url || !hash_equals($mcp_api_url, $api_url)) {
                $status = 404;
                $response = $model_e2pdf_mcp->error(null, -32000, 'Not Found');
            } elseif (get_option('e2pdf_mcp', '0') !== '1') {
                $status = 403;
                $response = $model_e2pdf_mcp->error(null, -32000, 'MCP Server is disabled');
            } elseif ($rpc->get_request_method() !== 'POST') {
                header('Allow: POST');
                $status = 405;
                $response = $model_e2pdf_mcp->error(null, -32000, 'Method Not Allowed');
            } elseif (!$mcp_api_key || !$api_key || !hash_equals($mcp_api_key, $api_key)) {
                header('WWW-Authenticate: Bearer');
                $status = 401;
                $response = $model_e2pdf_mcp->error(null, -32001, 'Unauthorized');
            } elseif (!$user) {
                $status = 403;
                $response = $model_e2pdf_mcp->error(null, -32000, 'MCP User is not set');
            } elseif (!$user->has_cap('manage_options') && !$user->has_cap('e2pdf') && !$user->has_cap('e2pdf_templates')) {
                $status = 403;
                $response = $model_e2pdf_mcp->error(null, -32000, 'MCP User has no E2Pdf capabilities (manage_options, e2pdf or e2pdf_templates)');
            } else {
                wp_set_current_user($user->ID);
                $message = json_decode($rpc->get_body(), true);
                if (!is_array($message)) {
                    $status = 400;
                    $response = $model_e2pdf_mcp->error(null, -32700, 'Parse error');
                } elseif (!empty($message) && array_keys($message) === range(0, count($message) - 1)) {
                    $response = [];
                    foreach ($message as $sub_message) {
                        $sub_response = $model_e2pdf_mcp->request($sub_message);
                        if ($sub_response !== null) {
                            $response[] = $sub_response;
                        }
                    }
                    if (empty($response)) {
                        $response = null;
                    }
                } else {
                    $response = $model_e2pdf_mcp->request($message);
                }
                if ($response === null) {
                    $status = 202;
                }
            }

            status_header($status);
            nocache_headers();
            if ($response !== null) {
                header('Content-Type: application/json; charset=utf-8');
                echo wp_json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            exit;
        }
    }

    public function restore_action($rpc) {
        if (is_a($rpc, 'Model_E2pdf_Rpc')) {
            switch ($rpc->get('action')) {
                case 'request':
                    if (get_transient('e2pdf_restore_key') && $rpc->get_arg('restore_key') === get_transient('e2pdf_restore_key')) {
                        wp_send_json_success(
                                [
                                    'restore_key' => get_transient('e2pdf_restore_key'),
                                ]
                        );
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
