<?php

/**
 * E2pdf Filter Model
 * 
 * @copyright  Copyright 2017 https://e2pdf.com
 * @license    GPLv3
 * @version    1
 * @link       https://e2pdf.com
 * @since      1.06.02
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_Filter extends Model_E2pdf_Model {

    public function pre_filter($content) {
        if (false === strpos($content, '[')) {
            return $content;
        }
        $shortcode_tags = array(
            'e2pdf-download',
            'e2pdf-save',
            'e2pdf-view',
            'e2pdf-adobesign',
            'e2pdf-attachment'
        );
        preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
        $tagnames = array_intersect($shortcode_tags, $matches[1]);
        if (!empty($tagnames)) {
            preg_match_all('/(?:(\[))(?>[^][]++|(?R))*\]/', $content, $matches);
            foreach ($matches[0] as $key => $shortcode_value) {
                if (false !== strpos($shortcode_value, 'e2pdf-download') ||
                        false !== strpos($shortcode_value, 'e2pdf-save') ||
                        false !== strpos($shortcode_value, 'e2pdf-view') ||
                        false !== strpos($shortcode_value, 'e2pdf-adobesign') ||
                        false !== strpos($shortcode_value, 'e2pdf-attachment')
                ) {
                    $new_shortcode_value = preg_replace('/([\w+\-]+)\=("|\')(.*?)\2/', '${1}=${2}[e2pdf-filter]${3}[/e2pdf-filter]${2}', $shortcode_value);
                    $content = str_replace($shortcode_value, $new_shortcode_value, $content);
                }
            }
        }
        return $content;
    }

    public function filter($content) {
        if (false === strpos($content, '[')) {
            return $content;
        }
        $shortcode_tags = array(
            'e2pdf-filter',
        );
        preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
        $tagnames = array_intersect($shortcode_tags, $matches[1]);
        if (!empty($tagnames)) {
            preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $content, $shortcodes);
            foreach ($shortcodes[0] as $key => $shortcode_value) {
                $shortcode = array();
                $shortcode[1] = $shortcodes[1][$key];
                $shortcode[2] = $shortcodes[2][$key];
                $shortcode[3] = $shortcodes[3][$key];
                $shortcode[4] = $shortcodes[4][$key];
                $shortcode[5] = $shortcodes[5][$key];
                $shortcode[6] = $shortcodes[6][$key];
                $content = str_replace($shortcode_value, do_shortcode_tag($shortcode), $content);
            }
        }
        return $content;
    }
}
