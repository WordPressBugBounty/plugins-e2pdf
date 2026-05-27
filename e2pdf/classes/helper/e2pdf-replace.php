<?php

/**
 * File: /helper/e2pdf-replace.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Replace {

    public function replace($search, $replace, $string = '') {
        if (!$string) {
            return false;
        }
        $found = false;
        foreach ($search as $key => $value) {
            if (stripos($string, $value) !== false) {
                $found = true;
                break;
            }
        }
        if ($found) {
            return str_replace($search, $replace, $string);
        }
        return false;
    }
}
