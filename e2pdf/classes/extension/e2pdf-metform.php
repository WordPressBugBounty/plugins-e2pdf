<?php

/**
 * File: /extension/e2pdf-metform.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Extension_E2pdf_Metform extends Model_E2pdf_Model {

    private $options;
    private $info = array(
        'key' => 'metform',
        'title' => 'MetForm',
    );

    // info
    public function info($key = false) {
        if ($key && isset($this->info[$key])) {
            return $this->info[$key];
        } else {
            return array(
                $this->info['key'] => $this->info['title'],
            );
        }
    }

    // active
    public function active() {
        if (defined('E2PDF_METFORM_EXTENSION') || $this->helper->load('extension')->is_plugin_active('metform/metform.php')) {
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
                $this->set('cached_form', get_post($this->get('item')));
                break;
            case 'dataset':
                $this->set('cached_entry', false);
                if ($this->get('cached_form') && $this->get('dataset')) {
                    if (get_post_meta($this->get('dataset'), 'metform_entries__form_id', true) == $this->get('cached_form')->ID) {
                        $form_data = get_post_meta($this->get('dataset'), 'metform_entries__form_data', true);
                        if (is_array($form_data)) {
                            foreach ($form_data as $data_key => $data) {
                                if (is_array($data) && $this->is_repeater($data)) {
                                    $raw = array();
                                    foreach ($data as $sub_data_key => $sub_data) {
                                        $form_data[$data_key . '-' . $sub_data_key] = $sub_data;
                                        if (preg_match('/-(\d+)$/', $sub_data_key, $matches)) {
                                            $raw[$matches[1]][$sub_data_key] = $sub_data;
                                        }
                                    }
                                    $form_data[$data_key] = serialize($raw); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
                                }
                            }
                            $uploads_data = get_post_meta($this->get('dataset'), 'metform_entries__file_upload_new', true);
                            if (is_array($uploads_data)) {
                                foreach ($uploads_data as $data_key => $data) {
                                    $uploads = array();
                                    if (is_array($data)) {
                                        foreach ($data as $upload) {
                                            $uploads[] = $upload['url'];
                                        }
                                    }
                                    $form_data[$data_key] = implode(',', $uploads);
                                }
                            }
                            if (!isset($form_data['mf_id'])) {
                                $form_data['mf_id'] = $this->get('dataset');
                            }
                            $post = get_post($this->get('dataset'));
                            if ($post) {
                                if (!isset($form_data['mf_post_date'])) {
                                    $form_data['mf_post_date'] = $post->post_date;
                                }
                                if (!isset($form_data['mf_post_date_gmt'])) {
                                    $form_data['mf_post_date_gmt'] = $post->post_date_gmt;
                                }
                                if (!isset($form_data['mf_post_modified'])) {
                                    $form_data['mf_post_modified'] = $post->post_modified;
                                }
                                if (!isset($form_data['mf_post_modified_gmt'])) {
                                    $form_data['mf_post_modified_gmt'] = $post->post_modified_gmt;
                                }
                            }
                            if (!isset($form_data['mf_form_name'])) {
                                $form_data['mf_form_name'] = get_the_title($this->get('cached_form')->ID);
                            }
                            if (!isset($form_data['mf_user_id'])) {
                                $form_data['mf_user_id'] = get_post_meta($this->get('dataset'), 'metform_entries__user_id', true);
                            }
                            if (!isset($form_data['mf_payment_status'])) {
                                $form_data['mf_payment_status'] = get_post_meta($this->get('dataset'), 'metform_entries__payment_status', true);
                            }
                            $browser_data = get_post_meta($this->get('dataset'), 'metform_form__entry_browser_data', true);
                            if (is_array($browser_data)) {
                                foreach ($browser_data as $browser_key => $browser) {
                                    if (!isset($form_data['mf_browser_' . $browser_key])) {
                                        $form_data['mf_browser_' . $browser_key] = $browser;
                                    }
                                }
                            }
                            $cached_entry = \MetForm\Core\Entries\Metform_Shortcode::instance();
                            $cached_entry->set_values($form_data);
                            $this->set('cached_entry', $cached_entry);
                        }
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
                    $value = array();
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
        add_action('metform_after_store_form_data', array($this, 'action_metform_after_store_form_data'));
        add_action('add_meta_boxes', array($this, 'hook_metform_entry_view'));
    }

    // load filters
    public function load_filters() {
        add_filter('post_row_actions', array($this, 'hook_metform_row_actions'), 10, 2);
        add_filter('manage_metform-entry_posts_columns', array($this, 'hook_metform_entry_row_column'), 11);
    }

    // items
    public function items() {
        $items = array();
        $forms = get_posts(
                array(
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'post_type' => 'metform-form',
                )
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
        $form = get_post($item_id);
        if ($form) {
            $item->id = (string) $item_id;
            $item->name = $form->post_title ? $form->post_title : $item_id;
            $item->url = $this->helper->get_url(
                    array(
                        'post' => $item_id,
                        'action' => 'elementor',
                    ), 'post.php?'
            );
        } else {
            $item->id = '';
            $item->name = '';
            $item->url = 'javascript:void(0);';
        }
        return $item;
    }

    // datasets
    public function datasets($item_id = false, $name = false) {
        global $wpdb;

        $datasets = array();
        $entries = $wpdb->get_results($wpdb->prepare('SELECT `post_id`  FROM `' . $wpdb->prefix . "postmeta` WHERE `meta_key` = 'metform_entries__form_id' AND `meta_value` = %d ORDER BY `post_id` DESC", $item_id), OBJECT);
        $this->set('item', $item_id);
        foreach ($entries as $key => $entry) {
            $this->set('dataset', $entry->post_id);
            $entry_title = $this->render($name);
            if (!$entry_title) {
                $entry_title = $entry->post_id;
            }
            $datasets[] = array(
                'key' => $entry->post_id,
                'value' => $entry_title,
            );
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
                array(
                    'post' => $dataset_id,
                    'action' => 'edit',
                ), 'post.php?'
        );
        $actions->delete = false;
        return $actions;
    }

    // render
    public function render($value, $field = array(), $convert_shortcodes = true, $raw = false) {
        $value = $this->render_shortcodes($value, $field);
        if (!$raw) {
            $value = $this->strip_shortcodes($value);
            $value = $this->convert_shortcodes($value, $convert_shortcodes, isset($field['type']) && $field['type'] == 'e2pdf-html' ? true : false);
            $value = $this->helper->load('field')->render_checkbox($value, $this, $field, ',');
            $value = $this->helper->load('field')->render_select_multiline($value, $this, $field, ',');
        }
        return $value;
    }

    // render shortcodes
    public function render_shortcodes($value, $field = array()) {
        $element_id = isset($field['element_id']) ? $field['element_id'] : false;
        if ($this->verify()) {
            if (false !== strpos($value, '[')) {
                $value = $this->helper->load('field')->pre_shortcodes($value, $this, $field);
                $value = $this->helper->load('field')->inner_shortcodes($value, $this, $field);
                $value = $this->helper->load('field')->wrapper_shortcodes($value, $this, $field);
            }

            $value = $this->helper->load('field')->do_shortcodes($value, $this, $field);

            $value = $this->get('cached_entry')->get_process_shortcode($value);

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

    // visual mapper
    public function visual_mapper() {
        $html = '';
        if ($this->get('item')) {
            $form = $this->auto();
            $html = '<div class="metform-form">';
            $float = false;
            foreach ($form['elements'] as $element) {
                switch ($element['type']) {
                    case 'e2pdf-html':
                        $html .= '<div>' . $element['properties']['value'] . '</div>';
                        if ($float) {
                            $html .= '<div class="clearfix"></div>';
                            $float = false;
                        }
                        break;
                    case 'e2pdf-input':
                        $html .= '<div><input type="text" name="' . $element['properties']['value'] . '"></div>';
                        break;
                    case 'e2pdf-image':
                    case 'e2pdf-signature':
                    case 'e2pdf-textarea':
                        $html .= '<div><textarea name="' . $element['properties']['value'] . '"></textarea></div>';
                        break;
                    case 'e2pdf-select':
                        $html .= '<div>';
                        $html .= '<select name="' . $element['properties']['value'] . '">';
                        $options = explode("\n", $element['properties']['options']);
                        foreach ($options as $option) {
                            $html .= '<option>' . $option . '</option>';
                        }
                        $html .= '</select>';
                        $html .= '</div>';
                        break;
                    case 'e2pdf-checkbox':
                        $html .= '<div class="metform-checkbox"><input type="checkbox" name="' . $element['properties']['value'] . '" value="' . $element['properties']['option'] . '"></div>';
                        $float = true;
                        break;
                    case 'e2pdf-radio':
                        $html .= '<div class="metform-radio"><input type="radio" name="' . $element['properties']['value'] . '" value="' . $element['properties']['option'] . '"></div>';
                        $float = true;
                        break;
                    default:
                        break;
                }
            }
            $html .= '</div>';
            return $html;
        }
        return false;
    }

    // auto
    public function auto() {
        $response = array();
        $elements = array();
        $fields = array();

        $fields = \MetForm\Core\Entries\Action::instance()->get_fields($this->get('item'));
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $elements = $this->auto_fields($elements, $field);
            }
        }
        $response['page'] = array(
            'bottom' => '20',
            'top' => '20',
        );
        $response['elements'] = $elements;
        return $response;
    }

    // auto fields
    public function auto_fields($elements = array(), $field = false, $repeater = '') {
        if ($repeater) {
            $field_value = '[' . $repeater->mf_input_name . '-' . $field->mf_input_repeater_name . '-1]';
            $field_label = isset($field->mf_input_repeater_label) ? $field->mf_input_repeater_label : '';
            $field_type = isset($field->mf_input_repeater_type) ? 'mf-' . $field->mf_input_repeater_type : 'mf-text';

            if ($field_type == 'mf-radio' || $field_type == 'mf-checkbox' || $field_type == 'mf-select') {
                $field->mf_input_list = array();
                if (!empty($field->mf_input_repeater_option)) {
                    $options = preg_split('/\r?\n/', $field->mf_input_repeater_option);
                    foreach ($options as $option) {
                        $option_label_value = explode('|', $option);
                        if (count($option_label_value) == 2) {
                            $opt = new \stdClass();
                            $opt->mf_input_option_value = $option_label_value[1];
                            $opt->mf_input_option_text = $option_label_value[0];
                            $field->mf_input_list[] = $opt;
                        }
                    }
                }
            } elseif ($field_type == 'mf-switch') {
                $field->mf_swtich_enable_text = '1';
            }
        } else {
            $field_value = '[' . $field->mf_input_name . ']';
            $field_label = isset($field->mf_input_label) ? $field->mf_input_label : '';
            $field_type = isset($field->widgetType) ? $field->widgetType : 'mf-text';
        }
        switch ($field_type) {
            case 'mf-text':
            case 'mf-email':
            case 'mf-number':
            case 'mf-telephone':
            case 'mf-date':
            case 'mf-time':
            case 'mf-range':
            case 'mf-url':
            case 'mf-password':
            case 'mf-listing-fname':
            case 'mf-listing-lname':
            case 'mf-rating':
            case 'mf-mobile':
            case 'mf-calculation':
            case 'mf-color-picker':
            case 'mf-payment-method':
            case 'mf-credit-card':
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'block' => true,
                            'float' => true,
                            'properties' => array(
                                'top' => '20',
                                'left' => '20',
                                'right' => '20',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_label,
                            ),
                        )
                );
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-input',
                            'properties' => array(
                                'top' => '5',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_value,
                            ),
                        )
                );
                break;
            case 'mf-select':
            case 'mf-multi-select':
            case 'mf-image-select':
            case 'mf-toggle-select':
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'block' => true,
                            'float' => true,
                            'properties' => array(
                                'top' => '20',
                                'left' => '20',
                                'right' => '20',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_label,
                            ),
                        )
                );
                $field_options = array();
                foreach ($field->mf_input_list as $option) {
                    if ($field_type == 'mf-multi-select') {
                        $option_value = isset($option->value) ? $option->value : '';
                    } else {
                        $option_value = isset($option->mf_input_option_value) ? $option->mf_input_option_value : '';
                    }
                    $field_options[] = $option_value;
                }

                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-select',
                            'properties' => array(
                                'top' => '5',
                                'width' => '100%',
                                'height' => 'auto',
                                'options' => implode("\n", $field_options),
                                'value' => $field_value,
                                'height' => $field_type == 'mf-multi-select' ? '80' : 'auto',
                                'multiline' => $field_type == 'mf-multi-select' ? '1' : '0',
                            ),
                        )
                );
                break;
            case 'mf-radio':
            case 'mf-switch':
            case 'mf-like-dislike':
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'block' => true,
                            'float' => true,
                            'properties' => array(
                                'top' => '20',
                                'left' => '20',
                                'right' => '20',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_label,
                            ),
                        )
                );

                if ($field_type == 'mf-switch') {
                    if (isset($field->mf_swtich_enable_text)) {
                        $elements[] = $this->auto_field(
                                $field,
                                array(
                                    'type' => 'e2pdf-radio',
                                    'properties' => array(
                                        'top' => '5',
                                        'width' => 'auto',
                                        'height' => 'auto',
                                        'value' => $field_value,
                                        'option' => $field->mf_swtich_enable_text,
                                        'group' => $field_value,
                                    ),
                                )
                        );
                        $elements[] = $this->auto_field(
                                $field,
                                array(
                                    'type' => 'e2pdf-html',
                                    'float' => true,
                                    'properties' => array(
                                        'left' => '5',
                                        'width' => '100%',
                                        'height' => 'auto',
                                        'value' => 'Yes',
                                    ),
                                )
                        );
                    }

                    if (isset($field->mf_swtich_disable_text)) {
                        $elements[] = $this->auto_field(
                                $field,
                                array(
                                    'type' => 'e2pdf-radio',
                                    'properties' => array(
                                        'top' => '5',
                                        'width' => 'auto',
                                        'height' => 'auto',
                                        'value' => $field_value,
                                        'option' => $field->mf_swtich_disable_text,
                                        'group' => $field_value,
                                    ),
                                )
                        );
                        $elements[] = $this->auto_field(
                                $field,
                                array(
                                    'type' => 'e2pdf-html',
                                    'float' => true,
                                    'properties' => array(
                                        'left' => '5',
                                        'width' => '100%',
                                        'height' => 'auto',
                                        'value' => 'No',
                                    ),
                                )
                        );
                    }
                } elseif ($field_type == 'mf-like-dislike') {
                    $elements[] = $this->auto_field(
                            $field,
                            array(
                                'type' => 'e2pdf-radio',
                                'properties' => array(
                                    'top' => '5',
                                    'width' => 'auto',
                                    'height' => 'auto',
                                    'value' => $field_value,
                                    'option' => '1',
                                    'group' => $field_value,
                                ),
                            )
                    );
                    $elements[] = $this->auto_field(
                            $field,
                            array(
                                'type' => 'e2pdf-html',
                                'float' => true,
                                'properties' => array(
                                    'left' => '5',
                                    'width' => '100%',
                                    'height' => 'auto',
                                    'value' => 'Like',
                                ),
                            )
                    );
                    $elements[] = $this->auto_field(
                            $field,
                            array(
                                'type' => 'e2pdf-radio',
                                'properties' => array(
                                    'top' => '5',
                                    'width' => 'auto',
                                    'height' => 'auto',
                                    'value' => $field_value,
                                    'option' => '0',
                                    'group' => $field_value,
                                ),
                            )
                    );
                    $elements[] = $this->auto_field(
                            $field,
                            array(
                                'type' => 'e2pdf-html',
                                'float' => true,
                                'properties' => array(
                                    'left' => '5',
                                    'width' => '100%',
                                    'height' => 'auto',
                                    'value' => 'Dislike',
                                ),
                            )
                    );
                } else {
                    foreach ($field->mf_input_list as $option) {
                        $option_value = isset($option->mf_input_option_value) ? $option->mf_input_option_value : '';
                        $option_label = isset($option->mf_input_option_text) ? $option->mf_input_option_text : '';
                        $elements[] = $this->auto_field(
                                $field,
                                array(
                                    'type' => 'e2pdf-radio',
                                    'properties' => array(
                                        'top' => '5',
                                        'width' => 'auto',
                                        'height' => 'auto',
                                        'value' => $field_value,
                                        'option' => $option_value,
                                        'group' => $field_value,
                                    ),
                                )
                        );
                        $elements[] = $this->auto_field(
                                $field,
                                array(
                                    'type' => 'e2pdf-html',
                                    'float' => true,
                                    'properties' => array(
                                        'left' => '5',
                                        'width' => '100%',
                                        'height' => 'auto',
                                        'value' => $option_label,
                                    ),
                                )
                        );
                    }
                }
                break;
            case 'mf-checkbox':
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'block' => true,
                            'float' => true,
                            'properties' => array(
                                'top' => '20',
                                'left' => '20',
                                'right' => '20',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_label,
                            ),
                        )
                );

                foreach ($field->mf_input_list as $option) {
                    $option_value = isset($option->mf_input_option_value) ? $option->mf_input_option_value : '';
                    $option_label = isset($option->mf_input_option_text) ? $option->mf_input_option_text : '';
                    $elements[] = $this->auto_field(
                            $field,
                            array(
                                'type' => 'e2pdf-checkbox',
                                'properties' => array(
                                    'top' => '5',
                                    'width' => 'auto',
                                    'height' => 'auto',
                                    'value' => $field_value,
                                    'option' => $option_value,
                                ),
                            )
                    );
                    $elements[] = $this->auto_field(
                            $field,
                            array(
                                'type' => 'e2pdf-html',
                                'float' => true,
                                'properties' => array(
                                    'left' => '5',
                                    'width' => '100%',
                                    'height' => 'auto',
                                    'value' => $option_label,
                                ),
                            )
                    );
                }
                break;
            case 'mf-file-upload':
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'block' => true,
                            'float' => true,
                            'properties' => array(
                                'top' => '20',
                                'left' => '20',
                                'right' => '20',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_label,
                            ),
                        )
                );
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-image',
                            'float' => true,
                            'block' => true,
                            'properties' => array(
                                'top' => '5',
                                'left' => '20',
                                'right' => '0',
                                'width' => '170',
                                'height' => '150',
                                'value' => isset($field->mf_input_multiple_file) && $field->mf_input_multiple_file == 'multiple' ? '[e2pdf-format-output explode="," output="{0}"]' . $field_value . '[/e2pdf-format-output]' : $field_value,
                            ),
                        )
                );
                break;
            case 'mf-textarea':
            case 'mf-map-location':
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'block' => true,
                            'float' => true,
                            'properties' => array(
                                'top' => '20',
                                'left' => '20',
                                'right' => '20',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_label,
                            ),
                        )
                );
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-textarea',
                            'properties' => array(
                                'top' => '5',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_value,
                            ),
                        )
                );
                break;
            case 'mf-listing-optin':
            case 'mf-gdpr-consent':
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'block' => true,
                            'float' => true,
                            'properties' => array(
                                'top' => '20',
                                'left' => '20',
                                'right' => '20',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_label,
                            ),
                        )
                );

                if ($field_type == 'mf-gdpr-consent') {
                    $option_label = isset($field->mf_gdpr_consent_option_text) ? $field->mf_gdpr_consent_option_text : '';
                } else {
                    $option_label = isset($field->mf_listing_optin_option_text) ? $field->mf_listing_optin_option_text : '';
                }

                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-checkbox',
                            'properties' => array(
                                'top' => '5',
                                'width' => 'auto',
                                'height' => 'auto',
                                'value' => $field_value,
                                'option' => 'Accepted',
                            ),
                        )
                );
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'float' => true,
                            'properties' => array(
                                'left' => '5',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $option_label,
                            ),
                        )
                );
                break;
            case 'mf-signature':
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'block' => true,
                            'float' => true,
                            'properties' => array(
                                'top' => '20',
                                'left' => '20',
                                'right' => '20',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_label,
                            ),
                        )
                );
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-signature',
                            'properties' => array(
                                'top' => '5',
                                'width' => '100%',
                                'height' => '150',
                                'dimension' => '1',
                                'block_dimension' => '1',
                                'value' => $field_value,
                            ),
                        )
                );
                break;
            case 'mf-text-editor':
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'block' => true,
                            'float' => true,
                            'properties' => array(
                                'top' => '20',
                                'left' => '20',
                                'right' => '20',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_label,
                            ),
                        )
                );
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'properties' => array(
                                'top' => '5',
                                'width' => '100%',
                                'height' => '150',
                                'value' => $field_value,
                            ),
                        )
                );
                break;
            case 'mf-simple-repeater':
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'block' => true,
                            'float' => true,
                            'properties' => array(
                                'top' => '20',
                                'left' => '20',
                                'right' => '20',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_label,
                            ),
                        )
                );
                $subfields = array();
                if (!empty($field->mf_input_repeater)) {
                    foreach ($field->mf_input_repeater as $subfield) {
                        $elements = $this->auto_fields($elements, $subfield, $field);
                        $subfields[] = '[' . $field->mf_input_name . '-' . $subfield->mf_input_repeater_name . '-[e2pdf-for-key]]';
                    }
                }
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-html',
                            'block' => true,
                            'float' => true,
                            'properties' => array(
                                'top' => '20',
                                'left' => '20',
                                'right' => '20',
                                'width' => '100%',
                                'height' => 'auto',
                                'value' => $field_label . ' (Iteration)',
                            ),
                        )
                );
                $elements[] = $this->auto_field(
                        $field,
                        array(
                            'type' => 'e2pdf-textarea',
                            'properties' => array(
                                'top' => '5',
                                'width' => '100%',
                                'height' => 'auto',
                                'text_auto_font_size' => '1',
                                'value' => '[e2pdf-for]'
                                . '[e2pdf-for-data]' . $field_value . '[/e2pdf-for-data]' . "\n"
                                . '[e2pdf-for-do]' . implode(' - ', $subfields) . '' . "\n"
                                . '[/e2pdf-for-do]' . "\n"
                                . '[/e2pdf-for]',
                            ),
                        )
                );
                break;
            default:
                break;
        }
        return $elements;
    }

    // auto field
    public function auto_field($field = false, $element = array()) {
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
        $styles = array();
        $styles[] = plugins_url('css/extension/metform.css?v=' . time(), $this->helper->get('plugin_file_path'));
        return $styles;
    }

    // is repeater
    public function is_repeater($value) {
        if (is_array($value)) {
            $key = array_key_first($value);
            if (false !== strpos($key, 'repeater-')) {
                return true;
            }
        }
        return false;
    }

    // store form data action
    public function action_metform_after_store_form_data() {
        if (class_exists('ReflectionProperty')) {

            $submission = \MetForm\Core\Entries\Action::instance();

            $reflection = new ReflectionProperty(get_class($submission), 'entry_id');
            $reflection->setAccessible(true);
            $dataset = $reflection->getValue($submission);

            $reflection = new ReflectionProperty(get_class($submission), 'form_settings');
            $reflection->setAccessible(true);
            $form_settings = $reflection->getValue($submission);
            $form_settings['success_message'] = $this->filter_message($form_settings['success_message'], $dataset);

            if (isset($form_settings['enable_user_notification']) && $form_settings['enable_user_notification'] == 1) {
                $form_settings['user_email_body'] = $this->filter_message($form_settings['user_email_body'], $dataset, 'mail');
            }

            if (isset($form_settings['enable_admin_notification']) && $form_settings['enable_admin_notification'] == 1) {
                $form_settings['admin_email_body'] = $this->filter_message($form_settings['admin_email_body'], $dataset, 'mail');
            }
            $reflection->setValue($submission, $form_settings);
        }
    }

    // filter message
    public function filter_message($message = '', $dataset = '', $type = 'message') {
        if (false !== strpos($message, '[')) {
            $shortcode_tags = array(
                'e2pdf-download',
                'e2pdf-save',
                'e2pdf-view',
                'e2pdf-adobesign',
                'e2pdf-zapier',
                'e2pdf-attachment',
            );
            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $message, $matches);
            $tagnames = array_intersect($shortcode_tags, $matches[1]);
            if (!empty($tagnames)) {
                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $message, $shortcodes);
                foreach ($shortcodes[0] as $key => $shortcode_value) {
                    $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                    $atts = shortcode_parse_atts($shortcode[3]);
                    if (!isset($atts['dataset']) && isset($atts['id'])) {
                        $template = new Model_E2pdf_Template();
                        $template->load($atts['id']);
                        if ($template->get('extension') === 'metform') {
                            $atts['dataset'] = $dataset;
                            $shortcode[3] .= ' dataset="' . $dataset . '"';
                        }
                    }
                    if (!isset($atts['apply'])) {
                        $shortcode[3] .= ' apply="true"';
                    }
                    if (!isset($atts['iframe_download']) && $type == 'message') {
                        $shortcode[3] .= ' iframe_download="true"';
                    }
                    if ($this->helper->load('shortcode')->is_attachment($shortcode, $atts)) {
                        if ($type == 'mail') {
                            add_filter('wp_mail', array($this, 'filter_wp_mail'), 11);
                            $message = str_replace($shortcode_value, '[' . $shortcode[2] . $shortcode[3] . ']', $message);
                        } else {
                            $message = str_replace($shortcode_value, '', $message);
                        }
                    } else {
                        $message = str_replace($shortcode_value, do_shortcode_tag($shortcode), $message);
                    }
                }
            }
        }
        return $message;
    }

    // wp mail filter
    public function filter_wp_mail($args) {
        if (false !== strpos($args['message'], '[')) {
            $shortcode_tags = array(
                'e2pdf-save',
                'e2pdf-attachment',
            );
            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $args['message'], $matches);
            $tagnames = array_intersect($shortcode_tags, $matches[1]);
            if (!empty($tagnames)) {
                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $args['message'], $shortcodes);
                foreach ($shortcodes[0] as $key => $shortcode_value) {
                    $shortcode = $this->helper->load('shortcode')->get_shortcode($shortcodes, $key);
                    $atts = shortcode_parse_atts($shortcode[3]);
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
                                    $this->helper->add('metform_attachments', $file);
                                }
                            } else {
                                $this->helper->add('metform_attachments', $file);
                            }
                            $args['attachments'][] = $file;
                        }
                        $args['message'] = str_replace($shortcode_value, '', $args['message']);
                    }
                }
                $files = $this->helper->get('metform_attachments');
                if (is_array($files) && !empty($files)) {
                    add_action('wp_mail_succeeded', array($this, 'after_mail_sent'));
                    add_action('wp_mail_failed', array($this, 'after_mail_sent'));
                }
            }
        }
        $wp_mail = array(
            'to' => $args['to'],
            'subject' => $args['subject'],
            'message' => $args['message'],
            'headers' => $args['headers'],
            'attachments' => $args['attachments'],
        );
        return $wp_mail;
    }

    // after mail sent
    public function after_mail_sent() {
        remove_filter('wp_mail', array($this, 'filter_wp_mail'), 30);
        $files = $this->helper->get('metform_attachments');
        if (is_array($files) && !empty($files)) {
            foreach ($files as $key => $file) {
                $this->helper->delete_dir(dirname($file) . '/');
            }
            $this->helper->deset('metform_attachments');
        }
    }

    // row actions hook
    public function hook_metform_row_actions($actions, $post) {
        if (!empty($post->post_type) && $post->post_type == 'metform-entry' && !empty($post->ID)) {
            $hooks = $this->helper->load('hooks')->get('metform', 'hook_metform_row_actions', get_post_meta($post->ID, 'metform_entries__form_id', true));
            foreach ($hooks as $hook) {
                if ($this->helper->load('hooks')->process_hook(
                                $hook,
                                [
                                    'dataset' => $post->ID,
                                ],
                                'hook_metform_row_actions'
                        )
                ) {
                    $action = apply_filters(
                            'e2pdf_hook_action_button',
                            array(
                                'html' => '<a class="e2pdf-download-hook" target="_blank" href="%s">%s</a>',
                                'url' => $this->helper->get_url(
                                        array(
                                            'page' => 'e2pdf',
                                            'action' => 'export',
                                            'id' => $hook,
                                            'dataset' => $post->ID,
                                        ), 'admin.php?'
                                ),
                                'title' => 'PDF #' . $hook,
                            ), 'hook_metform_row_actions', $hook, $post->ID
                    );
                    if (!empty($action)) {
                        $actions['e2pdf_' . $hook] = sprintf(
                                $action['html'], $action['url'], $action['title']
                        );
                    }
                }
            }
        }
        return $actions;
    }

    // entry view hook
    public function hook_metform_entry_view() {
        $items = $this->helper->load('hooks')->get_items('metform', 'hook_metform_entry_view');
        if (!empty($items)) {
            add_meta_box(
                    'e2pdf',
                    apply_filters('e2pdf_hook_section_title', __('E2Pdf Actions', 'e2pdf'), 'hook_metform_entry_view'),
                    array($this, 'hook_metform_entry_view_callback'),
                    array((new \MetForm\Core\Entries\Cpt())->get_name()),
                    'side',
                    'default'
            );
        }
    }

    // entry view callback hook
    public function hook_metform_entry_view_callback($post) {
        if (!empty($post->post_type) && $post->post_type == 'metform-entry') {
            $hooks = $this->helper->load('hooks')->get('metform', 'hook_metform_entry_view', get_post_meta($post->ID, 'metform_entries__form_id', true));
            if (!empty($hooks)) {
                foreach ($hooks as $hook) {
                    if ($this->helper->load('hooks')->process_hook(
                                    $hook,
                                    [
                                        'dataset' => $post->ID,
                                    ],
                                    'hook_metform_entry_view'
                            )
                    ) {
                        $action = apply_filters(
                                'e2pdf_hook_action_button',
                                array(
                                    'html' => '<p><a class="e2pdf-download-hook" target="_blank" title="%2$s" href="%1$s"><span class="dashicons dashicons-pdf"></span> %2$s</a></p>',
                                    'url' => $this->helper->get_url(
                                            array(
                                                'page' => 'e2pdf',
                                                'action' => 'export',
                                                'id' => $hook,
                                                'dataset' => $post->ID,
                                            ), 'admin.php?'
                                    ),
                                    'title' => 'PDF #' . $hook,
                                ), 'hook_wordpress_page_edit', $hook, $post->ID
                        );
                        if (!empty($action)) {
                            echo sprintf(
                                    $action['html'], $action['url'], $action['title']
                            );
                        }
                    }
                }
            } else {
                echo __('None', 'e2pdf');
            }
        }
    }

    // row column hook
    public function hook_metform_entry_row_column($columns) {
        $items = $this->helper->load('hooks')->get_items('metform', 'hook_metform_entry_row_column');
        if (!empty($items)) {
            $columns['e2pdf_hook_metform_entry_row_column'] = apply_filters('e2pdf_hook_section_title', __('E2Pdf Actions', 'e2pdf'), 'hook_metform_entry_row_column');
            add_action('manage_metform-entry_posts_custom_column', array($this, 'hook_metform_entry_row_column_callback'), 10, 2);
        }
        return $columns;
    }

    // row column collback hook
    public function hook_metform_entry_row_column_callback($column, $post_id) {
        if ($column == 'e2pdf_hook_metform_entry_row_column') {
            $hooks = $this->helper->load('hooks')->get('metform', 'hook_metform_entry_row_column', get_post_meta($post_id, 'metform_entries__form_id', true));
            foreach ($hooks as $hook) {
                if ($this->helper->load('hooks')->process_hook(
                                $hook,
                                [
                                    'dataset' => $post_id,
                                ],
                                'hook_metform_entry_row_column'
                        )
                ) {
                    $action = apply_filters(
                            'e2pdf_hook_action_button',
                            array(
                                'html' => '<a class="button e2pdf-download-hook e2pdf-download-hook-icon-button" target="_blank" title="%2$s" href="%1$s">%2$s</a> ',
                                'url' => $this->helper->get_url(
                                        array(
                                            'page' => 'e2pdf',
                                            'action' => 'export',
                                            'id' => $hook,
                                            'dataset' => $post_id,
                                        ), 'admin.php?'
                                ),
                                'title' => 'PDF #' . $hook,
                            ), 'hook_metform_entry_row_column', $hook, $post_id
                    );
                    if (!empty($action)) {
                        echo sprintf(
                                $action['html'], $action['url'], $action['title']
                        );
                    }
                }
            }
        }
    }
}
