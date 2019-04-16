<?php

/**
 * @wordpress-plugin
 * Plugin Name:   Hobo Plugin Framework - Plugin Disable..
 * Plugin URI:    https://www.hobo.co.uk
 * Description:   Disable plugins based on regexp for page and ajax requests.
 * Version:       0.0.0
 * Author:        Hobo Digital Ltd.
 * Author URI:    https://www.hobo.co.uk
 *
 * eg:
 *   $plugin_disable = new Plugin_Disable([
 *      'plugin-folder/plugin-filename.php' => new Plugin_Disable_Container(
 *           new Plugin_Disable_Config('regular_expression', Plugin_Disable_Config::DISABLE_NOT | Plugin_Disable_Config::DISABLE), new Plugin_Disable_Config | NULL)
 *   ]);
 */

namespace Hobo\Framework;

/**
 * Class Plugin_Disable_Container
 * @package Hobo\Framework
 */
class Plugin_Disable_Container
{
    private $_page;
    private $_ajax;

    /**
     * Plugin_Disable_Container constructor.
     *
     * @param \Hobo\Framework\Plugin_Disable_Config $page
     * @param \Hobo\Framework\Plugin_Disable_Config $ajax
     */
    public function __construct(Plugin_Disable_Config $page = NULL, Plugin_Disable_Config $ajax = NULL)
    {
        $this->_page = $page;
        $this->_ajax = $ajax;
    }

    /**
     * @return Plugin_Disable_Config
     */
    public function get_page()
    {
        return $this->_page;
    }

    /**
     * @param Plugin_Disable_Config $page
     */
    public function set_page(Plugin_Disable_Config $page): void
    {
        $this->_page = $page;
    }

    /**
     * @return Plugin_Disable_Config
     */
    public function get_ajax()
    {
        return $this->_ajax;
    }

    /**
     * @param Plugin_Disable_Config $ajax
     */
    public function set_ajax(Plugin_Disable_Config $ajax): void
    {
        $this->_ajax = $ajax;
    }

}

/**
 * Class Plugin_Disable_Config
 * @package Hobo\Framework
 */
class Plugin_Disable_Config
{
    private $_regexp;
    private $_disable_type;

    /** Disable the plugin when regexp match is not found. */
    const INACTIVE_ON = FALSE;

    /** Disable the plugin when regexp match is found. */
    const ACTIVE_ON = TRUE;

    /**
     * Plugin_Disable_Config constructor.
     *
     * @param string $regexp
     * @param bool   $disable_type
     */
    public function __construct(string $regexp, bool $disable_type)
    {
        $this->_regexp = $regexp;
        $this->_disable_type = $disable_type;
    }

    /**
     * @return string
     */
    public function get_regexp(): string
    {
        return $this->_regexp;
    }

    /**
     * @param string $regexp
     */
    public function set_regexp(string $regexp): void
    {
        $this->_regexp = $regexp;
    }

    /**
     * @return bool
     */
    public function get_disable_type(): bool
    {
        return $this->_disable_type;
    }

    /**
     * @param bool $disable_type
     */
    public function set_disable_type(bool $disable_type): void
    {
        $this->_disable_type = $disable_type;
    }

}

/**
 * Class Plugin_Disable
 * @package Hobo\Framework
 */
class Plugin_Disable
{
    private $_debug = FALSE;
    private $_filtered_plugin_list = NULL;
    private $_disable_plugins = NULL;

    /**
     * Plugin_Disable constructor.
     *
     * @param array $disable_plugins
     * @param bool  $debug
     */
    public function __construct(array $disable_plugins = [], bool $debug = FALSE)
    {
        $this->_disable_plugins = $disable_plugins;
        $this->_debug = $debug;
        if (!Util::is_admin_request()) {
            add_filter('option_active_plugins', array($this, 'disable_plugins_per_page'));
        }
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }

    /**
     * The 'plugin_row_meta' filter.
     *
     * @param array  $actions
     * @param string $file
     *
     * @return array
     */
    public function plugin_row_meta(array $actions, string $file): array
    {
        if (isset($this->_disable_plugins[$file])) {
            $value = '<br/><br/><em><i class="dashicons dashicons-filter"></i> Plugin filtering provided by ' . __CLASS__ . '<ul>';
            foreach ([$this->_disable_plugins[$file]->get_page(), $this->_disable_plugins[$file]->get_ajax()] as $index => $item) {
                if (!is_null($item)) {
                    $value .= sprintf('<li>(%s - %s) %s', ['Page', 'Ajax'][$index], $item->get_disable_type() === Plugin_Disable_Config::ACTIVE_ON ? 'Active On' : 'Inactive On', esc_html($item->get_regexp()));
                }
            }
            $actions[] = $value . '</ul></em>';
        }
        return $actions;
    }

    /**
     * The 'option_active_plugins' filter can be fired multiple times.
     *
     * @param array $plugin_list
     *
     * @return array
     */
    public function disable_plugins_per_page(array $plugin_list): array
    {
        // Return the filtered list if we've already performed the filter operation.
        if (!is_null($this->_filtered_plugin_list)) {
            return $this->_filtered_plugin_list;
        }

        $plugin_removed = array();
        foreach ($plugin_list as $plugin) {
            if (isset($this->_disable_plugins[$plugin])) {
                $plugin_disable_container = $this->_disable_plugins[$plugin];
                if (wp_doing_ajax()) {
                    $plugin_disable_config = $plugin_disable_container->get_ajax();
                    $source = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : NULL;
                } else {
                    $plugin_disable_config = $plugin_disable_container->get_page();
                    $source = $_SERVER['REQUEST_URI'];
                }
                if (is_null($plugin_disable_config) || is_null($source)) {
                    continue;
                }
                if (preg_match('/' . $plugin_disable_config->get_regexp() . '/', $source, $matches) === ($plugin_disable_config->get_disable_type() === Plugin_Disable_Config::ACTIVE_ON ? 0 : 1)) {
                    $plugin_removed[] = $plugin;
                }
            }
        }
        if (count($plugin_removed)) {
            $plugin_list = array_diff($plugin_list, $plugin_removed);
        }
        $this->_filtered_plugin_list = $plugin_list;

        if ($this->_debug && !wp_doing_ajax()) {
            Util::debug($_SERVER['REQUEST_URI'], '$_SERVER[\'REQUEST_URI\']');
            Util::debug($this->_disable_plugins, 'Filter Parameters');
            Util::debug($plugin_removed, 'Disabled Plugins');
            Util::debug($this->_filtered_plugin_list, 'Active Plugins Post Filter Operation');
        }
        return $plugin_list;
    }

    /**
     * The filtered plugin list.
     *
     * @return array
     */
    public function get_filtered_plugin_list(): array
    {
        return $this->_filtered_plugin_list;
    }

}
