<?php

/**
 * @wordpress-plugin
 * Plugin Name:   Hobo Plugin Framework - Queries.
 * Plugin URI:    https://www.hobo.co.uk
 * Description:   A simple class for handling database queries.
 * Version:       0.0.0
 * Author:        Hobo Digital Ltd.
 * Author URI:    https://www.hobo.co.uk
 */

namespace Hobo\Framework;

define('DEFAULT_QUERY_RESULT_TYPE', 'ARRAY_A');
define('MYSQL_FORMAT_DATE', 'Y-m-d');
define('MYSQL_FORMAT_DATE_AND_TIME', 'Y-m-d H:i:s');

class Queries extends Request_Cache
{
    private $_return_query = TRUE;
    private $_today;

    public function __construct()
    {
        $this->_today = date(MYSQL_FORMAT_DATE);
    }

    public function start_transaction(): void
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
    }

    public function commit_transaction(): void
    {
        global $wpdb;
        $wpdb->query('COMMIT');
    }

    public function rollback_transaction(): void
    {
        global $wpdb;
        $wpdb->query('ROLLBACK');
    }

    protected function get_today()
    {
        return $this->_today;
    }

    public function get_user_id(): int
    {
        return get_current_user_id();
    }

    /**
     * @param        $cacheKey
     * @param        $sql
     * @param string $output
     *
     * @return array
     */
    public function execute_build_return(string $cacheKey, string $sql, $output = DEFAULT_QUERY_RESULT_TYPE): array
    {
        global $wpdb;
        if ($this->is_use_cache() && !is_null($cacheKey) && $this->is_in_cache($cacheKey)) {
            return $this->get_from_cache($cacheKey);
        }
        $query_result = $wpdb->get_results($sql, $output);

        $result = $this->build_return($query_result);
        if ($this->is_use_cache()) {
            $this->put_in_cache($cacheKey, $result);
        }
        return $result;
    }

    /**
     * @param $result
     *
     * @return array
     */
    public function build_return($result): array
    {
        global $wpdb;
        $return = [];
        $return['success'] = $wpdb->last_error === '' ? TRUE : FALSE;
        $return['ajax'] = wp_doing_ajax();
        $return['error'] = $wpdb->last_error;
        $return['result'] = $result;
        $return['insert_id'] = $wpdb->insert_id;
        $return['rows_affected'] = $wpdb->rows_affected;
        $return['count'] = $return['success'] ? (is_array($result) ? count($result) : 0) : 0;
        $return['user_id'] = $this->get_user_id();
        if ($this->_return_query === TRUE) {
            $return['query'] = $wpdb->last_query;
        }
        return $return;
    }

    /**
     * @param $table
     * @param $data
     *
     * @return false|int
     */
    protected function insert_rows(string $table, array $data)
    {
        global $wpdb;
        $values = array();
        $place_holders = array();
        $query_columns = '';

        $query = "INSERT INTO {$table} (";
        foreach ($data as $count => $row_array) {
            foreach ($row_array as $key => $value) {
                if ($count == 0) {
                    if ($query_columns) {
                        $query_columns .= ',' . $key;
                    } else {
                        $query_columns .= $key;
                    }
                }
                $values[] = $value;
                if (is_numeric($value)) {
                    if (isset($place_holders[$count])) {
                        $place_holders[$count] .= ", '%d'";
                    } else {
                        $place_holders[$count] = "( '%d'";
                    }
                } else {
                    if (isset($place_holders[$count])) {
                        $place_holders[$count] .= ", '%s'";
                    } else {
                        $place_holders[$count] = "( '%s'";
                    }
                }
            }
            $place_holders[$count] .= ')';
        }

        $query .= " $query_columns ) VALUES ";
        $query .= implode(', ', $place_holders);
        return $wpdb->query($wpdb->prepare($query, $values));
    }

    public function query(string $sql): array
    {
        global $wpdb;
        return $this->build_return($wpdb->query($sql));
    }

    /**
     * @param $table
     *
     * @return array
     */
    public function describe_table(string $table): array
    {
        $cacheKey = $this->get_cache_key(__FUNCTION__, func_get_args());
        $table = preg_replace('/[^\w-]/', '', $table);
        $query = 'DESCRIBE ' . sanitize_text_field($table);
        return $this->execute_build_return($cacheKey, $query);
    }

    /**
     * @param $table
     * @param $key
     * @param $value
     *
     * @return array
     */
    public function get_data_from_table_by_key_and_value(string $table, string $key, int $value): array
    {
        global $wpdb;
        $cacheKey = $this->get_cache_key(__FUNCTION__, func_get_args());
        $table = preg_replace('/[^\w-]/', '', $table);
        $key = preg_replace('/[^\w-]/', '', $key);
        $query = $wpdb->prepare('SELECT * FROM ' . $table . ' WHERE ' . sanitize_text_field($key) . ' = %d', $value);
        return $this->execute_build_return($cacheKey, $query);
    }

    /**
     * @param       $table
     * @param       $values
     * @param       $where
     * @param       $values_format
     * @param array $where_format
     *
     * @return array
     */
    public function wp_update($table, $values, $where, $values_format, $where_format = array('%d')): array
    {
        global $wpdb;
        return $this->build_return($wpdb->update($table, $values, $where, $values_format, $where_format));
    }

    /**
     * @param $table
     * @param $values
     * @param $values_format
     *
     * @return array
     */
    public function wp_insert(string $table, $values, $values_format): array
    {
        global $wpdb;
        return $this->build_return($wpdb->insert($table, $values, $values_format));
    }

    /**
     * Performs a plugin database update
     *
     * @param string $version
     * @param string $plugin_path
     */
    public function update_plugin_database(string $version, string $plugin_path): void
    {
        $update_file = $plugin_path . DIRECTORY_SEPARATOR . 'update-' . $version . '.sql';
        if (file_exists($update_file)) {
            global $wpdb;
            $statements = explode(';', file_get_contents($update_file));
            foreach ($statements as $sql) {
                $wpdb->query($sql);
            }
        }
    }

}
