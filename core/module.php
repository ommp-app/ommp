<?php
/**
 * Online Module Management Platform
 * 
 * Contains the fonctions to manage, load and execute a module
 * 
 * @author  The OMMP Team
 * @version 1.0 
 */

/**
 * Execute a module for a given page
 * 
 * @param string $name
 *      The name of the module
 * @param string $page
 *      The page of the module
 * @param boolean $check
 *      Should we check if the module is enabled
 *      Optional, TRUE by default
 * 
 * @return boolean
 *      TRUE if execution is successful
 *      FALSE else
 */
function exec_module($name, $page, $check=TRUE) {
    global $user, $config;

    // Check if the module is enabled
    if ($check && !module_is_enabled($name)) {
        page_404_error();
        return FALSE;
    }

    // Check if user has the right to access this module
    if (!$user->has_right("{$name}.use")) {
        page_404_error();
        return FALSE;
    }

    // Get informations about the module
    $module_path = module_get_path($name);
    
    // Load the module language based on user's language
    $user->module_lang = module_get_lang($name);

    // Load the module's functions
    require_once "{$module_path}module.php";

    // Get the page content from the module
    $content = call_user_func("{$name}_process_page", $page, "{$module_path}pages/");

    // Check for 404
    if ($content === FALSE) {
        page_404_error();
        return FALSE;
    }

    // If method used is POST, check the session key hash
    if ($_SERVER['REQUEST_METHOD'] == "POST") {
        if (!isset($_POST['skh']) || $_POST['skh'] != $user->session_key_hmac) {
            page_error("{L:WRONG_SKH}", "{L:WRONG_SKH_EXPLAIN}");
            return;
        }
    }

    // Displays the page
    page_display(
        $content['content'],
        $config->get("ommp.name") . " - " . $content['title'],
        [],
        isset($content['og_image']) ? $content['og_image'] : NULL,
        isset($content['description']) ? $content['description'] : NULL,
		isset($content['navbar']) ? $content['navbar'] : TRUE
    );

    return TRUE;

}

/**
 * Redirect an API call to the module's function
 * 
 * @param string $page
 *      The page containing the module name and action
 */
function module_api($page) {
    global $user;

    // Check the session key hmac
    if (!isset($_POST['skh']) || $_POST['skh'] != $user->session_key_hmac) {
        output_json(["error" => $user->lang->get("wrong_skh")]);
    }

    // Get the module name and api action
    $module_name = $page;
    $api_action = "";
    $slash_pos = strpos($module_name, "/");
    if ($slash_pos !== FALSE) {
        $api_action = substr($module_name, $slash_pos + 1);
        $second_slash_pos = strpos($api_action, "/");
        if ($second_slash_pos !== FALSE) {
            $api_action = substr($module_name, 0, $second_slash_pos);
        }
        $module_name = substr($module_name, 0, $slash_pos);
    }

    // Check if the module is enabled and if user can access it
    if (!module_is_enabled($module_name) || !$user->has_right("{$module_name}.use")) {
        output_json(["error" => $user->lang->get("module_disabled")]);
    }

    // Load the module language based on user's language
    $user->module_lang = module_get_lang($module_name);

    // Load the module's functions
    require_once module_get_path($module_name) . "module.php";

    // Call the api function
    $response = call_user_func("{$module_name}_process_api", $api_action, $_POST);

    // Check if the action exists
    if ($response === FALSE) {
        output_json(["error" => $user->lang->get("action_does_not_exists")]);
    }

    // Send the response
    output_json($response);

}

/**
 * Make an internal call to an API action for a specific module
 * Note that this function emulates an API call but does not perform any HTTP request
 * 
 * @param string $module_name
 * 		The name of the module we want to call
 * @param string $api_action
 * 		The name of the action we want to call
 * @param array $data
 * 		The parameters that needs to be passed to the API
 * 		You don't need to provide the session key hmac
 * 
 * @return array
 * 		The array representing the JSON as returned by the API call
 */
function module_api_internal_call($module_name, $api_action, $data) {

	global $user;

    // Add the session key hmac (just in case some API uses it, but it is not recomended)
    $data['skh'] = $user->session_key_hmac;

    // Check if the module is enabled and if user can access it
    if (!module_is_enabled($module_name) || !$user->has_right("{$module_name}.use")) {
        return ["error" => $user->lang->get("module_disabled")];
    }

	// Saves the current module lang
	$current_module_lang = $user->module_lang;

    // Load the module language based on user's language
    $user->module_lang = module_get_lang($module_name);

    // Load the module's functions
    require_once module_get_path($module_name) . "module.php";

    // Call the api function
    $response = call_user_func("{$module_name}_process_api", $api_action, $data);

    // Check if the action exists
    if ($response === FALSE) {
        return ["error" => $user->lang->get("action_does_not_exists")];
    }

	// Reset the module lang
	$user->module_lang = $current_module_lang;

    // Return the response
    return $response;

}

/**
 * Displays a module's media
 * 
 * @param string $media
 *      The media requested
 * @param boolean $prepare
 * 		Should we prepare the media before returning it?
 * 		Will work only for text files
 */
function module_media($media, $prepare) {
    global $user;

    // Get the module name
    $module_name = $media;
    $media_page = "";
    $slash_pos = strpos($module_name, "/");
    if ($slash_pos !== FALSE) {
        $media_page = substr($module_name, $slash_pos + 1);
        $module_name = substr($module_name, 0, $slash_pos);
    }

    // Check if the module is enabled and if user can access it
    if (!module_is_enabled($module_name) || !$user->has_right("{$module_name}.use_media")) {
        page_404_error();
        return;
    }

    // Get the module's media path
    $module_media_path = OMMP_ROOT;
    if (is_core_module($module_name)) {
        $module_media_path .= "/core/modules/$module_name/" . ($prepare ? "prepared_" : "") . "media/";
    } else {
        $module_media_path .= "/modules/$module_name/" . ($prepare ? "prepared_" : "") . "media/";
    }
    $full_path = $module_media_path . $media_page;

    // Check if the file exists
    if (!file_exists($full_path) || is_dir($full_path)) {
        page_404_error();
        return;
    }

    // Set the mime type header
    $mime = better_mime_type($full_path);
    header("Content-type: $mime");

    // Set the cache control
	if ($prepare) {
		headers_no_cache();
	} else {
		headers_cache();
	}

	if ($prepare) {

		// Prepare the file before displaying it
		$content = file_get_contents($full_path);
		$content = prepare_html($content, module_get_lang($module_name));
		print($content);

	} else {

		// Read the file
		readfile($full_path);

	}

}

/**
 * Print the headers to indicate to the brwoser to cache this file
 * The cache duration is defined by config "ommp.cache_lifetime"
 */
function headers_cache() {
	global $config;
	$seconds_to_cache = intval($config->get('ommp.cache_lifetime'));
	$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
	header("Expires: $ts");
	header("Pragma: cache");
	header("Cache-Control: max-age=$seconds_to_cache");
}

/**
 * Set headers to indicate to the browser not to cache the file
 */
function headers_no_cache() {
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
	header('Expires: Tue, 17 Sep 1996 00:00:00 GMT');
}

/**
 * Check for all module if they an handle a special URL
 * 
 * @param string $url
 * 		The URL to handle
 * 
 * @return boolean
 * 		TRUE if a module has handled the URL
 * 		FALSE else
 */
function module_special_url($url) {
	global $sql, $db_prefix, $user;

	// Get the list of the modules in priority order
	$request = $sql->query("SELECT `name` FROM {$db_prefix}modules WHERE `enabled` = 1 ORDER BY priority ASC");
	while ($module = $request->fetch()) {

		// Load the module language based on user's language
		$user->module_lang = module_get_lang($module['name']);

		// Load the module's functions
		require_once module_get_path($module['name']) . "module.php";
	
		// Call the URL handler function
		$response = call_user_func("{$module['name']}_url_handler", $url);
	
		// Check if the action exists
		if ($response) {
			$request->closeCursor();
			return TRUE;
		}

	}
	$request->closeCursor();

	// Return FALSE
	return FALSE;

}

/**
 * Handle pages management for modules that doesn't want to pre-process its pages
 * 
 * @param string $page
 *      The page requested in the module
 * @param string $pages_path
 *      The absolute path where the pages are stored for this module
 * @param array $variables
 *      The variables to use in the page
 *      Optional, default is an empty array
 * @param array|null $titles
 *      An array associating the pages name to the title to display
 *      Optional, NULL to use the module name
 * 
 * @return array|boolean
 *      An array containing multiple informations about the page as described below
 *      [
 *          "content" => The content of the page,
 *          "title" => The title of the page,
 *          "og_image" => The Open Graph image (optional),
 *          "description" => A description of the web page
 *      ]
 *      FALSE to generate a 404 error
 */
function module_simple_html($page, $pages_path, $variables=[], $titles=NULL) {
    global $user;

    // Get the HTML file path
    $file = $pages_path . $page;
    if (is_dir($file)) {
        $file .= (substr($file, -1) != "/" ? "/" : "") . "index.html";
    }

    // Check if the file exists
    if (!file_exists($file)) {
        if (file_exists($file . ".html")) {
            $file .= ".html";
        } else {
            return FALSE;
        }
    }

	// Get the title
	$title = $user->module_lang->get("@module_name"); // Default title
	if ($titles !== NULL) {

		// Add the ".html" and "/" titles if needed
		foreach ($titles as $title => $value) {
			if (!isset($titles["$title.html"])) {
				$titles["$title.html"] = $titles[$title];
			}
			if (!isset($titles["$title/"])) {
				$titles["$title/"] = $titles[$title];
			}
		}

		// Check if is set
		if (isset($titles[$page])) {
			$title = $titles[$page];
		} else if (endsWith($page, "index.html") && isset($titles[substr($page, 0, -10)]))  {
			$title = $titles[substr($page, 0, -10)];
		} else if (endsWith($page, "index") && isset($titles[substr($page, 0, -5)]))  {
			$title = $titles[substr($page, 0, -5)];
		}

	}

    // Read the file
    return [
        "content" => page_read_module($file, $variables),
        "title" => $title
    ];
}

/**
 * Get the module's path
 * 
 * @param string $name
 *      The name of the module
 * 
 * @return string
 *      The absolute path where the module is located
 */
function module_get_path($name) {
    return OMMP_ROOT . (is_core_module($name) ? "/core" : "") . "/modules/$name/";
}

/**
 * Get metadata informations for a module
 * 
 * @param string $name
 *      The name of the module
 * 
 * @return stdClass
 *      The object representing the module's metadata
 */
function module_get_metadata($name) {
    return json_decode(file_get_contents(module_get_path($name) . "meta.json"));
}

/**
 * Get the Lang object for a given module
 * 
 * @param string $name
 *      The name of the module
 * 
 * @return Lang
 *      The lang object corresponding to the given module
 *      The language loaded is the current user's language or the default module language if not found 
 */
function module_get_lang($name) {
    global $user;

    // Load the module language based on user's language
    $module_lang = new Lang($user->lang->current_language(), $name);

    // Check if the user's language exists in the module
    if ($module_lang->is_loaded()) {
        return $module_lang;
    }

    // If not, loads the default module language
    $module_meta = module_get_metadata($name);
    return new Lang($module_meta->default_language, $name);
}

/**
 * Check if a module is a core module
 * 
 * @param string $name
 *      The name of the module to check
 * 
 * @return boolean
 *      TRUE if the module is a core module (but not necessarily installed)
 *      FALSE else
 */
function is_core_module($name) {
    $path = OMMP_ROOT . "/core/modules/$name/";
    return file_exists($path) && is_dir($path);
}

/**
 * Check if a module exists
 * 
 * @param string $name
 *      The name of the module
 * 
 * @return boolean
 *      TRUE if the module exists (but not necessarily installed or enabled)
 *      FALSE else
 */
function module_exists($name) {
    $path = OMMP_ROOT . "/modules/$name/";
    return (file_exists($path) && is_dir($path)) || is_core_module($name); 
}

/**
 * Check if a module is installed
 * 
 * @param string $name  
 *      The name of the module
 * 
 * @return boolean
 *      TRUE if the module is installed (but not necessarily enabled)
 *      FALSE else
 */
function module_is_installed($name) {
    global $db_prefix;
    return dbSearchValue("{$db_prefix}modules", "name", $name);
}

/**
 * Check if a module is enabled
 * 
 * @param string $name
 *      The name of the module
 * 
 * @return boolean
 *      TRUE if the module is enabled
 *      FALSE esle
 */
function module_is_enabled($name) {
    global $sql, $db_prefix;
    // Note that an installed core module is always considered as enabled
    return (is_core_module($name) && module_is_installed($name)) || dbExists("{$db_prefix}modules", "enabled = TRUE AND name = " . $sql->quote($name));
}

/**
 * Install a module
 * 
 * @param string $zip_file
 * 		The path where the zip file to install is stored
 * 
 * @return string|boolean
 * 		TRUE if the module installation is successful
 * 		An error message as a string in case of failure
 */
function module_install($zip_file) {
	global $user, $sql, $db_prefix;

	// Unzip the file
	$output_dir = OMMP_TEMP_DIR . "/module_install_" . rand();
	mkdir($output_dir, 0777, TRUE);
	$zip = new ZipArchive;
	if ($zip->open($zip_file) !== TRUE || $zip->extractTo($output_dir) !== TRUE) {
		unlink($zip_file);
		rrmdir($output_dir);
		return $user->lang->get('cannot_open_zip');
	}
	
	// Remove temp file
	$zip->close();
	unlink($zip_file);

	// Read metadata
	$meta = json_decode(@file_get_contents("$output_dir/meta.json"));
	if ($meta === NULL || !isset($meta->id)) {
		rrmdir($output_dir);
		return $user->lang->get('cannot_read_metadata');
	}

	// Check module id
	if (!ctype_alnum(str_replace("_", "", $meta->id))) {
		rrmdir($output_dir);
		return $user->lang->get('wrong_id_format');
	}

	// Check if module is already installed
	if (module_is_installed($meta->id)) {
		rrmdir($output_dir);
		return $user->lang->get('module_already_installed');
	}

	// Check module version
	if (!isset($meta->requirement) || $meta->requirement > OMMP_VERSION) {
		rrmdir($output_dir);
		return $user->lang->get('wrong_ommp_version');
	}
	
	// Create the tables for the module
	$sql_content = str_replace("{PREFIX}", $db_prefix, @file_get_contents("$output_dir/install.sql"));
	$result = $sql->exec($sql_content);
	if ($result === FALSE) {
		rrmdir($output_dir);
		return $user->lang->get('cannot_create_tables');
	}

	// Read the default values
	$defaults = json_decode(@file_get_contents("$output_dir/defaults.json"));
	if ($defaults === NULL || !isset($defaults->configurations) || !isset($defaults->rights) || !isset($defaults->protected)) {
		rrmdir($output_dir);
		return $user->lang->get('cannot_read_defaults');
	}

	// Create the configurations
	foreach ($defaults->configurations as $name => $value) {
		$full_name = $sql->quote($meta->id . "." . $name);
		$result = $sql->exec("INSERT INTO {$db_prefix}config VALUES ($full_name, " . $sql->quote($value) . ")");
		if ($result === FALSE) {
			$sql->exec("DELETE FROM {$db_prefix}config WHERE `name` LIKE '$meta->id\\.%'");
			$sql->exec(str_replace("{PREFIX}", $db_prefix, @file_get_contents("$output_dir/uninstall.sql")));
			rrmdir($output_dir);
			return $user->lang->get('cannot_create_config');
		}
	}

	// Get the list of all the groups
	$groups = [];
	$request = $sql->query("SELECT id FROM {$db_prefix}groups");
	while ($group = $request->fetch()) {
		$groups[] = $group['id'];
	}
	$request->closeCursor();

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
			$sql->exec("DELETE FROM {$db_prefix}config WHERE `name` LIKE '$meta->id\\.%'");
			$sql->exec("DELETE FROM {$db_prefix}rights WHERE `name` LIKE '$meta->id\\.%'");
			$sql->exec(str_replace("{PREFIX}", $db_prefix, @file_get_contents("$output_dir/uninstall.sql")));
			rrmdir($output_dir);
			return $user->lang->get('cannot_create_right');
		}
	}

	// Create the directories
	$result = @mkdir(OMMP_ROOT . "/data/$meta->id", 0777, TRUE);
	if ($result === FALSE) {
		$sql->exec("DELETE FROM {$db_prefix}config WHERE `name` LIKE '$meta->id\\.%'");
		$sql->exec("DELETE FROM {$db_prefix}rights WHERE `name` LIKE '$meta->id\\.%'");
		$sql->exec(str_replace("{PREFIX}", $db_prefix, @file_get_contents("$output_dir/uninstall.sql")));
		rrmdir($output_dir);
		return $user->lang->get('cannot_create_data_dir');
	}

	// Move module content
	$final_dir = OMMP_ROOT . "/modules/$meta->id";
	$result = @rename($output_dir, $final_dir);
	if ($result === FALSE) {
		$sql->exec("DELETE FROM {$db_prefix}config WHERE `name` LIKE '$meta->id\\.%'");
		$sql->exec("DELETE FROM {$db_prefix}rights WHERE `name` LIKE '$meta->id\\.%'");
		$sql->exec(str_replace("{PREFIX}", $db_prefix, @file_get_contents("$output_dir/uninstall.sql")));
		rrmdir(OMMP_ROOT . "/data/$meta->id");
		rrmdir($output_dir);
		return $user->lang->get('cannot_move_files');
	}

	// Create the module in the database
	$count = dbCount("{$db_prefix}modules");
	$result = $sql->exec("INSERT INTO {$db_prefix}modules VALUES (NULL, '$meta->id', 1, $count)");
	if ($result === FALSE) {
		$sql->exec("DELETE FROM {$db_prefix}config WHERE `name` LIKE '$meta->id\\.%'");
		$sql->exec("DELETE FROM {$db_prefix}rights WHERE `name` LIKE '$meta->id\\.%'");
		$sql->exec(str_replace("{PREFIX}", $db_prefix, @file_get_contents("$final_dir/uninstall.sql")));
		rrmdir(OMMP_ROOT . "/data/$meta->id");
		rrmdir($final_dir);
		return $user->lang->get('cannot_register_module');
	}

	return TRUE;

}

/**
 * Uninstall a module
 * 
 * @param string $name
 * 		The name of the module to uninstall
 */
function module_uninstall($name) {
	global $sql, $db_prefix;
	$module_path = OMMP_ROOT . "/modules/$name";

	// Get module priority
	$priority = intval(dbGetFirstLineSimple("{$db_prefix}modules", "`name` = " . $sql->quote($name), "priority", TRUE));

	// Remove from database
	$sql->exec("DELETE FROM {$db_prefix}modules WHERE `name` = " . $sql->quote($name));
	$sql->exec("DELETE FROM {$db_prefix}config WHERE `name` LIKE " . $sql->quote("$name\\.%"));
	$sql->exec("DELETE FROM {$db_prefix}rights WHERE `name` LIKE " . $sql->quote("$name\\.%"));

	// Execute SQL uninstall script
	$sql->exec(str_replace("{PREFIX}", $db_prefix, @file_get_contents("$module_path/uninstall.sql")));

	// Re-order the modules priorities
	$sql->exec("UPDATE {$db_prefix}modules SET priority = priority - 1 WHERE priority > $priority");

	// Remove files
	rrmdir(OMMP_ROOT . "/data/$name");
	rrmdir($module_path);

}