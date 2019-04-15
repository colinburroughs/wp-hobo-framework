<?php

/**
 * Plugin Name: Hobo Plugin Framework - Validation.
 * Description: A simple validation class.
 * Version: 1.0
 * Author: Hobo Digital Ltd.
 */

namespace Hobo\Framework;

abstract class VALIDATION_TYPE
{
    const STRING = 'string';
    const INTEGER = 'integer';
    const FLOAT = 'float';
    const BOOLEAN = 'bool';
    const EMAIL = 'email';
    const URL = 'url';
    const IPV4 = 'ipv4';
    const IPV6 = 'ipv6';
}

class Validation
{
    /* errors array */
    public $errors = array();

    /* the sanitized values array */
    public $sanitized = array();

    /* the validation rules array */
    private $validation_rules = array();

    /* the source */
    private $source = array();

    /**
     * Validation constructor.
     *
     * @param array|NULL $source
     */
    public function __construct(array $source = NULL)
    {
        if (!is_null($source)) {
            $this->source = $source;
        }
    }

    /**
     * @param array $source
     *
     * @return \Hobo\Framework\Validation
     */
    public function add_source(array $source): Validation
    {
        $this->source = $source;
        /* allow chaining */
        return $this;
    }


    /**
     * @param string $name
     * @param string $nice_name
     * @param string $type
     * @param bool   $required
     * @param bool   $trim
     * @param int    $min
     * @param int    $max
     * @param bool   $strip_tags
     *
     * @return \Hobo\Framework\Validation
     */
    public function addRule(string $name, string $nice_name, string $type, bool $required = FALSE, bool $trim = TRUE, int $min = 0, int $max = 0, bool $strip_tags = FALSE): Validation
    {
        $this->validation_rules [$name] = array(
            'nicename' => $nice_name,
            'type' => $type,
            'required' => $required,
            'min' => $min,
            'max' => $max,
            'trim' => $trim,
            'striptags' => $strip_tags
        );
        /* allow chaining */
        return $this;
    }

    /**
     * @param array $rules_array
     */
    public function add_rules(array $rules_array): void
    {
        $this->validation_rules = array_merge($this->validation_rules, $rules_array);
    }

    /**
     * Run the validation rules.
     */
    public function validate(): void
    {
        foreach ($this->validation_rules as $name => $opt) {

            /* Trim whitespace from beginning and end if trim option is stated. */
            if (isset($opt['trim']) && $opt ['trim'] === TRUE) {
                $this->source[$name] = trim($this->source[$name]);
            }

            /* Required. */
            if ($opt ['required'] === TRUE) {
                $this->is_required($name, $opt ['nicename']);
                continue;
            }

            switch ($opt ['type']) {
                case VALIDATION_TYPE::STRING:
                    $this->validate_string($name, $opt ['nicename'], $opt ['min'], $opt ['max'], $opt ['striptags']);
                    if (!isset($this->errors[$name])) {
                        $this->sanitize_string($name);
                    }
                    break;

                case VALIDATION_TYPE::INTEGER:
                    $this->validate_int($name, $opt ['nicename'], $opt ['min'], $opt ['max']);
                    if (!isset($this->errors[$name])) {
                        $this->sanitize_int($name);
                    }
                    break;

                case VALIDATION_TYPE::FLOAT:
                    $this->validate_float($name, $opt ['nicename'], $opt ['min'], $opt ['max']);
                    if (!isset($this->errors[$name])) {
                        $this->sanitize_float($name);
                    }
                    break;

                case VALIDATION_TYPE::BOOLEAN:
                    $this->validate_bool($name, $opt ['nicename']);
                    if (!isset($this->errors[$name])) {
                        $this->sanitized[$name] = (bool)$this->source[$name];
                    }
                    break;

                case VALIDATION_TYPE::EMAIL:
                    $this->validate_email($name, $opt ['nicename']);
                    if (!isset($this->errors[$name])) {
                        $this->sanitize_email($name);
                    }
                    break;

                case VALIDATION_TYPE::URL:
                    $this->validate_url($name, $opt ['nicename']);
                    if (!isset($this->errors[$name])) {
                        $this->sanitize_url($name);
                    }
                    break;

                case VALIDATION_TYPE::IPV4:
                    $this->validate_ipv4($name, $opt ['nicename']);
                    if (!isset($this->errors[$name])) {
                        $this->sanitize_string($name);
                    }
                    break;

                case VALIDATION_TYPE::IPV6:
                    $this->validate_ipv6($name, $opt ['nicename']);
                    if (!isset($this->errors[$name])) {
                        $this->sanitize_string($name);
                    }
                    break;
            }
        }
    }

    /**
     * Check if source variable is required and set
     *
     * @param string $name
     * @param string $nice_name
     */
    private function is_required(string $name, string $nice_name): void
    {
        if (!isset($this->source[$name]) || strlen($this->source[$name]) === 0) {
            $this->errors[$name] = $nice_name . ' is not set';
        }
    }

    /**
     * @param string $name
     * @param string $nice_name
     */
    private function validate_email(string $name, string $nice_name): void
    {
        if (filter_var($this->source[$name], FILTER_VALIDATE_EMAIL) === FALSE) {
            $this->errors[$name] = $nice_name . ' is not a valid email address';
        }
    }

    /**
     * @param string $name
     */
    private function sanitize_email(string $name): void
    {
        $email = sanitize_text_field($this->source[$name]);
        $this->sanitized[$name] = filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    /**
     * @param string $name
     * @param string $nice_name
     */
    private function validate_url(string $name, string $nice_name): void
    {
        if (filter_var($this->source[$name], FILTER_VALIDATE_URL) === FALSE) {
            $this->errors[$name] = $nice_name . ' is not a valid URL';
        }
    }

    /**
     * @param string $name
     */
    private function sanitize_url(string $name): void
    {
        $this->sanitized[$name] = ( string )filter_var($this->source[$name], FILTER_SANITIZE_URL);
    }

    /**
     * @param string $name
     * @param string $nice_name
     * @param int    $min
     * @param int    $max
     */
    private function validate_int(string $name, string $nice_name, int $min = 0, int $max = 0): void
    {
        if (filter_var($this->source[$name], FILTER_VALIDATE_INT, array(
                "options" => array(
                    "min_range" => $min,
                    "max_range" => $max
                )
            )) === FALSE) {
            $this->errors[$name] = $nice_name . ' is an invalid value';
        }
    }

    /**
     * @param $name
     */
    private function sanitize_int(string $name): void
    {
        $this->sanitized[$name] = ( int )filter_var($this->source[$name], FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * @param string $name
     * @param string $nice_name
     * @param float  $min
     * @param float  $max
     */
    private function validate_float(string $name, string $nice_name, float $min = 0, float $max = 0): void
    {
        if (filter_var($this->source[$name], FILTER_VALIDATE_FLOAT, array(
                "options" => array(
                    "min_range" => $min,
                    "max_range" => $max
                )
            )) === FALSE) {
            $this->errors[$name] = $nice_name . ' is an invalid value';
        }
    }

    /**
     * @param $name
     */
    private function sanitize_float(string $name): void
    {
        $this->sanitized[$name] = (float)filter_var($this->source[$name], FILTER_SANITIZE_NUMBER_FLOAT);
    }

    /**
     * @param string $name
     * @param string $nice_name
     * @param int    $min
     * @param int    $max
     * @param bool   $striptags
     */
    private function validate_string(string $name, string $nice_name, int $min = 0, int $max = 0, bool $striptags = FALSE): void
    {
        if (isset ($this->source[$name])) {
            if ($striptags === TRUE) {
                $this->source[$name] = wp_strip_all_tags($this->source[$name]);
            }
            if (strlen($this->source[$name]) < $min) {
                if (strlen($this->source[$name]) == 0) {
                    $this->errors[$name] = $nice_name . ' is not set';
                } else {
                    $this->errors[$name] = $nice_name . ' is too short';
                }
            } else if (strlen($this->source[$name]) > $max) {
                $this->errors[$name] = $nice_name . ' is too long';
            } else if (!is_string($this->source[$name])) {
                $this->errors[$name] = $nice_name . ' is invalid';
            }
        }
    }

    /**
     * @param string $name
     */
    private function sanitize_string(string $name): void
    {
        $this->sanitized[$name] = (string)filter_var($this->source[$name], FILTER_SANITIZE_STRING);
    }

    /**
     * @param string $name
     * @param string $nice_name
     */
    private function validate_ipv4(string $name, string $nice_name): void
    {
        if (filter_var($this->source[$name], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === FALSE) {
            $this->errors[$name] = $nice_name . ' is not a valid IPv4';
        }
    }

    /**
     * @param string $name
     * @param string $nice_name
     */
    private function validate_ipv6(string $name, string $nice_name): void
    {
        if (filter_var($this->source[$name], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === FALSE) {
            $this->errors[$name] = $nice_name . ' is not a valid IPv6';
        }
    }

    /**
     * @param string $name
     * @param string $nice_name
     */
    private function validate_bool(string $name, string $nice_name): void
    {
        if (filter_var($this->source[$name], FILTER_VALIDATE_BOOLEAN) === FALSE) {
            $this->errors[$name] = $nice_name . ' is invalid';
        }
    }

    /**
     * @param string $value
     */
    private function validate_mx_record(string $value): void
    {
        list ($prefix, $domain) = explode('@', $value);
        $result = function_exists("getmxrr") && getmxrr($domain, $mxhosts);
    }

}
