<?php

/**
 * File: /model/e2pdf-license.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_License extends Model_E2pdf_Model {

    private $license;

    public function __construct($license) {
        parent::__construct();
        $this->license = $license;
    }

    public function get($key) {
        if (isset($this->license[$key])) {
            return $this->license[$key];
        } else {
            return false;
        }
    }
}
