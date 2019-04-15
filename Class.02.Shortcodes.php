<?php

/**
 * Plugin Name: Hobo Plugin Framework - Shortcodes.
 * Description: Abstract class for Shortocde operations.
 * Version: 1.0
 * Author: Hobo Digital Ltd.
 */

namespace Hobo\Framework;

abstract class Shortcodes extends Request_Cache
{
    private $_validate_methods = TRUE;

    function __construct()
    {
        $this->add_shortcode($this->get_shortcode());
    }

    protected abstract function get_shortcode(): array;

    protected function add_shortcode(array $shortcodes): void
    {
        foreach ($shortcodes as $shortcode) {
            $method = sprintf('shortcode_%s', $shortcode);
            $callable = array($this, $method);
            if ($this->_validate_methods && is_callable($callable)) {
                add_shortcode($shortcode, $callable);
            } else {
                wp_die(sprintf('Method <em>%s</em> does not exist in class <em>%s</em>.', $method, get_called_class()));
            }
        }
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
