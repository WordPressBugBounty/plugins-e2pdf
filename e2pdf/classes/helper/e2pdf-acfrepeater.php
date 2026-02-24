<?php

/**
 * File: /helper/e2pdf-acfrepeater.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Acfrepeater {

    private $helper;

    public function __construct() {
        $this->helper = Helper_E2pdf_Helper::instance();
    }

    public function do_shortcode($atts = array(), $value = '', $for = 0) {
        $result = [];
        $field = isset($atts['field']) ? $atts['field'] : null;
        $post_id = isset($atts['post_id']) ? $atts['post_id'] : null;
        $implode = isset($atts['implode']) ? $atts['implode'] : '';
        $index = 0;
        if (function_exists('have_rows') && have_rows($field, $post_id)) {
            $total = count(get_field($field, $post_id));
            while (have_rows($field, $post_id)) {
                the_row();
                $result[] = $this->apply($value, $atts, $index, $for, $total);
                $index++;
            }
        }
        return implode($implode, $result);
    }

    public function apply($value, $atts, $index, $for = 0, $total = 0) {
        if ($value) {

            $field = isset($atts['field']) ? $atts['field'] : null;
            $post_id = isset($atts['post_id']) ? $atts['post_id'] : null;

            $for_index = $for ? '-' . $for : '';
            $evenodd = $index % 2 == 0 ? '0' : '1';
            $replace = array(
                '[e2pdf-acf-repeater-index' . $for_index . ']' => $index,
                '[e2pdf-acf-repeater-counter' . $for_index . ']' => $index + 1,
                '[e2pdf-acf-repeater-evenodd' . $for_index . ']' => $evenodd,
                '[e2pdf-acf-repeater-count' . $for_index . ']' => $total,
            );
            $value = str_replace(array_keys($replace), $replace, $value);
            $value = preg_replace('/\[(e2pdf-for-index|e2pdf-for-counter|e2pdf-for-key|e2pdf-for-evenodd|e2pdf-acf-repeater-index|e2pdf-acf-repeater-counter|e2pdf-acf-repeater-evenodd)(-\d+)?\]/', '#$1$2#', $value);
            $shortcode_tags = array(
                'acf',
            );
            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $value, $matches);
            $tagnames = array_intersect($shortcode_tags, $matches[1]);
            if (!empty($tagnames)) {
                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $value, $shortcodes);
                foreach ($shortcodes[0] as $key => $shortcode_value) {
                    $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                    $atts = shortcode_parse_atts($shortcode[3]);
                    $repeater = isset($atts['repeater']) ? $atts['repeater'] : $field;
                    if ($shortcode[2] === 'acf') {
                        if (!isset($atts['post_id']) && $post_id) {
                            $shortcode[3] .= ' post_id="' . $post_id . '"';
                        }
                        if ($field && isset($atts['field'])) {
                            if ($repeater !== $field) {
                                $value = str_replace($shortcode_value, '[' . $shortcode[2] . $shortcode[3] . ']', $value);
                            } else {
                                if (isset($atts['repeater'])) {
                                    $shortcode[3] .= ' field="' . $field . '_' . $index . '_' . $atts['field'] . '"';
                                } else {
                                    $shortcode[3] .= ' field="' . $field . '_' . $index . '_' . $atts['field'] . '" repeater="' . $field . '"';
                                }
                                $value = str_replace($shortcode_value, '[' . $shortcode[2] . $shortcode[3] . ']', $value);
                            }
                        } else {
                            $value = str_replace($shortcode_value, '', $value);
                        }
                    }
                }
            }
            $value = preg_replace('/#(e2pdf-for-index|e2pdf-for-counter|e2pdf-for-key|e2pdf-for-evenodd|e2pdf-acf-repeater-index|e2pdf-acf-repeater-counter|e2pdf-acf-repeater-evenodd)(-\d+)?#/', '[$1$2]', $value);
        }
        return $value;
    }
}
