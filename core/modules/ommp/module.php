<?php
/**
 * Online Module Management Platform
 * 
 * Main file for OMMP module
 * This module allow user to administrate it's platform
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
function ommp_check_config($name, $value, $lang) {

	// Booleans
	if ($name == "homepage_in_menu") {
		if ($value !== "0" && $value !== "1") {
			return $lang->get('value_0_or_1');
		}
		return TRUE;
	}

	// Positive integers
    if (in_array($name, ["cache_lifetime", "session_duration"])) {
		if (!ctype_digit($value)) {
			return $lang->get('must_be_positive_integer');
		}
		return TRUE;
	}

	// Email
	if (in_array($name, ["contact_email", "mail_sender"])) {
		if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
			return $lang->get('invalid_email');
		}
		return TRUE;
	}

	// Domain name or ip address
	if ($name == "domain") {
		if (!(filter_var($value, FILTER_VALIDATE_DOMAIN) || filter_var($value, FILTER_VALIDATE_IP))) {
			return $lang->get('invalid_domain_or_ip');
		}
		return TRUE;
	}

	// Enabled module
	if ($name == "homepage") {
		if (!module_is_enabled($value)) {
			return $lang->get('invalid_module');
		}
		return TRUE;
	}

	// Path
	if ($name == "dir") {
		if (substr($value, 0, 1) != "/" || substr($value, -1) != "/") {
			return $lang->get("path_wrong_start_end");
		}
		return TRUE;
	}

	// Protocol
	if ($name == "scheme") {
		if (!in_array($value, ["http", "https"])) {
			return $lang->get("wrong_scheme");
		}
		return TRUE;
	}

	// All other values are free text, so we accept them
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
function ommp_delete_user($id) {
	global $sql, $db_prefix;

	// Delete user from the groups
	$sql->exec("DELETE FROM {$db_prefix}groups_members WHERE user_id = " . $id);

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
function ommp_process_api($action, $data) {
    global $sql, $db_prefix, $user, $config;

    if ($action == "get-modules") {

        // Get all the installed modules
        $result = [];
        $query = $sql->query("SELECT * FROM {$db_prefix}modules ORDER BY priority");
        while ($module = $query->fetch()) {
            // Load module lang to get it's name
            $module_lang = module_get_lang($module['name']);
            // Save the result
            $result[$module['name']] = [
                "name" => $module_lang->get("@module_name"),
                "description" => $module_lang->get("@module_description"),
				"id" => $module['id'],
				"enabled" => $module['enabled'] == "1",
				"priority" => intval($module['priority']),
				"core" => is_core_module($module['name'])
            ];
        }
        $query->closeCursor();

        // Return the data
        return [
			"ok" => TRUE,
			"modules" => $result
		];

    } else if ($action == "get-rights") {

        $result = [];

        // Get all the rights
        $query = $sql->query("SELECT `name`, group_id, `value`, protected FROM {$db_prefix}rights ORDER BY `name`");
        while ($right = $query->fetch()) {

            // Get the module name
            $name = substr($right['name'], 0, strpos($right['name'], "."));

            // Create the right if it does not exists
            if (!isset($result[$right['name']])) {

                // Get the name and description
                if ($right['name'] == "$name.use") {
                    $name = $user->lang->get("@default_right.use#name");
                    $desc = $user->lang->get("@default_right.use#desc");
                } else if ($right['name'] == "$name.use_media") {
                    $name = $user->lang->get("@default_right.use_media#name");
                    $desc = $user->lang->get("@default_right.use_media#desc");
                } else {
                    $module_lang = module_get_lang($name);
                    $name = $module_lang->get($right['name'] . "#name");
                    $desc = $module_lang->get($right['name'] . "#desc");
                }

                // Create the right with name and description
                $result[$right['name']] = [
                    "name" => $name,
                    "description" => $desc,
                    "values" => [],
                    "protections" => []
                ];
            }

            // Add the right
            $result[$right['name']]['values'][$right['group_id']] = $right['value'] == "1";
            $result[$right['name']]['protections'][$right['group_id']] = $right['protected'] == "1";

        }
        $query->closeCursor();

        // Return the data
        return $result;

    } else if ($action == "get-groups") {

        // Get all the groups
        $result = [];
        $query = $sql->query("SELECT id, `name`, `description` FROM {$db_prefix}groups");
        while ($group = $query->fetch()) {
            $result[$group['id']] = [
                "name" => prepare_html($group['name'], $user->lang),
                "description" => prepare_html($group['description'], $user->lang),
				"raw_name" => $group['name'],
				"raw_description" => $group['description']
            ];
        }
        $query->closeCursor();

        // Return the data
        return $result;

    } else if ($action == "update-right") {

        // Check the parameters
        if (!check_keys($data, ["right", "value", "group"])) {
            return ["error" => $user->module_lang->get("missing_parameter")];
        }

        // Update the right
        $value = $data['value'] == "true";
        $result = $sql->exec("UPDATE {$db_prefix}rights SET `value` = " . ($value ? "TRUE" : "FALSE") . " WHERE NOT protected AND `name` = " . $sql->quote($data['right']) . " AND group_id = " . $sql->quote($data['group']));

        // Check for error
        if ($result === FALSE || $result === 0) {
            return ["error" => $user->module_lang->get("cannot_update_right")];
        }

        // Return the new state
        return ["new_state" => $value];

    } else if ($action == "update-protection") {

        // Check the parameters
        if (!check_keys($data, ["right", "value", "group"])) {
            return ["error" => $user->module_lang->get("missing_parameter")];
        }

        // Update the protection
        $value = $data['value'] == "true";
        $result = $sql->exec("UPDATE {$db_prefix}rights SET `protected` = " . ($value ? "TRUE" : "FALSE") . " WHERE `name` = " . $sql->quote($data['right']) . " AND group_id = " . $sql->quote($data['group']));

        // Check for error
        if ($result === FALSE || $result === 0) {
            return ["error" => $user->module_lang->get("cannot_update_protection")];
        }

        // Return the new state
        return ["new_protection" => $value];

    } else if ($action == "get-group-members") {

		// Check the parameter
		if (!check_keys($data, ["group"])) {
            return ["error" => $user->module_lang->get("missing_parameter")];
        }

		// Check if group exists
		if (!dbSearchValue("{$db_prefix}groups", "id", $data['group'])) {
			return ["error" => $user->module_lang->get("group_does_not_exists")];
		}

		// Get the members
		$members = [];
		$request = $sql->query("SELECT id, username, longname FROM {$db_prefix}groups_members, {$db_prefix}users WHERE group_id = " . $sql->quote($data['group']) . " AND `user_id` = id");
		while ($member = $request->fetch()) {
			$members[] = $member;
		}
		$request->closeCursor();

		// Return the members
		return $members;

	} else if ($action == "remove-member") {

		// Check the parameters
        if (!check_keys($data, ["user", "group"])) {
            return ["error" => $user->module_lang->get("missing_parameter")];
        }

		// Check if user exists
		$requiredUser = new User(intval($data['user']));
		if ($requiredUser->id == 0) {
			return ["error" => $user->module_lang->get("user_does_not_exists")];
		}

		// Escape values
		$escaped_user = $sql->quote($data['user']);
		$escaped_group = $sql->quote($data['group']);

		// Check if user is in the group
		if (!dbExists("{$db_prefix}groups_members", "group_id = $escaped_group AND `user_id` = $escaped_user")) {
			return ["error" => $user->module_lang->get("user_not_in_group")];
		}

		// Check if we want to remove the last administrator
		if ($data['group'] == "1" && dbCount("{$db_prefix}groups_members", "group_id = 1 AND `user_id` != $escaped_user") == 0) {
			return ["error" => $user->module_lang->get("too_few_admin")];
		}

		// Check if the user has at least one other group
		if (dbCount("{$db_prefix}groups_members", "`user_id` = $escaped_user AND group_id != $escaped_group") == 0) {
			return ["error" => $user->module_lang->get("user_must_have_group")];
		}

		// Remove the user from the group
		$result = $sql->exec("DELETE FROM {$db_prefix}groups_members WHERE group_id = $escaped_group AND `user_id` = $escaped_user");

		// Check for errors
		if ($result === FALSE || $result === 0) {
            return ["error" => $user->module_lang->get("cannot_remove_member")];
        }

        // Return success and member informations
        return ["ok" => TRUE, "user" => ["username" => $requiredUser->username, "longname" => $requiredUser->longname, "id" => $requiredUser->id]];

	} else if ($action == "get-all-members") {

		// Get all the members with their groups
		$request = $sql->query("SELECT id, username, longname, group_id FROM {$db_prefix}users, {$db_prefix}groups_members WHERE id = `user_id`");
		$members = [];
		while ($member = $request->fetch()) {
			if (!isset($members[$member['id']])) {
				$members[$member['id']] = [
					"username" => $member['username'],
					"longname" => $member['longname'],
					"groups" => []
				];
			}
			$members[$member['id']]['groups'][] = $member['group_id'];
		}
		$request->closeCursor();

		// Return the members
		return $members;

	} else if ($action == "add-user-to-group") {

		// Check the parameters
        if (!check_keys($data, ["username", "group"])) {
            return ["error" => $user->module_lang->get("missing_parameter")];
        }

		// Check if group exists
		if (!dbSearchValue("{$db_prefix}groups", "id", $data['group'])) {
			return ["error" => $user->module_lang->get("group_does_not_exists")];
		}

		// Check if user exists
		$requiredUser = new User($data['username']);
		if ($requiredUser->id == 0) {
			return ["error" => $user->module_lang->get("user_does_not_exists")];
		}

		// Escape values
		$escaped_group = $sql->quote($data['group']);

		// Check if user is in the group
		if (dbExists("{$db_prefix}groups_members", "group_id = $escaped_group AND `user_id` = $requiredUser->id")) {
			return ["error" => $user->module_lang->get("user_already_in_group")];
		}

		// Add user to group
		$result = $sql->exec("INSERT INTO {$db_prefix}groups_members VALUES ($escaped_group, $requiredUser->id)");

		// Check for errors
		if ($result === FALSE || $result === 0) {
            return ["error" => $user->module_lang->get("cannot_add_member")];
        }

        // Return success and informations about the added user
        return ["ok" => TRUE, "user" => ["username" => $requiredUser->username, "longname" => $requiredUser->longname, "id" => $requiredUser->id]];

	} else if ($action == "edit-group-infos") {

		// Check the parameters
        if (!check_keys($data, ["id", "name", "description"])) {
            return ["error" => $user->module_lang->get("missing_parameter")];
        }

		// Check if the group exists
		if (!dbSearchValue("{$db_prefix}groups", "id", $data['id'])) {
			return ["error" => $user->module_lang->get("group_does_not_exists")];
		}

		// Update the informations
		$result = $sql->exec("UPDATE {$db_prefix}groups SET `name` = " . $sql->quote($data['name']) . ", description = " . $sql->quote($data['description']) . " WHERE id = " . $sql->quote($data['id']));

		// Check for errors
		if ($result === FALSE) {
            return ["error" => $user->module_lang->get("cannot_edit_group")];
        }

        // Return success and new informations about group
        return [
			"ok" => TRUE,
			"infos" => [
				"name" => prepare_html($data['name'], $user->lang),
				"description" => prepare_html($data['description'], $user->lang),
				"raw_name" => $data['name'],
				"raw_description" => $data['description']
			]
		];

	} else if ($action == "create-group") {

		// Check the parameters
		if (!check_keys($data, ["name", "description", "template"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Check if the template exists
		if (!dbSearchValue("{$db_prefix}groups", "id", $data['template'])) {
			return ["error" => $user->module_lang->get("group_does_not_exists")];
		}

		// Create the group
		$result = $sql->exec("INSERT INTO {$db_prefix}groups VALUES (NULL, " . $sql->quote($data['name']) . ", " . $sql->quote($data['description']) . ")");

		// Check for errors
		if ($result === FALSE || $result === 0) {
            return ["error" => $user->module_lang->get("cannot_create_group")];
        }
		$id = $sql->lastInsertId();

		// Copy the permissions
		$request = $sql->query("SELECT `name`, `value`, protected FROM {$db_prefix}rights WHERE group_id = " . $sql->quote($data['template']));
		while ($right = $request->fetch()) {
			
			// Create the right
			$result = $sql->exec("INSERT INTO {$db_prefix}rights VALUES (" . $sql->quote($right['name']) . ", $id, " . $sql->quote($right['value']) . ", " . $sql->quote($right['protected']) . ")");
			
			// Check for error
			if ($result === FALSE || $result === 0) {
				
				// In case of error, remove the group and the created rights
				$sql->exec("DELETE FROM {$db_prefix}groups WHERE id = $id");
				$sql->exec("DELETE FROM {$db_prefix}rights WHERE group_id = $id");

				// Close cursor and return an error
				$request->closeCursor();
				return ["error" => $user->module_lang->get("cannot_create_group")];
				
			}

		}
		$request->closeCursor();

		// Return group informations
		return [
			"ok" => TRUE,
			"group" => [
				"id" => $id,
				"name" => prepare_html($data['name'], $user->lang),
				"description" => prepare_html($data['description'], $user->lang),
				"raw_name" => $data['name'],
				"raw_description" => $data['description']
			]
		];

	} else if ($action == "delete-group") {

		// Check the parameters
		if (!check_keys($data, ["id", "fallback"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Check if group is protected
		if (in_array($data['id'], ["1", "2", "3"])) {
			return ["error" => $user->module_lang->get("protected_group")];
		}

		// Check if the group exists
		if (!dbSearchValue("{$db_prefix}groups", "id", $data['id'])) {
			return ["error" => $user->module_lang->get("group_does_not_exists")];
		}

		// Check if the fallback exists and is different than itself
		if ($data['fallback'] == $data['id'] || !dbSearchValue("{$db_prefix}groups", "id", $data['fallback'])) {
			return ["error" => $user->module_lang->get("fallback_does_not_exists")];
		}

		// Remove the group
		$id = $sql->quote($data['id']);
		$result = $sql->exec("DELETE FROM {$db_prefix}groups WHERE id = $id");

		// Check for errors
		if ($result === FALSE || $result === 0) {
            return ["error" => $user->module_lang->get("cannot_delete_group")];
        }

		// Remove the rights
		$sql->exec("DELETE FROM {$db_prefix}rights WHERE group_id = $id");

		// Move the users
		$fallback = $sql->quote($data['fallback']);
		$request = $sql->query("SELECT user_id FROM {$db_prefix}groups_members WHERE group_id = $id");
		while ($member = $request->fetch()) {
			// Check if member is already in group
			if (!dbExists("{$db_prefix}groups_members", "group_id = $fallback AND user_id = $member[user_id]")) {
				// Add member to fallback
				$sql->exec("INSERT INTO {$db_prefix}groups_members VALUES ($fallback, $member[user_id])");
			}
		}
		$request->closeCursor();

		// Remove the members
		$sql->exec("DELETE FROM {$db_prefix}groups_members WHERE group_id = $id");

		// Return success
		return ["ok" => TRUE];

	} else if ($action == "get-configurations") {

		// Get the configurations
		$languages = [];
		$configs = [];
		$request = $sql->query("SELECT * FROM {$db_prefix}config ORDER BY `name`");
		while ($config = $request->fetch()) {

			// Get the translation of the module
			$module = substr($config['name'], 0, strpos($config['name'], "."));
			if (!isset($languages[$module])) {
				$languages[$module] = module_get_lang($module);
			}

			// Add the configuration and translations to the list
			$configs[] = [
				"raw_name" => $config['name'],
				"value" => $config['value'],
				"name" => $languages[$module]->get("$config[name]#name"),
				"description" => $languages[$module]->get("$config[name]#desc"),
				"raw_module" => $module,
				"module" => $languages[$module]->get("@module_name")
			];

		}
		$request->closeCursor();

		// Return result
		return ["ok" => TRUE, "configurations" => $configs];

	} else if ($action == "update-configuration") {

		// Check the parameters
		if (!check_keys($data, ["name", "value"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Check if the configuration exists
		if (!dbSearchValue("{$db_prefix}config", "name", $data['name'])) {
			return ["error" => $user->module_lang->get("config_not_found")];
		}

		// Check the value
		$module = substr($data['name'], 0, strpos($data['name'], "."));
		$config_name = substr($data['name'], strpos($data['name'], ".") + 1);
		require_once module_get_path($module) . "module.php"; // Load the module's functions
		$response = call_user_func("{$module}_check_config", $config_name, $data['value'], module_get_lang($module));
		if ($response !== TRUE) {
			return ["error" => $user->module_lang->get("incorrect_value") . $response];
		}

		// Update the configuration
		$result = $config->set($data['name'], $data['value']);

		// Check for errors
		if ($result === FALSE) {
            return ["error" => $user->module_lang->get("cannot_update_config")];
        }

		// Return success
		return ["ok" => TRUE];

	} else if ($action == "get-user-informations") {

		// Check the parameters
		if (!check_keys($data, ["username"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Get the user
		$requiredUser = new User($data['username']);

		// Check if user exists
		if ($requiredUser->id == 0) {
			return ["error" => $user->module_lang->get("user_does_not_exists")];
		}

		// Get the groups names
		$groups_names = [];
		$request = $sql->query("SELECT `name` FROM {$db_prefix}groups WHERE id IN ($requiredUser->groups_sql)");
		while ($group = $request->fetch()) {
			$groups_names[] = prepare_html($group['name'], $user->lang);
		}
		$request->closeCursor();

		// Return the informations
		return [
			"ok" => TRUE,
			"user" => [
				"id" => $requiredUser->id,
				"lang" => $requiredUser->lang->current_language(),
				"lang_html" => $user->lang->supported_languages_HTML($requiredUser->lang->current_language(), "lang", "width:70%;display:inline-block;"),
				"username" => $requiredUser->username,
				"longname" => $requiredUser->longname,
				"email" => $requiredUser->email,
				"registration_time" => $requiredUser->registration_time,
				"formatted_registration" => date($user->module_lang->get("date_format"), $requiredUser->registration_time),
				"groups" => $requiredUser->groups,
				"groups_names" => $groups_names
			]
		];

	} else if ($action == "update-user") {

		// Check the parameters
		if (!check_keys($data, ["id", "property", "value"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Check if property is editable
		if (!in_array($data['property'], ["username", "longname", "email", "lang", "password"])) {
			return ["error" => $user->module_lang->get("property_not_found")];
		}

		// Get the user
		$requiredUser = new User(intval($data['id']));

		// Check if user exists
		if ($requiredUser->id == 0) {
			return ["error" => $user->module_lang->get("user_does_not_exists")];
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

		// Check password
		if ($data['property'] == "password" && strlen($data['value']) < 8) {
            return ["error" => $user->module_lang->get("pass_too_short")];
        }

        // Check the language
        if ($data['property'] == "lang" && !$user->lang->check_supported_language($data['value'])) {
            return ["error" => $user->module_lang->get("wrong_lang")];
        }

		// Hash password if needed
		if ($data['property'] == "password") {
			$data['value'] = hash("sha256", $data['value']);
		}

		// Update the property
		$result = $sql->exec("UPDATE {$db_prefix}users SET $data[property] = " . $sql->quote($data['value']) . " WHERE id = $requiredUser->id");

		// Check for errors
		if ($result === FALSE) {
            return ["error" => $user->module_lang->get("cannot_update_user")];
        }

		// Return success
		return ["ok" => TRUE];

	} else if ($action == "delete-user") {

		// Check the parameters
		if (!check_keys($data, ["id"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Get the user
		$requiredUser = new User(intval($data['id']));

		// Check if user exists
		if ($requiredUser->id == 0) {
			return ["error" => $user->module_lang->get("user_does_not_exists")];
		}

		// If the user is administrator, we can't remove it
		if (in_array(1, $requiredUser->groups)) {
			return ["error" => $user->module_lang->get("cannot_delete_admin")];
		}

		// Call all the modules to allow them to delete all the data related to the user
		$query = $sql->query("SELECT `name` FROM {$db_prefix}modules"); // We select all the modules event the not enabled because they can still have data for the user
        while ($module = $query->fetch()) {
            // Load and call the delete function
			try {
				require_once module_get_path($module['name']) . "module.php";
				$response = call_user_func("{$module['name']}_delete_user", $requiredUser->id);
			} catch (Exception $_) {}
        }
        $query->closeCursor();

		// Delete the user
		$result = $sql->exec("DELETE FROM {$db_prefix}users WHERE id = $requiredUser->id");

		// Check for errors
		if ($result === FALSE) {
            return ["error" => $user->module_lang->get("cannot_delete_user")];
        }

		// Return success
		return ["ok" => TRUE];

	} else if ($action == "update-module-priority") {

		// Check the parameters
		if (!check_keys($data, ["id", "direction"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Check direction
		if (!in_array($data['direction'], ["up", "down"])) {
			return ["error" => $user->module_lang->get("wrong_direction")];
		}

		// Check if module exists
		if (!dbSearchValue("{$db_prefix}modules", "id", $data['id'])) {
			return ["error" => $user->module_lang->get("module_does_not_exists")];
		}

		// Get current module priority and max priority
		$priority = intval(dbGetFirstLineSimple("{$db_prefix}modules", "id = " . $sql->quote($data['id']), "priority", TRUE));
		$max_priority = dbCount("{$db_prefix}modules") - 1;

		// Check if we can move it
		if (($data['direction'] == "up" && $priority == 0) || ($data['direction'] == "down" && $priority == $max_priority)) {
			return ["error" => $user->module_lang->get("wrong_direction")];
		}

		// Update the directions
		$from = $priority;
		$to = $priority + ($data['direction'] == "up" ? -1 : 1);
		$result = $sql->exec("UPDATE {$db_prefix}modules m1 INNER JOIN {$db_prefix}modules m2 ON (m1.priority, m2.priority) IN (($from, $to), ($to, $from)) SET m1.priority = m2.priority");

		// Check for errors
		if ($result === FALSE) {
            return ["error" => $user->module_lang->get("cannot_update_priority")];
        }

		// Return success
		return [
			"ok" => TRUE,
			"from" => $from, // From priority
			"to" => $to, // To priority
			"replace" => intval(dbGetFirstLineSimple("{$db_prefix}modules", "priority = $from", "id", TRUE)), // The module we switch with
			"max" => $max_priority// The maximum priority
		];

	} else if ($action == "set-module-state") {

		// Check the parameters
		if (!check_keys($data, ["id", "state"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Check the state
		if (!in_array($data['state'], ["true", "false"])) {
			return ["error" => $user->module_lang->get("wrong_state")];
		}
		$state = $data['state'] == "true";

		// Get the module name
		$module = dbGetFirstLineSimple("{$db_prefix}modules", "id = " . $sql->quote($data['id']), "name", TRUE);

		// Check if module exists
		if ($module === FALSE) {
			return ["error" => $user->module_lang->get("module_does_not_exists")];
		}

		// Check if we try to disable a core module
		if (is_core_module($module) && !$state) {
			return ["error" => $user->module_lang->get("cannot_disable_core_module")];
		}

		// Set the state
		$result = $sql->exec("UPDATE {$db_prefix}modules SET `enabled` = " . ($state ? "1" : "0") . " WHERE id = " . $sql->quote($data['id']));

		// Check for errors
		if ($result === FALSE) {
            return ["error" => $user->module_lang->get("cannot_update_module")];
        }

		// Return success
		return ["ok" => TRUE, "state" => $state];

	} else if ($action == "install-from-internet") {

		// Check the parameters
		if (!check_keys($data, ["url"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Check URL
		if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
			return ["error" => $user->module_lang->get("wrong_url_format")];
		}

		// Download the file
		$file_name = basename($data['url']) . "_" . rand() . ".zip";
		$temp_file = OMMP_TEMP_DIR . "/$file_name";
		if (file_put_contents($temp_file, file_get_contents($data['url']))) {
			
			// Run the module installation
			$result = module_install($temp_file);

			// Check for error
			if ($result !== TRUE) {
				return ["error" => $result];
			}

		} else {
			return ["error" => $user->module_lang->get("cannot_download")];
		}

		// Return success
		return ["ok" => TRUE];

	} else if ($action == "install-from-file") {

		// Check the parameters
		if (!check_keys($_FILES, ["module_zip"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Move file
		$dest = OMMP_ROOT . "/tmp/module_" . rand() . ".zip";
		move_uploaded_file($_FILES['module_zip']['tmp_name'], $dest);

		// Run the module installation
		$result = module_install($dest);

		// Check for error
		if ($result !== TRUE) {
			return ["error" => $result];
		}

		// Return success
		return ["ok" => TRUE];

	} else if ($action == "unsinstall-module") {

		// Check the parameters
		if (!check_keys($data, ["id"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Get the module name
		$module = dbGetFirstLineSimple("{$db_prefix}modules", "id = " . $sql->quote($data['id']), "name", TRUE);

		// Check if module exists
		if ($module === FALSE) {
			return ["error" => $user->module_lang->get("module_does_not_exists")];
		}

		// Check if we try to uninstall a core module
		if (is_core_module($module) && !$state) {
			return ["error" => $user->module_lang->get("cannot_uninstall_core_module")];
		}

		// Uninstall
		module_uninstall($module);

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
function ommp_process_page($page, $pages_path) {
    global $user;
    // This module uses only the HTML files without processing them
    return module_simple_html($page, $pages_path, [], [
		"" => $user->module_lang->get("platform_administration"),
        "rights" => $user->module_lang->get("rights_management"),
        "groups" => $user->module_lang->get("groups_management"),
        "configurations" => $user->module_lang->get("configurations"),
        "members" => $user->module_lang->get("members_management"),
        "modules" => $user->module_lang->get("modules_management"),
        "modules/install" => $user->module_lang->get("install_module")
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
function ommp_url_handler($url) {
    // This module does not have special URL
    return FALSE;
}