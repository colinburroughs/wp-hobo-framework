<?php

/**
 * Plugin Name: Hobo Plugin Framework - Model View Controller.
 * Description: Abstract class for ModelViewController (MVC) operations.
 * Version: 1.0
 * Author: Hobo Digital Ltd.
 */

namespace Hobo\Framework;

define('MVC_ACTION_NAME', 'action');

abstract class MVC_Controller
{
    /** @var array Model */
    private $_model = array();

    /** @var object Plugin */
    private $_plugin;

    /** @var array view paths */
    private $_view_paths = array();

    function __construct(Plugin $plugin = NULL)
    {
        $this->_plugin = $plugin;
        $this->_view_paths = [
            $this->get_plugin()->get_plugin_path() . 'views/',
            HOBO_FRAMEWORK_ROOT_PATH . 'views/'
        ];
    }

    public function get_page(): string
    {
        return isset($_REQUEST['page']) ? sanitize_text_field($_REQUEST ['page']) : '';
    }

    public function enqueue_view_javascript(string $script, bool $in_footer = FALSE, string $handle = NULL): void
    {
        if (empty($handle)) {
            $handle = sanitize_file_name($script);
        }
        $path = $this->get_plugin()->get_plugin_path() . 'views/js/' . $script;
        $file = realpath($path);
        if (!file_exists($file)) {
            wp_die(sprintf('The javascript file <em>%s</em> does not exist.', $path));
        }
        wp_enqueue_script($handle, $this->get_plugin()->get_plugin_url() . 'views/js/' . $script, array(), filemtime($file), $in_footer);
    }

    function get_plugin(): Plugin
    {
        return $this->_plugin;
    }

    /* -- Dispatcher -- */

    public final function dispatch(): void
    {
        // Grab the action.
        $action = $this->get_action();

        // Construct the delegate method to handle the action.
        $delegate_method = 'mvc_action_' . $action;

        // Delegate to the action method if we can, otherwise show an error.
        if (method_exists($this, $delegate_method)) {
            call_user_func(array($this, $delegate_method));
        } else {
            wp_die(sprintf('Unable to locate delegate action method <em>%s</em> in class <em>%s</em>.', $delegate_method, get_called_class()));
        }
    }

    /* -- Actions -- */

    /**
     * Get the requested action and put it in the model
     */
    protected function get_action(): string
    {
        $action = $this->get(MVC_ACTION_NAME);
        if (empty ($action)) {
            if (isset($_REQUEST[MVC_ACTION_NAME])) {
                $action = $_REQUEST [MVC_ACTION_NAME];
            }
            if (empty ($action)) {
                $action = 'default';
            }
            $this->put(MVC_ACTION_NAME, $action);
        }
        return $action;
    }

    /* -- Model -- */

    final function get_model()
    {
        return $this->_model;
    }

    final function get(string $key, string $default = '')
    {
        return array_key_exists($key, $this->_model) ? $this->_model [$key] : $default;
    }

    final function put(string $key, $value)
    {
        if (array_key_exists($key, $this->_model)) {
            $existing_value = $this->_model [$key];
        } else {
            $existing_value = NULL;
        }
        $this->_model [$key] = $value;
        return $existing_value;
    }

    /* -- View -- */

    final function view(string $view_name, string $capability = NULL): void
    {
        if (!is_null($capability)) {
            if (!current_user_can($capability)) {
                $this->get_plugin()->capability_error_page();
                exit;
            }
        }

        $view_filename = $view_name;
        foreach ($this->_view_paths as $path) {
            $view_filename = $path . $view_name . '.php';
            $view_file = realpath($view_filename);
            if (file_exists($view_file)) {
                include_once $view_file;
                return;
            }
        }
        wp_die(sprintf('View <em>%s</em> does not exist.', $view_filename));
    }

    public function mvc_action_default(): void
    {
        wp_die(sprintf('Either <em>action</em> not specified, or you must override the default <em>%s</em> method in <em>%s</em>.', __FUNCTION__, get_called_class()));
    }

}
