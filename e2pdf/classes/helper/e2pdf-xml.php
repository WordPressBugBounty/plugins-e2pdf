<?php

/**
 * File: /helper/e2pdf-xml.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Xml {

    private $xml;

    // check
    public function check() {
        if (extension_loaded('simplexml')) {
            return true;
        } else {
            return false;
        }
    }

    // create
    public function create($key = false) {
        $this->set('xml', new Helper_E2pdf_SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $key . '></' . $key . '>'));
        return $this->get('xml');
    }

    // get xml
    public function get_xml() {
        if ($this->get('xml')) {
            return $this->get('xml')->asXML();
        }
        return '';
    }

    // set
    public function set($key, $value) {
        if (!isset($this->xml)) {
            $this->xml = new stdClass();
        }
        $this->xml->$key = $value;
    }

    // get
    public function get($key) {
        if (isset($this->xml->$key)) {
            return $this->xml->$key;
        } else {
            return false;
        }
    }

    // get node value
    public function get_node_value($element, $attribute = '') {
        $value = '';
        if (is_object($element) && $attribute && isset($element->attributes) && $element->attributes->getNamedItem($attribute)) {
            $value = $element->attributes->getNamedItem($attribute)->nodeValue;
        }
        return $value;
    }

    // set node value
    public function set_node_value($element, $attribute = '', $value = '', $parent = false) {

        if (!is_object($element) || !method_exists($element, 'setAttribute') || !$attribute) {
            return $element;
        }

        if ($parent && (!is_object($element->parentNode) || !method_exists($element->parentNode, 'setAttribute'))) {
            return $element;
        }

        if ($parent) {
            $target = $element->parentNode;
        } else {
            $target = $element;
        }
        $target->setAttribute($attribute, $value);

        return $element;
    }

    // parse xml page
    public function parse_xml_page($page_node, &$pages, $pdf_images_dir, $extension) {

        $page_number = (string) $page_node['number'];
        $pages[$page_number] = [];
        $pages[$page_number]['page_id'] = $page_number;

        if (isset($page_node->properties)) {
            $pages[$page_number]['properties'] = [];
            foreach ($page_node->properties->children() as $prop_name => $prop_value) {
                $prop_string = (string) $prop_value;
                if ($prop_name === 'background' && !empty($prop_string)) {
                    file_put_contents($pdf_images_dir . $page_number . '.png', base64_decode($prop_string), LOCK_EX);
                    do_action('e2pdf_pdf_upload_background_save_after');
                } else {
                    $pages[$page_number]['properties'][$prop_name] = $prop_string;
                }
            }
        }
        if (isset($page_node->elements)) {
            $pages[$page_number]['elements'] = [];
            $element_index = 0;
            foreach ($page_node->elements->element as $element_node) {
                $pages[$page_number]['elements'][$element_index] = [];
                foreach ($element_node->children() as $elem_name => $elem_value) {
                    if ($elem_name == 'properties') {
                        $pages[$page_number]['elements'][$element_index]['properties'] = [];
                        foreach ($elem_value->children() as $inner_prop_name => $inner_prop_value) {
                            $pages[$page_number]['elements'][$element_index]['properties'][$inner_prop_name] = (string) $inner_prop_value;
                        }
                    } else {
                        $pages[$page_number]['elements'][$element_index][$elem_name] = (string) $elem_value;
                    }
                }
                $element = $pages[$page_number]['elements'][$element_index];
                if ($element['type'] === 'e2pdf-image' && isset($element['base64']) && $element['base64']) {
                    $image = base64_decode($element['base64']);
                    $file_name = basename($element['value']);
                    if (!$file_name) {
                        $ext = $this->helper->load('image')->get_extension($image);
                        if ($ext) {
                            $file_name = md5(mktime()) . '.' . $ext;
                        }
                    }
                    if ($file_name) {
                        $upload_file = wp_upload_bits($file_name, null, $image);
                        if (!$upload_file['error']) {
                            $wp_filetype = wp_check_filetype($file_name, null);
                            $attachment = array(
                                'post_mime_type' => $wp_filetype['type'],
                                'post_parent' => 0,
                                'post_title' => preg_replace('/\.[^.]+$/', '', $file_name),
                                'post_content' => '',
                                'post_status' => 'inherit',
                            );
                            $attachment_id = wp_insert_attachment($attachment, $upload_file['file'], 0);
                            if (!is_wp_error($attachment_id)) {
                                require_once ABSPATH . 'wp-admin/includes/image.php';
                                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
                                wp_update_attachment_metadata($attachment_id, $attachment_data);
                                $pages[$page_number]['elements'][$element_index]['value'] = $upload_file['url'];
                            }
                        }
                        unset($pages[$page_number]['elements'][$element_index]['base64']);
                    } else {
                        unset($pages[$page_number]['elements'][$element_index]);
                    }
                } elseif (isset($element['name']) && $element['name']) {
                    $el_value = $extension->auto_map($element['name']);
                    if ($el_value !== false) {
                        $pages[$page_number]['elements'][$element_index]['value'] = $el_value;
                    }
                }
                $element_index++;
            }
        }
    }

    // parse xml font
    public function parse_xml_font($font) {

        $model_e2pdf_font = new Model_E2pdf_Font();
        $fonts = $model_e2pdf_font->get_fonts();
        $fonts_dir = $this->helper->get('fonts_dir');

        $font_title = (string) $font->title;
        $font_name = (string) $font->name;
        $font_value = (string) $font->value;

        $exist = array_search($font_title, $fonts, true);
        if ($exist === false) {
            if (!file_exists($fonts_dir . $font_name)) {
                $f_name = $font_name;
            } else {
                $i = 0;
                do {
                    $f_name = $i . '_' . $font_name;
                    $i++;
                } while (file_exists($fonts_dir . $f_name));
            }
            $font_ext = strtolower(pathinfo($f_name, PATHINFO_EXTENSION));
            if (in_array($font_ext, $model_e2pdf_font->get_allowed_extensions())) {
                $font_data = base64_decode($font_value);
                if ($font_data !== false) {
                    file_put_contents($fonts_dir . $f_name, $font_data, LOCK_EX);
                }
            }
        }
    }
}
