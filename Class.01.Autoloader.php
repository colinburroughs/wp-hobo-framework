<?php

/**
 * @wordpress-plugin
 * Plugin Name:   Hobo Plugin Framework - Autoloader.
 * Plugin URI:    https://www.hobo.co.uk
 * Description:   Abstract autoloader class to dynamically load classes instantiated elsewhere in a plugin.
 * Version:       0.0.0
 * Author:        Hobo Digital Ltd.
 * Author URI:    https://www.hobo.co.uk
 *
 * Dynamically loads the class attempting to be instantiated elsewhere in the plugin by looking at the $class_name parameter being passed as an argument.
 *
 * The argument should be in the form: Your_Root_Namespace\Namespace. The function will then break the fully qualified class name into pieces and build
 * a file to the path based on the namespace.
 *
 * The namespaces of the plugin maps to directory structure paths.
 *
 * Usage:
 * use Hobo\Framework\Autoloader;
 *
 * class My_Plugin_Autoloader extends Autoloader {
 *     public function __construct() {
 *         parent::__construct(__FILE__, 'namespace');
 *     }
 * }
 *
 */

namespace Hobo\Framework;

abstract class Autoloader
{
    private $_namespace;
    private $_root;

    /**
     * Autoloader constructor.
     *
     * @param string $file
     * @param string $namespace
     *
     * @throws \Exception
     */
    public function __construct(string $file, string $namespace)
    {
        $this->_namespace = $namespace;
        $this->_root = dirname(dirname($file)) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR;
        spl_autoload_register(array($this, 'autoload'));
    }

    /**
     * @param string $class_name The fully qualified name of the class to load.
     */
    public function autoload(string $class_name): void
    {
        // If the specified $class_name does not include our namespace, run away.
        if (strpos($class_name, $this->_namespace) !== 0) {
            return;
        }

        // Split the class name into an array to read the namespace and class.
        $file_parts = explode('\\', $class_name);

        // Do a reverse loop through $file_parts to build the path to the file.
        $namespace = $filename = '';

        for ($i = count($file_parts) - 1; $i > 0; $i--) {
            // Read the current component of the file part.
            $current = strtolower($file_parts[$i]);
            $current = str_ireplace('_', '-', $current);

            // If we're at the first entry, then we're at the filename.
            if (count($file_parts) - 1 === $i) {
                // If interface is contained in the parts of the file name, then define the $filename differently so that it's properly loaded. Otherwise, just set the $filename equal to that of the class filename structure.
                if (strpos(strtolower($file_parts[count($file_parts) - 1]), 'interface')) {
                    // Grab the name of the interface from its qualified name.
                    $interface_name = explode('_', $file_parts[count($file_parts) - 1]);
                    $interface_name = $interface_name[0];
                    $filename = 'interface-' . $interface_name . '.php';
                } else {
                    $filename = 'class-' . $current . '.php';
                }
            } else {
                $namespace = DIRECTORY_SEPARATOR . $current . $namespace . DIRECTORY_SEPARATOR;
            }
        }

        // Now build a path to the file using mapping to the file location.
        $filepath = $this->_root . $namespace . $filename;

        // If the file exists in the specified path, then include it.
        if (file_exists($filepath)) {
            include_once $filepath;
        } else {
            if (!wp_doing_ajax()) {
                echo '<pre>';
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                echo '</pre>';
            } else {
                $return = [];
                $return['success'] = FALSE;
                $return['ajax'] = TRUE;
                $return['error'] = print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), TRUE);
                wp_send_json($return, 200);
            }
            wp_die(sprintf('Failed to autoload - %s does not exist.', $filepath));
        }
    }

    public function get_namespace(): string
    {
        return $this->_namespace;
    }

    public function get_root(): string
    {
        return $this->_root;
    }
}
