<?php
/**
 * Online Module Management Platform
 * 
 * Main file for settings module
 * This module allow user to manage their account settings
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
 * 		The Lang object for the current module
 * 
 * @return boolean|string
 *      TRUE is the value is correct for the given name
 *      else a string explaination of the error
 */
function settings_check_config($name, $value, $lang) {
	// No settings for this module
	return TRUE;
}

/**
 * Handle user deletion calls
 * This function will be called by the plateform when a user is deleted,
 * it must delete all the data relative to the user
 * 
 * @param int $id
 * 		The id of the user that will be deleted
 */
function settings_delete_user($id) {
	// Nothing to do on user delete
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
function settings_process_api($action, $data) {
    global $sql, $db_prefix, $user, $config;

    if ($action == "get-user-informations") {

		// Get the groups names
		$groups_names = [];
		$request = $sql->query("SELECT `name` FROM {$db_prefix}groups WHERE id IN ($user->groups_sql)");
		while ($group = $request->fetch()) {
			$groups_names[] = prepare_html($group['name'], $user->lang);
		}
		$request->closeCursor();

		// Return the informations
		return [
			"ok" => TRUE,
			"user" => [
				"id" => $user->id,
				"lang" => $user->lang->current_language(),
				"lang_html" => $user->lang->supported_languages_HTML($user->lang->current_language(), "lang", "width:70%;display:inline-block;"),
				"username" => $user->username,
				"longname" => $user->longname,
				"email" => $user->email,
				"registration_time" => $user->registration_time,
				"formatted_registration" => date($user->module_lang->get("date_format"), $user->registration_time),
				"groups" => $user->groups,
				"groups_names" => $groups_names,
				"certified" => $user->certified,
				"certified_image" => $user->certification_html()
			]
		];

	} else if ($action == "get-rights") {
	
		// Return the rights for this user
		return [
			"ok" => TRUE,
			"rights" => [
				"change_username" => $user->has_right("settings.change_username"),
				"change_email" => $user->has_right("settings.change_email"),
				"change_name" => $user->has_right("settings.change_name"),
			]
		];
		
	} else if ($action == "update-user") {

		// Check the parameters
		if (!check_keys($data, ["property", "value"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Check if property is editable
		if (!in_array($data['property'], ["username", "longname", "email", "lang"])) {
			return ["error" => $user->module_lang->get("property_not_found")];
		}

		// Check if user has right to update it
		if (
			($data['property'] == "username" && !$user->has_right("settings.change_username"))
			|| ($data['property'] == "longname" && !$user->has_right("settings.change_name"))
			|| ($data['property'] == "email" && !$user->has_right("settings.change_email"))
		) {
			return ["error" => $user->module_lang->get("missing_right")];
		}

		// Check if username is correct
		if ($data['property'] == "username" && !User::check_username_format($data['value'])) {
			return ["error" => $user->module_lang->get("wrong_username_format")];
		}

		// Check if username is available
		if ($data['property'] == "username" && User::username_taken($data['value'])) {
			return ["error" => $user->module_lang->get("username_taken")];
		}

		// Check if longname is correct
		if ($data['property'] == "longname" && strlen($data['value']) > 50) {
			return ["error" => $user->module_lang->get("longname_too_long")];
		}

		// Check email
		if ($data['property'] == "email" && !filter_var($data['value'], FILTER_VALIDATE_EMAIL)) {
            return ["error" => $user->module_lang->get("invalid_email")];
        }

        // Check the language
        if ($data['property'] == "lang" && !$user->lang->check_supported_language($data['value'])) {
            return ["error" => $user->module_lang->get("wrong_lang")];
        }

		// Update the property
		$result = $sql->exec("UPDATE {$db_prefix}users SET $data[property] = " . $sql->quote($data['value']) . " WHERE id = $user->id");

		// Check for errors
		if ($result === FALSE) {
            return ["error" => $user->module_lang->get("cannot_update_user")];
        }

		// Return success
		return ["ok" => TRUE];

	} else if ($action == "update-password") {

		// Check the parameters
		if (!check_keys($data, ["current", "new", "confirm"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Check the current pass
		if (hash("sha256", $data['current']) != $user->password) {
			return ["error" => $user->module_lang->get("wrong_password")];
		}

		// Check confirmation match
		if ($data['new'] != $data['confirm']) {
			return ["error" => $user->module_lang->get("wrong_confirmation")];
		}

		// Check password
		if (strlen($data['new']) < 8) {
			return ["error" => $user->module_lang->get("pass_too_short")];
		}

		// Update password
		$result = $sql->exec("UPDATE {$db_prefix}users SET `password` = " . $sql->quote(hash("sha256", $data['new'])) . " WHERE id = $user->id");

		// Check for errors
		if ($result === FALSE) {
            return ["error" => $user->module_lang->get("cannot_update_user")];
        }

		// Return success
		return ["ok" => TRUE];

	} else if ($action == "logout") {

		// Delete session
		$sql->exec("DELETE FROM {$db_prefix}sessions WHERE user_id  = $user->id AND session_key = " . $sql->quote($user->session_key));

		// Delete the cookies
		delete_ommp_cookie($config->get('ommp.cookie_user'));
		delete_ommp_cookie($config->get('ommp.cookie_session'));

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
 *          "description" => A description of the web page
 *      ]
 *      FALSE to generate a 404 error
 */
function settings_process_page($page, $pages_path) {
    global $user;
    // This module uses only the HTML files without processing them
    return module_simple_html($page, $pages_path, [], [
        "" => $user->module_lang->get("settings")
    ]);
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
function settings_url_handler($url) {
    // This module does not have special URL
    return FALSE;
}