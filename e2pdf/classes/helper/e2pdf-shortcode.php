<?php

/**
 * File: /helper/e2pdf-shortcode.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Shortcode {

    private $helper;

    public function __construct() {
        $this->helper = Helper_E2pdf_Helper::instance();
    }

    public function get_shortcode_regex($tagnames = null) {
        if (version_compare(get_bloginfo('version'), '4.4.0', '<')) {
            global $shortcode_tags;

            if (empty($tagnames)) {
                $tagnames = array_keys($shortcode_tags);
            }
            $tagregexp = join('|', array_map('preg_quote', $tagnames));

            return '\\['
                    . '(\\[?)'
                    . "($tagregexp)"
                    . '(?![\\w-])'
                    . '('
                    . '[^\\]\\/]*'
                    . '(?:'
                    . '\\/(?!\\])'
                    . '[^\\]\\/]*'
                    . ')*?'
                    . ')'
                    . '(?:'
                    . '(\\/)'
                    . '\\]'
                    . '|'
                    . '\\]'
                    . '(?:'
                    . '('
                    . '[^\\[]*+'
                    . '(?:'
                    . '\\[(?!\\/\\2\\])'
                    . '[^\\[]*+'
                    . ')*+'
                    . ')'
                    . '\\[\\/\\2\\]'
                    . ')?'
                    . ')'
                    . '(\\]?)';
        } else {
            return get_shortcode_regex($tagnames);
        }
    }

    public function get_shortcode($shortcodes = array(), $key = '') {
        $shortcode = array();
        $shortcode[1] = $shortcodes[1][$key];
        $shortcode[2] = $shortcodes[2][$key];
        $shortcode[3] = $shortcodes[3][$key];
        $shortcode[4] = $shortcodes[4][$key];
        $shortcode[5] = $shortcodes[5][$key];
        $shortcode[6] = $shortcodes[6][$key];
        return $shortcode;
    }

    public function get_shortcode_content($shortcode_tag = '', $value = '') {
        $response = '';
        preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $value, $matches);
        $tagnames = array_intersect(array($shortcode_tag), $matches[1]);
        if (!empty($tagnames)) {
            preg_match('/' . $this->get_shortcode_regex($tagnames) . '/', $value, $shortcode);
            if (isset($shortcode[5])) {
                $response = $shortcode[5];
            }
        }
        return $response;
    }

    public function apply_path_attribute($value, $path = false, $delimiter = '.') {
        if ($path !== false) {
            if (!is_array($value) && !is_object($value)) {
                $value = $this->helper->load('convert')->is_unserialized_array($value);
            }
            $keys = explode($delimiter, $path);
            $obj = &$value;
            $found = true;
            foreach ($keys as $key) {
                if (is_array($obj) && isset($obj[$key])) {
                    $obj = &$obj[$key];
                } elseif (is_object($obj) && isset($obj->$key)) {
                    $obj = &$obj->$key;
                } else {
                    $found = false;
                    break;
                }
            }
            return $found ? $obj : '';
        }
        return '';
    }

    public function apply_attachment_attribute($value, $function = 'attachment_url', $size = 'thumbnail') {
        if (is_array($value)) {
            $attachments = array();
            foreach ($value as $post_meta_part) {
                if (!is_array($post_meta_part)) {
                    if ($function == 'attachment_url') {
                        $image = wp_get_attachment_url($post_meta_part);
                    } else {
                        $image = wp_get_attachment_image_url($post_meta_part, $size);
                    }
                    if ($image) {
                        $attachments[] = $image;
                    }
                }
            }
            return $attachments;
        } else {
            if ($function == 'attachment_url') {
                $image = wp_get_attachment_url($value);
            } else {
                $image = wp_get_attachment_image_url($value, $size);
            }
            return $image ? $image : '';
        }
    }

    public function apply_convert_attribute($convert, $post_meta, $implode = ',') {
        $type = false;
        switch (true) {
            case 0 === strpos($convert, 'term_id_to_'):
                $type = 'term';
                $convert = str_replace('term_id_to_', '', $convert);
                break;
            case 0 === strpos($convert, 'user_id_to_'):
                $type = 'user';
                $convert = str_replace('user_id_to_', '', $convert);
                break;
            case 0 === strpos($convert, 'post_id_to_'):
                $type = 'post';
                $convert = str_replace('post_id_to_', '', $convert);
                break;
        }
        if ($type && $post_meta) {
            $implode = $implode === false ? ',' : $implode;
            if (!is_array($post_meta)) {
                if (strpos($post_meta, ',') !== false) {
                    $post_meta = explode(',', $post_meta);
                }
            }
            if (is_array($post_meta)) {
                $sub_metas = array();
                foreach ($post_meta as $post_meta_part) {
                    if (!is_array($post_meta_part)) {
                        switch ($type) {
                            case 'term':
                                $sub_meta = get_term($post_meta_part);
                                break;
                            case 'user':
                                $sub_meta = get_userdata($post_meta_part);
                                break;
                            case 'post':
                                $sub_meta = get_post($post_meta_part);
                                break;
                        }
                        if ($sub_meta && !is_wp_error($sub_meta)) {
                            if ($convert == $type) {
                                $sub_metas[] = $sub_meta;
                            } else {
                                if (isset($sub_meta->$convert)) {
                                    $sub_metas[] = $sub_meta->$convert;
                                }
                            }
                        }
                    }
                }
                $post_meta = $sub_metas;
            } else {
                switch ($type) {
                    case 'term':
                        $sub_meta = get_term($post_meta);
                        break;
                    case 'user':
                        $sub_meta = get_userdata($post_meta);
                        break;
                    case 'post':
                        $sub_meta = get_post($post_meta);
                        break;
                }
                if ($sub_meta && !is_wp_error($sub_meta)) {
                    if ($convert == $type) {
                        $post_meta = $sub_meta;
                    } else {
                        if (isset($sub_meta->$convert)) {
                            $post_meta = $sub_meta->$convert;
                        }
                    }
                } else {
                    $post_meta = '';
                }
            }
        }
        return $post_meta;
    }

    public function apply_inline_shortcodes($value, $shortcode_tags, $extension) {
        preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $value, $matches);
        $tagnames = array_intersect($shortcode_tags, $matches[1]);
        if (!empty($tagnames)) {
            preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $value, $shortcodes);
            foreach ($shortcodes[0] as $key => $shortcode_value) {
                $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                $atts = shortcode_parse_atts($shortcode[3]);
                switch (true) {
                    case (0 === strpos($shortcode[2], 'e2pdf-for')):
                        $for_index = (0 === strpos($shortcode[2], 'e2pdf-for-')) ? str_replace('e2pdf-for-', '', $shortcode[2]) : 0;
                        $sub_value = $this->helper->load('for')->do_shortcode($atts, $shortcode[5], $for_index, $extension);

                        $sub_shortcode_tags = array_values(array_diff($shortcode_tags, [$shortcode[2]]));
                        $sub_shortcode_tags[] = 'e2pdf-for-' . $for_index + 1;
                        foreach ($sub_shortcode_tags as $sub_shortcode_key => $sub_shortcode_tag) {
                            if (false === strpos($sub_value, '[' . $sub_shortcode_tag)) {
                                unset($sub_shortcode_tags[$sub_shortcode_key]);
                            }
                        }
                        if (!empty($sub_shortcode_tags)) {
                            $sub_value = $this->apply_inline_shortcodes($sub_value, $sub_shortcode_tags, $extension);
                        }
                        $value = str_replace($shortcode_value, $sub_value, $value);
                        break;
                    case (0 === strpos($shortcode[2], 'e2pdf-acf-repeater')):
                        if ($extension instanceof Extension_E2pdf_Wordpress) {
                            if (!isset($atts['post_id']) && isset($extension->get('cached_post')->ID) && $extension->get('cached_post')->ID) {
                                if ($extension->get('item') == '-3') {
                                    $atts['post_id'] = 'user_' . $extension->get('cached_post')->ID;
                                } else {
                                    $atts['post_id'] = $extension->get('cached_post')->ID;
                                }
                            }
                        } elseif ($extension instanceof Extension_E2pdf_Woocommerce) {
                            if (!isset($atts['post_id']) && isset($extension->get('cached_post')->ID) && $extension->get('cached_post')->ID) {
                                if ($extension->get('item') == 'product_variation' && isset($extension->get('cached_post')->post_parent)) {
                                    $atts['post_id'] = $extension->get('cached_post')->post_parent;
                                } else {
                                    $atts['post_id'] = $extension->get('cached_post')->ID;
                                }
                            }
                        }

                        $acf_repeater_index = (0 === strpos($shortcode[2], 'e2pdf-acf-repeater-')) ? str_replace('e2pdf-acf-repeater-', '', $shortcode[2]) : 0;
                        $sub_value = $this->helper->load('acfrepeater')->do_shortcode($atts, $shortcode[5], $acf_repeater_index);

                        $sub_shortcode_tags = array_values(array_diff($shortcode_tags, [$shortcode[2]]));
                        $sub_shortcode_tags[] = 'e2pdf-acf-repeater-' . $acf_repeater_index + 1;
                        foreach ($sub_shortcode_tags as $sub_shortcode_key => $sub_shortcode_tag) {
                            if (false === strpos($sub_value, '[' . $sub_shortcode_tag)) {
                                unset($sub_shortcode_tags[$sub_shortcode_key]);
                            }
                        }
                        if (!empty($sub_shortcode_tags)) {
                            $sub_value = $this->apply_inline_shortcodes($sub_value, $sub_shortcode_tags, $extension);
                        }
                        $value = str_replace($shortcode_value, $sub_value, $value);
                        break;
                }
            }
        }
        return $value;
    }

    public function is_attachment($shortcode, $atts) {
        $true = [
            'true', '1',
        ];
        if (($shortcode[2] === 'e2pdf-save' && isset($atts['attachment']) && in_array(strtolower((string) $atts['attachment']), $true, true)) || $shortcode[2] === 'e2pdf-attachment') {
            return true;
        }
        return false;
    }
}
