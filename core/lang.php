<?php
/**
 * Online Module Management Platform
 * 
 * Class to load and use a language
 * 
 * @author  The OMMP Team
 * @version 1.0 
 */

// Language handling class
class Lang {

    // Location of translation files
    private $language_path;

    // The language data will be saved in this variable
    private $data;

    // The loaded language code
    private $lang_code;

    // Is the lang loaded
    private $loaded = FALSE;

    /**
     * Class constructor
     * Load the associated language file
     * 
     * @param string $code
     *      The language code to load (see class attributes)
     * @param boolean|string $module
     *      The name of the module whose language we want to load
     *      Optional, default is FALSE to load the Ommp default language
     */
    function __construct($code, $module=FALSE) {
        $this->lang_code = $code;
        // Set the correct directory
        if ($module === FALSE) {
            $this->language_path = OMMP_ROOT . "/core/languages/";
        } else {
            $this->language_path = module_get_path($module) . "languages/";
        }
        // Read the json file
        $path = $this->language_path . $code . ".json";
        if (!file_exists($path)) {
            return;
        }
        $this->data = json_decode(file_get_contents($path));
        $this->loaded = TRUE;
    }

    /**
     * Check if the language has been loaded
     * 
     * @return boolean
     *      TRUE if the language has been loaded successfuly
     *      FALSE else
     */
    public function is_loaded() {
        return $this->loaded;
    }

    /**
     * Checks if a given language is supported
     * 
     * @param string $code
     *      The language code to check
     * 
     * @return boolean
     *      TRUE if the language is supported
     *      FALSE else
     */
    function check_supported_language($code) {
        return file_exists($this->language_path . $code . ".json");
    }

    /**
     * Retrieve current language code
     * 
     * @return string
     *      The current language code
     */
    function current_language() {
        return $this->lang_code;
    }

    /**
     * Retrieving a value
     * 
     * @param string $key
     *      The key of the string to retrieve in the current language
     * @param array $replace
     *      An array containing the variables to replace in the result
     *      Optional
     * @param bool $escape
     *      Should HTML characters be escaped before returning the string?
     *      Optional, default to TRUE
     * 
     * @return string
     *      The character string corresponding to the given key
     */
    function get($key, $replace=[], $escape=TRUE) {
        $key = strtolower($key);
        if (isset($this->data->$key)) {
            $data = $this->data->$key;
        } else {
            $data = "{$key}";
        }
        foreach($replace as $key => $value) {
            $key = strtoupper($key);
            $data = str_replace("{" . $key . "}", $value, $data);
        }
        if ($escape) {
            return htmlvarescape($data);
        }
        return $data;
    }

    /**
     * Add or update a lang entry
     * 
     * @param string $key
     *      The key to create/update
     * @param string $value
     *      The value to save
     */
    public function set($key, $value) {
        $this->data->$key = $value;
    }

    /**
     * Returns the list of all supported languages
     * 
     * @return array
     *      An array with all supported language codes
     */
    public function supported_languages() {
        return Lang::get_languages_from_dir($this->language_path);
    }

    /**
     * Returns the list of all supported languages in HTML
     * 
     * @param string $default
     *      The language to select by default
     * @param string $name
     *      The name of the HTML field
     *      Optional, "language" by default
	 * @param string|null $style
	 * 		Additional style to add to the control (CSS format)
	 * 		Optional, null to ignore
     * 
     * @return string
     *      The html code of the languages drop-down menu
     */
    public function supported_languages_HTML($default, $name="language", $style=null) {
        $escapedName = htmlvarescape($name);
        $result = "<select name=\"$escapedName\" id=\"$escapedName\" class=\"form-select\"" . ($style !== null ? " style=\"$style\"" : "") . ">";
        foreach($this->supported_languages() as $language) {
            $tmp_lang = new Lang($language);
            $result .= "<option value=\"".htmlvarescape($language)."\"".($default == $language ? " selected" : "").">".htmlvarescape($tmp_lang->get("@name"))."</option>";
        }
        return $result."</select>";
    }

    /**
     * Return the lang codes for the files in a given language directory
     * 
     * @param string $path
     *      The path of the languages directory
     * 
     * @return  array
     *      An array with all supported language codes
     */
    public static function get_languages_from_dir($path) {
        $lanuages = [];
        foreach (scandir($path) as $file) {
            if (substr($file, -5) == ".json") {
                $lanuages[] = substr($file, 0, -5);
            }
        }
        return $lanuages;
    }

}