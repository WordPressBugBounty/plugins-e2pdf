<?php

/**
 * File: /helper/e2pdf-hooks.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Helper_E2pdf_Hooks {

    private $helper;

    // construct
    public function __construct() {
        $this->helper = Helper_E2pdf_Helper::instance();
    }

    // get
    public function get($extension = '', $hook = '', $item = '') {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $hooks = $wpdb->get_col($wpdb->prepare('SELECT `ID` FROM `' . (new Model_E2pdf_Template())->get_table() . '` WHERE trash = %s AND activated = %s AND extension = %s AND item = %s AND FIND_IN_SET(%s, hooks)', '0', '1', $extension, $item, $hook));
        return $hooks;
    }

    // get items
    public function get_items($extension = '', $hook = '') {
        global $wpdb;
        if ($hook == 'hook_wordpress_metabox') {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $hooks = $wpdb->get_col($wpdb->prepare('SELECT DISTINCT `item` FROM `' . (new Model_E2pdf_Template())->get_table() . '` WHERE trash = %s AND activated = %s AND extension = %s AND item != %s AND item != %s AND FIND_IN_SET(%s, hooks)', '0', '1', $extension, '', '-3', $hook));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $hooks = $wpdb->get_col($wpdb->prepare('SELECT DISTINCT `item` FROM `' . (new Model_E2pdf_Template())->get_table() . '` WHERE trash = %s AND activated = %s AND extension = %s AND item != %s AND FIND_IN_SET(%s, hooks)', '0', '1', $extension, '', $hook));
        }
        return $hooks;
    }

    // proces hook
    public function process_hook($template_id = 0, $atts = [], $hook = '') {
        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare('SELECT 1 FROM `' . $wpdb->prefix . 'e2pdf_templates` WHERE ID = %d AND (actions LIKE %s OR actions LIKE %s) LIMIT 1', $template_id, '%' . $hook . '%', '%hooks%'))) {

            $dataset = isset($atts['dataset']) ? $atts['dataset'] : false;
            $dataset2 = isset($atts['dataset2']) ? $atts['dataset2'] : false;
            $wc_order_id = isset($atts['wc_order_id']) ? $atts['wc_order_id'] : false;
            $wc_product_item_id = isset($atts['wc_product_item_id']) ? $atts['wc_product_item_id'] : false;

            $template = new Model_E2pdf_Template();
            if (!$template->load($template_id, false)) {
                return true;
            }

            $template->extension()->patch('template_id', $template_id);
            $template->extension()->patch('dataset', $dataset);
            $template->extension()->patch('dataset2', $dataset2);
            $template->extension()->patch('wc_order_id', $wc_order_id);
            $template->extension()->patch('wc_product_item_id', $wc_product_item_id);
            if (!($template->get('extension') === 'wordpress' && $template->get('item') === '-3')) {
                $template->extension()->patch('user_id', get_current_user_id());
            }
            $template->extension()->patch('storing_engine', $template->extension()->get_storing_engine());

            if ($template->get('actions')) {
                $model_e2pdf_action = new Model_E2pdf_Action();
                $model_e2pdf_action->load($template->extension());
                if (!is_array($template->get('actions'))) {
                    $template->set('actions', $this->helper->load('convert')->unserialize($template->get('actions')));
                }
                $actions = $model_e2pdf_action->process_global_actions($template->get('actions'));
                foreach ($actions as $action) {
                    if (isset($action['action']) && $action['action'] == 'disable_hooks' && isset($action['success'])) {
                        return false;
                    } elseif (isset($action['action']) && $action['action'] == 'enable_hooks' && !isset($action['success'])) {
                        return false;
                    } elseif (isset($action['action']) && $action['action'] == 'disable_' . $hook && isset($action['success'])) {
                        return false;
                    } elseif (isset($action['action']) && $action['action'] == 'enable_' . $hook && !isset($action['success'])) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
}
