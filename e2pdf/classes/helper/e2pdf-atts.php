<?php

/**
 * E2Pdf Pdf Helper
 * @copyright  Copyright 2017 https://e2pdf.com
 * @license    GPLv3
 * @version    1
 * @link       https://e2pdf.com
 * @since      0.00.01
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Atts {

    private $helper;
    private $atts = [];

    public function __construct() {
        $this->helper = Helper_E2pdf_Helper::instance();
    }

    // load
    public function load($atts = []) {
        if (is_array($atts)) {
            /* Backward compatiability with old format since 1.13.07 */
            if (isset($atts['iframe-download'])) {
                $atts['iframe_download'] = $atts['iframe-download'];
            }
            /* Backward compatiability with old format since 1.09.05 */
            if (isset($atts['button-title'])) {
                $atts['button_title'] = $atts['button-title'];
            }

            /* Backward compatiability */
            if (isset($atts['output'])) {
                if ($atts['output'] === 'url' && isset($atts['esc_url_raw']) && $atts['esc_url_raw']) {
                    $atts['output'] = 'url_raw';
                }
            }

            /* Backward compatiablity with old format since 1.29.x */
            $this->atts = $atts;
        }
        return $this;
    }

    public function set($key = '', $value = '') {
        if ($key) {
            $this->atts[$key] = $value;
        }
    }

    public function get($key = '', $default = false) {
        if ($key) {
            switch ($key) {
                case 'id':
                    return isset($this->atts[$key]) ? (int) $this->atts[$key] : $default;
                case 'inline':
                case 'local':
                case 'preload':
                case 'print':
                case 'wc_product_download':
                case 'iframe_download':
                case 'auto':
                case 'download':
                case 'view':
                case 'attachment':
                case 'zapier':
                case 'create_dir':
                case 'create_index':
                case 'create_htaccess':
                case 'filter':
                case 'single_page_mode':
                    if (isset($this->atts[$key])) {
                        return in_array(strtolower((string) $this->atts[$key]), ['true', '1'], true) ? '1' : '0';
                    }
                    return $default;
                case 'responsive':
                    if (isset($this->atts[$key])) {
                        return in_array(strtolower((string) $this->atts[$key]), ['1', 'true', 'page'], true) ? strtolower((string) $this->atts[$key]) : '0';
                    }
                    return $default;
                case 'disable':
                    return isset($this->atts[$key]) ? explode(',', $this->atts[$key]) : [];
                case 'class':
                    return isset($this->atts[$key]) ? explode(' ', $this->atts[$key]) : [];
                case 'style':
                    return isset($this->atts[$key]) ? explode(';', $this->atts[$key]) : [];
                case 'flatten':
                    if (isset($this->atts[$key])) {
                        if (in_array((string) $this->atts[$key], ['0', '1', '2'], true)) {
                            return $this->atts[$key];
                        } else {
                            return in_array(strtolower((string) $this->atts[$key]), ['true', '1'], true) ? '1' : '0';
                        }
                    }
                    return $default;
                case 'overwrite':
                    if (isset($this->atts[$key])) {
                        if (in_array((string) $this->atts[$key], ['0', '1', '2'], true)) {
                            return $this->atts[$key];
                        } else {
                            return in_array(strtolower((string) $this->atts[$key]), ['true', '1'], true) ? '1' : '0';
                        }
                    }
                    return $default;
                case 'format':
                    if (isset($this->atts[$key])) {
                        if (in_array(strtolower((string) $this->atts[$key]), ['pdf', 'jpg'], true)) {
                            return strtolower($this->atts[$key]);
                        }
                    }
                    return $default;
                case 'arguments':
                    $args = [];
                    foreach ($this->atts as $att_key => $att_value) {
                        if (substr($att_key, 0, 3) === 'arg') {
                            $args[$att_key] = $att_value;
                        }
                    }
                    return $args;
                case 'user_id':
                    return isset($this->atts[$key]) ? (int) $this->atts[$key] : get_current_user_id();
                case 'iframe_loader':
                    if (isset($this->atts[$key])) {
                        $iframe_loader = in_array(strtolower((string) $this->atts[$key]), ['true', '1'], true) ? '1' : '0';
                        return $iframe_loader ? plugins_url('img/loader.gif', $this->helper->get('plugin_file_path')) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
                    }
                case 'apply':
                    return isset($this->atts[$key]) ? true : false;
                default:
                    return isset($this->atts[$key]) ? $this->atts[$key] : $default;
            }
        }
        return $this->atts;
    }

    public function button_title($template = null) {

        $button_title = __('Download', 'e2pdf');

        if ($template) {
            if ($this->get('button_title') !== false) {
                $button_title = $this->get('filter') ? $template->extension()->convert_shortcodes($this->get('button_title'), true) : $template->extension()->render($this->get('button_title'));
            } elseif ($template->get('button_title')) {
                $button_title = $template->extension()->render(
                        $this->helper->load('translator')->pre_translate(
                                $template->get('button_title'), $template->get('ID'), 'button_title', 'template'
                        )
                );
            }
        } else {
            if ($this->get('button_title') !== false) {
                $button_title = $this->get('button_title');
            }
        }

        if (false !== strpos($button_title, '<')) {
            $button_title = wp_kses_post(
                    $button_title,
                    apply_filters(
                            'e2pdf_helper_filter_button_title',
                            array(
                                'img' => array(
                                    'src' => true,
                                    'class' => true,
                                    'style' => true,
                                ),
                                'span' => array(
                                    'class' => true,
                                    'style' => true,
                                ),
                                'div' => array(
                                    'class' => true,
                                    'style' => true,
                                ),
                                'br' => array(
                                    'class' => true,
                                    'style' => true,
                                ),
                                'p' => array(
                                    'class' => true,
                                    'style' => true,
                                ),
                                'i' => array(
                                    'class' => true,
                                    'style' => true,
                                ),
                                'strong' => array(
                                    'class' => true,
                                    'style' => true,
                                ),
                                'b' => array(
                                    'class' => true,
                                    'style' => true,
                                ),
                                'em' => array(
                                    'class' => true,
                                    'style' => true,
                                ),
                            )
                    )
            );
        }
        return $button_title;
    }
}
