<?php

/**
 * File: /model/e2pdf-rpc.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_Rpc extends Model_E2pdf_Model {

    protected $options = array(
        'get' => array(),
    );

    public function __construct($url) {
        parent::__construct();
        $request_url = explode('/e2pdf-rpc/', $url);
        if (isset($request_url[1])) {
            list( $version, $method, $action ) = array_pad(explode('/', wp_parse_url($request_url[1], PHP_URL_PATH)), 3, '');
            $this->set('version', $version);
            $this->set('method', $method);
            $this->set('action', $action);
            $this->set('get', wp_parse_args(wp_parse_url($request_url[1], PHP_URL_QUERY)));
        }
    }

    public function get($key) {
        if (isset($this->options[$key])) {
            $value = $this->options[$key];
            if ($key == 'version' && !in_array($value, array('v1'), true)) {
                $value = 'v1';
            }
        } else {
            switch ($key) {
                case 'version':
                    $value = 'v1';
                    break;
                default:
                    $value = '';
                    break;
            }
        }
        return $value;
    }

    public function get_arg($key) {
        return isset($this->options['get'][$key]) ? sanitize_text_field(wp_unslash($this->options['get'][$key])) : '';
    }

    public function set($key, $value) {
        $this->options[$key] = $value;
    }

    public function get_request_method() {
        return strtoupper($this->helper->load('server')->get('REQUEST_METHOD'));
    }

    public function get_body() {
        if (!isset($this->options['body'])) {
            $body = file_get_contents('php://input'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $this->set('body', $body === false ? '' : $body);
        }
        return $this->options['body'];
    }

    public function get_bearer_token() {
        $authorization = '';
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorization = wp_unslash($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authorization = wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $header_key => $header_value) {
                if (strtolower($header_key) === 'authorization') {
                    $authorization = $header_value;
                    break;
                }
            }
        }
        // phpcs:enable
        if (is_string($authorization) && preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $authorization, $matches)) {
            return $matches[1];
        }
        return '';
    }
}
