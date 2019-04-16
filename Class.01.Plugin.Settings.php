<?php

/**
 * @wordpress-plugin
 * Plugin Name:   Hobo Plugin Framework - Settings.
 * Plugin URI:    https://www.hobo.co.uk
 * Description:   Abstract class for handling plugin admin settings.
 * Version:       0.0.0
 * Author:        Hobo Digital Ltd.
 * Author URI:    https://www.hobo.co.uk
 */

namespace Hobo\Framework;

use DateTime;

abstract class SETTING_ERROR_TYPE
{
    const UPDATED = 'updated';
    const ERROR = 'error';
}

abstract class SETTING_NAME
{
    const MISSING_REQUIRED_SETTING = 'missing_required_settings';
}

abstract class Settings
{
    protected $settings;
    protected $validate_methods;

    private $_plugin;
    private $_page;
    private $_setting_detail_by_id;
    private $_media_script_added = FALSE;
    private $_script = '';
    private $_has_required = FALSE;
    private $_required_settings = [];
    private $_has_media = FALSE;
    private $_has_color_picker = FALSE;

    /**
     * Settings constructor.
     *
     * @param \Hobo\Framework\Plugin $plugin
     * @param bool                   $validate_methods
     */
    public function __construct(Plugin $plugin, bool $validate_methods = TRUE)
    {
        $this->_plugin = $plugin;
        $this->_page = $plugin->get_settings_slug();
        $this->validate_methods = $validate_methods;
    }

    /**
     * @return array
     */
    public function get_setting_detail_by_id(): array
    {
        return $this->_setting_detail_by_id;
    }

    /**
     * @param $input
     *
     * @return array
     */
    public function sanitize_validate_settings(array $input): array
    {
        $output = array();
        foreach ([$input, $_FILES] as $settings) {
            foreach ($settings as $key => $value) {
                $setting = $this->_setting_detail_by_id[$key];
                if (isset($setting['sanitize'])) {
                    $value = call_user_func($setting['sanitize'], $value);
                }
                $output[$key] = $value;

                $validate = TRUE;
                $required = isset($setting['required']) && $setting['required'] === TRUE;
                if ($required) {
                    $validate = $this->validate_required_field($key, $this->_plugin->get_setting($key, ''), $output[$key], isset($setting['validate_args']) ? $setting['validate_args'] : []);
                }

                if ($validate) {
                    $validate = $setting['validate'];
                    // Be careful when using the $settings array in your validation, as this contains the unvalidated submitted values.
                    // You'll generally not need to use it. It is only ever required in special situations.
                    $output[$key] = call_user_func($validate, $key, $this->_plugin->get_setting($key, ''), $output[$key], isset($setting['validate_args']) ? $setting['validate_args'] : [], $settings);
                }
            }
        }

        // Required fields?
        if ($this->_has_required === TRUE) {
            $missing_require_fields = FALSE;
            foreach (array_keys($this->_required_settings) as $required_key) {
                if (empty($output[$required_key])) {
                    $missing_require_fields = TRUE;
                    break;
                }
            }
            // Add the missing required fields marker.
            $output[SETTING_NAME::MISSING_REQUIRED_SETTING] = $missing_require_fields;
        }

        return $output;
    }

    /**
     * Settings page header content, override and echo directly.
     */
    public function header(): void
    {
    }

    /**
     * Settings page footer content, override and echo directly.
     */
    public function footer(): void
    {
    }

    /**
     *  Prepare the settings.
     */
    public function prepare_settings(): void
    {
        // We must have submitted the settings page if the nonce validates, therefore register our settings.
        $register_settings = isset($_POST['_wpnonce']) ? wp_verify_nonce($_POST['_wpnonce'], $this->get_plugin()->get_option_group() . '-options') : FALSE;

        foreach ($this->settings as $section) {
            add_settings_section($section['id'], $section['title'], $section['callback'], $this->_page);
            foreach ($section['settings'] as $key => $setting) {
                // Ignore inactive settings.  All settings are active by default.
                if (isset($setting['is_active']) && !boolval($setting['is_active'])) {
                    continue;
                }
                $setting_render_method = $setting['render'];
                $this->setting_method_exists('Render', $setting_render_method, $key);

                switch ($setting_render_method[1]) {
                    case 'render_select_media':
                        $this->_has_media = TRUE;
                        break;
                    case 'render_colorpicker':
                        $this->_has_color_picker = TRUE;
                        break;
                }

                $clazz = NULL;
                if (isset($setting['class'])) {
                    $clazz = $setting['class'];
                } else {
                    if (isset($section['class'])) {
                        $clazz = $section['class'];
                    }
                }
                $required = isset($setting['required']) && $setting['required'] === TRUE;
                if ($required) {
                    $this->_required_settings[$key] = trim(strip_tags($setting['title']));
                    $this->_has_required = TRUE;
                }
                add_settings_field(
                    $key,
                    sprintf('<span%s>%s</span>', $required ? ' class="setting-required"' : '', $setting['title']),
                    $setting_render_method,
                    $this->_page,
                    $section['id'],
                    array(
                        'render_args' => isset($setting['render_args']) ? $setting['render_args'] : NULL,
                        'help' => isset($setting['help']) ? $setting['help'] : NULL,
                        'required' => $required,
                        'class' => $clazz,
                        $key,
                        $this->_plugin->get_setting($key, '')
                    )
                );

                if ($register_settings) {
                    if (isset($setting['sanitize'])) {
                        $this->setting_method_exists('Sanitize', $setting['sanitize'], $key);
                    }
                    if (isset($setting['validate'])) {
                        $this->setting_method_exists('Validate', $setting['validate'], $key);
                    }

                    $setting['section'] = $section['title'];
                    $this->_setting_detail_by_id[$key] = $setting;
                }
            }
        }

        if (!empty($section)) {
            // Add script
            add_settings_field('script', '', array($this, 'render_script'), $this->_page, $section['id'], array('class' => 'hidden'));
        }

        if ($register_settings) {
            register_setting($this->_plugin->get_option_group(), $this->_plugin->get_option_group(), array($this, 'sanitize_validate_settings'));
        }
        $this->_required_settings;
    }

    public function get_settings()
    {
        return $this->settings;
    }

    public function get_plugin(): Plugin
    {
        return $this->_plugin;
    }

    public function get_has_required(): bool
    {
        return $this->_has_required;
    }

    public function get_required_settings(): array
    {
        return $this->_required_settings;
    }

    public function set_has_media(bool $has_media): void
    {
        $this->_has_media = $has_media;
    }

    public function get_has_media(): bool
    {
        return $this->_has_media;
    }

    public function set_has_color_picker(bool $color_picker): void
    {
        $this->_has_color_picker = $color_picker;
    }

    public function get_has_color_picker(): bool
    {
        return $this->_has_color_picker;
    }

    public function report_setting_changed(string $id, $existing, $value): void
    {
        if ($existing !== $value) {
            $setting_detail = $this->_setting_detail_by_id[$id];
            $this->add_settings_update($id, NULL, sprintf('Updated - %s - %s.', wp_strip_all_tags($setting_detail['section']), $setting_detail['title']));
        }
    }

    private function setting_method_exists(string $type, $method, string $key): void
    {
        if ($this->validate_methods && !is_callable($method)) {
            wp_die(sprintf('%s method <em>%s</em> does not exist in class <em>%s</em>. Error in setting <em>%s</em>.', $type, is_array($method) ? $method[1] : $method, get_called_class(), $key));
        }
    }

    public function add_settings_error(string $setting, $code, string $message): void
    {
        add_settings_error($setting, $code, $message, SETTING_ERROR_TYPE::ERROR);
    }

    public function add_settings_update(string $setting, $code, string $message): void
    {
        add_settings_error($setting, $code, $message, SETTING_ERROR_TYPE::UPDATED);
    }

    public function upload_types_defined(array $existing_mimes = array()): array
    {
        return $existing_mimes;
    }

    public function render_script(): void
    {
        if (!empty($this->_script)) {
            echo '<script>' . PHP_EOL;
            echo 'jQuery(function($) {' . PHP_EOL;
            echo '$(document).ready(function() {' . PHP_EOL;
            echo $this->_script . PHP_EOL;
            echo '})' . PHP_EOL;
            echo '})' . PHP_EOL;
            echo '</script>';
            $this->_script = '';
        }
    }

    // --------------------------------------------------------------------------------

    public function sanitize_no_script(string $value): string
    {
        $value = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $value);
        return $value;
    }

    public function sanitize_alphanumeric_dash_field(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9-]/', '', $value);
    }

    public function sanitize_numeric_field(string $value): string
    {
        return preg_replace('/[\D\.\-]/', '', $value);
    }

    public function sanitize_color_field(string $value): string
    {
        return preg_replace('/[^#0-9A-Fa-frgb\.,\(\)]/', '', $value);
    }

    public function sanitize_date_field(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9-:]/', '', $value);
    }

    // --------------------------------------------------------------------------------

    public function render_empty($args): void
    {
        $this->render_help($args);
    }

    public function render_file_field(array $args): void
    {
        $file_field_name = 'document_file';
        $not_defined_error = '<strong>PLEASE UPLOAD</strong>';
        $not_found_title = '';
        if (isset($args['render_args'])) {
            extract($args['render_args']);
        }

        if (empty($args[1])) {
            echo $this->render_true_false_icon(FALSE) . ' ' . $not_defined_error . '<br/>';
        } else {
            $realPath = realpath($args[1]);
            if (file_exists($realPath)) {
                echo $this->render_true_false_icon(TRUE) . ' <em>' . $realPath . '</em><br/>';
            } else {
                echo $this->render_true_false_icon(FALSE) . ' <strong>Unable to locate ' . $not_found_title . ' at [' . $args[1] . ']. Try uploading it again.</strong><br/>';
            }
        }

        echo '<input type="file" name="' . $file_field_name . '">';
        $this->render_help($args);
    }

    public function render_text(array $args): void
    {
        $value = '';
        if (isset($args['render_args'])) {
            extract($args['render_args']);
        }
        echo $value;
        $this->render_help($args);
    }

    public function render_standard_text_field(array $args): void
    {
        $type = 'text';
        $size = 120;
        $class = '';
        if (isset($args['render_args'])) {
            extract($args['render_args']);
        }
        echo '<input type="' . $type . '" autocomplete="off" name="' . $this->_plugin->get_option_group() . '[' . $args[0] . ']" class="' . $class . '" size="' . $size . '" value="' . $args[1] . '">';
        $this->render_help($args);
    }

    public function render_standard_text_area(array $args): void
    {
        $rows = 6;
        $cols = 120;
        if (isset($args['render_args'])) {
            extract($args['render_args']);
            if (isset($delimiter)) {
                $args[1] = str_replace($delimiter, PHP_EOL, $args[1]);
            }
        }
        echo '<textarea autocomplete="off" name="' . $this->_plugin->get_option_group() . '[' . $args[0] . ']" rows="' . $rows . '" cols="' . $cols . '">' . $args[1] . '</textarea>';
        $this->render_help($args);
    }

    public function render_file_path_field(array $args): void
    {
        $method = 'is_dir';
        if (isset($args['render_args'])) {
            extract($args['render_args']);
        }
        $realpath = !empty($args[1]) ? realpath($args[1]) : '';
        if (call_user_func($method, $realpath)) {
            $this->render_true_false_icon(TRUE);
        } else {
            $this->render_true_false_icon(FALSE);
            echo '<strong> Unable to locate path ' . $args[1] . '</strong>';
        }
        $this->render_standard_text_field($args);
    }

    public function render_help(array $args): void
    {
        if (!isset($args['help'])) {
            return;
        }
        $help = $args['help'];
        preg_match_all('/{(.*?)}/', $help, $matches);
        if (count($matches[1]) > 0) {
            foreach (array_unique($matches[1]) as $setting) {
                $value = $this->get_plugin()->get_setting($setting);
                $help = str_replace('{' . $setting . '}', $value, $help);
            }
        }
        if (is_callable($help)) {
            call_user_func($help, $args);
        } else {
            echo $help;
        }
    }

    public function render_yes_no(array $args): void
    {
        $args['render_args'] = ['array' => array(0 => 'No', 1 => 'Yes')];
        $this->render_true_false_icon(boolval($args[1]));
        $this->render_select($args);
    }

    public function render_switch(array $args): void
    {
        $on = 'Yes';
        $off = 'No';
        if (isset($args['render_args'])) {
            extract($args['render_args']);
        }
        $flag = boolval($args[1]);
        $field = $this->_plugin->get_option_group() . '[' . $args[0] . ']';
        echo '<div class="hobo-true-false">';
        echo '<input type="hidden" name="' . $field . '" value="0" />';
        echo '<label>';
        echo '<input type="checkbox" id="' . $field . '" name="' . $field . '" value="1" class="hobo-switch-input settings-reset-marker" autocomplete="off"' . ($flag ? ' checked' : '') . '>';
        echo '<div class="hobo-switch' . ($flag ? ' -on' : '') . '">';
        echo '<span class="hobo-switch-on">' . $on . '</span>';
        echo '<span class="hobo-switch-off">' . $off . '</span>';
        echo '<div class="hobo-switch-slider"></div>';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        $this->render_help($args);
    }

    public function render_select(array $args): void
    {
        if (!isset($args['render_args']['array'])) {
            $args['render_args']['array'] = [];
        }
        if (!isset($args['render_args']['use_key_as_value'])) {
            $args['render_args']['use_key_as_value'] = TRUE;
        }
        if (isset($args['render_args'])) {
            extract($args['render_args']);
        }
        if (!isset($array)) {
            $array = [];
        }
        if (!isset($use_key_as_value)) {
            $use_key_as_value = FALSE;
        }
        echo '<select name="' . $this->_plugin->get_option_group() . '[' . $args[0] . ']">';
        Html_Helper::select_options_from_array($array, $args[1], $use_key_as_value);
        echo '</select>';

        $this->render_help($args);
    }

    public function render_true_false_icon(bool $true): void
    {
        if ($true) {
            echo '<span style="font-size:20px;color:green;"><i class="dashicons dashicons-yes"></i></span>';
        } else {
            echo '<span style="font-size:20px;color:red;"><i class="dashicons dashicons-no"></i></span>';
        }
    }

    public function render_wp_editor(array $args): void
    {
        $settings = ['wpautop' => FALSE, 'media_buttons' => FALSE, 'textarea_name' => $this->_plugin->get_option_group() . '[' . $args[0] . ']'];
//        $settings['tinymce'] = array(
//            'init_instance_callback' => 'function(editor) {
//                editor.on("keyup", function (e) {
//                    console.log(e, e.keyCode);
//                    // Ignore arrow and page keyboard key codes]
//                    if ([33, 34, 37, 38, 39, 40].indexOf(e.keyCode) === -1) {
//                        console.log("X");
//                        $.fn.edited(true);
//                    }
//                });
//            }'
//        );
        if (isset($args['render_args'])) {
            $settings = array_merge($settings, $args['render_args']);
        }
        wp_editor(stripslashes($args[1]), $args[0], $settings);
        $this->render_help($args);
    }

    public function render_button(array $args): void
    {
        $text = 'Button Text';
        $class = 'button button-primary';
        if (isset($args['render_args'])) {
            extract($args['render_args']);
        }
        echo '<button id="' . $this->_plugin->get_option_group() . '[' . $args[0] . ']" class="' . $class . '">' . $text . '</button>';
        $this->render_help($args);
    }

    public function render_colorpicker(array $args)
    {
        $class = '';
        if (!isset($args['render_args']['class'])) {
            $args['render_args']['class'] = 'settings-reset-marker color-picker';
        } else {
            $args['render_args']['class'] = $args['render_args']['class'] . ' settings-reset-marker color-picker';
        }
        extract($args['render_args']);

        echo '<input type="text" ' . (isset($alpha) ? 'data-alpha="' . $alpha . '"' : '') . (isset($default) ? 'data-default-color="' . $default . '"' : '') . ' autocomplete="off" name="' . $this->_plugin->get_option_group() . '[' . $args[0] . ']" class="' . $class . '" value="' . $args[1] . '">';
        $this->render_help($args);

    }

    public function render_secret(array $args): void
    {
        $text = '<strong><em>*** DEFINED IN SECURE CONFIGURATION ***</em></strong>';
        if (isset($args['render_args'])) {
            extract($args['render_args']);
        }
        echo $text;
        $this->render_help($args);
    }

    public function render_select_media(array $args): void
    {
        $default = '//via.placeholder.com/350x150';
        $add_text = 'Add Media';
        $remove_text = '&times;';
        if (isset($args['render_args'])) {
            extract($args['render_args']);
        }
        if (!empty($args[1])) {
            $image_attributes = wp_get_attachment_image_src($args[1]);
            $src = $image_attributes[0];
        } else {
            $src = $default;
        }
        echo '<div' . (isset($class) ? ' class="' . $class . '"' : '') . '>';
        echo '<img id="' . $this->_plugin->get_option_group() . '[' . $args[0] . ']" class="settings-reset-marker" data-default="' . $default . '" data-src="' . $src . '" src="' . $src . '" />';
        echo '<div>';
        echo '<input type="hidden" name="' . $this->_plugin->get_option_group() . '[' . $args[0] . ']" value="' . $args[1] . '" />';
        echo '<button id="' . $args[0] . '_add"' . (isset($add_class) ? ' class="' . $add_class . '"' : '') . '>' . $add_text . '</button>';
        echo '<button id="' . $args[0] . '_remove"' . (isset($remove_class) ? ' class="' . $remove_class . '"' : '') . '>' . $remove_text . '</button>';
        echo '</div>';
        echo '</div>';
        $this->render_help($args);

        if ($this->_media_script_added === FALSE) {
            $this->_script .= <<<END
$.fn.add_media_click = function () { 
    var send_attachment_bkp = wp.media.editor.send.attachment;
    var button = $(this);
    wp.media.editor.send.attachment = function(props, attachment) {
        $(button).parent().prev().attr('src', attachment.url);
        $(button).prev().val(attachment.id).trigger('change');
        wp.media.editor.send.attachment = send_attachment_bkp;
    };
    wp.media.editor.open(button);
    return false;
};
$.fn.remove_media_click = function () {
    var button = $(this);
    var src = button.parent().prev().attr('data-default');
    button.parent().prev().attr('src', src);
    button.prev().prev().val('').trigger('change');
    return false;
};

END;
            $this->_media_script_added = TRUE;
        }
        $this->_script .= '$("#' . $args[0] . '_add").click($.fn.add_media_click);' . PHP_EOL;
        $this->_script .= '$("#' . $args[0] . '_remove").click($.fn.remove_media_click);' . PHP_EOL;
    }

    /**
     * @param array $args
     *                   $args[0] : setting id
     *                   $args[1] : setting value
     *                   $args['render_args']['class'] : HTML class value
     *                   $args['render_args']['js_format'] : jQuery datepicker dateFormat
     *                   $args['render_args']['php_format'] : PHP DateTime format
     *                   $args['render_args']['change_month'] : jQuery changeMonth
     *                   $args['render_args']['change_year'] : jQuery changeYear
     */
    public function render_datepicker(array $args): void
    {
        $js_format = 'yy-mm-dd';
        $php_format = 'Y-m-d';
        $change_month = FALSE;
        $change_year = FALSE;
        if (isset($args['render_args'])) {
            extract($args['render_args']);
        }
        if (is_object($args[1])) {
            $args[1] = $args[1]->format($php_format);
        }
        $name = $this->_plugin->get_option_group() . '[' . $args[0] . ']';
        echo sprintf('<input type="text" class="datepicker %s" id="%s" name="%s" value="%s" format="%s" changeMonth="%s" changeYear="%s">', isset($args['class']) ? $args['class'] : '', $name, $name, $args[1], $js_format, $change_month, $change_year);
        $this->render_help($args);
    }

    /**
     * @param array $args
     *                   $args[0] : setting id
     *                   $args[1] : setting value
     *                   $args['render_args']['class'] : HTML class value, default default-slider
     *                   $args['render_args']['step'] : jQuery slider step, default 1
     *                   $args['render_args']['min'] : jQuery slider min value, default 0
     *                   $args['render_args']['max'] : jQuery slider max value, default 100
     */
    public function render_slider(array $args): void
    {
        $initial = $args[1];
        $step = 1;
        $min = 0;
        $max = 100;
        $class = 'default-slider';
        if (isset($args['render_args'])) {
            extract($args['render_args']);
        }
        $name = $this->_plugin->get_option_group() . '[' . $args[0] . ']';
        echo sprintf('<span id="%s" class="%s slider settings-reset-marker" initial="%d" step="%d" min="%d" max="%d"><span class="custom-handle ui-slider-handle"></span></span>', $name, $class, $initial, $step, $min, $max);
        echo '<input type="hidden" name="' . $name . '" value="' . $initial . '" />';
        $this->render_help($args);
    }

    // --------------------------------------------------------------------------------

    private function validate_required_field(string $id, $existing, $value, array $args): bool
    {
        if (!empty($value)) {
            return TRUE;
        }
        $setting_detail = $this->_setting_detail_by_id[$id];
        $this->add_settings_error('report_' . $id, NULL, sprintf('Error - %s - %s - <em >%s </em > is a required setting.', wp_strip_all_tags($setting_detail['section']), $setting_detail['title'], esc_html($value)));
        return FALSE;
    }

    public function validate_simple(string $id, $existing, $value, array $args, array $values)
    {
        $this->report_setting_changed($id, $existing, $value);
        return $value;
    }

    public function validate_true_false_field(string $id, $existing, $value, array $args, array $values): bool
    {
        $value = intval(preg_replace(' / \D / ', '', $value), 10);
        if ($value < 0 || $value > 1) {
            $setting_detail = $this->_setting_detail_by_id[$id];
            $this->add_settings_error('report_' . $id, NULL, sprintf('Error - %s - %s - <em >%s </em > is an invalid value.', wp_strip_all_tags($setting_detail['section']), $setting_detail['title'], esc_html($value)));
            $value = FALSE;
        } else {
            $this->report_setting_changed($id, $existing, boolval($value));
        }
        return boolval($value);
    }

    public function validate_standard_text_field(string $id, $existing, $value, array $args, array $values)
    {
        return $this->validate_simple($id, $existing, $value, $args, $values);
    }

    public function validate_standard_numeric_field(string $id, $existing, $value, array $args, array $values)
    {
        $error = FALSE;
        if (!is_null($args)) {
            extract($args);
            if (isset($type)) {
                switch ($type) {
                    case 'INT':
                        $value = intval($value);
                        break;
                    case 'FLOAT':
                        $value = floatval($value);
                        break;
                }
            }
            if (isset($min)) {
                if ($value < $min) {
                    $setting_detail = $this->_setting_detail_by_id[$id];
                    $this->add_settings_error('report_' . $id, NULL, sprintf('Error - %s - %s - <em >%s </em > is less than the minimum value of %s.', wp_strip_all_tags($setting_detail['section']), $setting_detail['title'], $value, $min));
                    $error = TRUE;
                }
            }
            if (isset($max)) {
                if ($value > $max) {
                    $setting_detail = $this->_setting_detail_by_id[$id];
                    $this->add_settings_error('report_' . $id, NULL, sprintf('Error - %s - %s - <em >%s </em > is greater than the maximum value of %s.', wp_strip_all_tags($setting_detail['section']), $setting_detail['title'], $value, $max));
                    $error = TRUE;
                }
            }
        }
        if (!$error) {
            $this->report_setting_changed($id, $existing, $value);
        } else {
            $value = $existing;
        }
        return $value;
    }

    public function validate_filesystem_path(string $id, $existing, $value, array $args, array $values)
    {
        $method = 'file_exists';
        if (!is_null($args)) {
            extract($args);
        }
        $realpath = !empty($value) ? realpath($value) : '';
        if (!call_user_func($method, $realpath)) {
            $setting_detail = $this->_setting_detail_by_id[$id];
            $this->add_settings_error('report_' . $id, NULL, sprintf('Error - %s - %s - <em >%s </em > does not exist on the file system.', wp_strip_all_tags($setting_detail['section']), $setting_detail['title'], esc_html($value)));
            $value = FALSE;
        } else {
            $value = $realpath;
            $this->report_setting_changed($id, $existing, $value);
        }
        return $value;
    }

    public function validate_domain(string $id, $existing, $value, array $args, array $values)
    {
        if (gethostbyname($value) === $value) {
            $setting_detail = $this->_setting_detail_by_id[$id];
            $this->add_settings_error('report_' . $id, NULL, sprintf('Error - %s - %s - <em >%s </em > is an invalid domain name.', wp_strip_all_tags($setting_detail['section']), $setting_detail['title'], esc_html($value)));
            $value = FALSE;
        } else {
            $this->report_setting_changed($id, $existing, $value);
        }
        return $value;
    }

    public function validate_color(string $id, $existing, $value, array $args, array $values)
    {
        $color = $existing;
        preg_match_all('/^#(?:[0-9a-fA-F]{3}){1,2}$/m', $value, $matches, PREG_SET_ORDER, 0);
        $valid = count($matches) > 0;
        if (!$valid) {
            preg_match_all('/^rgba?\(\d+,\s*\d+,\s*\d+(?:,\s*(\d+(?:\.\d+)?))?\)$/m', $value, $matches, PREG_SET_ORDER, 0);
            $valid = count($matches) > 0;
        }
        if (!$valid) {
            $setting_detail = $this->_setting_detail_by_id[$id];
            $this->add_settings_error('report_' . $id, NULL, sprintf('Error - %s - %s - <em >%s</em > invalid colour.', wp_strip_all_tags($setting_detail['section']), $setting_detail['title'], esc_html($value)));
        } else {
            $color = $matches[0][0];
            $this->report_setting_changed($id, $existing, $color);
        }
        return $color;
    }


    public function validate_date(string $id, $existing, $value, array $args, array $values)
    {
        $date = DateTime::createFromFormat($args['php_format'], $value);
        if ($date === FALSE) {
            $setting_detail = $this->_setting_detail_by_id[$id];
            $this->add_settings_error('report_' . $id, NULL, sprintf('Error - %s - %s - <em >%s</em > invalid date.', wp_strip_all_tags($setting_detail['section']), $setting_detail['title'], esc_html($value)));
            $empty_format = isset($args['empty_format']) ? $args['empty_format'] : 'yyyy-mm-dd';
            $date = !empty($value) ? $value : $empty_format;
        } else {
            $this->report_setting_changed($id, $existing, $date);
        }
        return $date;
    }

    public function validate_email(string $id, $existing, $value, array $args, array $values)
    {
        $delimiter = ',';
        if (!is_null($args)) {
            extract($args);
        }
        $valid = [];
        $invalid = [];
        $emails = explode($delimiter, strtolower($value));
        foreach ($emails as $email) {
            $sanitized_email = sanitize_email($email);
            if (!empty($sanitized_email)) {
                list ($prefix, $domain) = explode('@', $sanitized_email);
                if (function_exists("getmxrr") && !getmxrr($domain, $mxhosts)) {
                    $invalid[] = $email;
                } else {
                    $valid[] = $sanitized_email;
                }
            } else {
                $setting_detail = $this->_setting_detail_by_id[$id];
                if (isset($setting['required']) && $setting_detail['required']) {
                    $invalid[] = $email;
                }
            }
        }
        $invalid_count = count($invalid);
        if ($invalid_count > 0) {
            $value = join(', ', $invalid);
            $setting_detail = $this->_setting_detail_by_id[$id];
            $this->add_settings_error('report_' . $id, NULL, sprintf('Error - %s - %s - <em >%s </em > %s invalid email %s.', wp_strip_all_tags($setting_detail['section']), $setting_detail['title'], esc_html($value), $invalid_count == 1 ? 'is an' : 'are', $invalid_count == 1 ? 'address' : 'addresses'));
        }
        $value = join(',', $valid);
        $this->report_setting_changed($id, $existing, $value);
        return $value;
    }

    public function validate_file_upload(string $id, $existing, $value, array $args, array $values)
    {
        add_filter('upload_mimes', array(
            $this,
            'upload_types_defined'
        ));

        $allowed_extensions = [];
        if (isset($args['allowed_extensions'])) {
            $allowed_extensions = is_array($args['allowed_extensions']) ? $args['allowed_extensions'] : array($args['allowed_extensions']);
        }

        $upload_dir_filter_function = NULL;
        if (isset($args['upload_dir_filter_function'])) {
            $upload_dir_filter_function = array($this, $args['upload_dir_filter_function']);
        }

        $optional = TRUE;
        if (isset($args['optional'])) {
            $optional = boolval($args['optional']);
        }

        $rename = TRUE;
        if (isset($args['rename'])) {
            $rename = boolval($args['rename']);
        }

        $error = FALSE;
        $helper = new Upload_Helper();
        $upload_result = $helper->upload($this->get_plugin()->get_option_group() . '-options', $id, $allowed_extensions, $upload_dir_filter_function, $optional);

        if (!$upload_result['success']) {
            if (!is_null($upload_result ['error'])) {
                $setting_detail = $this->_setting_detail_by_id[$id];
                $this->add_settings_error('report_' . $id, NULL, sprintf('Error - %s - %s - <em >%s </em > ', wp_strip_all_tags($setting_detail['section']), $setting_detail['title'], $upload_result['error']));
                $error = TRUE;
            }
        } else {
            if ($rename) {
                // Ensure we always overwrite an existing file with the most recently uploaded file.
                $upload_file_name = $upload_result ['file'] ['name'];
                $persist_file_name = dirname($upload_result ['file'] ['file']) . DIRECTORY_SEPARATOR . $upload_file_name;
                if (!rename($upload_result ['file'] ['file'], $persist_file_name)) {
                    $this->add_settings_error('report_rename_' . $id, NULL, 'Failed to rename ' . esc_html($upload_result ['file'] ['file']) . ' to ' . esc_html($persist_file_name));
                }
            } else {
                $persist_file_name = $upload_result ['file'] ['file'];
            }
            $value = $persist_file_name;
        }

        if (!$error && !is_null($upload_result ['file'] ['file'])) {
            $this->report_setting_changed($id, $existing, $value);
        } else {
            $value = $existing;
        }
        return $value;
    }

}
