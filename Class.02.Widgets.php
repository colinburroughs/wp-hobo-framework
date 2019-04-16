<?php

/**
 * @wordpress-plugin
 * Plugin Name:   Hobo Plugin Framework - Widgets.
 * Plugin URI:    https://www.hobo.co.uk
 * Description:   Abstract class for Widget operations.
 * Version:       0.0.0
 * Author:        Hobo Digital Ltd.
 * Author URI:    https://www.hobo.co.uk
 */

namespace Hobo\Framework;

abstract class Widgets
{
    function __construct()
    {
        add_action('widgets_init', array($this, 'init'));
    }

    public function init(): void
    {
        $this->add_widget($this->get_widget());
    }

    protected abstract function get_widget(): array;

    protected function add_widget(array $widgets): void
    {
        foreach ($widgets as $widget) {
            register_widget($widget);
        }
    }

}
