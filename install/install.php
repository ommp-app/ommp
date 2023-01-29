<?php
/**
 * Online Module Management Platform
 * 
 * Display the installation form
 * 
 * @author  The OMMP Team
 * @version 1.0 
 */

// Include functions
require_once OMMP_ROOT . "/install/functions.php";

// Define variables
$pages_path = OMMP_ROOT . "/install/pages";
$langs_path = OMMP_ROOT . "/install/languages";
$media_path = OMMP_ROOT . "/core/modules/ommp/media";
$supported_languages = ["en", "fr"];

// Check if we are visiting the site after step 5 without the parameter
if (!isset($_GET['step']) && file_exists(OMMP_ROOT . "/install/step_5")) {
    unlink(OMMP_ROOT . "/install/step_5");
	file_put_contents(OMMP_ROOT . "/install/finished", "1");
	readfile("$pages_path/redirect.html");
}

// Check if we want to display a media
if (isset($_GET['media'])) {
	media_display($_GET['media']);
}

// Special case for fonts loaded by CSS
if (substr($_SERVER['REQUEST_URI'], 0, 7) == "/fonts/") {
	media_display(substr($_SERVER['REQUEST_URI'], 1));
}

// Load the language
$lang_code = "en";
if (isset($_GET['lang']) && in_array($_GET['lang'], $supported_languages)) {
	$lang_code = $_GET['lang'];
}
$lang = json_decode(file_get_contents("$langs_path/$lang_code.json"));

// Display the page

if (!isset($_GET['step']) || $_GET['step'] == "1") {
	// Step 1: Ask language
	page_display("step_1.html", $lang->welcome);
}

if ($_GET['step'] == "2") {
	// Step 2: Ask MySQL credentials
	page_display("step_2.html", $lang->sql_connect);
}

if ($_GET['step'] == "3") {
	// Step 3: Test MySQL connection
	$sql = mysql_connect($_POST['db_host'], $_POST['db_name'], $_POST['db_user'], $_POST['db_pass']);
	// Search for error
	if (is_string($sql)) {
		// Display error and message from MySQL
		$error = mb_convert_encoding($sql, "UTF-8", "ASCII"); // We need to convert encoding because PDO in French return weird encoding causing htmlspecialchars to return an empty string
		page_display("sql_error.html", $lang->sql_error, ["error" => $error]);
	} else {
		// Display the success message and ask for final settings
		page_display("step_3.html", $lang->final_infos, [
			"db_host" => $_POST['db_host'],
			"db_name" => $_POST['db_name'],
			"db_user" => $_POST['db_user'],
			"db_pass" => $_POST['db_pass'],
			"db_prefix" => $_POST['db_prefix'],
			"domain" => $_SERVER['SERVER_NAME'],
			"select_http" => $_SERVER['REQUEST_SCHEME'] == "http" ? " selected" : "",
			"select_https" => $_SERVER['REQUEST_SCHEME'] == "https" ? " selected" : "",
			"dir" => str_replace("entry.php", "", $_SERVER['PHP_SELF'])
		]);
	}
}

if ($_GET['step'] == "4") {

	// Check if all the informations are here
	if (!check_keys($_POST, ["db_host", "db_user", "db_user", "db_name", "db_pass", "db_prefix", "name", "description",
		"mail_sender_name", "mail_sender", "contact_email", "domain", "scheme", "dir", "username", "longname", "email", "password", "password_confirm"])) {
		error($lang->missing_parameter);
	}

	// Check platform name
	if ($_POST['name'] == "") {
		error($lang->name_required);
	}

	// Check platform description
	if ($_POST['description'] == "") {
		error($lang->description_required);
	}

	// Check mail sender name
	if ($_POST['mail_sender_name'] == "") {
		error($lang->mail_sender_name_required);
	}

	// Check sender mail
	if (!filter_var($_POST['mail_sender'], FILTER_VALIDATE_EMAIL)) {
		error($lang->invalid_sender_email);
	}

	// Check contact mail
	if (!filter_var($_POST['contact_email'], FILTER_VALIDATE_EMAIL)) {
		error($lang->invalid_contact_email);
	}

	// Check domain
	if (!(filter_var($_POST['domain'], FILTER_VALIDATE_DOMAIN) || filter_var($_POST['domain'], FILTER_VALIDATE_IP))) {
		error($lang->invalid_domain);
	}

	// Check scheme
	if ($_POST['scheme'] != "http" && $_POST['scheme'] != "https") {
		error($lang->invalid_scheme);
	}

	// Check dir
	if (substr($_POST['dir'], 0, 1) != "/" || substr($_POST['dir'], -1) != "/") {
		error($lang->invalid_dir);
	}

	// Check the username
	$PREVENT_AUTOLOGIN = TRUE;
	require_once OMMP_ROOT . "/core/user.php";
	if (!User::check_username_format($_POST['username'])) {
		error($lang->wrong_username);
	}

	// Check the long name
	if (strlen($_POST['longname']) > 50) {
		error($lang->longname_too_long);
	}

	// Check the email format
	if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
		error($lang->invalid_admin_email);
	}

	// Check password match
	if ($_POST['password'] != $_POST['password_confirm']) {
		error($lang->password_mismatch);
	}

	// Check password length
	if (strlen($_POST['password']) < 8) {
		error($lang->pass_too_short);
	}

	// If everything is okay, we start the installation

	// Connect to MySQL
	$sql = mysql_connect($_POST['db_host'], $_POST['db_name'], $_POST['db_user'], $_POST['db_pass']);
	// Search for error
	if (is_string($sql)) {
		// Display error and message from MySQL
		$error = mb_convert_encoding($sql, "UTF-8", "ASCII"); // We need to convert encoding because PDO in French return weird encoding causing htmlspecialchars to return an empty string
		page_display("sql_error.html", $lang->sql_error, ["error" => $error]);
	}

	// Create the tables
	$db_prefix = $_POST['db_prefix'];
	$result = exec_sql_file(OMMP_ROOT . "/core/modules/connection/install.sql");
	$result &= exec_sql_file(OMMP_ROOT . "/core/modules/ommp/install.sql");
	$result &= exec_sql_file(OMMP_ROOT . "/core/modules/registration/install.sql");
	$result &= exec_sql_file(OMMP_ROOT . "/core/modules/settings/install.sql");
	$result &= exec_sql_file(OMMP_ROOT . "/modules/homepage/install.sql");
	if (!$result) {
		error($lang->create_table_error);
	}

	// Create the settings, configurations, directories and register modules
	$result = finish_module_creation(OMMP_ROOT . "/core/modules/ommp", 0);
	$result &= finish_module_creation(OMMP_ROOT . "/core/modules/registration", 1);
	$result &= finish_module_creation(OMMP_ROOT . "/core/modules/connection", 2);
	$result &= finish_module_creation(OMMP_ROOT . "/core/modules/settings", 3);
	$result &= finish_module_creation(OMMP_ROOT . "/modules/homepage", 4);
	if (!$result) {
		error($lang->create_module_error);
	}

	// Fill the configurations
	$result = set_config("ommp.name", $_POST['name']);
	$result &= set_config("ommp.description", $_POST['description']);
	$result &= set_config("ommp.mail_sender_name", $_POST['mail_sender_name']);
	$result &= set_config("ommp.mail_sender", $_POST['mail_sender']);
	$result &= set_config("ommp.contact_email", $_POST['contact_email']);
	$result &= set_config("ommp.domain", $_POST['domain']);
	$result &= set_config("ommp.scheme", $_POST['scheme']);
	$result &= set_config("ommp.dir", $_POST['dir']);
	if (!$result) {
		error($lang->create_settings_error);
	}

	// Create the admin user
	$result = $sql->exec("INSERT INTO {$db_prefix}users VALUES (NULL, " . $sql->quote($_POST['username']) . ", " . $sql->quote($_POST['longname']) . ", " .
		$sql->quote(hash("sha256", $_POST['password'])) . ", " . $sql->quote($_POST['email']) . ", " . $sql->quote(time()) . ", " . $sql->quote($lang_code) . ")");
	if ($result === FALSE) {
		error($lang->create_admin_error);
	}
	$result = $sql->exec("INSERT INTO {$db_prefix}groups_members VALUES (1, 1)");
	if ($result === FALSE) {
		error($lang->create_admin_error);
	}

	// Create the credentials file
	$credentials = @file_get_contents(OMMP_ROOT . "/install/credentials.php.template");
	$credentials = str_replace("{USER}", $_POST['db_user'], $credentials);
	$credentials = str_replace("{PASS}", $_POST['db_pass'], $credentials);
	$credentials = str_replace("{HOST}", $_POST['db_host'], $credentials);
	$credentials = str_replace("{NAME}", $_POST['db_name'], $credentials);
	$credentials = str_replace("{PREFIX}", $_POST['db_prefix'], $credentials);
	$credentials = str_replace("{HMAC}", random_str(), $credentials);
	$result = @file_put_contents(OMMP_ROOT . "/core/credentials.php", $credentials);
	if (!$result) {
		error($lang->create_credentials_error);
	}

	// Enable HTTPS redirection if needed
	if ($_POST['scheme'] == "https") {
		$htaccess = @file_get_contents(OMMP_ROOT . "/.htaccess");
		$htaccess = str_replace("#https#", "", $htaccess);
		@file_put_contents(OMMP_ROOT . "/.htaccess", $htaccess);
	}

	// Everything is okay, display the final message
	file_put_contents(OMMP_ROOT . "/install/step_5", "1");
	page_display("success.html", $lang->success, ["dir" => htmlspecialchars($_POST['dir'])]);

}

if ($_GET['step'] == "5") {
	// Step 5: Mark as finished and redirect to login
	unlink(OMMP_ROOT . "/install/step_5");
	file_put_contents(OMMP_ROOT . "/install/finished", "1");
	readfile("$pages_path/redirect.html");
}