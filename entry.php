<?php
/**
 * Online Module Management Platform
 * 
 * Entry point of OMMP, all the requests are redirected to this file to be processed
 * 
 * @author  The OMMP Team
 * @version 1.0
 */

// Define constants
define("OMMP_ROOT", dirname(__FILE__));
define("OMMP_TEMP_DIR", OMMP_ROOT . "/tmp");
define("OMMP_VERSION", 1);

// First thing to check is if we need to display the installation page
if (!file_exists(OMMP_ROOT . "/install/finished")) {
    require_once OMMP_ROOT . "/install/install.php";
    exit;
}

// Include required files
require_once OMMP_ROOT . "/core/sql.php";
require_once OMMP_ROOT . "/core/functions.php";
require_once OMMP_ROOT . "/core/config.php";
require_once OMMP_ROOT . "/core/user.php";
require_once OMMP_ROOT . "/core/module.php";
require_once OMMP_ROOT . "/core/page.php";

// Get informations about the module to load
$module_name = substr($_SERVER['REDIRECT_URL'], strlen($config->get("ommp.dir")));
$module_page = "";
$slash_pos = strpos($module_name, "/");
if ($slash_pos !== FALSE) {
    $module_page = substr($module_name, $slash_pos + 1);
    $module_name = substr($module_name, 0, $slash_pos);
}

// Handle API calls
if ($module_name == "api") {
    module_api($module_page);
    exit;
}

// Handle media calls
if ($module_name == "media") {
    module_media($module_page);
    exit;
}

// Check if we are on the home page
if ($module_name == "") {
    // Get the home module
    $module_name = $config->get("ommp.homepage");
}

// Check if the module exists and is enabled
if (module_is_enabled($module_name)) {
    // Execute the module
    exec_module($module_name, $module_page, FALSE);
    exit;
}

// Check for special URL handling in all the enabled modules, in priority order
// TODO

// If nothing was found, we display a 404
page_404_error();
