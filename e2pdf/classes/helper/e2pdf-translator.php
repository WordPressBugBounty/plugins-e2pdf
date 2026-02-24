<?php

/**
 * File: /helper/e2pdf-translator.php
 * 
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Translator {

    private $translator;
    private $translation;

    public function __construct() {

        $this->translation = get_option('e2pdf_pdf_translation', '2');

        if ($this->translation !== '0') {
            /**
             * Translate Multilingual sites – TranslatePress
             * https://wordpress.org/plugins/translatepress-multilingual/
             */
            if (class_exists('E2pdf_TRP_Translator')) {
                $this->translator = new E2pdf_TRP_Translator($this->translation);
            }

            /**
             * Weglot Translate – Translate your WordPress website and go multilingual
             * https://wordpress.org/plugins/weglot/
             */
            if (class_exists('E2pdf_Weglot_Translator')) {
                $this->translator = new E2pdf_Weglot_Translator($this->translation);
            }

            /**
             * The WordPress Multilingual Plugin
             * https://wpml.org/
             */
            if (class_exists('E2pdf_WPML_Translator')) {
                $this->translator = new E2pdf_WPML_Translator($this->translation);
            }

            /**
             * Polylang
             * https://wordpress.org/plugins/polylang/
             */
            if (class_exists('E2pdf_Polylang_Translator')) {
                $this->translator = new E2pdf_Polylang_Translator($this->translation);
            }
        }
    }

    public function translator() {
        return $this->translator;
    }

    public function isWPML() {
        return ($this->translator && is_a($this->translator, 'E2pdf_WPML_Translator')) ? true : false;
    }

    public function isTRP() {
        return ($this->translator && is_a($this->translator, 'E2pdf_TRP_Translator')) ? true : false;
    }

    public function isPolylang() {
        return ($this->translator && is_a($this->translator, 'E2pdf_Polylang_Translator')) ? true : false;
    }

    public function pre_translate($string = '', $template_id = 0, $element_id = '', $type = '') {
        if ($string && $this->translator && method_exists($this->translator, 'pre_translate')) {
            $string = $this->translator->pre_translate($string, $template_id, $element_id, $type);
        }
        return $string;
    }

    public function translate($string = '', $type = 'default', $post_id = 0) {
        if ($string && $this->translator && method_exists($this->translator, 'translate')) {
            $translation = false;
            switch ($type) {
                case 'full':
                    if ($this->translation === '2') {
                        $translation = true;
                    }
                    break;
                case 'partial':
                    if (
                            $this->translation === '1' ||
                            (is_a($this->translator, 'E2pdf_Polylang_Translator') && $this->translation === '2')
                    ) {
                        $translation = true;
                    }
                    break;
                default:
                    $translation = true;
                    break;
            }
            if ($translation) {
                /**
                 * Translate Multilingual sites – TranslatePress
                 * https://wordpress.org/plugins/translatepress-multilingual/
                 */
                if (is_a($this->translator, 'E2pdf_TRP_Translator')) {
                    return $this->translator->translate($string);
                }


                /**
                 * Weglot Translate – Translate your WordPress website and go multilingual
                 * https://wordpress.org/plugins/weglot/
                 */
                if (is_a($this->translator, 'E2pdf_Weglot_Translator')) {
                    return $this->translator->translate($string);
                }

                /**
                 * Multilingual CMS (WPML)
                 * https://wpml.org/
                 */
                if (is_a($this->translator, 'E2pdf_WPML_Translator')) {
                    return $string;
                }

                /**
                 * Polylang
                 * https://wordpress.org/plugins/polylang/
                 */
                if (is_a($this->translator, 'E2pdf_Polylang_Translator')) {
                    return $this->translator->translate($string, $post_id);
                }
            }
        }

        return $string;
    }

    public function lang($post_id = 0) {
        if (!$this->translator) {
            return '';
        }
        if (method_exists($this->translator, 'lang')) {
            $lang = $this->translator->lang($post_id = 0);
        }
        return !empty($lang) ? $lang : '';
    }

    public function translate_url($url = '') {
        if ($url && $this->translator && method_exists($this->translator, 'flush')) {
            $url = $this->translator->translate_url($url);
        }
        return $url;
    }

    public function flush($template_id = 0) {
        if ($this->translator && method_exists($this->translator, 'flush')) {
            $this->translator->flush($template_id);
        }
    }

    public function update_translation($string = '', $template_id = 0, $element_id = '', $type = '') {
        if ($this->translator && method_exists($this->translator, 'update_translation')) {
            $this->translator->update_translation($string, $template_id, $element_id, $type);
        }
    }
}

if (class_exists('WeglotWP\Services\Translate_Service_Weglot') && function_exists('weglot_get_current_language') && function_exists('weglot_get_original_language')) {

    class E2pdf_Weglot_Translator {

        private $helper;
        private $translation;
        private $weglot;

        public function __construct($translation) {
            $this->helper = Helper_E2pdf_Helper::instance();
            $this->translation = $translation;
            if (weglot_get_current_language() != weglot_get_original_language()) {
                $this->weglot = new WeglotWP\Services\Translate_Service_Weglot();
            }
        }

        public function translate($string) {
            if ($this->weglot) {
                $string = str_replace(array('e2pdf-page-number', 'e2pdf-page-total'), array('e-2-p-d-f-p-a-g-e-n-u-m-b-e-r', 'e-2-p-d-f-p-a-g-e-t-o-t-a-l'), $string);
                $string = $this->weglot->weglot_treat_page($string);
                $string = str_replace(array('e-2-p-d-f-p-a-g-e-n-u-m-b-e-r', 'e-2-p-d-f-p-a-g-e-t-o-t-a-l'), array('e2pdf-page-number', 'e2pdf-page-total'), $string);
            }
            return $string;
        }

        public function translate_url($url = '') {
            if (weglot_get_current_language() != weglot_get_original_language()) {
                $request_url_service = weglot_get_request_url_service();
                $replaced_url = $request_url_service->create_url_object($url)->getForLanguage($request_url_service->get_current_language());
                if ($replaced_url) {
                    $url = $replaced_url;
                }
            }
            return $url;
        }

        public function lang($post_id = 0) {
            return weglot_get_current_language();
        }
    }

}


if (class_exists('TRP_Translate_Press')) {

    class E2pdf_TRP_Translator {

        private $helper;
        private $translation;
        private $keys = [];
        private $lang;
        private $default_lang;
        private $translatepress;

        public function __construct($translation) {
            global $TRP_LANGUAGE;

            $this->helper = Helper_E2pdf_Helper::instance();
            $this->translation = $translation;
            $this->lang = $TRP_LANGUAGE;
            $this->translatepress = TRP_Translate_Press::get_trp_instance();

            $settings = get_option('trp_settings', false);
            $this->default_lang = isset($settings['default-language']) ? $settings['default-language'] : null;
        }

        public function update_translation($string = '', $template_id = 0, $element_id = '', $type = '') {

            if (!$string) {
                return;
            }

            global $wpdb;
            $domain = 'E2Pdf Template ' . $template_id;

            switch ($this->translation) {
                case '1':
                    if ($type === 'template') {
                        $exists = $wpdb->get_row(
                                $wpdb->prepare(
                                        'SELECT id, original FROM `' . $wpdb->prefix . 'trp_gettext_original_strings` WHERE domain = %s AND original = %s',
                                        $domain,
                                        $string
                                )
                        );
                        if (!$exists) {
                            $wpdb->insert(
                                    $wpdb->prefix . 'trp_gettext_original_strings',
                                    array(
                                        'original' => $string,
                                        'domain' => $domain,
                                        'context' => '',
                                        'original_plural' => ''
                                    )
                            );
                        }
                        $this->keys[] = $string;
                    } elseif (false !== strpos($string, '[')) {
                        $shortcode_tags = array(
                            'e2pdf-translate',
                        );
                        preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $string, $matches);
                        $tagnames = array_intersect($shortcode_tags, $matches[1]);
                        if (!empty($tagnames)) {
                            preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $string, $shortcodes);
                            foreach ($shortcodes[0] as $key => $shortcode_value) {
                                $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                                $atts = shortcode_parse_atts($shortcode[3]);
                                if (!isset($atts['context']) && $shortcode[5]) {
                                    $substring = $shortcode[5];
                                    $exists = $wpdb->get_row(
                                            $wpdb->prepare(
                                                    'SELECT id, original FROM `' . $wpdb->prefix . 'trp_gettext_original_strings` WHERE domain = %s AND original = %s',
                                                    $domain,
                                                    $substring
                                            )
                                    );
                                    if (!$exists) {
                                        $wpdb->insert(
                                                $wpdb->prefix . 'trp_gettext_original_strings',
                                                array(
                                                    'original' => $substring,
                                                    'domain' => $domain,
                                                    'context' => '',
                                                    'original_plural' => ''
                                                )
                                        );
                                    }
                                    $this->keys[] = $substring;
                                }
                            }
                        }
                    }
                    break;
                case '2':
                    $exists = $wpdb->get_row(
                            $wpdb->prepare(
                                    'SELECT id, original FROM `' . $wpdb->prefix . 'trp_gettext_original_strings` WHERE domain = %s AND original = %s',
                                    $domain,
                                    $string
                            )
                    );
                    if (!$exists) {
                        $wpdb->insert(
                                $wpdb->prefix . 'trp_gettext_original_strings',
                                array(
                                    'original' => $string,
                                    'domain' => $domain,
                                    'context' => '',
                                    'original_plural' => ''
                                )
                        );
                    }
                    $this->keys[] = $string;
                    break;
                default:
                    break;
            }
        }

        public function get_strings($domain = '') {
            global $wpdb;
            if (!$domain) {
                return [];
            }
            return $wpdb->get_results(
                            $wpdb->prepare(
                                    'SELECT id, original FROM `' . $wpdb->prefix . 'trp_gettext_original_strings` WHERE domain = %s',
                                    $domain
                            )
                    );
        }

        public function pre_translate($string = '', $template_id = 0, $element_id = '', $type = '') {
            global $wpdb;
            $domain = 'E2Pdf Template ' . $template_id;
            if (!$this->lang || $this->lang == $this->default_lang) {
                return $string;
            }
            $translated = $wpdb->get_var($wpdb->prepare(
                            'SELECT translated FROM `' . $wpdb->prefix . 'trp_gettext_' . $this->lang . '` WHERE original = %s AND domain = %s AND (status = 2 OR status = 1) LIMIT 1',
                            $string,
                            $domain
                    )
            );
            return $translated ? $translated : $string;
        }

        public function flush($template_id) {
            global $wpdb;
            $domain = 'E2Pdf Template ' . $template_id;

            $strings = $this->get_strings($domain);

            if (!empty($strings) && is_array($strings)) {
                foreach ($strings as $name) {
                    if (!in_array($name->original, $this->keys, false)) {
                        foreach ($this->languages() as $lang) {
                            $wpdb->delete(
                                    $wpdb->prefix . 'trp_gettext_' . strtolower($lang),
                                    array('original_id' => $name->id)
                            );
                        }
                        $wpdb->delete(
                                $wpdb->prefix . 'trp_gettext_original_strings',
                                array('id' => $name->id)
                        );
                    }
                }
            }
            $this->keys = [];
        }

        public function translate($string = '', $template_id = 0, $element_id = '', $type = '') {
            add_filter('trp_get_existing_translations', array($this, 'filter_trp_get_existing_translations'), 99, 5);
            if (method_exists($this->translatepress, 'get_component')) {
                $translation_render = $this->translatepress->get_component('translation_render');
                if (is_object($translation_render) && method_exists($translation_render, 'translate_page')) {
                    $string = $translation_render->translate_page($string);
                }
            }
            remove_filter('trp_get_existing_translations', array($this, 'filter_trp_get_existing_translations'), 99);
            return $string;
        }

        public function translate_url($url = '') {
            if (method_exists($this->translatepress, 'get_component')) {
                $url_converter = $this->translatepress->get_component('url_converter');
                if (is_object($url_converter) && method_exists($url_converter, 'get_url_for_language')) {
                    $url = $url_converter->get_url_for_language($this->lang(), $url, '');
                }
            }
            return $url;
        }

        public function lang($post_id = 0) {
            return $this->lang;
        }

        public function languages() {
            $languages = [];
            if (method_exists($this->translatepress, 'get_component')) {
                $settings_component = $this->translatepress->get_component('settings');
                if (is_object($settings_component) && method_exists($settings_component, 'get_settings')) {
                    $settings = $settings_component->get_settings();
                    if (isset($settings['publish-languages']) && is_array($settings['publish-languages'])) {
                        $languages = $settings['publish-languages'];
                    }
                }
            }
            return $languages;
        }

        public function filter_trp_get_existing_translations($dictionary, $prepared_query, $strings_array, $language_code, $block_type) {
            global $wpdb;
            if (!is_array($strings_array) || count($strings_array) == 0) {
                return $dictionary;
            }
            if ($block_type == null) {
                $and_block_type = '';
            } else {
                $and_block_type = ' AND block_type = ' . $block_type;
            }
            $placeholders = array();
            $values = array();
            foreach ($strings_array as $string) {
                $wptexturized_string = wptexturize($string);
                if ($string !== $wptexturized_string) {
                    $placeholders[] = '%s';
                    $values[] = wptexturize($string);
                }
            }
            if (!empty($values)) {
                if (method_exists($this->translatepress, 'get_component')) {
                    $translation_query = $this->translatepress->get_component('query');
                    if (is_object($translation_query) && method_exists($translation_query, 'get_table_name')) {
                        $query = 'SELECT original,translated, status FROM `' . sanitize_text_field($this->translatepress->get_component('query')->get_table_name($language_code)) . '` WHERE status != ' . $this->translatepress->get_component('query')->get_constant_not_translated() . $and_block_type . " AND translated <>'' AND original IN ";
                        $query .= '( ' . implode(', ', $placeholders) . ' )';
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                        $additional_dictionary = $wpdb->get_results($wpdb->prepare($query, $values), OBJECT_K);
                        if (!empty($additional_dictionary) && is_array($additional_dictionary)) {
                            foreach ($additional_dictionary as $dictionary_key => $dictionary_object) {
                                if (false != strpos($dictionary_key, '&#82')) {
                                    $replace = array(
                                        '&#8220;' => '"',
                                        '&#8221;' => '"',
                                        '&#8217;' => "'",
                                        '&#8242;' => "'",
                                        '&#8243;' => '"',
                                        '&#8216;' => "'",
                                        '&#8211;' => '-',
                                        '&#8212;' => '-',
                                    );
                                    $untexturized_dictionary_key = str_replace(array_keys($replace), $replace, $dictionary_key);
                                    if ($untexturized_dictionary_key !== $dictionary_key && !isset($dictionary[$untexturized_dictionary_key]) && isset($dictionary_object->original)) {
                                        $new_dicitionary_object = $dictionary_object;
                                        $new_dicitionary_object->original = $untexturized_dictionary_key;
                                        $dictionary[$untexturized_dictionary_key] = $new_dicitionary_object;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            return $dictionary;
        }
    }

}

if (function_exists('icl_register_string') && function_exists('icl_t') && !defined('POLYLANG_VERSION') && !class_exists('E2pdf_WPML_Translator')) {

    // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
    class E2pdf_WPML_Translator {

        private $helper;
        private $translation;
        private $keys = [];

        public function __construct($translation) {
            $this->helper = Helper_E2pdf_Helper::instance();
            $this->translation = $translation;
        }

        public function update_translation($string = '', $template_id = 0, $element_id = '', $type = '') {
            if ($string) {
                switch ($this->translation) {
                    case '1':
                        if ($type === 'template') {
                            $context = 'E2Pdf Template ' . $template_id;
                            do_action('wpml_register_single_string', $context, $string, $string, false, apply_filters('wpml_default_language', null));
                            $this->keys[] = $string;
                        } elseif (false !== strpos($string, '[')) {
                            $shortcode_tags = array(
                                'e2pdf-translate',
                            );
                            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $string, $matches);
                            $tagnames = array_intersect($shortcode_tags, $matches[1]);
                            if (!empty($tagnames)) {
                                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $string, $shortcodes);
                                foreach ($shortcodes[0] as $key => $shortcode_value) {
                                    $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                                    $atts = shortcode_parse_atts($shortcode[3]);
                                    if (!isset($atts['context']) && $shortcode[5]) {
                                        $context = 'E2Pdf Template ' . $template_id;
                                        $substring = $shortcode[5];
                                        do_action('wpml_register_single_string', $context, $substring, $substring, false, apply_filters('wpml_default_language', null));
                                        $this->keys[] = $substring;
                                    }
                                }
                            }
                        }
                        break;
                    case '2':
                        $context = 'E2Pdf Template ' . $template_id;
                        $name = 'e2pdf_template_' . $template_id . '_' . $element_id . '_' . $type;
                        do_action('wpml_register_single_string', $context, $name, $string, false, apply_filters('wpml_default_language', null));
                        $this->keys[] = $name;
                        break;
                    default:
                        break;
                }
            }
        }

        public function flush($template_id = 0) {
            $context = 'E2Pdf Template ' . $template_id;
            $strings = $this->get_strings($context);
            if (!empty($strings) && is_array($strings)) {
                foreach ($strings as $name) {
                    if (!in_array($name, $this->keys, false) && function_exists('icl_unregister_string')) {
                        icl_unregister_string($context, $name);
                    }
                }
            }
            $this->keys = [];
        }

        public function get_strings($context = '') {
            global $wpdb;
            return $wpdb->get_col(
                            $wpdb->prepare(
                                    'SELECT name FROM `' . $wpdb->prefix . 'icl_strings`  WHERE context = %s',
                                    $context
                            )
                    );
        }

        public function pre_translate($string = '', $template_id = 0, $element_id = '', $type = '') {
            return $this->translate($string, $template_id, $element_id, $type);
        }

        public function translate($string = '', $template_id = 0, $element_id = '', $type = '') {
            switch ($this->translation) {
                case '1':
                    if ($type === 'template') {
                        $context = 'E2Pdf Template ' . $template_id;
                        $string = icl_t($context, $string, $string);
                    } elseif (false !== strpos($string, '[')) {
                        $shortcode_tags = array(
                            'e2pdf-translate',
                        );
                        preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $string, $matches);
                        $tagnames = array_intersect($shortcode_tags, $matches[1]);
                        if (!empty($tagnames)) {
                            preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $string, $shortcodes);
                            foreach ($shortcodes[0] as $key => $shortcode_value) {
                                $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                                $atts = shortcode_parse_atts($shortcode[3]);
                                if (!isset($atts['context']) && $shortcode[5]) {
                                    $context = 'E2Pdf Template ' . $template_id;
                                    $substring = $shortcode[5];
                                    $string = str_replace($shortcode_value, icl_t($context, $substring, $substring), $string);
                                }
                            }
                        }
                    }
                    break;
                case '2':
                    $context = 'E2Pdf Template ' . $template_id;
                    $name = 'e2pdf_template_' . $template_id . '_' . $element_id . '_' . $type;
                    $string = icl_t($context, $name, $string);
                    break;
                default:
                    break;
            }
            return $string;
        }

        public function translate_url($url = '') {
            return $url;
        }

        public function lang($post_id = 0) {
            $lang = apply_filters('wpml_current_language', null);
            return empty($lang) ? '' : $lang;
        }
    }

}

if (defined('POLYLANG_VERSION') && function_exists('PLL') && version_compare(POLYLANG_VERSION, '3.7.0', '>=') && class_exists('PLL_WPML_Compat') && !class_exists('E2pdf_Polylang_Translator')) {

    // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
    class E2pdf_Polylang_Translator {

        private $helper;
        private $translation;
        private $wpml;
        private $keys = [];

        public function __construct($translation) {
            $this->helper = Helper_E2pdf_Helper::instance();
            $this->translation = $translation;
            $field_types = ['select', 'checkbox', 'radio', 'button_group'];
            foreach ($field_types as $type) {
                add_filter('acf/format_value/type=' . $type, [$this, 'filter_acf_format_value'], 10, 2);
            }
            $this->wpml = PLL_WPML_Compat::instance();
        }

        public function update_translation($string = '', $template_id = 0, $element_id = '', $type = '') {
            if ($string) {
                switch ($this->translation) {
                    case '1':
                        if ($type === 'template') {
                            $context = 'E2Pdf Template ' . $template_id;
                            if (method_exists($this->wpml, 'register_string')) {
                                $this->wpml->register_string($context, $string, $string);
                            }
                            $this->keys[] = $string;
                        } elseif (false !== strpos($string, '[')) {
                            $shortcode_tags = array(
                                'e2pdf-translate',
                            );
                            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $string, $matches);
                            $tagnames = array_intersect($shortcode_tags, $matches[1]);
                            if (!empty($tagnames)) {
                                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $string, $shortcodes);
                                foreach ($shortcodes[0] as $key => $shortcode_value) {
                                    $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                                    $atts = shortcode_parse_atts($shortcode[3]);
                                    if (!isset($atts['context']) && $shortcode[5]) {
                                        $context = 'E2Pdf Template ' . $template_id;
                                        $substring = $shortcode[5];
                                        if (method_exists($this->wpml, 'register_string')) {
                                            $this->wpml->register_string($context, $substring, $substring);
                                        }
                                        $this->keys[] = $substring;
                                    }
                                }
                            }
                        }
                        break;
                    case '2':
                        $context = 'E2Pdf Template ' . $template_id;
                        $name = 'e2pdf_template_' . $template_id . '_' . $element_id . '_' . $type;
                        if (method_exists($this->wpml, 'register_string')) {
                            $this->wpml->register_string($context, $name, $string);
                        }
                        $this->keys[] = $name;
                        break;
                    default:
                        break;
                }
            }
        }

        public function flush($template_id = 0) {
            $context = 'E2Pdf Template ' . $template_id;
            $strings = $this->get_strings($context);
            if (!empty($strings) && is_array($strings) && method_exists($this->wpml, 'unregister_string')) {
                foreach ($strings as $name) {
                    if (!in_array($name, $this->keys, false)) {
                        $this->wpml->unregister_string($context, $name);
                    }
                }
            }
            $this->keys = [];
        }

        public function get_strings($context = '') {
            $strings = array();
            if (method_exists($this->wpml, 'get_strings')) {
                $all_strings = $this->wpml->get_strings(array());
                foreach ($all_strings as $string) {
                    if ($string['context'] == $context) {
                        $strings[] = $string['name'];
                    }
                }
            }
            return $strings;
        }

        public function filter_acf_format_value($string = '', $post_id = 0) {
            if (apply_filters('e2pdf_pdf_render', false)) {
                $string = $this->translate($string, $post_id);
            }
            return $string;
        }

        public function pre_translate($string = '', $template_id = 0, $element_id = '', $type = '') {
            switch ($this->translation) {
                case '1':
                    if (!empty($string) && is_string($string)) {
                        if ($type === 'template') {
                            $string = $this->translate($string);
                        } elseif (false !== strpos($string, '[')) {
                            $shortcode_tags = array(
                                'e2pdf-translate',
                            );
                            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $string, $matches);
                            $tagnames = array_intersect($shortcode_tags, $matches[1]);
                            if (!empty($tagnames)) {
                                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $string, $shortcodes);
                                foreach ($shortcodes[0] as $key => $shortcode_value) {
                                    $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                                    $atts = shortcode_parse_atts($shortcode[3]);
                                    if (!isset($atts['context']) && $shortcode[5]) {
                                        $context = 'E2Pdf Template ' . $template_id;
                                        $substring = $shortcode[5];
                                        $string = str_replace($shortcode_value, $this->translate($substring), $string);
                                    }
                                }
                            }
                        }
                    }
                    break;
                case '2':
                    $context = 'E2Pdf Template ' . $template_id;
                    $name = 'e2pdf_template_' . $template_id . '_' . $element_id . '_' . $type;
                    if (empty($string) && method_exists($this->wpml, 'get_string_by_context_and_name')) {
                        $string = $this->wpml->get_string_by_context_and_name($context, $name);
                    }
                    $string = $this->translate($string);
                    break;
                default:
                    break;
            }
            return $string;
        }

        public function translate($string = '', $post_id = 0) {
            if ($string && function_exists('pll__') && function_exists('pll_translate_string')) {
                $lang = $this->current_lang($post_id);
                return empty($lang) ? pll__($string) : pll_translate_string($string, $lang);
            }
            return $string;
        }

        public function translate_url($url = '') {
            return $url;
        }

        public function current_lang($post_id = 0) {
            $post_id = (int) $post_id;
            $lang = $post_id && function_exists('pll_get_post_language') ? pll_get_post_language($post_id) : null;
            if (empty($lang)) {
                $lang = PLL()->curlang;
            }
            return $lang;
        }

        public function lang($post_id = 0) {
            $lang = $this->current_lang($post_id);
            return isset($lang->locale) ? $lang->locale : '';
        }
    }

}
