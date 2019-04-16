<?php

/**
 * @wordpress-plugin
 * Plugin Name:   Hobo Plugin Framework - Ajax.
 * Plugin URI:    https://www.hobo.co.uk
 * Description:   Abstract class to set up and handle AJAX requests.
 * Version:       0.0.0
 * Author:        Hobo Digital Ltd.
 * Author URI:    https://www.hobo.co.uk
 */

namespace Hobo\Framework;

abstract class AJAX_PRIVILEGE_TYPE
{
    const FALSE = 0;
    const TRUE = 1;
    const TRUE_FALSE = 2;
}

abstract class Ajax extends Request_Cache
{
    private $_validate_methods = TRUE;

    public function __construct()
    {
        $this->add_action($this->get_action());
    }

    protected abstract function get_action(): array;

    /**
     * @param $ajax_action
     */
    private function add_action($ajax_action): void
    {
        if (!empty($ajax_action)) {
            foreach ($ajax_action as $action => $privilege) {
                $method = 'ajax_' . $action;
                if ($privilege === AJAX_PRIVILEGE_TYPE::TRUE || $privilege === AJAX_PRIVILEGE_TYPE::TRUE_FALSE) {
                    $tag = 'wp_ajax_' . $action;
                    $callable = array($this, $method);
                    if ($this->_validate_methods && !is_callable($callable)) {
                        wp_die(sprintf('Method <em>%s</em> does not exist in class <em>%s</em>.', $method, get_called_class()));
                    }
                    add_action($tag, $callable);
                }
                if ($privilege === AJAX_PRIVILEGE_TYPE::FALSE || $privilege === AJAX_PRIVILEGE_TYPE::TRUE_FALSE) {
                    $tag = 'wp_ajax_nopriv_' . $action;
                    $callable = array($this, $method);
                    if ($this->_validate_methods && !is_callable($callable)) {
                        wp_die(sprintf('Method <em>%s</em> does not exist in class <em>%s</em>.', $method, get_called_class()));
                    }
                    add_action($tag, $callable);
                }
            }
        }
    }

    /**
     * @param string $error
     */
    protected function ajax_failed(string $error = 'Invalid params'): void
    {
        $return = [];
        $return['success'] = FALSE;
        $return['ajax'] = wp_doing_ajax();
        $return['error'] = $error;
        wp_send_json($return, 200);
    }

    /**
     * @return bool
     */
    public function is_validate_methods(): bool
    {
        return $this->_validate_methods;
    }

    /**
     * @param bool $validate_methods
     */
    public function set_validate_methods(bool $validate_methods): void
    {
        $this->_validate_methods = $validate_methods;
    }

}
