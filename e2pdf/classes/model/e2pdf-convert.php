<?php

/**
 * E2Pdf Convert Model
 * @copyright  Copyright 2017 https://e2pdf.com
 * @license    GPLv3
 * @version    1
 * @link       https://e2pdf.com
 * @since      0.00.01
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_Convert extends Model_E2pdf_Model {

    protected $convert = null;

    /**
     * Convert Template to PHP Output
     * @param array $data - Template
     * @return string - Converted template
     */
    public function toPHP($data = array()) {
        $content = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $content .= "'" . $key . "' => [";
                $content .= $this->toPHP($value);
                $content .= "],\n";
            } else {
                $content .= "'" . $key . "' => '" . str_replace("'", "\'", $value) . "',\n";
            }
        }
        return $content;
    }

    /**
     * Set options for API Request
     * @param string $key - Key
     * @param mixed $value - Value
     */
    public function set($key, $value = false) {
        if (!$this->convert) {
            $this->convert = new stdClass();
        }
        if (is_array($key)) {
            foreach ($key as $attr => $value) {
                $this->convert->$attr = $value;
            }
        } else {
            $this->convert->$key = $value;
        }
    }

    /**
     *  Convert Template
     */
    public function convert() {

        $data = !empty($this->convert->data) ? $this->convert->data : array();

        $file = '<?php' . PHP_EOL;
        $file .= 'function e2pdf_template() {' . PHP_EOL;
        $file .= '$template = [' . PHP_EOL;
        $file .= $this->toPHP($data);
        $file .= '];' . PHP_EOL;
        $file .= 'return $template;' . PHP_EOL;
        $file .= '}';

        if ($file) {
            $response['file'] = base64_encode($file);
        } else {
            $response['error'] = __('Something went wrong', 'e2pdf');
        }
        return $response;
    }

}
