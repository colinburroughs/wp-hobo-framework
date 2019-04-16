<?php

/**
 * @wordpress-plugin
 * Plugin Name:   Hobo Plugin Framework - Template Redirect.
 * Plugin URI:    https://www.hobo.co.uk
 * Description:   Abstract class to provide basic Wordpress template redirection.  Provided implementation for saving, restoring, and uploading plugin settings.
 * Version:       0.0.0
 * Author:        Hobo Digital Ltd.
 * Author URI:    https://www.hobo.co.uk
 */

namespace Hobo\Framework;

abstract class Template_Redirect
{
    public $_plugin;

    public function __construct(Plugin $plugin)
    {
        $this->_plugin = $plugin;
        add_action('template_redirect', array(&$this, 'wp_template_redirect'), 100);
    }

    public function send_response_and_exit(int $code = 200)
    {
        status_header($code);
        exit;
    }

    public function wp_template_redirect()
    {
        if (!isset($_REQUEST['page'])) {
            return FALSE;
        }
        $page = $_REQUEST['page'];

        if (is_user_logged_in()) {
            switch ($page) {
                // =================================================================================
                // DOWNLOAD AND SERIALIZE PLUGIN SETTINGS - User must have administrator capability
                // =================================================================================
                case $this->_plugin->get_settings_slug():
                    if (!current_user_can('administrator')) {
                        $this->send_response_and_exit(401);
                    }
                    $settings = $this->_plugin->get_settings();
                    $serializedSettings = serialize($settings);
                    if (!empty ($serializedSettings)) {
                        $filename = $this->_plugin->get_serialized_settings_filename();
                        file_put_contents($filename, $serializedSettings, LOCK_EX);

                        $info = pathinfo($filename);
                        $fh = $this->content_header($info['filename'], $info['extension']);
                        fwrite($fh, $serializedSettings);
                        fclose($fh);
                    } else {
                        $this->set_download_response_cookie(-1);
                    }
                    $this->send_response_and_exit();
                    break;
                // ======================================================================
                // RESTORE PLUGIN SETTINGS - User must have administrator capability
                // ======================================================================
                case $this->_plugin->get_settings_slug() . '_restore' :
                    if (!current_user_can('administrator')) {
                        $this->send_response_and_exit(401);
                    }
                    $restore_settings = @file_get_contents($this->_plugin->get_serialized_settings_filename());
                    if (empty($restore_settings)) {
                        $this->set_download_response_cookie(-1, TRUE);
                        $this->send_response_and_exit();
                    }
                    $restore_settings = unserialize($restore_settings);
                    if ($this->_plugin->get_settings() !== $restore_settings) {
                        $this->_plugin->clear_settings();
                        update_option($this->_plugin->get_option_group(), $restore_settings);
                        $this->set_download_response_cookie(1, TRUE);
                        $this->send_response_and_exit();
                    }
                    $this->set_download_response_cookie(2, TRUE);
                    $this->send_response_and_exit();
                    break;
                // ======================================================================
                // IMPORT PLUGIN SETTINGS - User must have administrator capability
                // ======================================================================
                case $this->_plugin->get_settings_slug() . '_import' :
                    if (!current_user_can('administrator')) {
                        $this->send_response_and_exit(401);
                    }
                    $redirect = admin_url('admin.php?page=' . $this->_plugin->get_settings_slug());
                    $marker = array_key_exists('upload-marker', $_REQUEST) ? sanitize_text_field($_REQUEST ['upload-marker']) : NULL;
                    $helper = new Upload_Helper(['test_type' => FALSE]);
                    $upload_result = $helper->upload($marker, 'upload-file', array('.txt'), array($this, 'get_temporary_directory'));
                    $status = 0;
                    if ($upload_result ['success'] === TRUE) {
                        $importSettings = unserialize(file_get_contents($upload_result ['file'] ['file']));
                        unlink($upload_result ['file'] ['file']);
                        if (is_array($importSettings)) {
                            $this->_plugin->clear_settings();
                            update_option($this->_plugin->get_option_group(), $importSettings);
                            $status = 1;
                        }
                    }
                    wp_redirect($redirect . '&status=' . $status);
                    exit;
            }
        }
        return $page;
    }

    public function get_plugin(): Plugin
    {
        return $this->_plugin;
    }

    /**
     * Relies on https://github.com/witstep/response-monitor.js
     *
     * @param int  $status
     * @param bool $output
     */
    protected function set_download_response_cookie(int $status = 1, bool $output = FALSE): void
    {
        $cookiePrefix = 'response-monitor'; // must match the one set on the client options
        if (isset($_GET[$cookiePrefix])) {
            $cookieName = $cookiePrefix . '_' . $_GET[$cookiePrefix]; // eg: response-monitor_1419642741528
            setcookie($cookieName, $status, time() + 30, $_SERVER['HTTP_HOST']);
        }
        if ($output === TRUE) {
            echo $status;
        }
    }

    /**
     * @param string $filename
     * @param string $type
     *
     * @return bool|resource
     */
    protected function content_header(string $filename, string $type = 'txt')
    {
        $filename = sanitize_file_name($filename . '.' . $type);
        $mime = 'text/plain';
        switch ($type) {
            case 'csv' :
                $mime = 'text/csv';
                break;
        }

        $this->set_download_response_cookie(1);

        // Force an HTTP 200 header response
        http_response_code(200);

        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Description: File Transfer');
        header('Content-type: ' . $mime);
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Expires: 0');
        header('Pragma: public');
        $fh = fopen('php://output', 'w');
        return $fh;
    }

    /**
     * Output the given file to CSV.
     *
     * @param $filename
     * @param $results
     */
    protected function download_to_csv(string $filename, array $results): void
    {
        if (!empty ($results)) {
            $fh = $this->content_header($filename, 'csv');
            // Write the header.
            fputcsv($fh, array_keys(get_object_vars($results [0])));
            // Write the data.
            foreach ($results as $row) {
                $arr = get_object_vars($row);
                // A bit of a hack to get some numeric values to display correctly in Excel.
                $arr = array_map(function ($o) {
                    return "$o\t";
                }, $arr);
                fputcsv($fh, $arr);
            }
            // Close the stream.
            fclose($fh);
        } else {
            $this->set_download_response_cookie(-1, TRUE);
            $this->send_response_and_exit();
        }
        exit;
    }

    /**
     * Override the temporary file upload location.
     *
     * protected function upload_temporary_directory($upload) {
     *     $upload ['path'] = UPLOAD_ROOT_FOLDER;
     *     return $upload;
     * }
     *
     * @param array $upload
     *
     * @return array
     */
    protected function upload_temporary_directory(array $upload): array
    {
        return $upload;
    }

    public function get_temporary_directory(array $upload): array
    {
        $upload ['path'] = sys_get_temp_dir();
        return $upload;
    }

}
