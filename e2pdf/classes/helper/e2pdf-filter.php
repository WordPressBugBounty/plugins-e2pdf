<?php

/**
 * File: /helper/e2pdf-filter.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Filter {

    // is stream
    public function is_stream($file_path) {
        if (strpos($file_path, '://') > 0) {
            $wrappers = array(
                'phar',
            );
            if (function_exists('stream_get_wrappers')) {
                $wrappers = stream_get_wrappers();
            }

            foreach ($wrappers as $wrapper) {
                if (in_array($wrapper, ['http', 'https', 'file'], true)) {
                    continue;
                }
                if (stripos($file_path, $wrapper . '://') === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    // is downloadable
    public function is_downloadable($file_path) {
        if (!$file_path || !$this->is_downloadable_ext($file_path)) {
            return false;
        }
        return true;
    }

    // is downlodable ext
    public function is_downloadable_ext($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $allowed = apply_filters(
                'e2pdf_helper_filter_is_downloadable_allowed_extensions',
                ['pdf', 'jpg', 'doc', 'docx']
        );
        return in_array($ext, $allowed, true);
    }

    // filter html tags
    public function filter_html_tags($value) {
        if ($value) {
            $tags = array(
                'script',
                'style',
            );
            foreach ($tags as $tag) {
                $value = preg_replace('#<' . $tag . '(.*?)>(.*?)</' . $tag . '>#is', '', $value);
            }
        }
        return $value;
    }
}
