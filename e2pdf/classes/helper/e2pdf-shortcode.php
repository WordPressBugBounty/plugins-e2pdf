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
    private $template_value = null;

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

    // e2pdf shortcodes in the data value are not rendered
    public function escape($value) {
        if (is_array($value)) {
            $escaped = [];
            foreach ($value as $key => $sub_value) {
                $escaped[$this->escape($key)] = $this->escape($sub_value);
            }
            return $escaped;
        }
        if (is_string($value)) {
            return str_replace(['[e2pdf-', '[/e2pdf-'], ['&#91;e2pdf-', '&#91;/e2pdf-'], $value);
        }
        return $value;
    }

    // render the template value
    public function do_shortcode($value) {
        $template_value = $this->template_value;
        $this->template_value = $value;
        $value = do_shortcode($value);
        $this->template_value = $template_value;
        return $value;
    }

    // render the template shortcode
    public function do_shortcode_tag($shortcode) {
        $template_value = $this->template_value;
        $this->template_value = '[' . $shortcode[2] . $shortcode[3] . $shortcode[4] . ']';
        $value = do_shortcode_tag($shortcode);
        $this->template_value = $template_value;
        return $value;
    }

    // e2pdf data shortcode is rendered only if it is written in the template value
    public function filter_pre_do_shortcode_tag($output, $tag, $attr, $m) {
        if ($this->template_value === null) {
            return $output;
        }
        $data = in_array($tag, $this->helper->shortcodes('data'), true);
        if ($tag === 'e2pdf-content' && is_array($attr) && !empty($attr['id']) && !empty($attr['key'])) {
            $data = true;
        }
        if (!$data) {
            return $output;
        }
        if (false !== strpos($this->template_value, '[' . $tag . $m[3] . $m[4] . ']')) {
            return $output;
        }
        if (apply_filters('e2pdf_shortcode_enable_' . str_replace('-', '_', $tag), false)) {
            return $output;
        }
        return '';
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
                        $sub_shortcode_tags[] = 'e2pdf-for-' . ($for_index + 1);
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
                        $sub_shortcode_tags[] = 'e2pdf-acf-repeater-' . ($acf_repeater_index + 1);
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

    // adds the attributes to the shortcode if they are not set, empty values are skipped
    public function add(&$shortcode, &$atts, $values) {
        if (!is_array($atts)) {
            $atts = [];
        }
        foreach ($values as $key => $value) {
            if (!isset($atts[$key]) && (string) $value !== '') {
                $atts[$key] = $value;
                $shortcode[3] .= ' ' . $key . '="' . $value . '"';
            }
        }
    }

    // sets the attributes of the shortcode
    public function set(&$shortcode, &$atts, $values) {
        if (!is_array($atts)) {
            $atts = [];
        }
        foreach ($values as $key => $value) {
            $atts[$key] = $value;
            $shortcode[3] .= ' ' . $key . '="' . $value . '"';
        }
    }

    // hash "template|dataset,wc_order_id|context|expires:hash", one per shortcode
    // $atts - attributes inserted by e2pdf, they are 100% trusted and accepted for everyone
    // $context - place where the shortcode was hashed by e2pdf
    // $shortcode_atts - attributes of the shortcode, the hash is valid only for its template id
    public function hash($atts = [], $context = '', $shortcode_atts = []) {
        $context = preg_replace('/[^a-z0-9_]/', '', (string) $context);
        $keys = implode(',', array_unique(array_filter((array) $atts, 'strlen')));
        if (!$keys && !$context) {
            return '';
        }
        // lifetime of the hash in seconds, 0 disables the expiry, the site is bound to the hash
        $ttl = (int) apply_filters('e2pdf_shortcode_hash_ttl', DAY_IN_SECONDS, $context);
        $template_id = isset($shortcode_atts['id']) ? (int) $shortcode_atts['id'] : 0;
        $data = $template_id . '|' . $keys . '|' . $context . '|' . ($ttl > 0 ? time() + $ttl : 0);
        return $data . ':' . substr(hash_hmac('sha256', get_current_blog_id() . ':' . $data, wp_salt('e2pdf_hash')), 0, 20);
    }

    // trusted attributes and expiry of the hash, empty when it is missing, wrong or expired
    public function get_hash_data($atts) {
        if (empty($atts['hash']) || !is_string($atts['hash']) || false === strpos($atts['hash'], ':')) {
            return [];
        }
        $data = substr($atts['hash'], 0, strpos($atts['hash'], ':'));
        $parts = explode('|', $data);
        $hash = substr(hash_hmac('sha256', get_current_blog_id() . ':' . $data, wp_salt('e2pdf_hash')), 0, 20);
        if (4 !== count($parts) || !hash_equals($data . ':' . $hash, $atts['hash']) || ((int) $parts[3] > 0 && (int) $parts[3] < time())) {
            return [];
        }
        if ((int) $parts[0] !== (isset($atts['id']) ? (int) $atts['id'] : 0)) {
            return [];
        }
        return [
            'keys' => array_filter(explode(',', (string) $parts[1]), 'strlen'),
            'context' => (string) $parts[2],
            'expires' => (int) $parts[3],
        ];
    }

    // shortcode or shortcodes of the content hidden from the form plugin until e2pdf runs them
    public function wrap($value, $tagnames = []) {
        if (is_array($value)) {
            return '{{' . $value[2] . $value[3] . '}}';
        }
        if (!is_string($value) || empty($tagnames)) {
            return $value;
        }
        $tags = implode('|', array_map('preg_quote', $tagnames));
        return preg_replace('/\[((?:' . $tags . ')(?=[\s\]\/])[^\]]*?)\]/', '{{$1}}', $value);
    }

    // signed shortcodes are restored, other shortcodes stay as text
    // $signed = false restores all wrapped shortcodes, submitted values must be escaped by the extension
    public function unwrap($content, $tagnames = [], $signed = true) {
        if (!is_string($content) || empty($tagnames)) {
            return $content;
        }
        $tags = implode('|', array_map('preg_quote', $tagnames));
        if ($signed) {
            $content = preg_replace('/\[(' . $tags . ')(?=[\s\]\/])/', '&#91;$1', $content);
        }
        if (false === strpos($content, '{{')) {
            return $content;
        }
        return preg_replace_callback(
                '/\{\{((?:' . $tags . ')(?=[\s\}\/])[^\}]*?)\}\}/',
                function ($matches) use ($signed) {
                    if (!$signed || $this->get_hash_data((array) shortcode_parse_atts($matches[1]))) {
                        return '[' . $matches[1] . ']';
                    }
                    return $matches[0];
                },
                $content
        );
    }

    // e2pdf download / view html in form messages filtered with wp_kses_post()
    public function filter_wp_kses_allowed_html($html, $context) {
        if (is_array($context)) {
            return $html;
        }
        if ($context === 'post') {
            $html['iframe']['name'] = true;
            $html['iframe']['onload'] = ['value_callback' => [$this, 'is_onload']];
            $html['iframe']['style'] = true;
            $html['iframe']['class'] = true;
            $html['iframe']['width'] = true;
            $html['iframe']['height'] = true;
            $html['iframe']['src'] = true;
            $html['iframe']['preload'] = true;
            $html['iframe']['resolution'] = true;
            $html['iframe']['cursor'] = true;
            $html['iframe']['scroll'] = true;
            $html['iframe']['spread'] = true;
            $html['img']['onload'] = ['value_callback' => [$this, 'is_onload']];
            $html['img']['style'] = true;
            $html['img']['preload'] = true;
            $html['img']['class'] = true;
            $html['a']['lid'] = true;
            $html['a']['download'] = true;
            $html['a']['class'] = true;
        }
        return $html;
    }

    // onload values of e2pdf download / view html
    public function is_onload($value) {
        return (bool) preg_match('/^e2pdfViewer\.(iframeLoad|imageLoad|autoDownload)\((this|\'[a-f0-9.]+\')\);?$/', html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5));
    }

    public function filter_safe_style_css($styles) {
        $styles[] = 'display';
        $styles[] = 'width';
        $styles[] = 'height';
        $styles[] = 'border-width';
        $styles[] = 'border';
        return $styles;
    }

}
