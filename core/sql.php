<?php
/**
 * Online Module Management Platform
 * 
 * File used to connect to the database
 * 
 * @author  The OMMP Team
 * @version 1.0 
 */

require_once OMMP_ROOT . "/core/credentials.php";

// Try to connect to the database and print an error if it failed
try {
    $sql = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass, [
		PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
	]);
} catch (Exception $e) {
    print "An unexpected error occurred!<br />Please contact an administrator with these informations:<br /><code>MySQL Connection Error: ".htmlspecialchars($e->getMessage())."</code>";
    exit;
}

/**
 * Checks if a value is in a table for a given column
 * 
 * @param string $table
 *      The name of the table (with prefix)
 * @param string $column
 *      The column name
 * @param mixed $value
 *      The value to look for
 * 
 * @return boolean
 *      TRUE if the value was found
 *      FALSE else
 */
function dbSearchValue($table, $column, $value) {
    global $sql;
    return dbExists($table, "$column = " . $sql->quote($value));
}

/**
 * Checks if a row satisfies the given condition
 * 
 * @param string $table
 *      The table in which to check the condition (with prefix)
 * @param string $condition
 *      The SQL condition to check
 * 
 * @return boolean
 *      TRUE if at least one line validates the condition
 *      FALSE else
 */
function dbExists($table, $condition) {
	return dbCount($table, $condition) != 0;
}

/**
 * Returns the first row of a table matching a condition
 * 
 * @param string $table
 *      The name of the table (with prefix)
 * @param string $condition
 *      The SQL condition to execute
 * @param string $columns
 *      The list of columns to return ("*" by default)
 * @param boolean $return_single_column
 * 		Should we return only one column instead of an array?
 * 		If TRUE you must specify only one column in $columns
 * 		Optional, default is FALSE to return an array
 * 
 * @return array|mixed
 *      An array containing the result of the query
 * 		Or a single value if $return_single_column is set to TRUE
 */
function dbGetFirstLineSimple($table, $condition, $columns="*", $return_single_column=FALSE) {
    $result = dbGetFirstLine("SELECT $columns FROM $table WHERE $condition");
	if ($return_single_column && $result !== FALSE) {
		return $result[$columns];
	}
	return $result;
}

/**
 * Returns the first row matching an SQL query
 * 
 * @param string $request
 *      The query to execute
 * 
 * @return array
 *      An array containing the result of the query
 */
function dbGetFirstLine($request) {
    global $sql;
    $request = $sql->query($request);
    $result = $request->fetch();
    $request->closeCursor();
    return $result;
}

/**
 * Count the number of rows in a table
 * 
 * @param string $table
 * 		The name of the table (with prefix)
 * @param string $confition
 * 		The condition on the lines to count
 * 		Optional, NULL to select all
 * 
 * @return int
 * 		The number of lines
 */
function dbCount($table, $condition=NULL) {
	$result = dbGetFirstLine("SELECT COUNT(*) AS c FROM $table". ($condition === NULL ? "" : " WHERE $condition"));
	return intval($result['c']);
}