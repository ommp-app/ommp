<?php
/**
 * Online Module Management Platform
 * 
 * Contains the fonctions to display the HTML pages
 * 
 * @author  The OMMP Team
 * @version 1.0 
 */

// Default templates path
define("TEMPLATE_PATH", OMMP_ROOT . "/core/templates/");

/**
 * Displays content on the page
 * 
 * @param string $content
 *      The HTML content of the page
 * @param string $title
 *      The page title
 * @param array $variables
 *      The list of variables to replace in $content
 * @param string|null $og_image
 *      The image to use as the open graph image
 *      Optional, NULL to use the default image
 * @param string|null $description
 *      The description of the page
 *      Optional, NULL to user the default description
 * @param boolean $navbar
 * 		Should we display the navigation bar
 * 		Optional, TRUE be default
 */
function page_display($content, $title, $variables=[], $og_image=NULL, $description=NULL, $navbar=TRUE) {
    global $config;
    if ($og_image === NULL) {
        $og_image = $config->get("ommp.og_image");
    }
    if ($description === NULL) {
        $description = $config->get("ommp.description");
    }
    $variables['s:og_image'] = htmlspecialchars($og_image);
    $variables['s:description'] = htmlvarescape($description);
    print page_header($title, $variables, $navbar);
    print $content;
    print page_footer($variables);
}

/**
 * Returns the start of the HTML page
 * 
 * @param string $title
 *      The page title
 * @param array $variables
 *      The list of variables to replace in the header
 * @param boolean $navbar
 * 		Should we display the navigation bar
 * 		Optional, TRUE be default
 * 
 * @return string
 *      The beginning of the page in HTML
 */
function page_header($title, $variables=[], $navbar=TRUE) {
    global $user, $config;
	if ($navbar) {
		// Get the list of all available modules
		$modules  = "";
		foreach ($user->accessible_modules() as $module) {
			// Check if we must hide the home page
			if ($config->get("ommp.homepage_in_menu") == "0" && $config->get("ommp.homepage") == $module) {
				continue;
			}
			// Add the module's name to the list
			$module_lang = module_get_lang($module);
			$modules .= page_read_template(TEMPLATE_PATH . "navbar_element.html", [
				"module" => htmlvarescape($module),
				"module_name" => htmlvarescape($module_lang->get("@module_name"))
			]);
		}
		// Read the navbar's template
		$navbar_content = page_read_template(TEMPLATE_PATH . "navbar.html", ["modules" => $modules]);
	}
	// Add the variables
	$variables = array_merge($variables, array(
		"title" => $title,
		"navbar" => (isset($navbar_content) ? $navbar_content : "")
	));
    // Read the template
    return page_read_template(TEMPLATE_PATH . "header.html", $variables);
}

/**
 * Returns the end of the HTML page
 * 
 * @param array $variables
 *      The list of variables to replace in the footer
 * 
 * @return string
 *      The end of the page in HTML
 */
function page_footer($variables = []) {
    return page_read_template(TEMPLATE_PATH . "footer.html", $variables);
}

/**
 * Reads a template file, replaces variables and returns its contents
 * The Lang object will be the module's one
 * 
 * @param string $file
 *      The HTML file to read
 * @param array $variables
 *      An array associating the name of the variable and its content
 *      Optional, empty array by default
 * @param boolean $prepare
 *      Should the content be prepared (replacement of variables)
 *      Optional, TRUE by default
 * 
 * @return string
 *      The content of the file
 */
function page_read_module($file, $variables=[], $prepare=TRUE) {
    global $user;
    return page_read_template($file, $variables, $prepare, $user->module_lang);
}

/**
 * Reads a template file, replaces variables and returns its contents
 * 
 * @param string $file
 *      The HTML file to read
 * @param array $variables
 *      An array associating the name of the variable and its content
 *      Optional, empty array by default
 * @param boolean $prepare
 *      Should the content be prepared (replacement of variables)
 *      Optional, TRUE by default
 * @param Lang $lang
 *      A language object to use, if different from the current user
 *      Optional, NULL by default to use the current user's language
 * 
 * @return string
 *      The content of the file
 */
function page_read_template($file, $variables=[], $prepare=TRUE, $lang=NULL) {
    // Reads the content
    $content = file_get_contents($file);
    if (!$prepare) {
        return $content;
    }
    global $user;
    // Prepare it
    return prepare_html($content, $lang === NULL ? $user->lang : $lang, $variables);
}

/**
 * Replace variables and translations in a string
 * 
 * @param string $content
 *      The content in which to replace
 * @param Lang $lang
 *      The language object to use
 * @param array $variables
 *      The variables to replace
 *
 * @return string
 *      The content with the replaced variables
 */
function prepare_html($content, $lang, $variables=[]) {
    global $user, $config;
    // Add some default variables
    $max_upload = @file_upload_max_size();
    $variables = array_merge($variables, array(
        's:page_resources_version' => OMMP_VERSION . '-' . $user->lang->current_language(), // Some resources depends on the language
        's:domain' => $config->get("ommp.domain"),
        's:scheme' => $config->get("ommp.scheme"),
        's:dir' => $config->get("ommp.dir"),
        's:name' => htmlvarescape($config->get("ommp.name")),
        's:referer' => isset($_SERVER['HTTP_REFERER']) ? htmlvarescape($_SERVER['HTTP_REFERER']) : "",
        's:max_upload' => $max_upload,
        's:max_upload_hr' => intval($max_upload/1024/1024)." {L:MEGA_BYTE}",
        's:contact_email' => $config->get("ommp.contact_email"),
        's:site_logo' => $config->get("ommp.site_logo"),
        's:favicon' => $config->get("ommp.favicon"),
        "u:username" => htmlvarescape($user->username),
        "u:longname" => htmlvarescape($user->longname),
        "u:display_name" => htmlvarescape($user->longname == "" ? $user->username : $user->longname),
        "u:email" => htmlvarescape($user->email),
        "u:session_key_hmac" => $user->session_key_hmac
    ));
    if (!isset($variables['s:og_image']) || $variables['s:og_image'] === NULL) {
        $variables['s:og_image'] = htmlspecialchars($config->get("ommp.og_image"));
    }
    if (!isset($variables['s:description']) || $variables['s:description'] === NULL) {
        $variables['s:description'] = htmlvarescape($config->get("ommp.description"));
    }
    // Find variables and replace them
    $re = '/(\{([\w_:\.]+)\})/m';
    while (TRUE) {
        preg_match_all($re, $content, $matches, PREG_SET_ORDER, 0);
        $done = [];
        if (count($matches) == 0) {
            break;
        }
        foreach ($matches as $match) {
            if (!in_array($match[2], $done)) {

                // Get the key
                $key = $match[2];
                $value = $match[2];

                // Check if we must escape the quotes
                $escape = "";
                if (substr($key, 0, 3) == "JS:") {
                    $key = substr($key, 3);
                    $escape = "'";
                } else if (substr($key, 0, 5) == "HTML:") {
                    $key = substr($key, 5);
                    $escape = '"';
                }

                // Get the value to replace
                if (substr($key, 0, 2) == "L:") {
                    // Lang variable
                    $key = substr($key, 2);
                    $value = $lang->get($key);
                } else if (substr($key, 0, 2) == "R:") {
					// Right variable
					$key = substr($key, 2);
					$value = $user->has_right($key) ? "1" : "0";
                } else if (substr($key, 0, 2) == "C:") {
                    // Configuration
                    $key = strtolower($key);
                    $key = substr($key, 2);
					$value = $config->get($key);
				} else {
                    $key = strtolower($key);
                    $value = isset($variables[$key]) ? $variables[$key] : $key;
                }

                // Escape if needed
                if ($escape != "") {
                    $value = addcslashes($value, $escape);
                }

                // Replace in content
                $content = str_replace($match[0], $value, $content);

                // Save variable as already processed (to avoid infinite loops)
                array_push($done, $match[2]);

            }
        }
    }
    // Return prepared content
    return $content;
}

/**
 * Displays a 404 error and exit
 */
function page_404_error() {
    // Sends an HTTP 404 error code
    header('HTTP/1.0 404 Not Found');
    // Displays the error page
    page_error("{L:404_ERROR}", "{L:404_ERROR_EXPLAIN}", FALSE);
}

/**
 * Display an HTML redirect then quit
 * 
 * @param string $url
 *      The forwarding address
 */
function page_redirect($url) {
    @ob_clean();
    print page_read_template(TEMPLATE_PATH . "redirect.html", array("url" => htmlvarescape($url)));
    exit;
}

/**
 * Displays a generic error page with a return button and exit
 * 
 * @param string $error
 *      The error message
 * @param string $explain
 *      An explaination of the error
 * @param boolean $from_module
 *      Is the function called from a module (to know the lang to use)
 *      Optional, default is TRUE
 */
function page_error($error, $explain, $from_module=TRUE) {
    global $config, $user;
    if ($from_module) {
        $user->module_lang->set("back", $user->lang->get("back")); // Add the "back" key in module lang for the button
    }
    page_display(
        page_read_template(TEMPLATE_PATH . "error.html", ["error" => $error, "explain" => $explain], TRUE, $from_module ? $user->module_lang : NULL),
        $config->get('ommp.name') . " - {L:ERROR}"
    );
    exit;
}