<?php

/**
 * Plugin Name: Hobo Plugin Framework - Plugin Admin.
 * Description: Abstract class for ModelViewController (MVC) operations for a plugin.
 * Version: 1.0
 * Author: Hobo Digital Ltd.
 */

namespace Hobo\Framework;

abstract class Plugin_Admin extends MVC_Controller
{
    private $_settings = NULL;

    private $_is_options_general = FALSE;

    public function __construct(Plugin $plugin)
    {
        parent::__construct($plugin);

        // Add plugin settings if we are on the plugin settings page.
        $page = $this->get_page();
        if ($page === $plugin->get_settings_slug()) {
            add_action('admin_init', array($this, 'do_settings'));
        }

        // Add standard admin javascript and css.
        add_action('admin_init', function () {
            $framework_version = $this->get_plugin()->get_framework_plugin_data()['Version'];
            wp_enqueue_script('hobo-framework-admin', HOBO_FRAMEWORK_ROOT_URL . 'js/admin.min.js', array(), $framework_version, TRUE);

            wp_enqueue_script('polyfill-js', 'https://cdnjs.cloudflare.com/ajax/libs/core-js/2.4.1/core.js'); // Promise library for stupid IE.
            wp_enqueue_script('sweetalert2', HOBO_FRAMEWORK_ROOT_URL . 'js/sweetalert2/dist/sweetalert2.js', array(), '6.1.1', TRUE);
            wp_enqueue_script('response-monitor', HOBO_FRAMEWORK_ROOT_URL . 'js/responseMonitor/response-monitor.min.js', array(), '1.0.0', TRUE);

            wp_enqueue_style('hobo-framework-admin', HOBO_FRAMEWORK_ROOT_URL . 'css/admin.min.css', NULL, $framework_version);
            wp_enqueue_style('sweetalert2', HOBO_FRAMEWORK_ROOT_URL . 'js/sweetalert2/dist/sweetalert2.css', array(), '6.1.1', "all");

            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-slider');
            wp_enqueue_script('jquery-ui-datepicker');

            $wp_scripts = wp_scripts();
            wp_enqueue_style('admin-jquery-ui-css', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $wp_scripts->registered['jquery-ui-core']->ver . '/themes/smoothness/jquery-ui.css', FALSE, $framework_version, FALSE);
        });

        // Add plugin admin menu items.
        add_action('admin_menu', array($this, 'admin_menu'));

        // Add plugin admin notices.
        add_action('admin_notices', array($this, 'admin_notices'));

        // Add extra links to the plugin admin settings page from the plugin listing page.
        add_filter('plugin_action_links', array($this, 'plugin_action_links'), 10, 2);
    }

    /**
     * You must call $this->set_is_options_general(TRUE); if your settings page resides under the general WordPress settings page (by using add_options_page).
     * The settings page admin url is different when settings are defined as their own settings page.
     */
    public abstract function admin_menu(): void;

    public function admin_notices(): void
    {
        if ($this->get_plugin()->get_setting(SETTING_NAME::MISSING_REQUIRED_SETTING) === TRUE) {
            $settings_link = '<a style="text-decoration: none;" href="' . admin_url(sprintf('%s?page=%s', $this->_is_options_general ? 'options-general.php' : 'admin.php', $this->get_plugin()->get_settings_slug())) . '"><i class="dashicons-before dashicons-admin-settings"></i> Settings</a>';
            $this->add_admin_notice(ADMIN_NOTICE_TYPE::WARNING, sprintf('Missing required %s.', $settings_link), TRUE);
        }
    }

    /**
     * @return bool
     */
    public function is_options_general(): bool
    {
        return $this->_is_options_general;
    }

    /**
     * @param bool $is_options_general
     */
    public function set_is_options_general(bool $is_options_general): void
    {
        $this->_is_options_general = $is_options_general;
    }

    /**
     * @param string $type
     * @param string $text
     * @param bool   $is_dismissible
     */
    public function add_admin_notice(string $type, string $text, bool $is_dismissible = TRUE): void
    {
        printf('<div class="notice notice-%s%s"><p>%s - %s</p></div>', $type, $is_dismissible === TRUE ? ' is-dismissible' : '', $this->get_plugin()->get_plugin_data()['Name'], $text);
    }

    /**
     * Add a link to the plugin admin settings page from the plugin listing page.
     *
     * @param $links
     * @param $file
     *
     * @return mixed
     */
    public function plugin_action_links(array $links, string $file): array
    {
        if (basename($file) === basename($this->get_plugin()->get_filename())) {
            $settings_link = '<a href="' . admin_url(sprintf('%s?page=%s', $this->_is_options_general ? 'options-general.php' : 'admin.php', $this->get_plugin()->get_settings_slug())) . '"><i class="dashicons-before dashicons-admin-settings"></i> Settings</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }

    /**
     * Action 'admin_init' response. Only called when we are on the plugin settings page.
     */
    public function do_settings(): void
    {
        $this->_settings = $this->create_settings_object();
        $this->_settings->prepare_settings();
    }

    /**
     * Create the plugin settings object.
     */
    public function create_settings_object()
    {
        $class = sprintf('%s\\Admin\\%s_Settings', $this->get_plugin()->get_namespace(), $this->get_plugin()->get_package());
        return Singleton::get_instance($class, [$this->get_plugin()]);
    }

    public function get_temporary_directory($upload)
    {
        $upload ['path'] = sys_get_temp_dir();
        return $upload;
    }

    public function add_menu_separator($menu, $capability)
    {
        add_submenu_page($menu, '', '<span style="display:block; margin:1px 0 1px -5px; padding:0; height:1px; line-height:1px; background:#CCCCCC;"></span>', $capability, '#');
    }

    /**
     * @param string      $title
     * @param bool|string $dashicon
     */
    public function page_title(string $title, $dashicon = FALSE): void
    {
        $data = $this->get_plugin()->get_plugin_data();
        if ($dashicon !== FALSE) {
            $logo = '<span class="alignleft dashicons ' . $dashicon . '" style="width:40px;font-size:40px;"></span>';
        } else {
            $logo = '<img src="' . $this->get_plugin()->get_plugin_url() . 'img/logo.svg" class="alignleft" style="width: 44px;"/>';
        }
        echo sprintf('<div class="plugin-title">%s<h2 style="display:table-cell;height:44px;vertical-align:middle;"> &nbsp; <em>%s v%s - %s</em><br/> &nbsp; <span style="font-size:12px;">written by %s</span></h2></div>',
            $logo,
            $data['Name'],
            $data['Version'],
            $title,
            $this->get_plugin()->get_plugin_data()['Author']
        );
    }

    public function mvc_action_settings()
    {
        // Report import settings result
        if (array_key_exists('status', $_GET)) {
            if ($_GET['status'] === '1') {
                add_settings_error('import', NULL, sprintf('Successfully imported %s settings.', $this->get_plugin()->get_plugin_data()['Name']), 'updated');
            } else if ($_GET['status'] === '0') {
                add_settings_error('import', NULL, sprintf('Failed to import %s settings.', $this->get_plugin()->get_plugin_data()['Name']), 'error');
            }
        }

        // Can we create setting backups?
        $template_redirect_class_name = preg_replace('/_Plugin/', '_Template_Redirect', $this->get_plugin()->get_name());
        $this->put('canbackup', class_exists(sprintf('%s\%s', $this->get_plugin()->get_namespace(), $template_redirect_class_name), FALSE));

        // Last backup date - Do we have a backup of our settings?
        $filename = $this->get_plugin()->get_serialized_settings_filename();
        $this->put('backupdate', file_exists($filename) ? date("F dS Y H:i:s", filemtime($filename)) : FALSE);

        // Standard settings page variables
        $page = $this->get_page();
        $this->put('page', $page);
        $this->put('siteurl', site_url());
        $this->put('group', $this->get_plugin()->get_option_group());
        $this->put('adminurl', admin_url(sprintf('%s?page=%s', $this->_is_options_general ? 'options-general.php' : 'admin.php', $this->get_plugin()->get_settings_slug())));
        $this->put('upload-marker', $this->get_plugin()->get_package() . '-import-settings-upload');
        $this->put('nonce', wp_create_nonce($this->get('upload-marker')));
        $section = NULL;
        if (array_key_exists('section', $_REQUEST)) {
            $section = $_REQUEST ['section'];
        } else {
            $settings = $this->_settings->get_settings();
            if (count($settings) === 1) {
                $section = trim(strip_tags($settings[0]['title']));
            }
        }
        $this->put('section', $section);
        $this->put('hasrequired', $this->_settings->get_has_required());
        $this->put('settings', $this->_settings);

        // Enqueue WordPress media scripts if required.
        if ($this->_settings->get_has_media() === TRUE) {
            wp_enqueue_media();
        }

        $framework_version = $this->get_plugin()->get_framework_plugin_data()['Version'];

        // Enqueue WordPress colorpicker scripts if required.
        if ($this->_settings->get_has_color_picker() === TRUE) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script( 'wp-color-picker-alpha', WPMU_PLUGIN_URL . '/hobo-framework/js/wp-color-picker-alpha.min.js', array( 'wp-color-picker' ), $framework_version, TRUE );
        }

        // Standard settings page javascript
        wp_enqueue_script('hobo-settings', WPMU_PLUGIN_URL . '/hobo-framework/views/js/settings.min.js', array('jquery', 'jquery-ui-core'), $framework_version, TRUE);
        wp_enqueue_script('hobo-standard-form', WPMU_PLUGIN_URL . '/hobo-framework/views/js/standard-form.min.js', array('jquery'), $framework_version, TRUE);

        // Standard settings css
        wp_enqueue_style('hobo-settings', WPMU_PLUGIN_URL . '/hobo-framework/css/settings.min.css', NULL, $framework_version);
    }

}
