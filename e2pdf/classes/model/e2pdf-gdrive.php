<?php

/**
 * File: /model/e2pdf-gdrive.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_Gdrive extends Model_E2pdf_Model {

    protected $provider = [];

    public function __construct() {
        parent::__construct();
        $this->provider = [
            'client_id' => get_option('e2pdf_gdrive_client_id', ''),
            'client_secret' => get_option('e2pdf_gdrive_client_secret', ''),
            'access_token' => get_transient('e2pdf_gdrive_access_token'),
            'refresh_token' => get_option('e2pdf_gdrive_refresh_token', ''),
            'redirect_uri' => site_url('/e2pdf-rpc/v1/gdrive/auth?api_key=' . get_option('e2pdf_gdrive_api_key')),
        ];
        if (!$this->provider['access_token'] && $this->provider['refresh_token']) {
            $this->refresh_token();
        }
    }

    // get code
    public function get_code() {
        $response = [];
        if ($this->provider['client_id'] && $this->provider['client_secret']) {
            $response['redirect'] = add_query_arg(
                    [
                        'response_type' => 'code',
                        'client_id' => $this->provider['client_id'],
                        'redirect_uri' => $this->provider['redirect_uri'],
                        'scope' => 'https://www.googleapis.com/auth/drive.file',
                        'access_type' => 'offline',
                        'prompt' => 'consent',
                    ],
                    'https://accounts.google.com/o/oauth2/v2/auth'
            );
        }
        return $response;
    }

    // get token
    public function get_token($code = '') {
        $response = wp_remote_post(
                'https://oauth2.googleapis.com/token',
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body' => [
                        'code' => $code,
                        'client_id' => $this->provider['client_id'],
                        'client_secret' => $this->provider['client_secret'],
                        'redirect_uri' => $this->provider['redirect_uri'],
                        'grant_type' => 'authorization_code',
                    ],
                ]
        );
        if (is_wp_error($response)) {
            return;
        }
        $request = json_decode(wp_remote_retrieve_body($response));
        if (!empty($request->refresh_token) && !empty($request->access_token)) {
            update_option('e2pdf_gdrive_refresh_token', $request->refresh_token);
            set_transient('e2pdf_gdrive_access_token', $request->access_token, 1800);
        }
    }

    public function refresh_token() {
        $response = wp_remote_post(
                'https://oauth2.googleapis.com/token',
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body' => [
                        'client_id' => $this->provider['client_id'],
                        'client_secret' => $this->provider['client_secret'],
                        'refresh_token' => $this->provider['refresh_token'],
                        'grant_type' => 'refresh_token',
                    ],
                ]
        );
        if (is_wp_error($response)) {
            return;
        }
        $request = json_decode(wp_remote_retrieve_body($response));
        if (!empty($request->access_token)) {
            $this->provider['access_token'] = $request->access_token;
            set_transient('e2pdf_gdrive_access_token', $request->access_token, 1800);
        }
    }

    public function create_dir($dir) {

        $parts = array_values(array_filter(explode('/', trim($this->helper->load('convert')->to_file_dir($dir), '/'))));
        $parent_id = 'root';

        if (!empty($parts)) {
            try {
                foreach ($parts as $part) {
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                    $query = urlencode("name = '$part' and mimeType = 'application/vnd.google-apps.folder' and '$parent_id' in parents and trashed = false");
                    $response = wp_remote_get(
                            'https://www.googleapis.com/drive/v3/files?q=' . $query . '&fields=files(id,name)',
                            [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $this->provider['access_token'],
                                    'Accept' => 'application/json',
                                ],
                            ]
                    );
                    if (is_wp_error($response)) {
                        throw new Exception('Request failed: ' . $response->get_error_message());
                    }
                    $data = json_decode(wp_remote_retrieve_body($response), true);
                    if (!empty($data['files'])) {
                        $parent_id = $data['files'][0]['id'];
                    } else {
                        $create_url = 'https://www.googleapis.com/drive/v3/files?fields=id';
                        $body = [
                            'name' => $part,
                            'mimeType' => 'application/vnd.google-apps.folder',
                            'parents' => [$parent_id],
                        ];

                        $response = wp_remote_post(
                                $create_url,
                                [
                                    'headers' => [
                                        'Authorization' => 'Bearer ' . $this->provider['access_token'],
                                        'Content-Type' => 'application/json',
                                    ],
                                    'body' => json_encode($body), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
                                ]
                        );

                        if (is_wp_error($response)) {
                            throw new Exception('Request failed: ' . $response->get_error_message());
                        }
                        $data = json_decode(wp_remote_retrieve_body($response), true);
                        $parent_id = isset($data['id']) ? $data['id'] : 'root';
                    }
                }
            } catch (Exception $ex) {
                return 'root';
            }
        }

        return $parent_id;
    }

    public function upload($file, $file_name, $dir = 'root') {
        if (!$file) {
            return false;
        }
        try {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
            $meta = json_encode(
                    [
                        'name' => $file_name,
                        'parents' => [$dir],
                    ]
            );

            $boundary = uniqid();
            $delimiter = "------$boundary";

            $body = $this->body($delimiter, $meta, $file);

            $response = wp_remote_post(
                    'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart',
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->provider['access_token'],
                            'Content-Type' => "multipart/related; boundary=$delimiter",
                            'Content-Length' => strlen($body),
                        ],
                        'body' => $body,
                    ]
            );

            if (is_wp_error($response)) {
                return false;
            }
            $data = json_decode(wp_remote_retrieve_body($response), true);
            return isset($data['id']) ? $data['id'] : false;
        } catch (Exception $ex) {
            return false;
        }
        return false;
    }

    public function reupload($file, $file_id) {
        if ($file_id) {
            try {
                $response = wp_remote_request(
                        'https://www.googleapis.com/upload/drive/v3/files/' . $file_id . '?uploadType=media',
                        [
                            'method' => 'PATCH',
                            'headers' => [
                                'Authorization' => 'Bearer ' . $this->provider['access_token'],
                            ],
                            'body' => $file,
                        ]
                );
                if (is_wp_error($response)) {
                    return false;
                }
                $data = json_decode(wp_remote_retrieve_body($response), true);
                return isset($data['id']) ? $data['id'] : false;
            } catch (Exception $ex) {
                return false;
            }
        }
        return false;
    }

    public function permission($file_id, $role = 'reader', $type = 'anyone', $email = false) {
        if ($file_id) {
            try {
                $permission = [
                    'role' => $role,
                    'type' => $type,
                ];
                if ($email && $type === 'user') {
                    $permission['emailAddress'] = $email;
                }

                $response = wp_remote_post(
                        'https://www.googleapis.com/drive/v3/files/' . $file_id . '/permissions',
                        [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $this->provider['access_token'],
                                'Content-Type' => 'application/json',
                            ],
                            'body' => json_encode($permission), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
                        ]
                );

                if (is_wp_error($response)) {
                    return false;
                }
                $code = wp_remote_retrieve_response_code($response);
                return $code >= 200 && $code < 300;
            } catch (Exception $ex) {
                return false;
            }
        }
        return false;
    }

    public function download($file_id) {
        if ($file_id) {
            $response = wp_remote_get(
                    'https://www.googleapis.com/drive/v3/files/' . $file_id . '?fields=name',
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->provider['access_token'],
                        ],
                    ]
            );
            if (is_wp_error($response)) {
                return false;
            }
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                return false;
            }
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!isset($data['name'])) {
                return false;
            }
            $name = $data['name'];
            $response = wp_remote_get(
                    'https://www.googleapis.com/drive/v3/files/' . $file_id . '?alt=media',
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->provider['access_token'],
                        ],
                    ]
            );
            if (is_wp_error($response)) {
                return false;
            }
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                return false;
            }
            $file = wp_remote_retrieve_body($response);
            return [
                'name' => $name,
                'file' => $file,
            ];
        }
        return false;
    }

    public function body($delimiter, $meta, $file) {
        $body = '';
        $body .= '--' . $delimiter . "\r\n";
        $body .= 'Content-Type: application/json; charset=UTF-8' . "\r\n\r\n";
        $body .= $meta . "\r\n";
        $body .= '--' . $delimiter . "\r\n";
        $body .= "\r\n";
        $body .= $file . "\r\n";
        $body .= '--' . $delimiter . '--' . "\r\n";
        return $body;
    }

    public function exists($file_id) {
        if ($file_id) {
            $response = wp_remote_get(
                    'https://www.googleapis.com/drive/v3/files/' . $file_id . '?fields=id,name,trashed',
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->provider['access_token'],
                        ],
                    ]
            );
            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                if ($code === 200) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    if (isset($data['name'])) {
                        if (isset($data['trashed']) && $data['trashed'] === true) {
                            return false;
                        }
                        return $data['name'];
                    }
                }
            }
        }
        return false;
    }
}
