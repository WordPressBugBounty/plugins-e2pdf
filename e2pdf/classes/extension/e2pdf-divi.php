<?php

/**
 * File: /extension/e2pdf-divi.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Extension_E2pdf_Divi extends Model_E2pdf_Model {

    private $options;
    private $info = [
        'key' => 'divi',
        'title' => 'Divi Forms',
    ];

    // info
    public function info($key = false) {
        if ($key && isset($this->info[$key])) {
            return $this->info[$key];
        } else {
            return [
                $this->info['key'] => $this->info['title'],
            ];
        }
    }

    // active
    public function active() {
        if (file_exists(get_template_directory() . '/et-pagebuilder/et-pagebuilder.php')) {
            return true;
        } else {
            if (defined('E2PDF_DIVI_EXTENSION') || $this->helper->load('extension')->is_plugin_active('divi-builder/divi-builder.php')) {
                return true;
            }
        }
        return false;
    }

    // set
    public function set($key, $value) {
        if (!isset($this->options)) {
            $this->options = new stdClass();
        }
        $this->options->$key = $value;
        switch ($key) {
            case 'dataset':
                $this->set('cached_entry', []);
                $this->set('cached_meta', []);
                if ($this->get('item') && $this->get('dataset')) {
                    $entry = new Model_E2pdf_Dataset();
                    if ($entry->load($this->get('dataset'), $this->get('item'), 'divi')) {
                        $cached_entry = $entry->get('entry');
                        $this->set('cached_entry', $cached_entry);

                        $meta_data = [];
                        if ($cached_entry && is_array($cached_entry)) {
                            // Divi5
                            if (!empty($cached_entry['ET_CORE_VERSION'])) {
                                foreach ($cached_entry as $field_key => $field_value) {
                                    $meta_data['%%' . $field_key . '%%'] = $field_value;
                                }
                                if (!isset($meta_data['%%e2pdf_entry_id%%'])) {
                                    $meta_data['%%e2pdf_entry_id%%'] = (int) $this->get('dataset');
                                }
                            } else {
                                // Divi4
                                $processed_fields_values = [];
                                $et_contact_proccess = array_search('et_contact_proccess', $cached_entry);
                                $et_pb_contact_form_num = $et_contact_proccess === false ? 0 : str_replace('et_pb_contactform_submit_', '', $et_contact_proccess);
                                $current_form_fields = isset($cached_entry['et_pb_contact_email_fields_' . $et_pb_contact_form_num]) ? $cached_entry['et_pb_contact_email_fields_' . $et_pb_contact_form_num] : '';
                                if ('' !== $current_form_fields) {
                                    $fields_data_json = str_replace('\\', '', $current_form_fields);
                                    $fields_data_array = json_decode($fields_data_json, true);
                                    if (!empty($fields_data_array)) {
                                        foreach ($fields_data_array as $field_value) {
                                            $processed_fields_values[$field_value['original_id']]['value'] = isset($cached_entry[$field_value['field_id']]) ? $cached_entry[$field_value['field_id']] : '';
                                            $processed_fields_values[$field_value['original_id']]['label'] = $field_value['field_label'];

                                            $is_file = isset($cached_entry[$field_value['field_id'] . '_is_file']) && $cached_entry[$field_value['field_id'] . '_is_file'] == 'yes';
                                            $is_signature_pad = isset($cached_entry[$field_value['field_id'] . '_is_signature_pad']) && $cached_entry[$field_value['field_id'] . '_is_signature_pad'] == 'yes';

                                            if (($is_signature_pad || $is_file) && $processed_fields_values[$field_value['original_id']]['value']) {
                                                $subdir = isset($cached_entry['_subdir']) ? $cached_entry['_subdir'] : '';
                                                if (isset($cached_entry['_save_files_to_media']) && $cached_entry['_save_files_to_media'] == 'on') {
                                                    if ($subdir && !file_exists(path_join(wp_upload_dir()['basedir'] . $subdir, $processed_fields_values[$field_value['original_id']]['value'])) && preg_match('/^\/\d{4}\/\d{2}$/', $subdir)) {
                                                        $tmpsubdir = '/' . date('Y/m', strtotime(str_replace('/', '-', ltrim($subdir, '/')) . ' -1 month'));
                                                        if (file_exists(path_join(wp_upload_dir()['basedir'] . $tmpsubdir, $processed_fields_values[$field_value['original_id']]['value']))) {
                                                            $subdir = $tmpsubdir;
                                                        }
                                                    }
                                                    $processed_fields_values[$field_value['original_id']]['value'] = path_join(wp_upload_dir()['baseurl'] . $subdir, $processed_fields_values[$field_value['original_id']]['value']);
                                                } else {
                                                    $contact_form_id = isset($cached_entry['_unique_id']) ? $cached_entry['_unique_id'] : '';
                                                    if (function_exists('pwh_dcfh_file_helpers') && $contact_form_id) {
                                                        if ($subdir && !file_exists(pwh_dcfh_file_helpers()::get_form_upload_dir($contact_form_id, $subdir, $processed_fields_values[$field_value['original_id']]['value'])) && preg_match('/^\d{4}\/\d{2}$/', $subdir)) {
                                                            $tmpsubdir = date('Y/m', strtotime(str_replace('/', '-', ltrim($subdir, '/')) . " -1 month"));
                                                            if (file_exists(pwh_dcfh_file_helpers()::get_form_upload_dir($contact_form_id, $tmpsubdir, $processed_fields_values[$field_value['original_id']]['value']))) {
                                                                $subdir = $tmpsubdir;
                                                            }
                                                        }
                                                        $processed_fields_values[$field_value['original_id']]['value'] = pwh_dcfh_file_helpers()::get_form_upload_url($contact_form_id, $subdir, $processed_fields_values[$field_value['original_id']]['value']);
                                                    } elseif (function_exists('ks_pac_dcfh_file_helper') && $contact_form_id) {
                                                        if ($subdir && !file_exists(ks_pac_dcfh_file_helper()::get_form_upload_dir($contact_form_id, $subdir, $processed_fields_values[$field_value['original_id']]['value'])) && preg_match('/^\d{4}\/\d{2}$/', $subdir)) {
                                                            $tmpsubdir = date('Y/m', strtotime(str_replace('/', '-', ltrim($subdir, '/')) . " -1 month"));
                                                            if (file_exists(ks_pac_dcfh_file_helper()::get_form_upload_dir($contact_form_id, $tmpsubdir, $processed_fields_values[$field_value['original_id']]['value']))) {
                                                                $subdir = $tmpsubdir;
                                                            }
                                                        }
                                                        $processed_fields_values[$field_value['original_id']]['value'] = ks_pac_dcfh_file_helper()::get_form_upload_url($contact_form_id, $subdir, $processed_fields_values[$field_value['original_id']]['value']);
                                                    } else {
                                                        $processed_fields_values[$field_value['original_id']]['value'] = '';
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                if (!isset($processed_fields_values['_wp_http_referer'])) {
                                    $processed_fields_values['_wp_http_referer'] = [
                                        'value' => isset($cached_entry['_wp_http_referer']) ? $cached_entry['_wp_http_referer'] : '',
                                        'label' => '_wp_http_referer',
                                    ];
                                }
                                if (!isset($processed_fields_values['e2pdf_entry_id'])) {
                                    $processed_fields_values['e2pdf_entry_id'] = [
                                        'value' => (int) $this->get('dataset'),
                                        'label' => 'e2pdf_entry_id',
                                    ];
                                }
                                foreach ($processed_fields_values as $field_key => $field_value) {
                                    $meta_data['%%' . $field_key . '%%'] = $field_value['value'];
                                }
                            }
                        }
                        $this->set('cached_meta', $meta_data);
                    }
                }
                break;
            default:
                break;
        }
    }

    // get
    public function get($key) {
        if (isset($this->options->$key)) {
            $value = $this->options->$key;
        } else {
            switch ($key) {
                case 'args':
                case 'cached_entry':
                case 'cached_meta':
                    $value = [];
                    break;
                default:
                    $value = false;
                    break;
            }
        }
        return $value;
    }

    // items
    public function items() {
        global $wpdb;
        $condition = [
            'post_content' => [
                'condition' => 'LIKE',
                'value' => '%et_pb_contact_form%',
                'type' => '%s',
                'or' => [
                    [
                        'post_content' => [
                            'condition' => 'LIKE',
                            'value' => '%wp:divi/contact-form%',
                            'type' => '%s',
                        ],
                    ],
                ],
            ],
            'post_type' => [
                'condition' => '<>',
                'value' => [
                    'revision',
                    'et_pb_layout',
                ],
                'type' => '%s',
            ],
        ];
        $order_condition = [
            'orderby' => 'id',
            'order' => 'desc',
        ];
        $where = $this->helper->load('db')->prepare_where($condition);
        $orderby = $this->helper->load('db')->prepare_orderby($order_condition);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $posts = $wpdb->get_results($wpdb->prepare('SELECT * FROM `' . $wpdb->prefix . 'posts`' . $where['sql'] . $orderby . '', $where['filter']));

        $content = [];
        foreach ($posts as $key => $post) {
            foreach ($this->get_forms($post->post_content) as $form_key => $form_value) {
                $content[] = $this->item($form_key);
            }
        }
        return $content;
    }

    // get forms
    public function get_forms($content) {
        $forms = [];

        if ($this->divi4_form($content)) {
            $shortcode_tags = [
                'et_pb_contact_form',
            ];
            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
            $tagnames = array_intersect($shortcode_tags, $matches[1]);
            if (!empty($tagnames)) {
                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $content, $shortcodes);
                foreach ($shortcodes[0] as $key => $shortcode_value) {
                    $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                    preg_match_all('/admin_label="(.*?)"/', $shortcode[3], $labels);
                    if (isset($labels['1'])) {
                        foreach ($labels['1'] as $label) {
                            $forms[$label] = $label;
                        }
                    }
                }
            }
        } elseif ($this->divi5_form($content)) {
            $blocks = $this->get_forms_blocks(parse_blocks($content));
            foreach ($blocks as $block) {
                $label = !empty($block['attrs']['module']['meta']['adminLabel']['desktop']['value']) ? $block['attrs']['module']['meta']['adminLabel']['desktop']['value'] : '';
                if ($label) {
                    $forms[$label] = $label;
                }
            }
        }
        return $forms;
    }

    // datasets
    public function datasets($item_id = false, $name = false) {
        global $wpdb;
        $datasets = [];
        if ($item_id) {
            $condition = [
                'extension' => [
                    'condition' => '=',
                    'value' => 'divi',
                    'type' => '%s',
                ],
                'item' => [
                    'condition' => '=',
                    'value' => $item_id,
                    'type' => '%s',
                ],
            ];
            $order_condition = [
                'orderby' => 'ID',
                'order' => 'desc',
            ];
            $where = $this->helper->load('db')->prepare_where($condition);
            $orderby = $this->helper->load('db')->prepare_orderby($order_condition);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $entries = $wpdb->get_results($wpdb->prepare('SELECT * FROM `' . $wpdb->prefix . 'e2pdf_datasets`' . $where['sql'] . $orderby . '', $where['filter']));

            if ($entries) {
                $this->set('item', $item_id);
                foreach ($entries as $key => $entry) {
                    $this->set('dataset', $entry->ID);
                    $entry_title = $this->render($name);
                    if (!$entry_title) {
                        $entry_title = $entry->ID;
                    }
                    $datasets[] = [
                        'key' => $entry->ID,
                        'value' => $entry_title,
                    ];
                }
            }
        }
        return $datasets;
    }

    // get dataset actions
    public function get_dataset_actions($dataset_id = false) {
        $dataset_id = (int) $dataset_id;
        if (!$dataset_id) {
            return;
        }
        $actions = new stdClass();
        $actions->view = false;
        $actions->delete = true;
        return $actions;
    }

    // get template actions
    public function get_template_actions($template = false) {
        $template = (int) $template;
        if (!$template) {
            return;
        }
        $actions = new stdClass();
        $actions->delete = true;
        return $actions;
    }

    // get forms blocks
    public function get_forms_blocks($blocks) {
        $forms = [];
        foreach ($blocks as $block) {
            if ('divi/contact-form' === $block['blockName']) {
                $forms[] = $block;
            }
            if (!empty($block['innerBlocks'])) {
                $nested_forms = $this->get_forms_blocks($block['innerBlocks']);
                $forms = array_merge($forms, $nested_forms);
            }
        }
        return $forms;
    }

    // get fields blocks
    public function get_fields_blocks($form) {
        $fields = [];
        if (!empty($form['innerBlocks'])) {
            foreach ($form['innerBlocks'] as $block) {
                if ('divi/contact-field' === $block['blockName']) {
                    $field_type = !empty($block['attrs']['fieldItem']['advanced']['type']['desktop']['value']) ? $block['attrs']['fieldItem']['advanced']['type']['desktop']['value'] : '';
                    $field_label = !empty($block['attrs']['fieldItem']['innerContent']['desktop']['value']) ? $block['attrs']['fieldItem']['innerContent']['desktop']['value'] : '';
                    $field_id = !empty($block['attrs']['fieldItem']['advanced']['id']['desktop']['value']) ? $block['attrs']['fieldItem']['advanced']['id']['desktop']['value'] : '';
                    $fields[] = [
                        'field_type' => $field_type,
                        'field_title' => $field_label,
                        'field_id' => $field_id,
                    ];
                }
                if (!empty($block['innerBlocks'])) {
                    $nested_fields = $this->get_fields_blocks($block);
                    $fields = array_merge($fields, $nested_fields);
                }
            }
        }
        return $fields;
    }

    // item
    public function item($item_id = false) {
        if (!$item_id && $this->get('item')) {
            $item_id = $this->get('item');
        }
        $item = new stdClass();
        if ($item_id) {
            $item->id = (string) $item_id;
            $item->name = $item_id;
            $form = $this->get_form($item_id);
            if (isset($form->ID)) {
                $item->url = $this->helper->get_url(
                        [
                            'post' => $form->ID,
                            'action' => 'edit',
                        ], 'post.php?'
                );
            } else {
                $item->url = '';
            }
        } else {
            $item->id = '';
            $item->name = '';
            $item->url = '';
        }
        return $item;
    }

    // get form
    public function get_form($item_id = false) {
        global $wpdb;
        $item_post = false;
        $condition = [
            'post_content' => [
                'condition' => 'LIKE',
                'value' => '%admin_label="' . $item_id . '"%',
                'type' => '%s',
                'or' => [
                    [
                        'post_content' => [
                            'condition' => 'LIKE',
                            'value' => '%"meta":{"adminLabel":{"desktop":{"value":"' . $item_id . '"%',
                            'type' => '%s',
                        ],
                    ],
                ],
            ],
            'post_type' => [
                'condition' => '<>',
                'value' => [
                    'revision',
                    'et_pb_layout',
                ],
                'type' => '%s',
            ],
        ];

        $order_condition = [
            'orderby' => 'id',
            'order' => 'desc',
        ];

        $where = $this->helper->load('db')->prepare_where($condition);
        $orderby = $this->helper->load('db')->prepare_orderby($order_condition);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $posts = $wpdb->get_results($wpdb->prepare('SELECT * FROM `' . $wpdb->prefix . 'posts`' . $where['sql'] . $orderby . '', $where['filter']));
        foreach ($posts as $post) {
            if (in_array($item_id, $this->get_forms($post->post_content))) {
                $item_post = $post;
                break;
            }
        }
        return $item_post;
    }

    // render
    public function render($value, $field = [], $convert_shortcodes = true, $raw = false) {
        $value = $this->render_shortcodes($value, $field);
        if (!$raw) {
            $value = $this->strip_shortcodes($value);
            $value = $this->convert_shortcodes($value, $convert_shortcodes, isset($field['type']) && $field['type'] == 'e2pdf-html' ? true : false);
            $value = $this->helper->load('field')->render_checkbox($value, $this, $field);
        }
        return $value;
    }

    // render shortcodes
    public function render_shortcodes($value, $field = []) {
        $element_id = isset($field['element_id']) ? $field['element_id'] : false;
        if ($this->verify()) {
            if (false !== strpos($value, '[')) {
                $value = $this->helper->load('field')->pre_shortcodes($value, $this, $field);
                $value = $this->helper->load('field')->inner_shortcodes($value, $this, $field);
                $value = $this->helper->load('field')->wrapper_shortcodes($value, $this, $field);
            }
            $value = $this->helper->load('field')->do_shortcodes($value, $this, $field);
            if (!empty($this->get('cached_meta'))) {
                $value = $this->helper->load('convert')->stritr($value, $this->get('cached_meta'));
            }
            $value = $this->helper->load('field')->render(
                    apply_filters('e2pdf_extension_render_shortcodes_pre_value', $value, $element_id, $this->get('template_id'), $this->get('item'), $this->get('dataset'), false, false),
                    $this,
                    $field
            );
        }
        return apply_filters(
                'e2pdf_extension_render_shortcodes_value', $value, $element_id, $this->get('template_id'), $this->get('item'), $this->get('dataset'), false, false
        );
    }

    // strip shortcodes
    public function strip_shortcodes($value) {
        $value = preg_replace('~(?:\[/?)[^/\]]+/?\]~s', '', $value);
        $value = preg_replace('~%%[^%%]*%%~', '', $value);
        return $value;
    }

    // convert shortcodes
    public function convert_shortcodes($value, $to = false, $html = false) {
        if ($value) {
            if ($to) {
                $value = stripslashes_deep($value);
                $value = str_replace('&#91;', '[', $value);
                if (!$html) {
                    $value = wp_specialchars_decode($value, ENT_QUOTES);
                }
            } else {
                $value = str_replace('[', '&#91;', $value);
            }
        }
        return $value;
    }

    // auto
    public function auto() {

        $elements = [];

        if ($this->get('item')) {
            $post = $this->get_form($this->get('item'));
            if ($post && isset($post->post_content)) {

                $source = '';
                $content = $post->post_content;

                if ($this->divi4_form($content)) {
                    $shortcode_tags = [
                        'et_pb_contact_form',
                    ];
                    preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
                    $tagnames = array_intersect($shortcode_tags, $matches[1]);
                    if (!empty($tagnames)) {
                        preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $content, $shortcodes);
                        foreach ($shortcodes[0] as $key => $shortcode_value) {
                            $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                            $atts = shortcode_parse_atts($shortcode[3]);
                            if (isset($atts['admin_label']) && $atts['admin_label'] == $this->get('item')) {
                                $this->divi4_load();
                                $source = do_shortcode($shortcode_value);
                            }
                        }
                    }
                } elseif ($this->divi5_form($content)) {
                    $blocks = $this->get_forms_blocks(parse_blocks($content));
                    foreach ($blocks as $key => $block) {
                        $label = !empty($block['attrs']['module']['meta']['adminLabel']['desktop']['value']) ? $block['attrs']['module']['meta']['adminLabel']['desktop']['value'] : '';
                        if ($label == $this->get('item')) {
                            $source = render_block($block);
                            break;
                        }
                    }
                }

                if ($source) {
                    $dom = new DOMDocument();
                    $html = $this->helper->load('convert')->load_html($source, $dom, true);
                    if ($html) {
                        $xml = $this->helper->load('xml');
                        $xpath = new DomXPath($dom);

                        // remove parent by class
                        $remove_parent_by_class = [
                            'et_pb_contact_captcha_question',
                        ];
                        foreach ($remove_parent_by_class as $key => $class) {
                            $xml_elements = $xpath->query("//*[contains(@class, '{$class}')]/parent::*");
                            foreach ($xml_elements as $xml_element) {
                                $xml_element->parentNode->removeChild($xml_element);
                            }
                        }

                        $blocks = $xpath->query("//*[contains(@class, 'et_pb_contact_field')]");
                        foreach ($blocks as $element) {

                            if ($xml->get_node_value($element, 'data-type') == 'radio') {

                                $label = $xpath->query('.//label', $element)->item(0);
                                $check_handler = $xpath->query(".//input[contains(@class, 'et_pb_checkbox_handle')]", $element)->item(0);

                                $name = '';
                                if ($check_handler) {
                                    $name = '%%' . $xml->get_node_value($check_handler, 'data-original_id') . '%%';
                                }

                                if ($label) {
                                    $elements[] = $this->auto_field(
                                            $element,
                                            [
                                                'type' => 'e2pdf-html',
                                                'block' => true,
                                                'class' => $xml->get_node_value($element, 'class'),
                                                'properties' => [
                                                    'top' => '20',
                                                    'left' => '20',
                                                    'right' => '20',
                                                    'width' => '100%',
                                                    'height' => 'auto',
                                                    'value' => $label->nodeValue,
                                                ],
                                            ]
                                    );
                                }

                                $fields = $xpath->query("//*[contains(@class, 'et_pb_contact_field_radio')]", $element);
                                foreach ($fields as $field) {
                                    $radio_label = $xpath->query('.//label', $field)->item(0);
                                    $radio = $xpath->query(".//input[@type='radio']", $field)->item(0);

                                    if (
                                            $radio->attributes->getNamedItem('data-original_id') &&
                                            $radio->attributes->getNamedItem('value')
                                    ) {
                                        $elements[] = $this->auto_field(
                                                $field,
                                                [
                                                    'type' => 'e2pdf-radio',
                                                    'class' => $xml->get_node_value($field, 'class'),
                                                    'properties' => [
                                                        'top' => '5',
                                                        'width' => 'auto',
                                                        'height' => 'auto',
                                                        'value' => '%%' . $xml->get_node_value($radio, 'data-original_id') . '%%',
                                                        'option' => $xml->get_node_value($radio, 'value'),
                                                        'group' => '%%' . $xml->get_node_value($radio, 'data-original_id') . '%%',
                                                    ],
                                                ]
                                        );
                                    }

                                    if ($radio_label) {
                                        $elements[] = $this->auto_field(
                                                $radio,
                                                [
                                                    'type' => 'e2pdf-html',
                                                    'float' => true,
                                                    'class' => $xml->get_node_value($radio, 'class'),
                                                    'properties' => [
                                                        'left' => '5',
                                                        'width' => '100%',
                                                        'height' => 'auto',
                                                        'value' => $radio_label->nodeValue,
                                                    ],
                                                ]
                                        );
                                    }
                                }
                            } elseif ($xml->get_node_value($element, 'data-type') == 'checkbox') {
                                $label = $xpath->query('.//label', $element)->item(0);
                                $check_handler = $xpath->query(".//input[contains(@class, 'et_pb_checkbox_handle')]", $element)->item(0);

                                $name = '';
                                if ($check_handler) {
                                    $name = '%%' . $xml->get_node_value($check_handler, 'data-original_id') . '%%';
                                }

                                if ($label) {
                                    $elements[] = $this->auto_field(
                                            $element,
                                            [
                                                'type' => 'e2pdf-html',
                                                'block' => true,
                                                'class' => $xml->get_node_value($element, 'class'),
                                                'properties' => [
                                                    'top' => '20',
                                                    'left' => '20',
                                                    'right' => '20',
                                                    'width' => '100%',
                                                    'height' => 'auto',
                                                    'value' => $label->nodeValue,
                                                ],
                                            ]
                                    );
                                }

                                $fields = $xpath->query("//*[contains(@class, 'et_pb_contact_field_checkbox')]", $element);
                                foreach ($fields as $field) {
                                    $checkbox_label = $xpath->query('.//label', $field)->item(0);
                                    $checkbox = $xpath->query(".//input[@type='checkbox']", $field)->item(0);

                                    $elements[] = $this->auto_field(
                                            $field,
                                            [
                                                'type' => 'e2pdf-checkbox',
                                                'class' => $xml->get_node_value($field, 'class'),
                                                'properties' => [
                                                    'top' => '5',
                                                    'width' => 'auto',
                                                    'height' => 'auto',
                                                    'value' => $name,
                                                    'option' => $xml->get_node_value($checkbox, 'value'),
                                                ],
                                            ]
                                    );

                                    if ($checkbox_label) {
                                        $elements[] = $this->auto_field(
                                                $checkbox,
                                                [
                                                    'type' => 'e2pdf-html',
                                                    'float' => true,
                                                    'class' => $xml->get_node_value($checkbox, 'class'),
                                                    'properties' => [
                                                        'left' => '5',
                                                        'width' => '100%',
                                                        'height' => 'auto',
                                                        'value' => $checkbox_label->nodeValue,
                                                    ],
                                                ]
                                        );
                                    }
                                }
                            } else {
                                $label = $xpath->query('.//label', $element)->item(0);
                                $input_text = $xpath->query(".//input[@type='text']", $element)->item(0);
                                $select = $xpath->query('.//select', $element)->item(0);
                                $textarea = $xpath->query('.//textarea', $element)->item(0);

                                if ($label && ($input_text || $select || $textarea)) {
                                    $elements[] = $this->auto_field(
                                            $element,
                                            [
                                                'type' => 'e2pdf-html',
                                                'block' => true,
                                                'class' => $xml->get_node_value($element, 'class'),
                                                'properties' => [
                                                    'top' => '20',
                                                    'left' => '20',
                                                    'right' => '20',
                                                    'width' => '100%',
                                                    'height' => 'auto',
                                                    'value' => $label->nodeValue,
                                                ],
                                            ]
                                    );
                                }

                                if ($input_text) {
                                    $elements[] = $this->auto_field(
                                            $input_text,
                                            [
                                                'type' => 'e2pdf-input',
                                                'class' => $xml->get_node_value($input_text, 'class'),
                                                'properties' => [
                                                    'top' => '5',
                                                    'width' => '100%',
                                                    'height' => 'auto',
                                                    'value' => '%%' . $xml->get_node_value($input_text, 'data-original_id') . '%%',
                                                ],
                                            ]
                                    );
                                } elseif ($select) {
                                    $options_tmp = [];
                                    $options = $xpath->query('.//option', $select);
                                    foreach ($options as $option) {
                                        $options_tmp[] = $xml->get_node_value($option, 'value');
                                    }

                                    $elements[] = $this->auto_field(
                                            $select,
                                            [
                                                'type' => 'e2pdf-select',
                                                'class' => $xml->get_node_value($select, 'class'),
                                                'properties' => [
                                                    'top' => '5',
                                                    'width' => '100%',
                                                    'height' => 'auto',
                                                    'options' => implode("\n", $options_tmp),
                                                    'value' => '%%' . $xml->get_node_value($select, 'data-original_id') . '%%',
                                                ],
                                            ]
                                    );
                                } elseif ($textarea) {
                                    $elements[] = $this->auto_field(
                                            $textarea,
                                            [
                                                'type' => 'e2pdf-textarea',
                                                'class' => $xml->get_node_value($textarea, 'class'),
                                                'properties' => [
                                                    'top' => '5',
                                                    'width' => '100%',
                                                    'height' => '150',
                                                    'value' => '%%' . $xml->get_node_value($textarea, 'data-original_id') . '%%',
                                                ],
                                            ]
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
        return [
            'page' => [
                'bottom' => '20',
                'top' => '20',
                'right' => '20',
                'left' => '20',
            ],
            'elements' => $elements,
        ];
    }

    // auto map
    public function auto_map($name = false) {
        $item = $this->get('item');
        if ($item) {
            $post = $this->get_form($item);
            if ($post && isset($post->post_content)) {
                $content = $post->post_content;
                if ($this->divi4_form($content)) {
                    $shortcode_tags = [
                        'et_pb_contact_form',
                    ];
                    preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
                    $tagnames = array_intersect($shortcode_tags, $matches[1]);
                    if (!empty($tagnames)) {
                        preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $content, $shortcodes);
                        foreach ($shortcodes[0] as $key => $shortcode_value) {
                            $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                            $atts = shortcode_parse_atts($shortcode[3]);
                            if (isset($atts['admin_label']) && $atts['admin_label'] == $this->get('item')) {
                                $field_content = $shortcode_value;
                                $field_shortcode_tags = [
                                    'et_pb_contact_field',
                                ];
                                preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $field_content, $field_matches);
                                $field_tagnames = array_intersect($field_shortcode_tags, $field_matches[1]);
                                if (!empty($field_tagnames)) {
                                    preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($field_tagnames) . '/', $field_content, $field_shortcodes);
                                    foreach ($field_shortcodes[0] as $field_key => $field_shortcode_value) {
                                        $field_shortcode = [];
                                        $field_shortcode[3] = $field_shortcodes[3][$field_key];
                                        $field = shortcode_parse_atts($field_shortcode[3]);
                                        if (isset($field['field_title']) && isset($field['field_id'])) {
                                            if ($field['field_title'] == $name || $field['field_id'] == $name) {
                                                return '%%' . strtolower($field['field_id']) . '%%';
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } elseif ($this->divi5_form($content)) {
                    $blocks = $this->get_forms_blocks(parse_blocks($content));
                    foreach ($blocks as $key => $block) {
                        $label = !empty($block['attrs']['module']['meta']['adminLabel']['desktop']['value']) ? $block['attrs']['module']['meta']['adminLabel']['desktop']['value'] : '';
                        if ($label == $this->get('item')) {
                            $fields = $this->get_fields_blocks($block);
                            if (!empty($fields)) {
                                foreach ($fields as $field) {
                                    if (isset($field['field_title']) && isset($field['field_id'])) {
                                        if ($field['field_title'] == $name || $field['field_id'] == $name) {
                                            return '%%' . strtolower($field['field_id']) . '%%';
                                        }
                                    }
                                }
                            }
                        }
                        break;
                    }
                }
            }
        }

        return false;
    }

    // auto field
    public function auto_field($field = false, $element = []) {

        if (!$field) {
            return false;
        }

        if (!isset($element['block'])) {
            $element['block'] = false;
        }

        if (!isset($element['float'])) {
            $element['float'] = false;
        }

        $classes = [];
        if (isset($element['class']) && $element['class']) {
            $classes = explode(' ', $element['class']);
            unset($element['class']);
        }

        $float_classes = [
            'et_pb_contact_field_half',
        ];
        $array_intersect = array_intersect($classes, $float_classes);

        if (!empty($array_intersect) && $element['block']) {
            $element['float'] = true;
        };

        $primary_class = false;
        if (!empty($array_intersect)) {
            $primary_class = end($array_intersect);
        }

        if ($element['block']) {
            switch ($primary_class) {
                case 'et_pb_contact_field_half':
                    $element['width'] = '50%';
                    break;
                default:
                    break;
            }
        }

        return $element;
    }

    // verify
    public function verify() {
        if ($this->get('cached_entry')) {
            return true;
        }
        return false;
    }

    // visual mapper
    public function visual_mapper() {

        $source = '';
        $html = '';

        if ($this->get('item')) {
            $post = $this->get_form($this->get('item'));
            if ($post && isset($post->post_content)) {
                $content = $post->post_content;

                if ($this->divi4_form($content)) {
                    $shortcode_tags = [
                        'et_pb_contact_form',
                    ];
                    preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
                    $tagnames = array_intersect($shortcode_tags, $matches[1]);
                    if (!empty($tagnames)) {
                        preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $content, $shortcodes);
                        foreach ($shortcodes[0] as $key => $shortcode_value) {
                            $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                            $atts = shortcode_parse_atts($shortcode[3]);
                            if (isset($atts['admin_label']) && $atts['admin_label'] == $this->get('item')) {
                                $this->divi4_load();
                                $source = do_shortcode($shortcode_value);
                            }
                        }
                    }
                } elseif ($this->divi5_form($content)) {
                    $blocks = $this->get_forms_blocks(parse_blocks($content));
                    foreach ($blocks as $key => $block) {
                        $label = !empty($block['attrs']['module']['meta']['adminLabel']['desktop']['value']) ? $block['attrs']['module']['meta']['adminLabel']['desktop']['value'] : '';
                        if ($label == $this->get('item')) {
                            $source = render_block($block);
                            break;
                        }
                    }
                }

                if ($source) {
                    $dom = new DOMDocument();
                    $html = $this->helper->load('convert')->load_html($source, $dom, true);
                }
                if (!$source) {
                    return '<div class="e2pdf-vm-error">' . __("The form source is empty or doesn't exist", 'e2pdf') . '</div>';
                } elseif (!$html) {
                    return '<div class="e2pdf-vm-error">' . __('The form could not be parsed due the incorrect HTML', 'e2pdf') . '</div>';
                } else {
                    $xml = $this->helper->load('xml');
                    $xpath = new DomXPath($dom);

                    // remove by name
                    $remove_by_name = [
                        'et_pb_contactform_submit_0',
                        '_wpnonce-et-pb-contact-form-submitted-0',
                        '_wp_http_referer',
                    ];
                    foreach ($remove_by_name as $key => $name) {
                        $xml_elements = $xpath->query('//*[@name="' . $name . '"]');
                        foreach ($xml_elements as $xml_element) {
                            $xml_element->parentNode->removeChild($xml_element);
                        }
                    }

                    // replace names
                    $fields = $xpath->query("//*[contains(@name, 'et_pb_contact_')]");
                    foreach ($fields as $xml_element) {
                        $xml->set_node_value($xml_element, 'name', '%%' . $xml->get_node_value($xml_element, 'data-original_id') . '%%');
                    }

                    $checkboxes = $xpath->query("//*[contains(@class, 'et_pb_contact_field') and @data-type='checkbox']");
                    foreach ($checkboxes as $xml_element) {
                        $check_handler = $xpath->query(".//input[contains(@class, 'et_pb_checkbox_handle')]", $xml_element)->item(0);
                        $name = '';
                        if ($check_handler) {
                            $name = '%%' . $xml->get_node_value($check_handler, 'data-original_id') . '%%';
                        }
                        $checks = $xpath->query(".//input[@type='checkbox']", $xml_element);
                        foreach ($checks as $check) {
                            $xml->set_node_value($check, 'name', $name);
                        }
                    }

                    // remove by class
                    $remove_by_class = [
                        'et_pb_contact_submit',
                        'et_pb_contactform_validate_field',
                        'et_pb_checkbox_handle',
                    ];
                    foreach ($remove_by_class as $key => $class) {
                        $xml_elements = $xpath->query("//*[contains(@class, '{$class}')]");
                        foreach ($xml_elements as $xml_element) {
                            $xml_element->parentNode->removeChild($xml_element);
                        }
                    }

                    // remove parent by class
                    $remove_parent_by_class = [
                        'et_pb_contact_captcha_question',
                    ];
                    foreach ($remove_parent_by_class as $key => $class) {
                        $xml_elements = $xpath->query("//*[contains(@class, '{$class}')]/parent::*");
                        foreach ($xml_elements as $xml_element) {
                            $xml_element->parentNode->removeChild($xml_element);
                        }
                    }
                }

                if (defined('LIBXML_HTML_NOIMPLIED') && defined('LIBXML_HTML_NODEFDTD')) {
                    return str_replace(['<html>', '</html>'], '', $dom->saveHTML());
                } else {
                    return $dom->saveHTML();
                }
            }
        }

        return false;
    }

    // filter mail
    public function filter_wp_mail($args) {

        if (!$this->get('process')) {
            return $args;
        }

        if ((defined('PWH_DCFH_PLUGIN_VERSION') && version_compare(PWH_DCFH_PLUGIN_VERSION, '1.5.1', '>=')) || defined('KS_PAC_DCFH_PLUGIN_FILE')) {
            $args['message'] = preg_replace('/(\{)((e2pdf-download|e2pdf-view|e2pdf-save|e2pdf-attachment|e2pdf-adobesign|e2pdf-zapier)[^\}]*?)(\})/', '[$2]', $args['message']);
        }

        if (isset($args['message'])) {
            if (false !== strpos($args['message'], '[')) {
                $shortcode_tags = [
                    'e2pdf-download',
                    'e2pdf-save',
                    'e2pdf-attachment',
                    'e2pdf-adobesign',
                    'e2pdf-zapier',
                ];
                preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $args['message'], $matches);
                $tagnames = array_intersect($shortcode_tags, $matches[1]);
                if (!empty($tagnames)) {
                    preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $args['message'], $shortcodes);
                    foreach ($shortcodes[0] as $key => $shortcode_value) {
                        $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                        $atts = shortcode_parse_atts($shortcode[3]);
                        if (!isset($atts['apply'])) {
                            $shortcode[3] .= ' apply="true"';
                        }
                        if (!isset($atts['filter'])) {
                            $shortcode[3] .= ' filter="true"';
                        }
                        $file = false;
                        if ($this->helper->load('shortcode')->is_attachment($shortcode, $atts)) {
                            $file = do_shortcode_tag($shortcode);
                            if ($file) {
                                $tmp = false;
                                if (substr($file, 0, 4) === 'tmp:') {
                                    $file = substr($file, 4);
                                    $tmp = true;
                                }
                                if ($shortcode[2] === 'e2pdf-save' || isset($atts['pdf'])) {
                                    if ($tmp) {
                                        $this->helper->add('divi_attachments', $file);
                                    }
                                } else {
                                    $this->helper->add('divi_attachments', $file);
                                }
                                $args['attachments'][] = $file;
                                if (!has_action('wp_mail_succeeded', [$this, 'filter_wp_mail_attachments'])) {
                                    add_action('wp_mail_succeeded', [$this, 'filter_wp_mail_attachments']);
                                }
                                if (!has_action('wp_mail_failed', [$this, 'filter_wp_mail_attachments'])) {
                                    add_action('wp_mail_failed', [$this, 'filter_wp_mail_attachments']);
                                }
                            }
                            $args['message'] = str_replace($shortcode_value, '', $args['message']);
                        } else {
                            if (is_array($args['headers']) && !in_array('Content-Type: text/html; charset=UTF-8', $args['headers'])) {
                                $args['headers'][] = 'Content-Type: text/html; charset=UTF-8';
                            }
                            $args['message'] = str_replace($shortcode_value, do_shortcode_tag($shortcode), $args['message']);
                            $args['message'] = str_replace("\r\n", '<br/>', $args['message']);
                        }
                    }
                }
            }
        }
        $wp_mail = [
            'to' => $args['to'],
            'subject' => $args['subject'],
            'message' => $args['message'],
            'headers' => $args['headers'],
            'attachments' => $args['attachments'],
        ];
        return $wp_mail;
    }

    public function filter_wp_mail_attachments() {
        $files = $this->helper->get('divi_attachments');
        if (is_array($files) && !empty($files)) {
            foreach ($files as $key => $file) {
                $this->helper->delete_dir(dirname($file) . '/');
            }
            $this->helper->deset('divi_attachments');
        }
    }

    // load actions
    public function load_actions() { // phpcs:ignore Squiz.WhiteSpace.SuperfluousWhitespace.EndLine
        add_action('et_pb_contact_form_submit', [$this, 'action_et_pb_contact_form_submit'], 11);
    }

    public function action_et_pb_contact_form_submit() {
        if ($this->divi5()) {
            add_filter('wp_mail', [$this, 'filter_wp_mail'], 1000);
        }
    }

    // load filters
    public function load_filters() {
        // Divi Contact Form Helper Confirmation Email compatibility fix
        if (defined('PWH_DCFH_PLUGIN_FILE') || defined('KS_PAC_DCFH_PLUGIN_FILE')) {
            add_filter('et_pb_module_shortcode_attributes', [$this, 'filter_et_pb_module_shortcode_attributes'], 9, 5);
        } else {
            add_filter('et_pb_module_shortcode_attributes', [$this, 'filter_et_pb_module_shortcode_attributes'], 30, 5);
        }
        add_filter('et_module_shortcode_output', [$this, 'filter_et_module_shortcode_output'], 30, 3);

        // Divi 5
        add_filter('divi_module_library_register_module_attrs', [$this, 'filter_divi_module_library_register_module_attrs'], 11, 2);
    }

    public function filter_divi_module_library_register_module_attrs($module_attrs, $filter_args) {

        if ('divi/contact-form' !== $filter_args['name']) {
            return $module_attrs;
        }

        if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
            return $module_attrs;
        }

        $module_id = !empty($filter_args['id']) ? $filter_args['id'] : '';
        $store_instance = !empty($filter_args['storeInstance']) ? $filter_args['storeInstance'] : 0;

        $module = \ET\Builder\FrontEnd\BlockParser\BlockParserStore::get($module_id, $store_instance);

        if (!$module) {
            return $module_attrs;
        }

        if (
                !class_exists('\ET\Builder\Packages\ModuleLibrary\ContactForm\ContactFormUtils') ||
                !method_exists('\ET\Builder\Packages\ModuleLibrary\ContactForm\ContactFormUtils', 'get_unique_id') ||
                !class_exists('\ET\Builder\Packages\ModuleLibrary\ContactForm\ContactFormHandler') ||
                !class_exists('\ET\Builder\Packages\ModuleLibrary\ContactField\ContactFieldModule') ||
                !method_exists('\ET\Builder\Packages\ModuleLibrary\ContactField\ContactFieldModule', 'get_field_unique_id')
        ) {
            return $module_attrs;
        }

        $unique_id = \ET\Builder\Packages\ModuleLibrary\ContactForm\ContactFormUtils::get_unique_id($module_attrs, (array) $module);

        $submitted = isset($_POST['_wpnonce-et-pb-contact-form-submitted-' . $unique_id]);
        if (!$submitted) {
            return $module_attrs;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce-et-pb-contact-form-submitted-' . $unique_id])), 'et-pb-contact-form-submit-' . $unique_id)) {
            return $module_attrs;
        }

        $use_basic_captcha = $module_attrs['module']['advanced']['spamProtection']['desktop']['value']['useBasicCaptcha'] ?? 'on';
        $use_spam_service = $module_attrs['module']['advanced']['spamProtection']['desktop']['value']['enabled'] ?? 'off';

        if ('on' === $use_basic_captcha && 'off' === $use_spam_service) {
            $captcha_answer = sanitize_text_field(wp_unslash($_POST['et_pb_contact_captcha_' . $unique_id] ?? ''));
            $first_digit = sanitize_text_field(wp_unslash($_POST['et_pb_contact_captcha_first_digit_' . $unique_id] ?? ''));
            $second_digit = sanitize_text_field(wp_unslash($_POST['et_pb_contact_captcha_second_digit_' . $unique_id] ?? ''));

            if (!$captcha_answer || ((int) $first_digit + (int) $second_digit) !== (int) $captcha_answer) {
                return $module_attrs;
            }
        } elseif ('on' === $use_spam_service) {
            $provider = $module_attrs['module']['advanced']['spamProtection']['desktop']['value']['provider'] ?? 'recaptcha';
            $account = $module_attrs['module']['advanced']['spamProtection']['desktop']['value']['account'] ?? '';
            $min_score = (float) ($module_attrs['module']['advanced']['spamProtection']['desktop']['value']['minScore'] ?? 0.0);

            if (empty($_POST['token']) || !SpamProtectionService::validate_token($provider, $account, $min_score)) {
                return $module_attrs;
            }
        }

        $messages = [
            'successMessage' => isset($module_attrs['module']['advanced']['successMessage']['desktop']['value']) ? $module_attrs['module']['advanced']['successMessage']['desktop']['value'] : '',
        ];
        if (defined('KS_PAC_DCFH_PLUGIN_FILE')) {
            $send_admin_email = isset($module_attrs['adminEmail']['advanced']['adminEmailEnabled']['desktop']['value']) ? $module_attrs['adminEmail']['advanced']['adminEmailEnabled']['desktop']['value'] : 'on';
            if ($send_admin_email === 'on') {
                $admin_email_rich_text = isset($module_attrs['adminEmail']['advanced']['emailRichTextEnabled']['desktop']['value']) ? $module_attrs['adminEmail']['advanced']['emailRichTextEnabled']['desktop']['value'] : 'off';
                if ($admin_email_rich_text === 'on') {
                    $messages['adminEmailRichTextMessage'] = isset($module_attrs['adminEmail']['innerContent']['emailRichTextMessage']['desktop']['value']) ? $this->decode_shortcodes($module_attrs['adminEmail']['innerContent']['emailRichTextMessage']['desktop']['value']) : '';
                } else {
                    $messages['email'] = isset($module_attrs['email']['innerContent']['desktop']['value']) ? $module_attrs['email']['innerContent']['desktop']['value'] : '';
                }
            }
            $send_user_email = isset($module_attrs['confirmationEmail']['advanced']['sendEmailToSubmitter']['desktop']['value']) ? $module_attrs['confirmationEmail']['advanced']['sendEmailToSubmitter']['desktop']['value'] : 'off';
            if ($send_user_email === 'on') {
                $user_email_rich_text = isset($module_attrs['confirmationEmail']['advanced']['emailMessageRichTextEnabled']['desktop']['value']) ? $module_attrs['confirmationEmail']['advanced']['emailMessageRichTextEnabled']['desktop']['value'] : 'off';
                if ($user_email_rich_text === 'on') {
                    $messages['confirmationEmailRichTextMessage'] = isset($module_attrs['confirmationEmail']['innerContent']['emailRichTextMessage']['desktop']['value']) ? $this->decode_shortcodes($module_attrs['confirmationEmail']['innerContent']['emailRichTextMessage']['desktop']['value']) : '';
                } else {
                    $messages['confirmationEmailMessage'] = isset($module_attrs['confirmationEmail']['innerContent']['emailMessage']['desktop']['value']) ? $module_attrs['confirmationEmail']['innerContent']['emailMessage']['desktop']['value'] : '';
                }
            }
        } else {
            $messages['email'] = isset($module_attrs['email']['innerContent']['desktop']['value']) ? $module_attrs['email']['innerContent']['desktop']['value'] : '';
        }

        foreach ($messages as $message_key => $message) {
            if (false !== strpos($message, '[')) {
                $shortcode_tags = [
                    'e2pdf-download',
                    'e2pdf-save',
                    'e2pdf-view',
                    'e2pdf-adobesign',
                    'e2pdf-zapier',
                    'e2pdf-attachment',
                ];
                preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $message, $matches);
                $tagnames = array_intersect($shortcode_tags, $matches[1]);
                if (!empty($tagnames)) {
                    $this->set('process', true);
                } else {
                    unset($messages[$message_key]);
                }
            } else {
                unset($messages[$message_key]);
            }
        }

        $_POST = $this->sanitize($_POST);

        if (!$this->get('process')) {
            return $module_attrs;
        }

        $fields_raw = [];

        $fields = \ET\Builder\FrontEnd\BlockParser\BlockParserStore::get_children($module_id, $store_instance);
        foreach ($fields as $field) {
            // Skip nested modules (Text, Button, Divider, etc.), only process Contact Field modules.
            if ('divi/contact-field' !== $field->blockName) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- This is a property of the WP Core class.
                continue;
            }

            // Use pre-filtered field attributes if provided, otherwise use field attributes from storage.
            $field_attrs = $field->attrs;
            $field_unique_id = \ET\Builder\Packages\ModuleLibrary\ContactField\ContactFieldModule::get_field_unique_id($field->id, $store_instance);
            $field_type = $field_attrs['fieldItem']['advanced']['type']['desktop']['value'] ?? 'input';
            $field_id = $field_attrs['fieldItem']['advanced']['id']['desktop']['value'] ?? '';
            $field_key = strtolower($field_id);
            $field_label = $field_attrs['fieldItem']['innerContent']['desktop']['value'] ?? '';

            if (!$field_label) {
                $field_label = __('New Field', 'et_builder_5');
            }

            $fields_raw[$field_key]['id'] = $field_id;
            $fields_raw[$field_key]['label'] = $field_label;
            $fields_raw[$field_key]['type'] = $field_type;
            $fields_raw[$field_key]['allowedSymbols'] = $field_attrs['fieldItem']['advanced']['allowedSymbols']['desktop']['value'] ?? '';
            $fields_raw[$field_key]['maxLength'] = $field_attrs['fieldItem']['advanced']['maxLength']['desktop']['value'] ?? '';
            $fields_raw[$field_key]['minLength'] = $field_attrs['fieldItem']['advanced']['minLength']['desktop']['value'] ?? '';
            $fields_raw[$field_key]['isRequired'] = 'on' === ($field_attrs['fieldItem']['advanced']['required']['desktop']['value'] ?? 'on');

            $fields_raw[$field_key]['conditionalLogic'] = [
                'enabled' => 'on' === ($field_attrs['conditionalLogic']['advanced']['enable']['desktop']['value'] ?? 'off'),
                'matchAll' => 'on' === ($field_attrs['conditionalLogic']['advanced']['relation']['desktop']['value'] ?? 'off'),
                'rules' => $field_attrs['conditionalLogic']['innerContent']['desktop']['value'] ?? [],
            ];

            if ('text' === $field_type) {
                $fields_raw[$field_key]['value'] = isset($_POST[$field_unique_id]) ? sanitize_textarea_field(wp_unslash($_POST[$field_unique_id])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated and sanitized.
            } elseif ('checkbox' === $field_type) {
                // For checkbox fields, decode and sanitize immediately, store as array.
                $raw_value = isset($_POST[$field_unique_id]) ? wp_unslash($_POST[$field_unique_id]) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated and sanitized.

                if ('' !== $raw_value) {
                    // Split the URL-encoded comma-separated values.
                    $parts = explode(',', $raw_value);
                    $parts = array_map('urldecode', $parts);
                    $parts = array_map('sanitize_text_field', $parts);
                    $parts = array_map('trim', $parts);
                    $fields_raw[$field_key]['value'] = $parts;
                } else {
                    $fields_raw[$field_key]['value'] = [];
                }
            } else {
                $fields_raw[$field_key]['value'] = isset($_POST[$field_unique_id]) ? sanitize_text_field(wp_unslash($_POST[$field_unique_id])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated and sanitized.
            }

            switch ($field_type) {
                case 'checkbox':
                    $options = $field_attrs['fieldItem']['advanced']['checkboxOptions']['desktop']['value'] ?? [];
                    $fields_raw[$field_key]['options'] = is_array($options) ? array_map(
                                    function ($option) {
                                        return wp_strip_all_tags($option['value'] ?? '');
                                    },
                                    $options
                            ) : [];
                    break;
                case 'radio':
                    $options = $field_attrs['fieldItem']['advanced']['radioOptions']['desktop']['value'] ?? [];
                    $fields_raw[$field_key]['options'] = is_array($options) ? array_map(
                                    function ($option) {
                                        return sanitize_text_field($option['value'] ?? '');
                                    },
                                    $options
                            ) : [];
                    break;
                case 'select':
                    $options = $field_attrs['fieldItem']['advanced']['selectOptions']['desktop']['value'] ?? [];
                    $fields_raw[$field_key]['options'] = is_array($options) ? array_map(
                                    function ($option) {
                                        return sanitize_text_field($option['value'] ?? '');
                                    },
                                    $options
                            ) : [];
                    break;
                default:
                    $fields_raw[$field_key]['options'] = [];
                    break;
            }
        }

        $refClass = new ReflectionClass('\ET\Builder\Packages\ModuleLibrary\ContactForm\ContactFormHandler');
        $obj = $refClass->newInstanceWithoutConstructor();

        try {
            $refFields = $refClass->getProperty('_fields_raw');
            $refFields->setAccessible(true);
            $refFields->setValue($obj, $fields_raw);

            $refError = $refClass->getProperty('_error');
            $refError->setAccessible(true);
            $refError->setValue($obj, new WP_Error());
        } catch (\ReflectionException $e) {
            return $module_attrs;
        }

        $obj->validate_fields();

        $errorObj = $refError->getValue($obj);

        if ($errorObj->has_errors()) {
            return $module_attrs;
        }

        $fields_raw = $refFields->getValue($obj);

        $item = !empty($module_attrs['module']['meta']['adminLabel']['desktop']['value']) ? $module_attrs['module']['meta']['adminLabel']['desktop']['value'] : '';
        if (!$item) {
            return $module_attrs;
        }

        $data = [
            'ET_CORE_VERSION' => '5',
        ];
        if (!empty($fields_raw) && is_array($fields_raw)) {
            foreach ($fields_raw as $field_key => $field_raw) {
                if (!empty($field_raw['value'])) {
                    $data[$field_key] = $field_raw['value'];
                }
            }
        }
        if (empty($data['_wp_http_referer']) && !empty($_POST['_wp_http_referer'])) {
            $data['_wp_http_referer'] = esc_url_raw($_POST['_wp_http_referer']);
        }

        $entry = new Model_E2pdf_Dataset();
        $entry->set('extension', 'divi');
        $entry->set('item', $item);
        $entry->set('entry', $data);
        $dataset = $entry->save();

        foreach ($messages as $message_key => $message) {
            if (false !== strpos($message, '[')) {
                $shortcode_tags = [
                    'e2pdf-download',
                    'e2pdf-save',
                    'e2pdf-view',
                    'e2pdf-adobesign',
                    'e2pdf-zapier',
                    'e2pdf-attachment',
                ];
                preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $message, $matches);
                $tagnames = array_intersect($shortcode_tags, $matches[1]);
                if (!empty($tagnames)) {
                    preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $message, $shortcodes);
                    foreach ($shortcodes[0] as $key => $shortcode_value) {
                        $item = false;
                        $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                        $atts = shortcode_parse_atts($shortcode[3]);

                        if (!isset($atts['dataset']) && isset($atts['id'])) {
                            $template = new Model_E2pdf_Template();
                            $template->load($atts['id']);
                            if ($template->get('extension') === 'divi') {
                                $item = $template->get('item');
                                $atts['dataset'] = $dataset;
                                $shortcode[3] .= ' dataset="' . $dataset . '"';
                            }
                        }
                        if (!isset($atts['apply'])) {
                            $shortcode[3] .= ' apply="true"';
                        }
                        if (!isset($atts['iframe_download'])) {
                            $shortcode[3] .= ' iframe_download="true"';
                        }
                        switch ($message_key) {
                            case 'successMessage':
                                $module_attrs['module']['advanced']['successMessage']['desktop']['value'] = str_replace($shortcode_value, do_shortcode_tag($shortcode), $module_attrs['module']['advanced']['successMessage']['desktop']['value']);
                                break;
                            case 'email':
                                $module_attrs['email']['innerContent']['desktop']['value'] = str_replace($shortcode_value, '[' . $shortcode[2] . $shortcode[3] . ']', $module_attrs['email']['innerContent']['desktop']['value']);
                                break;
                            case 'adminEmailRichTextMessage':
                            case 'confirmationEmailMessage':
                            case 'confirmationEmailRichTextMessage':
                                $messages[$message_key] = str_replace($shortcode_value, '{' . $shortcode[2] . $shortcode[3] . '}', $messages[$message_key]);
                                break;
                            default:
                                break;
                        }
                    }
                }
            }
        }

        foreach ($messages as $message_key => $message) {
            switch ($message_key) {
                case 'adminEmailRichTextMessage':
                    $class = '\KS_PAC_DCFH\Frontend\Controllers\ContactFormDataRepository';
                    if (class_exists($class) && method_exists($class, 'instance')) {
                        $contact_form_rep = $class::instance();
                        $contact_form_rep->admin_message = $messages[$message_key];
                    }
                    break;
                case 'confirmationEmailMessage':
                case 'confirmationEmailRichTextMessage':
                    $class = '\KS_PAC_DCFH\Frontend\Services\ConfirmationEmailService';
                    if (class_exists($class) && method_exists($class, 'instance')) {
                        $contact_form_rep = $class::instance();
                        $contact_form_rep->email_message = $messages[$message_key];
                    }
                    break;
                default:
                    break;
            }
        }
        return $module_attrs;
    }

    // filter shortcode attributes
    public function filter_et_pb_module_shortcode_attributes($props, $attrs, $render_slug, $address, $content) {

        if ($render_slug !== 'et_pb_contact_form') {
            return $props;
        }

        $et_contact_proccess = array_search('et_contact_proccess', (array) $_POST);
        if ($et_contact_proccess === false) {
            return $props;
        }

        $success_message = isset($props['success_message']) ? $this->decode_shortcodes($props['success_message'], true) : '';

        if (false !== strpos($success_message, '[')) {
            $shortcode_tags = [
                'e2pdf-download',
                'e2pdf-save',
                'e2pdf-view',
                'e2pdf-adobesign',
                'e2pdf-zapier',
            ];
            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $success_message, $matches);
            $tagnames = array_intersect($shortcode_tags, $matches[1]);
            if (!empty($tagnames)) {
                $this->set('process', true);
            } else {
                $success_message = '';
            }
        }

        $admin_email_rich_text = isset($props['use_custom_message_richtext']) && $props['use_custom_message_richtext'] == 'on' && isset($props['custom_message_richtext']) ? true : false;
        if ($admin_email_rich_text) {
            $admin_email_message = $this->decode_shortcodes($props['custom_message_richtext']);
        } else {
            $admin_email_message = isset($props['custom_message']) ? $this->decode_shortcodes($props['custom_message']) : '';
        }
        if (false !== strpos($admin_email_message, '[')) {
            $shortcode_tags = [
                'e2pdf-download',
                'e2pdf-save',
                'e2pdf-attachment',
                'e2pdf-adobesign',
                'e2pdf-zapier',
            ];
            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $admin_email_message, $matches);
            $tagnames = array_intersect($shortcode_tags, $matches[1]);
            if (!empty($tagnames)) {
                $this->set('process', true);
            } else {
                $admin_email_message = '';
            }
        }

        /* Divi Contact Form Helper Confirmation Email and Confirmation Email RichText */
        $user_email_rich_text = isset($props['use_confirmation_email']) && $props['use_confirmation_email'] == 'on' && isset($props['use_confirmation_message_richtext']) && $props['use_confirmation_message_richtext'] == 'on' && isset($props['confirmation_message_richtext']) ? true : false;
        if ($user_email_rich_text) {
            $user_email_message = $this->decode_shortcodes($props['confirmation_message_richtext']);
        } else {
            $user_email_message = isset($props['use_confirmation_email']) && $props['use_confirmation_email'] == 'on' && isset($props['confirmation_email_message']) ? $this->decode_shortcodes($props['confirmation_email_message']) : '';
        }
        if (false !== strpos($user_email_message, '[')) {
            $shortcode_tags = [
                'e2pdf-download',
                'e2pdf-save',
                'e2pdf-attachment',
                'e2pdf-adobesign',
                'e2pdf-zapier',
            ];
            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $user_email_message, $matches);
            $tagnames = array_intersect($shortcode_tags, $matches[1]);
            if (!empty($tagnames)) {
                $this->set('process', true);
            } else {
                $user_email_message = '';
            }
        }

        $_POST = $this->sanitize($_POST);

        // check if E2Pdf shortcodes exist in messages
        if (!$this->get('process')) {
            return $props;
        }

        $data = $_POST;
        if (isset($props['_unique_id'])) {
            $data['_unique_id'] = $props['_unique_id'];
        }
        if (isset($props['save_files_to_media']) && $props['save_files_to_media'] == 'on') {
            $data['_save_files_to_media'] = $props['save_files_to_media'];
            $data['_subdir'] = wp_upload_dir()['subdir'];
        } elseif (function_exists('pwh_dcfh_file_helpers')) {
            $subdir = pwh_dcfh_file_helpers()::get_subdir();
            if (is_array($subdir)) {
                $data['_subdir'] = implode('/', $subdir);
            }
        } elseif (function_exists('ks_pac_dcfh_file_helper')) {
            $subdir = ks_pac_dcfh_file_helper()::get_subdir();
            if (is_array($subdir)) {
                $data['_subdir'] = implode('/', $subdir);
            }
        }

        $captcha = isset($props['captcha']) ? $props['captcha'] : '';
        $use_spam_service = isset($props['use_spam_service']) ? $props['use_spam_service'] : 'off';
        $et_pb_contact_form_num = str_replace('et_pb_contactform_submit_', '', $et_contact_proccess);
        $et_contact_error = false;
        $current_form_fields = isset($data['et_pb_contact_email_fields_' . $et_pb_contact_form_num]) ? $data['et_pb_contact_email_fields_' . $et_pb_contact_form_num] : '';
        $contact_email = '';
        $nonce_result = isset($data['_wpnonce-et-pb-contact-form-submitted-' . $et_pb_contact_form_num]) && wp_verify_nonce($data['_wpnonce-et-pb-contact-form-submitted-' . $et_pb_contact_form_num], 'et-pb-contact-form-submit') ? true : false;

        if ($nonce_result && isset($data['et_pb_contactform_submit_' . $et_pb_contact_form_num]) && empty($data['et_pb_contact_et_number_' . $et_pb_contact_form_num])) {
            if ('' !== $current_form_fields) {
                $fields_data_json = str_replace('\\', '', $current_form_fields);
                $fields_data_array = json_decode($fields_data_json, true);

                // check whether captcha field is not empty
                if ('on' === $captcha && 'off' === $use_spam_service && (!isset($data['et_pb_contact_captcha_' . $et_pb_contact_form_num]) || empty($data['et_pb_contact_captcha_' . $et_pb_contact_form_num]))) {
                    $et_contact_error = true;
                } elseif ('on' === $use_spam_service) {
                    if (class_exists('ET_Builder_Element')) {
                        $contact_form = ET_Builder_Element::get_module('et_pb_contact_form');
                        if ($contact_form && is_object($contact_form) && method_exists($contact_form, 'is_spam_submission')) {
                            $contact_form->props = $props;
                            if ($contact_form->is_spam_submission()) {
                                if (!empty($_POST['token'])) {
                                    unset($_POST['token']);
                                }
                                $et_contact_error = true;
                            } else {
                                $props['use_spam_service'] = 'off';
                                $props['captcha'] = 'off';
                            }
                        }
                    }
                }

                // check all fields on current form and generate error message if needed
                if (!empty($fields_data_array)) {
                    foreach ($fields_data_array as $value) {
                        if (isset($value['field_id']) && 'et_pb_contact_et_number_' . $et_pb_contact_form_num === $value['field_id']) {
                            continue;
                        }
                        /* Check all the required fields, generate error message if required field is empty */
                        $field_value = isset($data[$value['field_id']]) ? trim($data[$value['field_id']]) : '';
                        if ('required' === $value['required_mark'] && empty($field_value) && !is_numeric($field_value)) {
                            $et_contact_error = true;
                            continue;
                        }
                        /* Additional check for email field */
                        if ('email' === $value['field_type'] && 'required' === $value['required_mark'] && !empty($field_value)) {
                            $contact_email = isset($data[$value['field_id']]) ? sanitize_email($data[$value['field_id']]) : '';
                            if (!empty($contact_email) && !is_email($contact_email)) {
                                $et_contact_error = true;
                            }
                        }
                    }
                }
            } else {
                $et_contact_error = true;
            }
        } else {
            $et_contact_error = true;
        }

        if (!$et_contact_error && $nonce_result) {
            // divi contact form helper 2.1.3 bug fix wp_mail filter removed at maybe_filter_wp_mail
            if (defined('KS_PAC_DCFH_PLUGIN_VERSION') && version_compare(KS_PAC_DCFH_PLUGIN_VERSION, '2.1.3', '>=')) {
                add_filter('wp_mail', function ($args) {
                    return $args;
                }, 999);
            }
            add_filter('wp_mail', [$this, 'filter_wp_mail'], 1000);

            $dataset = false;
            if (false !== strpos($success_message, '[')) {
                $shortcode_tags = [
                    'e2pdf-download',
                    'e2pdf-save',
                    'e2pdf-view',
                    'e2pdf-adobesign',
                    'e2pdf-zapier',
                ];
                preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $success_message, $matches);
                $tagnames = array_intersect($shortcode_tags, $matches[1]);
                if (!empty($tagnames)) {
                    preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $success_message, $shortcodes);
                    foreach ($shortcodes[0] as $key => $shortcode_value) {
                        $item = false;
                        $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                        $atts = shortcode_parse_atts($shortcode[3]);
                        if ($this->helper->load('shortcode')->is_attachment($shortcode, $atts)) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
                        } else {
                            if (!isset($atts['dataset']) && isset($atts['id'])) {
                                $template = new Model_E2pdf_Template();
                                $template->load($atts['id']);
                                if ($template->get('extension') === 'divi') {
                                    $item = $template->get('item');
                                    if (!$dataset) {
                                        $entry = new Model_E2pdf_Dataset();
                                        $entry->set('extension', 'divi');
                                        $entry->set('item', $item);
                                        $entry->set('entry', $data);
                                        $dataset = $entry->save();
                                    }
                                    $atts['dataset'] = $dataset;
                                    $shortcode[3] .= ' dataset="' . $dataset . '"';
                                }
                            }
                            if (!isset($atts['apply'])) {
                                $shortcode[3] .= ' apply="true"';
                            }
                            if (!isset($atts['iframe_download'])) {
                                $shortcode[3] .= ' iframe_download="true"';
                            }
                            $props['success_message'] = str_replace($this->encode_shortcodes($shortcode_value), '[' . $shortcode[2] . $shortcode[3] . ']', $props['success_message']);
                        }
                    }
                }
            }

            if (false !== strpos($admin_email_message, '[')) {
                $shortcode_tags = [
                    'e2pdf-download',
                    'e2pdf-save',
                    'e2pdf-attachment',
                    'e2pdf-adobesign',
                    'e2pdf-zapier',
                ];
                preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $admin_email_message, $matches);
                $tagnames = array_intersect($shortcode_tags, $matches[1]);
                if (!empty($tagnames)) {
                    preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $admin_email_message, $shortcodes);
                    foreach ($shortcodes[0] as $key => $shortcode_value) {
                        $item = false;
                        $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                        $atts = shortcode_parse_atts($shortcode[3]);
                        if (!isset($atts['dataset']) && isset($atts['id'])) {
                            $template = new Model_E2pdf_Template();
                            $template->load($atts['id']);
                            if ($template->get('extension') === 'divi') {
                                $item = $template->get('item');
                                if (!$dataset) {
                                    $entry = new Model_E2pdf_Dataset();
                                    $entry->set('extension', 'divi');
                                    $entry->set('item', $item);
                                    $entry->set('entry', $data);
                                    $dataset = $entry->save();
                                }
                                $atts['dataset'] = $dataset;
                                $shortcode[3] .= ' dataset="' . $dataset . '"';
                            }
                        }
                        if ($admin_email_rich_text) {
                            if ((defined('PWH_DCFH_PLUGIN_VERSION') && version_compare(PWH_DCFH_PLUGIN_VERSION, '1.5.1', '>=')) || defined('KS_PAC_DCFH_PLUGIN_FILE')) {
                                $props['custom_message_richtext'] = str_replace($this->encode_shortcodes($shortcode_value), '{' . $shortcode[2] . $shortcode[3] . '}', $props['custom_message_richtext']);
                            } else {
                                $props['custom_message_richtext'] = str_replace($this->encode_shortcodes($shortcode_value), '[' . $shortcode[2] . $shortcode[3] . ']', $props['custom_message_richtext']);
                            }
                        } else {
                            $props['custom_message'] = str_replace($this->encode_shortcodes($shortcode_value), '[' . $shortcode[2] . $shortcode[3] . ']', $props['custom_message']);
                        }
                    }
                }
            }

            if (false !== strpos($user_email_message, '[')) {
                $shortcode_tags = [
                    'e2pdf-download',
                    'e2pdf-save',
                    'e2pdf-attachment',
                    'e2pdf-adobesign',
                    'e2pdf-zapier',
                ];
                preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $user_email_message, $matches);
                $tagnames = array_intersect($shortcode_tags, $matches[1]);
                if (!empty($tagnames)) {
                    preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $user_email_message, $shortcodes);
                    foreach ($shortcodes[0] as $key => $shortcode_value) {
                        $item = false;
                        $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                        $atts = shortcode_parse_atts($shortcode[3]);
                        if (!isset($atts['dataset']) && isset($atts['id'])) {
                            $template = new Model_E2pdf_Template();
                            $template->load($atts['id']);
                            if ($template->get('extension') === 'divi') {
                                $item = $template->get('item');
                                if (!$dataset) {
                                    $entry = new Model_E2pdf_Dataset();
                                    $entry->set('extension', 'divi');
                                    $entry->set('item', $item);
                                    $entry->set('entry', $data);
                                    $dataset = $entry->save();
                                }
                                $atts['dataset'] = $dataset;
                                $shortcode[3] .= ' dataset="' . $dataset . '"';
                            }
                        }
                        if ((defined('PWH_DCFH_PLUGIN_VERSION') && version_compare(PWH_DCFH_PLUGIN_VERSION, '1.5.1', '>=')) || defined('KS_PAC_DCFH_PLUGIN_FILE')) {
                            if ($user_email_rich_text) {
                                $props['confirmation_message_richtext'] = str_replace($this->encode_shortcodes($shortcode_value), '{' . $shortcode[2] . $shortcode[3] . '}', $props['confirmation_message_richtext']);
                            } else {
                                $props['confirmation_email_message'] = str_replace($this->encode_shortcodes($shortcode_value), '{' . $shortcode[2] . $shortcode[3] . '}', $props['confirmation_email_message']);
                            }
                        } else {
                            if ($user_email_rich_text) {
                                $props['confirmation_message_richtext'] = str_replace($this->encode_shortcodes($shortcode_value), '[' . $shortcode[2] . $shortcode[3] . ']', $props['confirmation_message_richtext']);
                            } else {
                                $props['confirmation_email_message'] = str_replace($this->encode_shortcodes($shortcode_value), '[' . $shortcode[2] . $shortcode[3] . ']', $props['confirmation_email_message']);
                            }
                        }
                    }
                }
            }
        }

        return $props;
    }

    // filter shortcode output
    public function filter_et_module_shortcode_output($output, $render_slug, $form) {

        if ($render_slug !== 'et_pb_contact_form') {
            return $output;
        }

        $et_contact_proccess = array_search('et_contact_proccess', (array) $_POST);
        if ($et_contact_proccess === false) {
            return $output;
        }

        $et_pb_contact_form_num = str_replace(['pwh_dcfh_et_pb_contactform_submit_', 'et_pb_contactform_submit_'], ['', ''], $et_contact_proccess);
        $nonce_result = isset($_POST['_wpnonce-et-pb-contact-form-submitted-' . $et_pb_contact_form_num]) && wp_verify_nonce($_POST['_wpnonce-et-pb-contact-form-submitted-' . $et_pb_contact_form_num], 'et-pb-contact-form-submit') ? true : false;

        if ($nonce_result && (
                (isset($_POST['et_pb_contactform_submit_' . $et_pb_contact_form_num]) && empty($_POST['et_pb_contact_et_number_' . $et_pb_contact_form_num])) ||
                (isset($_POST['pwh_dcfh_et_pb_contactform_submit_' . $et_pb_contact_form_num]) && $_POST['pwh_dcfh_et_pb_contactform_submit_' . $et_pb_contact_form_num] == 'et_contact_proccess')
                )
        ) {
            $success_message = isset($form->props['success_message']) ? $this->decode_shortcodes($form->props['success_message'], true) : '';
            if (false !== strpos($success_message, '[')) {
                $shortcode_tags = [
                    'e2pdf-download',
                    'e2pdf-save',
                    'e2pdf-view',
                    'e2pdf-adobesign',
                    'e2pdf-zapier',
                ];
                preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $success_message, $matches);
                $tagnames = array_intersect($shortcode_tags, $matches[1]);
                if (!empty($tagnames)) {
                    preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $success_message, $shortcodes);
                    foreach ($shortcodes[0] as $key => $shortcode_value) {
                        $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                        if (isset($form->props['enable_multistep']) && 'on' === $form->props['enable_multistep']) {
                            $output = str_replace($shortcode_value, do_shortcode_tag($shortcode), $output);
                        } else {
                            $output = str_replace(str_replace(['"'], ['&quot;'], $shortcode_value), do_shortcode_tag($shortcode), $output);
                        }
                    }
                }
            }
        }
        return $output;
    }

    // delete item
    public function delete_item($template_id = false, $dataset = false) {
        global $wpdb;
        $template = new Model_E2pdf_Template();
        if ($template_id && $dataset && $template->load($template_id)) {
            if ($template->get('extension') === 'divi' && $template->get('item')) {
                $item = $template->get('item');
                $where = [
                    'ID' => $dataset,
                    'item' => $item,
                    'extension' => 'divi',
                ];
                $wpdb->delete($wpdb->prefix . 'e2pdf_datasets', $where);
                return true;
            }
        }
        return false;
    }

    // delete items
    public function delete_items($template_id = false) {
        global $wpdb;
        $template = new Model_E2pdf_Template();
        if ($template_id && $template->load($template_id)) {
            if ($template->get('extension') === 'divi' && $template->get('item')) {
                $where = [
                    'item' => $template->get('item'),
                    'extension' => 'divi',
                ];
                $wpdb->delete($wpdb->prefix . 'e2pdf_datasets', $where);
                return true;
            }
        }
        return false;
    }

    // styles
    public function styles($item_id = false) {
        $styles = [];
        if ($this->divi5()) {
            $assets_list = \ET\Builder\FrontEnd\Assets\DynamicAssetsUtils::get_assets_list(
                    [
                        'prefix' => \ET\Builder\FrontEnd\Assets\DynamicAssetsUtils::get_dynamic_assets_path(),
                        'suffix' => '',
                        'specialty_suffix' => '',
                    ]
            );
            foreach ($assets_list as $list) {
                if (!empty($list['css']) && is_array($list['css'])) {
                    foreach ($list['css'] as $css) {
                        $styles[] = $this->wp_convert_path_to_url($css);
                    }
                }
            }
        }
        $styles[] = plugins_url('css/extension/divi.css?v=' . time(), $this->helper->get('plugin_file_path'));
        return $styles;
    }

    public function wp_convert_path_to_url($path) {
        // Standardize slashes for Windows/Linux compatibility
        $path = wp_normalize_path($path);
        $root = wp_normalize_path(ABSPATH);

        // Replace the server root with the Site URL
        return str_replace($root, site_url('/'), $path);
    }

    // sanitize
    public function sanitize($value) {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->sanitize($v);
            }
            return $value;
        }
        if (!is_string($value)) {
            return $value;
        }
        if (stripos($value, 'e2pdf-') === false) {
            return $value;
        }
        return preg_replace('/(?:\[|&#91;|\{)(e2pdf-download|e2pdf-view|e2pdf-save|e2pdf-attachment|e2pdf-adobesign|e2pdf-zapier)[^\]}\r\n]*(?:\]|&#93;|\})/i', '', $value);
    }

    // decode shortcodes
    public function decode_shortcodes($value = '', $quote = false) {
        return preg_replace_callback(
                '/(&#91;)((e2pdf-download|e2pdf-view|e2pdf-save|e2pdf-attachment|e2pdf-adobesign|e2pdf-zapier)[^&]*?)(&#93;)/',
                function ($m) use ($quote) {
                    $shortcode = $m[2];
                    if ($quote !== false) {
                        $shortcode = str_replace('&quot;', '"', $shortcode);
                    }

                    return '[' . $shortcode . ']';
                },
                $value
        );
    }

    // encode shortcodes
    public function encode_shortcodes($value = '', $quote = false) {
        return preg_replace_callback(
                '/(\[)((e2pdf-download|e2pdf-view|e2pdf-save|e2pdf-attachment|e2pdf-adobesign|e2pdf-zapier)[^\]]*?)(\])/',
                function ($m) use ($quote) {
                    $shortcode = $m[2];
                    if ($quote !== false) {
                        $shortcode = str_replace('"', '&quot;', $shortcode);
                    }

                    return '&#91;' . $shortcode . '&#93;';
                },
                $value
        );
    }

    // divi4 load
    public function divi4_load() {
        if (defined('ET_BUILDER_DIR') && !$this->divi5()) {
            require_once ET_BUILDER_DIR . 'class-et-builder-element.php';
            require_once ET_BUILDER_DIR . 'functions.php';
            require_once ET_BUILDER_DIR . 'ab-testing.php';
            require_once ET_BUILDER_DIR . 'class-et-global-settings.php';
            if (file_exists(ET_BUILDER_DIR . 'module/type/WithSpamProtection.php')) {
                require_once ET_BUILDER_DIR . 'module/type/WithSpamProtection.php';
            }
            require_once ET_BUILDER_DIR . 'module/ContactForm.php';
            require_once ET_BUILDER_DIR . 'module/ContactFormItem.php';
            new ET_Builder_Module_Contact_Form();
            new ET_Builder_Module_Contact_Form_Item();
        }
    }

    // is divi5
    public function divi5() {
        if (defined('ET_CORE_VERSION') && version_compare(ET_CORE_VERSION, '4.99', '>')) {
            return true;
        }
        return false;
    }

    // is divi4 form
    public function divi4_form($content = '') {
        return (false !== strpos($content, '[et_pb_contact_form'));
    }

    // is divi 5 form
    public function divi5_form($content = '') {
        return (false !== strpos($content, 'wp:divi/contact-form'));
    }
}
