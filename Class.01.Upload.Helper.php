<?php

/**
 * Plugin Name: Hobo Plugin Framework - Upload Helper.
 * Description: File upload helper.
 * Version: 1.0
 * Author: Hobo Digital Ltd.
 */

namespace Hobo\Framework;

class Upload_Helper
{
    private $_wp_handle_upload_overrides;

    public function __construct($wp_handle_upload_overrides = [])
    {
        if (!isset($wp_handle_upload_overrides['test_form'])) {
            $wp_handle_upload_overrides['test_form'] = FALSE;
        }
        $this->_wp_handle_upload_overrides = $wp_handle_upload_overrides;
    }

    public function upload($wpnonce, string $id, array $allowedExtensions = [], array $uploadDirFilterFunc = NULL, bool $uploadIsOptional = FALSE): array
    {
        $upload_success = FALSE;
        $error = NULL;
        $file = NULL;
        $real_mime = NULL;

        // Catch file upload size error.
        if (empty ($_FILES) && empty ($_POST) && isset ($_SERVER ['REQUEST_METHOD']) && strtolower($_SERVER ['REQUEST_METHOD']) == 'post') {
            $error = 'The file size is larger than ' . ini_get('post_max_size') . '. This is a limitation set by the hosting server';
        }

        // Upload submitted?
        if (is_null($error) && !empty($wpnonce) && wp_verify_nonce(isset($_POST['_wpnonce']) ? $_POST ['_wpnonce'] : '', $wpnonce)) {
            // Check that we actually tried to upload a file.
            if (is_null($error) && empty ($_FILES [$id])) {
                $error = 'No file found. Try again';
            }

            // Check if we have a file.
            if (is_null($error)) {
                $file = $_FILES [$id];
                if (empty ($file ['name'])) {
                    if (!$uploadIsOptional) {
                        $error = 'Nothing uploaded. Please select a file to upload';
                    } else {
                        return array(
                            'success' => FALSE,
                            'error' => NULL,
                            'file' => NULL
                        );
                    }
                }
            }

            // Check that the file extension of the file being uploaded is allowed.
            if (is_null($error)) {
                $file ['name'] = strtolower($file ['name']);
                $upload_extension = strrchr($file ['name'], '.');
                if (!empty($allowedExtensions)) {
                    if (!in_array($upload_extension, $allowedExtensions)) {
                        $error = 'Wrong file type when uploading [' . $file ['name'] . '], file extension should be one of [' . implode(',', $allowedExtensions) . '] not [' . $upload_extension . ']. Try again';
                    }
                }
            }

            // Upload the file.
            if (is_null($error)) {
                if (!is_null($uploadDirFilterFunc)) {
                    // Register our path override.
                    add_filter('upload_dir', $uploadDirFilterFunc);
                }

                // Perform the upload.
                if (!function_exists('wp_handle_upload')) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                }
                if (!function_exists('wp_check_filetype_and_ext')) {
                    require_once ABSPATH . 'wp-includes/functions.php';
                }

                $upload = wp_handle_upload($file, $this->_wp_handle_upload_overrides);
                $file = array_merge($file, $upload);

                // Grab the error if applicable.
                if (isset ($upload ['error'])) {
                    // Hack the excel mime type issue in WordPress.
                    if (in_array($file['type'], array('application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))) {
                        if (!is_null($uploadDirFilterFunc)) {
                            $path = call_user_func($uploadDirFilterFunc, $upload);
                            $file['file'] = $path['path'] . $file['name'];
                            move_uploaded_file($file['tmp_name'], $file['file']);
                            $upload_success = TRUE;
                        }
                    } else {
                        // Failed upload
                        $error = $upload ['error'];
                    }
                } else {
                    // Successful upload
                    $upload_success = TRUE;
                }

                if (is_null($error)) {
                    // Grab the reported mime type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $real_mime = finfo_file($finfo, $file['file']);
                    finfo_close($finfo);
                }

                if (!is_null($uploadDirFilterFunc)) {
                    // Remove our path override.
                    remove_filter('upload_dir', $uploadDirFilterFunc);
                }
            }
        }

        return array(
            'success' => $upload_success,
            'error' => $error,
            'file' => $file,
            'ext' => pathinfo($file['name'], PATHINFO_EXTENSION),
            'mime' => $real_mime
        );
    }

}
