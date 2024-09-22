<?php

/**
 * E2pdf Loader Helper
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

class Model_E2pdf_Model {

    protected $helper;

    public function __construct() {
        $this->helper = Helper_E2pdf_Helper::instance();
    }

}
