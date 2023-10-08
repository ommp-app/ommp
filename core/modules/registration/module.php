<?php
/**
 * Online Module Management Platform
 * 
 * Main file for registration module
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
function registration_check_config($name, $value, $lang) {
    if ($name == "google_recaptcha" || $name == "open") {
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
 * 		The id of the user that will be deleted
 */
function registration_delete_user($id) {
	// Nothing to do here because the user is deleted from the OMMP API call function
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
function registration_process_api($action, $data) {
    if ($action == "check-username") {
        return ["available" => User::check_username_format($data['username']) && !User::username_taken($data['username'])];
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
function registration_process_page($page, $pages_path) {
    global $user, $config, $sql, $db_prefix, $db_name;

    // Registration has only one page
    if ($page != "") {
        return FALSE;
    }

    // Check if registrations are opened
    if ($config->get("registration.open") != "1") {
        page_error("{L:REGISTRATION_CLOSED}", "{L:REGISTRATION_CLOSED_EXPLAIN}");
    }

    // Check if the form is submited
    if (check_keys($_POST, ["username", "longname", "email", "password", "password_confirm", "language"])) {

        // Check the username
        if (!User::check_username_format($_POST['username'])) {
            page_error("{L:WRONG_USERNAME_FORMAT}", "{L:USERNAME_EXPLAIN}");
        }

        // Check the availability of the username
        if (User::username_taken($_POST['username'])) {
            page_error("{L:USERNAME_TAKEN}", "{L:USERNAME_TAKEN_EXPLAIN}");
        }

        // Check the long name
        if (strlen($_POST['longname']) > 50) {
            page_error("{L:LONGNAME_TOO_LONG}", "{L:LONGNAME_TOO_LONG_EXPLAIN}");
        }

        // Check the email format
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            page_error("{L:INVALID_EMAIL}", "{L:INVALID_EMAIL_EXPLAIN}");
        }

        // Check password match
        if ($_POST['password'] != $_POST['password_confirm']) {
            page_error("{L:PASSWORD_DONT_MATCH}", "{L:PASSWORD_DONT_MATCH_EXPLAIN}");
        }

        // Check password length
        if (strlen($_POST['password']) < 8) {
            page_error("{L:PASSWORD_TOO_SHORT}", "{L:PASSWORD_TOO_SHORT_EXPLAIN}");
        }

        // Check the language
        if (!$user->lang->check_supported_language($_POST['language'])) {
            page_error("{L:LANGUAGE_ERROR}", "{L:LANGUAGE_ERROR_EXPLAIN}");
        }

        // Check reCAPTCHA
        if ($config->get("registration.google_recaptcha") == "1" && !recaptcha_is_valid()) {
            page_error("{L:CAPTCHA_ERROR}", "{L:CAPTCHA_ERROR_EXPLAIN}");
        }

        // If everything is good we create the account
        $response = $sql->exec("INSERT INTO $db_name.{$db_prefix}users VALUES (NULL, " . $sql->quote($_POST['username']) . ", " . $sql->quote($_POST['longname']) . ", " . $sql->quote(hash("sha256", $_POST['password'])) . ", " . $sql->quote($_POST['email']) . ", " . time() . ", " . $sql->quote($_POST['language']) . ", FALSE)");

		// Add user to the default group
		if ($response !== FALSE) {
			$response = $sql->exec("INSERT INTO {$db_prefix}groups_members VALUES (2, " . $sql->lastInsertId() . ")");
		}

        if ($response === FALSE) {
			$sql->exec("DELETE FROM {$db_prefix}users WHERE username = " . $sql->quote($_POST['username'])); // Delete user from users table if error occurs when adding in the group
            page_error("{L:REGISTRATION_ERROR}", "{L:REGISTRATION_ERROR_EXPLAIN}");
        }

        // Display success page
        return [
            "content" => page_read_module($pages_path . "success.html"),
            "title" => $user->module_lang->get("@module_name")
        ];

    }

    // Return the page to display
    return [
        "content" => page_read_module($pages_path . "form.html", [
            "languages" => $user->lang->supported_languages_HTML($user->lang->current_language()),
            "captcha" => $config->get("registration.google_recaptcha") == "0" ? "" : page_read_module($pages_path . "recaptcha.html", ["site_key" => $config->get("ommp.recaptcha_site")])
        ]),
        "title" => $user->module_lang->get("@module_name")
    ];
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
function registration_url_handler($url) {
    // This module does not have special URL
    return FALSE;
}