<?php

/**
 * Plugin Name: Hobo Plugin Framework - Plugin.
 * Description: Abstract class to provide the base functionality and structure to build plugins.
 * Version: 1.0.0
 * Author: Hobo Digital Ltd.
 *
 * Your extended class must end with '_Plugin'.
 *
 * Filters
 *   xxxxxx_error_shutdown_report_error_only - Return boolean, default TRUE.
 *   xxxxxx_error_shutdown_email_recipient   - Return the recipient of the error report, default is the blog admin email address.
 *   xxxxxx_error_shutdown_message           - Return the constructed error message.
 *
 * Hooks
 *   xxxxxx_error_shutdown_message_action    - What to do with the constructed error message.
 *   xxxxxx_update                           - The plugin has had a version update.
 */

namespace Hobo\Framework;

use ReflectionObject;
use WP_Admin_Bar;

abstract class ADMIN_NOTICE_TYPE
{
    const INFO = 'info';
    const SUCCESS = 'success';
    const WARNING = 'warning';
    const ERROR = 'error';
}

define('HOBO_FRAMEWORK_ROOT_PATH', trailingslashit(dirname(__FILE__)));
define('HOBO_FRAMEWORK_ROOT_URL', plugin_dir_url(__FILE__));

abstract class Plugin
{
    /** @var object Admin implementation. */
    private $_admin;

    /** @var array Plugin settings */
    private $_settings;

    /** @var array Framework plugin data */
    private $_framework_plugin_data;

    /** @var array Plugin data */
    private $_plugin_data;

    /** @var string asset version */
    private $_asset_version;

    /** @var string child class filename */
    private $_filename;

    /** @var string plugin URL */
    private $_url;

    /** @var string plugin path */
    private $_path;

    /** @var string child class name */
    private $_name;

    /**  @var string package name */
    private $_package;

    /** @var string plugin namespace */
    private $_namespace;

    /** @var string option group name */
    private $_option_group;

    /** @var string admin settings page slug */
    private $_settings_slug;

    /** @var string admin settings serialized settings filename */
    private $_serialized_settings_filename;

    /** @var int maximum transient key size */
    const MAX_TRANSIENT_KEY_SIZE = 45;

    /** @var string the plugin version setting name */
    const SETTING_PLUGIN_VERSION_NAME = 'plugin_version';

    public function __construct()
    {
        /* Reflect the object. */
        $reflectionObject = new ReflectionObject($this);

        /* The class filename. */
        $this->_filename = $reflectionObject->getFileName();

        /* The class name. */
        $this->_name = $reflectionObject->getName();

        /* The package. */
        $this->_package = strtolower(preg_replace('/_Plugin/', '', $this->_name));

        /* The plugin root url. */
        $this->_url = plugin_dir_url($this->_filename);

        /* The plugin root path. */
        $this->_path = str_replace('\\', '/', plugin_dir_path($this->_filename));

        /* The options group */
        $this->_option_group = strtolower($this->_name . '_options');

        /* The admin settings page slug  */
        $this->_settings_slug = $this->_package . '_settings';

        /* The serialized settings filename */
        $this->_serialized_settings_filename = $this->get_setting('upload_root_folder', wp_get_upload_dir()['basedir']) . DIRECTORY_SEPARATOR . strtolower(str_replace(' ', '_', sprintf('%s_%s.txt', get_bloginfo('name'), $this->_settings_slug)));

        /* The asset version. */
        add_action('plugins_loaded', function () {
            if (!function_exists('get_plugin_data')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $this->_asset_version = $this->get_plugin_data()['Version'];
        });

        /* Define encryption parameters. */
        $this->encryption_parameters();

        /* Create the autoloader. */
        $this->autoloader();

        /* Register activation hook. */
        register_activation_hook($this->_filename, array($this, 'activation'));

        /* Register deactivation hook. */
        register_deactivation_hook($this->_filename, array($this, 'deactivation'));

        /* Register uninstall hook. */
        register_uninstall_hook($this->_filename, 'uninstall');

        /* Load the admin files. */
        add_action('admin_menu', array($this, 'wp_plugin_admin'), 1);

        // Add plugin quick links to the WordPress admin toolbar.
        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 100);

        // Plugin localisation
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));

        // Plugin updates
        add_action('plugins_loaded', array($this, 'plugin_update'));

        // Register the shutdown function.
        register_shutdown_function(array(&$this, 'error_shutdown_function'));
    }

    private function autoloader(): void
    {
        $autoloader_path = $this->_path . 'classes' . DIRECTORY_SEPARATOR . 'autoloader.php';
        require_once $autoloader_path;
        $autoloader = $this->_name . '_Autoloader';
        if (class_exists($autoloader)) {
            $loader = new $autoloader;
            $this->_namespace = $loader->get_namespace();
        } else {
            wp_die(sprintf('Unable to locate autoloader class <em>%s</em> at <em>%s</em><p>Example implementation:</p><pre>%s</pre>', $autoloader, realpath($autoloader_path), 'use Hobo\Framework\Autoloader;

class My_Class_Plugin_Autoloader extends Autoloader
{
    public function __construct()
    {
        parent::__construct(__FILE__, \'My_Namespace\');
    }
}
'));
        }
    }

    public function capability_error(): void
    {
        $return = [];
        $return['success'] = FALSE;
        $return['ajax'] = wp_doing_ajax();
        $return['error'] = 'You do not have the required capability';
        wp_send_json($return, 200);
    }

    public function capability_error_page(): void
    {
        wp_die('You do not have the required capability to access this page.');
    }

    /**
     * Create a new instance of your new Plugin_Admin implementation class.
     */
    public function wp_plugin_admin(): void
    {
        $class = sprintf('%s\\Admin\\%s_Admin', $this->get_namespace(), $this->get_package());
        $this->_admin = Singleton::get_instance($class, [$this]);
    }

    /**
     * Override this method to add quick links to the WordPress admin toolbar.
     *
     * @param $admin_bar
     */
    public function admin_bar_menu(WP_Admin_Bar $admin_bar): void
    {
    }

    /**
     * Loads the language context. Defaults to en_GB.
     *
     * @return bool
     */
    public function load_plugin_textdomain(): bool
    {
        $mo_path = $this->get_plugin_path() . 'languages/';
        $mo = $mo_path . get_user_locale() . '.mo';
        if (!file_exists($mo)) {
            $mo = $mo_path . 'en_GB.mo';
        }
        if (!file_exists($mo)) {
            return FALSE;
        }
        return load_textdomain($this->get_plugin_data()['TextDomain'], $mo);
    }

    public function plugin_update(): bool
    {
        $version = $this->_asset_version;
        $update = version_compare($version, $this->get_setting(self::SETTING_PLUGIN_VERSION_NAME, 0), '>');
        if ($update) {
            do_action($this->_package . '_update', $version);
            $this->put_setting(self::SETTING_PLUGIN_VERSION_NAME, $version);
        }
        return $update;
    }

    /**
     * @return string
     */
    public function get_namespace(): string
    {
        return $this->_namespace;
    }

    /**
     * @return string
     */
    public function get_package(): string
    {
        return $this->_package;
    }

    /**
     * @return string
     */
    public function get_filename(): string
    {
        return $this->_filename;
    }

    /**
     * @return string
     */
    public function get_name(): string
    {
        return $this->_name;
    }

    /**
     * @return object
     */
    public function get_admin()
    {
        return $this->_admin;
    }

    /**
     * @return string
     */
    public function get_asset_version(): string
    {
        return $this->_asset_version;
    }

    /**
     * @return string
     */
    public function get_plugin_url(): string
    {
        return $this->_url;
    }

    /**
     * @return string
     */
    public function get_plugin_path(): string
    {
        return $this->_path;
    }

    /* -- Plugin settings -- */

    /**
     * @return string
     */
    public function get_option_group(): string
    {
        return $this->_option_group;
    }

    public function clear_settings(): void
    {
        $this->_settings = NULL;
    }

    /**
     * @return array
     */
    function get_settings(): array
    {
        if (is_null($this->_settings)) {
            $this->_get_settings();
        }
        return $this->_settings;
    }

    private function _get_settings(): void
    {
        if (is_null($this->_settings)) {
            $this->_settings = get_option($this->get_option_group());
        }
        if (!is_array($this->_settings)) {
            $this->_settings = array();
        }

        $this->_settings = wp_parse_args($this->_settings, $this->get_setting_defaults());
    }

    /**
     * @return string
     */
    public function get_settings_slug(): string
    {
        return $this->_settings_slug;
    }

    /**
     * @return string
     */
    public function get_serialized_settings_filename(): string
    {
        return $this->_serialized_settings_filename;
    }

    /**
     * @return array
     */
    public function get_setting_defaults(): array
    {
        return [
            'upload_root_folder' => wp_get_upload_dir()['basedir'],
            SETTING_NAME::MISSING_REQUIRED_SETTING => FALSE
        ];
    }

    /**
     * @param string $settingName
     * @param bool   $default
     *
     * @return bool|mixed
     */
    function get_setting(string $settingName, $default = FALSE)
    {
        if (is_null($this->_settings)) {
            $this->_get_settings();
        }
        if (isset ($this->_settings [$settingName])) {
            return $this->_settings [$settingName];
        } else {
            return $default;
        }
    }

    /**
     * @param string $settingName
     * @param        $value
     *
     * @return bool
     */
    function put_setting(string $settingName, $value): bool
    {
        if (is_null($this->_settings)) {
            $this->_get_settings();
        }
        $this->_settings [$settingName] = $value;
        return update_option($this->get_option_group(), $this->_settings);
    }

    /* -- Plugin data -- */

    /**
     * @return array
     */
    public function get_plugin_data(): array
    {
        if (empty ($this->_plugin_data)) {
            $this->_plugin_data = get_plugin_data($this->_filename);
        }
        return $this->_plugin_data;
    }

    /**
     * @return array
     */
    public function get_framework_plugin_data(): array
    {
        if (empty ($this->_framework_plugin_data)) {
            $this->_framework_plugin_data = get_plugin_data(__FILE__);
        }
        return $this->_framework_plugin_data;
    }

    /* -- Activation / Deactivation / Uninstall -- */

    abstract function activation();

    abstract function deactivation();

    abstract function uninstall();

    /* -- Transient Cache -- */

    /**
     * Return a boolean value.
     */
    abstract function cache_output();

    /**
     * @param $function
     * @param $attributes
     *
     * @return string
     */
    public function get_cache_key($function, $attributes): string
    {
        return $this->get_cache_prefix() . md5(serialize(func_get_args()));
    }

    /**
     * Return a string value of 13 characters or less.
     *
     * @return string
     */
    abstract function get_cache_prefix(): string;

    /**
     * Retrieve a value from the transient cache.
     *
     * @param $cache_key
     *
     * @return bool|mixed|string
     */
    public function get_from_cache(string $cache_key)
    {
        if ($this->cache_output()) {
            if (!empty ($cache_key)) {
                $cache_content = get_transient($cache_key);
                if ($cache_content) {
                    return $cache_content;
                }
            }
        }
        return FALSE;
    }

    /**
     * Put a value in the transient cache.
     *
     * @param string    $cache_key
     * @param           $value
     * @param int       $duration
     */
    public function put_in_cache(string $cache_key, $value, int $duration = WEEK_IN_SECONDS): void
    {
        if ($this->cache_output()) {
            if (!empty ($cache_key)) {
                if (strlen($cache_key) > self::MAX_TRANSIENT_KEY_SIZE) {
                    wp_die(sprintf('Transient cache key [%s] is longer than %d characters. Please ensure your cache_key_prefix is %d characters or less.', $cache_key, self::MAX_TRANSIENT_KEY_SIZE, self::MAX_TRANSIENT_KEY_SIZE - 32));
                }
                set_transient($cache_key, $value, $duration);
            }
        }
    }

    /**
     * @return bool|int
     */
    public function clear_cache()
    {
        global $wpdb;
        $cache_key_prefix = $this->get_cache_prefix();
        $sql = "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_" . $cache_key_prefix . "%' OR option_name LIKE '_transient_timeout_" . $cache_key_prefix . "%'";
        return $wpdb->query($sql);
    }

    /**
     * @return mixed
     */
    public function get_cache_count()
    {
        global $wpdb;
        $cache_key_prefix = $this->get_cache_prefix();
        $sql = "SELECT count(*) as 'COUNT' FROM $wpdb->options WHERE option_name LIKE '_transient_" . $cache_key_prefix . "%' OR option_name LIKE '_transient_timeout_" . $cache_key_prefix . "%'";
        return $wpdb->get_results($sql) [0];
    }

    /* -- Encryption -- */

    /**
     * Does your plugin require encryption?
     *
     * @return bool
     */
    public function use_encryption(): bool
    {
        return FALSE;
    }

    /**
     * Define encryption parameters.
     *
     * Check that ENCRYPTION_KEY_VALUE and ENCRYPTION_IV_VALUE values are defined and do not match the predefined values.
     *
     * Define unique ENCRYPTION_KEY_VALUE and ENCRYPTION_IV_VALUE outside of your plugin.
     */
    private function encryption_parameters(): void
    {
        if ($this->use_encryption()) {
            if (!defined('ENCRYPTION_KEY_VALUE')) {
                define('ENCRYPTION_KEY_VALUE', 'NVWG9bMgetehyASFB4FZIJlOJomwiQKk'); // Default value
                define('ENCRYPTION_KEY_VALUE_DEFAULT', TRUE); // Flag the fact that the encryption key is the default value.
            }
            if (!defined('ENCRYPTION_IV_VALUE')) {
                define('ENCRYPTION_IV_VALUE', 'KddhSnjJQHLLOOEcB1g9AihLTCK0eyFv'); // Default value
                define('ENCRYPTION_IV_VALUE_DEFAULT', TRUE); // Flag the fact that the encryption IV is the default value.
            }

            if (!defined('ENCRYPTION_KEY')) {
                define('ENCRYPTION_KEY', hash('sha256', ENCRYPTION_KEY_VALUE));
            }
            if (!defined('ENCRYPTION_IV')) {
                define('ENCRYPTION_IV', substr(hash('sha256', ENCRYPTION_IV_VALUE), 0, 16));
            }
        }
    }

    /**
     * @param string $data
     * @param string $encrypt_method
     *
     * @return string
     */
    public function encrypt(string $data, string $encrypt_method = 'AES-256-CBC'): string
    {
        if ($this->use_encryption()) {
            $data = base64_encode(openssl_encrypt($data, $encrypt_method, ENCRYPTION_KEY, 0, ENCRYPTION_IV));
        }
        return $data;
    }

    /**
     * @param string $data
     * @param string $encrypt_method
     *
     * @return string
     */
    public function decrypt(string $data, string $encrypt_method = 'AES-256-CBC'): string
    {
        if ($this->use_encryption()) {
            $data = openssl_decrypt(base64_decode($data), $encrypt_method, ENCRYPTION_KEY, 0, ENCRYPTION_IV);
        }
        return $data;
    }

    /* -- Error reporting -- */

    public function error_shutdown_function(): void
    {
        $error_only = TRUE;
        $error_only = apply_filters($this->_package . '_error_shutdown_report_error_only', $error_only);
        $error = error_get_last();
        if ($error !== NULL) {
            // Only report if this is a plugin error by checking the file paths.
            $error_file = realpath($error['file']);
            $plugin_path = realpath($this->get_plugin_path());
            if (substr($error_file, 0, strlen($plugin_path)) === $plugin_path) {
                $error_number = $error['type'];
                if (!$error_only || ($error_only && $error_number == E_ERROR)) {
                    $error_line = $error['line'];
                    $error_message = $error['message'];

                    $blog_name = get_bloginfo('name');
                    $blog_url = get_bloginfo('url');

                    $email_recipient = get_option('admin_email');
                    $email_recipient = apply_filters($this->_package . '_error_shutdown_email_recipient', $email_recipient);

                    $report_message = <<<EOT
This is an error report from $blog_name - $blog_url
                    
Error No: $error_number
Error File: $error_file
Error Line: $error_line
Error Msg: $error_message

EOT;
                    $report_message = apply_filters($this->_package . '_error_shutdown_message', $report_message, $report_message, $blog_name, $blog_url, $email_recipient, $error);
                    do_action($this->_package . '_error_shutdown_message_action', $report_message, $blog_name, $blog_url, $email_recipient);
                }
            }
        }
    }

}
