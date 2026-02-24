<?php

/**
 * File: /helper/e2pdf-get.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Get {

    private $get = [];
    private $page;

    // construct
    public function __construct($url) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $args = array_merge(wp_parse_args($url), $_GET);
        if (isset($args['page'])) {
            $this->page = $args['page'];
            unset($args['page']);
        }
        $this->get = $args;
    }

    // get
    public function get($key = false) {
        if (!$key) {
            return !empty($this->get) ? $this->get : [];
        } else {
            return isset($this->get[$key]) ? $this->get[$key] : null;
        }
    }

    // get page
    public function get_page() {
        return $this->page;
    }
}
