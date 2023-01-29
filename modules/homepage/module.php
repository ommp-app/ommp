<?php
/**
 * Online Module Management Platform
 * 
 * Main file for homepage module
 * Contains the required function to allow the module to work
 * 
 * @author  The OMMP Team
 * @version 1.0
 */

/**
 * Check a configuration value
 * 
 * @param string $name
 *      The configuration name (without the module name)
 * @param string $value
 *      The configuration value
 * @param Lang $lang
 *         The Lang object for the current module
 * 
 * @return boolean|string
 *      TRUE is the value is correct for the given name
 *      else a string explaination of the error
 */
function homepage_check_config($name, $value, $lang) {
    if ($name == "custom_homepage" || $name == "hide_navbar") {
        if ($value !== "0" && $value !== "1") {
            return $lang->get('value_0_or_1');
        }
        return TRUE;
    }
}

/**
 * Handle user deletion calls
 * This function will be called by the plateform when a user is deleted,
 * it must delete all the data relative to the user
 * 
 * @param int $id
 *         The id of the user that will be deleted
 */
function homepage_delete_user($id) {
    // This module does not interracts with the user
}

/**
 * Handle an API call
 * 
 * @param string $action
 *      The name of the action to process
 * @param array $data
 *      The data given with the action
 * 
 * @return array|boolean
 *      An array containing the data to respond
 *      FALSE if the action does not exists
 */
function homepage_process_api($action, $data) {
    global $user;
    
    // Handle the different actions

    if ($action == "update-content") {

		// Check the parameters
        if (!check_keys($data, ["content"])) {
            return ["error" => $user->module_lang->get("missing_parameter")];
        }

		// Check the right
		if (!$user->has_right("homepage.allow_edit")) {
			return ["error" => $user->module_lang->get("missing_right")];
		}

		// Update the content
		$result = @file_put_contents(OMMP_ROOT . "/data/homepage/custom.html", $data['content']);

		// Check for error
		if ($result === FALSE) {
			return ["error" => $user->module_lang->get("save_error")];
		}

		// Return success
		return ["ok" => TRUE];

	}

    return FALSE;
}

/**
 * Handle page loading for the module
 * 
 * @param string $page
 *      The page requested in the module
 * @param string $pages_path
 *      The absolute path where the pages are stored for this module
 * 
 * @return array|boolean
 *      An array containing multiple informations about the page as described below
 *      [
 *          "content" => The content of the page,
 *          "title" => The title of the page,
 *          "og_image" => The Open Graph image (optional),
 *          "description" => A description of the web page,
 * 			"navbar" => Should we display the navbar (optional, default is TRUE)
 *      ]
 *      FALSE to generate a 404 error
 */
function homepage_process_page($page, $pages_path) {
    global $user, $config;

    // This module has only one page
    if ($page != "") {
        return FALSE;
    }

	// Check if we must display the default homepage
	if ($config->get("homepage.custom_homepage") == "0") {

		// List active modules
		$modules = "";
		foreach ($user->accessible_modules() as $module) {
			// Do not display current module
			if ($module == "homepage") {
				continue;
			}
			// Get template
			$module_lang = module_get_lang($module);
			$modules .= page_read_module($pages_path . "button.html", [
				"module" => htmlvarescape($module),
				"module_name" => htmlvarescape($module_lang->get("@module_name"))
			]);
		}

		// Return the page to display
		return [
			"content" => page_read_module($pages_path . "index.html", [
				"modules" => $modules
			]),
			"title" => $user->module_lang->get("home"),
			"navbar" => $config->get("homepage.hide_navbar") == "0"
		];

	} else {

		// Read the custom page
		$content = @file_get_contents(OMMP_ROOT . "/data/homepage/custom.html");

		// Return the page to display
		return [
			"content" => page_read_module($pages_path . "custom.html", [
				"edit" => $user->has_right("homepage.allow_edit") ? page_read_module($pages_path . "edit.html", ["escaped_content" => htmlvarescape($content)]) : "",
				"content" => $content
			]),
			"title" => $user->module_lang->get("home"),
			"navbar" => $config->get("homepage.hide_navbar") == "0"
		];

	}

}

/**
 * Handle the special URL pages
 * 
 * @param string $url
 *      The url to check for a special page
 * 
 * @return boolean
 *      TRUE if this module can process this url (in this case this function will manage the whole page display)
 *      FALSE else (in this case, we will check the url with the remaining modules, order is defined by module's priority value)
 */
function homepage_url_handler($url) {
    // This module does not have special URL
    return FALSE;
}