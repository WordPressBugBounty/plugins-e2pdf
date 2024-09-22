<?php

/**
 * E2Pdf Properties Helper
 * @copyright  Copyright 2017 https://e2pdf.com
 * @license    GPLv3
 * @version    1
 * @link       https://e2pdf.com
 * @since      1.08.08
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Properties {

    public function apply($field = array(), $value = '') {
        if ($value) {
            if (isset($field['properties']['nl2br']) && $field['properties']['nl2br']) {
                $value = nl2br($value);
            }
            if (isset($field['properties']['preg_pattern']) && $field['properties']['preg_pattern']) {
                $value = $this->preg_replace($field['properties']['preg_pattern'], isset($field['properties']['preg_replacement']) ? $field['properties']['preg_replacement'] : '', $value);
            }
            if (isset($field['properties']['preg_match_all_pattern']) && $field['properties']['preg_match_all_pattern']) {
                $value = $this->preg_match_all($field['properties']['preg_match_all_pattern'], isset($field['properties']['preg_match_all_output']) ? $field['properties']['preg_match_all_output'] : '', $value);
            }
            if (isset($field['properties']['html_worker']) && $field['properties']['html_worker']) {
                $value = $this->html_worker($value);
            }
        }
        return $value;
    }

    public function preg_replace($pattern = '', $replacement = '', $value = '') {
        if ($pattern && $value) {
            $value = @preg_replace($pattern, $replacement, $value); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
        return $value;
    }

    public function preg_match_all($pattern = '', $output = '', $value = '') {
        if ($pattern && $value) {
            @preg_match_all($pattern, $value, $path_value); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            $path_parts = explode('.', $output);
            $value = '';
            if (!empty($path_value)) {
                $found = true;
                foreach ($path_parts as $path_part) {
                    if (isset($path_value[$path_part])) {
                        $path_value = &$path_value[$path_part];
                    } else {
                        $found = false;
                        break;
                    }
                }
                if ($found) {
                    if (is_array($path_value)) {
                        $value = serialize($path_value); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                    } else {
                        $value = $path_value;
                    }
                }
            }
        }
        return $value;
    }

    public function html_worker($value) {
        if ($value) {
            $value = preg_replace('#(src|href)(=[\'"])(/)#i', '$1$2' . get_site_url() . '/', $value);
        }
        return $value;
    }
}
