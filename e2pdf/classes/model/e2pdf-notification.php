<?php

/**
 * File: /model/e2pdf-notification.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_Notification extends Model_E2pdf_Model {

    private static $instance = null;
    private $notifications = [];

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // load
    public function load() {
        $notifications = get_transient('e2pdf_notifications');
        return (is_array($notifications) && !empty($notifications)) ? $notifications : [];
    }

    // add notification
    public function add_notification($type, $text) {

        $notification_id = (int) round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(3));
        $notifications = $this->load();
        
        $notifications[$notification_id] = [
            'type' => $type,
            'text' => $text,
            'expires' => time() + 10,
        ];
        set_transient('e2pdf_notifications', $notifications, DAY_IN_SECONDS);

        $this->notifications[$notification_id] = [
            'type' => $type,
            'text' => $text,
        ];
        return $notification_id;
    }

    // get notifications
    public function get_notifications($notification_id = '') {

        $notifications = $this->load();
        $now = time();

        if ($notification_id && isset($notifications[$notification_id])) {
            $this->notifications[$notification_id] = [
                'type' => $notifications[$notification_id]['type'],
                'text' => $notifications[$notification_id]['text'],
            ];
            unset($notifications[$notification_id]);
        }

        foreach ($notifications as $id => $entry) {
            if (isset($this->notifications[$id]) || $now >= $entry['expires']) {
                unset($notifications[$id]);
            }
        }

        set_transient('e2pdf_notifications', $notifications, DAY_IN_SECONDS);

        if (!get_option('e2pdf_hide_warnings', '0')) {
            if ($this->helper->get('license')->get('status') == 'pre_expired') {
                /* translators: %s: License Key Expire Date */
                $message = sprintf(__('Your E2Pdf License Key will expire at <strong>%s</strong>', 'e2pdf'), $this->helper->get('license')->get('expire'));
                if (current_user_can('manage_options') || current_user_can('e2pdf_license')) {
                    $message .= ' | ' . sprintf('<a class="e2pdf-link" target="_blank" href="%s">%s »</a>', esc_url('https://e2pdf.com/checkout/license/renew/' . get_option('e2pdf_license')), __('Renew License Key', 'e2pdf'));
                }
                array_unshift(
                        $this->notifications,
                        [
                            'type' => 'notice',
                            'text' => $message,
                        ]
                );
            }

            if ($this->helper->get('license')->get('status') == 'expired') {
                $message = __('Your E2Pdf License Key has expired', 'e2pdf');
                if (current_user_can('manage_options') || current_user_can('e2pdf_license')) {
                    $message .= ' | ' . sprintf('<a class="e2pdf-link" target="_blank" href="%s">%s »</a>', esc_url('https://e2pdf.com/checkout/license/renew/' . get_option('e2pdf_license')), __('Renew License Key', 'e2pdf'));
                }
                array_unshift(
                        $this->notifications,
                        [
                            'type' => 'error',
                            'text' => $message,
                        ]
                );
            }

            if ($this->helper->get('license')->get('type') == 'FREE' && $this->helper->get('page') == 'e2pdf-templates') {
                array_unshift(
                        $this->notifications,
                        [
                            'type' => 'notice',
                            'text' => sprintf(__('You are using E2Pdf FREE License Type', 'e2pdf') . ' | ' . __('Up to 1 Page and up to 1 Template allowed', 'e2pdf') . ' | <a class="e2pdf-link" target="_blank" href="%s">%s »</a>', esc_url('https://e2pdf.com/price'), __('Upgrade License Key', 'e2pdf')),
                        ]
                );
            }
        }

        if ($this->helper->get('license')->get('error')) {
            foreach ($this->notifications as $key => $notify) {
                if ($notify['type'] === 'error' && $notify['text'] === $this->helper->get('license')->get('error')) {
                    unset($this->notifications[$key]);
                }
            }
            if ($this->helper->get('license')->get('error') === 'License Key does not match this site. Please correct License Key to continue usage.') {
                $message = __('E2Pdf License Key does not match this site', 'e2pdf');
                if (current_user_can('manage_options') || current_user_can('e2pdf_license')) {
                    /* translators: 1: Support URL 2: Support URL */
                    $message .= ' | ' . sprintf(__('Failed to Restore License Key. Contact Support at <a target="_blank" href="%1$s">%2$s</a>', 'e2pdf'), 'https://e2pdf.com/support/contact', 'https://e2pdf.com/support/contact');
                }
            } else {
                $message = $this->helper->get('license')->get('error');
            }
            array_unshift(
                    $this->notifications,
                    [
                        'type' => 'error',
                        'text' => $message,
                    ]
            );
        }

        return array_values($this->notifications);
    }
}
