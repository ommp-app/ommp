<?php
/**
 * Online Module Management Platform
 * 
 * Functions for the installation form
 * 
 * @author  The OMMP Team
 * @version 1.0 
 */

/**
 * Display a page replacing the variables and exit
 * 
 * @param string $file
 * 		The file containing the page
 * @param string $title
 * 		The title of the page
 * @param array $variables
 * 		The variables to replace in the file
 */
function page_display($file, $title, $variables=[]) {
	global $lang_code, $lang, $pages_path;

	// Read the files
	$content = file_get_contents("$pages_path/header.html") . file_get_contents("$pages_path/$file") . file_get_contents("$pages_path/footer.html");

	// Replace the lang variables
	foreach ($lang as $key => $value) {
		$content = str_replace("{L:" . strtoupper($key) . "}", htmlspecialchars($value), $content);
	}

	// Replace the user variables
	$variables['title'] = $title;
	$variables['lang'] = $lang_code;
	foreach ($variables as $key => $value) {
		$content = str_replace("{" . strtoupper($key) . "}", htmlspecialchars($value), $content);
	}

	// Print the page
	print($content);

	// Exit
	exit;

}

/**
 * Display a media and exit
 * 
 * @param string $file
 * 		The file containing the media
 */
function media_display($file) {
	global $media_path;
	
	// Check file name and if file exists
	$path = "$media_path/$file";
	if (strpos($file, "..") !== FALSE || !file_exists($path)) {
		exit;
	}

	// Get mime type
	$mime = better_mime_type($path);
	header("Content-type: $mime");

	// Display file
	readfile($path);

	// Exit
	exit;

}

/**
 * Get the mime type of a file, with extention management
 * 
 * @param string $file
 *      The file to check
 * 
 * @return string
 *      The mime type of the file
 */
function better_mime_type($file) {

    // Check extensions for files that PHP does not recognize every times
    $types = [
        "css" => "text/css",
        "js" => "text/javascript"
    ];
    $extension = substr($file, strrpos($file, ".") + 1);
    if (isset($types[$extension])) {
        return $types[$extension];
    }

    // For all others, return the detected mime type
    return mime_content_type($file);
    
}

/**
 * Connect to MySQL
 * 
 * @param string $db_host
 * 		The database host
 * @param string $db_name
 * 		The database name
 * @param string $db_user
 * 		The MySQL username
 * @param string $db_pass
 * 		The password for the user
 * 
 * @return PDO|string
 * 		A PDO object representing the connection to the database
 * 		A string representing the error message in case of error
 */
function mysql_connect($db_host, $db_name, $db_user, $db_pass) {
	// Try to connect to the database and print an error if it failed
	try {
		$sql = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass, [
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		]);
		return $sql;
	} catch (Exception $e) {
		return $e->getMessage();
	}
}

/**
 * Check if all the keys are set in an array
 * 
 * @param array $array
 *      The array to check in
 * @param array $keys
 *      The keys to check in the array
 * 
 * @return boolean
 *      TRUE if all the keys are in the array
 *      FALSE else
 */
function check_keys($array, $keys) {
    foreach ($keys as $key) {
        if (!isset($array[$key])) {
            return FALSE;
        }
    }
    return TRUE;
}

/**
 * Display an error page and exit
 * 
 * @param string $error
 * 		The error message
 */
function error($error) {
	global $lang;
	page_display("error.html", $lang->error, ["error" => $error]);
}

/**
 * Execute a SQL file
 * 
 * @param string $file
 * 		The file
 * 
 * @return boolean
 * 		TRUE if success
 * 		FALSE else
 */
function exec_sql_file($file) {
	global $sql, $db_prefix;
	$sql_content = str_replace("{PREFIX}", $db_prefix, @file_get_contents($file));
	return $sql->exec($sql_content) !== FALSE;
}

/**
 * Create the settings and the rights for a module
 * 
 * @param string $path
 * 		The path of the module
 * @param int $priority
 * 		The module priority
 * 
 * @return boolean
 * 		TRUE if success
 * 		FALSE else
 */
function finish_module_creation($path, $priority) {
	global $sql, $db_prefix;

	// Read metadata
	$meta = json_decode(@file_get_contents("$path/meta.json"));
	if ($meta === NULL || !isset($meta->id)) {
		print("1<br />");
		return FALSE;
	}

	// Read the default values
	$defaults = json_decode(@file_get_contents("$path/defaults.json"));
	if ($defaults === NULL || !isset($defaults->configurations) || !isset($defaults->rights) || !isset($defaults->protected)) {
		print("2<br />");
		return FALSE;
	}

	// Create the configurations
	foreach ($defaults->configurations as $name => $value) {
		$full_name = $sql->quote($meta->id . "." . $name);
		$result = $sql->exec("INSERT INTO {$db_prefix}config VALUES ($full_name, " . $sql->quote($value) . ")");
		if ($result === FALSE) {
			print("3<br />");
			return FALSE;
		}
	}

	// List of all the groups
	$groups = [1, 2, 3];

	// Create the rights
	foreach ($defaults->rights as $name => $right) {
		$full_name = $sql->quote($meta->id . "." . $name);
		// Get the protection
		$protection = [0, 0, 0];
		if (isset($defaults->protected->$name)) {
			$protection = $defaults->protected->$name;
		}
		// Create the rights for every groups
		$list = "";
		foreach ($groups as $group) {
			$list .= "($full_name, $group, " . ($group <= 3 ? $right[$group - 1] : $right[1]) . ", " . ($group <= 3 ? $protection[$group - 1] : $protection[1]) . "),";
		}
		$result = $sql->exec("INSERT INTO {$db_prefix}rights VALUES " . substr($list, 0, -1));
		if ($result === FALSE) {
			print("4: INSERT INTO {$db_prefix}rights VALUES " . substr($list, 0, -1) . "<br />");
			return FALSE;
		}
	}

	// Create the directories
	$result = @mkdir(OMMP_ROOT . "/data/$meta->id", 0777, TRUE);
	if ($result === FALSE) {
		print("5<br />");
		return FALSE;
	}

	// Register the module in the database
	$result = $sql->exec("INSERT INTO {$db_prefix}modules VALUES (NULL, '$meta->id', 1, $priority)");
	if ($result === FALSE) {
		print("6<br />");
		return FALSE;
	}

	// Success
	return TRUE;

}

/**
 * Set a configuration in the database
 * 
 * @param string $name
 * 		The name of the configuration
 * @param string $value
 * 		The value of the configuration
 * 
 * @return boolean
 * 		TRUE if success
 * 		FALSE else
 */
function set_config($name, $value) {
	global $sql, $db_prefix;
	return $sql->exec("UPDATE {$db_prefix}config SET `value` = " . $sql->quote($value) . " WHERE `name` = " . $sql->quote($name)) !== FALSE;
}

/**
 * Generates a random string
 * 
 * @source
 *      https://stackoverflow.com/a/4356295
 */
function random_str($length=32, $characters='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}