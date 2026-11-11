<?php

/**
 * File: /extension/e2pdf-sureforms.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Extension_E2pdf_Sureforms extends Model_E2pdf_Model {

    private $options;
    private $info = [
        'key' => 'sureforms',
        'title' => 'SureForms',
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
        if (defined('E2PDF_SUREFORMS_EXTENSION') || $this->helper->load('extension')->is_plugin_active('sureforms/sureforms.php')) {
            return true;
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
            case 'item':
                $this->set('cached_form', false);
                $this->set('cached_fields', []);
                $form = $this->get('item') ? get_post($this->get('item')) : false;
                if ($form && $form->post_type == 'sureforms_form') {
                    $this->set('cached_form', $form);
                    $this->set('cached_fields', $this->get_form_fields(parse_blocks($form->post_content)));
                }
                break;
            case 'dataset':
                $this->set('cached_entry', false);
                if ($this->get('cached_form') && $this->get('dataset') && class_exists('SRFM\Inc\Database\Tables\Entries')) {
                    if (substr($this->get('dataset'), 0, 4) === 'tmp_') {
                        $entry = [
                            'form_id' => $this->get('cached_form')->ID,
                            'form_data' => get_transient('e2pdf_' . $this->get('dataset')),
                            'user_id' => class_exists('SRFM\Inc\Helper') && method_exists('SRFM\Inc\Helper', 'get_submitting_user_id') ? \SRFM\Inc\Helper::get_submitting_user_id() : get_current_user_id(),
                            'created_at' => current_time('mysql'),
                        ];
                    } else {
                        $entry = \SRFM\Inc\Database\Tables\Entries::get((int) $this->get('dataset'));
                    }
                    if (!empty($entry['form_id']) && $entry['form_id'] == $this->get('cached_form')->ID) {
                        if (!isset($entry['form_data']) || !is_array($entry['form_data'])) {
                            $entry['form_data'] = [];
                        }
                        $this->set('cached_entry', $entry);
                    }
                }
                break;
            default:
                break;
        }
        return true;
    }

    // get
    public function get($key) {
        if (isset($this->options->$key)) {
            $value = $this->options->$key;
        } else {
            switch ($key) {
                case 'args':
                case 'cached_fields':
                    $value = [];
                    break;
                default:
                    $value = false;
                    break;
            }
        }
        return $value;
    }

    // load actions
    public function load_actions() {
        add_action('srfm_form_submit', [$this, 'action_srfm_form_submit'], 99);
    }

    // load filters
    public function load_filters() {
        add_filter('srfm_before_fields_processing', [$this, 'filter_srfm_before_fields_processing'], 99);
        add_filter('srfm_email_notification_should_send', [$this, 'filter_srfm_email_notification_should_send'], 99, 3);
        add_filter('srfm_email_notification', [$this, 'filter_srfm_email_notification'], 99, 4);
        add_filter('srfm_after_submit_confirmation_message', [$this, 'filter_srfm_after_submit_confirmation_message'], 99, 3);
        add_filter('srfm_form_submission_response', [$this, 'filter_srfm_form_submission_response'], 99, 3);
        add_filter('e2pdf_extension_mcp_instructions', [$this, 'filter_e2pdf_extension_mcp_instructions']);
    }

    // extension mcp instructions
    public function filter_e2pdf_extension_mcp_instructions($instructions) {
        $instructions[$this->info['key']] = [
            'values' => [
                'Field values (SureForms smart tags): {form:field-slug}; repeater sub fields: {form:sub-slug} (all rows), {form:sub-slug:1} (row 1 = first), {form:repeater-slug} (table). [e2pdf-for-data]{form:field-slug}[/e2pdf-for-data] loops over multiple values / files / repeater rows.',
                'Entry values: {entry_id}, {form_title}, {ip}, {date_mdy}, {date_dmy}, {browser_name}, {browser_platform}, {user_id}, {user_display_name}, {user_first_name}, {user_last_name}, {user_email}, {user_login}, {site_url}, {site_title}, {admin_email}, {embed_post_id}, {embed_post_title}.',
            ],
            'places' => [
                [
                    'place' => 'Confirmation / success message shown after submit',
                    'location' => 'Form settings: confirmation message',
                    'shortcodes' => ['[e2pdf-download]', '[e2pdf-view]'],
                ],
                [
                    'place' => 'Email notification message (body)',
                    'location' => 'Form settings: Email Notification > email body',
                    'shortcodes' => ['[e2pdf-attachment]', '[e2pdf-download]'],
                ],
            ],
        ];
        return $instructions;
    }

    // items
    public function items() {
        $items = [];
        $forms = get_posts(
                [
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'post_type' => 'sureforms_form',
                ]
        );
        if ($forms) {
            foreach ($forms as $key => $form) {
                $items[] = $this->item($form->ID);
            }
        }
        return $items;
    }

    // item
    public function item($item_id = false) {
        if (!$item_id && $this->get('item')) {
            $item_id = $this->get('item');
        }
        $item = new stdClass();
        $form = $item_id ? get_post($item_id) : false;
        if ($form && $form->post_type == 'sureforms_form') {
            $item->id = (string) $item_id;
            $item->name = $form->post_title ? $form->post_title : $item_id;
            $item->url = $this->helper->get_url(
                    [
                        'post' => $item_id,
                        'action' => 'edit',
                    ], 'post.php?'
            );
        } else {
            $item->id = '';
            $item->name = '';
            $item->url = '';
        }
        return $item;
    }

    // datasets
    public function datasets($item_id = false, $name = false) {
        $datasets = [];
        if ($item_id && class_exists('SRFM\Inc\Database\Tables\Entries')) {
            // entries of the form, without trashed entries
            $entries = \SRFM\Inc\Database\Tables\Entries::get_instance()->get_results(
                    [
                        [
                            [
                                'key' => 'form_id',
                                'compare' => '=',
                                'value' => (int) $item_id,
                            ],
                            [
                                'key' => 'status',
                                'compare' => '!=',
                                'value' => 'trash',
                            ],
                        ],
                    ],
                    'ID',
                    [
                        'ORDER BY ID DESC',
                    ]
            );
            if ($entries) {
                $this->set('item', $item_id);
                foreach ($entries as $key => $entry) {
                    $entry_id = is_array($entry) && isset($entry['ID']) ? $entry['ID'] : $entry;
                    $this->set('dataset', $entry_id);
                    $entry_title = $this->render($name);
                    if (!$entry_title) {
                        $entry_title = $entry_id;
                    }
                    $datasets[] = [
                        'key' => $entry_id,
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
            return false;
        }
        $actions = new stdClass();
        $actions->view = $this->helper->get_url(
                        [
                            'page' => 'sureforms_entries',
                        ], 'admin.php?'
                ) . '#/entry/' . $dataset_id;
        $actions->delete = false;
        return $actions;
    }

    // get template actions
    public function get_template_actions($template = false) {
        $template = (int) $template;
        if (!$template) {
            return;
        }
        $actions = new stdClass();
        $actions->delete = false;
        return $actions;
    }

    // render
    public function render($value, $field = [], $convert_shortcodes = true, $raw = false) {
        $value = $this->render_shortcodes($value, $field);
        if (!$raw) {
            $value = $this->strip_shortcodes($value);
            $value = $this->convert_shortcodes($value, $convert_shortcodes, isset($field['type']) && $field['type'] == 'e2pdf-html' ? true : false);
            $value = $this->helper->load('field')->render_checkbox($value, $this, $field);
            $value = $this->helper->load('field')->render_select_multiline($value, $this, $field);
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
            if (false !== strpos($value, '{')) {
                $entry = $this->get('cached_entry');

                // entry related smart tags
                $submission_info = isset($entry['submission_info']) && is_array($entry['submission_info']) ? $entry['submission_info'] : [];
                $created_at = !empty($entry['created_at']) ? strtotime($entry['created_at']) : false;
                $user = !empty($entry['user_id']) ? get_userdata($entry['user_id']) : false;
                // temporary dataset has no saved entry
                $entry_id = substr($this->get('dataset'), 0, 4) === 'tmp_' ? '' : $this->get('dataset');
                // form fields
                $fields = [
                    '{entry_id}' => $entry_id,
                    '{ip}' => isset($submission_info['user_ip']) ? $submission_info['user_ip'] : '',
                    '{browser_name}' => isset($submission_info['browser_name']) ? $submission_info['browser_name'] : '',
                    '{browser_platform}' => isset($submission_info['device_name']) ? $submission_info['device_name'] : '',
                    '{date_dmy}' => $created_at ? gmdate('d/m/Y', $created_at) : '',
                    '{date_mdy}' => $created_at ? gmdate('m/d/Y', $created_at) : '',
                    '{user_id}' => $user ? $user->ID : '',
                    '{user_display_name}' => $user ? $user->display_name : '',
                    '{user_first_name}' => $user ? $user->first_name : '',
                    '{user_last_name}' => $user ? $user->last_name : '',
                    '{user_email}' => $user ? $user->user_email : '',
                    '{user_login}' => $user ? $user->user_login : '',
                ];
                foreach ($entry['form_data'] as $key => $field_value) {
                    $form_field = $this->parse_field_key($key);
                    if (!$form_field) {
                        continue;
                    }
                    if (0 === strpos($form_field['block_type'], 'srfm-repeater')) {
                        // repeater: {form:repeater} table, {form:sub-field} all rows, {form:sub-field:N} single row
                        $rows = is_array($field_value) ? $field_value : [];
                        $sub_values = [];
                        $for_rows = [];
                        foreach ($rows as $index => $row) {
                            if (!is_array($row)) {
                                continue;
                            }
                            foreach ($row as $sub_key => $sub_value) {
                                $sub_field = $this->parse_field_key($sub_key);
                                if (!$sub_field) {
                                    continue;
                                }
                                $sub_value = $this->get_field_value($sub_field['block_type'], $sub_value);
                                $sub_values[$sub_field['slug']][] = $sub_value;
                                if (!isset($for_rows[$index + 1][$sub_field['slug']])) {
                                    $for_rows[$index + 1][$sub_field['slug']] = $sub_value;
                                }
                                if (!isset($fields['{form:' . $sub_field['slug'] . ':' . ($index + 1) . '}'])) {
                                    $fields['{form:' . $sub_field['slug'] . ':' . ($index + 1) . '}'] = $sub_value;
                                }
                            }
                        }
                        foreach ($sub_values as $sub_slug => $values) {
                            if (!isset($fields['{form:' . $sub_slug . '}'])) {
                                $fields['{form:' . $sub_slug . '}'] = implode(', ', $values);
                            }
                        }
                        if (apply_filters('e2pdf_for_do_shortcode_data_process', false)) {
                            // [e2pdf-for-data] rows keyed by row number: [e2pdf-for-key] matches {form:sub-field:N}
                            $field_value = serialize($for_rows); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                        } else {
                            $field_value = $this->get_repeater_table($rows, $form_field['label']);
                        }
                    } elseif (apply_filters('e2pdf_for_do_shortcode_data_process', false) && (0 === strpos($form_field['block_type'], 'srfm-input-multi-choice') || 0 === strpos($form_field['block_type'], 'srfm-dropdown') || 0 === strpos($form_field['block_type'], 'srfm-upload'))) {
                        // [e2pdf-for-data] multiple choice values and uploaded files
                        $field_value = $this->get_field_value($form_field['block_type'], $field_value);
                        $field_value = serialize($field_value !== '' ? explode(', ', $field_value) : []); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                    } else {
                        $field_value = $this->get_field_value($form_field['block_type'], $field_value);
                    }
                    if (!isset($fields['{form:' . $form_field['slug'] . '}'])) {
                        $fields['{form:' . $form_field['slug'] . '}'] = $field_value;
                    }
                }
                $value = str_replace(array_keys($fields), $fields, $value);

                // general smart tags
                if (false !== strpos($value, '{') && class_exists('SRFM\Inc\Smart_Tags')) {
                    // quiz smart tags are resolved from a per-request result, restore it from the entry
                    $quiz_result = $this->swap_quiz_result(isset($entry['extras']['quiz_result']) && is_array($entry['extras']['quiz_result']) ? $entry['extras']['quiz_result'] : null);
                    // the entry is already stored, so survey results must not count it twice
                    if (class_exists('SRFM_Pro\Inc\Pro\Survey\Survey_Smart_Tags')) {
                        \SRFM_Pro\Inc\Pro\Survey\Survey_Smart_Tags::get_instance()->on_before_entry_save([]);
                    }
                    $processed = \SRFM\Inc\Smart_Tags::get_instance()->process_smart_tags(
                            $value,
                            $entry['form_data'],
                            [
                                'form-id' => $this->get('cached_form')->ID,
                                'entry_id' => $entry_id,
                                'smart_tag_context' => 'redirect',
                                'upload_format_type' => 'raw',
                            ]
                    );
                    $this->swap_quiz_result($quiz_result);
                    if (is_string($processed)) {
                        $value = $processed;
                    }
                }
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

    // parse field key, only fields of the form: submitted keys can be added by the visitor
    public function parse_field_key($key) {
        if (!is_string($key) || false === strpos($key, '-lbl-') || !class_exists('SRFM\Inc\Helper')) {
            return false;
        }
        list($block_type, $label) = explode('-lbl-', $key, 2);
        $label = explode('-', $label);
        $block_id = \SRFM\Inc\Helper::get_block_id_from_key($key);
        $slug = str_replace(' ', '_', implode('-', array_slice($label, 1)));
        $fields = $this->get('cached_fields');
        if (!isset($fields[$block_id]) || $fields[$block_id] !== $slug) {
            return false;
        }
        return [
            'block_type' => $block_type,
            'label' => (string) \SRFM\Inc\Helper::decode($label[0]),
            'slug' => $slug,
        ];
    }

    // form fields: block id => slug
    public function get_form_fields($blocks = []) {
        $fields = [];
        foreach ($blocks as $block) {
            if (!empty($block['blockName']) && 0 === strpos($block['blockName'], 'srfm/') && !empty($block['attrs']['block_id']) && !empty($block['attrs']['slug'])) {
                $fields[(string) $block['attrs']['block_id']] = (string) $block['attrs']['slug'];
            }
            if (!empty($block['innerBlocks'])) {
                $fields += $this->get_form_fields($block['innerBlocks']);
            }
        }
        return $fields;
    }

    // get field value
    public function get_field_value($block_type, $field_value) {
        if (is_array($field_value)) {
            if (0 === strpos($block_type, 'srfm-upload')) {
                $field_value = array_map('rawurldecode', $field_value);
            }
            $field_value = implode(', ', array_filter($field_value, 'is_scalar'));
        } elseif (0 === strpos($block_type, 'srfm-input-multi-choice') || 0 === strpos($block_type, 'srfm-dropdown')) {
            // multiple choices are stored as "Option 1 | Option 2"
            $field_value = str_replace(' | ', ', ', (string) $field_value);
        }
        return (string) $field_value;
    }

    // get repeater table
    public function get_repeater_table($rows, $label = '') {
        $html = '';
        $cell = 'border:1px solid #cccccc;padding:4px;';
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $html .= '<tr><th colspan="2" style="' . $cell . 'text-align:left;">' . esc_html(sprintf('%s #%d', $label, $index + 1)) . '</th></tr>';
            foreach ($row as $sub_key => $sub_value) {
                $sub_field = $this->parse_field_key($sub_key);
                if (!$sub_field) {
                    continue;
                }
                $sub_value = $this->get_field_value($sub_field['block_type'], $sub_value);
                if ('' === $sub_value) {
                    continue;
                }
                $html .= '<tr><td style="' . $cell . '">' . esc_html($sub_field['label']) . '</td><td style="' . $cell . '">' . esc_html(wp_specialchars_decode($sub_value, ENT_QUOTES)) . '</td></tr>';
            }
        }
        return $html ? '<table class="sureforms-repeater" style="width:100%;border-collapse:collapse;">' . $html . '</table>' : '';
    }

    // swap quiz result
    public function swap_quiz_result($quiz_result) {
        $class = 'SRFM_Pro\Inc\Business\Quiz\Score_Calculator';
        if (!class_exists($class) || !property_exists($class, 'quiz_result')) {
            return null;
        }
        $swap = Closure::bind(
                static function ($quiz_result) {
                    $previous = self::$quiz_result;
                    self::$quiz_result = $quiz_result;
                    return $previous;
                }, null, $class
        );
        return $swap($quiz_result);
    }

    // strip shortcodes
    public function strip_shortcodes($value) {
        $value = preg_replace('~(?:\[/?)[^/\]]+/?\]~s', '', $value);
        $value = preg_replace('~a\:\d+\:{[^}]*}(*SKIP)(*FAIL)|{[^}]*}~', '', $value);
        return $value;
    }

    // strip math
    public function strip_math($value, &$replacements) {
        if ($value) {
            preg_match_all('/\{[^}]+\}/', $value, $matches);
            foreach ($matches[0] as $match) {
                $index = count($replacements) + 1;
                $replacements[] = $match;
                $value = str_replace($match, '%' . $index . '$s', $value);
            }
        }
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

    // verify
    public function verify() {
        if ($this->get('cached_entry')) {
            return true;
        }
        return false;
    }

    // record attributes the user has no access to: the loaded entry is allowed for its owner or entries capability
    public function access($atts = [], $user_id = 0) {
        if (isset($atts['dataset'])) {
            $entry = $this->get('cached_entry');
            if ((!empty($entry['user_id']) && (int) $entry['user_id'] === $user_id) || user_can($user_id, 'manage_options')) {
                unset($atts['dataset']);
            }
        }
        return $atts;
    }

    // visual mapper
    public function visual_mapper() {

        $html = '';
        $source = '';

        if ($this->get('cached_form') && class_exists('SRFM\Inc\Generate_Form_Markup')) {
            $source = \SRFM\Inc\Generate_Form_Markup::get_form_markup($this->get('cached_form')->ID, false, '', 'sureforms_form', true);
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

                // remove by class
                $remove_by_class = [
                    'srfm-page-break-header-container',
                    'srfm-page-break-buttons',
                    'srfm-submit-container',
                    'srfm-common-error-message',
                    'srfm-error-wrap',
                    'srfm-success-box',
                    'srfm-repeater-template',
                    'srfm-repeater-item-controls',
                    'srfm-time-picker',
                    'srfm-nps-buttons',
                    'srfm-nps-labels',
                    'srfm-slider-wrap',
                    'srfm-signature-buttons',
                    'srfm-signature-upload',
                    'srfm-input-signature',
                    'srfm-upload-size',
                    'srfm-upload-data',
                    'srfm-upload-icon',
                    'srfm-input-icon',
                    'srfm-repeater-index',
                    'srfm-multi-choice-icon',
                    'srfm-multi-choice-icon-unchecked',
                    'wp-block-uagb-icon',
                    'wp-block-uagb-image',
                    'wp-block-uagb-separator',
                ];
                foreach ($remove_by_class as $key => $class) {
                    $elements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]");
                    foreach ($elements as $element) {
                        $element->parentNode->removeChild($element);
                    }
                }

                // remove by tag
                $remove_by_tag = [
                    'script',
                    'noscript',
                    'button',
                ];
                foreach ($remove_by_tag as $key => $tag) {
                    $elements = $xpath->query('//' . $tag);
                    foreach ($elements as $element) {
                        $element->parentNode->removeChild($element);
                    }
                }

                // rating icons
                $elements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' srfm-rating-block ')]//ul");
                foreach ($elements as $element) {
                    $element->parentNode->removeChild($element);
                }

                // the visual mapper is placed inside a form, nested forms are dropped by the browser
                $elements = $xpath->query('//form');
                foreach ($elements as $element) {
                    $div = $dom->createElement('div');
                    foreach ($element->attributes as $attr) {
                        if (in_array($attr->nodeName, ['id', 'class', 'form-id'], true)) {
                            $div->setAttribute($attr->nodeName, $attr->nodeValue);
                        }
                    }
                    while ($element->firstChild) {
                        $div->appendChild($element->firstChild);
                    }
                    $element->parentNode->replaceChild($div, $element);
                }

                // show all pages
                $elements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' srfm-page-break ')]");
                foreach ($elements as $element) {
                    $xml->set_node_value($element, 'style', '');
                }

                // multi choice options get the name of the hidden input and the option text as value
                $multi_choices = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' srfm-multi-choice-block ')]");
                foreach ($multi_choices as $multi_choice) {
                    $hidden = $xpath->query(".//input[contains(concat(' ', normalize-space(@class), ' '), ' srfm-input-multi-choice-hidden ')]", $multi_choice)->item(0);
                    if (!$hidden) {
                        continue;
                    }
                    $radio = false !== strpos($xml->get_node_value($multi_choice, 'class'), 'srfm-radio-mode');
                    $options = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' srfm-multi-choice-single ')]", $multi_choice);
                    foreach ($options as $option) {
                        $input = $xpath->query('.//input', $option)->item(0);
                        $label = $xpath->query('.//label', $option)->item(0);
                        if ($input && $label) {
                            $xml->set_node_value($input, 'type', $radio ? 'radio' : 'checkbox');
                            $xml->set_node_value($input, 'name', $xml->get_node_value($hidden, 'name'));
                            $xml->set_node_value($input, 'value', $label->attributes->getNamedItem('data-option-text') ? $xml->get_node_value($label, 'data-option-text') : trim($label->nodeValue));
                        }
                    }
                    $hidden->parentNode->removeChild($hidden);
                }

                // dropdowns
                $elements = $xpath->query("//input[contains(concat(' ', normalize-space(@class), ' '), ' srfm-input-dropdown-hidden ')]");
                foreach ($elements as $element) {
                    $element->parentNode->removeChild($element);
                }
                $selects = $xpath->query('//select');
                foreach ($selects as $select) {
                    if ($xml->get_node_value($select, 'data-multiple') == 'true') {
                        $xml->set_node_value($select, 'multiple', 'multiple');
                    }
                    $options = $xpath->query('.//option', $select);
                    foreach ($options as $option) {
                        if ($xml->get_node_value($option, 'value') === '') {
                            $option->parentNode->removeChild($option);
                        }
                    }
                }

                // replace names on inputs
                $inputs = $xpath->query('//input|//textarea|//select');
                foreach ($inputs as $element) {
                    $name = preg_replace('/\[\]$/', '', $xml->get_node_value($element, 'name'));
                    // repeater sub fields: parent[index][sub-field] are mapped to the first row
                    $repeater = '';
                    if (preg_match('/\[([^\[\]]+)(?:\[\])?\]$/', $name, $matches)) {
                        $name = $matches[1];
                        $repeater = ':1';
                    }
                    $form_field = $this->parse_field_key($name);
                    if (!$form_field) {
                        $element->parentNode->removeChild($element);
                        continue;
                    }
                    $class = $xml->get_node_value($element, 'class');
                    if (false !== strpos($class, 'srfm-input-rating') || false !== strpos($class, 'srfm-input-nps')) {
                        $xml->set_node_value($element, 'type', 'text');
                    } elseif (false !== strpos($class, 'srfm-input-checkbox') || false !== strpos($class, 'srfm-input-gdpr')) {
                        $xml->set_node_value($element, 'value', 'on');
                    }
                    $xml->set_node_value($element, 'class', trim(str_replace('screen-reader-text', '', $class)));
                    $xml->set_node_value($element, 'name', '{form:' . $form_field['slug'] . $repeater . '}');
                }

                return $dom->saveHTML();
            }
        }
        return false;
    }

    // auto
    public function auto() {
        $response = [];
        $elements = [];
        if ($this->get('cached_form')) {
            foreach ($this->get_blocks(parse_blocks($this->get('cached_form')->post_content)) as $block) {
                $elements = $this->auto_fields($elements, $block);
            }
        }
        $response['page'] = [
            'bottom' => '20',
            'top' => '20',
        ];
        $response['elements'] = $elements;
        return $response;
    }

    // get blocks
    public function get_blocks($blocks = []) {
        $found = [];
        foreach ($blocks as $block) {
            if (!empty($block['blockName']) && 0 === strpos($block['blockName'], 'srfm/')) {
                $found[] = $block;
            }
            // repeater sub fields are rendered with the repeater
            if (!empty($block['innerBlocks']) && $block['blockName'] != 'srfm/repeater') {
                $found = array_merge($found, $this->get_blocks($block['innerBlocks']));
            }
        }
        return $found;
    }

    // auto fields
    public function auto_fields($elements = [], $block = [], $repeater = '') {
        $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
        $block_name = $block['blockName'];

        // attributes left at their default values (label, options, etc.) are not stored in the block
        $block_type = WP_Block_Type_Registry::get_instance()->get_registered($block_name);
        if ($block_type && is_array($block_type->attributes)) {
            foreach ($block_type->attributes as $attr_key => $attr) {
                if (!array_key_exists($attr_key, $attrs) && isset($attr['default'])) {
                    $attrs[$attr_key] = $attr['default'];
                }
            }
        }

        $field_label = isset($attrs['label']) ? $attrs['label'] : '';
        $field_value = !empty($attrs['slug']) ? '{form:' . $attrs['slug'] . $repeater . '}' : '';

        if (!$field_value && $block_name != 'srfm/address' && $block_name != 'srfm/html') {
            return $elements;
        }

        // dynamic options (posts, taxonomies, users)
        if (in_array($block_name, ['srfm/dropdown', 'srfm/multi-choice'], true)) {
            $attrs['options'] = apply_filters(
                    'srfm_field_options',
                    isset($attrs['options']) ? $attrs['options'] : '',
                    array_merge(
                            $attrs,
                            [
                                'block_args' => [
                                    'slug' => substr($block_name, 5),
                                ],
                            ]
                    )
            );
        }

        $label = $this->auto_field(
                $block,
                [
                    'type' => 'e2pdf-html',
                    'block' => true,
                    'float' => true,
                    'properties' => [
                        'top' => '20',
                        'left' => '20',
                        'right' => '20',
                        'width' => '100%',
                        'height' => 'auto',
                        'value' => $field_label,
                    ],
                ]
        );

        switch ($block_name) {
            case 'srfm/address':
                $elements[] = $label;
                break;
            case 'srfm/html':
                if (!empty($attrs['htmlContent'])) {
                    $elements[] = $this->auto_field(
                            $block,
                            [
                                'type' => 'e2pdf-html',
                                'block' => true,
                                'properties' => [
                                    'top' => '20',
                                    'left' => '20',
                                    'right' => '20',
                                    'width' => '100%',
                                    'height' => 'auto',
                                    'value' => $attrs['htmlContent'],
                                ],
                            ]
                    );
                }
                break;
            case 'srfm/repeater':
                // sub fields are mapped to the first row: {form:sub-field:1}
                $elements[] = $label;
                if (!empty($block['innerBlocks'])) {
                    foreach ($this->get_blocks($block['innerBlocks']) as $sub_block) {
                        $elements = $this->auto_fields($elements, $sub_block, ':1');
                    }
                }
                break;
            case 'srfm/textarea':
                $elements[] = $label;
                $elements[] = $this->auto_field(
                        $block,
                        [
                            'type' => 'e2pdf-textarea',
                            'properties' => [
                                'top' => '5',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_value,
                            ],
                        ]
                );
                break;
            case 'srfm/dropdown':
                $elements[] = $label;
                $field_options = [];
                if (!empty($attrs['options']) && is_array($attrs['options'])) {
                    foreach ($attrs['options'] as $option) {
                        $field_options[] = isset($option['label']) ? $option['label'] : '';
                    }
                }
                $multiple = !empty($attrs['multiSelect']);
                $elements[] = $this->auto_field(
                        $block,
                        [
                            'type' => 'e2pdf-select',
                            'properties' => [
                                'top' => '5',
                                'width' => '100%',
                                'height' => $multiple ? '80' : 'auto',
                                'multiline' => $multiple ? '1' : '0',
                                'options' => implode("\n", $field_options),
                                'value' => $field_value,
                            ],
                        ]
                );
                break;
            case 'srfm/multi-choice':
                $elements[] = $label;
                $options = !empty($attrs['options']) && is_array($attrs['options']) ? $attrs['options'] : [];
                foreach ($options as $option) {
                    $option_value = isset($option['optionTitle']) ? $option['optionTitle'] : '';
                    if (!empty($attrs['singleSelection'])) {
                        $elements[] = $this->auto_field(
                                $block,
                                [
                                    'type' => 'e2pdf-radio',
                                    'properties' => [
                                        'top' => '5',
                                        'width' => 'auto',
                                        'height' => 'auto',
                                        'value' => $field_value,
                                        'option' => $option_value,
                                        'group' => $field_value,
                                    ],
                                ]
                        );
                    } else {
                        $elements[] = $this->auto_field(
                                $block,
                                [
                                    'type' => 'e2pdf-checkbox',
                                    'properties' => [
                                        'top' => '5',
                                        'width' => 'auto',
                                        'height' => 'auto',
                                        'value' => $field_value,
                                        'option' => $option_value,
                                    ],
                                ]
                        );
                    }
                    $elements[] = $this->auto_field(
                            $block,
                            [
                                'type' => 'e2pdf-html',
                                'float' => true,
                                'properties' => [
                                    'left' => '5',
                                    'width' => '100%',
                                    'height' => 'auto',
                                    'value' => $option_value,
                                ],
                            ]
                    );
                }
                break;
            case 'srfm/checkbox':
            case 'srfm/gdpr':
                $elements[] = $label;
                $elements[] = $this->auto_field(
                        $block,
                        [
                            'type' => 'e2pdf-checkbox',
                            'properties' => [
                                'top' => '5',
                                'width' => 'auto',
                                'height' => 'auto',
                                'value' => $field_value,
                                'option' => 'on',
                            ],
                        ]
                );
                break;
            case 'srfm/upload':
                $elements[] = $label;
                $elements[] = $this->auto_field(
                        $block,
                        [
                            'type' => 'e2pdf-textarea',
                            'properties' => [
                                'top' => '5',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_value,
                                'text_auto_font_size' => '1',
                            ],
                        ]
                );
                break;
            case 'srfm/signature':
                $elements[] = $label;
                $elements[] = $this->auto_field(
                        $block,
                        [
                            'type' => 'e2pdf-signature',
                            'properties' => [
                                'top' => '5',
                                'width' => '100%',
                                'height' => '150',
                                'dimension' => '1',
                                'block_dimension' => '1',
                                'value' => $field_value,
                            ],
                        ]
                );
                break;
            case 'srfm/hidden':
            case 'srfm/inline-button':
            case 'srfm/payment':
            case 'srfm/payment-history':
            case 'srfm/page-break':
            case 'srfm/password':
            case 'srfm/login':
            case 'srfm/register':
            case 'srfm/lost-password':
            case 'srfm/reset-password':
            case 'srfm/link':
                break;
            default:
                $elements[] = $label;
                $elements[] = $this->auto_field(
                        $block,
                        [
                            'type' => 'e2pdf-input',
                            'properties' => [
                                'top' => '5',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_value,
                            ],
                        ]
                );
                break;
        }
        return $elements;
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
        return $element;
    }

    // styles
    public function styles($item_id = false) {
        $styles = [];
        if (defined('SRFM_URL') && defined('SRFM_VER')) {
            $styles[] = SRFM_URL . 'assets/css/minified/common.min.css?ver=' . SRFM_VER;
            $styles[] = SRFM_URL . 'assets/css/minified/blocks/default/frontend.min.css?ver=' . SRFM_VER;
            $styles[] = SRFM_URL . 'assets/css/minified/frontend/form.min.css?ver=' . SRFM_VER;
        }
        $styles[] = plugins_url('css/extension/sureforms.css?v=' . time(), $this->helper->get('plugin_file_path'));
        return $styles;
    }

    // form submit action
    public function action_srfm_form_submit($response) {
        remove_filter('wp_mail', [$this, 'filter_wp_mail'], 99);
        $this->set('srfm_mail_attachments', false);
        // all form mails are sent, tmp attachments are removed
        $files = $this->helper->get('tmp_sureforms_attachments');
        if (is_array($files) && !empty($files)) {
            foreach ($files as $file) {
                $this->helper->delete_tmp_dir(basename(dirname($file)));
            }
            $this->helper->deset('tmp_sureforms_attachments');
        }
    }

    // before fields processing filter
    public function filter_srfm_before_fields_processing($form_data) {
        // e2pdf shortcodes are removed from submitted values
        return $this->sanitize($form_data);
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

    // email notification should send filter
    public function filter_srfm_email_notification_should_send($email_notification, $submission_data, $form_data) {
        $this->set('srfm_attachments', []);
        if (is_array($email_notification)) {
            $dataset = is_array($form_data) && !empty($form_data['entry_id']) ? $form_data['entry_id'] : false;
            $form_id = is_array($form_data) && !empty($form_data['form-id']) ? (int) $form_data['form-id'] : 0;
            $attachments = [];
            foreach ($email_notification as $notification_key => $notification) {
                if (!is_array($notification)) {
                    continue;
                }
                foreach ($notification as $item_key => $item) {
                    // email body is processed before smart tags, so shortcodes within submitted values are not processed
                    if (!empty($item['status']) && !empty($item['email_body']) && is_string($item['email_body']) && false !== strpos($item['email_body'], '[e2pdf-')) {
                        $this->helper->deset('sureforms_attachments');
                        $email_notification[$notification_key][$item_key]['email_body'] = $this->filter_message($item['email_body'], $dataset, 'mail', $form_id, $submission_data);
                        $files = $this->helper->get('sureforms_attachments');
                        if (is_array($files) && !empty($files)) {
                            $attachments[isset($item['id']) ? $item['id'] : $item_key] = $files;
                        }
                        $this->helper->deset('sureforms_attachments');
                    }
                }
            }
            $this->set('srfm_attachments', $attachments);
        }
        return $email_notification;
    }

    // email notification filter
    public function filter_srfm_email_notification($parsed, $submission_data, $item, $form_data) {
        remove_filter('wp_mail', [$this, 'filter_wp_mail'], 99);
        $this->set('srfm_mail_attachments', false);
        $attachments = $this->get('srfm_attachments');
        $key = isset($item['id']) ? $item['id'] : false;
        if (false !== $key && !empty($attachments[$key])) {
            $this->set('srfm_mail_attachments', $attachments[$key]);
            add_filter('wp_mail', [$this, 'filter_wp_mail'], 99);
        }
        return $parsed;
    }

    // wp mail filter
    public function filter_wp_mail($args) {
        $files = $this->get('srfm_mail_attachments');
        if (is_array($files) && !empty($files)) {
            if (!is_array($args['attachments'])) {
                $args['attachments'] = $args['attachments'] ? explode("\n", str_replace("\r\n", "\n", $args['attachments'])) : [];
            }
            foreach ($files as $file) {
                $args['attachments'][] = $file;
            }
        }
        $this->set('srfm_mail_attachments', false);
        return $args;
    }

    // confirmation message filter: the entry is not stored, the e2pdf shortcodes of the form are removed
    public function filter_srfm_after_submit_confirmation_message($message, $form_data, $submission_data) {
        if (is_string($message) && false !== strpos($message, '[e2pdf-') && is_array($form_data) && empty($form_data['entry_id'])) {
            $form_id = !empty($form_data['form-id']) ? (int) $form_data['form-id'] : 0;
            $message = $this->filter_message($message, false, 'message', $form_id);
        }
        return $message;
    }

    // form submission response filter: the entry is stored, runs after the confirmation message is cleaned from js attributes
    public function filter_srfm_form_submission_response($response, $form_data, $submission_data) {
        if (!empty($response['data']['submission_id']) && !empty($response['message']) && is_string($response['message']) && false !== strpos($response['message'], '[e2pdf-')) {
            $form_id = is_array($form_data) && !empty($form_data['form-id']) ? (int) $form_data['form-id'] : 0;
            $response['message'] = $this->filter_message($response['message'], $response['data']['submission_id'], 'message', $form_id);
        }
        return $response;
    }

    // filter message
    public function filter_message($message = '', $dataset = '', $type = 'message', $form_id = 0, $submission_data = []) {
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
                    $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                    $atts = shortcode_parse_atts($shortcode[3]);
                    $hash_data = [];
                    $transient = false;
                    if (isset($atts['id'])) {
                        $template = new Model_E2pdf_Template();
                        $template->load($atts['id']);
                        if ($template->get('extension') === 'sureforms' && $template->get('item') == $form_id) {
                            if ($dataset && (!isset($atts['dataset']) || (string) $atts['dataset'] === (string) $dataset)) {
                                $this->helper->load('shortcode')->set($shortcode, $atts, ['dataset' => $dataset]);
                                $hash_data[] = 'dataset';
                            } elseif (!$dataset && !isset($atts['dataset'])) {
                                // entry is not stored: attachments are created from the submission, other shortcodes are removed
                                if ($type == 'mail' && $this->helper->load('shortcode')->is_attachment($shortcode, $atts)) {
                                    $tmp_dataset = 'tmp_' . $this->helper->load('encryption')->random_md5();
                                    $transient = 'e2pdf_' . $tmp_dataset;
                                    set_transient($transient, $submission_data, 1800);
                                    $this->helper->load('shortcode')->set($shortcode, $atts, ['dataset' => $tmp_dataset]);
                                    $hash_data[] = 'dataset';
                                } else {
                                    $message = str_replace($shortcode_value, '', $message);
                                    continue;
                                }
                            }
                        }
                    }
                    $this->helper->load('shortcode')->add($shortcode, $atts, ['apply' => 'true']);
                    if ($type == 'message') {
                        $this->helper->load('shortcode')->add($shortcode, $atts, ['iframe_download' => 'true']);
                    }
                    $this->helper->load('shortcode')->add($shortcode, $atts, ['hash' => $this->helper->load('shortcode')->hash($hash_data, ($type === 'mail' ? 'sureforms_mail' : 'sureforms_success_message'), $atts)]);
                    if ($this->helper->load('shortcode')->is_attachment($shortcode, $atts)) {
                        if ($type == 'mail') {
                            $this->helper->load('shortcode')->add($shortcode, $atts, ['is_tmp' => 'true']);
                            $file = do_shortcode_tag($shortcode);
                            if ($file) {
                                $tmp = substr($file, 0, 4) === 'tmp:';
                                $file = $tmp ? substr($file, 4) : $file;
                                if (is_file($file)) {
                                    if ($tmp) {
                                        $this->helper->add('tmp_sureforms_attachments', $file);
                                    }
                                    $this->helper->add('sureforms_attachments', $file);
                                }
                            }
                        }
                        if ($transient) {
                            delete_transient($transient);
                        }
                        $message = str_replace($shortcode_value, '', $message);
                    } else {
                        $message = str_replace($shortcode_value, do_shortcode_tag($shortcode), $message);
                    }
                }
            }
        }
        return $message;
    }
}
