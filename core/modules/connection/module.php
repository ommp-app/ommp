<?php
/**
 * Online Module Management Platform
 * 
 * Main file for connection module
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
function connection_check_config($name, $value, $lang) {
    if ($name == "google_recaptcha") {
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
function connection_delete_user($id) {
	global $sql, $db_prefix;

	// Delete user's sessions
	$sql->exec("DELETE FROM {$db_prefix}sessions WHERE user_id = " . $id);

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
function connection_process_api($action, $data) {
    // No API for this module
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
function connection_process_page($page, $pages_path) {
    global $user, $config;

    // Connection has only one page
    if ($page != "") {
        return FALSE;
    }

    // Check if the form is submited
    if (check_keys($_POST, ["username", "password", "redirect"])) {

        // Check reCAPTCHA
        if ($config->get("connection.google_recaptcha") == "1" && !recaptcha_is_valid()) {
            page_error("{L:CAPTCHA_ERROR}", "{L:CAPTCHA_ERROR_EXPLAIN}");
        }

        // Get the user
        $required_user = new User($_POST['username']);

        // Check the password
        if ($required_user->id == 0 || hash("sha256", $_POST['password']) != $required_user->password) {
            page_error("{L:CREDENTIALS_ERROR}", "{L:CREDENTIALS_ERROR_EXPLAIN}");
        }

        // If the user exists and the password is correct, we create a session
        if (!$required_user->create_session()) {
            page_error("{L:CONNECTION_ERROR}", "{L:CONNECTION_ERROR_EXPLAIN}");
        }

        // If the sessions is created, redirect to home (or required page)
        page_redirect($_POST['redirect']);

    }

    // Return the page to display
    return [
        "content" => page_read_module($pages_path . "form.html", [
            "redirect" => isset($_GET['r']) ? htmlvarescape($_GET['r']) : "{S:DIR}",
            "captcha" => $config->get("connection.google_recaptcha") == "0" ? "" : page_read_module($pages_path . "recaptcha.html", ["site_key" => $config->get("ommp.recaptcha_site")])
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
function connection_url_handler($url) {
    // This module does not have special URL
    return FALSE;
}