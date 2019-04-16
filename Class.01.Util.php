<?php

/**
 * @wordpress-plugin
 * Plugin Name:   Hobo Plugin Framework - Util.
 * Plugin URI:    https://www.hobo.co.uk
 * Description:   A collection of static utility methods.
 * Version:       0.0.0
 * Author:        Hobo Digital Ltd.
 * Author URI:    https://www.hobo.co.uk
 */

namespace Hobo\Framework;

class Util
{
    /**
     * Check if this is a request at the admin backend.
     *
     * WordPress is_admin() function returns TRUE when using admin-ajax.php from frontend script.
     *
     * @return bool
     */
    public static function is_admin_request(): bool
    {
        $current_url = home_url(add_query_arg(NULL, NULL));
        $admin_url = strtolower(admin_url());
        $referrer = strtolower(wp_get_referer());

        if (0 === strpos($current_url, $admin_url)) {
            // Check if the user comes from a admin page.
            if (0 === strpos($referrer, $admin_url)) {
                return TRUE;
            } else {
                // Check for AJAX requests.
                if (function_exists('wp_doing_ajax')) {
                    return !wp_doing_ajax();
                } else {
                    return !(defined('DOING_AJAX') && DOING_AJAX);
                }
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Pad a string message to force the output buffer to flush.
     *
     * @param $message
     */
    public static function flush_message(string $message): void
    {
        echo str_pad($message, 4096);
        flush();
        ob_flush();
    }

    /**
     * Simple debug reporting.
     *
     * @param        $obj
     * @param string $title
     * @param bool   $debug
     * @param bool   $html
     */
    public static function debug($obj, string $title = '', bool $debug = TRUE, bool $html = FALSE): void
    {
        if ($debug) {
            $caller = debug_backtrace() [1] ['class'] . '::' . debug_backtrace() [1] ['function'];
            if (!empty($title)) {
                echo "<h3>$title</h3>";
            }
            echo "<h4>$caller</h4>";
            if (extension_loaded('xdebug')) {
                var_dump($obj);
            } else {
                $output = print_r($obj, TRUE);
                if ($html === TRUE) {
                    $output = htmlentities($output);
                }
                echo sprintf('<pre>%s</pre>', $output);
            }
        }
    }

    /**
     * Generate a random sequence of alphanumeric characters.
     *
     * @param int $length
     *
     * @return bool|string
     */
    public static function random_alphanumeric(int $length = 10): string
    {
        return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
    }

    /**
     * GUID format is XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX for readability.
     *
     * @return string
     */
    public static function create_guid(): string
    {
        if (function_exists('openssl_random_pseudo_bytes') === TRUE) {
            $data = openssl_random_pseudo_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
            $guid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
            return $guid;
        }

        $token = $_SERVER['HTTP_HOST'];
        $token .= $_SERVER['REQUEST_URI'];
        $token .= uniqid(rand(), TRUE);
        $hash = strtoupper(md5($token));
        $guid = substr($hash, 0, 8) . '-' .
            substr($hash, 8, 4) . '-' .
            substr($hash, 12, 4) . '-' .
            substr($hash, 16, 4) . '-' .
            substr($hash, 20, 12);
        return $guid;
    }

    /**
     * Convert a number of bytes into human readable form.
     *
     * @param $size
     *
     * @return string
     */
    public static function convert_memory_to_human_readable($size): string
    {
        $unit = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }

    /**
     * Use curl to retrieve the contents of the given url.
     *
     * @param string $url
     * @param array  $opts
     *
     * @return array
     */
    public static function http_get_contents(string $url, array $opts = []): array
    {
        $ch = curl_init();
        if (!isset($opts[CURLOPT_TIMEOUT])) {
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (is_array($opts) && $opts) {
            foreach ($opts as $key => $val) {
                curl_setopt($ch, $key, $val);
            }
        }
        if (!isset($opts[CURLOPT_USERAGENT])) {
            curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['SERVER_NAME']);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $error = FALSE;
        if (FALSE === ($retval = curl_exec($ch))) {
            $error = curl_error($ch);
        }
        return ['error' => $error, 'result' => $retval];
    }

    /**
     * Unzip the given file.
     *
     * @param string $path
     * @param string $target
     *
     * @return array
     */
    public static function unzip(string $path, string $target): array
    {
        $files = array();
        if (file_exists($path)) {
            $zip = zip_open($path);
            if (is_resource($zip)) {
                while ($zip_entry = zip_read($zip)) {
                    $zname = sprintf('%s%s', $target, zip_entry_name($zip_entry));
                    $dirname = dirname($zname);
                    if (!file_exists($dirname)) {
                        mkdir($dirname, 0755, TRUE);
                    }
                    $lastChar = $zname [strlen($zname) - 1];
                    if ($lastChar == '/' || $lastChar == '\\') {
                        if (!file_exists($zname)) {
                            mkdir($zname, 0755, TRUE);
                        }
                    } else {
                        if (zip_entry_open($zip, $zip_entry, 'r')) {
                            $fstream = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                            file_put_contents($zname, $fstream);
                            zip_entry_close($zip_entry);
                            $files[] = $zname;
                        }
                    }
                }
                zip_close($zip);
            }
        }
        return $files;
    }

    /**
     * Delete a file or folder.
     *
     * @param $target
     */
    public static function delete_files($target): void
    {
        if (is_dir($target)) {
            $files = glob($target . '*', GLOB_MARK);
            foreach ($files as $file) {
                if ($file === '..' || $file === '.') {
                    continue;
                }
                if (is_file($file)) {
                    @unlink($file);
                } else if (is_dir($file)) {
                    // Recursive call - be careful, you don't want to pop the stack.
                    self::delete_files($file);
                }
            }
            rmdir($target);
        } else if (is_file($target)) {
            @unlink($target);
        }
    }

}
