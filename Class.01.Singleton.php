<?php

/**
 * @wordpress-plugin
 * Plugin Name:   Hobo Plugin Framework - Singleton.
 * Plugin URI:    https://www.hobo.co.uk
 * Description:   Singleton ensures that there will only be one instance of a class, and provides a global access point to that instance.
 * Version:       0.0.0
 * Author:        Hobo Digital Ltd.
 * Author URI:    https://www.hobo.co.uk
 *
 * <code>
 * $var = Singleton::getInstance('className');
 * </code>
 */

namespace Hobo\Framework;

use ReflectionClass;
use ReflectionException;

class Singleton
{
    /**
     * The instances of the singleton
     *
     * @var array
     * @staticvar
     * @access private
     */
    private static $instances = array();

    /**
     * Private constructor to prevent creating a new instance of the
     * Singleton via the new operator from outside of this class.
     *
     * @access private
     */
    private function __construct()
    {
        // Locked down the constructor, therefore the class cannot be externally instantiated
    }

    /**
     * Returns the Singleton instance of this class.
     *
     * @param string $class_name
     * @param array  $params
     *
     * @return mixed
     */
    public static function get_instance(string $class_name, array $params = array())
    {
        if (!isset(self::$instances[$class_name])) {
            if (count($params) === 0) {
                self::$instances[$class_name] = new $class_name;
            } else {
                $object = NULL;
                try {
                    $reflector = new ReflectionClass($class_name);
                    $object = $reflector->newInstanceArgs($params);
                } catch (ReflectionException $e) {
                    trigger_error($e->getMessage(), E_USER_ERROR);
                }
                self::$instances[$class_name] = $object;
            }
        }
        return self::$instances[$class_name];
    }

    /**
     * Private clone method to prevent cloning of the instance of the Singleton instance.
     *
     * @access public
     * @return void
     */
    public function __clone()
    {
        trigger_error("Cannot clone instance of Singleton pattern", E_USER_ERROR);
    }

    /**
     * Private deserialize method to prevent deserialization of the Singleton instance.
     *
     * @access public
     * @return void
     */
    public function __wakeup()
    {
        trigger_error("Cannot deserialize instance of Singleton pattern", E_USER_ERROR);
    }
}
