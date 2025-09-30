<?php

/**
 * E2pdf Convert Helper
 * 
 * @copyright  Copyright 2017 https://e2pdf.com
 * @license    GPLv3
 * @version    1
 * @link       https://e2pdf.com
 * @since      1.01.02
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Convert {

    private $helper;

    public function __construct() {
        $this->helper = Helper_E2pdf_Helper::instance();
    }

    public function to_hex_color($hex = '') {
        $color = array(
            0x00, 0x00, 0x00
        );
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $color = array(
                hexdec(substr($hex, 0, 1)),
                hexdec(substr($hex, 1, 1)),
                hexdec(substr($hex, 2, 1))
            );
        } elseif (strlen($hex) === 6) {
            $color = array(
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2))
            );
        }
        return $color;
    }

    /**
     * Convert from bytes to Human View
     * 
     * @param int $size - Size in Bytes
     * 
     * @return string - Converted size
     */
    public function from_bytes($size) {
        $unit = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . '' . $unit[$i];
    }

    public function to_bytes($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }

    public function to_file_dir($file_dir = '') {
        if ($file_dir) {
            $file_dir = str_replace('./', '.', $file_dir);
        }

        return $file_dir;
    }

    public function to_file_name($file_name = '') {
        if ($file_name) {
            $search = array(
                '/',
                '\\',
                '"',
                '&#91;',
                '&#93;',
                '&#39;',
                '&#34;',
                '&#91;',
                '&#93;',
                '&amp;',
                ';',
            );
            $replace = array(
                '',
                '_',
                '',
                '[',
                ']',
                '\'',
                '',
                '[',
                ']',
                '&',
                ''
            );

            $file_name = str_replace($search, $replace, $file_name);
        }

        return $file_name;
    }

    /*
      stritr - case insensitive version of strtr
      Author: Alexander Peev
      Posted in PHP.NET
     */

    public function stritr($haystack, $needle) {
        if (is_array($needle)) {
            $pos1 = 0;
            $result = $haystack;
            while (count($needle) > 0) {
                $positions = array();
                foreach ($needle as $from => $to) {
                    if (($pos2 = stripos($result, $from, $pos1)) === FALSE) {
                        unset($needle[$from]);
                    } else {
                        $positions[$from] = $pos2;
                    }
                }
                if (count($needle) <= 0) {
                    break;
                }

                $winner = min($positions);
                $key = array_search($winner, $positions);
                $result = (substr($result, 0, $winner) . $needle[$key] . substr($result, ($winner + strlen($key))));
                $pos1 = ($winner + strlen($needle[$key]));
            }
            return $result;
        } else {
            return $haystack;
        }
    }

    public function path_to_url($path = '') {
        $url = str_replace(
                wp_normalize_path(untrailingslashit(ABSPATH)), site_url(), wp_normalize_path($path)
        );
        return esc_url_raw($url);
    }

    public function unserialize($value) {
        /* Compatibility fix with Docket Cache */
        if (is_array($value)) {
            return $value;
        }
        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            return @unserialize($value);
        } else {
            return @unserialize($value, array('allowed_classes' => false));
        }
    }

    public function is_content_key($content_key = false, $value = '') {
        $response = '';
        if ($content_key) {
            $shortcode_tags = array(
                'e2pdf-content'
            );
            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $value, $matches);
            $tagnames = array_intersect($shortcode_tags, $matches[1]);
            if (!empty($tagnames)) {
                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $value, $shortcodes);
                foreach ($shortcodes[0] as $key => $shortcode_value) {
                    $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                    $atts = shortcode_parse_atts($shortcode[3]);
                    if (isset($atts['key']) && $atts['key'] == $content_key) {
                        $response = $shortcode[5];
                    }
                }
            }
        }
        return $response;
    }

    public function is_string_array($value) {
        if (!is_array($value)) {
            $value = !empty($value) ? (array) $value : array();
        }
        return $value;
    }

    public function is_unserialized_array($value) {
        if (!is_array($value)) {
            if (is_serialized($value)) {
                $value = $this->unserialize($value);
            }
        }
        return is_array($value) ? $value : array();
    }

    public function load_html($source, $dom, $form = false) {
        libxml_use_internal_errors(true);
        $options = defined('LIBXML_HTML_NOIMPLIED') && defined('LIBXML_HTML_NODEFDTD') ? LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD : 0;
        if ($form && $options) {
            $source = '<html>' . $source . '</html>';
        }
        if (function_exists('mb_encode_numericentity')) {
            $html = $dom->loadHTML($this->html_entities($source), $options);
        } else {
            $html = $dom->loadHTML('<?xml encoding="UTF-8">' . $source, $options);
        }
        libxml_clear_errors();
        return $html;
    }

    public function html_entities($source) {
        if (!function_exists('mb_encode_numericentity')) {
            return $source;
        }
        $map = [
            0x80, 0xFFFF, 0, 0xFFFF
        ];
        try {
            return mb_encode_numericentity($source, $map, 'UTF-8');
        } catch (Exception $e) {
            if (function_exists('mb_detect_encoding') && function_exists('mb_detect_order') && function_exists('iconv')) {
                $charset = mb_detect_encoding($source, mb_detect_order(), true);
                $source = iconv($charset, 'UTF-8//IGNORE', $source);
                return mb_encode_numericentity($source, $map, 'UTF-8');
            }
            return $source;
        }
    }

    public function html_entities_decode($source) {
        return html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
