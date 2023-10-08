<?php
/**
 * Online Module Management Platform
 * 
 * Class to represent user and manage session
 * 
 * @author  The OMMP Team
 * @version 1.0 
 */

# Include required files
require_once OMMP_ROOT . "/core/lang.php";

// Create a user (with auto-login if a valid session is detected)
$user = !isset($PREVENT_AUTOLOGIN) ? User::get_user_from_session() : NULL;

class User {

    public $id = 0; // User id (0 is for visitor)
    public $lang; // User lang object for Ommp translations
    public $module_lang; // Module lang object
    public $username = ""; // Username
    public $longname = ""; // User complete name
    public $password = ""; // User password hash
    public $email = ""; // User email
    public $registration_time = 0; // User registration time
    public $session_key = ""; // User current session key (if connected)
    public $session_key_hmac = ""; // User current session key hmac (if connected)
    public $groups = [3]; // List of user's groups (visitors by default)
    public $groups_sql = "3"; // A SQL list of the groups id to allow quick requests
    
    /**
     * Instantiates a user class
     * 
     * @param string|int $user
     *      Username to load, email address or id
     */
    public function __construct($user) {
        global $sql, $db_prefix;

        // Retrieves user information
        $field = "username";
        if (is_int($user)) {
            $field = "id";
        } else if (strpos($user, "@") !== FALSE) {
            $field = "email";
        }
        $line = dbGetFirstLineSimple("{$db_prefix}users", "$field = ".$sql->quote($user));

        if ($line) {

            // Load data if found
            $this->lang = new Lang($line['lang']);
            $this->username = $line['username'];
            $this->longname = $line['longname'];
            $this->id = intval($line['id']);
            $this->email = $line['email'];
            $this->registration_time = intval($line['registration_time']);
            $this->password = $line['password'];

            // Get groups
            $this->groups = [];
            $this->groups_sql = "";
            $request = $sql->query("SELECT group_id FROM {$db_prefix}groups_members WHERE `user_id` = " . $sql->quote($this->id));
            while ($line = $request->fetch()) {
                $this->groups[] = intval($line['group_id']);
                $this->groups_sql .= $line['group_id'] . ",";
            }
            $request->closeCursor();
            $this->groups_sql = substr($this->groups_sql, 0, -1);

        } else {

            // Otherwise, we indicate a default language according to the browser
            $lang_code = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2)) : "en";
            $lang_code = in_array($lang_code, Lang::get_languages_from_dir(OMMP_ROOT . "/core/languages/")) ? $lang_code : 'en';
            $this->lang = new Lang($lang_code);

        }
        
    }

    /**
     * Create a session for the current user
     * 
     * @return boolean
     *      TRUE if the session was created
     *      FALSE else
     */
    function create_session() {
        global $sql, $config, $db_name, $db_prefix, $hmac_key;
        if ($this->id == 0) {
            return FALSE;
        }
        $session_key = random_str(64);
        $expire = time() + intval($config->get('ommp.session_duration'));
        setcookie($config->get('ommp.cookie_user'), $this->username, $expire, "/", $config->get('ommp.domain'), $config->get('ommp.scheme') == "https", TRUE);
        setcookie($config->get('ommp.cookie_session'), $session_key, $expire, "/", $config->get('ommp.domain'), $config->get('ommp.scheme') == "https", TRUE);
        $this->session_key = $session_key;
        $this->session_key_hmac = hash_hmac("sha256", $this->session_key, $hmac_key);
        return $sql->exec("INSERT INTO $db_name.{$db_prefix}sessions VALUES (".$sql->quote($this->id).", ".$sql->quote($session_key).", ".$sql->quote($expire).")");
    }

    /**
     * Returns the User object representing the current user
     * 
     * @return User
     *      The user object
     */
    public static function get_user_from_session() {
        global $sql, $config, $hmac_key, $db_prefix;
        // Check the cookies
        if (isset($_COOKIE[$config->get('ommp.cookie_user')]) && isset($_COOKIE[$config->get('ommp.cookie_session')])) {
            // Checks if a session is associated
            $requiredUser = new User($_COOKIE[$config->get('ommp.cookie_user')]);
            $check = dbGetFirstLineSimple(
                "{$db_prefix}sessions",
                "user_id = ".$sql->quote($requiredUser->id)." AND session_key = ".$sql->quote($_COOKIE[$config->get('ommp.cookie_session')])." AND expire > ".$sql->quote(time())
            );
            // If the session is valid, we return the user
            if ($check !== FALSE) {
                $requiredUser->session_key = $check['session_key'];
                $requiredUser->session_key_hmac = hash_hmac("sha256", $requiredUser->session_key, $hmac_key);
                return $requiredUser;
            }
            // Otherwise, cookies are deleted
            $expire = time() - 3600;
            setcookie($config->get('ommp.cookie_user'), "", $expire, "/", $config->get('ommp.domain'), $config->get('ommp.scheme') == "https", TRUE);
            setcookie($config->get('ommp.cookie_session'), "", $expire, "/", $config->get('ommp.domain'), $config->get('ommp.scheme') == "https", TRUE);
        }
        // Returns the visitor user
        return new User('');
    }

    /**
     * Check if the user has a given right granted
     * 
     * @param string $right
     *      The name of the right (with module prefix)
     * 
     * @return boolean
     *      TRUE if the user have this right in at least one of his groups
     *      FALSE else
     */
    public function has_right($right) {
        global $db_prefix, $sql;
        $result = dbGetFirstLine("SELECT COUNT(*) AS c FROM {$db_prefix}groups_members AS members, {$db_prefix}rights AS rights WHERE members.group_id IN ($this->groups_sql) AND members.user_id = $this->id AND rights.group_id = members.group_id AND rights.value AND rights.name = " . $sql->quote($right));
        return $result !== FALSE && intval($result['c']) > 0;
    }

    /**
     * Get list of all module's accessible by the user
     * 
     * @return array
     *      The name of all the modules the current user can use
     */
    public function accessible_modules() {
        global $sql, $db_prefix;
        $query = $sql->query("SELECT `name`, `enabled` FROM {$db_prefix}modules");
        $modules = [];
        while ($module = $query->fetch()) {
            if (($module['enabled'] || is_core_module($module['name'])) && $this->has_right("$module[name].use")) {
                $modules[] = $module['name'];
            }
        }
        $query->closeCursor();
        return $modules;
    }

    /**
     * Check if the format of a username is valid
     * This method does not check is a username is available
     * 
     * @param string $username
     *      The username to check
     * 
     * @return boolean
     *      TRUE if the format of the username is valid
     *      FALSE else
     */
    public static function check_username_format($username) {
        return strlen($username) <= 32 && preg_match('/^[a-zA-Z0-9_](?:(?:\.[a-zA-Z0-9_])|[a-zA-Z0-9_])+$/m', $username) && !ctype_digit($username);
    }

    /**
     * Check if a username is already taken
     * 
     * @param string $username
     *      The username to check
     * 
     * @return boolean
     *      TRUE if the username is taken
     *      FALSE else
     */
    public static function username_taken($username) {
        global $db_prefix;
        return dbSearchValue("{$db_prefix}users", "username", $username);
    }

}