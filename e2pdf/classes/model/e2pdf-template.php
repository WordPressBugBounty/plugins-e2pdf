<?php

/**
 * E2pdf Template Model
 * 
 * @copyright  Copyright 2017 https://e2pdf.com
 * @license    GPLv3
 * @version    1
 * @link       https://e2pdf.com
 * @since      0.00.01
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_Template extends Model_E2pdf_Model {

    private $template = array();
    private $extension = null;
    private $table;

    /*
     * On Template init
     */

    public function __construct() {
        global $wpdb;
        parent::__construct();
        $this->table = $wpdb->prefix . 'e2pdf_templates';
    }

    /**
     * Load Template by ID
     * 
     * @param int $template_id - ID of template
     */
    public function load($template_id, $full = true, $revision_id = 0) {
        global $wpdb;

        $template = false;
        if ($this->helper->get('cache') && !$revision_id) {
            $template = wp_cache_get($template_id, 'e2pdf_templates');
        }

        if ($template === false) {
            $this->helper->load('cache')->pre_objects_cache();
            $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$this->get_table()}` WHERE ID = %d", $template_id), ARRAY_A);
            if ($this->helper->get('cache') && !$revision_id) {
                wp_cache_set($template_id, $template, 'e2pdf_templates');
            }
        }

        if ($revision_id) {
            $revision = new Model_E2pdf_Revision();
            if ($revision->load($template_id, false, $revision_id)) {
                $template = array_replace($template, $revision->template());
            } else {
                $template = array();
            }
        }

        if ($template) {
            $this->template = $template;
            $extension = new Model_E2pdf_Extension();
            if ($this->get('extension')) {
                $extension->load($this->get('extension'));
            }
            if ($this->get('item')) {
                $extension->set('item', $this->get('item'));
                if ($this->get('item') == '-2') {
                    $extension->set('item1', $this->get('item1'));
                    $extension->set('item2', $this->get('item2'));
                }
            }
            if ($this->get('ID')) {
                $extension->set('template_id', $this->get('ID'));
            }
            $this->extension = $extension;

            if ($full) {
                $this->set('fonts', $this->helper->load('convert')->unserialize($template['fonts']));
                $this->set('actions', $this->helper->load('convert')->unserialize($template['actions']));

                $pages = false;
                if ($this->helper->get('cache') && !$revision_id) {
                    $pages = wp_cache_get($this->get('ID'), 'e2pdf_pages');
                }
                if ($pages === false) {
                    $this->helper->load('cache')->pre_objects_cache();
                    $model_e2pdf_page = new Model_E2pdf_Page();
                    $pages = $model_e2pdf_page->get_pages($this->get('ID'), $revision_id);
                    if ($this->helper->get('cache') && !$revision_id) {
                        wp_cache_set($this->get('ID'), $pages, 'e2pdf_pages');
                    }
                }
                $this->set('pages', $pages);

                $revisions = false;
                if ($this->helper->get('cache')) {
                    $revisions = wp_cache_get($this->get('ID'), 'e2pdf_revisions');
                }

                if ($revisions === false) {
                    $this->helper->load('cache')->pre_objects_cache();
                    $model_e2pdf_revision = new Model_E2pdf_Revision();
                    $revisions = $model_e2pdf_revision->revisions($this->get('ID'));
                    if ($this->helper->get('cache')) {
                        wp_cache_set($this->get('ID'), $revisions, 'e2pdf_revisions');
                    }
                }
                $this->set('revisions', $revisions);
            }
            return true;
        }
        return false;
    }

    /**
     * Get loaded Template
     * 
     * @return object
     */
    public function template() {
        return $this->template;
    }

    public function extension() {
        return $this->extension;
    }

    /**
     * Set Template attribute
     * 
     * @param string $key - Attribute Key 
     * @param string $value - Attribute Value 
     * 
     * @return boolean
     */
    public function set($key, $value) {
        if ($key == 'format') {
            $formats = array(
                'pdf',
                'jpg'
            );
            if (in_array($value, $formats)) {
                $this->template[$key] = $value;
                return true;
            } else {
                return false;
            }
        } elseif ($key == 'permissions') {
            $this->template[$key] = serialize($value);
        } else {
            $this->template[$key] = $value;
            return true;
        }
    }

    /**
     * Get Template attribute by Key
     * 
     * @param string $key - Attribute Key 
     * 
     * @return mixed
     */
    public function get($key, $force = false) {
        if ($force && $this->get('ID')) {
            global $wpdb;
            if ($key == 'activated') {
                $value = $wpdb->get_var($wpdb->prepare('SELECT `activated` FROM `' . $this->get_table() . '` WHERE ID = %d', $this->get('ID')));
            } elseif ($key == 'uid') {
                $value = $wpdb->get_var($wpdb->prepare('SELECT `uid` FROM `' . $this->get_table() . '` WHERE ID = %d', $this->get('ID')));
            }
            return $value;
        } elseif (isset($this->template[$key])) {
            if ($key == 'revisions') {
                $value = array(
                    '0' => __('Latest', 'e2pdf')
                );
                if (is_array($this->template[$key])) {
                    foreach ($this->template[$key] as $revision) {
                        $value[$revision['revision_id']] = date('d M Y H:i:s', strtotime($revision['updated_at']));
                    }
                }
            } elseif ($key == 'permissions') {
                $permissions = $this->helper->load('convert')->unserialize($this->template[$key]);
                if (is_array($permissions)) {
                    $value = $permissions;
                } else {
                    $value = array(
                        'printing'
                    );
                }
            } else {
                $value = $this->template[$key];
            }
            return $value;
        } else {
            switch ($key) {
                case 'title':
                    $value = __("(no title)", 'e2pdf');
                    break;
                case 'width':
                case 'height':
                case 'inline':
                case 'auto':
                case 'trash':
                case 'locked':
                case 'activated':
                case 'revision_id':
                case 'rtl':
                case 'tab_order':
                    $value = '0';
                    break;
                case 'flatten':
                case 'appearance':
                    $value = '1';
                    break;
                case 'compression':
                    $value = '-1';
                    break;
                case 'optimization':
                    $value = '-1';
                    break;
                case 'format':
                    $value = 'pdf';
                    break;
                case 'resample':
                    $value = '100';
                    break;
                case 'text_align':
                    $value = 'left';
                    break;
                case 'fonts':
                case 'pages':
                case 'revisions':
                case 'actions':
                    $value = array();
                    break;
                case 'permissions':
                    $value = array(
                        'printing'
                    );
                    break;
                case 'name':
                    $value = '[e2pdf-dataset]';
                case 'savename':
                    $value = '[e2pdf-dataset]';
                    break;
                case 'font_processor':
                    $value = get_option('e2pdf_font_processor', '0');
                    break;
                default:
                    $value = '';
                    break;
            }
            return $value;
        }
    }

    /**
     * Get Templates table
     * 
     * @return string
     */
    public function get_table() {
        return $this->table;
    }

    /**
     * Delete loaded template
     */
    public function delete() {
        global $wpdb;
        if ($this->get('ID')) {

            $revision = new Model_E2pdf_Revision();
            foreach ($this->get('revisions') as $revision_id => $revision_name) {
                if ($revision_id != '0') {
                    $revision->load($this->get('ID'), true, $revision_id);
                    $revision->delete();
                }
            }

            if ($this->get('pdf')) {
                $pdf_dir = $this->helper->get('pdf_dir') . $this->get('pdf') . "/";
                $this->helper->delete_dir($pdf_dir);
            }

            $tpl_dir = $this->helper->get('tpl_dir') . $this->get('ID') . "/";
            $this->helper->delete_dir($tpl_dir);

            $where = array(
                'ID' => $this->get('ID')
            );
            $wpdb->delete($this->get_table(), $where);

            foreach ($this->get('pages') as $page) {
                $model_e2pdf_page = new Model_E2pdf_Page();
                $model_e2pdf_page->load($page['page_id'], $page['template_id']);
                $model_e2pdf_page->delete();
            }

            if ($this->helper->get('cache')) {
                wp_cache_delete($this->get('ID'), 'e2pdf_templates');
                wp_cache_delete($this->get('ID'), 'e2pdf_pages');
                wp_cache_delete($this->get('ID'), 'e2pdf_revisions');
            }
        }
    }

    public function update($keys = array()) {
        global $wpdb;
        if ($this->get('ID') && !empty($keys)) {
            $show_errors = false;
            if ($wpdb->show_errors) {
                $wpdb->show_errors(false);
                $show_errors = true;
            }
            foreach ($keys as $key) {
                $template[$key] = $this->get($key);
            }
            $where = array(
                'ID' => $this->get('ID')
            );
            $success = $wpdb->update($this->get_table(), $template, $where);
            if ($success === false) {
                $this->helper->load('db')->db_init($wpdb->prefix);
                $wpdb->update($this->get_table(), $template, $where);
            }
            if ($this->helper->get('cache')) {
                wp_cache_delete($this->get('ID'), 'e2pdf_templates');
            }
            if ($show_errors) {
                $wpdb->show_errors();
            }
        }
    }

    /**
     * Save template
     */
    public function save($rebuild = false) {
        global $wpdb;
        $template = $this->pre_save();
        $show_errors = false;
        if ($wpdb->show_errors) {
            $wpdb->show_errors(false);
            $show_errors = true;
        }

        if ($this->get('ID')) {
            if ($rebuild) {
                $revision = new Model_E2pdf_Revision();
                $revision->revision($this->get('ID'));
                if (isset($template['pdf']) && $template['pdf'] && ($template['pdf'] == $revision->get('pdf') || $this->get('revision_id'))) {
                    $pdf_name = md5(time());
                    $pdf_dir = $this->helper->get('pdf_dir') . $pdf_name . '/';
                    $pdf_images_dir = $pdf_dir . 'images/';

                    $this->helper->create_dir($pdf_dir);
                    $this->helper->create_dir($pdf_images_dir);

                    if (file_exists($this->helper->get('pdf_dir') . $template['pdf'] . '/' . $template['pdf'] . '.pdf')) {
                        $images = glob($this->helper->get('pdf_dir') . $template['pdf'] . "/images/*");
                        foreach ($images as $image) {
                            copy($image, $pdf_images_dir . pathinfo($image, PATHINFO_BASENAME));
                        }
                        copy($this->helper->get('pdf_dir') . $template['pdf'] . '/' . $template['pdf'] . '.pdf', $this->helper->get('pdf_dir') . $pdf_name . '/' . $pdf_name . '.pdf');
                        $template['pdf'] = $pdf_name;
                    }
                }
                $revision->flush();
            }
            $where = array(
                'ID' => $this->get('ID')
            );
            $success = $wpdb->update($this->get_table(), $template, $where);
            if ($success === false) {
                $this->helper->load('db')->db_init($wpdb->prefix);
                $wpdb->update($this->get_table(), $template, $where);
            }
        } else {
            $success = $wpdb->insert($this->get_table(), $template);
            if ($success === false) {
                $this->helper->load('db')->db_init($wpdb->prefix);
                $wpdb->insert($this->get_table(), $template);
            }
            $this->set('ID', $wpdb->insert_id);
        }

        if ($show_errors) {
            $wpdb->show_errors();
        }

        if ($this->get('ID') && $rebuild) {
            foreach ($this->get('pages') as $page_key => $page_value) {
                $page = new Model_E2pdf_Page();
                foreach ($page_value as $page_value_key => $page_value_value) {
                    $page->set($page_value_key, $page_value_value);
                }
                if (!isset($page_value['actions'])) {
                    $page_actions = array();
                } else {
                    $page_actions = $page_value['actions'];
                }
                $page->set('actions', $page_actions);
                $page->set('template_id', $this->get('ID'));
                $page->set('page_id', $page_key);
                $page->save();
                if (isset($page_value['elements'])) {
                    foreach ($page_value['elements'] as $element_key => $element_value) {
                        $element = new Model_E2pdf_Element();
                        foreach ($element_value as $element_value_key => $element_value_value) {
                            $element->set($element_value_key, $element_value_value);
                        }
                        if (!isset($element_value['properties'])) {
                            $element_properties = array();
                        } else {
                            $element_properties = $element_value['properties'];
                        }
                        $element->set('properties', $element_properties);

                        if (!isset($element_value['actions'])) {
                            $element_actions = array();
                        } else {
                            $element_actions = $element_value['actions'];
                        }
                        $element->set('actions', $element_actions);
                        $element->set('page_id', $page->get('page_id'));
                        $element->set('template_id', $this->get('ID'));
                        $element->save();
                    }
                }
            }
        }

        if ($this->helper->get('cache') && $this->get('ID')) {
            wp_cache_delete($this->get('ID'), 'e2pdf_templates');
            wp_cache_delete($this->get('ID'), 'e2pdf_pages');
            wp_cache_delete($this->get('ID'), 'e2pdf_revisions');
        }

        return $this->get('ID');
    }

    /**
     * Before save template
     */
    public function pre_save() {

        $fonts = array();
        $model_e2pdf_font = new Model_E2pdf_Font();

        $all_fonts = $model_e2pdf_font->get_fonts();

        $c_font = array_search($this->get('font'), $all_fonts);
        if ($c_font) {
            $fonts[$c_font] = $this->get('font');
        }

        $pages = $this->get('pages');
        foreach ($pages as $key => $page) {
            if (!empty($page['elements'])) {
                foreach ($page['elements'] as $element_key => $element) {
                    $tmp_fonts = $model_e2pdf_font->get_element_fonts($element, $all_fonts);
                    if (!empty($tmp_fonts)) {
                        $fonts = array_merge($fonts, $tmp_fonts);
                    }
                }
            }
        }

        $this->set('fonts', $fonts);

        $template = array(
            'uid' => $this->get('uid'),
            'activated' => $this->get('activated'),
            'title' => $this->get('title'),
            'pdf' => $this->get('pdf'),
            'updated_at' => current_time('mysql', 1),
            'flatten' => $this->get('flatten'),
            'tab_order' => $this->get('tab_order'),
            'format' => $this->get('format'),
            'resample' => $this->get('resample'),
            'compression' => $this->get('compression'),
            'optimization' => $this->get('optimization'),
            'appearance' => $this->get('appearance'),
            'width' => $this->get('width'),
            'height' => $this->get('height'),
            'extension' => $this->get('extension'),
            'item' => $this->get('item'),
            'item1' => $this->get('item1'),
            'item2' => $this->get('item2'),
            'dataset_title' => $this->get('dataset_title'),
            'dataset_title1' => $this->get('dataset_title1'),
            'dataset_title2' => $this->get('dataset_title2'),
            'button_title' => $this->get('button_title'),
            'dpdf' => $this->get('dpdf'),
            'inline' => $this->get('inline'),
            'auto' => $this->get('auto'),
            'rtl' => $this->get('rtl'),
            'font_processor' => $this->get('font_processor'),
            'name' => $this->get('name'),
            'savename' => $this->get('savename'),
            'password' => $this->get('password'),
            'owner_password' => $this->get('owner_password'),
            'permissions' => serialize($this->get('permissions')),
            'meta_title' => $this->get('meta_title'),
            'meta_subject' => $this->get('meta_subject'),
            'meta_author' => $this->get('meta_author'),
            'meta_keywords' => $this->get('meta_keywords'),
            'fonts' => serialize($this->get('fonts')),
            'font' => $this->get('font'),
            'font_size' => $this->get('font_size'),
            'font_color' => $this->get('font_color'),
            'line_height' => $this->get('line_height'),
            'text_align' => $this->get('text_align'),
            'trash' => $this->get('trash'),
            'locked' => $this->get('locked'),
            'author' => get_current_user_id(),
            'actions' => serialize($this->get('actions'))
        );

        if (!$this->get('ID')) {
            $template['created_at'] = current_time('mysql', 1);
        }

        return $template;
    }

    public function fill() {

        do_action('e2pdf_model_template_fill_pre', $this, $this->extension());

        $action = new Model_E2pdf_Action();
        $action->load($this->extension());
        $pages = $this->get('pages');
        $changed_elements = array();
        foreach ($pages as $key => $value) {
            $changed_elements = array_merge($changed_elements, $action->process_page_id($value));
        }

        $changed_pages = array();
        if (!empty($changed_elements)) {
            foreach ($changed_elements as $element) {
                $changed_pages[$element['page_id']][] = $element;
            }
        }

        foreach ($pages as $key => $value) {
            if (isset($changed_pages[$value['page_id']])) {
                $value['elements'] = array_merge($value['elements'], $changed_pages[$value['page_id']]);
            }
            $pages[$key] = $value = $action->process_actions($value);
            if (isset($value['hidden']) && $value['hidden']) {
                $pages[$key]['page_id'] = '0';
                $pages[$key]['elements'] = array();
            } else {
                if (!empty($value['elements'])) {
                    $pages[$key]['elements'] = $value['elements'];
                    foreach ($value['elements'] as $el_key => $el_value) {
                        if (isset($el_value['type'])) {
                            switch ($el_value['type']) {
                                case 'e2pdf-checkbox':
                                case 'e2pdf-radio':
                                    $pages[$key]['elements'][$el_key]['properties']['option'] = $this->extension()->render(
                                            isset($el_value['properties']['option']) ? $el_value['properties']['option'] : '', $el_value
                                    );
                                    $pages[$key]['elements'][$el_key]['value'] = $this->extension()->render(
                                            $el_value['value'], $el_value
                                    );
                                    break;
                                case 'e2pdf-select':
                                    $pages[$key]['elements'][$el_key]['properties']['options'] = $this->extension()->render(
                                            isset($el_value['properties']['options']) ? $el_value['properties']['options'] : '', $el_value
                                    );
                                    $pages[$key]['elements'][$el_key]['value'] = $this->extension()->render(
                                            $el_value['value'], $el_value
                                    );
                                    break;
                                case 'e2pdf-html':
                                case 'e2pdf-page-number':
                                    $pages[$key]['elements'][$el_key]['value'] = $this->helper->load('filter')->filter_html_tags(
                                            $this->extension()->render(
                                                    $el_value['value'], $el_value
                                            )
                                    );
                                    break;
                                case 'e2pdf-image':
                                case 'e2pdf-signature':
                                    $el_value['optimization'] = $this->get('optimization');
                                    $pages[$key]['elements'][$el_key]['value'] = $this->extension()->render(
                                            $el_value['value'], $el_value
                                    );
                                    break;
                                default:
                                    $pages[$key]['elements'][$el_key]['value'] = $this->extension()->render(
                                            $el_value['value'], $el_value
                                    );
                                    break;
                            }
                        }
                    }
                }
            }
        }
        $this->set('pages', $pages);

        do_action('e2pdf_model_template_fill_after', $this, $this->extension());
    }

    public function pre_render() {
        $pages = $this->get('pages');
        foreach ($pages as $key => $page) {
            $page = (array) $page;
            $pages[$key] = $page;
            if (!empty($page['elements'])) {
                foreach ($page['elements'] as $el_key => $el_value) {
                    $el_value = (array) $el_value;
                    $pages[$key]['elements'][$el_key] = $el_value;
                    if ($el_value['type'] == 'e2pdf-qrcode') {
                        $pages[$key]['elements'][$el_key]['type'] = 'e2pdf-image';
                    } else if ($el_value['type'] == 'e2pdf-barcode') {
                        $pages[$key]['elements'][$el_key]['type'] = 'e2pdf-image';
                    } else if ($el_value['type'] == 'e2pdf-graph') {
                        $pages[$key]['elements'][$el_key]['type'] = 'e2pdf-image';
                    }
                }
            }

            $this->helper->load('sort')->uasort($pages[$key]['elements'], "sort_by_elementid");
            $this->helper->load('sort')->stable_uasort($pages[$key]['elements'], "sort_by_zindex");
        }

        return $pages;
    }

    public function convert($pre_save, $type = false) {

        $model_e2pdf_convert = new Model_E2pdf_Convert();
        $model_e2pdf_font = new Model_E2pdf_Font();

        if ($pre_save) {
            $pages = $this->get('pages');
            $this->template = $this->pre_save();
            $this->set('pages', $pages);
            $this->set('fonts', $this->helper->load('convert')->unserialize($this->get('fonts')));
        }

        $fonts = array();
        if ($this->get('fonts')) {
            foreach ($this->get('fonts') as $key => $value) {
                $fonts[] = array(
                    'name' => $value,
                    'value' => $model_e2pdf_font->get_font($key)
                );
            }
        }

        $pages = $this->pre_render();

        $settings = array(
            'uid' => $this->get('uid'),
            'activated' => $this->get('activated'),
            'title' => $this->get_name(),
            'flatten' => $this->get('flatten'),
            'tab_order' => $this->get('tab_order'),
            'format' => $this->get('format'),
            'resample' => $this->get('resample'),
            'compression' => $this->get('compression'),
            'appearance' => $this->get('appearance'),
            'password' => $this->get('password'),
            'owner_password' => $this->get('owner_password'),
            'permissions' => $this->get('permissions'),
            'meta_title' => $this->get('meta_title'),
            'meta_subject' => $this->get('meta_subject'),
            'meta_author' => $this->get('meta_author'),
            'meta_keywords' => $this->get('meta_keywords'),
            'font' => $this->get('font'),
            'font_size' => $this->get('font_size'),
            'font_color' => $this->get('font_color'),
            'line_height' => $this->get('line_height'),
            'text_align' => $this->get('text_align'),
            'rtl' => $this->get('rtl'),
            'font_processor' => apply_filters('e2pdf_font_processor', $this->get('font_processor'), $this->get('ID')),
            'created_at' => $this->get('created_at'),
            'updated_at' => $this->get('updated_at'),
        );

        if ($this->get('pdf')) {
            $pdf = $this->helper->get('pdf_dir') . $this->get('pdf') . '/' . $this->get('pdf') . '.pdf';
            if (file_exists($pdf)) {
                $settings['pdf'] = base64_encode(file_get_contents($pdf));
            }
        }

        $model_e2pdf_convert->set(array(
            'type' => $type,
            'data' => array(
                'settings' => $settings,
                'pages' => $pages,
                'fonts' => $fonts
            ),
        ));

        return $model_e2pdf_convert->convert();
    }

    public function render($pre_save = false) {
        $model_e2pdf_api = new Model_E2pdf_Api();
        $model_e2pdf_font = new Model_E2pdf_Font();

        if ($pre_save) {
            $pages = $this->get('pages');
            $this->template = $this->pre_save();
            $this->set('pages', $pages);
            $this->set('fonts', $this->helper->load('convert')->unserialize($this->get('fonts')));
        }

        $fonts = array();
        if (get_option('e2pdf_cache_fonts', '1')) {
            $cached_fonts = get_option('e2pdf_cached_fonts', array());
            if ($this->get('fonts')) {
                $uncached_fonts = array();
                foreach ($this->get('fonts') as $key => $value) {
                    $md5 = $model_e2pdf_font->get_font($key, true);
                    if ($md5 && in_array($md5, $cached_fonts)) {
                        $fonts[] = array(
                            'name' => $value,
                            'md5' => $md5,
                            'cache' => true
                        );
                    } else {
                        $uncached_fonts[] = $md5;
                        $fonts[] = array(
                            'name' => $value,
                            'value' => $model_e2pdf_font->get_font($key),
                            'cache' => true
                        );
                    }
                }
                if (!empty($uncached_fonts)) {
                    $model_e2pdf_api->set(array(
                        'action' => 'cache/fonts',
                        'data' => array(
                            'fonts' => $uncached_fonts
                        ),
                    ));
                    $request = $model_e2pdf_api->request();
                    if (isset($request['fonts']) && is_array($request['fonts'])) {
                        $cached_fonts = array_merge($cached_fonts, $request['fonts']);
                    }
                    update_option('e2pdf_cached_fonts', $cached_fonts);
                }
            }
        } else {
            if ($this->get('fonts')) {
                foreach ($this->get('fonts') as $key => $value) {
                    $fonts[] = array(
                        'name' => $value,
                        'value' => $model_e2pdf_font->get_font($key)
                    );
                }
            }
        }

        $pages = $this->pre_render();

        $settings = array(
            'uid' => $this->get('uid'),
            'activated' => $this->get('activated'),
            'title' => $this->get_name(),
            'flatten' => $this->get('flatten'),
            'tab_order' => $this->get('tab_order'),
            'format' => $this->get('format'),
            'resample' => $this->get('resample'),
            'compression' => $this->get('compression'),
            'appearance' => $this->get('appearance'),
            'password' => $this->get('password'),
            'owner_password' => $this->get('owner_password'),
            'permissions' => $this->get('permissions'),
            'meta_title' => $this->get('meta_title'),
            'meta_subject' => $this->get('meta_subject'),
            'meta_author' => $this->get('meta_author'),
            'meta_keywords' => $this->get('meta_keywords'),
            'font' => $this->get('font'),
            'font_size' => $this->get('font_size'),
            'font_color' => $this->get('font_color'),
            'line_height' => $this->get('line_height'),
            'text_align' => $this->get('text_align'),
            'rtl' => $this->get('rtl'),
            'font_processor' => apply_filters('e2pdf_font_processor', $this->get('font_processor'), $this->get('ID')),
            'created_at' => $this->get('created_at'),
            'updated_at' => $this->get('updated_at'),
        );

        if ($this->get('dpdf')) {
            if ($dpdf = $this->helper->load('pdf')->get_pdf($this->get('dpdf'))) {
                $settings['pdf'] = $dpdf;
                $settings['dpdf'] = '1';
            }
        } elseif ($this->get('pdf')) {
            $pdf = $this->helper->get('pdf_dir') . $this->get('pdf') . '/' . $this->get('pdf') . '.pdf';
            if (file_exists($pdf)) {
                $settings['pdf'] = base64_encode(file_get_contents($pdf));
            }
        }

        $data = array(
            'settings' => $settings,
            'pages' => $pages,
            'fonts' => $fonts
        );

        $cached_pdf = md5(json_encode($data));
        $request = $this->helper->load('cache')->get_cached_pdf($cached_pdf);
        if (empty($request['file'])) {
            $model_e2pdf_api->set(array(
                'action' => 'template/build',
                'data' => $data
            ));
            $request = $model_e2pdf_api->request();
        }

        if (isset($request['error']) && $request['error'] == 'cache_error') {
            update_option('e2pdf_cached_fonts', array());
            $fonts = array();
            if ($this->get('fonts')) {
                foreach ($this->get('fonts') as $key => $value) {
                    $fonts[] = array(
                        'name' => $value,
                        'value' => $model_e2pdf_font->get_font($key)
                    );
                }
            }
            $data = array(
                'settings' => $settings,
                'pages' => $pages,
                'fonts' => $fonts
            );
            $cached_pdf = md5(json_encode($data));
            $request = $this->helper->load('cache')->get_cached_pdf($cached_pdf);
            if (empty($request['file'])) {
                $model_e2pdf_api->set(array(
                    'action' => 'template/build',
                    'data' => $data,
                ));
                $request = $model_e2pdf_api->request();
            }
        }
        $this->helper->load('cache')->cache_pdf($cached_pdf, $request);
        return $request;
    }

    public function get_name() {
        if ($this->get('name')) {
            $name = $this->get('name');
        } elseif ($this->get('title')) {
            $name = $this->get('title');
        } else {
            $name = __('(no title)', 'e2pdf');
        }
        return $name;
    }

    public function flush() {
        if ($this->get('ID')) {
            $model_e2pdf_page = new Model_E2pdf_Page();
            $pages = $model_e2pdf_page->get_pages($this->get('ID'));
            foreach ($pages as $page_value) {
                $page = new Model_E2pdf_Page();
                $page->load($page_value['page_id'], $page_value['template_id']);
                $page->delete();
            }
        }
    }

    public function activate() {
        if ($this->get('ID')) {
            if ($this->get('activated', true)) {
                $request = array(
                    'template_id' => $this->get('ID'),
                    'template_uid' => $this->get('uid'),
                );
            } else {
                $model_e2pdf_api = new Model_E2pdf_Api();
                $model_e2pdf_api->set(
                        array(
                            'action' => 'template/activate',
                            'data' => array(
                                'template_id' => $this->get('ID'),
                                'template_uid' => $this->get('uid'),
                                'template_title' => $this->get('title'),
                                'template_extension' => $this->get('extension'),
                            ),
                        )
                );
                $request = $model_e2pdf_api->request();
                if (!isset($request['error'])) {
                    $this->set('activated', '1');
                    $this->set('uid', $request['template_uid']);
                    $this->update(array('activated', 'uid'));
                } else {
                    $this->set('activated', '0');
                    $this->update(array('activated'));
                }
                if ($this->helper->get('cache')) {
                    wp_cache_delete($this->get('ID'), 'e2pdf_templates');
                }
            }
        } else {
            $request = array(
                'error' => __('Something went wrong', 'e2pdf'),
            );
        }
        return $request;
    }

    public function deactivate() {
        if ($this->get('ID')) {
            if (!$this->get('activated', true)) {
                $request = array(
                    'success' => __('Template Deactivated', 'e2pdf')
                );
            } else {
                $model_e2pdf_api = new Model_E2pdf_Api();
                $model_e2pdf_api->set(
                        array(
                            'action' => 'template/deactivate',
                            'data' => array(
                                'template_id' => $this->get('ID'),
                                'template_uid' => $this->get('uid'),
                            ),
                        )
                );
                $request = $model_e2pdf_api->request();
                if (!isset($request['error'])) {
                    $this->set('activated', '0');
                    $this->update(array('activated'));
                }
                if ($this->helper->get('cache')) {
                    wp_cache_delete($this->get('ID'), 'e2pdf_templates');
                }
            }
        } else {
            $request = array(
                'error' => __('Something went wrong', 'e2pdf'),
            );
        }
        return $request;
    }
}
