<?php

/**
 * E2pdf Files Helper
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

class Helper_E2pdf_Files {

    private $files = array();
    private $helper;

    // construct
    public function __construct() {
        $this->helper = Helper_E2pdf_Helper::instance();
        $this->files = $_FILES;
    }

    // get
    public function get($key) {

        if (!$key) {
            if (!empty($this->files)) {
                return $this->files;
            } else {
                return false;
            }
        } else {

            if (isset($this->files[$key])) {
                return $this->files[$key];
            } else {
                return null;
            }
        }
    }

    // get upload max filesize
    public function get_upload_max_filesize() {
        $max_size = -1;
        if ($max_size < 0) {
            $post_max_size = $this->helper->load('convert')->to_bytes(ini_get('post_max_size'));
            if ($post_max_size > 0) {
                $max_size = $post_max_size;
            }

            $upload_max = $this->helper->load('convert')->to_bytes(ini_get('upload_max_filesize'));
            if ($upload_max > 0 && $upload_max < $max_size) {
                $max_size = $upload_max;
            }
        }
        return $this->helper->load('convert')->from_bytes($max_size);
    }

    // upload
    public function upload($file, $exts = [], $mimes = []) {
        $upload = [
            'name' => $file['name'],
            'tmp_name' => $file['tmp_name'],
            'type' => $file['type'],
            'ext' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)),
        ];
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $upload['error'] = __('Choose file to upload', 'e2pdf');
        } elseif (!empty($file['error'])) {
            $upload['error'] = $file['error'];
        } elseif (!is_readable($file['tmp_name'])) {
            $upload['error'] = __('TMP file is not readable', 'e2pdf');
        } elseif (!empty($exts) && !in_array($upload['ext'], $exts)) {
            $upload['error'] = sprintf(__('Only %s files allowed', 'e2pdf'), '.' . implode(', .', $exts));
        } elseif (!empty($mimes) && function_exists('finfo_open') && function_exists('finfo_file') && defined('FILEINFO_MIME_TYPE')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if ($mime && !in_array($mime, $mimes)) {
                    $upload['error'] = __('Invalid Type', 'e2pdf');
                }
            }
        } elseif (!empty($mimes) && !in_array($upload['type'], $mimes)) {
            $upload['error'] = __('Invalid Type', 'e2pdf');
        }
        return $upload;
    }
}
