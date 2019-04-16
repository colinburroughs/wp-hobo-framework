<?php

/**
 * @wordpress-plugin
 * Plugin Name:   Hobo Plugin Framework - Html Helper.
 * Plugin URI:    https://www.hobo.co.uk
 * Description:   Static HTML helper methods.
 * Version:       0.0.0
 * Author:        Hobo Digital Ltd.
 * Author URI:    https://www.hobo.co.uk
 */

namespace Hobo\Framework;

class Html_Helper
{
    /**
     * @param      $array
     * @param null $selected
     * @param bool $use_key_as_value
     */
    public static function select_options_from_array(array $array, $selected = NULL, $use_key_as_value = TRUE): void
    {
        $output = '';
        foreach ($array as $key => $value) {
            $option_value = $use_key_as_value ? $key : $value;
            $output .= sprintf('<option value="%s"%s>%s', $option_value, $option_value == $selected ? ' selected' : '', $value);
        }
        echo $output;
    }

    /**
     * @param array  $array
     * @param string $name
     * @param array  $checked
     * @param bool   $use_key_as_value
     * @param string $separator
     */
    public static function checkbox_from_array(array $array, string $name, $checked = [], bool $use_key_as_value = FALSE, string $separator = '<br/>'): void
    {
        if (!is_array($checked)) {
            $checked = [$checked];
        }
        $output = '';
        $index = 0;
        foreach ($array as $key => $value) {
            $checkbox_value = $use_key_as_value ? $key : $value;
            $output .= sprintf('%s<input name="%s" type="checkbox" value="%s"%s>%s', $index > 0 ? $separator : '', $name, $checkbox_value, in_array($checkbox_value, $checked) ? ' checked' : '', $value);
            $index++;
        }
        echo $output;
    }

    /**
     * @param array  $array
     * @param string $name
     * @param        $checked
     * @param bool   $use_key_as_value
     * @param string $separator
     */
    public static function radio_from_array(array $array, string $name, $checked, bool $use_key_as_value = FALSE, string $separator = '&nbsp;'): void
    {
        $output = '';
        $index = 0;
        foreach ($array as $key => $value) {
            $radio_value = $use_key_as_value ? $key : $value;
            $output .= sprintf('%s<input name="%s" type="radio" value="%s"%s>%s', $index > 0 ? $separator : '', $name, $radio_value, $key === $checked ? ' checked' : '', $value);
            $index++;
        }
        echo $output;
    }

}
