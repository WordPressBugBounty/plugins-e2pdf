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
            return base64_encode($this->get('xml')->asXML());
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
}
