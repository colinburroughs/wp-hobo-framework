<?php

/**
 * @wordpress-plugin
 * Plugin Name:   Hobo Plugin Framework - Model.
 * Plugin URI:    https://www.hobo.co.uk
 * Description:   Abstract class for Model operations.
 * Version:       0.0.0
 * Author:        Hobo Digital Ltd.
 * Author URI:    https://www.hobo.co.uk
 */

namespace Hobo\Framework;

abstract class Model
{
    // The state as loaded from the database.
    private const MODEL = 'model';

    // The state before validation.
    private const ORIGINAL = 'original';

    // The state after validation.
    private const VALIDATED = 'validated';

    // The state errors.
    private const ERROR = 'error';

    // The format for database updates, defined by the validated model.
    private const FORMAT = 'format';

    private $_queries = NULL;
    private $_values = NULL;
    protected $_nice_name = __CLASS__;
    protected $_table = NULL;
    protected $_key = 'id';
    protected $_schema = array();
    protected $_custom_validation = array();
    private $_has_text_field = FALSE;

    public function __construct()
    {
        $this->_queries = Singleton::get_instance('Queries');
        $this->_values[self::MODEL] = [];
        $this->_values[self::ORIGINAL] = [];
        $this->_values[self::VALIDATED] = [];
        $this->_values[self::ERROR] = [];
        $this->_values[self::FORMAT] = [];
    }

    public function load($id, $key = NULL)
    {
        if (is_null($key)) {
            $key = $this->get_key();
        }
        $result = $this->_queries->get_data_from_table_by_key_and_value($this->get_table(), $key, $id);
        if ($result['success'] && $result['count'] == 1) {
            // Validate the model out of the database.
            $this->_values[self::MODEL] = $result['result'][0];
            $this->validate($result['result'][0]);
        }
        return $this;
    }

    public function new(array $params): void
    {
        foreach ($this->_schema as $field) {
            // Ignore display separator
            if (is_null($field)) {
                continue;
            }
            if (!array_key_exists($field['name'], $params)) {
                $params[$field['name']] = $field['default'];
            }
        }
        $this->validate($params);
    }

    public function save(): array
    {
        if (empty($this->_values[self::VALIDATED])) {
            return ['success' => FALSE, 'error' => ''];
        }
        if ($this->has_errors()) {
            return ['success' => FALSE, 'error' => join(', ', $this->get_values_error())];
        }
        $values = $this->get_values_validated();
        $format = $this->get_values_format();

        // Handle the created field.
        $index = array_search('created', array_keys($values));
        if ($index !== FALSE) {
            unset($values['created']);
            unset($format[$index]);
        }
        // Handle the modified field.
        $index = array_search('modified', array_keys($values));
        if ($index !== FALSE) {
            unset($values['modified']);
            unset($format[$index]);
        }

        if ($values[$this->get_key()] > 0) {
            $sql = $this->build_update_query($values, $format);
            $result = $this->_queries->query($sql);
            if ($result['success'] === TRUE) {
                if ($result['rows_affected'] === 0) {
                    $result['success'] = FALSE;
                    $result['error'] = 'Update failed to affect any rows in the database. ' . (isset($result['query']) ? '<em>' . $result['query'] . '</em>' : '');
                }
            }
        } else {
            $index = array_search('id', array_keys($values));
            if ($index !== FALSE) {
                unset($values['id']);
                unset($format[$index]);
            }
            $sql = $this->build_insert_query($values, $format);
            $result = $this->_queries->query($sql);
        }
        return $result;
    }

    private function build_update_query(array $values, array $format): string
    {
        $fields = [];
        foreach ($values as $key => $value) {
            $fmt = $format[$key];
            if (is_null($value)) {
                $fields[] = "`$key` = NULL";
                continue;
            }
            if ($fmt !== NULL) {
                if ($fmt === '%s') {
                    $value = '"' . sprintf($fmt, addslashes($value)) . '"';
                } else {
                    $value = sprintf($fmt, $value);
                }
            }
            $fields[] = "`$key` = " . $value;
        }
        return 'UPDATE `' . $this->get_table() . '` SET ' . join(', ', $fields) . ' WHERE ' . $this->get_key() . ' = ' . $values[$this->get_key()];
    }

    private function build_insert_query(array $values, array $format): string
    {
        $fields = [];
        foreach ($values as $key => $value) {
            $fmt = $format[$key];
            if (is_null($value)) {
                $fields[] = 'NULL';
                continue;
            }
            if (!is_null($fmt)) {
                if ($fmt === '%s') {
                    $value = '"' . sprintf($fmt, addslashes($value)) . '"';
                } else {
                    $value = sprintf($fmt, $value);
                }
            }
            $fields[] = $value;
        }
        return 'INSERT INTO `' . $this->get_table() . '` (' . join(', ', array_keys($values)) . ') VALUES (' . join(', ', $fields) . ')';
    }

    public function validate(array $params): void
    {
        $this->_values[self::ORIGINAL] = $params;
        $this->_values[self::VALIDATED] = [];
        $this->_values[self::ERROR] = [];
        $this->_values[self::FORMAT] = [];

        foreach ($this->_schema as $field) {
            // Ignore display separator
            if (is_null($field)) {
                continue;
            }

            // Is the field read only?
            $readonly = isset($field['readonly']) && $field['readonly'] === TRUE;
            if (isset($field['edit_capability'])) {
                if (!current_user_can($field['edit_capability'])) {
                    $readonly = TRUE;
                }
            }

            // Does the value exist?
            if ($readonly && isset($this->_values[self::MODEL][$field['name']])) {
                $exists = TRUE;
                $value = $this->_values[self::MODEL][$field['name']];
            } else {
                $exists = array_key_exists($field['name'], $params);
                $value = $exists ? trim($params[$field['name']]) : '';
            }

            // Is the field required?
            $len = strlen($value);
            if (!$exists || $len === 0) {
                if ($field['required']) {
                    $this->_values[self::ERROR][$field['name']] = 'Value required';
                    continue;
                } else {
                    $value = $field['default'];
                }
            }

            if (isset($field['sanitize'])) {
                $value = call_user_func($field['sanitize'], $value);
            }

            if ($field['type'] === 'text') {
                $this->_has_text_field = TRUE;
            }

            switch ($field['type']) {
                case 'tinyint':
                case 'smallint':
                case 'mediumint':
                case 'int':
                case 'bigint':
                    if (!is_null($value)) {
                        if (filter_var($value, FILTER_VALIDATE_INT) === FALSE) {
                            $this->_values[self::ERROR][$field['name']] = 'Value is not an integer';
                        } else {
                            $value = intval($value);
                            if (isset($field['min']) && $value < $field['min']) {
                                $this->_values[self::ERROR][$field['name']] = 'Value is less than ' . $field['min'];
                            }
                            if (isset($field['max']) && $value > $field['max']) {
                                $this->_values[self::ERROR][$field['name']] = 'Value is greater than ' . $field['max'];
                            }
                        }
                    }
                    $this->_values[self::FORMAT][$field['name']] = '%d';
                    break;
                case 'bit':
                    if (!is_null($value)) {
                        if (filter_var($value, FILTER_VALIDATE_INT) === FALSE) {
                            $this->_values[self::ERROR][$field['name']] = 'Value must be 0 or 1';
                        } else {
                            $value = intval($value);
                            if ($value < 0 || $value > 1) {
                                $this->_values[self::ERROR][$field['name']] = 'Value must be 0 or 1';
                            }
                        }
                    }
                    $this->_values[self::FORMAT][$field['name']] = '%d';
                    break;
                case 'float':
                    if (!is_null($value)) {
                        if (filter_var($value, FILTER_VALIDATE_FLOAT) === FALSE) {
                            $this->_values[self::ERROR][$field['name']] = 'Value is not a float type';
                        } else {
                            $value = floatval($value);
                            if (isset($field['min']) && $value < $field['min']) {
                                $this->_values[self::ERROR][$field['name']] = 'Value is less than ' . $field['min'];
                            }
                            if (isset($field['max']) && $value > $field['max']) {
                                $this->_values[self::ERROR][$field['name']] = 'Value is greater than ' . $field['max'];
                            }
                        }
                    }
                    $this->_values[self::FORMAT][$field['name']] = '%f';
                    break;
                case 'date':
                    if (!is_null($value)) {
                        if (DateTime::createFromFormat(MYSQL_FORMAT_DATE, $value)) {
                            $this->_values[self::ERROR][$field['name']] = sprintf('Invalid date format, use %s', MYSQL_FORMAT_DATE);
                        }
                    }
                    $this->_values[self::FORMAT][$field['name']] = '%s';
                    break;
                case 'timestamp':
                    if (!is_null($value)) {
                        if (!DateTime::createFromFormat(MYSQL_FORMAT_DATE_AND_TIME, $value)) {
                            $this->_values[self::ERROR][$field['name']] = sprintf('Invalid datetime format, use %s', MYSQL_FORMAT_DATE_AND_TIME);
                        }
                    }
                    $this->_values[self::FORMAT][$field['name']] = '%s';
                    break;
                case 'varchar':
                case 'text':
                    $value = stripslashes($value);
                    if (!is_null($value) && isset($field['size'])) {
                        if ($len > $field['size']) {
                            $this->_values[self::ERROR][$field['name']] = sprintf('Value too long, max length is %d', $field['size']);
                        }
                    }
                    $this->_values[self::FORMAT][$field['name']] = '%s';
                    break;
                case 'enum' :
                    if (!is_null($value)) {
                        if (!in_array($value, $field['data'])) {
                            $this->_values[self::ERROR][$field['name']] = sprintf('Value of %s is invalid', $value);
                        }
                    }
                    $this->_values[self::FORMAT][$field['name']] = '%s';
                    break;
                case 'point' :
                    $point_params = [];
                    foreach ($field['params'] as $param) {
                        if (isset($param, $this->_values[self::VALIDATED])) {
                            $point_params[] = $this->_values[self::VALIDATED][$param];
                        }
                    }
                    $value = sprintf('POINT(%f, %f)', $point_params[0], $point_params[1]);
                    $this->_values[self::FORMAT][$field['name']] = NULL;
                    break;
            }

            if (!isset($this->_values[self::ERROR][$field['name']])) {
                $this->_values[self::VALIDATED][$field['name']] = $value;
            }
        }

        foreach ($this->_custom_validation as $rule) {
            if (is_callable($rule)) {
                call_user_func_array($rule, [$this->_values[self::VALIDATED]]);
            }
        }
    }

    public function render(): void
    { ?>
        <style>
            .m_err {
                outline: thin solid;
                outline-color: red;
            }

            .m_req {
                font-style: italic;
            }

            .m_desc {
                font-style: italic;
            }

            .m_clear {
                height: 15px;
            }

            .m_ro {
                height: 32px;
            }

            .m_ro td:nth-child(2) {
                font-style: italic;
                font-weight: 600;
            }
        </style>
        <table>
            <?php
            $model_values_original = $this->get_values_original();
            $model_values_validated = $this->get_values_validated();
            $model_values_error = $this->get_values_error();
            $model_table = $this->get_table();
            foreach ($this->_schema as $field) {
                if (is_null($field)) {
                    echo '<tr class="m_clear"><td></td><td></td></tr>';
                    continue;
                }

                // Do we render this field?
                if (isset($field['render']) && $field['render'] === FALSE) {
                    continue;
                }

                // All id fields are hidden.
                if (substr($field['name'], -2) === 'id') {
                    echo sprintf('<input type="hidden" name="%s[%s]" value="%s">', $model_table, $field['name'], esc_html($model_values_validated[$field['name']]));
                    continue;
                }

                $field_display_name = __($model_table . '_' . $field['name'], '_plugin');
                $field_input_name = sprintf('%s[%s]', $model_table, $field['name']);
                $validated_value = isset($model_values_validated[$field['name']]) ? $model_values_validated[$field['name']] : @$model_values_original[$field['name']];

                $class = 'm_val';
                $error = '';
                if ($field['required']) {
                    $field_display_name .= ' <strong>*</strong>';
                    $class .= ' m_req';
                }
                if (array_key_exists($field['name'], $model_values_error)) {
                    $class .= ' m_err';
                    $error = $model_values_error[$field['name']];
                }

                // Is the field read only?
                $readonly = isset($field['readonly']) && $field['readonly'] === TRUE;
                if (isset($field['edit_capability'])) {
                    if (!current_user_can($field['edit_capability'])) {
                        $readonly = TRUE;
                    }
                }
                if ($readonly) {
                    $class .= ' m_ro';
                }

                // Is the field disabled?
                $disabled = isset($field['disabled']) && $field['disabled'] === TRUE ? 'disabled' : '';

                echo sprintf('<tr class="%s"><td>%s</td><td>', $class, $field_display_name);
                switch ($field['type']) {
                    case 'tinyint':
                    case 'smallint':
                    case 'mediumint':
                    case 'int':
                    case 'bigint':
                    case 'float':
                        if ($readonly) {
                            echo $validated_value;
                        } else {
                            echo sprintf('<input %s type="number" id="%s" name="%s" value="%s">', $disabled, $field['name'], $field_input_name, $validated_value);
                        }
                        break;
                    case 'bit':
                        if ($readonly) {
                            echo $validated_value === 0 ? 'No' : 'Yes';
                        } else {
                            echo sprintf('<select %s id="%s" name="%s">', $disabled, $field['name'], $field_input_name);
                            if (is_null($field['default'])) {
                                echo sprintf('<option value=""%s></option><br/>', $validated_value === NULL ? ' selected' : '');
                            }
                            echo sprintf('<option value="0"%s>No</option><br/>', $validated_value === 0 ? ' selected' : '');
                            echo sprintf('<option value="1"%s>Yes</option><br/>', $validated_value === 1 ? ' selected' : '');
                            echo '</select>';
                        }
                        break;
                    case 'timestamp':
                    case 'date':
                        if ($readonly) {
                            echo $validated_value;
                        } else {
                            echo sprintf('<input %s data-type="%s" class="datepicker" type="text" id="%s" name="%s" value="%s">', $disabled, $field['type'], $field['name'], $field_input_name, $validated_value);
                        }
                        break;
                    case 'varchar':
                        if ($readonly) {
                            echo esc_html($validated_value);
                        } else {
                            if ($field['size'] > 100) {
                                echo sprintf('<textarea %s rows="4" autocomplete="off" cols="90" maxlength="%d" id="%s" name="%s">%s</textarea>', $disabled, $field['size'], $field['name'], $field_input_name, $validated_value);
                            } else {
                                echo sprintf('<input %s type="text" id="%s" name="%s" value="%s" size="%d">', $disabled, $field['name'], $field_input_name, $validated_value, $field['size']);
                            }
                        }
                        break;
                    case 'text':
                        $settings = array(
                            'wpautop' => FALSE,
                            'media_buttons' => FALSE,
                            'textarea_name' => $field_input_name,
                            'textarea_rows' => get_option('default_post_edit_rows', 10),
                            'editor_css' => '',
                            'editor_class' => '',
                            'teeny' => FALSE,
                            'tinymce' => TRUE,
                            'quicktags' => TRUE,
                            'readonly' => $readonly
                        );
                        if ($readonly) {
                            echo esc_html($validated_value);
                        } else {
                            wp_editor($validated_value, sprintf('%s_%s', $model_table, $field['name']), $settings);
                        }
                        break;
                    case 'enum' :
                        if ($readonly) {
                            echo $validated_value;
                        } else {
                            echo sprintf('<select %s name="%s">', $disabled, $field_input_name);
                            foreach ($field['data'] as $index => $value) {
                                echo sprintf('<option value="%s"%s>%s</option>', $value, $validated_value === $value ? ' selected' : '', esc_html($value));
                            }
                            echo '</select>';
                        }
                        break;
                }
                echo sprintf('%s</td></tr>', esc_html($error));

                if (isset($field['description'])) {
                    echo sprintf('<tr class="%s"><td></td><td>%s</td></tr>', 'm_desc', $field['description']);
                }
            }
            ?>
        </table>
        <p>
            <em><strong>*</strong> required fields.</em>
        </p>
        <?php
    }

    public function get_table(): string
    {
        return $this->_table;
    }

    public function get_key(): string
    {
        return $this->_key;
    }

    public function get_schema(): array
    {
        return $this->_schema;
    }

    public function get_values_original(): array
    {
        return $this->_values[self::ORIGINAL];
    }

    public function get_values_validated(): array
    {
        return $this->_values[self::VALIDATED];
    }

    public function get_values_error(): array
    {
        return $this->_values[self::ERROR];
    }

    public function get_values_format(): array
    {
        return $this->_values[self::FORMAT];
    }

    public function get_values_model(): array
    {
        return $this->_values[self::MODEL];
    }

    public function has_errors(): bool
    {
        return !empty($this->_values[self::ERROR]);
    }

    public function has_error(stirng $field_name): bool
    {
        return !empty($this->_values[self::ERROR][$field_name]);
    }

    public function add_error(string $field_name, string $error): void
    {
        $this->_values[self::ERROR][$field_name] = $error;
    }

    public function get_nice_name(): string
    {
        return $this->_nice_name;
    }

    public function get_has_text_field(): bool
    {
        return $this->_has_text_field;
    }

}
