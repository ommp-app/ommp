<?php
/**
 * Online Module Management Platform
 * 
 * Class to read and update the configuration
 * 
 * @author  The OMMP Team
 * @version 1.0 
 */

// Create a configuration object
$config = new Config();

// The configuration class
class Config {

    // The cache of the configurations values
    private $values;

    /**
     * Class constructor
     * Load the current config and cache it
     */
    function __construct() {
        global $sql, $db_name, $db_prefix;
        $request = $sql->query("SELECT * FROM $db_name.{$db_prefix}config");
        while ($line = $request->fetch()) {
            $this->values[$line['name']] = $line['value'];
        }
        $request->closeCursor();
    }

    /**
     * Get a configuration value
     * 
     * @param string $name
     *      The name of the configuration to read
     * @param mixed $default
     *      The value to return if the key is not found
     *      Optional, default is NULL
     * 
     * @return string|mixed
     *      The value in the configuration
     */
    public function get($name, $default=NULL) {
        if (isset($this->values[$name])) {
            return $this->values[$name];
        }
        return $default;
    }

    /**
     * Set a configuration value
     * 
     * @param string $name
     *      The name of the configuration to set
     * @param mixed $value
     *      The value of the configuration (will be stored as a string)
     * 
     * @return boolean
     *      TRUE if the value has been set
     *      FALSE else
     */
    public function set($name, $value) {
        // TODO: Add a check fonction to validate the value
        global $sql, $db_name, $db_prefix;
        $quoted_name = $sql->quote($name);
        // Check if the value exists in the base
        if (dbExists("{$db_prefix}config", "name = $quoted_name")) {
            // Update the data base
            $request = "UPDATE $db_name.{$db_prefix}config SET `value` = " . $sql->quote($value) . " WHERE name = $quoted_name";
        } else {
            // Create the value
            $request = "INSERT INTO $db_name.{$db_prefix}config VALUES (" . $sql->quote($value) . ", $quoted_name)";
        }
        // Execute the modification in MySQL
        $result = $sql->exec($request);
        if ($result === FALSE) {
            return FALSE;
        }
        // Update the cache
        $this->values[$name] = $value;
        return TRUE;
    }

}