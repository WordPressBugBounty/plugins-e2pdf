<?php

/**
 * File: /model/e2pdf-shortcode.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_Shortcode extends Model_E2pdf_Model {

    // e2pdf-attachment
    public function e2pdf_attachment($atts = []) {

        $response = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_attachment_atts', $atts);
        $attributes = (new Helper_E2pdf_Atts())->load($atts);

        if ($attributes->get('pdf') || $attributes->get('attachment_id') || $attributes->get('file_id')) {
            $pdf = $attributes->get('pdf');
            $downloadable = false;
            if ($attributes->get('file_id')) {
                $pdf = (new Model_E2pdf_Gdrive())->exists($attributes->get('file_id'));
                if ($pdf) {
                    $downloadable = $attributes->get('apply') && $pdf;
                }
            } else {
                if ($attributes->get('attachment_id')) {
                    $pdf = get_attached_file($attributes->get('attachment_id'));
                } else {
                    $pdf = $this->helper->load('convert')->to_file_dir($pdf);
                }
                if (strpos($pdf, '/') !== 0 && !preg_match('/^[A-Za-z]:/', $pdf)) {
                    $pdf = ABSPATH . $pdf;
                }
                $downloadable = $attributes->get('apply') && !$this->helper->load('filter')->is_stream($pdf) && file_exists($pdf) && $this->helper->load('filter')->is_downloadable($pdf);
            }

            if (!$downloadable) {
                return $response;
            }

            $pdf = apply_filters('e2pdf_model_e2pdf_shortcode_attachment_path', $pdf, $atts);
            if (trim($attributes->get('name', ''))) {
                $name = $attributes->get('name');
                $ext = pathinfo($pdf, PATHINFO_EXTENSION);
                $tmp_dir = $this->helper->get('tmp_dir') . 'e2pdf' . md5($pdf . $name) . '/';
                $this->helper->create_dir($tmp_dir);
                $filename = $this->helper->load('convert')->to_file_name($name . '.' . ($ext == 'jpg' ? 'jpg' : 'pdf'));
                $filepath = $tmp_dir . $filename;
                if (copy($pdf, $filepath)) {
                    $pdf = 'tmp:' . $filepath;
                }
            }
            return $pdf;
        }

        if (!$attributes->get('apply') || !$attributes->get('id') || (!$attributes->get('dataset') && !$attributes->get('dataset2'))) {
            return $response;
        }

        $template = new Model_E2pdf_Template();

        if (!$template->load($attributes->get('id'))) {
            return $response;
        }

        $entry = new Model_E2pdf_Entry();

        $template->extension()->patch('template_id', $attributes->get('id'), $entry);
        $template->extension()->patch('dataset', $attributes->get('dataset'), $entry);
        $template->extension()->patch('dataset2', $attributes->get('dataset2'), $entry);
        $template->extension()->patch('wc_order_id', $attributes->get('wc_order_id'), $entry);
        $template->extension()->patch('wc_product_item_id', $attributes->get('wc_product_item_id'), $entry);
        // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
        if (!($template->get('extension') === 'wordpress' && $template->get('item') === '-3')) {
            $template->extension()->patch('user_id', $attributes->get('user_id'), $entry);
        }
        $template->extension()->patch('args', $attributes->get('arguments'), $entry);
        $template->extension()->patch('storing_engine', $template->extension()->get_storing_engine(), $entry);

        $options = apply_filters('e2pdf_model_shortcode_extension_options', [], $template);
        $options = apply_filters('e2pdf_model_shortcode_e2pdf_attachment_extension_options', $options, $template);
        foreach ($options as $option_key => $option_value) {
            $template->extension()->set($option_key, $option_value);
        }

        if ($template->extension()->verify() && $this->process_shortcode('e2pdf_attachment', $template, $attributes)) {

            $template->patch('inline', $attributes->get('inline'), $entry, $attributes->get('filter'));
            $template->patch('flatten', $attributes->get('flatten'), $entry, $attributes->get('filter'));
            $template->patch('format', $attributes->get('format'), $entry, $attributes->get('filter'));
            $template->patch('password', $attributes->get('password'), $entry, $attributes->get('filter'));
            $template->patch('dpdf', $attributes->get('dpdf'), $entry, $attributes->get('filter'));
            $template->patch('name', $attributes->get('name'), $entry, $attributes->get('filter'));
            $template->patch('meta_title', $attributes->get('meta_title'), $entry, $attributes->get('filter'));
            $template->patch('meta_subject', $attributes->get('meta_subject'), $entry, $attributes->get('filter'));
            $template->patch('meta_author', $attributes->get('meta_author'), $entry, $attributes->get('filter'));
            $template->patch('meta_keywords', $attributes->get('meta_keywords'), $entry, $attributes->get('filter'));

            $template->extension()->set('entry', $entry);
            $template->fill();
            $request = $template->render();

            if (!isset($request['error'])) {

                $tmp_dir = $this->helper->get('tmp_dir') . 'e2pdf' . md5($entry->get('uid')) . '/';
                $this->helper->create_dir($tmp_dir);

                $filename = $template->get('name') . '.' . $template->get('format');
                $filename = $this->helper->load('convert')->to_file_name($filename);
                $filepath = $tmp_dir . $filename;

                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
                file_put_contents($filepath, $request['file'], LOCK_EX);
                if (file_exists($filepath)) {
                    if ($entry->load_by_uid()) {
                        $entry->set('pdf_num', $entry->get('pdf_num') + 1);
                        $entry->save();
                    }
                    $filepath = apply_filters('e2pdf_model_e2pdf_shortcode_attachment_path', $filepath, $atts);
                    return $filepath;
                }
            }
        }

        return $response;
    }

    // e2pdf-download
    public function e2pdf_download($atts = []) {

        if (function_exists('vc_is_page_editable') && vc_is_page_editable()) {
            return '[e2pdf-download]';
        }

        $response = '';
        $iframe_download = false;

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_download_atts', $atts);
        $attributes = (new Helper_E2pdf_Atts())->load($atts);

        $target = $attributes->get('target', '_blank');

        /**
         * WPBakery Page Builder Grid Item
         * [e2pdf-download id="1" dataset="{{ post_data:ID }}"]
         */
        if (strpos($attributes->get('dataset'), 'post_data:ID') !== false) {
            $response .= '[e2pdf-download ';
            foreach ($atts as $key => $value) {
                $response .= $key . '="' . str_replace('"', '', $value) . '" ';
            }
            $response .= ']';
            return $response;
        }

        if ($attributes->get('pdf') || $attributes->get('attachment_id') || $attributes->get('file_id')) {
            $pdf = $attributes->get('pdf');
            $downloadable = false;
            if ($attributes->get('file_id')) {
                $pdf = (new Model_E2pdf_Gdrive())->exists($attributes->get('file_id'));
                if ($pdf) {
                    $downloadable = true;
                }
            } else {
                if ($attributes->get('attachment_id')) {
                    $pdf = get_attached_file($attributes->get('attachment_id'));
                } else {
                    $pdf = $this->helper->load('convert')->to_file_dir($pdf);
                }
                if (strpos($pdf, '/') !== 0 && !preg_match('/^[A-Za-z]:/', $pdf)) {
                    $pdf = ABSPATH . $pdf;
                }
                $downloadable = !$this->helper->load('filter')->is_stream($pdf) && file_exists($pdf) && $this->helper->load('filter')->is_downloadable($pdf);
            }

            if (!$downloadable) {
                return $response;
            }
            $entry = new Model_E2pdf_Entry();
            if ($attributes->get('attachment_id')) {
                $entry->set_data('attachment_id', $attributes->get('attachment_id'));
            } elseif ($attributes->get('file_id')) {
                $entry->set_data('file_id', $attributes->get('file_id'));
            } else {
                $entry->set_data('pdf', $pdf);
            }

            $classes = array_merge($attributes->get('class'), ['e2pdf-download', 'e2pdf-format-pdf']);

            $inline = '0';
            if ($attributes->get('inline') !== false) {
                $inline = $attributes->get('inline');
                $entry->set_data('inline', $inline);
            }
            if ($inline) {
                $classes[] = 'e2pdf-inline';
            }

            $auto = $attributes->get('auto', '0');
            if ($auto) {
                $classes[] = 'e2pdf-auto';
                if ($attributes->get('iframe_download')) {
                    $classes[] = 'e2pdf-iframe-download';
                    $iframe_download = true;
                }
            }

            if ($attributes->get('name') !== false) {
                $entry->set_data('name', $attributes->get('name'));
            }

            $button_title = apply_filters('e2pdf_model_shortcode_e2pdf_download_button_title', $attributes->button_title(), $atts);
            if ($attributes->get('output') === 'button_title') {
                return $button_title;
            }

            if ($attributes->get('local')) {
                if ($attributes->get('file_id')) {
                    if ($inline) {
                        $url = 'https://drive.google.com/file/d/' . $entry->get_data('file_id') . '/view';
                    } else {
                        $url = 'https://drive.google.com/uc?id=' . $entry->get_data('file_id') . '&export=download';
                    }
                } else {
                    $url = $attributes->get('attachment_id') ? wp_get_attachment_url($entry->get_data('attachment_id')) : $this->helper->get_frontend_local_pdf_url($pdf);
                }
                switch ($attributes->get('output')) {
                    case 'url':
                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_download_output_url', $url, $atts);
                        $response = esc_url($url);
                        break;
                    case 'url_raw':
                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_download_output_url', $url, $atts);
                        $response = esc_url_raw($url);
                        break;
                    case 'url_encode':
                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_download_output_url', $url, $atts);
                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                        $response = urlencode(esc_url_raw($url));
                        break;
                    default:
                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_download_pdf_url', $url, $atts);
                        $file_download = '';
                        if ($attributes->get('print') && $this->helper->load('server')->isPrintingSupported()) {
                            $classes[] = 'e2pdf-print-pdf';
                            $url = add_query_arg(['v' => $this->helper->get('version')], $url);
                        } elseif (get_option('e2pdf_download_loader', '0') == '1' && $this->helper->load('server')->isLoaderSupported()) {
                            if ($inline) {
                                $target = '_blank';
                            } else {
                                $download_name = $entry->get_data('name') ? $entry->get_data('name') : basename($pdf, '.' . pathinfo($pdf, PATHINFO_EXTENSION));
                                $download_name = apply_filters('e2pdf_controller_frontend_e2pdf_download_name', $download_name, $entry->get('uid'), $entry->get('entry'));
                                $file_download = 'download="' . esc_attr($download_name . '.' . (pathinfo($pdf, PATHINFO_EXTENSION))) . '"';
                                $classes[] = 'e2pdf-download-loader';
                                if ($this->helper->load('server')->isIOS() && $this->helper->load('server')->isSafari()) {
                                    $classes[] = 'e2pdf-ios-safari-loader';
                                }
                            }
                        }

                        $lid = uniqid('', true);
                        $response = '<a lid="' . esc_attr($lid) . '" rel="nofollow" ' . $file_download . ' id="e2pdf-download" class="' . esc_attr(implode(' ', $classes)) . '" style="' . esc_attr(implode(';', $attributes->get('style'))) . '" target="' . esc_attr($target) . '" href="' . esc_url($url) . '">' . $button_title . '</a>';
                        if ($iframe_download) {
                            $url = add_query_arg(['v' => $this->helper->get('version')], $url);

                            $src = $attributes->get('preload') ? 'preload' : 'src';
                            $preload_class = $attributes->get('preload') ? 'e2pdf-preload' : '';

                            if ($inline || (get_option('e2pdf_download_loader', '0') == '1' && $this->helper->load('server')->isLoaderSupported())) {
                                $response .= '<img class="' . esc_attr($preload_class) . '" onload="e2pdfViewer.autoDownload(\'' . esc_attr($lid) . '\');" style="display:none" ' . $src . '="' . $attributes->get('iframe_loader') . '">';
                            } else {
                                $response .= '<iframe class="' . esc_attr($preload_class) . '" style="width:0;height:0;border-width:0;border:none;" ' . $src . '="' . esc_url($url) . '"></iframe>';
                            }
                        }
                        break;
                }
            } else {
                if (!$entry->load_by_uid()) {
                    $entry->save();
                }
                if ($entry->get('ID')) {
                    $url_data = [
                        'page' => 'e2pdf-download',
                        'uid' => $entry->get('uid'),
                    ];

                    $url_data = apply_filters(
                            'e2pdf_model_shortcode_e2pdf_download_url_data',
                            apply_filters('e2pdf_model_shortcode_url_data', $url_data, $atts),
                            $atts
                    );

                    $url = $this->helper->get_frontend_pdf_url(
                            $url_data, $attributes->get('site_url'),
                            [
                                'e2pdf_model_shortcode_site_url',
                                'e2pdf_model_shortcode_e2pdf_download_site_url',
                            ]
                    );
                    switch ($attributes->get('output')) {
                        case 'url':
                            $url = apply_filters('e2pdf_model_shortcode_e2pdf_download_output_url', $url, $atts);
                            $response = esc_url($url);
                            break;
                        case 'url_raw':
                            $url = apply_filters('e2pdf_model_shortcode_e2pdf_download_output_url', $url, $atts);
                            $response = esc_url_raw($url);
                            break;
                        case 'url_encode':
                            $url = apply_filters('e2pdf_model_shortcode_e2pdf_download_output_url', $url, $atts);
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                            $response = urlencode(esc_url_raw($url));
                            break;
                        default:
                            $file_download = '';
                            $url = apply_filters('e2pdf_model_shortcode_e2pdf_download_pdf_url', $url, $atts);
                            if ($attributes->get('print') && $this->helper->load('server')->isPrintingSupported()) {
                                $classes[] = 'e2pdf-print-pdf';
                                $url = add_query_arg(['v' => $this->helper->get('version')], $url);
                            } elseif (get_option('e2pdf_download_loader', '0') == '1' && $this->helper->load('server')->isLoaderSupported()) {
                                if ($inline) {
                                    $target = '_blank';
                                } else {
                                    $download_name = $entry->get_data('name') ? $entry->get_data('name') : basename($pdf, '.' . pathinfo($pdf, PATHINFO_EXTENSION));
                                    $download_name = apply_filters('e2pdf_controller_frontend_e2pdf_download_name', $download_name, $entry->get('uid'), $entry->get('entry'));
                                    $file_download = 'download="' . esc_attr($download_name . '.' . (pathinfo($pdf, PATHINFO_EXTENSION))) . '"';
                                    $classes[] = 'e2pdf-download-loader';
                                    if ($this->helper->load('server')->isIOS() && $this->helper->load('server')->isSafari()) {
                                        $classes[] = 'e2pdf-ios-safari-loader';
                                    }
                                }
                            }

                            $lid = uniqid('', true);
                            $response = '<a lid="' . esc_attr($lid) . '" rel="nofollow" ' . $file_download . ' id="e2pdf-download" class="' . esc_attr(implode(' ', $classes)) . '" style="' . esc_attr(implode(';', $attributes->get('style'))) . '" target="' . esc_attr($target) . '" href="' . esc_url($url) . '">' . $button_title . '</a>';
                            if ($iframe_download) {
                                $url = add_query_arg(['v' => $this->helper->get('version')], $url);

                                $src = $attributes->get('preload') ? 'preload' : 'src';
                                $preload_class = $attributes->get('preload') ? 'e2pdf-preload' : '';

                                if ($inline || (get_option('e2pdf_download_loader', '0') == '1' && $this->helper->load('server')->isLoaderSupported())) {
                                    $response .= '<img class="' . esc_attr($preload_class) . '" onload="e2pdfViewer.autoDownload(\'' . esc_attr($lid) . '\');" style="display:none" ' . $src . '="' . $attributes->get('iframe_loader') . '">';
                                } else {
                                    $response .= '<iframe class="' . esc_attr($preload_class) . '" style="width:0;height:0;border-width:0;border:none;" ' . $src . '="' . esc_url($url) . '"></iframe>';
                                }
                            }
                            break;
                    }
                }
            }
            return $response;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['e2pdf-hash']) && !$attributes->get('dataset')) {
            $hash_id = sanitize_text_field(wp_unslash($_GET['e2pdf-hash']));
            $attributes->set('dataset', get_transient('e2pdf_hash_' . $hash_id));
            if ($attributes->get('dataset') && apply_filters('e2pdf_hash_clear', true, 'shortcode', $atts) && has_action('shutdown', [$this, 'action_e2pdf_hash_clear']) === false) {
                add_action('shutdown', [$this, 'action_e2pdf_hash_clear']);
            }
        }
        // phpcs:enable

        if (!$attributes->get('id') || (!$attributes->get('dataset') && !$attributes->get('dataset2'))) {
            return $response;
        }

        $template = new Model_E2pdf_Template();
        if (!$template->load($attributes->get('id'), false)) {
            return $response;
        }

        $entry = new Model_E2pdf_Entry();

        $template->extension()->patch('template_id', $attributes->get('id'), $entry);
        $template->extension()->patch('dataset', $attributes->get('dataset'), $entry);
        $template->extension()->patch('dataset2', $attributes->get('dataset2'), $entry);
        $template->extension()->patch('wc_order_id', $attributes->get('wc_order_id'), $entry);
        $template->extension()->patch('wc_product_item_id', $attributes->get('wc_product_item_id'), $entry);
        // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
        if (!($template->get('extension') === 'wordpress' && $template->get('item') === '-3')) {
            $template->extension()->patch('user_id', $attributes->get('user_id'), $entry);
        }
        $template->extension()->patch('args', $attributes->get('arguments'), $entry);
        $template->extension()->patch('storing_engine', $template->extension()->get_storing_engine(), $entry);

        $classes = array_merge($attributes->get('class'), ['e2pdf-download']);

        $options = apply_filters('e2pdf_model_shortcode_extension_options', [], $template);
        $options = apply_filters('e2pdf_model_shortcode_e2pdf_download_extension_options', $options, $template);

        foreach ($options as $option_key => $option_value) {
            $template->extension()->set($option_key, $option_value);
        }

        if ($template->extension()->verify() && $this->process_shortcode('e2pdf_download', $template, $attributes)) {

            $button_title = apply_filters('e2pdf_model_shortcode_e2pdf_download_button_title', $attributes->button_title($template), $atts);
            if ($attributes->get('output') === 'button_title') {
                return $button_title;
            }

            $template->patch('inline', $attributes->get('inline'), $entry, $attributes->get('filter'));
            $template->patch('flatten', $attributes->get('flatten'), $entry, $attributes->get('filter'));
            $template->patch('format', $attributes->get('format'), $entry, $attributes->get('filter'));
            $template->patch('password', $attributes->get('password'), $entry, $attributes->get('filter'));
            $template->patch('dpdf', $attributes->get('dpdf'), $entry, $attributes->get('filter'));
            $template->patch('name', $attributes->get('name'), $entry, $attributes->get('filter'));
            $template->patch('meta_title', $attributes->get('meta_title'), $entry, $attributes->get('filter'));
            $template->patch('meta_subject', $attributes->get('meta_subject'), $entry, $attributes->get('filter'));
            $template->patch('meta_author', $attributes->get('meta_author'), $entry, $attributes->get('filter'));
            $template->patch('meta_keywords', $attributes->get('meta_keywords'), $entry, $attributes->get('filter'));

            if ($template->get('inline')) {
                $classes[] = 'e2pdf-inline';
            }

            if ($attributes->get('auto') !== false) {
                $template->set('auto', $attributes->get('auto'));
            }
            if ($template->get('auto')) {
                $classes[] = 'e2pdf-auto';
                if ($attributes->get('iframe_download')) {
                    $classes[] = 'e2pdf-iframe-download';
                    $iframe_download = true;
                }
            }
            $classes[] = 'e2pdf-format-' . $template->get('format');

            if (!$entry->load_by_uid()) {
                $entry->save();
            }

            if ($entry->get('ID')) {

                $download_name = apply_filters('e2pdf_controller_frontend_e2pdf_download_name', $template->get('name'), $entry->get('uid'), $entry->get('entry'));

                $url_data = [
                    'page' => 'e2pdf-download',
                    'uid' => $entry->get('uid'),
                ];

                if ($attributes->get('wc_product_download')) {
                    $url_data['#saveName'] = '/' . $download_name . '.' . $template->get('format');
                }

                $url_data = apply_filters(
                        'e2pdf_model_shortcode_e2pdf_download_url_data',
                        apply_filters('e2pdf_model_shortcode_url_data', $url_data, $atts),
                        $atts
                );

                $url = $this->helper->get_frontend_pdf_url(
                        $url_data, $attributes->get('site_url'),
                        [
                            'e2pdf_model_shortcode_site_url',
                            'e2pdf_model_shortcode_e2pdf_download_site_url',
                        ]
                );
                switch ($attributes->get('output')) {
                    case 'url':
                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_download_output_url', $url, $atts);
                        $response = esc_url($url);
                        break;
                    case 'url_raw':
                        $url = esc_url_raw($url);
                        $response = apply_filters('e2pdf_model_shortcode_e2pdf_download_output_url', $url, $atts);
                        break;
                    case 'url_encode':
                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_download_output_url', $url, $atts);
                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                        $response = urlencode(esc_url_raw($url));
                        break;
                    default:
                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_download_pdf_url', $url, $atts);
                        $file_download = '';
                        if ($attributes->get('print') && $this->helper->load('server')->isPrintingSupported()) {
                            $classes[] = 'e2pdf-print-pdf';
                            $url = add_query_arg(['v' => $this->helper->get('version')], $url);
                        } elseif (get_option('e2pdf_download_loader', '0') == '1' && $this->helper->load('server')->isLoaderSupported()) {
                            if ($template->get('inline')) {
                                $target = '_blank';
                            } else {
                                $file_download = 'download="' . esc_attr($download_name) . '.' . $template->get('format') . '"';
                                $classes[] = 'e2pdf-download-loader';
                                if ($this->helper->load('server')->isIOS() && $this->helper->load('server')->isSafari()) {
                                    $classes[] = 'e2pdf-ios-safari-loader';
                                }
                            }
                        }

                        $lid = uniqid('', true);
                        $response = '<a lid="' . esc_attr($lid) . '" rel="nofollow" ' . $file_download . ' id="e2pdf-download" class="' . esc_attr(implode(' ', $classes)) . '" style="' . esc_attr(implode(';', $attributes->get('style'))) . '" target="' . esc_attr($target) . '" href="' . esc_url($url) . '">' . $button_title . '</a>';
                        if ($iframe_download) {
                            $url = add_query_arg(['v' => $this->helper->get('version')], $url);

                            $src = $attributes->get('preload') ? 'preload' : 'src';
                            $preload_class = $attributes->get('preload') ? 'e2pdf-preload' : '';

                            if ($template->get('inline') || (get_option('e2pdf_download_loader', '0') == '1' && $this->helper->load('server')->isLoaderSupported())) {
                                $response .= '<img class="' . esc_attr($preload_class) . '" onload="e2pdfViewer.autoDownload(\'' . esc_attr($lid) . '\');" style="display:none" ' . $src . '="' . $attributes->get('iframe_loader') . '">';
                            } else {
                                $response .= '<iframe class="' . esc_attr($preload_class) . '" style="width:0;height:0;border-width:0;border:none;" ' . $src . '="' . esc_url($url) . '"></iframe>';
                            }
                        }
                        break;
                }
            }
        }

        return $response;
    }

    // e2pdf-save
    public function e2pdf_save($atts = []) {

        unset($atts['pdf']);

        $response = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_save_atts', $atts);
        $attributes = (new Helper_E2pdf_Atts())->load($atts);

        if (!$attributes->get('apply') || !$attributes->get('id') || (!$attributes->get('dataset') && !$attributes->get('dataset2'))) {
            return $response;
        }

        $template = new Model_E2pdf_Template();

        if (!$template->load($attributes->get('id'))) {
            return $response;
        }

        $entry = new Model_E2pdf_Entry();
        $update = $attributes->get('media') || $attributes->get('gdrive') ? true : false;

        $template->extension()->patch('template_id', $attributes->get('id'), $update ? $entry : null);
        $template->extension()->patch('dataset', $attributes->get('dataset'), $update ? $entry : null);
        $template->extension()->patch('dataset2', $attributes->get('dataset2'), $update ? $entry : null);
        $template->extension()->patch('wc_order_id', $attributes->get('wc_order_id'), $update ? $entry : null);
        $template->extension()->patch('wc_product_item_id', $attributes->get('wc_product_item_id'), $update ? $entry : null);
        $template->extension()->patch('user_id', $attributes->get('user_id'), $update ? $entry : null);
        $template->extension()->patch('args', $attributes->get('arguments'), $update ? $entry : null);
        $template->extension()->patch('storing_engine', $template->extension()->get_storing_engine(), $update ? $entry : null);

        $options = apply_filters('e2pdf_model_shortcode_extension_options', [], $template);
        $options = apply_filters('e2pdf_model_shortcode_e2pdf_save_extension_options', $options, $template);
        foreach ($options as $option_key => $option_value) {
            $template->extension()->set($option_key, $option_value);
        }

        if ($template->extension()->verify() && $this->process_shortcode('e2pdf_save', $template, $attributes)) {

            $template->patch('inline', $attributes->get('inline'), $update ? $entry : null, $attributes->get('filter'));
            $template->patch('flatten', $attributes->get('flatten'), $update ? $entry : null, $attributes->get('filter'));
            $template->patch('format', $attributes->get('format'), $update ? $entry : null, $attributes->get('filter'));
            $template->patch('password', $attributes->get('password'), $update ? $entry : null, $attributes->get('filter'));
            $template->patch('dpdf', $attributes->get('dpdf'), $update ? $entry : null, $attributes->get('filter'));
            $template->patch('name', $attributes->get('name'), $update ? $entry : null, $attributes->get('filter'));
            $template->patch('savename', $attributes->get('savename'), $update ? $entry : null, $attributes->get('filter'));
            $template->patch('meta_title', $attributes->get('meta_title'), $update ? $entry : null, $attributes->get('filter'));
            $template->patch('meta_subject', $attributes->get('meta_subject'), $update ? $entry : null, $attributes->get('filter'));
            $template->patch('meta_author', $attributes->get('meta_author'), $update ? $entry : null, $attributes->get('filter'));
            $template->patch('meta_keywords', $attributes->get('meta_keywords'), $update ? $entry : null, $attributes->get('filter'));

            if ($template->get('inline')) {
                $entry->set_data('inline', $template->get('inline'));
                $atts['inline'] = $template->get('inline');
            }

            if (!$attributes->get('media') && !$attributes->get('gdrive')) {
                $dir = $attributes->get('dir');
                if ($dir) {
                    $dir = $attributes->get('filter') ? $template->extension()->convert_shortcodes($dir, true) : $template->extension()->render($dir);
                    $dir = rtrim(trim($this->helper->load('convert')->to_file_dir($dir)), '/') . '/';
                    if (strpos($dir, '/') !== 0 && !preg_match('/^[A-Za-z]:/', $dir)) {
                        $dir = ABSPATH . $dir;
                    }
                    if ($attributes->get('create_dir')) {
                        $this->helper->create_dir($dir, true, $attributes->get('create_index', '1'), $attributes->get('create_htaccess', '1'));
                    }
                } else {
                    $tpl_dir = $this->helper->get('tpl_dir') . $template->get('ID') . '/';
                    $dir = $tpl_dir . 'save/';
                    $this->helper->create_dir($tpl_dir, false, true);
                    $this->helper->create_dir($dir, false, $attributes->get('create_index', '1'), $attributes->get('create_htaccess', '1'));
                }
                $htaccess = $dir . '.htaccess';
                if ($attributes->get('create_htaccess', '1') && !file_exists($htaccess)) {
                    if ($attributes->get('local')) {
                        $htaccess_content = 'DENY FROM ALL' . PHP_EOL;
                        $htaccess_content .= '<Files ~ "\.(jpg|pdf)$">' . PHP_EOL;
                        $htaccess_content .= 'ALLOW FROM ALL' . PHP_EOL;
                        $htaccess_content .= '</Files>' . PHP_EOL;
                        $this->helper->create_file($htaccess, $htaccess_content);
                    } else {
                        $this->helper->create_file($htaccess, 'DENY FROM ALL');
                    }
                }
            }

            $filename = $template->get('savename') . '.' . $template->get('format');
            $filename = $this->helper->load('convert')->to_file_name($filename);

            if ($template->get('savename') !== $template->get('name')) {
                $entry->set_data('name', $template->get('name'));
                $atts['name'] = $template->get('name');
            }

            if ($attributes->get('media')) {
                $entry->set_data('format', $template->get('format'));
                $entry->set_data('media', true);
                if ($entry->load_by_uid() && $entry->get_data('attachment_id')) {
                    $filepath = get_attached_file($entry->get_data('attachment_id'));
                    if ($filepath) {
                        if ($attributes->get('overwrite', '1') || !file_exists($filepath)) {
                            $template->extension()->set('entry', $entry);
                            $template->fill();
                            $request = $template->render();
                            if (isset($request['error'])) {
                                return $response;
                            }
                            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
                            file_put_contents($filepath, $request['file'], LOCK_EX);
                            $entry->set('pdf_num', $entry->get('pdf_num') + 1);
                            $entry->save();
                        }
                    } else {
                        $template->extension()->set('entry', $entry);
                        $template->fill();
                        $request = $template->render();
                        if (isset($request['error'])) {
                            return $response;
                        } else {
                            $file = wp_upload_bits(
                                    $filename,
                                    null,
                                    $request['file']
                            );
                            if (is_array($file) && isset($file['file']) && file_exists($file['file'])) {
                                $attachment_args = [
                                    'import_id' => $entry->get_data('attachment_id'),
                                    'guid' => $file['url'],
                                    'post_mime_type' => $file['type'],
                                    'post_title' => preg_replace('/\.[^.]+$/', '', basename($file['file'])),
                                    'post_excerpt' => '',
                                    'post_content' => '',
                                ];
                                $attachment_id = wp_insert_attachment($attachment_args, $file['file']);
                                if (!is_wp_error($attachment_id) && $attachment_id) {
                                    require_once ABSPATH . 'wp-admin/includes/image.php';
                                    $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file['file']);
                                    wp_update_attachment_metadata($attachment_id, $attachment_metadata);
                                    $filepath = get_attached_file($attachment_id);
                                    $entry->set('pdf_num', $entry->get('pdf_num') + 1);
                                    $entry->set_data('attachment_id', $attachment_id);
                                    $entry->save();
                                } else {
                                    return $response;
                                }
                            } else {
                                return $response;
                            }
                        }
                    }
                } else {
                    $template->extension()->set('entry', $entry);
                    $template->fill();
                    $request = $template->render();
                    if (isset($request['error'])) {
                        return $response;
                    } else {
                        $file = wp_upload_bits(
                                $filename,
                                null,
                                $request['file']
                        );
                        if (is_array($file) && isset($file['file']) && file_exists($file['file'])) {
                            $attachment_args = [
                                'guid' => $file['url'],
                                'post_mime_type' => $file['type'],
                                'post_title' => preg_replace('/\.[^.]+$/', '', basename($file['file'])),
                                'post_excerpt' => '',
                                'post_content' => '',
                            ];
                            $attachment_id = wp_insert_attachment($attachment_args, $file['file']);
                            if (!is_wp_error($attachment_id) && $attachment_id) {
                                require_once ABSPATH . 'wp-admin/includes/image.php';
                                $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file['file']);
                                wp_update_attachment_metadata($attachment_id, $attachment_metadata);
                                $filepath = get_attached_file($attachment_id);
                                $entry->save();
                                $entry->set_data('attachment_id', $attachment_id);
                                $entry->save();
                            } else {
                                return $response;
                            }
                        } else {
                            return $response;
                        }
                    }
                }
                if (!$this->helper->load('filter')->is_stream($filepath) && file_exists($filepath) && $entry->get_data('attachment_id')) {
                    $atts['attachment_id'] = $entry->get_data('attachment_id');
                    if ($attributes->get('download')) {
                        $atts['button_title'] = $attributes->button_title($template);
                        $response = $this->e2pdf_download($atts);
                    } elseif ($attributes->get('view')) {
                        $response = $this->e2pdf_view($atts);
                    } elseif ($attributes->get('attachment')) {
                        $response = $this->e2pdf_attachment($atts);
                    } elseif ($attributes->get('zapier')) {
                        $response = $this->e2pdf_zapier($atts);
                    } elseif ($attributes->get('output') === 'path') {
                        $response = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_path', $filepath, $atts);
                    } else {
                        if ($attributes->get('local')) {
                            $url = wp_get_attachment_url($entry->get_data('attachment_id'));
                            switch ($attributes->get('output')) {
                                case 'attachment_id':
                                    $attachment_id = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_attachment_id', $entry->get_data('attachment_id'), $atts);
                                    $response = $attachment_id;
                                    break;
                                case 'url':
                                    $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                    $response = esc_url($url);
                                    break;
                                case 'url_raw':
                                    $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                    $response = esc_url_raw($url);
                                    break;
                                case 'url_encode':
                                    $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                    $response = urlencode(esc_url_raw($url));
                                    break;
                                default:
                                    $response = '';
                                    break;
                            }
                        } else {
                            if (!$entry->load_by_uid()) {
                                $entry->save();
                            }
                            if ($entry->get('ID')) {
                                $url_data = [
                                    'page' => 'e2pdf-download',
                                    'uid' => $entry->get('uid'),
                                ];
                                $url_data = apply_filters('e2pdf_model_shortcode_url_data', $url_data, $atts);
                                $url_data = apply_filters('e2pdf_model_shortcode_e2pdf_save_url_data', $url_data, $atts);
                                $url = $this->helper->get_frontend_pdf_url(
                                        $url_data, $attributes->get('site_url'),
                                        [
                                            'e2pdf_model_shortcode_site_url',
                                            'e2pdf_model_shortcode_e2pdf_save_site_url',
                                        ]
                                );
                                switch ($attributes->get('output')) {
                                    case 'attachment_id':
                                        $attachment_id = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_attachment_id', $entry->get_data('attachment_id'), $atts);
                                        $response = $attachment_id;
                                        break;
                                    case 'url':
                                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                        $response = esc_url($url);
                                        break;
                                    case 'url_raw':
                                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                        $response = esc_url_raw($url);
                                        break;
                                    case 'url_encode':
                                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                        $response = urlencode(esc_url_raw($url));
                                        break;
                                    default:
                                        $response = '';
                                        break;
                                }
                            }
                        }
                    }
                }
            } elseif ($attributes->get('gdrive')) {
                $dir = $attributes->get('dir');
                if ($dir) {
                    $dir = $attributes->get('filter') ? $template->extension()->convert_shortcodes($dir, true) : $template->extension()->render($dir);
                    $dir = (new Model_E2pdf_Gdrive())->create_dir($dir);
                } else {
                    $dir = 'root';
                }
                if ($dir !== 'root') {
                    $entry->set_data('dir', $dir);
                }

                $entry->set_data('format', $template->get('format'));
                $entry->set_data('gdrive', true);

                if ($entry->load_by_uid() && $entry->get_data('file_id')) {
                    $file_id = $entry->get_data('file_id');
                    if ((new Model_E2pdf_Gdrive())->exists($file_id)) {
                        if ($attributes->get('overwrite', '1')) {
                            $template->extension()->set('entry', $entry);
                            $template->fill();
                            $request = $template->render();
                            if (isset($request['error'])) {
                                return $response;
                            }
                            $file_id = (new Model_E2pdf_Gdrive())->reupload($request['file'], $file_id);
                            if ($file_id) {
                                $entry->set('pdf_num', $entry->get('pdf_num') + 1);
                                $entry->save();
                            }
                        }
                    } else {
                        $template->extension()->set('entry', $entry);
                        $template->fill();
                        $request = $template->render();
                        if (isset($request['error'])) {
                            return $response;
                        } else {
                            $file_id = (new Model_E2pdf_Gdrive())->upload($request['file'], $filename, $dir);
                            if ($file_id) {
                                $entry->set('pdf_num', $entry->get('pdf_num') + 1);
                                $entry->set_data('file_id', $file_id);
                                $entry->save();
                            } else {
                                return $response;
                            }
                        }
                    }
                } else {
                    $template->extension()->set('entry', $entry);
                    $template->fill();
                    $request = $template->render();
                    if (isset($request['error'])) {
                        return $response;
                    } else {
                        $file_id = (new Model_E2pdf_Gdrive())->upload($request['file'], $filename, $dir);
                        if ($file_id) {
                            $entry->save();
                            $entry->set_data('file_id', $file_id);
                            $entry->save();
                        } else {
                            return $response;
                        }
                    }
                }
                if ($entry->get_data('file_id')) {
                    $atts['file_id'] = $entry->get_data('file_id');
                    if ($attributes->get('download')) {
                        $atts['button_title'] = $attributes->button_title($template);
                        $response = $this->e2pdf_download($atts);
                    } elseif ($attributes->get('view')) {
                        $response = $this->e2pdf_view($atts);
                    } elseif ($attributes->get('attachment')) {
                        $response = $this->e2pdf_attachment($atts);
                    } elseif ($attributes->get('zapier')) {
                        $response = $this->e2pdf_zapier($atts);
                    } elseif ($attributes->get('output') === 'path') {
                        $response = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_path', $filepath, $atts);
                    } else {
                        if ($attributes->get('local')) {
                            if ($template->get('inline')) {
                                $url = 'https://drive.google.com/file/d/' . $entry->get_data('file_id') . '/view';
                            } else {
                                $url = 'https://drive.google.com/uc?id=' . $entry->get_data('file_id') . '&export=download';
                            }
                            switch ($attributes->get('output')) {
                                case 'file_id':
                                    $file_id = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_file_id', $entry->get_data('file_id'), $atts);
                                    $response = $file_id;
                                    break;
                                case 'url':
                                    $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                    $response = esc_url($url);
                                    break;
                                case 'url_raw':
                                    $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                    $response = esc_url_raw($url);
                                    break;
                                case 'url_encode':
                                    $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                    $response = urlencode(esc_url_raw($url));
                                    break;
                                default:
                                    $response = '';
                                    break;
                            }
                        } else {
                            if (!$entry->load_by_uid()) {
                                $entry->save();
                            }
                            if ($entry->get('ID')) {
                                $url_data = [
                                    'page' => 'e2pdf-download',
                                    'uid' => $entry->get('uid'),
                                ];
                                $url_data = apply_filters('e2pdf_model_shortcode_url_data', $url_data, $atts);
                                $url_data = apply_filters('e2pdf_model_shortcode_e2pdf_save_url_data', $url_data, $atts);
                                $url = $this->helper->get_frontend_pdf_url(
                                        $url_data, $attributes->get('site_url'),
                                        [
                                            'e2pdf_model_shortcode_site_url',
                                            'e2pdf_model_shortcode_e2pdf_save_site_url',
                                        ]
                                );
                                switch ($attributes->get('output')) {
                                    case 'file_id':
                                        $file_id = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_file_id', $entry->get_data('file_id'), $atts);
                                        $response = $file_id;
                                        break;
                                    case 'url':
                                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                        $response = esc_url($url);
                                        break;
                                    case 'url_raw':
                                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                        $response = esc_url_raw($url);
                                        break;
                                    case 'url_encode':
                                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                        $response = urlencode(esc_url_raw($url));
                                        break;
                                    default:
                                        $response = '';
                                        break;
                                }
                            }
                        }
                    }
                }
            } else {
                $filepath = apply_filters('e2pdf_model_e2pdf_shortcode_pre_save_path', $dir . $filename, $atts);
                $entry->set_data('pdf', $filepath);
                if ($attributes->get('local')) {
                    $entry->set_data('e2pdf-url', $this->helper->get_frontend_local_pdf_url($filepath));
                }
                if ($attributes->get('overwrite', '1') || !file_exists($filepath)) {
                    $template->extension()->set('entry', $entry);
                    $template->fill();
                    $request = $template->render();
                }
                if (isset($request['error']) && ($attributes->get('overwrite', '1') || !file_exists($filepath))) {
                    return false;
                } else {
                    if (is_dir($dir) && is_writable($dir)) {
                        if ($attributes->get('overwrite', '1') || !file_exists($filepath)) {
                            if ($attributes->get('overwrite', '1') === '2' && file_exists($filepath)) {
                                $path = pathinfo($filepath);
                                $current_name = $path['filename'];
                                $i = 1;
                                while (file_exists($path['dirname'] . '/' . $current_name . '.' . $path['extension'])) {
                                    $current_name = $path['filename'] . '(' . $i . ')';
                                    $filepath = $path['dirname'] . '/' . $current_name . '.' . $path['extension'];
                                    $i++;
                                }
                                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
                                file_put_contents($filepath, $request['file'], LOCK_EX);
                            } else {
                                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
                                file_put_contents($filepath, $request['file'], LOCK_EX);
                            }
                            if ($entry->load_by_uid()) {
                                $entry->set('pdf_num', $entry->get('pdf_num') + 1);
                                $entry->save();
                            }
                        }
                        if (!$this->helper->load('filter')->is_stream($filepath) && file_exists($filepath)) {
                            $filepath = apply_filters('e2pdf_model_e2pdf_shortcode_save_path', $filepath, $atts);
                            $atts['pdf'] = $filepath;
                            if ($attributes->get('download')) {
                                $atts['button_title'] = $attributes->button_title($template);
                                $response = $this->e2pdf_download($atts);
                            } elseif ($attributes->get('view')) {
                                $response = $this->e2pdf_view($atts);
                            } elseif ($attributes->get('attachment')) {
                                $response = $this->e2pdf_attachment($atts);
                            } elseif ($attributes->get('zapier')) {
                                $response = $this->e2pdf_zapier($atts);
                            } elseif ($attributes->get('output') === 'path') {
                                $response = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_path', $filepath, $atts);
                            } else {
                                if ($attributes->get('local')) {
                                    $url = $this->helper->get_frontend_local_pdf_url($filepath);
                                    switch ($attributes->get('output')) {
                                        case 'url':
                                            $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                            $response = esc_url($url);
                                            break;
                                        case 'url_raw':
                                            $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                            $response = esc_url_raw($url);
                                            break;
                                        case 'url_encode':
                                            $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                            $response = urlencode(esc_url_raw($url));
                                            break;
                                        default:
                                            $response = '';
                                            break;
                                    }
                                } else {
                                    if (!$entry->load_by_uid()) {
                                        $entry->save();
                                    }
                                    if ($entry->get('ID')) {
                                        $url_data = [
                                            'page' => 'e2pdf-download',
                                            'uid' => $entry->get('uid'),
                                        ];
                                        $url_data = apply_filters('e2pdf_model_shortcode_url_data', $url_data, $atts);
                                        $url_data = apply_filters('e2pdf_model_shortcode_e2pdf_save_url_data', $url_data, $atts);
                                        $url = $this->helper->get_frontend_pdf_url(
                                                $url_data, $attributes->get('site_url'),
                                                [
                                                    'e2pdf_model_shortcode_site_url',
                                                    'e2pdf_model_shortcode_e2pdf_save_site_url',
                                                ]
                                        );
                                        switch ($attributes->get('output')) {
                                            case 'url':
                                                $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                                $response = esc_url($url);
                                                break;
                                            case 'url_raw':
                                                $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                                $response = esc_url_raw($url);
                                                break;
                                            case 'url_encode':
                                                $url = apply_filters('e2pdf_model_shortcode_e2pdf_save_output_url', $url, $atts);
                                                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                                $response = urlencode(esc_url_raw($url));
                                                break;
                                            default:
                                                $response = '';
                                                break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $response;
    }

    // e2pdf-zapier
    public function e2pdf_zapier($atts = []) {

        $response = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_zapier_atts', $atts);
        $attributes = (new Helper_E2pdf_Atts())->load($atts);

        if (!$attributes->get('webhook')) {
            return $response;
        }

        if ($attributes->get('pdf') || $attributes->get('attachment_id') || $attributes->get('file_id')) {
            $pdf = $attributes->get('pdf');
            $downloadable = false;
            if ($attributes->get('file_id')) {
                $pdf = (new Model_E2pdf_Gdrive())->exists($attributes->get('file_id'));
                if ($pdf) {
                    $downloadable = true;
                }
            } else {
                if ($attributes->get('attachment_id')) {
                    $pdf = get_attached_file($attributes->get('attachment_id'));
                } else {
                    $pdf = $this->helper->load('convert')->to_file_dir($pdf);
                }
                if (strpos($pdf, '/') !== 0 && !preg_match('/^[A-Za-z]:/', $pdf)) {
                    $pdf = ABSPATH . $pdf;
                }
                $downloadable = !$this->helper->load('filter')->is_stream($pdf) && file_exists($pdf) && $this->helper->load('filter')->is_downloadable($pdf);
            }

            if (!$downloadable) {
                return $response;
            }

            $entry = new Model_E2pdf_Entry();
            if ($attributes->get('attachment_id')) {
                $entry->set_data('attachment_id', $attributes->get('attachment_id'));
            } elseif ($attributes->get('file_id')) {
                $entry->set_data('file_id', $attributes->get('file_id'));
            } else {
                $entry->set_data('pdf', $pdf);
            }

            $classes = $attributes->get('class');
            $classes[] = 'e2pdf-download';

            $inline = '0';
            if ($attributes->get('inline') !== false) {
                $inline = $attributes->get('inline');
                $entry->set_data('inline', $inline);
            }

            if ($attributes->get('name') !== false) {
                $name = $attributes->get('name');
                $entry->set_data('name', $name);
            } else {
                $name = '';
            }
            $content_type = 'application/json';
            $blog_charset = get_option('blog_charset');
            if (!empty($blog_charset)) {
                $content_type .= '; charset=' . get_option('blog_charset');
            }
            if ($attributes->get('local')) {
                $url = $attributes->get('attachment_id') ? wp_get_attachment_url($attributes->get('attachment_id')) : $this->helper->get_frontend_local_pdf_url($pdf);
                $url = apply_filters('e2pdf_model_shortcode_e2pdf_zapier_pdf_url', $url, $atts);
                $ext = pathinfo($pdf, PATHINFO_EXTENSION);
                $name = basename($pdf, '.' . $ext);
            } else {
                if (!$entry->load_by_uid()) {
                    $entry->save();
                }

                if ($entry->get('ID')) {

                    $url_data = [
                        'page' => 'e2pdf-download',
                        'uid' => $entry->get('uid'),
                    ];
                    $url_data = apply_filters('e2pdf_model_shortcode_url_data', $url_data, $atts);
                    $url_data = apply_filters('e2pdf_model_shortcode_e2pdf_zapier_url_data', $url_data, $atts);

                    $url = esc_url_raw(
                            $this->helper->get_frontend_pdf_url(
                                    $url_data, $attributes->get('site_url'),
                                    [
                                        'e2pdf_model_shortcode_site_url',
                                        'e2pdf_model_shortcode_e2pdf_zapier_site_url',
                                    ]
                            )
                    );
                    $url = apply_filters('e2pdf_model_shortcode_e2pdf_zapier_pdf_url', $url, $atts);
                    $ext = pathinfo($pdf, PATHINFO_EXTENSION);
                    if (!$name) {
                        $name = basename($pdf, '.' . $ext);
                    }
                }
            }
            if ($attributes->get('local') || $entry->get('ID')) {
                $zapier = [];
                if ($attributes->get('id')) {
                    $zapier['id'] = $attributes->get('id');
                }
                if ($attributes->get('dataset')) {
                    $zapier['dataset'] = $attributes->get('dataset');
                }
                if ($attributes->get('dataset2')) {
                    $zapier['dataset2'] = $attributes->get('dataset2');
                }
                if ($attributes->get('wc_order_id')) {
                    $zapier['wc_order_id'] = $attributes->get('wc_order_id');
                }
                if ($attributes->get('wc_product_item_id')) {
                    $zapier['wc_product_item_id'] = $attributes->get('wc_product_item_id');
                }
                if ($entry->get('ID')) {
                    $zapier['uid'] = $entry->get('uid');
                }
                $zapier['name'] = $name;
                $zapier['format'] = strtolower($ext);
                $zapier['url'] = $url;

                $data = apply_filters(
                        'e2pdf_model_shortcode_e2pdf_zapier_data',
                        array_merge(
                                $zapier, $attributes->get('arguments')
                        ), $atts
                );

                $zapier_args = apply_filters(
                        'e2pdf_model_shortcode_e2pdf_zapier_args',
                        [
                            'method' => 'POST',
                            'body' => json_encode($data), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
                            'headers' => [
                                'Content-Type' => $content_type,
                            ],
                        ], $atts
                );
                $result = [];
                if ($attributes->get('webhook') !== 'local') {
                    $result = wp_remote_post($attributes->get('webhook'), $zapier_args);
                }
                $response = apply_filters('e2pdf_model_shortcode_e2pdf_zapier_response', $response, $result, $atts, $data);
            }

            return $response;
        }

        if (!$attributes->get('id') || (!$attributes->get('dataset') && !$attributes->get('dataset2'))) {
            return $response;
        }

        $template = new Model_E2pdf_Template();
        if (!$template->load($attributes->get('id'), false)) {
            return $response;
        }

        $entry = new Model_E2pdf_Entry();

        $template->extension()->patch('template_id', $attributes->get('id'), $entry);
        $template->extension()->patch('dataset', $attributes->get('dataset'), $entry);
        $template->extension()->patch('dataset2', $attributes->get('dataset2'), $entry);
        $template->extension()->patch('wc_order_id', $attributes->get('wc_order_id'), $entry);
        $template->extension()->patch('wc_product_item_id', $attributes->get('wc_product_item_id'), $entry);
        // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
        if (!($template->get('extension') === 'wordpress' && $template->get('item') === '-3')) {
            $template->extension()->patch('user_id', $attributes->get('user_id'), $entry);
        }
        $template->extension()->patch('args', $attributes->get('arguments'), $entry);
        $template->extension()->patch('storing_engine', $template->extension()->get_storing_engine(), $entry);

        $classes = $attributes->get('class');
        $classes[] = 'e2pdf-download';

        $options = apply_filters('e2pdf_model_shortcode_extension_options', [], $template);
        $options = apply_filters('e2pdf_model_shortcode_e2pdf_zapier_extension_options', $options, $template);
        foreach ($options as $option_key => $option_value) {
            $template->extension()->set($option_key, $option_value);
        }

        if ($template->extension()->verify() && $this->process_shortcode('e2pdf_zapier', $template, $attributes)) {

            $template->patch('inline', $attributes->get('inline'), $entry, $attributes->get('filter'));
            $template->patch('flatten', $attributes->get('flatten'), $entry, $attributes->get('filter'));
            $template->patch('format', $attributes->get('format'), $entry, $attributes->get('filter'));
            $template->patch('password', $attributes->get('password'), $entry, $attributes->get('filter'));
            $template->patch('dpdf', $attributes->get('dpdf'), $entry, $attributes->get('filter'));
            $template->patch('name', $attributes->get('name'), $entry, $attributes->get('filter'));
            $template->patch('meta_title', $attributes->get('meta_title'), $entry, $attributes->get('filter'));
            $template->patch('meta_subject', $attributes->get('meta_subject'), $entry, $attributes->get('filter'));
            $template->patch('meta_author', $attributes->get('meta_author'), $entry, $attributes->get('filter'));
            $template->patch('meta_keywords', $attributes->get('meta_keywords'), $entry, $attributes->get('filter'));

            if (!$entry->load_by_uid()) {
                $entry->save();
            }

            if ($entry->get('ID')) {

                $content_type = 'application/json';
                $blog_charset = get_option('blog_charset');
                if (!empty($blog_charset)) {
                    $content_type .= '; charset=' . get_option('blog_charset');
                }

                $url_data = [
                    'page' => 'e2pdf-download',
                    'uid' => $entry->get('uid'),
                ];
                $url_data = apply_filters('e2pdf_model_shortcode_url_data', $url_data, $atts);
                $url_data = apply_filters('e2pdf_model_shortcode_e2pdf_zapier_url_data', $url_data, $atts);

                $url = esc_url_raw(
                        $this->helper->get_frontend_pdf_url(
                                $url_data, $attributes->get('site_url'),
                                [
                                    'e2pdf_model_shortcode_site_url',
                                    'e2pdf_model_shortcode_e2pdf_zapier_site_url',
                                ]
                        )
                );
                $url = apply_filters('e2pdf_model_shortcode_e2pdf_zapier_pdf_url', $url, $atts);

                $zapier = [];
                if ($attributes->get('id')) {
                    $zapier['id'] = $attributes->get('id');
                }
                if ($attributes->get('dataset')) {
                    $zapier['dataset'] = $attributes->get('dataset');
                }
                if ($attributes->get('dataset2')) {
                    $zapier['dataset2'] = $attributes->get('dataset2');
                }
                if ($attributes->get('wc_order_id')) {
                    $zapier['wc_order_id'] = $attributes->get('wc_order_id');
                }
                if ($attributes->get('wc_product_item_id')) {
                    $zapier['wc_product_item_id'] = $attributes->get('wc_product_item_id');
                }

                $zapier['uid'] = $entry->get('uid');
                $zapier['name'] = $template->get('name');
                $zapier['format'] = $template->get('format');
                $zapier['url'] = $url;

                $data = apply_filters(
                        'e2pdf_model_shortcode_e2pdf_zapier_data',
                        array_merge(
                                $zapier, $attributes->get('arguments')
                        ), $atts
                );

                $zapier_args = apply_filters(
                        'e2pdf_model_shortcode_e2pdf_zapier_args',
                        [
                            'method' => 'POST',
                            'body' => json_encode($data), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
                            'headers' => [
                                'Content-Type' => $content_type,
                            ],
                        ], $atts
                );

                $result = [];
                if ($attributes->get('webhook') !== 'local') {
                    $result = wp_remote_post($attributes->get('webhook'), $zapier_args);
                }

                $response = apply_filters('e2pdf_model_shortcode_e2pdf_zapier_response', $response, $result, $atts, $data);
            }
        }

        return $response;
    }

    // e2pdf-view
    public function e2pdf_view($atts = []) {

        if (function_exists('vc_is_page_editable') && vc_is_page_editable()) {
            return '[e2pdf-view]';
        }

        $response = '';
        $name = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_view_atts', $atts);
        $attributes = (new Helper_E2pdf_Atts())->load($atts);

        $style = $attributes->get('style');

        $app_options = [];
        if ($attributes->get('resolution') !== false) {
            $app_options[] = 'resolution="' . esc_attr($attributes->get('resolution')) . '"';
        }
        if ($attributes->get('cursor') !== false) {
            $app_options[] = 'cursor="' . esc_attr($attributes->get('cursor')) . '"';
        }
        if ($attributes->get('scroll') !== false) {
            $app_options[] = 'scroll="' . esc_attr($attributes->get('scroll')) . '"';
        }
        if ($attributes->get('spread') !== false) {
            $app_options[] = 'spread="' . esc_attr($attributes->get('spread')) . '"';
        }

        $viewer_options = [];
        if ($attributes->get('page') !== false) {
            $viewer_options[] = 'page=' . $attributes->get('page');
        }

        if ($attributes->get('zoom') !== false) {
            $viewer_options[] = 'zoom=' . $attributes->get('zoom');
        }

        if ($attributes->get('nameddest') !== false) {
            $viewer_options[] = 'nameddest=' . $attributes->get('nameddest');
        }

        if ($attributes->get('pagemode') !== false) {
            $attributes->set('sidebar', $attributes->get('pagemode'));
        }

        if ($attributes->get('sidebar') !== false) {
            $viewer_options[] = 'pagemode=' . $attributes->get('sidebar');
        }

        $classes = $attributes->get('class');
        $classes[] = 'e2pdf-view';

        if ($attributes->get('preload')) {
            $classes[] = 'e2pdf-preload';
        }

        if ($attributes->get('responsive')) {
            $classes[] = 'e2pdf-responsive';
            if ($attributes->get('responsive') === 'page') {
                $classes[] = 'e2pdf-responsive-page';
            }
        }

        if ($attributes->get('single_page_mode')) {
            $classes[] = 'e2pdf-single-page-mode';
        }

        if ($attributes->get('theme', 'dark') === 'dark') {
            $classes[] = 'e2pdf-dark-theme';
        }

        if ($attributes->get('hide')) {
            $hidden = array_map('trim', explode(',', $attributes->get('hide')));
            foreach ($hidden as $item) {
                if ($item !== '') {
                    $classes[] = 'e2pdf-hide-' . $item;
                }
            }
        }

        if ($attributes->get('background') !== false) {
            $classes[] = 'e2pdf-hide-background';
        }

        if ($attributes->get('border') !== false) {
            array_unshift($style, 'border:' . $attributes->get('border'));
        }

        if ($attributes->get('background') !== false) {
            array_unshift($style, 'background:' . $attributes->get('background'));
        }

        if ($attributes->get('pdf') || $attributes->get('attachment_id') || $attributes->get('file_id')) {
            $pdf = $attributes->get('pdf');
            if ($pdf && filter_var($pdf, FILTER_VALIDATE_URL)) {
                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                $file = urlencode($pdf);
                if (!empty($viewer_options)) {
                    $file .= '#' . implode('&', $viewer_options);
                }
                switch ($attributes->get('output')) {
                    case 'url':
                    case 'url_raw':
                    case 'url_encode':
                        $viewer_url = add_query_arg(
                                [
                                    'class' => implode(';', $classes),
                                    'file' => $file,
                                ],
                                $attributes->get('viewer', plugins_url('assets/pdf.js/web/viewer.html', $this->helper->get('plugin_file_path')))
                        );
                        if ($attributes->get('output') === 'url') {
                            $response = esc_url($viewer_url);
                        } elseif ($attributes->get('output') === 'url_raw') {
                            $response = esc_url_raw($viewer_url);
                        } elseif ($attributes->get('output') === 'url_encode') {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                            $response = urlencode(esc_url_raw($viewer_url));
                        }
                        break;
                    default:
                        $viewer_url = esc_url(
                                add_query_arg(
                                        [
                                            'file' => $file,
                                        ],
                                        $attributes->get('viewer', plugins_url('assets/pdf.js/web/viewer.html', $this->helper->get('plugin_file_path')))
                                )
                        );

                        $attrs = [
                            'onload="e2pdfViewer.iframeLoad(this)"',
                            'name="' . md5($this->helper->get('version')) . '"',
                            'style="' . esc_attr(implode(';', $style)) . '"',
                            'class="' . esc_attr(implode(' ', $classes)) . '"',
                            implode(' ', $app_options),
                            'width="' . esc_attr($attributes->get('width', '100%')) . '"',
                            'height="' . esc_attr($attributes->get('height', '500')) . '"',
                        ];

                        if ($attributes->get('preload')) {
                            $attrs[] = 'preload="' . $viewer_url . '"';
                        } else {
                            $attrs[] = 'src="' . $viewer_url . '"';
                        }
                        $response = '<iframe ' . implode(' ', $attrs) . '></iframe>';
                        break;
                }
            } else {
                $downloadable = false;
                if ($attributes->get('file_id')) {
                    $pdf = (new Model_E2pdf_Gdrive())->exists($attributes->get('file_id'));
                    if ($pdf) {
                        $downloadable = true;
                    }
                } else {
                    if ($attributes->get('attachment_id')) {
                        $pdf = get_attached_file($attributes->get('attachment_id'));
                    } else {
                        $pdf = $this->helper->load('convert')->to_file_dir($pdf);
                    }
                    if (strpos($pdf, '/') !== 0 && !preg_match('/^[A-Za-z]:/', $pdf)) {
                        $pdf = ABSPATH . $pdf;
                    }
                    $downloadable = !$this->helper->load('filter')->is_stream($pdf) && file_exists($pdf) && $this->helper->load('filter')->is_downloadable($pdf);
                }
                if (!$downloadable) {
                    return $response;
                }
                $entry = new Model_E2pdf_Entry();
                if ($attributes->get('attachment_id')) {
                    $entry->set_data('attachment_id', $attributes->get('attachment_id'));
                } else {
                    $entry->set_data('pdf', $pdf);
                }

                $inline = '0';
                if ($attributes->get('inline') !== false) {
                    $inline = $attributes->get('inline');
                    $entry->set_data('inline', $inline);
                }

                if ($attributes->get('name') !== false) {
                    $name = $attributes->get('name');
                    $entry->set_data('name', $name);
                }

                if ($attributes->get('local')) {
                    $ext = pathinfo($pdf, PATHINFO_EXTENSION);
                    $url = $attributes->get('attachment_id') ? wp_get_attachment_url($attributes->get('attachment_id')) : $this->helper->get_frontend_local_pdf_url($pdf);
                    $url = apply_filters('e2pdf_model_shortcode_e2pdf_view_pdf_url', $url, $atts);
                    if ($ext == 'pdf') {
                        $file = $url;
                        if (!empty($viewer_options)) {
                            $file .= '#' . implode('&', $viewer_options);
                        }
                        switch ($attributes->get('output')) {
                            case 'url':
                            case 'url_raw':
                            case 'url_encode':
                                $viewer_url = add_query_arg(
                                        [
                                            'class' => implode(';', $classes),
                                            'file' => $file,
                                        ],
                                        $attributes->get('viewer', plugins_url('assets/pdf.js/web/viewer.html', $this->helper->get('plugin_file_path')))
                                );
                                if ($attributes->get('output') === 'url') {
                                    $response = esc_url($viewer_url);
                                } elseif ($attributes->get('output') === 'url_raw') {
                                    $response = esc_url_raw($viewer_url);
                                } elseif ($attributes->get('output') === 'url_encode') {
                                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                    $response = urlencode(esc_url_raw($viewer_url));
                                }
                                break;
                            default:
                                $viewer_url = esc_url(
                                        add_query_arg(
                                                [
                                                    'file' => $file,
                                                ],
                                                $attributes->get('viewer', plugins_url('assets/pdf.js/web/viewer.html', $this->helper->get('plugin_file_path')))
                                        )
                                );
                                $attrs = [
                                    'onload="e2pdfViewer.iframeLoad(this)"',
                                    'name="' . md5($this->helper->get('version')) . '"',
                                    'style="' . esc_attr(implode(';', $style)) . '"',
                                    'class="' . esc_attr(implode(' ', $classes)) . '"',
                                    implode(' ', $app_options),
                                    'width="' . esc_attr($attributes->get('width', '100%')) . '"',
                                    'height="' . esc_attr($attributes->get('height', '500')) . '"',
                                ];

                                if ($attributes->get('preload')) {
                                    $attrs[] = 'preload="' . $viewer_url . '"';
                                } else {
                                    $attrs[] = 'src="' . $viewer_url . '"';
                                }
                                $response = '<iframe ' . implode(' ', $attrs) . '></iframe>';
                                break;
                        }
                    } elseif ($ext == 'jpg') {
                        switch ($attributes->get('output')) {
                            case 'url':
                                $response = esc_url($url);
                                break;
                            case 'url_raw':
                                $response = esc_url_raw($url);
                                break;
                            case 'url_encode':
                                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                $response = urlencode(esc_url_raw($url));
                                break;
                            default:
                                $url = esc_url($url);
                                if ($attributes->get('preload', 'true')) {
                                    $classes[] = 'e2pdf-preload';
                                    if ($attributes->get('theme', 'dark') === 'dark') {
                                        $style[] = 'background: url(\'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0OCIgaGVpZ2h0PSI0OCIgdmlld0JveD0iMCAwIDI0IDI0Ij48ZyBzdHJva2U9IndoaXRlIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSI5LjUiIGZpbGw9Im5vbmUiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLXdpZHRoPSIzIj48YW5pbWF0ZSBhdHRyaWJ1dGVOYW1lPSJzdHJva2UtZGFzaGFycmF5IiBjYWxjTW9kZT0ic3BsaW5lIiBkdXI9IjEuNXMiIGtleVNwbGluZXM9IjAuNDIsMCwwLjU4LDE7MC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxIiBrZXlUaW1lcz0iMDswLjQ3NTswLjk1OzEiIHJlcGVhdENvdW50PSJpbmRlZmluaXRlIiB2YWx1ZXM9IjAgMTUwOzQyIDE1MDs0MiAxNTA7NDIgMTUwIi8+PGFuaW1hdGUgYXR0cmlidXRlTmFtZT0ic3Ryb2tlLWRhc2hvZmZzZXQiIGNhbGNNb2RlPSJzcGxpbmUiIGR1cj0iMS41cyIga2V5U3BsaW5lcz0iMC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxOzAuNDIsMCwwLjU4LDEiIGtleVRpbWVzPSIwOzAuNDc1OzAuOTU7MSIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHZhbHVlcz0iMDstMTY7LTU5Oy01OSIvPjwvY2lyY2xlPjxhbmltYXRlVHJhbnNmb3JtIGF0dHJpYnV0ZU5hbWU9InRyYW5zZm9ybSIgZHVyPSIycyIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHR5cGU9InJvdGF0ZSIgdmFsdWVzPSIwIDEyIDEyOzM2MCAxMiAxMiIvPjwvZz48L3N2Zz4=\') no-repeat center center';
                                    } else {
                                        $style[] = 'background: url(\'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0OCIgaGVpZ2h0PSI0OCIgdmlld0JveD0iMCAwIDI0IDI0Ij48ZyBzdHJva2U9ImJsYWNrIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSI5LjUiIGZpbGw9Im5vbmUiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLXdpZHRoPSIzIj48YW5pbWF0ZSBhdHRyaWJ1dGVOYW1lPSJzdHJva2UtZGFzaGFycmF5IiBjYWxjTW9kZT0ic3BsaW5lIiBkdXI9IjEuNXMiIGtleVNwbGluZXM9IjAuNDIsMCwwLjU4LDE7MC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxIiBrZXlUaW1lcz0iMDswLjQ3NTswLjk1OzEiIHJlcGVhdENvdW50PSJpbmRlZmluaXRlIiB2YWx1ZXM9IjAgMTUwOzQyIDE1MDs0MiAxNTA7NDIgMTUwIi8+PGFuaW1hdGUgYXR0cmlidXRlTmFtZT0ic3Ryb2tlLWRhc2hvZmZzZXQiIGNhbGNNb2RlPSJzcGxpbmUiIGR1cj0iMS41cyIga2V5U3BsaW5lcz0iMC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxOzAuNDIsMCwwLjU4LDEiIGtleVRpbWVzPSIwOzAuNDc1OzAuOTU7MSIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHZhbHVlcz0iMDstMTY7LTU5Oy01OSIvPjwvY2lyY2xlPjxhbmltYXRlVHJhbnNmb3JtIGF0dHJpYnV0ZU5hbWU9InRyYW5zZm9ybSIgZHVyPSIycyIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHR5cGU9InJvdGF0ZSIgdmFsdWVzPSIwIDEyIDEyOzM2MCAxMiAxMiIvPjwvZz48L3N2Zz4=\') no-repeat center center';
                                    }
                                }
                                $attrs = [
                                    'style="' . esc_attr(implode(';', $style)) . '"',
                                    'class="' . esc_attr(implode(' ', $classes)) . '"',
                                    'width="' . esc_attr($attributes->get('width', '100%')) . '"',
                                ];
                                if ($attributes->get('preload', 'true')) {
                                    $attrs[] = 'onload="e2pdfViewer.imageLoad(this)"';
                                    $attrs[] = 'preload="' . $url . '"';
                                    $attrs[] = 'src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mM8VA8AAgkBQ6KtxDkAAAAASUVORK5CYII="';
                                } else {
                                    $attrs[] = 'src="' . $url . '"';
                                }
                                $response = '<img ' . implode(' ', $attrs) . '>';
                                break;
                        }
                    }
                } else {
                    if (!$entry->load_by_uid()) {
                        $entry->save();
                    }

                    if ($entry->get('ID')) {
                        $ext = pathinfo($pdf, PATHINFO_EXTENSION);
                        if (!$name) {
                            $name = basename($pdf, '.' . $ext);
                        }

                        $url_data = [
                            'page' => 'e2pdf-download',
                            'uid' => $entry->get('uid'),
                            'v' => $this->helper->get('version'),
                        ];
                        if ($ext == 'pdf') {
                            $url_data['saveName'] = $name;
                        }
                        $url_data = apply_filters('e2pdf_model_shortcode_url_data', $url_data, $atts);
                        $url_data = apply_filters('e2pdf_model_shortcode_e2pdf_viewer_url_data', $url_data, $atts);

                        $url = esc_url_raw(
                                $this->helper->get_frontend_pdf_url(
                                        $url_data, $attributes->get('site_url'),
                                        [
                                            'e2pdf_model_shortcode_site_url',
                                            'e2pdf_model_shortcode_e2pdf_view_site_url',
                                        ]
                                )
                        );
                        $url = apply_filters('e2pdf_model_shortcode_e2pdf_view_pdf_url', $url, $atts);

                        if ($ext == 'pdf') {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                            $file = urlencode($url);
                            if (!empty($viewer_options)) {
                                $file .= '#' . implode('&', $viewer_options);
                            }
                            switch ($attributes->get('output')) {
                                case 'url':
                                case 'url_raw':
                                case 'url_encode':
                                    $viewer_url = add_query_arg(
                                            [
                                                'class' => implode(';', $classes),
                                                'file' => $file,
                                            ],
                                            $attributes->get('viewer', plugins_url('assets/pdf.js/web/viewer.html', $this->helper->get('plugin_file_path')))
                                    );
                                    if ($attributes->get('output') === 'url') {
                                        $response = esc_url($viewer_url);
                                    } elseif ($attributes->get('output') === 'url_raw') {
                                        $response = esc_url_raw($viewer_url);
                                    } elseif ($attributes->get('output') === 'url_encode') {
                                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                        $response = urlencode(esc_url_raw($viewer_url));
                                    }
                                    break;
                                default:
                                    $viewer_url = esc_url(
                                            add_query_arg(
                                                    [
                                                        'file' => $file,
                                                    ],
                                                    $attributes->get('viewer', plugins_url('assets/pdf.js/web/viewer.html', $this->helper->get('plugin_file_path')))
                                            )
                                    );
                                    $attrs = [
                                        'onload="e2pdfViewer.iframeLoad(this)"',
                                        'name="' . md5($this->helper->get('version')) . '"',
                                        'style="' . esc_attr(implode(';', $style)) . '"',
                                        'class="' . esc_attr(implode(' ', $classes)) . '"',
                                        implode(' ', $app_options),
                                        'width="' . esc_attr($attributes->get('width', '100%')) . '"',
                                        'height="' . esc_attr($attributes->get('height', '500')) . '"',
                                    ];

                                    if ($attributes->get('preload')) {
                                        $attrs[] = 'preload="' . $viewer_url . '"';
                                    } else {
                                        $attrs[] = 'src="' . $viewer_url . '"';
                                    }
                                    $response = '<iframe ' . implode(' ', $attrs) . '></iframe>';
                                    break;
                            }
                        } elseif ($ext == 'jpg') {
                            switch ($attributes->get('output')) {
                                case 'url':
                                    $response = esc_url($url);
                                    break;
                                case 'url_raw':
                                    $response = esc_url_raw($url);
                                    break;
                                case 'url_encode':
                                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                    $response = urlencode(esc_url_raw($url));
                                    break;
                                default:
                                    $url = esc_url($url);
                                    if ($attributes->get('preload', 'true')) {
                                        $classes[] = 'e2pdf-preload';
                                        if ($attributes->get('theme', 'dark') === 'dark') {
                                            $style[] = 'background: url(\'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0OCIgaGVpZ2h0PSI0OCIgdmlld0JveD0iMCAwIDI0IDI0Ij48ZyBzdHJva2U9IndoaXRlIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSI5LjUiIGZpbGw9Im5vbmUiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLXdpZHRoPSIzIj48YW5pbWF0ZSBhdHRyaWJ1dGVOYW1lPSJzdHJva2UtZGFzaGFycmF5IiBjYWxjTW9kZT0ic3BsaW5lIiBkdXI9IjEuNXMiIGtleVNwbGluZXM9IjAuNDIsMCwwLjU4LDE7MC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxIiBrZXlUaW1lcz0iMDswLjQ3NTswLjk1OzEiIHJlcGVhdENvdW50PSJpbmRlZmluaXRlIiB2YWx1ZXM9IjAgMTUwOzQyIDE1MDs0MiAxNTA7NDIgMTUwIi8+PGFuaW1hdGUgYXR0cmlidXRlTmFtZT0ic3Ryb2tlLWRhc2hvZmZzZXQiIGNhbGNNb2RlPSJzcGxpbmUiIGR1cj0iMS41cyIga2V5U3BsaW5lcz0iMC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxOzAuNDIsMCwwLjU4LDEiIGtleVRpbWVzPSIwOzAuNDc1OzAuOTU7MSIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHZhbHVlcz0iMDstMTY7LTU5Oy01OSIvPjwvY2lyY2xlPjxhbmltYXRlVHJhbnNmb3JtIGF0dHJpYnV0ZU5hbWU9InRyYW5zZm9ybSIgZHVyPSIycyIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHR5cGU9InJvdGF0ZSIgdmFsdWVzPSIwIDEyIDEyOzM2MCAxMiAxMiIvPjwvZz48L3N2Zz4=\') no-repeat center center';
                                        } else {
                                            $style[] = 'background: url(\'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0OCIgaGVpZ2h0PSI0OCIgdmlld0JveD0iMCAwIDI0IDI0Ij48ZyBzdHJva2U9ImJsYWNrIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSI5LjUiIGZpbGw9Im5vbmUiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLXdpZHRoPSIzIj48YW5pbWF0ZSBhdHRyaWJ1dGVOYW1lPSJzdHJva2UtZGFzaGFycmF5IiBjYWxjTW9kZT0ic3BsaW5lIiBkdXI9IjEuNXMiIGtleVNwbGluZXM9IjAuNDIsMCwwLjU4LDE7MC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxIiBrZXlUaW1lcz0iMDswLjQ3NTswLjk1OzEiIHJlcGVhdENvdW50PSJpbmRlZmluaXRlIiB2YWx1ZXM9IjAgMTUwOzQyIDE1MDs0MiAxNTA7NDIgMTUwIi8+PGFuaW1hdGUgYXR0cmlidXRlTmFtZT0ic3Ryb2tlLWRhc2hvZmZzZXQiIGNhbGNNb2RlPSJzcGxpbmUiIGR1cj0iMS41cyIga2V5U3BsaW5lcz0iMC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxOzAuNDIsMCwwLjU4LDEiIGtleVRpbWVzPSIwOzAuNDc1OzAuOTU7MSIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHZhbHVlcz0iMDstMTY7LTU5Oy01OSIvPjwvY2lyY2xlPjxhbmltYXRlVHJhbnNmb3JtIGF0dHJpYnV0ZU5hbWU9InRyYW5zZm9ybSIgZHVyPSIycyIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHR5cGU9InJvdGF0ZSIgdmFsdWVzPSIwIDEyIDEyOzM2MCAxMiAxMiIvPjwvZz48L3N2Zz4=\') no-repeat center center';
                                        }
                                    }
                                    $attrs = [
                                        'style="' . esc_attr(implode(';', $style)) . '"',
                                        'class="' . esc_attr(implode(' ', $classes)) . '"',
                                        'width="' . esc_attr($attributes->get('width', '100%')) . '"',
                                    ];
                                    if ($attributes->get('preload', 'true')) {
                                        $attrs[] = 'onload="e2pdfViewer.imageLoad(this)"';
                                        $attrs[] = 'preload="' . $url . '"';
                                        $attrs[] = 'src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mM8VA8AAgkBQ6KtxDkAAAAASUVORK5CYII="';
                                    } else {
                                        $attrs[] = 'src="' . $url . '"';
                                    }
                                    $response = '<img ' . implode(' ', $attrs) . '>';
                                    break;
                            }
                        }
                    }
                }
            }
            return $response;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['e2pdf-hash']) && !$attributes->get('dataset')) {
            $hash_id = sanitize_text_field(wp_unslash($_GET['e2pdf-hash']));
            $attributes->set('dataset', get_transient('e2pdf_hash_' . $hash_id));
            if ($attributes->get('dataset') && apply_filters('e2pdf_hash_clear', true, 'shortcode', $atts) && has_action('shutdown', [$this, 'action_e2pdf_hash_clear']) === false) {
                add_action('shutdown', [$this, 'action_e2pdf_hash_clear']);
            }
        }
        // phpcs:enable

        if (!$attributes->get('id') || (!$attributes->get('dataset') && !$attributes->get('dataset2'))) {
            return $response;
        }

        $template = new Model_E2pdf_Template();
        if (!$template->load($attributes->get('id'), false)) {
            return $response;
        }

        $entry = new Model_E2pdf_Entry();

        $template->extension()->patch('template_id', $attributes->get('id'), $entry);
        $template->extension()->patch('dataset', $attributes->get('dataset'), $entry);
        $template->extension()->patch('dataset2', $attributes->get('dataset2'), $entry);
        $template->extension()->patch('wc_order_id', $attributes->get('wc_order_id'), $entry);
        $template->extension()->patch('wc_product_item_id', $attributes->get('wc_product_item_id'), $entry);
        // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
        if (!($template->get('extension') === 'wordpress' && $template->get('item') === '-3')) {
            $template->extension()->patch('user_id', $attributes->get('user_id'), $entry);
        }
        $template->extension()->patch('args', $attributes->get('arguments'), $entry);
        $template->extension()->patch('storing_engine', $template->extension()->get_storing_engine(), $entry);

        $options = apply_filters('e2pdf_model_shortcode_extension_options', [], $template);
        $options = apply_filters('e2pdf_model_shortcode_e2pdf_view_extension_options', $options, $template);
        foreach ($options as $option_key => $option_value) {
            $template->extension()->set($option_key, $option_value);
        }

        if ($template->extension()->verify() && $this->process_shortcode('e2pdf_view', $template, $attributes)) {

            $template->patch('inline', $attributes->get('inline'), $entry, $attributes->get('filter'));
            $template->patch('flatten', $attributes->get('flatten'), $entry, $attributes->get('filter'));
            $template->patch('format', $attributes->get('format'), $entry, $attributes->get('filter'));
            $template->patch('password', $attributes->get('password'), $entry, $attributes->get('filter'));
            $template->patch('dpdf', $attributes->get('dpdf'), $entry, $attributes->get('filter'));
            $template->patch('name', $attributes->get('name'), $entry, $attributes->get('filter'));
            $template->patch('meta_title', $attributes->get('meta_title'), $entry, $attributes->get('filter'));
            $template->patch('meta_subject', $attributes->get('meta_subject'), $entry, $attributes->get('filter'));
            $template->patch('meta_author', $attributes->get('meta_author'), $entry, $attributes->get('filter'));
            $template->patch('meta_keywords', $attributes->get('meta_keywords'), $entry, $attributes->get('filter'));

            if (!$entry->load_by_uid()) {
                $entry->save();
            }

            if ($entry->get('ID')) {
                if ($template->get('format') == 'jpg') {

                    $url_data = [
                        'page' => 'e2pdf-download',
                        'uid' => $entry->get('uid'),
                        'v' => $this->helper->get('version'),
                    ];
                    $url_data = apply_filters('e2pdf_model_shortcode_url_data', $url_data, $atts);
                    $url_data = apply_filters('e2pdf_model_shortcode_e2pdf_viewer_url_data', $url_data, $atts);

                    $url = esc_url_raw(
                            $this->helper->get_frontend_pdf_url(
                                    $url_data, $attributes->get('site_url'),
                                    [
                                        'e2pdf_model_shortcode_site_url',
                                        'e2pdf_model_shortcode_e2pdf_view_site_url',
                                    ]
                            )
                    );
                    $url = apply_filters('e2pdf_model_shortcode_e2pdf_view_pdf_url', $url, $atts);
                    switch ($attributes->get('output')) {
                        case 'url':
                            $response = esc_url($url);
                            break;
                        case 'url_raw':
                            $response = esc_url_raw($url);
                            break;
                        case 'url_encode':
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                            $response = urlencode(esc_url_raw($url));
                            break;
                        default:
                            $url = esc_url($url);
                            if ($attributes->get('preload', 'true')) {
                                $classes[] = 'e2pdf-preload';
                                if ($attributes->get('theme', 'dark') === 'dark') {
                                    $style[] = 'background: url(\'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0OCIgaGVpZ2h0PSI0OCIgdmlld0JveD0iMCAwIDI0IDI0Ij48ZyBzdHJva2U9IndoaXRlIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSI5LjUiIGZpbGw9Im5vbmUiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLXdpZHRoPSIzIj48YW5pbWF0ZSBhdHRyaWJ1dGVOYW1lPSJzdHJva2UtZGFzaGFycmF5IiBjYWxjTW9kZT0ic3BsaW5lIiBkdXI9IjEuNXMiIGtleVNwbGluZXM9IjAuNDIsMCwwLjU4LDE7MC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxIiBrZXlUaW1lcz0iMDswLjQ3NTswLjk1OzEiIHJlcGVhdENvdW50PSJpbmRlZmluaXRlIiB2YWx1ZXM9IjAgMTUwOzQyIDE1MDs0MiAxNTA7NDIgMTUwIi8+PGFuaW1hdGUgYXR0cmlidXRlTmFtZT0ic3Ryb2tlLWRhc2hvZmZzZXQiIGNhbGNNb2RlPSJzcGxpbmUiIGR1cj0iMS41cyIga2V5U3BsaW5lcz0iMC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxOzAuNDIsMCwwLjU4LDEiIGtleVRpbWVzPSIwOzAuNDc1OzAuOTU7MSIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHZhbHVlcz0iMDstMTY7LTU5Oy01OSIvPjwvY2lyY2xlPjxhbmltYXRlVHJhbnNmb3JtIGF0dHJpYnV0ZU5hbWU9InRyYW5zZm9ybSIgZHVyPSIycyIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHR5cGU9InJvdGF0ZSIgdmFsdWVzPSIwIDEyIDEyOzM2MCAxMiAxMiIvPjwvZz48L3N2Zz4=\') no-repeat center center';
                                } else {
                                    $style[] = 'background: url(\'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0OCIgaGVpZ2h0PSI0OCIgdmlld0JveD0iMCAwIDI0IDI0Ij48ZyBzdHJva2U9ImJsYWNrIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSI5LjUiIGZpbGw9Im5vbmUiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLXdpZHRoPSIzIj48YW5pbWF0ZSBhdHRyaWJ1dGVOYW1lPSJzdHJva2UtZGFzaGFycmF5IiBjYWxjTW9kZT0ic3BsaW5lIiBkdXI9IjEuNXMiIGtleVNwbGluZXM9IjAuNDIsMCwwLjU4LDE7MC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxIiBrZXlUaW1lcz0iMDswLjQ3NTswLjk1OzEiIHJlcGVhdENvdW50PSJpbmRlZmluaXRlIiB2YWx1ZXM9IjAgMTUwOzQyIDE1MDs0MiAxNTA7NDIgMTUwIi8+PGFuaW1hdGUgYXR0cmlidXRlTmFtZT0ic3Ryb2tlLWRhc2hvZmZzZXQiIGNhbGNNb2RlPSJzcGxpbmUiIGR1cj0iMS41cyIga2V5U3BsaW5lcz0iMC40MiwwLDAuNTgsMTswLjQyLDAsMC41OCwxOzAuNDIsMCwwLjU4LDEiIGtleVRpbWVzPSIwOzAuNDc1OzAuOTU7MSIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHZhbHVlcz0iMDstMTY7LTU5Oy01OSIvPjwvY2lyY2xlPjxhbmltYXRlVHJhbnNmb3JtIGF0dHJpYnV0ZU5hbWU9InRyYW5zZm9ybSIgZHVyPSIycyIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHR5cGU9InJvdGF0ZSIgdmFsdWVzPSIwIDEyIDEyOzM2MCAxMiAxMiIvPjwvZz48L3N2Zz4=\') no-repeat center center';
                                }
                            }
                            $attrs = [
                                'style="' . esc_attr(implode(';', $style)) . '"',
                                'class="' . esc_attr(implode(' ', $classes)) . '"',
                                'width="' . esc_attr($attributes->get('width', '100%')) . '"',
                            ];
                            if ($attributes->get('preload', 'true')) {
                                $attrs[] = 'onload="e2pdfViewer.imageLoad(this)"';
                                $attrs[] = 'preload="' . $url . '"';
                                $attrs[] = 'src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mM8VA8AAgkBQ6KtxDkAAAAASUVORK5CYII="';
                            } else {
                                $attrs[] = 'src="' . $url . '"';
                            }
                            $response = '<img ' . implode(' ', $attrs) . '>';
                            break;
                    }
                } else {
                    $url_data = [
                        'page' => 'e2pdf-download',
                        'uid' => $entry->get('uid'),
                        'v' => $this->helper->get('version'),
                        'saveName' => $template->get('name') . '.pdf',
                    ];
                    $url_data = apply_filters('e2pdf_model_shortcode_url_data', $url_data, $atts);
                    $url_data = apply_filters('e2pdf_model_shortcode_e2pdf_viewer_url_data', $url_data, $atts);

                    $url = esc_url_raw(
                            $this->helper->get_frontend_pdf_url(
                                    $url_data, $attributes->get('site_url'),
                                    [
                                        'e2pdf_model_shortcode_site_url',
                                        'e2pdf_model_shortcode_e2pdf_view_site_url',
                                    ]
                            )
                    );
                    $url = apply_filters('e2pdf_model_shortcode_e2pdf_view_pdf_url', $url, $atts);
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                    $file = urlencode($url);
                    if (!empty($viewer_options)) {
                        $file .= '#' . implode('&', $viewer_options);
                    }
                    switch ($attributes->get('output')) {
                        case 'url':
                        case 'url_raw':
                        case 'url_encode':
                            $viewer_url = add_query_arg(
                                    [
                                        'class' => implode(';', $classes),
                                        'file' => $file,
                                    ],
                                    $attributes->get('viewer', plugins_url('assets/pdf.js/web/viewer.html', $this->helper->get('plugin_file_path')))
                            );
                            if ($attributes->get('output') === 'url') {
                                $response = esc_url($viewer_url);
                            } elseif ($attributes->get('output') === 'url_raw') {
                                $response = esc_url_raw($viewer_url);
                            } elseif ($attributes->get('output') === 'url_encode') {
                                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                $response = urlencode(esc_url_raw($viewer_url));
                            }
                            break;
                        default:
                            $viewer_url = esc_url(
                                    add_query_arg(
                                            [
                                                'file' => $file,
                                            ],
                                            $attributes->get('viewer', plugins_url('assets/pdf.js/web/viewer.html', $this->helper->get('plugin_file_path')))
                                    )
                            );
                            $attrs = [
                                'onload="e2pdfViewer.iframeLoad(this)"',
                                'name="' . md5($this->helper->get('version')) . '"',
                                'style="' . esc_attr(implode(';', $style)) . '"',
                                'class="' . esc_attr(implode(' ', $classes)) . '"',
                                implode(' ', $app_options),
                                'width="' . esc_attr($attributes->get('width', '100%')) . '"',
                                'height="' . esc_attr($attributes->get('height', '500')) . '"',
                            ];

                            if ($attributes->get('preload')) {
                                $attrs[] = 'preload="' . $viewer_url . '"';
                            } else {
                                $attrs[] = 'src="' . $viewer_url . '"';
                            }
                            $response = '<iframe ' . implode(' ', $attrs) . '></iframe>';
                            break;
                    }
                }
            }
        }

        return $response;
    }

    // e2pdf-adobesign
    public function e2pdf_adobesign($atts = []) {

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_adobesign_atts', $atts);
        $attributes = (new Helper_E2pdf_Atts())->load($atts);

        $response = '';

        if (!$attributes->get('apply') || !$attributes->get('id') || (!$attributes->get('dataset') && !$attributes->get('dataset2'))) {
            return $response;
        }

        $template = new Model_E2pdf_Template();
        if (!$template->load($attributes->get('id'))) {
            return $response;
        }

        $entry = new Model_E2pdf_Entry();

        $template->extension()->patch('template_id', $attributes->get('id'), $entry);
        $template->extension()->patch('dataset', $attributes->get('dataset'), $entry);
        $template->extension()->patch('dataset2', $attributes->get('dataset2'), $entry);
        $template->extension()->patch('wc_order_id', $attributes->get('wc_order_id'), $entry);
        $template->extension()->patch('wc_product_item_id', $attributes->get('wc_product_item_id'), $entry);
        // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
        if (!($template->get('extension') === 'wordpress' && $template->get('item') === '-3')) {
            $template->extension()->patch('user_id', $attributes->get('user_id'), $entry);
        }
        $template->extension()->patch('args', $attributes->get('arguments'), $entry);
        $template->extension()->patch('storing_engine', $template->extension()->get_storing_engine(), $entry);

        $options = apply_filters('e2pdf_model_shortcode_extension_options', [], $template);
        $options = apply_filters('e2pdf_model_shortcode_e2pdf_adobesign_extension_options', $options, $template);
        foreach ($options as $option_key => $option_value) {
            $template->extension()->set($option_key, $option_value);
        }

        if ($template->extension()->verify() && $this->process_shortcode('e2pdf_adobesign', $template, $attributes)) {

            $template->patch('inline', $attributes->get('inline'), $entry, $attributes->get('filter'));
            $template->patch('flatten', $attributes->get('flatten'), $entry, $attributes->get('filter'));
            $template->patch('format', $attributes->get('format'), $entry, $attributes->get('filter'));
            $template->patch('password', $attributes->get('password'), $entry, $attributes->get('filter'));
            $template->patch('dpdf', $attributes->get('dpdf'), $entry, $attributes->get('filter'));
            $template->patch('name', $attributes->get('name'), $entry, $attributes->get('filter'));
            $template->patch('meta_title', $attributes->get('meta_title'), $entry, $attributes->get('filter'));
            $template->patch('meta_subject', $attributes->get('meta_subject'), $entry, $attributes->get('filter'));
            $template->patch('meta_author', $attributes->get('meta_author'), $entry, $attributes->get('filter'));
            $template->patch('meta_keywords', $attributes->get('meta_keywords'), $entry, $attributes->get('filter'));

            $template->extension()->set('entry', $entry);
            $template->fill();
            $request = $template->render();

            if (!isset($request['error'])) {

                $tmp_dir = $this->helper->get('tmp_dir') . 'e2pdf' . md5($entry->get('uid')) . '/';
                $this->helper->create_dir($tmp_dir);

                $filename = $template->get('name') . '.pdf';
                $filename = $this->helper->load('convert')->to_file_name($filename);
                $filepath = $tmp_dir . $filename;
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
                file_put_contents($filepath, $request['file'], LOCK_EX);

                $disable = $attributes->get('disable');

                if (file_exists($filepath)) {

                    $agreement_id = false;
                    $documents = [];
                    if (!in_array('post_transientDocuments', $disable, true)) {
                        $model_e2pdf_adobesign = new Model_E2pdf_AdobeSign();
                        $model_e2pdf_adobesign->set(
                                [
                                    'action' => 'api/rest/v5/transientDocuments',
                                    'headers' => [
                                        'Content-Type: multipart/form-data',
                                    ],
                                    'data' => [
                                        'File-Name' => $filename,
                                        'Mime-Type' => 'application/pdf',
                                        'File' => class_exists('cURLFile') ? new cURLFile($filepath) : '@' . $filepath,
                                    ],
                                ]
                        );

                        $transientDocumentId = $model_e2pdf_adobesign->request('transientDocumentId');
                        if ($transientDocumentId) {
                            $documents[] = [
                                'transientDocumentId' => $transientDocumentId,
                            ];
                        }
                        $model_e2pdf_adobesign->flush();
                    }
                    // phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase
                    $documents = apply_filters('e2pdf_model_shortcode_e2pdf_adobesign_fileInfos', $documents, $atts, $template, $entry, $template->extension(), $filepath);

                    if (!in_array('post_agreements', $disable, true) && !empty($documents)) {
                        $recipients = [];
                        if ($attributes->get('recipients') !== false) {
                            $recipients_list = explode(',', $template->extension()->render($attributes->get('recipients')));
                            foreach ($recipients_list as $recipient_info) {
                                $recipients[] = [
                                    'recipientSetMemberInfos' => [
                                        'email' => trim($recipient_info),
                                    ],
                                    'recipientSetRole' => 'SIGNER',
                                ];
                            }
                        }

                        $data = [
                            'documentCreationInfo' => [
                                'signatureType' => 'ESIGN',
                                'recipientSetInfos' => $recipients,
                                'signatureFlow' => 'SENDER_SIGNATURE_NOT_REQUIRED',
                                'fileInfos' => $documents,
                                'name' => $template->get('name'),
                            ],
                        ];

                        $data = apply_filters('e2pdf_model_shortcode_e2pdf_adobesign_post_agreements_data', $data, $atts, $template, $entry, $template->extension(), $filepath, $documents);

                        $model_e2pdf_adobesign = new Model_E2pdf_AdobeSign();
                        $model_e2pdf_adobesign->set(
                                [
                                    'action' => 'api/rest/v5/agreements',
                                    'data' => $data,
                                ]
                        );

                        $agreement_id = $model_e2pdf_adobesign->request('agreementId');
                        $model_e2pdf_adobesign->flush();
                    }

                    $response = apply_filters('e2pdf_model_shortcode_e2pdf_adobesign_response', $response, $atts, $template, $entry, $template->extension(), $filepath, $documents, $agreement_id);
                }

                $this->helper->delete_dir($tmp_dir);
                return $response;
            }
        }

        return $response;
    }

    // e2pdf-format-number
    public function e2pdf_format_number($atts = [], $value = '') {

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /*
         * TODO: Prevent shortcode execution outside PDF rendering context
         * if (!apply_filters('e2pdf_shortcode_enable_e2pdf_format_number', false) && !apply_filters('e2pdf_pdf_render', false)) {
         * return '';
         * }
         */

        $response = $value;
        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_format_number_atts', $atts);

        $dec_point = isset($atts['dec_point']) ? $atts['dec_point'] : '.';
        $thousands_sep = isset($atts['thousands_sep']) ? $atts['thousands_sep'] : '';
        $decimal = isset($atts['decimal']) ? (int) $atts['decimal'] : false;
        $explode = isset($atts['explode']) ? $atts['explode'] : '';
        $implode = isset($atts['implode']) ? $atts['implode'] : '';

        $new_value = [];
        $response = array_filter((array) $response, 'strlen');
        foreach ($response as $v) {
            if ($explode && strpos($v, $explode) !== false) {
                $v = explode($explode, $v);
            }
            foreach ((array) $v as $n) {
                $n = str_replace([' ', ','], ['', '.'], $n);
                $n = preg_replace('/\.(?=.*\.)/', '', $n);
                $n = floatval($n);
                if ($decimal === false) {
                    $num = explode('.', $n);
                    $decimal = isset($num[1]) ? strlen($num[1]) : 0;
                }
                $n = number_format($n, $decimal, $dec_point, $thousands_sep);
                $new_value[] = $n;
            }
            unset($v);
        }

        $response = implode($implode, array_filter((array) $new_value, 'strlen'));
        if (!apply_filters('e2pdf_pdf_fill', false)) {
            $response = $this->sanitize_html($response);
        }
        return apply_filters('e2pdf_model_shortcode_e2pdf_format_number', $response, $atts, $value);
    }

    // e2pdf-format-date
    public function e2pdf_format_date($atts = [], $value = '') {

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /*
         * TODO: Prevent shortcode execution outside PDF rendering context
         * if (!apply_filters('e2pdf_shortcode_enable_e2pdf_format_date', false) && !apply_filters('e2pdf_pdf_render', false)) {
         * return '';
         * }
         */

        $response = trim(strtolower($value));

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_format_date_atts', $atts);

        $format = isset($atts['format']) ? $atts['format'] : get_option('date_format');
        $offset = isset($atts['offset']) ? $atts['offset'] : false;
        $function = isset($atts['function']) ? $atts['function'] : false;
        $timestamp = isset($atts['timestamp']) && $atts['timestamp'] == 'true' ? true : false;

        $timezone = null;
        if (isset($atts['timezone'])) {
            try {
                $timezone = new DateTimeZone($atts['timezone']);
            } catch (Exception $e) {
                $timezone = null;
            }
        }

        $gmt = isset($atts['gmt']) && $atts['gmt'] == 'true' ? true : false;
        $locale = isset($atts['locale']) && $atts['locale'] ? $atts['locale'] : false;

        if (!$response) {
            return '';
        }

        switch ($response) {
            case 'time':
                $response = time();
                break;
            case 'current_time':
                // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
                $response = current_time('timestamp', $gmt);
                break;
            default:
                if (!$timestamp) {
                    $response = strtotime($response);
                }
                break;
        }

        if ($timestamp && !is_numeric($response)) {
            return '';
        }

        if ($locale && function_exists('switch_to_locale') && function_exists('restore_previous_locale')) {
            switch_to_locale($locale);
        }

        if ($offset) {
            if ($function == 'wp_date' && function_exists('wp_date')) {
                $response = wp_date($format, strtotime($offset, $response), $timezone);
            } elseif ($function == 'date_i18n') {
                $response = date_i18n($format, strtotime($offset, $response));
            } else {
                // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
                $response = date($format, strtotime($offset, $response));
            }
        } else {
            if ($function == 'wp_date' && function_exists('wp_date')) {
                $response = wp_date($format, $response, $timezone);
            } elseif ($function == 'date_i18n') {
                $response = date_i18n($format, $response);
            } else {
                // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
                $response = date($format, $response);
            }
        }

        if ($locale && function_exists('switch_to_locale') && function_exists('restore_previous_locale')) {
            restore_previous_locale();
        }

        if (!apply_filters('e2pdf_pdf_fill', false)) {
            $response = $this->sanitize_html($response);
        }
        return apply_filters('e2pdf_model_shortcode_e2pdf_format_date', $response, $atts, $value);
    }

    // e2pdf-format-output
    public function e2pdf_format_output($atts = [], $value = '') {

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /*
         * TODO: Prevent shortcode execution outside PDF rendering context
         * if (!apply_filters('e2pdf_shortcode_enable_e2pdf_format_output', false) && !apply_filters('e2pdf_pdf_render', false)) {
         * return '';
         * }
         */

        $response = $value;
        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_format_output_atts', $atts);

        $explode = isset($atts['explode']) ? $atts['explode'] : false;
        $explode_filter = isset($atts['explode_filter']) ? $atts['explode_filter'] : false;
        $explode_limit = isset($atts['explode_limit']) ? intval($atts['explode_limit']) : false;
        $implode = isset($atts['implode']) ? $atts['implode'] : '';
        $output = isset($atts['output']) ? $atts['output'] : false;
        $filter = isset($atts['filter']) ? $atts['filter'] : false;
        $search = isset($atts['search']) ? explode('|||', $atts['search']) : [];
        $sreplace = isset($atts['sreplace']) ? explode('|||', $atts['sreplace']) : [];
        $ireplace = isset($atts['ireplace']) ? explode('|||', $atts['ireplace']) : [];
        $replace = isset($atts['replace']) ? explode('|||', $atts['replace']) : [];
        $substr = isset($atts['substr']) ? $atts['substr'] : false;
        $sprintf = isset($atts['sprintf']) ? $atts['sprintf'] : false;
        $remove_tags = isset($atts['remove_tags']) ? $atts['remove_tags'] : false;
        $trim = isset($atts['trim']) ? $atts['trim'] : false;
        $rtrim = isset($atts['rtrim']) ? $atts['rtrim'] : false;
        $ltrim = isset($atts['ltrim']) ? $atts['ltrim'] : false;
        $strip_tags_allowed = isset($atts['strip_tags_allowed']) ? $atts['strip_tags_allowed'] : false;
        $pre = isset($atts['pre']) && $atts['pre'] ? $atts['pre'] : '';
        $after = isset($atts['after']) && $atts['after'] ? $atts['after'] : '';
        $strip_shortcodes_tags = isset($atts['strip_shortcodes_tags']) ? explode(',', $atts['strip_shortcodes_tags']) : [];
        $strip_shortcodes_tags_full = isset($atts['strip_shortcodes_tags_full']) ? explode(',', $atts['strip_shortcodes_tags_full']) : [];
        $extract_by_tag = isset($atts['extract_by_tag']) ? explode(',', $atts['extract_by_tag']) : [];
        $extract_by_id = isset($atts['extract_by_id']) ? explode(',', $atts['extract_by_id']) : [];
        $extract_by_class = isset($atts['extract_by_class']) ? explode(',', $atts['extract_by_class']) : [];
        $extract_implode = isset($atts['extract_implode']) ? $atts['extract_implode'] : '';
        $remove_by_tag = isset($atts['remove_by_tag']) ? explode(',', $atts['remove_by_tag']) : [];
        $remove_by_id = isset($atts['remove_by_id']) ? explode(',', $atts['remove_by_id']) : [];
        $remove_by_class = isset($atts['remove_by_class']) ? explode(',', $atts['remove_by_class']) : [];
        $transliterate = isset($atts['transliterate']) ? $atts['transliterate'] : false;
        $truncate = isset($atts['truncate']) ? intval($atts['truncate']) : false;
        $truncate_html = isset($atts['truncate_html']) ? intval($atts['truncate_html']) : false;
        $truncate_ishtml = isset($atts['truncate_ishtml']) && $atts['truncate_ishtml'] == 'true' ? true : false;
        $truncate_breakwords = isset($atts['truncate_breakwords']) && $atts['truncate_breakwords'] == 'true' ? true : false;
        $truncate_readmore = isset($atts['truncate_readmore']) ? $atts['truncate_readmore'] : '...';
        $path = isset($atts['path']) ? $atts['path'] : false;

        /*
         * Backward compatibility
         */
        if (isset($atts['implode_filter']) && $atts['implode_filter'] == 'serialize') {
            $output = '{serialize}';
        }

        if ((!empty($extract_by_id) || !empty($extract_by_class) || !empty($extract_by_tag)) && $value) {
            $extracted = [];
            $dom = new DOMDocument();
            $html = $this->helper->load('convert')->load_html($value, $dom);
            if ($html) {
                $xpath = new DomXPath($dom);

                if (!empty($extract_by_tag)) {
                    foreach ($extract_by_tag as $by_tag) {
                        $query = '//' . $by_tag;
                        $elements = $xpath->query($query);
                        foreach ($elements as $element) {
                            $extracted[] = $this->helper->load('convert')->html_entities_decode($dom->saveHTML($element));
                        }
                    }
                }

                if (!empty($extract_by_id)) {
                    foreach ($extract_by_id as $by_id) {
                        $query = "//*[contains(concat(' ', @id, ' '), ' {$by_id} ')]";
                        $elements = $xpath->query($query);
                        foreach ($elements as $element) {
                            $extracted[] = $this->helper->load('convert')->html_entities_decode($dom->saveHTML($element));
                        }
                    }
                }

                if (!empty($extract_by_class)) {
                    foreach ($extract_by_class as $by_class) {
                        $query = '//*[';
                        $by_sub_classes = explode(' ', $by_class);
                        foreach ($by_sub_classes as $index => $by_sub_class) {
                            if ($index !== 0) {
                                $query .= ' and ';
                            }
                            $query .= "contains(concat(' ', normalize-space(@class), ' '), ' {$by_sub_class} ')";
                        }
                        $query .= ']';
                        $elements = $xpath->query($query);
                        foreach ($elements as $element) {
                            $extracted[] = $this->helper->load('convert')->html_entities_decode($dom->saveHTML($element));
                        }
                    }
                }
            }
            $response = implode($extract_implode, $extracted);
        }

        if ((!empty($remove_by_tag) || !empty($remove_by_id) || !empty($remove_by_class)) && $response) {
            $dom = new DOMDocument();
            $html = $this->helper->load('convert')->load_html($response, $dom);
            if ($html) {
                $xpath = new DomXPath($dom);

                if (!empty($remove_by_tag)) {
                    foreach ($remove_by_tag as $by_tag) {
                        $query = '//' . $by_tag;
                        $elements = $xpath->query($query);
                        foreach ($elements as $element) {
                            $element->parentNode->removeChild($element);
                        }
                    }
                }

                if (!empty($remove_by_id)) {
                    foreach ($remove_by_id as $by_id) {
                        $query = "//*[contains(concat(' ', @id, ' '), ' {$by_id} ')]";
                        $elements = $xpath->query($query);
                        foreach ($elements as $element) {
                            $element->parentNode->removeChild($element);
                        }
                    }
                }

                if (!empty($remove_by_class)) {
                    foreach ($remove_by_class as $by_class) {
                        $query = '//*[';
                        $by_sub_classes = explode(' ', $by_class);
                        foreach ($by_sub_classes as $index => $by_sub_class) {
                            if ($index !== 0) {
                                $query .= ' and ';
                            }
                            $query .= "contains(concat(' ', normalize-space(@class), ' '), ' {$by_sub_class} ')";
                        }
                        $query .= ']';
                        $elements = $xpath->query($query);
                        foreach ($elements as $element) {
                            $element->parentNode->removeChild($element);
                        }
                    }
                }

                $dom2 = new DOMDocument();
                $body = $dom->getElementsByTagName('body')->item(0);
                if ($body) {
                    foreach ($body->childNodes as $child) {
                        $dom2->appendChild($dom2->importNode($child, true));
                    }
                }
                $response = $this->helper->load('convert')->html_entities_decode($dom2->saveHTML());
            }
        }

        $filters = [];
        if ($filter) {
            if (strpos($filter, ',')) {
                $filters = explode(',', $filter);
            } else {
                $filters = array_filter((array) $filter, 'strlen');
            }
        }

        $explode_filters = [];
        if ($explode_filter) {
            if (strpos($explode_filter, ',')) {
                $explode_filters = explode(',', $explode_filter);
            } else {
                $explode_filters = array_filter((array) $explode_filter, 'strlen');
            }
        }

        $response = apply_filters('e2pdf_model_shortcode_e2pdf_format_output_pre_filter', $response, $atts, $value);
        if (!in_array('ireplace', $filters, true) && !in_array('replace', $filters, true) && !in_array('sreplace', $filters, true)) {
            if (!empty($sreplace)) {
                if (!empty($search) && count($search) === count($sreplace)) {
                    $replacements = array_combine($search, $sreplace);
                    if (is_array($replacements)) {
                        $response = strtr($response, $replacements);
                    }
                }
            } elseif (!empty($ireplace)) {
                $response = str_ireplace($search, $ireplace, $response);
            } elseif (!empty($replace)) {
                $response = str_replace($search, $replace, $response);
            }
        }

        if (!in_array('substr', $filters, true)) {
            if ($substr !== false) {
                $substr_start = false;
                $substr_length = false;
                if (strpos($substr, ',')) {
                    $substr_data = explode(',', $substr);
                    if (isset($substr_data[0])) {
                        $substr_start = trim($substr_data[0]);
                    }
                    if (isset($substr_data[1])) {
                        $substr_length = trim($substr_data[1]);
                    }
                } else {
                    $substr_start = trim($substr);
                }

                if ($substr_start !== false && $substr_length !== false) {
                    $response = substr($response, $substr_start, $substr_length);
                } elseif ($substr_start !== false) {
                    $response = substr($response, $substr_start);
                }
            }
        }

        if (!in_array('trim', $filters, true)) {
            if ($trim !== false) {
                $response = trim($response, $trim);
            }
        }

        if (!in_array('rtrim', $filters, true)) {
            if ($rtrim !== false) {
                $response = rtrim($response, $rtrim);
            }
        }

        if (!in_array('ltrim', $filters, true)) {
            if ($ltrim !== false) {
                $response = ltrim($response, $ltrim);
            }
        }

        if (!in_array('sprintf', $filters, true)) {
            if ($sprintf !== false) {
                $response = sprintf($sprintf, $response);
            }
        }

        if (!in_array('transliterate', $filters, true)) {
            if ($transliterate !== false && function_exists('transliterator_transliterate')) {
                $response = transliterator_transliterate(str_replace(['{{', '}}'], ['[', ']'], $transliterate), $response);
            }
        }

        if (!in_array('truncate', $filters, true)) {
            if ($truncate !== false) {
                $max_length = $truncate && intval($truncate) > 0 ? intval($truncate) : 100;
                $response = $this->helper->load('truncate')->truncate($response, $max_length, $truncate_readmore, $truncate_breakwords, $truncate_ishtml);
            }
        }

        if (!in_array('truncate_html', $filters, true)) {
            if ($truncate_html !== false) {
                $max_length = $truncate_html && intval($truncate_html) > 0 ? intval($truncate_html) : 100;
                $response = $this->helper->load('truncate')->truncate($response, $max_length, $truncate_readmore, $truncate_breakwords, true);
            }
        }

        $closed_tags = [
            'style', 'script', 'title', 'head', 'a',
        ];
        if (isset($atts['closed_tags']) && $atts['closed_tags']) {
            $closed_tags = array_merge($closed_tags, explode(',', $atts['closed_tags']));
        }

        $mixed_tags = [];
        if (isset($atts['mixed_tags']) && $atts['mixed_tags']) {
            $closed_tags = array_merge($closed_tags, explode(',', $atts['mixed_tags']));
        }

        $closed_tags = apply_filters('e2pdf_model_shortcode_wp_e2pdf_format_output_closed_tags', $closed_tags);
        $mixed_tags = apply_filters('e2pdf_model_shortcode_wp_e2pdf_format_output_mixed_tags', $mixed_tags);

        if (!in_array('remove_tags', $filters, true)) {
            if ($remove_tags) {
                $remove_tags_list = explode(',', $remove_tags);
                foreach ($remove_tags_list as $remove_tag) {
                    if (in_array($remove_tag, $mixed_tags, true)) {
                        $response = preg_replace('#<' . $remove_tag . '(.*?)>(.*?)</' . $remove_tag . '>#is', '', $response);
                        $response = preg_replace('#<' . $remove_tag . '([^>]+)?\>#is', '', $response);
                    } elseif (in_array($remove_tag, $closed_tags, true)) {
                        $response = preg_replace('#<' . $remove_tag . '(.*?)>(.*?)</' . $remove_tag . '>#is', '', $response);
                    } else {
                        $response = preg_replace('#<' . $remove_tag . '([^>]+)?\>#is', '', $response);
                    }
                }
            }
        }

        $new_value = [];
        if ($path !== false) {
            $response = $this->helper->load('shortcode')->apply_path_attribute($response, $path);
            if (is_array($response) || is_object($response)) {
                if (is_array($response) && !$this->helper->is_multidimensional($response)) {
                    $response = implode(', ', $response);
                } else {
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                    $response = serialize($response);
                }
            }
        }

        $response = array_filter((array) $response, 'strlen');
        foreach ($response as $v) {
            if ($explode && strpos($v, $explode) !== false) {
                if ($explode_limit !== false) {
                    $v = explode($explode, $v, $explode_limit);
                } else {
                    $v = explode($explode, $v);
                }
                if (is_array($v) && !empty($explode_filters)) {
                    foreach ((array) $explode_filters as $sub_explode_filter) {
                        switch ($sub_explode_filter) {
                            case 'array_filter':
                                $v = array_filter($v);
                                break;
                            case 'array_values':
                                $v = array_values($v);
                                break;
                            default:
                                break;
                        }
                    }
                }
            }

            foreach ((array) $v as $n) {
                if (!empty($filters)) {
                    foreach ((array) $filters as $sub_filter) {
                        switch ($sub_filter) {
                            case 'trim':
                                if ($trim !== false) {
                                    $n = trim($n, $trim);
                                } else {
                                    $n = trim($n);
                                }
                                break;
                            case 'rtrim':
                                if ($rtrim !== false) {
                                    $n = rtrim($n, $rtrim);
                                } else {
                                    $n = rtrim($n);
                                }
                                break;
                            case 'ltrim':
                                if ($ltrim !== false) {
                                    $n = ltrim($n, $ltrim);
                                } else {
                                    $n = ltrim($n);
                                }
                                break;
                            case 'strip_tags':
                                if ($strip_tags_allowed !== false) {
                                    // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
                                    $n = strip_tags($n, $strip_tags_allowed);
                                } else {
                                    // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
                                    $n = strip_tags($n);
                                }
                                break;
                            case 'strip_comments':
                                $n = preg_replace('/<!--(.|\s)*?-->/', '', $n);
                                break;
                            case 'strtolower':
                                if (function_exists('mb_strtolower')) {
                                    $n = mb_strtolower($n);
                                } elseif (function_exists('strtolower')) {
                                    $n = strtolower($n);
                                }
                                break;
                            case 'normalize_whitespace':
                                $n = normalize_whitespace($n);
                                break;
                            case 'sanitize_title':
                                $n = sanitize_title($n);
                                break;
                            case 'transliterate':
                                if (function_exists('transliterator_transliterate')) {
                                    if ($transliterate !== false) {
                                        $n = transliterator_transliterate(str_replace(['{{', '}}'], ['[', ']'], $transliterate), $n);
                                    } else {
                                        $n = transliterator_transliterate('Any-Latin; Latin-ASCII; NFD; NFC;', $n);
                                    }
                                }
                                break;
                            case 'ucfirst':
                                if (function_exists('mb_strtoupper') && function_exists('mb_strtolower')) {
                                    $fc = mb_strtoupper(mb_substr($n, 0, 1));
                                    $n = $fc . mb_substr($n, 1);
                                } elseif (function_exists('ucfirst') && function_exists('strtolower')) {
                                    $n = ucfirst($n);
                                }
                                break;
                            case 'ucwords':
                                if (version_compare(PHP_VERSION, '7.3.0', '>=') && function_exists('mb_convert_case')) {
                                    $n = mb_convert_case($n, MB_CASE_TITLE);
                                } elseif (function_exists('ucwords')) {
                                    $n = ucwords($n);
                                }
                                break;
                            case 'strtoupper':
                                if (function_exists('mb_strtoupper')) {
                                    $n = mb_strtoupper($n);
                                } elseif (function_exists('strtoupper')) {
                                    $n = strtoupper($n);
                                }
                                break;
                            case 'lines':
                                $n = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $n);
                                break;
                            case 'nl2br':
                                $n = nl2br($n);
                                break;
                            case 'wpautop':
                                $n = wpautop($n);
                                break;
                            case 'html_entity_decode':
                                $n = html_entity_decode($n);
                                break;
                            case 'urlencode':
                                $n = urlencode($n);  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                                break;
                            case 'urldecode':
                                $n = urldecode($n);
                                break;
                            case 'strip_shortcodes':
                                if (false !== strpos($n, '&#091;')) {
                                    $n = str_replace('&#091;', '[', $n);
                                    if (!empty($strip_shortcodes_tags_full)) {
                                        foreach ($strip_shortcodes_tags_full as $strip_shortcode_tag) {
                                            $n = preg_replace('~(\[(' . $strip_shortcode_tag . ')[^\]]*\].*?\[\/' . $strip_shortcode_tag . '])~s', '', $n);
                                        }
                                    }

                                    if (!empty($strip_shortcodes_tags)) {
                                        foreach ($strip_shortcodes_tags as $strip_shortcode_tag) {
                                            $n = preg_replace(' ~\[/?' . $strip_shortcode_tag . '[^\]]*\]~s', '', $n);
                                        }
                                    }

                                    $n = strip_shortcodes($n);
                                    $n = str_replace('[', '&#091;', $n);
                                }
                                break;
                            case 'strip_shortcodes_tags_full':
                                if (!empty($strip_shortcodes_tags_full) && false !== strpos($n, '&#091;')) {
                                    $n = str_replace('&#091;', '[', $n);
                                    foreach ($strip_shortcodes_tags_full as $strip_shortcode_tag) {
                                        $n = preg_replace('~(\[(' . $strip_shortcode_tag . ')[^\]]*\].*?\[\/' . $strip_shortcode_tag . '])~s', '', $n);
                                    }
                                    $n = str_replace('[', '&#091;', $n);
                                }
                                break;
                            case 'strip_shortcodes_tags':
                                if (!empty($strip_shortcodes_tags) && false !== strpos($n, '&#091;')) {
                                    $n = str_replace('&#091;', '[', $n);
                                    foreach ($strip_shortcodes_tags as $strip_shortcode_tag) {
                                        $n = preg_replace(' ~\[/?' . $strip_shortcode_tag . '[^\]]*\]~s', '', $n);
                                    }
                                    $n = str_replace('[', '&#091;', $n);
                                }
                                break;
                            case 'do_shortcode':
                                if (false !== strpos($n, '&#091;')) {
                                    $n = str_replace('&#091;', '[', $n);
                                    $n = do_shortcode($n);
                                    $n = str_replace('[', '&#091;', $n);
                                }
                                break;
                            case 'htmlentities':
                                $n = htmlentities($n);
                                break;
                            case 'substr':
                                if ($substr !== false) {
                                    $substr_start = false;
                                    $substr_length = false;
                                    if (strpos($substr, ',')) {
                                        $substr_data = explode(',', $substr);
                                        if (isset($substr_data[0])) {
                                            $substr_start = trim($substr_data[0]);
                                        }
                                        if (isset($substr_data[1])) {
                                            $substr_length = trim($substr_data[1]);
                                        }
                                    } else {
                                        $substr_start = trim($substr);
                                    }

                                    if ($substr_start !== false && $substr_length !== false) {
                                        $n = substr($n, $substr_start, $substr_length);
                                    } elseif ($substr_start !== false) {
                                        $n = substr($n, $substr_start);
                                    }
                                }
                                break;
                            case 'sreplace':
                                if (!empty($sreplace)) {
                                    if (!empty($search) && count($search) === count($sreplace)) {
                                        $replacements = array_combine($search, $sreplace);
                                        if (is_array($replacements)) {
                                            $n = strtr($n, $replacements);
                                        }
                                    }
                                }
                                break;
                            case 'ireplace':
                                if (!empty($ireplace)) {
                                    $n = str_ireplace($search, $ireplace, $n);
                                }
                                break;
                            case 'replace':
                                if (!empty($replace)) {
                                    $n = str_replace($search, $replace, $n);
                                }
                                break;
                            case 'remove_tags':
                                if ($remove_tags) {
                                    $remove_tags_list = explode(',', $remove_tags);
                                    foreach ($remove_tags_list as $remove_tag) {
                                        if (in_array($remove_tag, $mixed_tags, true)) {
                                            $n = preg_replace('#<' . $remove_tag . '(.*?)>(.*?)</' . $remove_tag . '>#is', '', $n);
                                            $n = preg_replace('#<' . $remove_tag . '([^>]+)?\>#is', '', $n);
                                        } elseif (in_array($remove_tag, $closed_tags, true)) {
                                            $n = preg_replace('#<' . $remove_tag . '(.*?)>(.*?)</' . $remove_tag . '>#is', '', $n);
                                        } else {
                                            $n = preg_replace('#<' . $remove_tag . '([^>]+)?\>#is', '', $n);
                                        }
                                    }
                                }
                                break;
                            case 'basename':
                                $n = basename($n);
                                break;
                            case 'sprintf':
                                if ($sprintf !== false) {
                                    $n = sprintf($sprintf, $n);
                                }
                                break;
                            case 'truncate':
                                $max_length = $truncate && intval($truncate) > 0 ? intval($truncate) : 100;
                                $n = $this->helper->load('truncate')->truncate($n, $max_length, $truncate_readmore, $truncate_breakwords, $truncate_ishtml);
                                break;
                            case 'truncate_html':
                                $max_length = $truncate_html && intval($truncate_html) > 0 ? intval($truncate_html) : 100;
                                $n = $this->helper->load('truncate')->truncate($n, $max_length, $truncate_readmore, $truncate_breakwords, true);
                                break;
                            default:
                                $n = apply_filters('e2pdf_model_shortcode_e2pdf_format_output_filter', $n, $sub_filter, $atts, $value);
                                break;
                        }
                    }
                }
                $new_value[] = $n;
            }
            unset($v);
        }
        $new_value = apply_filters('e2pdf_model_shortcode_e2pdf_format_output_after_filter', $new_value, $atts, $value);
        if ($output) {
            if ($output == '{count}') {
                $response = count($new_value);
            } elseif ($output == '{serialize}') {
                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                $response = serialize($new_value);
            } else {
                $o_search = [];
                $o_replace = [];
                foreach ($new_value as $key => $val) {
                    $o_search[] = '{' . $key . '}';
                    $o_replace[] = $val;
                }
                $output = str_replace($o_search, $o_replace, $output);
                $response = preg_replace('~(?:{/?)[^/}]+/?}~s', '', $output);
            }
        } else {
            if ($pre || $after) {
                $wrapped = [];
                foreach ($new_value as $key => $val) {
                    $wrapped[] = $pre . $val . $after;
                }
                $response = implode($implode, $wrapped);
            } else {
                $response = implode($implode, $new_value);
            }
        }

        if (!apply_filters('e2pdf_pdf_fill', false)) {
            $response = $this->sanitize_html($response);
        }
        return apply_filters('e2pdf_model_shortcode_e2pdf_format_output', $response, $atts, $value);
    }

    // e2pdf-math
    public function e2pdf_math($atts = [], $value = '') {

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /*
         * TODO: Prevent shortcode execution outside PDF rendering context
         * if (!apply_filters('e2pdf_shortcode_enable_e2pdf_math', false) && !apply_filters('e2pdf_pdf_render', false)) {
         * return '';
         * }
         */

        $thousands_sep_split = isset($atts['thousands_sep_split']) ? $atts['thousands_sep_split'] : ',';
        $dec_point_split = isset($atts['dec_point_split']) ? $atts['dec_point_split'] : '.';
        $default = isset($atts['default']) ? $atts['default'] : '0';
        $value = strtr(
                $value,
                [
                    $thousands_sep_split => '',
                    $dec_point_split => '.',
                    '%%' => '¦',
                ]
        );
        $value = preg_replace('/[^0-9\-\+\*\¦\/\^\(\)\.]/', '', $value);
        $value = $value ? $this->helper->load('math')->evaluate($value) : $default;

        $response = is_numeric($value) ? $this->e2pdf_format_number($atts, $value) : $value;
        if (!apply_filters('e2pdf_pdf_fill', false)) {
            $response = $this->sanitize_html($response);
        }
        return $response;
    }

    // e2pdf-user
    public function e2pdf_user($atts = [], $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_user', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_user_atts', $atts);

        $id = isset($atts['id']) ? $atts['id'] : '0';

        if ($id == 'current') {
            $id = get_current_user_id();
        } elseif ($id == 'dynamic') {
            $id = $value;
        }

        $key = isset($atts['key']) ? $atts['key'] : 'ID';
        $meta = isset($atts['meta']) && $atts['meta'] == 'true' ? true : false;
        $path = isset($atts['path']) ? $atts['path'] : false;
        $explode = isset($atts['explode']) ? $atts['explode'] : false;
        $implode = isset($atts['implode']) ? $atts['implode'] : false;
        $attachment_url = isset($atts['attachment_url']) && $atts['attachment_url'] == 'true' ? true : false;
        $attachment_image_url = isset($atts['attachment_image_url']) && $atts['attachment_image_url'] == 'true' ? true : false;
        $size = isset($atts['size']) ? $atts['size'] : 'thumbnail';
        $has_cap = isset($atts['has_cap']) && $atts['has_cap'] ? $atts['has_cap'] : false;
        $has_role = isset($atts['has_role']) && $atts['has_role'] ? $atts['has_role'] : false;
        $convert = isset($atts['convert']) ? $atts['convert'] : false;

        $response = '';

        $data_fields = apply_filters(
                'e2pdf_model_shortcode_user_data_fields',
                [
                    'ID',
                    'user_description',
                    'user_firstname',
                    'user_lastname',
                    'user_login',
                    'user_nicename',
                    'user_email',
                    'user_url',
                    'user_registered',
                    'user_status',
                    'user_level',
                    'display_name',
                    'spam',
                    'deleted',
                    'locale',
                    'rich_editing',
                    'syntax_highlighting',
                    'use_ssl',
                    'roles',
                ]
        );

        if ($has_cap) {
            $user = get_userdata($id);
            if ($user && $user->has_cap($has_cap)) {
                $user_meta = 'true';
            } else {
                $user_meta = 'false';
            }
        } elseif ($has_role) {
            $user = get_userdata($id);
            if ($user && in_array($has_role, (array) $user->roles, true)) {
                $user_meta = 'true';
            } else {
                $user_meta = 'false';
            }
        } elseif ($key && in_array($key, $data_fields, true) && !$meta) {
            $user = get_userdata($id);
            if (isset($user->$key)) {
                $user_meta = $user->$key;
            } elseif ($key == 'ID') {
                $user_meta = '0';
            } else {
                $user_meta = false;
            }
        } elseif (($key == 'user_avatar' || $key == 'get_avatar_url') && !$meta) {
            $user_meta = get_avatar_url($id, $atts);
        } elseif ($key == 'get_avatar' && !$meta) {
            $size = (int) $size;
            if (!$size) {
                $size = 96;
            }
            $user_meta = get_avatar($id, $size);
        } elseif ($key) {
            $user_meta = get_user_meta($id, $key, true);
        } else {
            $user_meta = false;
        }

        $user_meta = apply_filters('e2pdf_wp_user_meta', $user_meta, $id, $key, $atts);

        if ($user_meta !== false) {

            if (is_object($user_meta)) {
                $user_meta = apply_filters('e2pdf_model_shortcode_e2pdf_user_object', $user_meta, $atts);
            }

            if ($explode && !is_array($user_meta)) {
                $user_meta = explode($explode, $user_meta);
            }

            if (is_array($user_meta)) {
                $user_meta = apply_filters('e2pdf_model_shortcode_e2pdf_user_array', $user_meta, $atts);
            }

            if (is_string($user_meta) && $path !== false && is_object(json_decode($user_meta))) {
                $user_meta = apply_filters('e2pdf_model_shortcode_e2pdf_user_json', json_decode($user_meta, true), $atts);
            }

            if ((is_array($user_meta) || is_object($user_meta)) && $path !== false) {
                $user_meta = $this->helper->load('shortcode')->apply_path_attribute($user_meta, $path);
            }

            if ($attachment_url || $attachment_image_url) {
                if (!is_array($user_meta)) {
                    if (strpos($user_meta, ',') !== false) {
                        $user_meta = explode(',', $user_meta);
                        if ($implode === false) {
                            $implode = ',';
                        }
                    }
                }
                if ($attachment_url) {
                    $user_meta = $this->helper->load('shortcode')->apply_attachment_attribute($user_meta, 'attachment_url', $size);
                } else {
                    $user_meta = $this->helper->load('shortcode')->apply_attachment_attribute($user_meta, 'attachment_image_url', $size);
                }
            }

            if ($convert) {
                $user_meta = $this->helper->load('shortcode')->apply_convert_attribute($convert, $user_meta, $implode);
            }

            if (apply_filters('e2pdf_raw_output', false)) {
                $response = $user_meta;
            } else {
                if (is_array($user_meta)) {
                    if ($implode !== false) {
                        if (!$this->helper->is_multidimensional($user_meta)) {
                            foreach ($user_meta as $user_meta_key => $user_meta_value) {
                                $user_meta[$user_meta_key] = $this->helper->load('translator')->translate($user_meta_value);
                            }
                            $response = implode($implode, $user_meta);
                        } else {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                            $response = serialize($user_meta);
                        }
                    } else {
                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                        $response = serialize($user_meta);
                    }
                } elseif (is_object($user_meta)) {
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                    $response = serialize($user_meta);
                } else {
                    $response = $user_meta;
                }
            }
        }

        if (apply_filters('e2pdf_raw_output', false)) {
            return apply_filters('e2pdf_model_shortcode_e2pdf_user_raw', $response, $atts, $value);
        } else {
            $response = $this->helper->load('translator')->translate($response, 'partial');
            if (!apply_filters('e2pdf_pdf_fill', false)) {
                $response = $this->sanitize_html($response);
            }
            return apply_filters('e2pdf_model_shortcode_e2pdf_user_response', $response, $atts, $value);
        }
    }

    // e2pdf-frm-field-value
    public function e2pdf_frm_field_value($atts, $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_frm_field_value', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $response = '';
        if (class_exists('FrmProEntriesController')) {
            foreach ($atts as $atts_key => $att) {
                if ($att === 'dynamic') {
                    $atts[$atts_key] = $value;
                }
            }
            $response = FrmProEntriesController::get_field_value_shortcode($atts);
        }

        if (!apply_filters('e2pdf_pdf_fill', false)) {
            $response = $this->sanitize_html($response);
        }
        return apply_filters('e2pdf_model_shortcode_e2pdf_frm_field_value', $response, $atts, $value);
    }

    public function e2pdf_translate($atts, $value = null) {
        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_translate', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }
        $atts = shortcode_atts(
                [
                    'context' => '',
                    'domain' => 'default',
                ], $atts, 'e2pdf-translate'
        );
        if ($value) {
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralContext, WordPress.WP.I18n.NonSingularStringLiteralDomain
            return _x($value, $atts['context'], $atts['domain']);
        }
        return '';
    }

    // e2pdf-content
    public function e2pdf_content($atts = [], $value = '') {

        $response = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_content_atts', $atts);

        $id = isset($atts['id']) ? $atts['id'] : false;
        $key = isset($atts['key']) ? $atts['key'] : false;

        if ($id && $key) {
            $wp_post = get_post($id);
            if ($wp_post) {
                if (isset($wp_post->post_content) && $wp_post->post_content) {
                    $content = $this->helper->load('convert')->is_content_key($key, $wp_post->post_content);
                    remove_filter('the_content', 'wpautop');
                    $content = apply_filters('the_content', $content, $id);
                    add_filter('the_content', 'wpautop');
                    $content = str_replace('</p>', "</p>\r\n", $content);
                    $response = $content;
                }
            }
        } elseif ($value) {
            $response = apply_filters('the_content', $value);
        }
        return $response;
    }

    // e2pdf-exclude
    public function e2pdf_exclude($atts = [], $value = '') {
        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_exclude_atts', $atts);
        $attributes = (new Helper_E2pdf_Atts())->load($atts);
        return $attributes->get('apply') ? '' : apply_filters('the_content', $value);
    }

    // e2pdf-wp
    public function e2pdf_wp($atts = [], $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_wp', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $post_meta = false;
        $response = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_wp_atts', $atts);

        $id = isset($atts['id']) ? $atts['id'] : false;
        $key = isset($atts['key']) ? $atts['key'] : false;
        $subkey = isset($atts['subkey']) ? $atts['subkey'] : false;
        $path = isset($atts['path']) ? $atts['path'] : false;
        $names = isset($atts['names']) && $atts['names'] == 'true' ? true : false;
        $explode = isset($atts['explode']) ? $atts['explode'] : false;
        $implode = isset($atts['implode']) ? $atts['implode'] : false;
        $attachment_url = isset($atts['attachment_url']) && $atts['attachment_url'] == 'true' ? true : false;
        $attachment_image_url = isset($atts['attachment_image_url']) && $atts['attachment_image_url'] == 'true' ? true : false;
        $size = isset($atts['size']) ? $atts['size'] : 'thumbnail';
        $meta = isset($atts['meta']) && $atts['meta'] == 'true' ? true : false;
        $terms = isset($atts['terms']) && $atts['terms'] == 'true' ? true : false;
        $output = isset($atts['output']) ? $atts['output'] : false;
        $convert = isset($atts['convert']) ? $atts['convert'] : false;

        /* Backward compatibility */
        if ($convert == 'id_to_term') {
            if ($subkey) {
                $convert = 'term_id_to_' . $subkey;
            } else {
                $convert = 'term_id_to_term';
            }
        }

        if ($id == 'dynamic') {
            $id = $value;
        }

        $data_fields = apply_filters(
                'e2pdf_model_shortcode_wp_data_fields',
                [
                    'id',
                    'post_author',
                    'post_author_id',
                    'post_date',
                    'post_date_gmt',
                    'post_content',
                    'post_title',
                    'post_excerpt',
                    'post_status',
                    'permalink',
                    'post_permalink',
                    'get_permalink',
                    'get_post_permalink',
                    'comment_status',
                    'ping_status',
                    'post_password',
                    'post_name',
                    'to_ping',
                    'pinged',
                    'post_modified',
                    'post_modified_gmt',
                    'post_content_filtered',
                    'post_parent',
                    'guid',
                    'menu_order',
                    'post_type',
                    'post_mime_type',
                    'comment_count',
                    'filter',
                    'post_thumbnail',
                    'get_the_post_thumbnail',
                    'get_the_post_thumbnail_url',
                    'response_hook',
                ]
        );

        if ($id && $key) {
            $wp_post = get_post($id);
            if ($wp_post) {
                if (in_array($key, $data_fields, true) && !$meta && !$terms) {
                    if ($key == 'post_author') {
                        if (isset($wp_post->post_author) && $wp_post->post_author) {
                            if (isset($atts['subkey'])) {
                                $atts['id'] = $wp_post->post_author;
                                $atts['key'] = $subkey;
                                $post_meta = $this->e2pdf_user($atts);
                            } else {
                                $post_meta = get_userdata($wp_post->post_author)->user_nicename;
                            }
                        }
                    } elseif ($key == 'post_author_id') {
                        $post_meta = isset($wp_post->post_author) && $wp_post->post_author ? $wp_post->post_author : '0';
                    } elseif ($key == 'id' && isset($wp_post->ID)) {
                        $post_meta = $wp_post->ID;
                    } elseif (($key == 'post_thumbnail' || $key == 'get_the_post_thumbnail_url') && isset($wp_post->ID)) {
                        $post_meta = get_the_post_thumbnail_url($wp_post->ID, $size);
                    } elseif ($key == 'get_the_post_thumbnail' && isset($wp_post->ID)) {
                        $post_meta = get_the_post_thumbnail($wp_post->ID, $size);
                    } elseif ($key == 'post_content' && isset($wp_post->post_content)) {
                        $content = $wp_post->post_content;
                        if (false !== strpos($content, '[')) {
                            $shortcode_tags = [
                                'e2pdf-exclude',
                                'e2pdf-save',
                                'e2pdf-view',
                            ];
                            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
                            $tagnames = array_intersect($shortcode_tags, $matches[1]);
                            if (!empty($tagnames)) {
                                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $content, $shortcodes);
                                foreach ($shortcodes[0] as $key => $shortcode_value) {
                                    $content = str_replace($shortcode_value, '', $content);
                                }
                            }
                        }
                        if ($output) {
                            global $post;
                            $tmp_post = $post;
                            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                            $post = $wp_post;
                            if ($output == 'backend') {
                                if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin')) {
                                    \Elementor\Plugin::instance()->frontend->remove_content_filter();
                                }
                            } elseif ($output == 'frontend') {
                                if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin')) {
                                    \Elementor\Plugin::instance()->frontend->add_content_filter();
                                }
                            }
                        }

                        if (defined('ET_BUILDER_DIR') && 'on' === get_post_meta($id, '_et_pb_use_builder', true) && function_exists('et_builder_init_global_settings') && function_exists('et_builder_add_main_elements')) {
                            if (file_exists(ET_BUILDER_DIR . 'class-et-builder-value.php') && !class_exists('ET_Builder_Value')) {
                                require_once ET_BUILDER_DIR . 'class-et-builder-value.php';
                            }
                            require_once ET_BUILDER_DIR . 'class-et-builder-element.php';
                            require_once ET_BUILDER_DIR . 'functions.php';
                            require_once ET_BUILDER_DIR . 'ab-testing.php';
                            require_once ET_BUILDER_DIR . 'class-et-global-settings.php';
                            et_builder_add_main_elements();
                        }

                        if (class_exists('WPBMap') && method_exists('WPBMap', 'addAllMappedShortcodes')) {
                            WPBMap::addAllMappedShortcodes();
                        }

                        $content = apply_filters('the_content', $content, $id);
                        $content = str_replace('</p>', "</p>\r\n", $content);
                        $post_meta = $content;

                        if ($output) {
                            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                            $post = $tmp_post;
                        }
                    } elseif ($key == 'get_permalink' || $key == 'permalink') {
                        $leavename = isset($atts['leavename']) && $atts['leavename'] == 'true' ? true : false;
                        $post_meta = get_permalink($id, $leavename);
                        $post_meta = $this->helper->load('translator')->translate_url($post_meta);
                    } elseif ($key == 'get_post_permalink' || $key == 'post_permalink') {
                        $leavename = isset($atts['leavename']) && $atts['leavename'] == 'true' ? true : false;
                        $post_meta = get_post_permalink($id, $leavename);
                        $post_meta = $this->helper->load('translator')->translate_url($post_meta);
                    } elseif ($key == 'response_hook') {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wp_response_hook', '', $id, $atts, $wp_post);
                    } elseif (isset($wp_post->$key)) {
                        $post_meta = $wp_post->$key;
                    }
                } elseif ($terms && $names) {
                    $post_terms = wp_get_post_terms($id, $key, ['fields' => 'names']);
                    if (!is_wp_error($post_terms) && is_array($post_terms)) {
                        foreach ($post_terms as $post_term_key => $post_terms_value) {
                            $post_terms[$post_term_key] = $this->helper->load('translator')->translate($post_terms_value);
                        }
                        if ($implode === false) {
                            $implode = ', ';
                        }
                        $post_meta = implode($implode, $post_terms);
                    }
                } elseif ($terms) {
                    $post_terms = wp_get_post_terms($id, $key);
                    if (!is_wp_error($post_terms)) {
                        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
                        $post_meta = json_decode(json_encode($post_terms), true);
                    }
                } else {
                    $post_meta = get_post_meta($id, $key, true);
                }

                $post_meta = apply_filters('e2pdf_wp_post_meta', $post_meta, $id, $key, $atts);

                if ($post_meta !== false) {

                    if (is_object($post_meta)) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wp_object', $post_meta, $atts);
                    }

                    if ($explode && !is_array($post_meta)) {
                        $post_meta = explode($explode, $post_meta);
                    }

                    if (is_array($post_meta)) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wp_array', $post_meta, $atts);
                    }

                    if (is_string($post_meta) && $path !== false && is_object(json_decode($post_meta))) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wp_json', json_decode($post_meta, true), $atts);
                    }

                    if ((is_array($post_meta) || is_object($post_meta)) && $path !== false) {
                        $post_meta = $this->helper->load('shortcode')->apply_path_attribute($post_meta, $path);
                    }

                    if ($attachment_url || $attachment_image_url) {
                        if (!is_array($post_meta)) {
                            if (strpos($post_meta, ',') !== false) {
                                $post_meta = explode(',', $post_meta);
                                if ($implode === false) {
                                    $implode = ',';
                                }
                            }
                        }
                        if ($attachment_url) {
                            $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_url', $size);
                        } else {
                            $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_image_url', $size);
                        }
                    }

                    if ($convert) {
                        $post_meta = $this->helper->load('shortcode')->apply_convert_attribute($convert, $post_meta, $implode);
                    }

                    if (apply_filters('e2pdf_raw_output', false)) {
                        $response = $post_meta;
                    } else {
                        if (is_array($post_meta)) {
                            if ($implode !== false) {
                                if (!$this->helper->is_multidimensional($post_meta)) {
                                    foreach ($post_meta as $post_meta_key => $post_meta_value) {
                                        $post_meta[$post_meta_key] = $this->helper->load('translator')->translate($post_meta_value, 'default', $id);
                                    }
                                    $response = implode($implode, $post_meta);
                                } else {
                                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                                    $response = serialize($post_meta);
                                }
                            } else {
                                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                                $response = serialize($post_meta);
                            }
                        } elseif (is_object($post_meta)) {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                            $response = serialize($post_meta);
                        } else {
                            $response = $post_meta;
                        }
                    }
                }
            }
        }

        if (apply_filters('e2pdf_raw_output', false)) {
            return apply_filters('e2pdf_model_shortcode_e2pdf_wp_raw', $response, $atts, $value);
        } else {
            $response = $this->helper->load('translator')->translate($response, 'partial', $id);
            if (!apply_filters('e2pdf_pdf_fill', false)) {
                $response = $this->sanitize_html($response);
            }
            return apply_filters('e2pdf_model_shortcode_e2pdf_wp_response', $response, $atts, $value);
        }
    }

    // e2pdf-wp-term
    public function e2pdf_wp_term($atts = [], $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_wp_term', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $post_meta = false;
        $response = '';

        $id = isset($atts['id']) ? $atts['id'] : false;
        $key = isset($atts['key']) ? $atts['key'] : false;
        $meta = isset($atts['meta']) && $atts['meta'] == 'true' ? true : false;
        $implode = isset($atts['implode']) ? $atts['implode'] : false;
        $explode = isset($atts['explode']) ? $atts['explode'] : false;
        $attachment_url = isset($atts['attachment_url']) && $atts['attachment_url'] == 'true' ? true : false;
        $attachment_image_url = isset($atts['attachment_image_url']) && $atts['attachment_image_url'] == 'true' ? true : false;
        $size = isset($atts['size']) ? $atts['size'] : 'thumbnail';
        $path = isset($atts['path']) ? $atts['path'] : false;

        if ($id == 'dynamic') {
            $id = $value;
        }

        if ($id && $key) {
            $wp_post = get_term($id);
            if ($wp_post && !is_wp_error($wp_post)) {
                if (!$meta) {
                    if (isset($wp_post->$key)) {
                        $post_meta = $wp_post->$key;
                    }
                } else {
                    $post_meta = get_term_meta($id, $key, true);
                }

                $post_meta = apply_filters('e2pdf_wp_term_meta', $post_meta, $id, $key, $atts);

                if ($post_meta !== false) {

                    if (is_object($post_meta)) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wp_term_object', $post_meta, $atts);
                    }

                    if ($explode && !is_array($post_meta)) {
                        $post_meta = explode($explode, $post_meta);
                    }

                    if (is_array($post_meta)) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wp_term_array', $post_meta, $atts);
                    }

                    if (is_string($post_meta) && $path !== false && is_object(json_decode($post_meta))) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wp_term_json', json_decode($post_meta, true), $atts);
                    }

                    if ((is_array($post_meta) || is_object($post_meta)) && $path !== false) {
                        $post_meta = $this->helper->load('shortcode')->apply_path_attribute($post_meta, $path);
                    }

                    if ($attachment_url || $attachment_image_url) {
                        if (!is_array($post_meta)) {
                            if (strpos($post_meta, ',') !== false) {
                                $post_meta = explode(',', $post_meta);
                                if ($implode === false) {
                                    $implode = ',';
                                }
                            }
                        }
                        if ($attachment_url) {
                            $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_url', $size);
                        } else {
                            $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_image_url', $size);
                        }
                    }

                    if (apply_filters('e2pdf_raw_output', false)) {
                        $response = $post_meta;
                    } else {
                        if (is_array($post_meta)) {
                            if ($implode !== false) {
                                if (!$this->helper->is_multidimensional($post_meta)) {
                                    foreach ($post_meta as $post_meta_key => $post_meta_value) {
                                        $post_meta[$post_meta_key] = $this->helper->load('translator')->translate($post_meta_value);
                                    }
                                    $response = implode($implode, $post_meta);
                                } else {
                                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                                    $response = serialize($post_meta);
                                }
                            } else {
                                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                                $response = serialize($post_meta);
                            }
                        } elseif (is_object($post_meta)) {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                            $response = serialize($post_meta);
                        } else {
                            $response = $post_meta;
                        }
                    }
                }
            }
        }

        if (apply_filters('e2pdf_raw_output', false)) {
            return apply_filters('e2pdf_model_shortcode_e2pdf_wp_term_raw', $response, $atts, $value);
        } else {
            $response = $this->helper->load('translator')->translate($response, 'partial');
            if (!apply_filters('e2pdf_pdf_fill', false)) {
                $response = $this->sanitize_html($response);
            }
            return apply_filters('e2pdf_model_shortcode_e2pdf_wp_term_response', $response, $atts, $value);
        }
    }

    // e2pdf-wp-posts
    public function e2pdf_wp_posts($atts = [], $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_wp_posts', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $post_meta = false;
        $response = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_posts_atts', $atts);

        $path = isset($atts['path']) ? $atts['path'] : false;
        $explode = isset($atts['explode']) ? $atts['explode'] : false;
        $implode = isset($atts['implode']) ? $atts['implode'] : false;
        $attachment_url = isset($atts['attachment_url']) && $atts['attachment_url'] == 'true' ? true : false;
        $attachment_image_url = isset($atts['attachment_image_url']) && $atts['attachment_image_url'] == 'true' ? true : false;
        $size = isset($atts['size']) ? $atts['size'] : 'thumbnail';

        $args = [];

        if (isset($atts['fields'])) {
            $args['fields'] = $atts['fields'];
        }

        if (isset($atts['numberposts'])) {
            $args['numberposts'] = $atts['numberposts'];
        }

        if (isset($atts['posts_per_page'])) {
            $args['posts_per_page'] = $atts['posts_per_page'];
        }

        if (isset($atts['offset'])) {
            $args['offset'] = $atts['offset'];
        }

        if (isset($atts['category'])) {
            $category = $atts['category'] == 'dynamic' ? $value : $atts['category'];
            $args['category'] = $category;
        }

        if (isset($atts['category_name'])) {
            $args['category_name'] = $atts['category_name'];
        }

        if (isset($atts['tag'])) {
            $args['tag'] = $atts['tag'];
        }

        if (isset($atts['include'])) {
            $args['include'] = explode(',', $atts['include']);
        }

        if (isset($atts['exclude'])) {
            $args['exclude'] = explode(',', $atts['exclude']);
        }

        if (isset($atts['meta_key'])) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            $args['meta_key'] = $atts['meta_key'];
        }

        if (isset($atts['meta_value'])) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            $args['meta_value'] = $atts['meta_value'];
        }

        if (isset($atts['post_type'])) {
            $args['post_type'] = explode(',', $atts['post_type']);
        }

        if (isset($atts['post_mime_type'])) {
            $args['post_mime_type'] = explode(',', $atts['post_mime_type']);
        }

        if (isset($atts['post_status'])) {
            $args['post_status'] = $atts['post_status'];
        }

        if (isset($atts['post_parent'])) {
            $args['post_parent'] = $atts['post_parent'];
        }

        if (isset($atts['nopaging'])) {
            $args['nopaging'] = $atts['nopaging'] === 'true' ? true : false;
        }

        if (isset($atts['orderby'])) {
            $args['orderby'] = $atts['orderby'];
        }

        if (isset($atts['order'])) {
            $args['order'] = $atts['order'];
        }

        if (isset($atts['meta_value_list'])) {
            $args['meta_value_list'] = explode(',', $atts['meta_value_list']);
        }

        if (isset($atts['suppress_filters'])) {
            $args['suppress_filters'] = $args['suppress_filters'] === 'true' ? true : false;
        }

        $tax_query = [];
        foreach ($atts as $att_key => $att_value) {
            if (substr($att_key, 0, 9) === 'tax_query') {
                $tax = explode('|||', $att_value, 5);
                if (count($tax) >= 3) {
                    $tax_query[] = [
                        'taxonomy' => $tax[0],
                        'field' => $tax[1],
                        'terms' => explode(',', $tax[2]),
                        'operator' => isset($tax[3]) ? $tax[3] : 'IN',
                        'include_children' => isset($tax[4]) && $tax[4] == 'false' ? false : true,
                    ];
                }
            }
        }
        if (!empty($tax_query)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $args['tax_query'] = $tax_query;
        }

        $post_meta = get_posts(
                apply_filters('e2pdf_model_shortcode_e2pdf_posts_args', $args, $atts, $value)
        );

        if ($post_meta !== false) {

            if (is_object($post_meta)) {
                $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_posts_object', $post_meta, $atts);
            }

            if ($explode && !is_array($post_meta)) {
                $post_meta = explode($explode, $post_meta);
            }

            if (is_array($post_meta)) {
                $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_posts_array', $post_meta, $atts);
            }

            if (is_string($post_meta) && $path !== false && is_object(json_decode($post_meta))) {
                $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_posts_json', json_decode($post_meta, true), $atts);
            }

            if ((is_array($post_meta) || is_object($post_meta)) && $path !== false) {
                $post_meta = $this->helper->load('shortcode')->apply_path_attribute($post_meta, $path);
            }

            if ($attachment_url || $attachment_image_url) {
                if (!is_array($post_meta)) {
                    if (strpos($post_meta, ',') !== false) {
                        $post_meta = explode(',', $post_meta);
                        if ($implode === false) {
                            $implode = ',';
                        }
                    }
                }
                if ($attachment_url) {
                    $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_url', $size);
                } else {
                    $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_image_url', $size);
                }
            }

            if (apply_filters('e2pdf_raw_output', false)) {
                $response = $post_meta;
            } else {
                if (is_array($post_meta)) {
                    if ($implode !== false) {
                        if (!$this->helper->is_multidimensional($post_meta)) {
                            foreach ($post_meta as $post_meta_key => $post_meta_value) {
                                $post_meta[$post_meta_key] = $this->helper->load('translator')->translate($post_meta_value);
                            }
                            $response = implode($implode, $post_meta);
                        } else {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                            $response = serialize($post_meta);
                        }
                    } else {
                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                        $response = serialize($post_meta);
                    }
                } elseif (is_object($post_meta)) {
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                    $response = serialize($post_meta);
                } else {
                    $response = $post_meta;
                }
            }
        }

        if (apply_filters('e2pdf_raw_output', false)) {
            return apply_filters('e2pdf_model_shortcode_e2pdf_posts_raw', $response, $atts, $value);
        } else {
            $response = $this->helper->load('translator')->translate($response, 'partial');
            if (!apply_filters('e2pdf_pdf_fill', false)) {
                $response = $this->sanitize_html($response);
            }
            return apply_filters('e2pdf_model_shortcode_e2pdf_posts_response', $response, $atts, $value);
        }
    }

    // e2pdf-wp-users
    public function e2pdf_wp_users($atts = [], $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_wp_users', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $post_meta = false;
        $response = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_users_atts', $atts);

        $path = isset($atts['path']) ? $atts['path'] : false;
        $explode = isset($atts['explode']) ? $atts['explode'] : false;
        $implode = isset($atts['implode']) ? $atts['implode'] : false;
        $attachment_url = isset($atts['attachment_url']) && $atts['attachment_url'] == 'true' ? true : false;
        $attachment_image_url = isset($atts['attachment_image_url']) && $atts['attachment_image_url'] == 'true' ? true : false;
        $size = isset($atts['size']) ? $atts['size'] : 'thumbnail';

        $args = [];

        if (isset($atts['fields'])) {
            $args['fields'] = $atts['fields'];
        }

        if (isset($atts['search'])) {
            $args['search'] = $atts['search'];
        }

        $post_meta = get_users(
                apply_filters('e2pdf_model_shortcode_e2pdf_users_args', $args, $atts, $value)
        );

        if ($post_meta !== false) {

            if (is_object($post_meta)) {
                $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_users_object', $post_meta, $atts);
            }

            if ($explode && !is_array($post_meta)) {
                $post_meta = explode($explode, $post_meta);
            }

            if (is_array($post_meta)) {
                $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_users_array', $post_meta, $atts);
            }

            if (is_string($post_meta) && $path !== false && is_object(json_decode($post_meta))) {
                $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_users_json', json_decode($post_meta, true), $atts);
            }

            if ((is_array($post_meta) || is_object($post_meta)) && $path !== false) {
                $post_meta = $this->helper->load('shortcode')->apply_path_attribute($post_meta, $path);
            }

            if ($attachment_url || $attachment_image_url) {
                if (!is_array($post_meta)) {
                    if (strpos($post_meta, ',') !== false) {
                        $post_meta = explode(',', $post_meta);
                        if ($implode === false) {
                            $implode = ',';
                        }
                    }
                }
                if ($attachment_url) {
                    $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_url', $size);
                } else {
                    $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_image_url', $size);
                }
            }

            if (apply_filters('e2pdf_raw_output', false)) {
                $response = $post_meta;
            } else {
                if (is_array($post_meta)) {
                    if ($implode !== false) {
                        if (!$this->helper->is_multidimensional($post_meta)) {
                            foreach ($post_meta as $post_meta_key => $post_meta_value) {
                                $post_meta[$post_meta_key] = $this->helper->load('translator')->translate($post_meta_value);
                            }
                            $response = implode($implode, $post_meta);
                        } else {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                            $response = serialize($post_meta);
                        }
                    } else {
                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                        $response = serialize($post_meta);
                    }
                } elseif (is_object($post_meta)) {
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                    $response = serialize($post_meta);
                } else {
                    $response = $post_meta;
                }
            }
        }

        if (apply_filters('e2pdf_raw_output', false)) {
            return apply_filters('e2pdf_model_shortcode_e2pdf_users_raw', $response, $atts, $value);
        } else {
            $response = $this->helper->load('translator')->translate($response, 'partial');
            if (!apply_filters('e2pdf_pdf_fill', false)) {
                $response = $this->sanitize_html($response);
            }
            return apply_filters('e2pdf_model_shortcode_e2pdf_users_response', $response, $atts, $value);
        }
    }

    // e2pdf-wc-product
    public function e2pdf_wc_product($atts = [], $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_wc_product', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $post_meta = false;
        $response = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_atts', $atts);

        $id = isset($atts['id']) ? $atts['id'] : false;
        $index = isset($atts['index']) ? $atts['index'] : false;
        $wc_product_item_id = isset($atts['wc_product_item_id']) ? $atts['wc_product_item_id'] : false;
        $key = isset($atts['key']) ? $atts['key'] : false;
        $path = isset($atts['path']) ? $atts['path'] : false;
        $names = isset($atts['names']) && $atts['names'] == 'true' ? true : false;
        $explode = isset($atts['explode']) ? $atts['explode'] : false;
        $implode = isset($atts['implode']) ? $atts['implode'] : false;
        $attachment_url = isset($atts['attachment_url']) && $atts['attachment_url'] == 'true' ? true : false;
        $attachment_image_url = isset($atts['attachment_image_url']) && $atts['attachment_image_url'] == 'true' ? true : false;
        $size = isset($atts['size']) ? $atts['size'] : 'thumbnail';
        $meta = isset($atts['meta']) && $atts['meta'] == 'true' ? true : false;
        $output = isset($atts['output']) ? $atts['output'] : false;
        $terms = isset($atts['terms']) && $atts['terms'] == 'true' ? true : false;
        $parent = isset($atts['parent']) && $atts['parent'] == 'true' ? true : false;
        $wc_order_id = isset($atts['wc_order_id']) ? $atts['wc_order_id'] : false;
        $download_index = isset($atts['download_index']) ? $atts['download_index'] : false;
        $attribute = isset($atts['attribute']) ? $atts['attribute'] : false;
        $order = isset($atts['order']) && $atts['order'] == 'true' ? true : false;
        $detached = isset($atts['detached']) && $atts['detached'] == 'true' ? true : false;
        $order_item_meta = isset($atts['order_item_meta']) && $atts['order_item_meta'] == 'true' ? true : false;
        $wc_filter = isset($atts['wc_filter']) && $atts['wc_filter'] == 'true' ? true : false;
        $convert = isset($atts['convert']) ? $atts['convert'] : false;
        $wc_price = isset($atts['wc_price']) && $atts['wc_price'] == 'true' ? true : false;
        $wc_price_raw = isset($atts['wc_price_raw']) && $atts['wc_price_raw'] == 'true' ? true : false;

        if ($id == 'dynamic') {
            $id = $value;
        }

        /* Backward compatibility 1.14.07 */
        if ($attribute === 'true' && $key != 'get_attribute') {
            $attribute = $key;
            $key = 'get_attribute';
        }

        /* Backward compatibility 1.24.07 */
        if ($key == 'get_category_ids' && $order) {
            $order = false;
        }

        $data_fields = apply_filters(
                'e2pdf_model_shortcode_wc_product_data_fields',
                [
                    'id',
                    'post_author',
                    'post_author_id',
                    'post_date',
                    'post_date_gmt',
                    'post_content',
                    'post_title',
                    'post_excerpt',
                    'post_status',
                    'permalink',
                    'post_permalink',
                    'get_post_permalink',
                    'comment_status',
                    'ping_status',
                    'post_password',
                    'post_name',
                    'to_ping',
                    'pinged',
                    'post_modified',
                    'post_modified_gmt',
                    'post_content_filtered',
                    'post_parent',
                    'guid',
                    'menu_order',
                    'post_type',
                    'post_mime_type',
                    'comment_count',
                    'filter',
                    'post_thumbnail',
                    'get_the_post_thumbnail',
                    'get_the_post_thumbnail_url',
                    'response_hook',
                ]
        );

        $product_fields = apply_filters(
                'e2pdf_model_shortcode_wc_product_product_fields',
                [
                    'get_name',
                    'get_type',
                    'get_slug',
                    'get_date_created',
                    'get_date_modified',
                    'get_status',
                    'get_featured',
                    'get_catalog_visibility',
                    'get_description',
                    'get_short_description',
                    'get_sku',
                    'get_price',
                    'get_regular_price',
                    'get_sale_price',
                    'get_date_on_sale_from',
                    'get_date_on_sale_to',
                    'get_total_sales',
                    'get_tax_status',
                    'get_tax_class',
                    'get_manage_stock',
                    'get_stock_quantity',
                    'get_stock_status',
                    'get_backorders',
                    'get_low_stock_amount',
                    'get_sold_individually',
                    'get_weight',
                    'get_length',
                    'get_width',
                    'get_height',
                    'get_dimensions',
                    'get_upsell_ids',
                    'get_cross_sell_ids',
                    'get_parent_id',
                    'get_reviews_allowed',
                    'get_purchase_note',
                    'get_attributes',
                    'get_variation_attributes',
                    'get_default_attributes',
                    'get_menu_order',
                    'get_post_password',
                    'get_category_ids',
                    'get_tag_ids',
                    'get_virtual',
                    'get_gallery_image_ids',
                    'get_shipping_class_id',
                    'get_downloads',
                    'get_download_expiry',
                    'get_downloadable',
                    'get_download_limit',
                    'get_image_id',
                    'get_rating_counts',
                    'get_average_rating',
                    'get_review_count',
                    'get_title',
                    'get_permalink',
                    'get_children',
                    'get_stock_managed_by_id',
                    'get_price_html',
                    'get_formatted_name',
                    'get_min_purchase_quantity',
                    'get_max_purchase_quantity',
                    'get_image',
                    'get_shipping_class',
                    'get_attribute',
                    'get_variation_attribute',
                    'get_rating_count',
                    'get_file',
                    'get_file_download_path',
                    'get_price_suffix',
                    'get_availability',
                ]
        );

        $product_item_fields = apply_filters(
                'e2pdf_model_shortcode_wc_product_item_fields',
                [
                    'get_order_id',
                    'get_name',
                    'get_type',
                    'get_quantity',
                    'get_image',
                    'get_tax_status',
                    'get_tax_class',
                    'get_formatted_meta_data',
                    'get_formatted_cart_item_data', // Only cart
                    'get_product_id',
                    'get_variation_id',
                    'get_subtotal',
                    'get_subtotal_tax',
                    'get_total',
                    'get_total_tax',
                    'get_taxes',
                    'get_item_download_url',
                    'get_item_downloads',
                    'get_tax_status',
                    'get_product_price',
                    'get_order_item_id',
                    'item_response_hook',
                    'item_cart_response_hook',
                    'cart_response_hook',
                ]
        );

        $product_order_fields = apply_filters(
                'e2pdf_model_shortcode_wc_product_order_item_fields',
                [
                    'get_item_subtotal',
                    'get_item_total',
                    'get_item_tax',
                    'get_line_total',
                    'get_line_tax',
                    'get_formatted_line_subtotal',
                    'order_response_hook',
                ]
        );

        $wc_product = false;
        $wc_order = false;
        $wc_order_items = [];
        $wc_order_item_id = false;
        $wc_order_item = false;
        $wc_order_item_index = 0;

        if ($wc_order_id && ($index !== false || $id !== false) && !$detached) {
            if ($wc_order_id == 'cart') {
                if (function_exists('WC') && isset(WC()->cart) && WC()->cart && is_object(WC()->cart)) {
                    WC()->cart->calculate_totals();
                    $wc_order = WC()->cart;
                    $wc_order_items = WC()->cart->get_cart();
                }
            } else {
                $wc_order = wc_get_order($wc_order_id);
                if ($wc_order) {
                    $wc_order_items = $wc_order->get_items();
                }
            }

            foreach ($wc_order_items as $item_id => $item) {
                if ($wc_order_id == 'cart') {
                    $item_product = apply_filters('woocommerce_cart_item_product', $item['data'], $item, $item_id);
                    if ($item['variation_id']) {
                        $product_id = $item['variation_id'];
                    } else {
                        $product_id = apply_filters('woocommerce_cart_item_product_id', $item['product_id'], $item, $item_id);
                    }
                } else {
                    $item_product = $item->get_product();
                    if (!$item_product) {
                        $item_product = $item;
                    }
                    if ($item->get_variation_id()) {
                        $product_id = $item->get_variation_id();
                    } else {
                        $product_id = $item->get_product_id();
                    }
                }
                if ($wc_order_id != 'cart' || ($item_product && $item_product->exists() && $item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $item, $item_id))) {
                    if (
                            ($id === false && $index !== false && $wc_order_item_index == $index) ||
                            ($id !== false && $index !== false && $product_id == $id && $wc_order_item_index == $index) ||
                            ($id !== false && $index === false && $wc_product_item_id === false && $product_id == $id) ||
                            ($id !== false && $index === false && $wc_product_item_id !== false && $product_id == $id && $wc_product_item_id == $item_id)
                    ) {
                        $id = $product_id;
                        $wc_order_item = $item;
                        $wc_order_item_id = $item_id;
                        $wc_product = $item_product;
                        break;
                    }
                    if ($id !== false && $index !== false) {
                        if ($product_id == $id) {
                            $wc_order_item_index++;
                        }
                    } else {
                        $wc_order_item_index++;
                    }
                }
            }
        }

        if ($key && (!$wc_order_id || ($wc_order_id && ($wc_product || $detached)))) {
            if ($id) {
                $wp_post = get_post($id);
                if ($parent) {
                    $variation = wc_get_product($id);
                    if (get_post_type($id) == 'product_variation' && $variation) {
                        $id = $variation->get_parent_id();
                        $wp_post = get_post($variation->get_parent_id());
                        $wc_product = wc_get_product($id);
                    } else {
                        $wc_product = false;
                        $wp_post = false;
                    }
                } elseif (!$wc_product) {
                    $wc_product = wc_get_product($id);
                }
            } else {
                $wp_post = false;
            }

            if (in_array($key, $data_fields, true) && !$meta && !$terms && !$order_item_meta && !$order) {
                if ($wp_post) {
                    if ($key == 'post_author') {
                        $post_meta = isset($wp_post->post_author) && $wp_post->post_author ? get_userdata($wp_post->post_author)->user_nicename : '';
                    } elseif ($key == 'post_author_id') {
                        $post_meta = isset($wp_post->post_author) && $wp_post->post_author ? $wp_post->post_author : '0';
                    } elseif ($key == 'id' && isset($wp_post->ID)) {
                        $post_meta = $wp_post->ID;
                    } elseif (($key == 'post_thumbnail' || $key == 'get_the_post_thumbnail_url') && isset($wp_post->ID)) {
                        $post_meta = get_the_post_thumbnail_url($wp_post->ID, $size);
                    } elseif ($key == 'get_the_post_thumbnail' && isset($wp_post->ID)) {
                        $post_meta = get_the_post_thumbnail($wp_post->ID, $size);
                    } elseif ($key == 'post_content' && isset($wp_post->post_content)) {
                        $content = $wp_post->post_content;
                        if (false !== strpos($content, '[')) {
                            $shortcode_tags = [
                                'e2pdf-exclude',
                                'e2pdf-save',
                                'e2pdf-view',
                            ];
                            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
                            $tagnames = array_intersect($shortcode_tags, $matches[1]);
                            if (!empty($tagnames)) {
                                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $content, $shortcodes);
                                foreach ($shortcodes[0] as $key => $shortcode_value) {
                                    $content = str_replace($shortcode_value, '', $content);
                                }
                            }
                        }

                        if ($output) {
                            global $post;
                            $tmp_post = $post;
                            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                            $post = $wp_post;
                            if ($output == 'backend') {
                                if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin')) {
                                    \Elementor\Plugin::instance()->frontend->remove_content_filter();
                                }
                            } elseif ($output == 'frontend') {
                                if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin')) {
                                    \Elementor\Plugin::instance()->frontend->add_content_filter();
                                }
                            }
                        }

                        if (defined('ET_BUILDER_DIR') && 'on' === get_post_meta($id, '_et_pb_use_builder', true) && function_exists('et_builder_init_global_settings') && function_exists('et_builder_add_main_elements')) {
                            require_once ET_BUILDER_DIR . 'class-et-builder-element.php';
                            require_once ET_BUILDER_DIR . 'functions.php';
                            require_once ET_BUILDER_DIR . 'ab-testing.php';
                            require_once ET_BUILDER_DIR . 'class-et-global-settings.php';
                            et_builder_add_main_elements();
                        }

                        if (class_exists('WPBMap') && method_exists('WPBMap', 'addAllMappedShortcodes')) {
                            WPBMap::addAllMappedShortcodes();
                        }

                        $content = apply_filters('the_content', $content, $id);
                        $content = str_replace('</p>', "</p>\r\n", $content);
                        $post_meta = $content;

                        if ($output) {
                            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                            $post = $tmp_post;
                        }
                    } elseif ($key == 'permalink') {
                        $leavename = isset($atts['leavename']) && $atts['leavename'] == 'true' ? true : false;
                        $post_meta = get_permalink($id, $leavename);
                        $post_meta = $this->helper->load('translator')->translate_url($post_meta);
                    } elseif ($key == 'get_post_permalink' || $key == 'post_permalink') {
                        $leavename = isset($atts['leavename']) && $atts['leavename'] == 'true' ? true : false;
                        $post_meta = get_post_permalink($id, $leavename);
                        $post_meta = $this->helper->load('translator')->translate_url($post_meta);
                    } elseif ($key == 'response_hook') {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_response_hook', '', $id, $atts, $wp_post);
                    } elseif (isset($wp_post->$key)) {
                        $post_meta = $wp_post->$key;
                    }
                }
            } elseif (in_array($key, $product_fields, true) && !$meta && !$terms && !$order_item_meta && !$order) {
                if ($wc_product && is_object($wc_product) && (method_exists($wc_product, $key) || $key == 'get_variation_attribute')) {
                    if ($key == 'get_attributes' || $key == 'get_variation_attributes') {
                        $wc_parent_product = false;
                        $parent_attributes = [];
                        $child_attributes = [];
                        if ($key == 'get_variation_attributes') {
                            $post_meta = $wc_product->get_attributes($wc_product);
                        } else {
                            if ($wc_product->is_type('variation')) {
                                $wc_parent_product = wc_get_product($wc_product->get_parent_id());
                            }
                            if ($wc_parent_product) {
                                if (isset($atts['hidden']) && $atts['hidden'] == 'true') {
                                    $parent_attributes = $wc_parent_product->get_attributes();
                                } else {
                                    $parent_attributes = array_filter($wc_parent_product->get_attributes(), 'wc_attributes_array_filter_visible');
                                }
                                $child_attributes = $wc_product->get_attributes($wc_product);
                                $post_meta = array_merge($parent_attributes, $child_attributes);
                            } else {
                                if (isset($atts['hidden']) && $atts['hidden'] == 'true') {
                                    $post_meta = $wc_product->get_attributes();
                                } else {
                                    $post_meta = array_filter($wc_product->get_attributes(), 'wc_attributes_array_filter_visible');
                                }
                            }
                        }

                        if (is_array($post_meta) && !empty($post_meta)) {
                            $exclude = [];
                            if (isset($atts['exclude'])) {
                                $exclude = explode(',', $atts['exclude']);
                            }
                            foreach ($exclude as $excluded) {
                                if (array_key_exists($excluded, $post_meta)) {
                                    unset($post_meta[$excluded]);
                                }
                            }
                        }

                        if ($wc_filter) {
                            $product_attributes = [];
                            foreach ($post_meta as $attribute_key => $attribute) {
                                if ($attribute && is_a($attribute, 'WC_Product_Attribute')) {
                                    $values = [];
                                    if ($attribute->is_taxonomy()) {
                                        $attribute_taxonomy = $attribute->get_taxonomy_object();
                                        if ($wc_parent_product) {
                                            $attribute_values = wc_get_product_terms($wc_parent_product->get_id(), $attribute->get_name(), ['fields' => 'all']);
                                        } else {
                                            $attribute_values = wc_get_product_terms($wc_product->get_id(), $attribute->get_name(), ['fields' => 'all']);
                                        }
                                        foreach ($attribute_values as $attribute_value) {
                                            $value_name = esc_html($attribute_value->name);
                                            if ($attribute_taxonomy->attribute_public) {
                                                $values[] = '<a href="' . esc_url(get_term_link($attribute_value->term_id, $attribute->get_name())) . '" rel="tag">' . $value_name . '</a>';
                                            } else {
                                                $values[] = $value_name;
                                            }
                                        }
                                    } else {
                                        $values = $attribute->get_options();
                                        foreach ($values as &$value) {
                                            $value = make_clickable(esc_html($value));
                                        }
                                    }

                                    $product_attributes['attribute_' . sanitize_title_with_dashes($attribute->get_name())] = [
                                        'label' => wc_attribute_label($attribute->get_name()),
                                        'value' => apply_filters('woocommerce_attribute', wpautop(wptexturize(implode(', ', $values))), $attribute, $values),
                                    ];
                                } else {
                                    $attribute_value = $wc_product->get_attribute($attribute_key);
                                    $attribute_value = implode(', ', array_map('trim', explode('|', $attribute_value)));
                                    $attribute_value = apply_filters('e2pdf_model_shortcode_wc_product_get_attribute_value', $attribute_value, $wc_product);
                                    $product_attributes['attribute_' . $attribute_key] = [
                                        'label' => wc_attribute_label($attribute_key, $wc_product),
                                        'value' => $attribute_value,
                                    ];
                                }
                            }

                            if ($key != 'get_variation_attributes') {
                                if ($wc_parent_product) {
                                    $product_attributes = apply_filters('woocommerce_display_product_attributes', $product_attributes, $wc_parent_product);
                                } else {
                                    $product_attributes = apply_filters('woocommerce_display_product_attributes', $product_attributes, $wc_product);
                                }
                            }
                            $post_meta = $product_attributes;
                        }
                    } elseif ($key == 'get_attribute' || $key == 'get_variation_attribute') {
                        if ($attribute) {

                            $wc_parent_product = false;
                            $parent_attributes = [];
                            $child_attributes = [];

                            if ($key == 'get_variation_attribute') {
                                $attributes = $wc_product->get_attributes($wc_product);
                            } else {
                                if ($wc_product->is_type('variation')) {
                                    $wc_parent_product = wc_get_product($wc_product->get_parent_id());
                                }
                                if ($wc_parent_product) {
                                    $parent_attributes = $wc_parent_product->get_attributes();
                                    $child_attributes = $wc_product->get_attributes($wc_product);
                                    $attributes = array_merge($parent_attributes, $child_attributes);
                                } else {
                                    $attributes = $wc_product->get_attributes();
                                }
                            }

                            if (isset($atts['show']) && $atts['show'] == 'label') {
                                if (isset($attributes[$attribute]) || isset($attributes['pa_' . $attribute])) {
                                    $wc_attribute = isset($attributes[$attribute]) ? $attributes[$attribute] : $attributes['pa_' . $attribute];
                                    if ($wc_attribute && is_a($wc_attribute, 'WC_Product_Attribute')) {
                                        $post_meta = wc_attribute_label($wc_attribute->get_name());
                                    } else {
                                        $post_meta = wc_attribute_label($attribute, $wc_product);
                                    }
                                }
                            } elseif (isset($atts['show']) && $atts['show'] == 'value') {
                                if ($wc_filter) {
                                    if (isset($attributes[$attribute]) || isset($attributes['pa_' . $attribute])) {
                                        $wc_attribute = isset($attributes[$attribute]) ? $attributes[$attribute] : $attributes['pa_' . $attribute];
                                        if ($wc_attribute && is_a($wc_attribute, 'WC_Product_Attribute')) {
                                            if ($wc_attribute->is_taxonomy()) {
                                                $attribute_taxonomy = $wc_attribute->get_taxonomy_object();
                                                if ($wc_parent_product) {
                                                    $attribute_values = wc_get_product_terms($wc_parent_product->get_id(), $wc_attribute->get_name(), ['fields' => 'all']);
                                                } else {
                                                    $attribute_values = wc_get_product_terms($wc_product->get_id(), $wc_attribute->get_name(), ['fields' => 'all']);
                                                }
                                                foreach ($attribute_values as $attribute_value) {
                                                    $value_name = esc_html($attribute_value->name);
                                                    if ($attribute_taxonomy->attribute_public) {
                                                        $values[] = '<a href="' . esc_url(get_term_link($attribute_value->term_id, $wc_attribute->get_name())) . '" rel="tag">' . $value_name . '</a>';
                                                    } else {
                                                        $values[] = $value_name;
                                                    }
                                                }
                                            } else {
                                                $values = $wc_attribute->get_options();
                                                foreach ($values as &$value) {
                                                    $value = make_clickable(esc_html($value));
                                                }
                                            }
                                            $post_meta = apply_filters('woocommerce_attribute', wpautop(wptexturize(implode(', ', $values))), $wc_attribute, $values);
                                            $post_meta = apply_filters('e2pdf_model_shortcode_wc_product_get_attribute_value', $post_meta, $wc_product);
                                        } else {
                                            if ($wc_parent_product) {
                                                if (isset($child_attributes[$attribute]) || isset($child_attributes['pa_' . $attribute])) {
                                                    $post_meta = $wc_product->get_attribute($attribute);
                                                    $post_meta = implode(', ', array_map('trim', explode('|', $post_meta)));
                                                    $post_meta = apply_filters('e2pdf_model_shortcode_wc_product_get_attribute_value', $post_meta, $wc_product);
                                                } else {
                                                    $post_meta = $wc_parent_product->get_attribute($attribute);
                                                    $post_meta = implode(', ', array_map('trim', explode('|', $post_meta)));
                                                    $post_meta = apply_filters('e2pdf_model_shortcode_wc_product_get_attribute_value', $post_meta, $wc_parent_product);
                                                }
                                            } else {
                                                $post_meta = $wc_product->get_attribute($attribute);
                                                $post_meta = implode(', ', array_map('trim', explode('|', $post_meta)));
                                                $post_meta = apply_filters('e2pdf_model_shortcode_wc_product_get_attribute_value', $post_meta, $wc_product);
                                            }
                                        }
                                    }
                                } else {
                                    if ($wc_parent_product) {
                                        if (isset($child_attributes[$attribute]) || isset($child_attributes['pa_' . $attribute])) {
                                            $post_meta = $wc_product->get_attribute($attribute);
                                            $post_meta = implode(', ', array_map('trim', explode('|', $post_meta)));
                                            $post_meta = apply_filters('e2pdf_model_shortcode_wc_product_get_attribute_value', $post_meta, $wc_product);
                                        } else {
                                            $post_meta = $wc_parent_product->get_attribute($attribute);
                                            $post_meta = implode(', ', array_map('trim', explode('|', $post_meta)));
                                            $post_meta = apply_filters('e2pdf_model_shortcode_wc_product_get_attribute_value', $post_meta, $wc_parent_product);
                                        }
                                    } else {
                                        $post_meta = $wc_product->get_attribute($attribute);
                                        $post_meta = implode(', ', array_map('trim', explode('|', $post_meta)));
                                        $post_meta = apply_filters('e2pdf_model_shortcode_wc_product_get_attribute_value', $post_meta, $wc_product);
                                    }
                                }
                            } else {
                                if ($wc_parent_product) {
                                    if (isset($child_attributes[$attribute]) || isset($child_attributes['pa_' . $attribute])) {
                                        $post_meta = $wc_product->get_attribute($attribute);
                                    } else {
                                        $post_meta = $wc_parent_product->get_attribute($attribute);
                                    }
                                } else {
                                    $post_meta = $wc_product->get_attribute($attribute);
                                }
                            }
                        }
                    } elseif ($key == 'get_short_description' || $key == 'get_description') {
                        $content = $wc_product->$key();
                        if (false !== strpos($content, '[')) {
                            $shortcode_tags = [
                                'e2pdf-exclude',
                                'e2pdf-save',
                                'e2pdf-view',
                            ];
                            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
                            $tagnames = array_intersect($shortcode_tags, $matches[1]);
                            if (!empty($tagnames)) {
                                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $content, $shortcodes);
                                foreach ($shortcodes[0] as $key => $shortcode_value) {
                                    $content = str_replace($shortcode_value, '', $content);
                                }
                            }
                        }

                        if (isset($atts['wc_format_content']) && $atts['wc_format_content'] == 'true') {
                            $content = apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_description', $content, $id);
                            $content = wc_format_content($content);
                        }

                        $post_meta = $content;
                    } elseif ($key == 'get_file_download_path') {
                        if ($download_index !== false) {
                            $downloads = $wc_product->get_downloads();
                            $download_item_index = 0;
                            foreach ($downloads as $download) {
                                if ($download_item_index == $download_index) {
                                    $post_meta = $wc_product->$key($download['id']);
                                    break;
                                }
                            }
                        }
                    } elseif ($key == 'get_image') {
                        $size = isset($atts['size']) ? $atts['size'] : 'woocommerce_thumbnail';
                        if (false !== strpos($size, 'x')) {
                            $image_size = explode('x', $size);
                            if (isset($image_size['0']) && isset($image_size['1'])) {
                                $image_width = absint($image_size['0']);
                                $image_height = absint($image_size['1']);
                                if ($image_width && $image_height) {
                                    $size = [
                                        $image_width, $image_height,
                                    ];
                                }
                            }
                        }
                        $post_meta = $wc_product->$key($size);
                    } elseif ($key == 'get_file') {
                        if ($download_index !== false) {
                            $downloads = $wc_product->get_downloads();
                            $download_item_index = 0;
                            foreach ($downloads as $download) {
                                if ($download_item_index == $download_index) {
                                    $post_meta = $wc_product->$key($download['id']);
                                    break;
                                }
                            }
                        } else {
                            $post_meta = $wc_product->$key();
                        }
                    } elseif ($key == 'get_date_created' || $key == 'get_date_modified') {
                        $format = isset($atts['format']) && $atts['format'] ? $atts['format'] : get_option('date_format') . ', ' . get_option('time_format');
                        $post_meta = wc_format_datetime($wc_product->$key(), $format);
                    } elseif ($key == 'get_category_ids') {
                        $wc_parent_product = false;
                        if ($wc_product->is_type('variation')) {
                            $wc_parent_product = wc_get_product($wc_product->get_parent_id());
                        }
                        if ($wc_parent_product) {
                            $post_meta = $wc_parent_product->$key();
                        } else {
                            $post_meta = $wc_product->$key();
                        }
                    } else {
                        $post_meta = $wc_product->$key();
                    }
                }
            } elseif (in_array($key, $product_item_fields, true) && !$meta && !$terms && !$order_item_meta) {
                if ($wc_order_id == 'cart' && $wc_order_item && is_array($wc_order_item) && $wc_product && is_object($wc_product)) {
                    if ($key == 'item_cart_response_hook') {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_item_cart_response_hook', '', $id, $atts, $wc_product, $wc_order_item, $wc_order_item_id, $wc_order_item_index);
                    } elseif ($key == 'cart_response_hook') {
                        /* Backward compatibility fix 1.16.09 */
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_cart_response_hook', '', $id, $atts, $wc_product, $wc_order_item, $wc_order_item_id, $wc_order_item_index);
                    } elseif ($key == 'get_name') {
                        if ($wc_filter) {
                            $product_permalink = apply_filters('woocommerce_cart_item_permalink', $wc_product->is_visible() ? $wc_product->get_permalink($wc_order_item) : '', $wc_order_item, $wc_order_item_id);
                            if (!$product_permalink) {
                                $post_meta = wp_kses_post(apply_filters('woocommerce_cart_item_name', $wc_product->get_name(), $wc_order_item, $wc_order_item_id) . '&nbsp;');
                            } else {
                                $post_meta = wp_kses_post(apply_filters('woocommerce_cart_item_name', sprintf('<a target="_blank" href="%s">%s</a>', esc_url($product_permalink), $wc_product->get_name()), $wc_order_item, $wc_order_item_id));
                            }
                        } else {
                            $post_meta = $wc_product->$key($attribute);
                        }
                    } elseif ($key == 'get_image') {
                        if (false !== strpos($size, 'x')) {
                            $image_size = explode('x', $size);
                            if (isset($image_size['0']) && isset($image_size['1'])) {
                                $image_width = absint($image_size['0']);
                                $image_height = absint($image_size['1']);
                                if ($image_width && $image_height) {
                                    $size = [
                                        $image_width, $image_height,
                                    ];
                                }
                            }
                        }
                        if ($wc_filter) {
                            $post_meta = apply_filters('woocommerce_cart_item_thumbnail', $wc_product->get_image($size), $wc_order_item, $wc_order_item_id);
                        } else {
                            $post_meta = $wc_product->get_image($size);
                        }
                    } elseif ($key == 'get_quantity') {
                        $post_meta = isset($wc_order_item['quantity']) ? $wc_order_item['quantity'] : '0';
                    } elseif ($key == 'get_subtotal') {
                        if ($wc_filter) {
                            $post_meta = apply_filters('woocommerce_cart_item_subtotal', $wc_order->get_product_subtotal($wc_product, $wc_order_item['quantity']), $wc_order_item, $wc_order_item_id);
                        } else {
                            $post_meta = $wc_order->get_product_subtotal($wc_product, $wc_order_item['quantity']);
                        }
                    } elseif ($key == 'get_product_price') {
                        if ($wc_filter) {
                            $post_meta = apply_filters('woocommerce_cart_item_price', $wc_order->get_product_price($wc_product), $wc_order_item, $wc_order_item_id);
                        } else {
                            $post_meta = $wc_order->get_product_price($wc_product);
                        }
                    } elseif ($key == 'get_formatted_meta_data' || $key == 'get_formatted_cart_item_data') {
                        if ($wc_filter || $key == 'get_formatted_cart_item_data') {
                            if (isset($atts['flat']) && $atts['flat'] == 'true') {
                                $flat = true;
                            } else {
                                $flat = false;
                            }
                            if (isset($atts['nl2br']) && $atts['nl2br'] == 'true') {
                                $post_meta = nl2br(wc_get_formatted_cart_item_data($wc_order_item, $flat));
                            } else {
                                $post_meta = wc_get_formatted_cart_item_data($wc_order_item, $flat);
                            }
                        } else {
                            $item_data = [];
                            if ($wc_order_item['data']->is_type('variation') && is_array($wc_order_item['variation'])) {
                                foreach ($wc_order_item['variation'] as $name => $value) {
                                    $taxonomy = wc_attribute_taxonomy_name(str_replace('attribute_pa_', '', urldecode($name)));
                                    if (taxonomy_exists($taxonomy)) {
                                        $term = get_term_by('slug', $value, $taxonomy);
                                        if (!is_wp_error($term) && $term && $term->name) {
                                            $value = $term->name;
                                        }
                                        $label = wc_attribute_label($taxonomy);
                                    } else {
                                        $value = apply_filters('woocommerce_variation_option_name', $value, null, $taxonomy, $wc_order_item['data']);
                                        $label = wc_attribute_label(str_replace('attribute_', '', $name), $wc_order_item['data']);
                                    }

                                    if ('' === $value || wc_is_attribute_in_product_name($value, $wc_order_item['data']->get_name())) {
                                        continue;
                                    }

                                    $item_data[] = [
                                        'key' => $label,
                                        'value' => $value,
                                    ];
                                }
                            }

                            $item_data = apply_filters('woocommerce_get_item_data', $item_data, $wc_order_item);

                            foreach ($item_data as $key => $data) {
                                if (isset($atts['hidden']) && $atts['hidden'] == 'false') {
                                    if (!empty($data['hidden'])) {
                                        unset($item_data[$key]);
                                        continue;
                                    }
                                }
                                $item_data[$key]['display_key'] = !empty($data['key']) ? $data['key'] : $data['name'];
                                $item_data[$key]['display_value'] = !empty($data['display']) ? $data['display'] : $data['value'];
                            }

                            $post_meta = $item_data;
                        }
                    } else {
                        $post_meta = isset($wc_order_item[$key]) ? $wc_order_item[$key] : '';
                    }
                } elseif ($wc_order_item && is_object($wc_order_item)) {
                    if ($key == 'get_order_item_id') {
                        $post_meta = $wc_order_item_id ? $wc_order_item_id : '';
                    } elseif ($key == 'item_response_hook') {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_item_response_hook', '', $id, $atts, $wc_product, $wc_order_item, $wc_order_item_id, $wc_order_item_index);
                    } elseif (method_exists($wc_order_item, $key)) {
                        if ($key == 'get_item_download_url') {
                            if ($download_index !== false) {
                                $downloads = $wc_order_item->get_item_downloads();
                                $download_item_index = 0;
                                foreach ($downloads as $download) {
                                    if ($download_item_index == $download_index) {
                                        $post_meta = $wc_order_item->$key($download['id']);
                                        break;
                                    }
                                }
                            }
                        } elseif ($key == 'get_item_downloads' && $download_index !== false) {
                            $download_item_index = 0;
                            foreach ($downloads as $download) {
                                if ($download_item_index == $download_index) {
                                    $post_meta = $download;
                                    break;
                                }
                            }
                        } else {
                            $post_meta = $wc_order_item->$key();
                        }
                    }
                }
            } elseif (in_array($key, $product_order_fields, true) && !$meta && !$terms && !$order_item_meta) {
                if ($wc_order_item) {
                    if ($key == 'order_response_hook') {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_order_response_hook', '', $id, $atts, $wc_product, $wc_order_item, $wc_order_item_id, $wc_order_item_index);
                    } elseif ($wc_order && is_object($wc_order) && method_exists($wc_order, $key)) {
                        if ($key == 'get_item_subtotal' || $key == 'get_item_total' || $key == 'get_line_total') {
                            $inc_tax = isset($atts['inc_tax']) && $atts['inc_tax'] == 'true' ? true : false;
                            $round = isset($atts['round']) && $atts['round'] == 'false' ? false : true;
                            $post_meta = $wc_order->$key($wc_order_item, $inc_tax, $round);
                        } elseif ($key == 'get_item_tax') {
                            $round = isset($atts['round']) && $atts['round'] == 'false' ? false : true;
                            $post_meta = $wc_order->$key($wc_order_item, $round);
                        } elseif ($key == 'get_formatted_line_subtotal') {
                            $tax_display = isset($atts['tax_display']) && $atts['tax_display'] ? $atts['tax_display'] : '';
                            $post_meta = $wc_order->$key($wc_order_item, $tax_display);
                        } else {
                            $post_meta = $wc_order->$key($wc_order_item);
                        }
                    }
                }
            } elseif ($order_item_meta) {
                if ($wc_order_item_id) {
                    $post_meta = wc_get_order_item_meta($wc_order_item_id, $key, true);
                }
            } elseif ($terms && $names) {
                if ($wp_post) {
                    $post_terms = wp_get_post_terms($id, $key, ['fields' => 'names']);
                    if (!is_wp_error($post_terms) && is_array($post_terms)) {
                        foreach ($post_terms as $post_term_key => $post_terms_value) {
                            $post_terms[$post_term_key] = $this->helper->load('translator')->translate($post_terms_value);
                        }
                        if ($implode === false) {
                            $implode = ', ';
                        }
                        $post_meta = implode($implode, $post_terms);
                    }
                }
            } elseif ($terms) {
                if ($wp_post) {
                    $post_terms = wp_get_post_terms($id, $key);
                    if (!is_wp_error($post_terms)) {
                        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
                        $post_meta = json_decode(json_encode($post_terms), true);
                    }
                }
            } else {
                $post_meta = $wp_post ? get_post_meta($id, $key, true) : false;
                if ($post_meta === false && $wc_order_item) {
                    $post_meta = $wc_order_item->get_meta($key);
                }
            }

            if ($post_meta !== false) {

                if (is_object($post_meta)) {
                    $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_object', $post_meta, $atts);
                }

                if ($explode && !is_array($post_meta)) {
                    $post_meta = explode($explode, $post_meta);
                }

                if (is_array($post_meta)) {
                    $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_array', $post_meta, $atts);
                }

                if (is_string($post_meta) && $path !== false && is_object(json_decode($post_meta))) {
                    $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_json', json_decode($post_meta, true), $atts);
                }

                if ((is_array($post_meta) || is_object($post_meta)) && $path !== false) {
                    $post_meta = $this->helper->load('shortcode')->apply_path_attribute($post_meta, $path);
                }

                if ($attachment_url || $attachment_image_url) {
                    if (!is_array($post_meta)) {
                        if (strpos($post_meta, ',') !== false) {
                            $post_meta = explode(',', $post_meta);
                            if ($implode === false) {
                                $implode = ',';
                            }
                        }
                    }
                    if ($attachment_url) {
                        $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_url', $size);
                    } else {
                        $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_image_url', $size);
                    }
                }

                if ($wc_price || $wc_price_raw) {
                    if (is_array($post_meta) || is_object($post_meta)) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
                    } else {
                        if (isset($atts['currency'])) {
                            $post_meta = wc_price($post_meta, $atts['currency']);
                        } else {
                            if (!$wc_order && $wc_order_id) {
                                $wc_order = wc_get_order($wc_order_id);
                            }
                            if ($wc_order && is_object($wc_order) && method_exists($wc_order, 'get_currency')) {
                                $post_meta = wc_price($post_meta, $wc_order->get_currency());
                            } else {
                                $post_meta = wc_price($post_meta);
                            }
                        }
                        if ($wc_price_raw) {
                            $post_meta = html_entity_decode(wp_strip_all_tags($post_meta));
                        }
                    }
                }

                if ($convert) {
                    $post_meta = $this->helper->load('shortcode')->apply_convert_attribute($convert, $post_meta, $implode);
                }

                if (apply_filters('e2pdf_raw_output', false)) {
                    $response = $post_meta;
                } else {
                    if (is_array($post_meta)) {
                        if ($implode !== false) {
                            if (!$this->helper->is_multidimensional($post_meta)) {
                                foreach ($post_meta as $post_meta_key => $post_meta_value) {
                                    $post_meta[$post_meta_key] = $this->helper->load('translator')->translate($post_meta_value);
                                }
                                $response = implode($implode, $post_meta);
                            } else {
                                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                                $response = serialize($post_meta);
                            }
                        } else {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                            $response = serialize($post_meta);
                        }
                    } elseif (is_object($post_meta)) {
                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                        $response = serialize($post_meta);
                    } else {
                        $response = $post_meta;
                    }
                }
            }
        }

        if (apply_filters('e2pdf_raw_output', false)) {
            return apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_raw', $response, $atts, $value);
        } else {
            $response = $this->helper->load('translator')->translate($response, 'partial');
            if (!apply_filters('e2pdf_pdf_fill', false)) {
                $response = $this->sanitize_html($response);
            }
            return apply_filters('e2pdf_model_shortcode_e2pdf_wc_product_response', $response, $atts, $value);
        }
    }

    // e2pdf-wc-order
    public function e2pdf_wc_order($atts = [], $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_wc_order', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $post_meta = false;
        $response = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_wc_order_atts', $atts);

        $id = isset($atts['id']) ? $atts['id'] : false;
        $key = isset($atts['key']) ? $atts['key'] : false;
        $subkey = isset($atts['subkey']) ? $atts['subkey'] : false;
        $index = isset($atts['index']) ? $atts['index'] : false;
        $path = isset($atts['path']) ? $atts['path'] : false;
        $names = isset($atts['names']) && $atts['names'] == 'true' ? true : false;
        $explode = isset($atts['explode']) ? $atts['explode'] : false;
        $implode = isset($atts['implode']) ? $atts['implode'] : false;
        $attachment_url = isset($atts['attachment_url']) && $atts['attachment_url'] == 'true' ? true : false;
        $attachment_image_url = isset($atts['attachment_image_url']) && $atts['attachment_image_url'] == 'true' ? true : false;
        $size = isset($atts['size']) ? $atts['size'] : 'thumbnail';
        $meta = isset($atts['meta']) && $atts['meta'] == 'true' ? true : false;
        $order_item_meta = isset($atts['order_item_meta']) && $atts['order_item_meta'] == 'true' ? true : false;
        $terms = isset($atts['terms']) && $atts['terms'] == 'true' ? true : false;
        $output = isset($atts['output']) ? $atts['output'] : false;
        $checkout_field_editor = isset($atts['checkout_field_editor']) && $atts['checkout_field_editor'] == 'true' ? true : false;
        $parent = isset($atts['parent']) && $atts['parent'] == 'true' ? true : false;
        $wc_price = isset($atts['wc_price']) && $atts['wc_price'] == 'true' ? true : false;
        $wc_price_raw = isset($atts['wc_price_raw']) && $atts['wc_price_raw'] == 'true' ? true : false;

        if ($id == 'dynamic') {
            $id = $value;
        }

        if ($parent) {
            $order_id = false;
            if ($id && function_exists('wcs_get_subscription') && get_post_type($id) == 'shop_subscription') {
                $subscription = wcs_get_subscription($id);
                if ($subscription) {
                    $subscription_order = $subscription->get_parent();
                    if ($subscription_order) {
                        $order_id = $subscription_order->get_id();
                    }
                }
            }
            $id = $order_id;
        }

        $data_fields = apply_filters(
                'e2pdf_model_shortcode_wc_order_data_fields',
                [
                    'id',
                    'post_author',
                    'post_author_id',
                    'post_date',
                    'post_date_gmt',
                    'post_content',
                    'post_title',
                    'post_excerpt',
                    'post_status',
                    'permalink',
                    'post_permalink',
                    'get_post_permalink',
                    'comment_status',
                    'ping_status',
                    'post_password',
                    'post_name',
                    'to_ping',
                    'pinged',
                    'post_modified',
                    'post_modified_gmt',
                    'post_content_filtered',
                    'post_parent',
                    'guid',
                    'menu_order',
                    'post_type',
                    'post_mime_type',
                    'comment_count',
                    'filter',
                    'post_thumbnail',
                    'get_the_post_thumbnail',
                    'get_the_post_thumbnail_url',
                    'response_hook',
                ]
        );

        $order_fields = apply_filters(
                'e2pdf_model_shortcode_wc_order_order_fields',
                [
                    'cart',
                    'get_id',
                    'get_order_key',
                    'get_order_number',
                    'get_formatted_order_total',
                    'get_cart_tax',
                    'get_currency',
                    'get_discount_tax',
                    'get_discount_to_display',
                    'get_discount_total',
                    'get_shipping_tax',
                    'get_shipping_total',
                    'get_subtotal',
                    'get_subtotal_to_display',
                    'get_total',
                    'get_total_discount',
                    'get_total_tax',
                    'get_total_refunded',
                    'get_total_tax_refunded',
                    'get_total_shipping_refunded',
                    'get_item_count_refunded',
                    'get_total_qty_refunded',
                    'get_remaining_refund_amount',
                    'get_item_count',
                    'get_shipping_method',
                    'get_shipping_to_display',
                    'get_date_created',
                    'get_date_modified',
                    'get_date_completed',
                    'get_date_paid',
                    'get_customer_id',
                    'get_user_id',
                    'get_customer_ip_address',
                    'get_customer_user_agent',
                    'get_created_via',
                    'get_customer_note',
                    'get_billing_first_name',
                    'get_billing_last_name',
                    'get_billing_company',
                    'get_billing_address_1',
                    'get_billing_address_2',
                    'get_billing_city',
                    'get_billing_state',
                    'get_billing_postcode',
                    'get_billing_country',
                    'get_billing_email',
                    'get_billing_phone',
                    'get_shipping_first_name',
                    'get_shipping_last_name',
                    'get_shipping_company',
                    'get_shipping_address_1',
                    'get_shipping_address_2',
                    'get_shipping_city',
                    'get_shipping_state',
                    'get_shipping_postcode',
                    'get_shipping_country',
                    'get_shipping_address_map_url',
                    'get_formatted_billing_full_name',
                    'get_formatted_shipping_full_name',
                    'get_formatted_billing_address',
                    'get_formatted_shipping_address',
                    'get_payment_method',
                    'get_payment_method_title',
                    'get_transaction_id',
                    'get_checkout_payment_url',
                    'get_checkout_order_received_url',
                    'get_cancel_order_url',
                    'get_cancel_order_url_raw',
                    'get_cancel_endpoint',
                    'get_view_order_url',
                    'get_edit_order_url',
                    'get_status',
                    'get_coupons',
                    'get_fees',
                    'get_taxes',
                    'get_shipping_methods',
                    'get_coupon_codes',
                    'get_items_tax_classes',
                    'get_total_fees',
                    'get_order_item_totals',
                    'get_tax_totals',
                    'get_items',
                    'get_items_ids',
                    'get_items_category',
                    'get_items_category_ids',
                ]
        );

        if ($id && $key) {
            $wp_post = get_post($id);
            if ($wp_post) {
                if (in_array($key, $data_fields, true) && !$meta && !$order_item_meta && !$terms) {
                    if ($key == 'post_author') {
                        $post_meta = isset($wp_post->post_author) && $wp_post->post_author ? get_userdata($wp_post->post_author)->user_nicename : '';
                    } elseif ($key == 'post_author_id') {
                        $post_meta = isset($wp_post->post_author) && $wp_post->post_author ? $wp_post->post_author : '0';
                    } elseif ($key == 'id' && isset($wp_post->ID)) {
                        $post_meta = $wp_post->ID;
                    } elseif (($key == 'post_thumbnail' || $key == 'get_the_post_thumbnail_url') && isset($wp_post->ID)) {
                        $post_meta = get_the_post_thumbnail_url($wp_post->ID, $size);
                    } elseif ($key == 'get_the_post_thumbnail' && isset($wp_post->ID)) {
                        $post_meta = get_the_post_thumbnail($wp_post->ID, $size);
                    } elseif ($key == 'post_content' && isset($wp_post->post_content)) {
                        $content = $wp_post->post_content;
                        if (false !== strpos($content, '[')) {
                            $shortcode_tags = array(
                                'e2pdf-exclude',
                                'e2pdf-save',
                                'e2pdf-view',
                            );
                            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
                            $tagnames = array_intersect($shortcode_tags, $matches[1]);
                            if (!empty($tagnames)) {
                                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $content, $shortcodes);
                                foreach ($shortcodes[0] as $key => $shortcode_value) {
                                    $content = str_replace($shortcode_value, '', $content);
                                }
                            }
                        }
                        if ($output) {
                            global $post;
                            $tmp_post = $post;
                            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                            $post = $wp_post;
                            if ($output == 'backend') {
                                if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin')) {
                                    \Elementor\Plugin::instance()->frontend->remove_content_filter();
                                }
                            } elseif ($output == 'frontend') {
                                if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin')) {
                                    \Elementor\Plugin::instance()->frontend->add_content_filter();
                                }
                            }
                        }

                        if (defined('ET_BUILDER_DIR') && 'on' === get_post_meta($id, '_et_pb_use_builder', true) && function_exists('et_builder_init_global_settings') && function_exists('et_builder_add_main_elements')) {
                            require_once ET_BUILDER_DIR . 'class-et-builder-element.php';
                            require_once ET_BUILDER_DIR . 'functions.php';
                            require_once ET_BUILDER_DIR . 'ab-testing.php';
                            require_once ET_BUILDER_DIR . 'class-et-global-settings.php';
                            et_builder_add_main_elements();
                        }

                        if (class_exists('WPBMap') && method_exists('WPBMap', 'addAllMappedShortcodes')) {
                            WPBMap::addAllMappedShortcodes();
                        }

                        $content = apply_filters('the_content', $content, $id);
                        $content = str_replace('</p>', "</p>\r\n", $content);
                        $post_meta = $content;

                        if ($output) {
                            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                            $post = $tmp_post;
                        }
                    } elseif ($key == 'permalink') {
                        $leavename = isset($atts['leavename']) && $atts['leavename'] == 'true' ? true : false;
                        $post_meta = get_permalink($id, $leavename);
                        $post_meta = $this->helper->load('translator')->translate_url($post_meta);
                    } elseif ($key == 'get_post_permalink' || $key == 'post_permalink') {
                        $leavename = isset($atts['leavename']) && $atts['leavename'] == 'true' ? true : false;
                        $post_meta = get_post_permalink($id, $leavename);
                        $post_meta = $this->helper->load('translator')->translate_url($post_meta);
                    } elseif ($key == 'response_hook') {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_order_response_hook', '', $id, $atts, $wp_post);
                    } elseif (isset($wp_post->$key)) {
                        $post_meta = $wp_post->$key;
                    }
                } elseif (in_array($key, $order_fields, true) && !$meta && !$order_item_meta && !$terms) {
                    $order = wc_get_order($id);
                    if ($order) {
                        if ($key == 'cart') {
                            $items = $order->get_items();
                            $content = '';
                            if ($items) {
                                $show_products = isset($atts['show_products']) && $atts['show_products'] == 'false' ? false : true;
                                $show_image = isset($atts['show_image']) && $atts['show_image'] == 'false' ? false : true;
                                $show_sku = isset($atts['show_sku']) && $atts['show_sku'] == 'false' ? false : true;
                                $show_name = isset($atts['show_name']) && $atts['show_name'] == 'false' ? false : true;
                                $show_quantity = isset($atts['show_quantity']) && $atts['show_quantity'] == 'false' ? false : true;
                                $show_price = isset($atts['show_price']) && $atts['show_price'] == 'false' ? false : true;
                                $show_subtotal = isset($atts['show_subtotal']) && $atts['show_subtotal'] == 'false' ? false : true;
                                $show_meta = isset($atts['show_meta']) && $atts['show_meta'] == 'false' ? false : true;

                                $show_totals = isset($atts['show_totals']) && $atts['show_totals'] == 'false' ? false : true;
                                $show_totals_subtotal = isset($atts['show_totals_subtotal']) && $atts['show_totals_subtotal'] == 'false' ? false : true;
                                $show_totals_discount = isset($atts['show_totals_discount']) && $atts['show_totals_discount'] == 'false' ? false : true;
                                $show_totals_payment_method = isset($atts['show_totals_payment_method']) && $atts['show_totals_payment_method'] == 'false' ? false : true;
                                $show_totals_shipping = isset($atts['show_totals_shipping']) && $atts['show_totals_shipping'] == 'false' ? false : true;
                                $show_totals_total = isset($atts['show_totals_total']) && $atts['show_totals_total'] == 'false' ? false : true;
                                $show_comment = isset($atts['show_comment']) && $atts['show_comment'] == 'false' ? false : true;

                                if (isset($atts['size'])) {
                                    $size = $atts['size'];
                                } elseif (isset($atts['image_size'])) {
                                    $size = $atts['image_size'];
                                } else {
                                    $size = '32x32';
                                }

                                if (false !== strpos($size, 'x')) {
                                    $image_size = explode('x', $size);
                                    if (isset($image_size['0']) && isset($image_size['1'])) {
                                        $image_width = absint($image_size['0']);
                                        $image_height = absint($image_size['1']);
                                        if ($image_width && $image_height) {
                                            $size = [
                                                $image_width, $image_height,
                                            ];
                                        }
                                    }
                                }

                                $plain_text = isset($atts['plain_text']) ? $atts['plain_text'] : false;

                                if ($show_products) {
                                    $content .= "<table split='true' border='1' bordercolor='#eeeeee' cellpadding='5' class='e2pdf-wc-cart-products'>";
                                    $content .= "<tr bgcolor='#eeeeee' class='e2pdf-wc-cart-products-header'>";
                                    if ($show_image) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-image'>" . apply_filters('e2pdf_model_shortcode_wc_order_cart_header_image_text', '', $atts, $value) . '</td>';
                                    }
                                    if ($show_name) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-name'>" . apply_filters('e2pdf_model_shortcode_wc_order_cart_header_name_text', __('Product', 'woocommerce'), $atts, $value) . '</td>';
                                    }
                                    if ($show_sku) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-sku'>" . apply_filters('e2pdf_model_shortcode_wc_order_cart_header_sku_text', __('SKU', 'woocommerce'), $atts, $value) . '</td>';
                                    }
                                    if ($show_quantity) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-quantity'>" . apply_filters('e2pdf_model_shortcode_wc_order_cart_header_quantity_text', __('Quantity', 'woocommerce'), $atts, $value) . '</td>';
                                    }
                                    if ($show_price) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-price'>" . apply_filters('e2pdf_model_shortcode_wc_order_cart_header_pricey_text', __('Price', 'woocommerce'), $atts, $value) . '</td>';
                                    }
                                    if ($show_subtotal) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-subtotal'>" . apply_filters('e2pdf_model_shortcode_wc_order_cart_header_pricey_text', __('Subtotal', 'woocommerce'), $atts, $value) . '</td>';
                                    }
                                    $content .= '</tr>';

                                    $item_index = 0;
                                    foreach ($items as $item_id => $item) {

                                        $product = $item->get_product();
                                        $sku = '';
                                        $purchase_note = '';
                                        $image = '';

                                        $woocommerce_order_item_visible = apply_filters('woocommerce_order_item_visible', true, $item);
                                        if (!apply_filters('e2pdf_woocommerce_order_item_visible', $woocommerce_order_item_visible, $item, $atts)) {
                                            continue;
                                        }

                                        if (!empty($atts['in_categories'])) {
                                            $in_categories = explode(',', $atts['in_categories']);
                                            $categories = wp_get_post_terms($item->get_product_id(), 'product_cat', ['fields' => 'slugs']);
                                            if (empty($categories) || !array_intersect($in_categories, $categories)) {
                                                continue;
                                            }
                                        }

                                        if (is_object($product)) {
                                            $sku = $product->get_sku();
                                            $purchase_note = $product->get_purchase_note();
                                            $image = $product->get_image($size);
                                        }

                                        $even_odd = $item_index % 2 ? 'e2pdf-wc-cart-product-odd' : 'e2pdf-wc-cart-product-even';
                                        $content .= "<tr class='e2pdf-wc-cart-product " . $even_odd . "'>";

                                        if ($show_image) {
                                            $content .= "<td align='center' class='e2pdf-wc-cart-product-image'>" . apply_filters('woocommerce_order_item_thumbnail', $image, $item) . '</td>';
                                        }

                                        if ($show_name) {
                                            $content .= "<td class='e2pdf-wc-cart-product-name'>";

                                            $is_visible = $product && $product->is_visible();
                                            $product_permalink = apply_filters('woocommerce_order_item_permalink', $is_visible ? $product->get_permalink($item) : '', $item, $order);
                                            $content .= apply_filters('woocommerce_order_item_name', $product_permalink ? sprintf('<a target="_blank" href="%s">%s</a>', $product_permalink, $item->get_name()) : $item->get_name(), $item, $is_visible);

                                            if ($show_meta) {
                                                $wc_display_item_meta = wc_display_item_meta(
                                                        $item,
                                                        [
                                                            'echo' => false,
                                                            'before' => '',
                                                            'separator' => '',
                                                            'after' => '',
                                                            'label_before' => "<div size='8px' class='e2pdf-wc-cart-product-meta'>",
                                                            'lable_after' => '</div>',
                                                        ]
                                                );

                                                if ($wc_display_item_meta) {
                                                    $content .= str_replace(['<p>', '</p>'], ['', ''], $wc_display_item_meta);
                                                }
                                            }

                                            $content .= '</td>';
                                        }

                                        if ($show_sku) {
                                            $content .= "<td class='e2pdf-wc-cart-product-sku'>" . $sku . '</td>';
                                        }

                                        if ($show_quantity) {
                                            $qty = $item->get_quantity();
                                            $refunded_qty = $order->get_qty_refunded_for_item($item_id);
                                            if ($refunded_qty) {
                                                $qty_display = '<del>' . esc_html($qty) . '</del> ' . esc_html($qty - ($refunded_qty * -1)) . '';
                                            } else {
                                                $qty_display = esc_html($qty);
                                            }
                                            $content .= "<td class='e2pdf-wc-cart-product-quantity'>" . apply_filters('woocommerce_email_order_item_quantity', $qty_display, $item) . '</td>';
                                        }

                                        if ($show_price) {
                                            $content .= "<td class='e2pdf-wc-cart-product-price'>" . wc_price($order->get_item_subtotal($item, false, true), ['currency' => $order->get_currency()]) . '</td>';
                                        }

                                        if ($show_subtotal) {
                                            $content .= "<td class='e2pdf-wc-cart-product-subtotal'>" . $order->get_formatted_line_subtotal($item) . '</td>';
                                        }

                                        $content .= '</tr>';
                                        $item_index++;
                                    }
                                    $content .= '</table>';
                                }

                                if ($show_comment && $order->get_customer_note()) {
                                    $content .= "<table split='true' size='8px' margin-top='1' border='1' bordercolor='#eeeeee' cellpadding='5' class='e2pdf-wc-cart-comment'>";
                                    $content .= '<tr>';
                                    $content .= '<td>' . nl2br(wptexturize($order->get_customer_note())) . '</td>';
                                    $content .= '</tr>';
                                    $content .= '</table>';
                                }

                                if ($show_totals) {
                                    $item_totals = apply_filters('e2pdf_model_shortcode_wc_order_item_totals', $order->get_order_item_totals(), $atts, $value);
                                    if (!empty($item_totals)) {
                                        $total_index = 0;
                                        $content .= "<table split='true' cellpadding='5' class='e2pdf-wc-cart-totals'>";
                                        foreach ($item_totals as $total_key => $total) {
                                            if (
                                                    ($total_key == 'cart_subtotal' && !$show_totals_subtotal) ||
                                                    ($total_key == 'discount' && !$show_totals_discount) ||
                                                    ($total_key == 'shipping' && !$show_totals_shipping) ||
                                                    ($total_key == 'payment_method' && !$show_totals_payment_method) ||
                                                    ($total_key == 'order_total' && !$show_totals_total)
                                            ) {
                                                continue;
                                            }
                                            $even_odd = $total_index % 2 ? 'e2pdf-wc-cart-total-odd' : 'e2pdf-wc-cart-total-even';
                                            $content .= "<tr class='e2pdf-wc-cart-total e2pdf-wc-cart-total-" . $total_key . ' ' . $even_odd . "'>";
                                            $content .= "<td valign='top' width='60%' align='right' class='e2pdf-wc-cart-total-label'>" . $total['label'] . '</td>';
                                            $content .= "<td valign='top' align='right' class='e2pdf-wc-cart-total-value'>" . $total['value'] . '</td>';
                                            $content .= '</tr>';
                                            $total_index++;
                                        }
                                        $content .= '</table>';
                                    }
                                }

                                $post_meta = $content;
                            }
                        } elseif ($key == 'get_items_category' || $key == 'get_items_category_ids') {
                            $item_metas = [];
                            foreach ($order->get_items() as $item) {
                                if (is_callable(array($item, 'get_product_id'))) {
                                    $product_categories = wp_get_post_terms($item->get_product_id(), 'product_cat');
                                    foreach ($product_categories as $product_category) {
                                        if ($key == 'get_items_category') {
                                            $item_metas[] = $product_category->name;
                                        } else {
                                            $item_metas[] = $product_category->term_id;
                                        }
                                    }
                                }
                            }
                            $post_meta = $item_metas;
                        } elseif ($key == 'get_items_ids') {
                            $item_metas = [];
                            foreach ($order->get_items() as $item) {
                                if (is_callable(array($item, 'get_product_id')) && $item->get_product_id()) {
                                    $item_metas[] = $item->get_product_id();
                                }
                                if (is_callable(array($item, 'get_variation_id')) && $item->get_variation_id()) {
                                    $item_metas[] = $item->get_variation_id();
                                }
                            }
                            $post_meta = $item_metas;
                        } else {
                            if ($order && is_object($order) && method_exists($order, $key)) {
                                if ($key == 'get_date_created' || $key == 'get_date_modified' || $key == 'get_date_completed' || $key == 'get_date_paid') {
                                    $format = isset($atts['format']) && $atts['format'] ? $atts['format'] : get_option('date_format') . ', ' . get_option('time_format');
                                    $post_meta = wc_format_datetime($order->$key(), $format);
                                } elseif ($key == 'get_formatted_billing_address' || $key == 'get_formatted_shipping_address') {
                                    $empty_content = isset($atts['empty_content']) ? $atts['empty_content'] : '';
                                    $post_meta = $order->$key($empty_content);
                                } elseif ($key == 'get_status') {
                                    $post_meta = $order->$key();
                                    $wc_get_order_status_name = isset($atts['wc_get_order_status_name']) && $atts['wc_get_order_status_name'] == 'true' ? true : false;
                                    if ($wc_get_order_status_name) {
                                        $post_meta = wc_get_order_status_name($post_meta);
                                    }
                                } elseif ($key == 'get_items') {
                                    $types = isset($atts['types']) ? explode(',', $atts['types']) : ['line_item'];
                                    $post_meta = $order->$key($types);
                                } elseif ($key == 'get_subtotal_to_display') {
                                    $compound = isset($atts['compound']) && $atts['compound'] == 'true' ? true : false;
                                    $tax_display = isset($atts['tax_display']) ? $atts['tax_display'] : '';
                                    $post_meta = $order->$key($compound, $tax_display);
                                } elseif ($key == 'get_total_discount') {
                                    $ex_tax = isset($atts['ex_tax']) && $atts['ex_tax'] == 'false' ? false : true;
                                    $post_meta = $order->$key($ex_tax);
                                } else {
                                    $post_meta = $order->$key();
                                }
                            }
                        }
                    }
                } elseif ($terms && $names) {
                    $post_terms = wp_get_post_terms($id, $key, ['fields' => 'names']);
                    if (!is_wp_error($post_terms) && is_array($post_terms)) {
                        foreach ($post_terms as $post_term_key => $post_terms_value) {
                            $post_terms[$post_term_key] = $this->helper->load('translator')->translate($post_terms_value);
                        }
                        if ($implode === false) {
                            $implode = ', ';
                        }
                        $post_meta = implode($implode, $post_terms);
                    }
                } elseif ($terms) {
                    $post_terms = wp_get_post_terms($id, $key);
                    if (!is_wp_error($post_terms)) {
                        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
                        $post_meta = json_decode(json_encode($post_terms), true);
                    }
                } elseif ($order_item_meta) {
                    if ($subkey) {
                        $order = wc_get_order($id);
                        if ($order) {
                            $items = $order->get_items($key);
                            if ($items) {
                                if ($index !== false) {
                                    $i = 0;
                                    foreach ($items as $item_id => $item) {
                                        if ($i == $index) {
                                            $post_meta = wc_get_order_item_meta($item_id, $subkey, true);
                                            break;
                                        }
                                        $i++;
                                    }
                                } else {
                                    $item_metas = [];
                                    foreach ($items as $item_id => $item) {
                                        $item_metas[] = wc_get_order_item_meta($item_id, $subkey, true);
                                    }
                                    $post_meta = $item_metas;
                                }
                            }
                        }
                    } else {
                        $order = wc_get_order($id);
                        if ($order) {
                            $items = $order->get_items($key);
                            if ($items) {
                                global $wpdb;
                                $item_metas = [];
                                $i = 0;
                                foreach ($items as $item_id => $item) {
                                    if ($index !== false) {
                                        if ($i == $index) {
                                            $condition = [
                                                'meta.order_item_id' => [
                                                    'condition' => '=',
                                                    'value' => $item_id,
                                                    'type' => '%d',
                                                ],
                                            ];
                                            $where = $this->helper->load('db')->prepare_where($condition);

                                            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                                            $meta_data = $wpdb->get_results($wpdb->prepare('SELECT DISTINCT `meta`.`meta_key` FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta` `meta`' . $where['sql'] . '', $where['filter']), ARRAY_A);
                                            if (!empty($meta_data)) {
                                                foreach ($meta_data as $meta_key) {
                                                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                                                    $item_metas[$i][$meta_key['meta_key']] = wc_get_order_item_meta($item_id, $meta_key['meta_key'], true);
                                                }
                                            }
                                            break;
                                        }
                                    } else {
                                        $condition = [
                                            'meta.order_item_id' => [
                                                'condition' => '=',
                                                'value' => $item_id,
                                                'type' => '%d',
                                            ],
                                        ];
                                        $where = $this->helper->load('db')->prepare_where($condition);

                                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                                        $meta_data = $wpdb->get_results($wpdb->prepare('SELECT DISTINCT `meta`.`meta_key` FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta` `meta`' . $where['sql'] . '', $where['filter']), ARRAY_A);
                                        if (!empty($meta_data)) {
                                            foreach ($meta_data as $meta_key) {
                                                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                                                $item_metas[$i][$meta_key['meta_key']] = wc_get_order_item_meta($item_id, $meta_key['meta_key'], true);
                                            }
                                        }
                                    }
                                    $i++;
                                }
                                $post_meta = $item_metas;
                            }
                        }
                    }
                } else {
                    if (get_option('woocommerce_custom_orders_table_enabled') === 'yes' && get_option('woocommerce_custom_orders_table_data_sync_enabled') !== 'yes') {
                        $order = wc_get_order($id);
                        if ($order) {
                            $post_meta = $order->get_meta($key, true, 'edit');
                            if (!$post_meta) {
                                $post_meta = get_post_meta($id, $key, true);
                            }
                        }
                    } else {
                        $post_meta = get_post_meta($id, $key, true);
                    }
                }

                if ($post_meta !== false) {

                    if (is_object($post_meta)) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_order_object', $post_meta, $atts);
                    }

                    if ($explode && !is_array($post_meta)) {
                        $post_meta = explode($explode, $post_meta);
                    }

                    if (is_array($post_meta)) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_order_array', $post_meta, $atts);
                    }

                    if (is_string($post_meta) && $path !== false && is_object(json_decode($post_meta))) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_order_json', json_decode($post_meta, true), $atts);
                    }

                    if ((is_array($post_meta) || is_object($post_meta)) && $path !== false) {
                        $post_meta = $this->helper->load('shortcode')->apply_path_attribute($post_meta, $path);
                    }

                    if ($attachment_url || $attachment_image_url) {
                        if (!is_array($post_meta)) {
                            if (strpos($post_meta, ',') !== false) {
                                $post_meta = explode(',', $post_meta);
                                if ($implode === false) {
                                    $implode = ',';
                                }
                            }
                        }
                        if ($attachment_url) {
                            $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_url', $size);
                        } else {
                            $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_image_url', $size);
                        }
                    }

                    if ($wc_price || $wc_price_raw) {
                        if (is_array($post_meta) || is_object($post_meta)) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
                        } else {
                            if (isset($atts['currency'])) {
                                $post_meta = wc_price($post_meta, $atts['currency']);
                            } else {
                                if (!$order) {
                                    $order = wc_get_order($id);
                                }
                                if ($order) {
                                    $post_meta = wc_price($post_meta, $order->get_currency());
                                } else {
                                    $post_meta = wc_price($post_meta);
                                }
                            }
                            if ($wc_price_raw) {
                                $post_meta = html_entity_decode(wp_strip_all_tags($post_meta));
                            }
                        }
                    }

                    /*
                     * Checkout Field Editor (Checkout Manager) for WooCommerce
                     * https://wordpress.org/plugins/woo-checkout-field-editor-pro/
                     */
                    if ($checkout_field_editor) {
                        $checkout_field = false;

                        if (class_exists('WCFE_Checkout_Fields_Utils') && class_exists('THWCFE_Utils_Section') && class_exists('THWCFE_Utils')) {
                            $checkout_field_editor_sections = WCFE_Checkout_Fields_Utils::get_checkout_sections();
                            foreach ($checkout_field_editor_sections as $checkout_field_editor_section) {
                                $checkout_field_editor_fields = THWCFE_Utils_Section::get_fields($checkout_field_editor_section);
                                if (isset($checkout_field_editor_fields[$key])) {
                                    $post_meta = THWCFE_Utils::get_option_text_from_value($checkout_field_editor_fields[$key], $post_meta);
                                    $checkout_field = true;
                                    break;
                                }
                            }
                        }

                        if (!$checkout_field && class_exists('THWCFD_Utils')) {
                            $checkout_field_editor_fields = array_merge(THWCFD_Utils::get_fields('billing'), THWCFD_Utils::get_fields('shipping'), THWCFD_Utils::get_fields('additional'));
                            if (isset($checkout_field_editor_fields[$key])) {
                                $post_meta = THWCFD_Utils::get_option_text($checkout_field_editor_fields[$key], $post_meta);
                                $checkout_field = true;
                            }
                        }

                        if (!$checkout_field && class_exists('THWCFD_Utils_Block') && class_exists('THWCFE_Utils_Section') && class_exists('THWCFE_Utils_Section')) {
                            $checkout_field_editor_sections = THWCFD_Utils_Block::get_block_checkout_sections();
                            foreach ($checkout_field_editor_sections as $checkout_field_editor_section) {
                                $checkout_field_editor_fields = THWCFE_Utils_Section::get_fields($checkout_field_editor_section);
                                if (isset($checkout_field_editor_fields[$key])) {
                                    $post_meta = THWCFE_Utils::get_option_text_from_value($checkout_field_editor_fields[$key], $post_meta);
                                    $checkout_field = true;
                                    break;
                                }
                            }
                        }

                        if (!$checkout_field && class_exists('THWCFD_Utils_Block') && class_exists('THWCFD_Utils_Section') && class_exists('THWCFD_Utils')) {
                            $checkout_field_editor_sections = THWCFD_Utils_Block::get_block_checkout_sections();
                            foreach ($checkout_field_editor_sections as $checkout_field_editor_section) {
                                $checkout_field_editor_fields = [];
                                $checkout_field_editor_fields = THWCFD_Utils_Section::get_fieldset($checkout_field_editor_section);
                                if (isset($checkout_field_editor_fields[$key])) {
                                    $post_meta = THWCFD_Utils::get_option_text($checkout_field_editor_fields[$key], $post_meta);
                                    $checkout_field = true;
                                    break;
                                }
                            }
                        }
                    }

                    if (apply_filters('e2pdf_raw_output', false)) {
                        $response = $post_meta;
                    } else {
                        if (is_array($post_meta)) {
                            if ($implode !== false) {
                                if (!$this->helper->is_multidimensional($post_meta)) {
                                    foreach ($post_meta as $post_meta_key => $post_meta_value) {
                                        $post_meta[$post_meta_key] = $this->helper->load('translator')->translate($post_meta_value);
                                    }
                                    $response = implode($implode, $post_meta);
                                } else {
                                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                                    $response = serialize($post_meta);
                                }
                            } else {
                                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                                $response = serialize($post_meta);
                            }
                        } elseif (is_object($post_meta)) {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                            $response = serialize($post_meta);
                        } else {
                            $response = $post_meta;
                        }
                    }
                }
            }
        }

        if (apply_filters('e2pdf_raw_output', false)) {
            return apply_filters('e2pdf_model_shortcode_e2pdf_wc_order_raw', $response, $atts, $value);
        } else {
            $response = $this->helper->load('translator')->translate($response, 'partial');
            if (!apply_filters('e2pdf_pdf_fill', false)) {
                $response = $this->sanitize_html($response);
            }
            return apply_filters('e2pdf_model_shortcode_e2pdf_wc_order_response', $response, $atts, $value);
        }
    }

    // e2pdf-foreach
    public function e2pdf_foreach($atts = [], $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_foreach', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $response = [];
        $implode = isset($atts['implode']) ? $atts['implode'] : '';
        if (isset($atts['shortcode'])) {
            $foreach_shortcode = str_replace('-', '_', $atts['shortcode']);
            if (method_exists($this, $foreach_shortcode)) {
                add_filter('e2pdf_raw_output', array($this->helper, '__return_true'), 999);
                $data = $this->$foreach_shortcode($atts, '');
                remove_filter('e2pdf_raw_output', array($this->helper, '__return_true'), 999);
                if ($data && (is_string($data) || is_numeric($data))) {
                    $data = [$data];
                }
                if (is_array($data) && count($data) > 0) {
                    $index = 0;
                    foreach ($data as $data_key => $data_value) {
                        $sub_value = $this->helper->load('foreach')->do_shortcode($value, $data_key, $data_value, $index);
                        $response[] = $this->helper->load('foreach')->add($atts, $sub_value, 1);
                        $index++;
                    }
                }
            }
        }

        $response = implode($implode, $response);
        if (!apply_filters('e2pdf_pdf_fill', false)) {
            $response = $this->sanitize_html($response);
        }
        return $response;
    }

    // e2pdf-wc-cart
    public function e2pdf_wc_cart($atts = [], $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_wc_cart', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $post_meta = false;
        $response = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_wc_cart_atts', $atts);

        $id = isset($atts['id']) ? $atts['id'] : false;
        $key = isset($atts['key']) ? $atts['key'] : false;
        $path = isset($atts['path']) ? $atts['path'] : false;
        $names = isset($atts['names']) && $atts['names'] == 'true' ? true : false;
        $explode = isset($atts['explode']) ? $atts['explode'] : false;
        $implode = isset($atts['implode']) ? $atts['implode'] : false;
        $attachment_url = isset($atts['attachment_url']) && $atts['attachment_url'] == 'true' ? true : false;
        $attachment_image_url = isset($atts['attachment_image_url']) && $atts['attachment_image_url'] == 'true' ? true : false;
        $size = isset($atts['size']) ? $atts['size'] : 'thumbnail';
        $meta = isset($atts['meta']) && $atts['meta'] == 'true' ? true : false;
        $terms = isset($atts['terms']) && $atts['terms'] == 'true' ? true : false;
        $output = isset($atts['output']) ? $atts['output'] : false;
        $wc_price = isset($atts['wc_price']) && $atts['wc_price'] == 'true' ? true : false;
        $wc_price_raw = isset($atts['wc_price_raw']) && $atts['wc_price_raw'] == 'true' ? true : false;

        $data_fields = apply_filters(
                'e2pdf_model_shortcode_wc_cart_data_fields',
                [
                    'id',
                    'post_author',
                    'post_author_id',
                    'post_date',
                    'post_date_gmt',
                    'post_content',
                    'post_title',
                    'post_excerpt',
                    'post_status',
                    'permalink',
                    'post_permalink',
                    'get_post_permalink',
                    'comment_status',
                    'ping_status',
                    'post_password',
                    'post_name',
                    'to_ping',
                    'pinged',
                    'post_modified',
                    'post_modified_gmt',
                    'post_content_filtered',
                    'post_parent',
                    'guid',
                    'menu_order',
                    'post_type',
                    'post_mime_type',
                    'comment_count',
                    'filter',
                    'post_thumbnail',
                    'get_the_post_thumbnail',
                    'get_the_post_thumbnail_url',
                    'response_hook',
                ]
        );

        $cart_fields = apply_filters(
                'e2pdf_model_shortcode_wc_cart_cart_fields',
                [
                    'cart',
                    'get_cart',
                    'get_applied_coupons',
                    'get_cart_total',
                    'get_formatted_cart_totals',
                    'get_cart_subtotal',
                    'get_cart_tax',
                    'get_cart_hash',
                    'get_cart_contents_total',
                    'get_cart_contents_tax',
                    'get_cart_contents_taxes',
                    'get_cart_contents_count',
                    'get_cart_contents_weight',
                    'get_cart_item_quantities',
                    'get_cart_item_tax_classes',
                    'get_cart_item_tax_classes_for_shipping',
                    'get_cart_shipping_total',
                    'get_coupon_discount_totals',
                    'get_coupon_discount_tax_totals',
                    'get_totals',
                    'get_total',
                    'get_total_tax',
                    'get_total_ex_tax',
                    'get_total_discount',
                    'get_subtotal',
                    'get_subtotal_tax',
                    'get_discount_total',
                    'get_discount_tax',
                    'get_shipping_total',
                    'get_shipping_tax',
                    'get_shipping_taxes',
                    'get_fees',
                    'get_fee_total',
                    'get_fee_tax',
                    'get_fee_taxes',
                    'get_displayed_subtotal',
                    'get_tax_price_display_mode',
                    'get_taxes',
                    'get_taxes_total',
                    'get_shipping_method_title',
                    'get_payment_method_title',
                ]
        );

        if ($id && $key) {
            $wp_post = get_post($id);
            if ($wp_post) {
                if (in_array($key, $data_fields, true) && !$meta && !$terms) {
                    if ($key == 'post_author') {
                        $post_meta = isset($wp_post->post_author) && $wp_post->post_author ? get_userdata($wp_post->post_author)->user_nicename : '';
                    } elseif ($key == 'post_author_id') {
                        $post_meta = isset($wp_post->post_author) && $wp_post->post_author ? $wp_post->post_author : '0';
                    } elseif ($key == 'id' && isset($wp_post->ID)) {
                        $post_meta = $wp_post->ID;
                    } elseif (($key == 'post_thumbnail' || $key == 'get_the_post_thumbnail_url') && isset($wp_post->ID)) {
                        $post_meta = get_the_post_thumbnail_url($wp_post->ID, $size);
                    } elseif ($key == 'get_the_post_thumbnail' && isset($wp_post->ID)) {
                        $post_meta = get_the_post_thumbnail($wp_post->ID, $size);
                    } elseif ($key == 'post_content' && isset($wp_post->post_content)) {
                        $content = $wp_post->post_content;
                        if (false !== strpos($content, '[')) {
                            $shortcode_tags = [
                                'e2pdf-exclude',
                                'e2pdf-save',
                                'e2pdf-view',
                            ];
                            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
                            $tagnames = array_intersect($shortcode_tags, $matches[1]);
                            if (!empty($tagnames)) {
                                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $content, $shortcodes);
                                foreach ($shortcodes[0] as $key => $shortcode_value) {
                                    $content = str_replace($shortcode_value, '', $content);
                                }
                            }
                        }

                        if ($output) {
                            global $post;
                            $tmp_post = $post;
                            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                            $post = $wp_post;
                            if ($output == 'backend') {
                                if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin')) {
                                    \Elementor\Plugin::instance()->frontend->remove_content_filter();
                                }
                            } elseif ($output == 'frontend') {
                                if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin')) {
                                    \Elementor\Plugin::instance()->frontend->add_content_filter();
                                }
                            }
                        }

                        if (defined('ET_BUILDER_DIR') && 'on' === get_post_meta($id, '_et_pb_use_builder', true) && function_exists('et_builder_init_global_settings') && function_exists('et_builder_add_main_elements')) {
                            require_once ET_BUILDER_DIR . 'class-et-builder-element.php';
                            require_once ET_BUILDER_DIR . 'functions.php';
                            require_once ET_BUILDER_DIR . 'ab-testing.php';
                            require_once ET_BUILDER_DIR . 'class-et-global-settings.php';
                            et_builder_add_main_elements();
                        }

                        if (class_exists('WPBMap') && method_exists('WPBMap', 'addAllMappedShortcodes')) {
                            WPBMap::addAllMappedShortcodes();
                        }

                        $content = apply_filters('the_content', $content, $id);
                        $content = str_replace('</p>', "</p>\r\n", $content);
                        $post_meta = $content;

                        if ($output) {
                            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                            $post = $tmp_post;
                        }
                    } elseif ($key == 'permalink') {
                        $leavename = isset($atts['leavename']) && $atts['leavename'] == 'true' ? true : false;
                        $post_meta = get_permalink($id, $leavename);
                        $post_meta = $this->helper->load('translator')->translate_url($post_meta);
                    } elseif ($key == 'get_post_permalink' || $key == 'post_permalink') {
                        $leavename = isset($atts['leavename']) && $atts['leavename'] == 'true' ? true : false;
                        $post_meta = get_post_permalink($id, $leavename);
                        $post_meta = $this->helper->load('translator')->translate_url($post_meta);
                    } elseif ($key == 'response_hook') {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_cart_response_hook', '', $id, $atts, $wp_post);
                    } elseif (isset($wp_post->$key)) {
                        $post_meta = $wp_post->$key;
                    }
                } elseif (in_array($key, $cart_fields, true) && !$meta && !$terms) {
                    if (function_exists('WC') && isset(WC()->cart) && WC()->cart && is_object(WC()->cart)) {
                        WC()->cart->calculate_totals();
                        if ($key == 'cart') {

                            $items = WC()->cart->get_cart();
                            $content = '';

                            if ($items) {
                                $show_products = isset($atts['show_products']) && $atts['show_products'] == 'false' ? false : true;
                                $show_image = isset($atts['show_image']) && $atts['show_image'] == 'false' ? false : true;
                                $show_sku = isset($atts['show_sku']) && $atts['show_sku'] == 'false' ? false : true;
                                $show_name = isset($atts['show_name']) && $atts['show_name'] == 'false' ? false : true;
                                $show_quantity = isset($atts['show_quantity']) && $atts['show_quantity'] == 'false' ? false : true;
                                $show_price = isset($atts['show_price']) && $atts['show_price'] == 'false' ? false : true;
                                $show_subtotal = isset($atts['show_subtotal']) && $atts['show_subtotal'] == 'false' ? false : true;
                                $show_meta = isset($atts['show_meta']) && $atts['show_meta'] == 'false' ? false : true;

                                $show_totals = isset($atts['show_totals']) && $atts['show_totals'] == 'false' ? false : true;
                                $show_totals_subtotal = isset($atts['show_totals_subtotal']) && $atts['show_totals_subtotal'] == 'false' ? false : true;
                                $show_totals_coupons = isset($atts['show_totals_coupons']) && $atts['show_totals_coupons'] == 'false' ? false : true;
                                $show_totals_shipping = isset($atts['show_totals_shipping']) && $atts['show_totals_shipping'] == 'false' ? false : true;
                                $show_totals_shipping_destination = isset($atts['show_totals_shipping_destination']) && $atts['show_totals_shipping_destination'] == 'false' ? false : true;
                                $show_totals_shipping_package = isset($atts['show_totals_shipping_package']) && $atts['show_totals_shipping_package'] == 'false' ? false : true;
                                $show_totals_fees = isset($atts['show_totals_fees']) && $atts['show_totals_fees'] == 'false' ? false : true;
                                $show_totals_taxes = isset($atts['show_totals_taxes']) && $atts['show_totals_taxes'] == 'false' ? false : true;
                                $show_totals_total = isset($atts['show_totals_total']) && $atts['show_totals_total'] == 'false' ? false : true;

                                if (isset($atts['size'])) {
                                    $size = $atts['size'];
                                } elseif (isset($atts['image_size'])) {
                                    $size = $atts['image_size'];
                                } else {
                                    $size = '32x32';
                                }

                                if (false !== strpos($size, 'x')) {
                                    $image_size = explode('x', $size);
                                    if (isset($image_size['0']) && isset($image_size['1'])) {
                                        $image_width = absint($image_size['0']);
                                        $image_height = absint($image_size['1']);
                                        if ($image_width && $image_height) {
                                            $size = [
                                                $image_width, $image_height,
                                            ];
                                        }
                                    }
                                }

                                $plain_text = isset($atts['plain_text']) ? $atts['plain_text'] : false;
                                if ($show_products) {

                                    $content .= "<table border='1' split='true' bordercolor='#eeeeee' cellpadding='5' class='e2pdf-wc-cart-products'>";
                                    $content .= "<tr bgcolor='#eeeeee' class='e2pdf-wc-cart-products-header'>";
                                    if ($show_image) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-image'>" . apply_filters('e2pdf_model_shortcode_wc_cart_cart_header_image_text', '', $atts, $value) . '</td>';
                                    }
                                    if ($show_name) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-name'>" . apply_filters('e2pdf_model_shortcode_wc_cart_cart_header_name_text', __('Product', 'woocommerce'), $atts, $value) . '</td>';
                                    }
                                    if ($show_sku) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-sku'>" . apply_filters('e2pdf_model_shortcode_wc_cart_cart_header_sku_text', __('SKU', 'woocommerce'), $atts, $value) . '</td>';
                                    }
                                    if ($show_quantity) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-quantity'>" . apply_filters('e2pdf_model_shortcode_wc_cart_cart_header_quantity_text', __('Quantity', 'woocommerce'), $atts, $value) . '</td>';
                                    }
                                    if ($show_price) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-price'>" . apply_filters('e2pdf_model_shortcode_wc_cart_cart_header_pricey_text', __('Price', 'woocommerce'), $atts, $value) . '</td>';
                                    }
                                    if ($show_subtotal) {
                                        $content .= "<td class='e2pdf-wc-cart-products-header-subtotal'>" . apply_filters('e2pdf_model_shortcode_wc_cart_cart_header_pricey_text', __('Subtotal', 'woocommerce'), $atts, $value) . '</td>';
                                    }
                                    $content .= '</tr>';

                                    $item_index = 0;
                                    foreach ($items as $item_id => $item) {
                                        $product = apply_filters('woocommerce_cart_item_product', $item['data'], $item, $item_id);
                                        if ($product && $product->exists() && $item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $item, $item_id)) {
                                            $sku = '';
                                            $purchase_note = '';
                                            $image = '';

                                            if (is_object($product)) {
                                                $sku = $product->get_sku();
                                                $purchase_note = $product->get_purchase_note();
                                                $image = $product->get_image($size);
                                            }

                                            $even_odd = $item_index % 2 ? 'e2pdf-wc-cart-product-odd' : 'e2pdf-wc-cart-product-even';
                                            $content .= "<tr class='e2pdf-wc-cart-product " . $even_odd . "'>";
                                            if ($show_image) {
                                                $content .= "<td align='center' class='e2pdf-wc-cart-product-image'>" . apply_filters('woocommerce_cart_item_thumbnail', $image, $item, $item_id) . '</td>';
                                            }
                                            if ($show_name) {
                                                $content .= "<td class='e2pdf-wc-cart-product-name'>";
                                                $product_permalink = apply_filters('woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink($item) : '', $item, $item_id);
                                                if (!$product_permalink) {
                                                    $content .= wp_kses_post(apply_filters('woocommerce_cart_item_name', $product->get_name(), $item, $item_id) . '&nbsp;');
                                                } else {
                                                    $content .= wp_kses_post(apply_filters('woocommerce_cart_item_name', sprintf('<a target="_blank" href="%s">%s</a>', esc_url($product_permalink), $product->get_name()), $item, $item_id));
                                                }

                                                if ($show_meta) {
                                                    $wc_display_item_meta = wc_get_formatted_cart_item_data($item, true);

                                                    if ($wc_display_item_meta) {
                                                        $content .= "<div size='8px' class='e2pdf-wc-cart-product-meta'>" . nl2br($wc_display_item_meta) . '</div>';
                                                    }
                                                }

                                                $content .= '</td>';
                                            }
                                            if ($show_sku) {
                                                $content .= "<td class='e2pdf-wc-cart-product-sku'>" . $sku . '</td>';
                                            }
                                            if ($show_quantity) {
                                                $content .= "<td class='e2pdf-wc-cart-product-quantity'>" . $item['quantity'] . '</td>';
                                            }
                                            if ($show_price) {
                                                $content .= "<td class='e2pdf-wc-cart-product-price'>" . apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($product), $item, $item_id) . '</td>';
                                            }
                                            if ($show_subtotal) {
                                                $content .= "<td class='e2pdf-wc-cart-product-subtotal'>" . apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($product, $item['quantity']), $item, $item_id) . '</td>';
                                            }
                                            $content .= '</tr>';
                                            $item_index++;
                                        }
                                    }
                                    $content .= '</table>';
                                }

                                $item_totals = [];
                                if ($show_totals) {
                                    /* Total Subtotal */
                                    if ($show_totals_subtotal) {
                                        $item_totals['subtotal'] = [
                                            'label' => __('Subtotal', 'woocommerce'),
                                            'value' => WC()->cart->get_cart_subtotal(),
                                        ];
                                    }

                                    /* Total Coupons */
                                    if ($show_totals_coupons) {
                                        $index_id = 0;
                                        foreach (WC()->cart->get_coupons() as $code => $coupon) {
                                            if (is_string($coupon)) {
                                                $coupon = new WC_Coupon($coupon);
                                            }

                                            $discount_amount_html = '';
                                            $amount = WC()->cart->get_coupon_discount_amount($coupon->get_code(), WC()->cart->display_cart_ex_tax);
                                            $discount_amount_html = '-' . wc_price($amount);

                                            if ($coupon->get_free_shipping() && empty($amount)) {
                                                $discount_amount_html = __('Free shipping coupon', 'woocommerce');
                                            }

                                            $item_totals['coupon_' . $index_id] = [
                                                'label' => wc_cart_totals_coupon_label($coupon, false),
                                                'value' => $discount_amount_html,
                                            ];
                                            $index_id++;
                                        }
                                    }

                                    /* Total Shipping */
                                    if ($show_totals_shipping) {
                                        if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) {

                                            $packages = WC()->shipping()->get_packages();
                                            $first = true;

                                            $index_id = 0;
                                            foreach ($packages as $i => $package) {
                                                $chosen_method = isset(WC()->session->chosen_shipping_methods[$i]) ? WC()->session->chosen_shipping_methods[$i] : '';
                                                if ($chosen_method) {
                                                    $product_names = [];
                                                    if (count($packages) > 1) {
                                                        foreach ($package['contents'] as $item_id => $values) {
                                                            $product_names[$item_id] = $values['data']->get_name() . ' &times;' . $values['quantity'];
                                                        }
                                                        $product_names = apply_filters('woocommerce_shipping_package_details_array', $product_names, $package);
                                                    }

                                                    $available_methods = $package['rates'];
                                                    $show_package_details = count($packages) > 1;
                                                    $package_details = implode(', ', $product_names);
                                                    /* translators: %d: shipping package number */
                                                    $package_name = apply_filters('woocommerce_shipping_package_name', (($i + 1) > 1) ? sprintf(_x('Shipping %d', 'shipping packages', 'woocommerce'), ($i + 1)) : _x('Shipping', 'shipping packages', 'woocommerce'), $i, $package);
                                                    $formatted_destination = WC()->countries->get_formatted_address($package['destination'], ', ');

                                                    $item_totals['shipping_' . $index_id] = [
                                                        'label' => wp_kses_post($package_name),
                                                        'value' => '',
                                                    ];

                                                    if ($available_methods) {
                                                        foreach ($available_methods as $method) {
                                                            if ($method->get_id() == $chosen_method) {
                                                                $item_totals['shipping_' . $index_id]['value'] .= '<div>' . wc_cart_totals_shipping_method_label($method) . '</div>';
                                                            }
                                                        }
                                                    }

                                                    if ($show_totals_shipping_destination) {
                                                        if ($formatted_destination) {
                                                            /* translators: %s: location */
                                                            $item_totals['shipping_' . $index_id]['value'] .= "<div size='8px' class='e2pdf-wc-cart-total-shipping-destination'>" . sprintf(esc_html__('Shipping to %s.', 'woocommerce') . ' ', esc_html($formatted_destination)) . '</div>';
                                                        } else {
                                                            $item_totals['shipping_' . $index_id]['value'] .= "<div size='8px' class='e2pdf-wc-cart-total-shipping-destination'>" . wp_kses_post(apply_filters('woocommerce_shipping_estimate_html', __('Shipping options will be updated during checkout.', 'woocommerce'))) . '</div>';
                                                        }
                                                    }

                                                    if ($show_totals_shipping_package) {
                                                        if ($show_package_details) {
                                                            $item_totals['shipping_' . $index_id]['value'] .= "<div size='8px' class='e2pdf-wc-cart-total-shipping-package'>" . esc_html($package_details) . '</div>';
                                                        }
                                                    }

                                                    $index_id++;
                                                    $first = false;
                                                }
                                            }
                                        } elseif (WC()->cart->needs_shipping() && 'yes' === get_option('woocommerce_enable_shipping_calc')) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElseif
                                        }
                                    }

                                    /* Total Fees */
                                    if ($show_totals_fees) {
                                        $index_id = 0;
                                        foreach (WC()->cart->get_fees() as $fee) {
                                            $cart_totals_fee_html = WC()->cart->display_prices_including_tax() ? wc_price($fee->total + $fee->tax) : wc_price($fee->total);
                                            $item_totals['fee_' . $index_id] = [
                                                'label' => esc_html($fee->name),
                                                'value' => apply_filters('woocommerce_cart_totals_fee_html', $cart_totals_fee_html, $fee),
                                            ];
                                            $index_id++;
                                        }
                                    }

                                    /* Total Taxes */
                                    if ($show_totals_taxes) {
                                        if (wc_tax_enabled() && !WC()->cart->display_prices_including_tax()) {
                                            $taxable_address = WC()->customer->get_taxable_address();
                                            $estimated_text = '';

                                            if (WC()->customer->is_customer_outside_base() && !WC()->customer->has_calculated_shipping()) {
                                                /* translators: %s: location */
                                                $estimated_text = sprintf(' <small>' . esc_html__('(estimated for %s)', 'woocommerce') . '</small>', WC()->countries->estimated_for_prefix($taxable_address[0]) . WC()->countries->countries[$taxable_address[0]]);
                                            }

                                            if ('itemized' === get_option('woocommerce_tax_total_display')) {
                                                $index_id = 0;
                                                foreach (WC()->cart->get_tax_totals() as $code => $tax) {
                                                    $item_totals['tax_' . $index_id] = [
                                                        'label' => esc_html($tax->label) . $estimated_text,
                                                        'value' => wp_kses_post($tax->formatted_amount),
                                                    ];
                                                    $index_id++;
                                                }
                                            } else {
                                                $item_totals['tax_or_vat'] = [
                                                    'label' => esc_html(WC()->countries->tax_or_vat()) . $estimated_text,
                                                    'value' => apply_filters('woocommerce_cart_totals_taxes_total_html', wc_price(WC()->cart->get_taxes_total())),
                                                ];
                                            }
                                        }
                                    }

                                    /* Total Total */
                                    if ($show_totals_total) {
                                        $total = WC()->cart->get_total();
                                        if (wc_tax_enabled() && WC()->cart->display_prices_including_tax()) {
                                            $tax_string_array = [];
                                            $cart_tax_totals = WC()->cart->get_tax_totals();

                                            if (get_option('woocommerce_tax_total_display') === 'itemized') {
                                                foreach ($cart_tax_totals as $code => $tax) {
                                                    $tax_string_array[] = sprintf('%s %s', $tax->formatted_amount, $tax->label);
                                                }
                                            } elseif (!empty($cart_tax_totals)) {
                                                $tax_string_array[] = sprintf('%s %s', wc_price(WC()->cart->get_taxes_total(true, true)), WC()->countries->tax_or_vat());
                                            }

                                            if (!empty($tax_string_array)) {
                                                $taxable_address = WC()->customer->get_taxable_address();
                                                /* translators: %s: location */
                                                $estimated_text = WC()->customer->is_customer_outside_base() && !WC()->customer->has_calculated_shipping() ? sprintf(' ' . __('estimated for %s', 'woocommerce'), WC()->countries->estimated_for_prefix($taxable_address[0]) . WC()->countries->countries[$taxable_address[0]]) : '';
                                                $total .= '<small class="includes_tax"> ('
                                                        . esc_html__('includes', 'woocommerce')
                                                        . ' '
                                                        . wp_kses_post(implode(', ', $tax_string_array))
                                                        . esc_html($estimated_text)
                                                        . ')</small>';
                                            }
                                        }

                                        $item_totals['total'] = [
                                            'label' => __('Total', 'woocommerce'),
                                            'value' => apply_filters('woocommerce_cart_totals_order_total_html', $total),
                                        ];
                                    }

                                    $item_totals = apply_filters('e2pdf_model_shortcode_wc_cart_item_totals', $item_totals, $atts, $value);

                                    if (!empty($item_totals)) {
                                        $total_index = 0;
                                        $content .= "<table split='true' cellpadding='5' class='e2pdf-wc-cart-totals'>";
                                        foreach ($item_totals as $total_key => $total) {
                                            $even_odd = $total_index % 2 ? 'e2pdf-wc-cart-total-odd' : 'e2pdf-wc-cart-total-even';
                                            $content .= "<tr class='e2pdf-wc-cart-total e2pdf-wc-cart-total-" . $total_key . ' ' . $even_odd . "'>";
                                            $content .= "<td valign='top' width='60%' align='right' class='e2pdf-wc-cart-total-label'>" . $total['label'] . ':</td>';
                                            $content .= "<td valign='top' align='right' class='e2pdf-wc-cart-total-value'>" . $total['value'] . '</td>';
                                            $content .= '</tr>';
                                            $total_index++;
                                        }
                                        $content .= '</table>';
                                    }
                                }
                            }
                            $post_meta = $content;
                        } elseif ($key == 'get_shipping_method_title') {
                            $packages = WC()->shipping()->get_packages();
                            foreach ($packages as $i => $package) {
                                $chosen_method = isset(WC()->session->chosen_shipping_methods[$i]) ? WC()->session->chosen_shipping_methods[$i] : '';
                                if ($chosen_method) {
                                    $available_methods = $package['rates'];
                                    if ($available_methods) {
                                        foreach ($available_methods as $method) {
                                            if ($method->get_id() == $chosen_method) {
                                                $post_meta = wc_cart_totals_shipping_method_label($method);
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        } elseif ($key == 'get_payment_method_title') {
                            $chosen_method = isset(WC()->session->chosen_payment_method) ? WC()->session->chosen_payment_method : '';
                            $packages = WC()->payment_gateways->get_available_payment_gateways();
                            foreach ($packages as $i => $package) {
                                if ($i == $chosen_method) {
                                    $post_meta = $package->get_title();
                                    break;
                                }
                            }
                        } elseif ($key == 'get_formatted_cart_totals') {

                            $include = [
                                'subtotal',
                                'coupons',
                                'shipping',
                                'shipping_destination',
                                'shipping_package',
                                'fees',
                                'taxes',
                                'total',
                            ];

                            if (isset($atts['include'])) {
                                $include = explode(',', $atts['include']);
                            }

                            $exclude = [];
                            if (isset($atts['exclude'])) {
                                $exclude = explode(',', $atts['exclude']);
                            }
                            $include = array_diff($include, $exclude);

                            /* Total Subtotal */
                            if (in_array('subtotal', $include, true)) {
                                $item_totals['subtotal'] = [
                                    'label' => __('Subtotal', 'woocommerce'),
                                    'value' => WC()->cart->get_cart_subtotal(),
                                ];
                            }

                            /* Total Coupons */
                            if (in_array('coupons', $include, true)) {
                                $index_id = 0;
                                foreach (WC()->cart->get_coupons() as $code => $coupon) {
                                    if (is_string($coupon)) {
                                        $coupon = new WC_Coupon($coupon);
                                    }

                                    $discount_amount_html = '';
                                    $amount = WC()->cart->get_coupon_discount_amount($coupon->get_code(), WC()->cart->display_cart_ex_tax);
                                    $discount_amount_html = '-' . wc_price($amount);

                                    if ($coupon->get_free_shipping() && empty($amount)) {
                                        $discount_amount_html = __('Free shipping coupon', 'woocommerce');
                                    }

                                    $item_totals['coupon_' . $index_id] = [
                                        'label' => wc_cart_totals_coupon_label($coupon, false),
                                        'value' => $discount_amount_html,
                                    ];
                                    $index_id++;
                                }
                            }

                            /* Total Shipping */
                            if (in_array('shipping', $include, true)) {
                                if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) {

                                    $packages = WC()->shipping()->get_packages();
                                    $first = true;

                                    $index_id = 0;
                                    foreach ($packages as $i => $package) {
                                        $chosen_method = isset(WC()->session->chosen_shipping_methods[$i]) ? WC()->session->chosen_shipping_methods[$i] : '';
                                        if ($chosen_method) {
                                            $product_names = [];
                                            if (count($packages) > 1) {
                                                foreach ($package['contents'] as $item_id => $values) {
                                                    $product_names[$item_id] = $values['data']->get_name() . ' &times;' . $values['quantity'];
                                                }
                                                $product_names = apply_filters('woocommerce_shipping_package_details_array', $product_names, $package);
                                            }

                                            $available_methods = $package['rates'];
                                            $show_package_details = count($packages) > 1;
                                            $package_details = implode(', ', $product_names);
                                            /* translators: %d: shipping package number */
                                            $package_name = apply_filters('woocommerce_shipping_package_name', (($i + 1) > 1) ? sprintf(_x('Shipping %d', 'shipping packages', 'woocommerce'), ($i + 1)) : _x('Shipping', 'shipping packages', 'woocommerce'), $i, $package);
                                            $formatted_destination = WC()->countries->get_formatted_address($package['destination'], ', ');

                                            $item_totals['shipping_' . $index_id] = [
                                                'label' => wp_kses_post($package_name),
                                                'value' => '',
                                            ];

                                            if ($available_methods) {
                                                foreach ($available_methods as $method) {
                                                    if ($method->get_id() == $chosen_method) {
                                                        $item_totals['shipping_' . $index_id]['value'] .= '<div>' . wc_cart_totals_shipping_method_label($method) . '</div>';
                                                    }
                                                }
                                            }

                                            if (in_array('shipping_destination', $include, true)) {
                                                if ($formatted_destination) {
                                                    /* translators: %s location */
                                                    $item_totals['shipping_' . $index_id]['value'] .= "<div size='8px' class='e2pdf-wc-cart-total-shipping-destination'>" . sprintf(esc_html__('Shipping to %s.', 'woocommerce') . ' ', esc_html($formatted_destination)) . '</div>';
                                                } else {
                                                    $item_totals['shipping_' . $index_id]['value'] .= "<div size='8px' class='e2pdf-wc-cart-total-shipping-destination'>" . wp_kses_post(apply_filters('woocommerce_shipping_estimate_html', __('Shipping options will be updated during checkout.', 'woocommerce'))) . '</div>';
                                                }
                                            }

                                            if (in_array('shipping_package', $include, true)) {
                                                if ($show_package_details) {
                                                    $item_totals['shipping_' . $index_id]['value'] .= "<div size='8px' class='e2pdf-wc-cart-total-shipping-package'>" . esc_html($package_details) . '</div>';
                                                }
                                            }

                                            $index_id++;
                                            $first = false;
                                        }
                                    }
                                } elseif (WC()->cart->needs_shipping() && 'yes' === get_option('woocommerce_enable_shipping_calc')) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElseif
                                }
                            }

                            /* Total Fees */
                            if (in_array('fees', $include, true)) {
                                $index_id = 0;
                                foreach (WC()->cart->get_fees() as $fee) {
                                    $cart_totals_fee_html = WC()->cart->display_prices_including_tax() ? wc_price($fee->total + $fee->tax) : wc_price($fee->total);
                                    $item_totals['fee_' . $index_id] = [
                                        'label' => esc_html($fee->name),
                                        'value' => apply_filters('woocommerce_cart_totals_fee_html', $cart_totals_fee_html, $fee),
                                    ];
                                    $index_id++;
                                }
                            }

                            /* Total Taxes */
                            if (in_array('taxes', $include, true)) {
                                if (wc_tax_enabled() && !WC()->cart->display_prices_including_tax()) {
                                    $taxable_address = WC()->customer->get_taxable_address();
                                    $estimated_text = '';

                                    if (WC()->customer->is_customer_outside_base() && !WC()->customer->has_calculated_shipping()) {
                                        /* translators: %s: location */
                                        $estimated_text = sprintf(' <small>' . esc_html__('(estimated for %s)', 'woocommerce') . '</small>', WC()->countries->estimated_for_prefix($taxable_address[0]) . WC()->countries->countries[$taxable_address[0]]);
                                    }

                                    if ('itemized' === get_option('woocommerce_tax_total_display')) {
                                        $index_id = 0;
                                        foreach (WC()->cart->get_tax_totals() as $code => $tax) {
                                            $item_totals['tax_' . $index_id] = [
                                                'label' => esc_html($tax->label) . $estimated_text,
                                                'value' => wp_kses_post($tax->formatted_amount),
                                            ];
                                            $index_id++;
                                        }
                                    } else {
                                        $item_totals['tax_or_vat'] = [
                                            'label' => esc_html(WC()->countries->tax_or_vat()) . $estimated_text,
                                            'value' => apply_filters('woocommerce_cart_totals_taxes_total_html', wc_price(WC()->cart->get_taxes_total())),
                                        ];
                                    }
                                }
                            }

                            /*
                             * Total Total
                             */
                            if (in_array('total', $include, true)) {
                                $total = WC()->cart->get_total();
                                if (wc_tax_enabled() && WC()->cart->display_prices_including_tax()) {
                                    $tax_string_array = [];
                                    $cart_tax_totals = WC()->cart->get_tax_totals();

                                    if (get_option('woocommerce_tax_total_display') === 'itemized') {
                                        foreach ($cart_tax_totals as $code => $tax) {
                                            $tax_string_array[] = sprintf('%s %s', $tax->formatted_amount, $tax->label);
                                        }
                                    } elseif (!empty($cart_tax_totals)) {
                                        $tax_string_array[] = sprintf('%s %s', wc_price(WC()->cart->get_taxes_total(true, true)), WC()->countries->tax_or_vat());
                                    }

                                    if (!empty($tax_string_array)) {
                                        $taxable_address = WC()->customer->get_taxable_address();
                                        /* translators: %s: location */
                                        $estimated_text = WC()->customer->is_customer_outside_base() && !WC()->customer->has_calculated_shipping() ? sprintf(' ' . __('estimated for %s', 'woocommerce'), WC()->countries->estimated_for_prefix($taxable_address[0]) . WC()->countries->countries[$taxable_address[0]]) : '';
                                        $total .= '<small class="includes_tax"> ('
                                                . esc_html__('includes', 'woocommerce')
                                                . ' '
                                                . wp_kses_post(implode(', ', $tax_string_array))
                                                . esc_html($estimated_text)
                                                . ')</small>';
                                    }
                                }

                                $item_totals['total'] = [
                                    'label' => __('Total', 'woocommerce'),
                                    'value' => apply_filters('woocommerce_cart_totals_order_total_html', $total),
                                ];
                            }

                            $item_totals = apply_filters('e2pdf_model_shortcode_wc_cart_get_cart_totals', $item_totals, $atts, $value);
                            $post_meta = $item_totals;
                        } else {
                            if (method_exists(WC()->cart, $key)) {
                                if ($key == 'get_cart_subtotal' && $output == 'compound') {
                                    $post_meta = WC()->cart->$key(true);
                                } elseif ($key == 'get_total' && $output == 'edit') {
                                    $post_meta = WC()->cart->$key('edit');
                                } elseif ($key == 'get_taxes_total' && $output) {
                                    $compound = true;
                                    $display = true;
                                    $output_data = explode('|', $output);
                                    if (in_array('nocompound', $output_data, true)) {
                                        $compound = false;
                                    }
                                    if (in_array('nodisplay', $output_data, true)) {
                                        $display = false;
                                    }
                                    $post_meta = WC()->cart->$key($compound, $display);
                                } else {
                                    $post_meta = WC()->cart->$key();
                                }
                            }
                        }
                    }
                } elseif ($terms && $names) {
                    $post_terms = wp_get_post_terms($id, $key, ['fields' => 'names']);
                    if (!is_wp_error($post_terms) && is_array($post_terms)) {
                        foreach ($post_terms as $post_term_key => $post_terms_value) {
                            $post_terms[$post_term_key] = $this->helper->load('translator')->translate($post_terms_value);
                        }
                        if ($implode === false) {
                            $implode = ', ';
                        }
                        $post_meta = implode($implode, $post_terms);
                    }
                } elseif ($terms) {
                    $post_terms = wp_get_post_terms($id, $key);
                    if (!is_wp_error($post_terms)) {
                        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
                        $post_meta = json_decode(json_encode($post_terms), true);
                    }
                } else {
                    $post_meta = get_post_meta($id, $key, true);
                }

                if ($post_meta !== false) {

                    if (is_object($post_meta)) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_cart_object', $post_meta, $atts);
                    }

                    if ($explode && !is_array($post_meta)) {
                        $post_meta = explode($explode, $post_meta);
                    }

                    if (is_array($post_meta)) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_cart_array', $post_meta, $atts);
                    }

                    if (is_string($post_meta) && $path !== false && is_object(json_decode($post_meta))) {
                        $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_cart_json', json_decode($post_meta, true), $atts);
                    }

                    if ((is_array($post_meta) || is_object($post_meta)) && $path !== false) {
                        $post_meta = $this->helper->load('shortcode')->apply_path_attribute($post_meta, $path);
                    }

                    if ($attachment_url || $attachment_image_url) {
                        if (!is_array($post_meta)) {
                            if (strpos($post_meta, ',') !== false) {
                                $post_meta = explode(',', $post_meta);
                                if ($implode === false) {
                                    $implode = ',';
                                }
                            }
                        }
                        if ($attachment_url) {
                            $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_url', $size);
                        } else {
                            $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_image_url', $size);
                        }
                    }

                    if ($wc_price || $wc_price_raw) {
                        if (is_array($post_meta) || is_object($post_meta)) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
                        } else {
                            if (isset($atts['currency'])) {
                                $post_meta = wc_price($post_meta, $atts['currency']);
                            } else {
                                $post_meta = wc_price($post_meta);
                            }
                            if ($wc_price_raw) {
                                $post_meta = html_entity_decode(wp_strip_all_tags($post_meta));
                            }
                        }
                    }

                    if (apply_filters('e2pdf_raw_output', false)) {
                        $response = $post_meta;
                    } else {
                        if (is_array($post_meta)) {
                            if ($implode !== false) {
                                if (!$this->helper->is_multidimensional($post_meta)) {
                                    foreach ($post_meta as $post_meta_key => $post_meta_value) {
                                        $post_meta[$post_meta_key] = $this->helper->load('translator')->translate($post_meta_value);
                                    }
                                    $response = implode($implode, $post_meta);
                                } else {
                                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                                    $response = serialize($post_meta);
                                }
                            } else {
                                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                                $response = serialize($post_meta);
                            }
                        } elseif (is_object($post_meta)) {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                            $response = serialize($post_meta);
                        } else {
                            $response = $post_meta;
                        }
                    }
                }
            }
        }

        if (apply_filters('e2pdf_raw_output', false)) {
            return apply_filters('e2pdf_model_shortcode_e2pdf_wc_cart_raw', $response, $atts, $value);
        } else {
            $response = $this->helper->load('translator')->translate($response, 'partial');
            if (!apply_filters('e2pdf_pdf_fill', false)) {
                $response = $this->sanitize_html($response);
            }
            return apply_filters('e2pdf_model_shortcode_e2pdf_wc_cart_response', $response, $atts, $value);
        }
    }

    // e2pdf-wc-customer
    public function e2pdf_wc_customer($atts = [], $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_wc_customer', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $post_meta = false;
        $response = '';

        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_wc_customer', $atts);

        $key = isset($atts['key']) ? $atts['key'] : false;
        $path = isset($atts['path']) ? $atts['path'] : false;
        $explode = isset($atts['explode']) ? $atts['explode'] : false;
        $implode = isset($atts['implode']) ? $atts['implode'] : false;
        $size = isset($atts['size']) ? $atts['size'] : 'thumbnail';
        $attachment_url = isset($atts['attachment_url']) && $atts['attachment_url'] == 'true' ? true : false;
        $attachment_image_url = isset($atts['attachment_image_url']) && $atts['attachment_image_url'] == 'true' ? true : false;
        $output = isset($atts['output']) ? $atts['output'] : false;

        $customer_fields = apply_filters(
                'e2pdf_model_shortcode_wc_customer_fields',
                [
                    'get_taxable_address',
                    'is_vat_exempt',
                    'get_is_vat_exempt',
                    'has_calculated_shipping',
                    'get_calculated_shipping',
                    'get_avatar_url',
                    'get_username',
                    'get_email',
                    'get_first_name',
                    'get_last_name',
                    'get_display_name',
                    'get_role',
                    'get_date_created',
                    'get_date_modified',
                    'get_billing',
                    'get_billing_first_name',
                    'get_billing_last_name',
                    'get_billing_company',
                    'get_billing_address',
                    'get_billing_address_1',
                    'get_billing_address_2',
                    'get_billing_city',
                    'get_billing_state',
                    'get_billing_postcode',
                    'get_billing_country',
                    'get_billing_email',
                    'get_billing_phone',
                    'get_shipping',
                    'get_shipping_first_name',
                    'get_shipping_last_name',
                    'get_shipping_company',
                    'get_shipping_address',
                    'get_shipping_address_1',
                    'get_shipping_address_2',
                    'get_shipping_city',
                    'get_shipping_state',
                    'get_shipping_postcode',
                    'get_shipping_country',
                    'get_is_paying_customer',
                    'get_formatted_shipping_address',
                    'get_formatted_billing_address',
                ]
        );

        if (in_array($key, $customer_fields, true)) {
            if (function_exists('WC') && isset(WC()->customer) && WC()->customer && is_object(WC()->customer)) {
                if ($key == 'get_formatted_shipping_address') {
                    if (isset(WC()->countries) && isset(WC()->customer)) {
                        $post_meta = WC()->countries->get_formatted_address(WC()->customer->data['shipping']);
                    }
                } elseif ($key == 'get_formatted_billing_address') {
                    if (isset(WC()->countries) && isset(WC()->customer)) {
                        $post_meta = WC()->countries->get_formatted_address(WC()->customer->data['billing']);
                    }
                } elseif ($key == 'get_date_created' || $key == 'get_date_modified') {
                    $format = isset($atts['format']) && $atts['format'] ? $atts['format'] : get_option('date_format') . ', ' . get_option('time_format');
                    $post_meta = wc_format_datetime(WC()->customer->$key(), $format);
                } else {
                    if (method_exists(WC()->customer, $key)) {
                        if ($output == 'edit') {
                            $post_meta = WC()->customer->$key('edit');
                        } else {
                            $post_meta = WC()->customer->$key();
                        }
                    }
                }
            }
        }

        if ($post_meta !== false) {

            if (is_object($post_meta)) {
                $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_customer_object', $post_meta, $atts);
            }

            if ($explode && !is_array($post_meta)) {
                $post_meta = explode($explode, $post_meta);
            }

            if (is_array($post_meta)) {
                $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_customer_array', $post_meta, $atts);
            }

            if (is_string($post_meta) && $path !== false && is_object(json_decode($post_meta))) {
                $post_meta = apply_filters('e2pdf_model_shortcode_e2pdf_wc_customer_json', json_decode($post_meta, true), $atts);
            }

            if ((is_array($post_meta) || is_object($post_meta)) && $path !== false) {
                $post_meta = $this->helper->load('shortcode')->apply_path_attribute($post_meta, $path);
            }

            if ($attachment_url || $attachment_image_url) {
                if (!is_array($post_meta)) {
                    if (strpos($post_meta, ',') !== false) {
                        $post_meta = explode(',', $post_meta);
                        if ($implode === false) {
                            $implode = ',';
                        }
                    }
                }
                if ($attachment_url) {
                    $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_url', $size);
                } else {
                    $post_meta = $this->helper->load('shortcode')->apply_attachment_attribute($post_meta, 'attachment_image_url', $size);
                }
            }

            if (apply_filters('e2pdf_raw_output', false)) {
                $response = $post_meta;
            } else {
                if (is_array($post_meta)) {
                    if ($implode !== false) {
                        if (!$this->helper->is_multidimensional($post_meta)) {
                            foreach ($post_meta as $post_meta_key => $post_meta_value) {
                                $post_meta[$post_meta_key] = $this->helper->load('translator')->translate($post_meta_value);
                            }
                            $response = implode($implode, $post_meta);
                        } else {
                            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                            $response = serialize($post_meta);
                        }
                    } else {
                        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                        $response = serialize($post_meta);
                    }
                } elseif (is_object($post_meta)) {
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                    $response = serialize($post_meta);
                } else {
                    $response = $post_meta;
                }
            }
        }

        if (apply_filters('e2pdf_raw_output', false)) {
            return apply_filters('e2pdf_model_shortcode_e2pdf_wc_customer_raw', $response, $atts, $value);
        } else {
            $response = $this->helper->load('translator')->translate($response, 'partial');
            if (!apply_filters('e2pdf_pdf_fill', false)) {
                $response = $this->sanitize_html($response);
            }
            return apply_filters('e2pdf_model_shortcode_e2pdf_wc_customer_response', $response, $atts, $value);
        }
    }

    public function e2pdf_page_number($atts = [], $value = '') {
        return 'e2pdf-page-number';
    }

    public function e2pdf_page_total($atts = [], $value = '') {
        return 'e2pdf-page-total';
    }

    public function e2pdf_acf_repeater($atts, $value = '') {

        if (!apply_filters('e2pdf_shortcode_enable_e2pdf_acf_repeater', false) && !apply_filters('e2pdf_pdf_render', false)) {
            return '';
        }

        $response = $this->helper->load('acfrepeater')->do_shortcode($atts, $value);
        if (!apply_filters('e2pdf_pdf_fill', false)) {
            $response = $this->sanitize_html($response);
        }
        return apply_filters('e2pdf_model_shortcode_e2pdf_acf_repeater_response', $response, $atts, $value);
    }

    // e2pdf-filter
    public function e2pdf_filter($atts = [], $value = '') {
        $atts = apply_filters('e2pdf_model_shortcode_e2pdf_filter_atts', $atts);
        if ($value) {
            $search = ['[', ']', '&#091;', '&#093;'];
            $replace = ['&#91;', '&#93;', '&#91;', '&#93;'];
            $value = str_replace($search, $replace, $value);
            $value = esc_attr($value);
        }
        return $value;
    }

    public function process_shortcode($shortcode = false, $template = null, $attributes = null) {
        if (!$attributes->get('noactions') && $template->get('actions')) {
            $model_e2pdf_action = new Model_E2pdf_Action();
            $model_e2pdf_action->load($template->extension());
            if (!is_array($template->get('actions'))) {
                $template->set('actions', $this->helper->load('convert')->unserialize($template->get('actions')));
            }
            $actions = $model_e2pdf_action->process_global_actions($template->get('actions'));
            foreach ($actions as $action) {
                if (isset($action['action']) && $action['action'] == 'disable_shortcodes' && isset($action['success'])) {
                    return false;
                } elseif (isset($action['action']) && $action['action'] == 'enable_shortcodes' && !isset($action['success'])) {
                    return false;
                } elseif ($shortcode && isset($action['action']) && $action['action'] == 'disable_' . $shortcode && isset($action['success'])) {
                    return false;
                } elseif ($shortcode && isset($action['action']) && $action['action'] == 'enable_' . $shortcode && !isset($action['success'])) {
                    return false;
                }
            }
        }
        return true;
    }

    public function action_e2pdf_hash_clear() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['e2pdf-hash'])) {
            $hash_id = sanitize_text_field(wp_unslash($_GET['e2pdf-hash']));
            delete_transient('e2pdf_hash_' . $hash_id);
        }
        // phpcs:enable
    }

    // WPBakery Page Builder Download Shortcode
    public function e2pdf_vc_download($atts, $value = null) {
        if (function_exists('get_the_ID')) {
            $atts = array_filter((array) $atts, 'strlen');
            $atts['dataset'] = get_the_ID();
            return $this->e2pdf_download($atts);
        }
        return '';
    }

    // WPBakery Page Builder View Shortcode
    public function e2pdf_vc_view($atts, $value = null) {
        if (function_exists('get_the_ID')) {
            $atts = array_filter((array) $atts, 'strlen');
            $atts['dataset'] = get_the_ID();
            return $this->e2pdf_view($atts);
        }
        return '';
    }

    // WPBakery Page Builder Grid Item Download Shortcode
    public function e2pdf_vc_download_item($atts, $value = null) {
        return '{{ e2pdf_download:' . http_build_query((array) $atts) . ' }}';
    }

    // WPBakery Page Builder Grid Item View Shortcode
    public function e2pdf_vc_view_item($atts, $value = null) {
        return '{{ e2pdf_view:' . http_build_query((array) $atts) . ' }}';
    }

    // sanitize css
    public function sanitize_css($css) {
        $css = apply_filters('e2pdf_safe_css', $css);
        return $css;
    }

    // sanitize html
    public function sanitize_html($html) {
        $allowed_html = apply_filters('e2pdf_safe_html', wp_kses_allowed_html('post'));
        add_filter('safe_style_css', [$this, 'sanitize_css']);
        $html = wp_kses($html, $allowed_html);
        remove_filter('safe_style_css', [$this, 'sanitize_css']);
        return $html;
    }
}
