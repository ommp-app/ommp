# OMMP
## Online Module Management Platform
Ommp is a simple platform managing users and connections and running modules that can be combined to build your dreamed web service. You can directly download new modules from the marketplace and install them in just a few clicks.

## Requirements
- Apache >= 2.4.35 (with mod_rewrite)
- PHP >= 7.2.10
- MySQL >= 5.7.23

## Files and folders description
- ```core/``` The source files used by the platform
  - ```languages/``` Contains the JSON files for the translations
  - ```modules/``` The system's modules (they works the same as others but cannot be disabled or removed)
    - ```connection``` To allow users to connect to the platform
    - ```ommp``` To manage important settings and rights about the platform and the modules
    - ```registration``` To allow users to register (if registrations are open)
    - ```settings``` To allow users to manage their account's settings
  - ```templates/``` Contains the HTML templates for the platform
  - ```config.php``` The class to read and write the configurations
  - ```credentials.php``` File storing the credentials used to connect to the SQL database (created during the installation, does not exists before)
  - ```functions.php``` Misc useful functions
  - ```lang.php``` The class to load and use a given language
  - ```module.php``` The functions to manage, load and execute a module
  - ```page.php``` The functions to display the pages
  - ```sql.php``` The file that connects to the database (also contains function to do quick SQL requests)
  - ```user.php``` User and session management
- ```data/``` All the files generated by the module (one directory per module)
- ```install/``` All the files needed to install the platform
  - ```credentials.php.template``` Template file for the credentials file
  - ```finished``` File created when the installation is complete to tell the platform to don't show the installation page again
  - ```install.php``` The source code of the installation program
- ```modules/``` All the sources of the installed modules (one directory per module)
- ```.gitignore``` Rules to ignore some files in Git project
- ```.htaccess``` Apache configuration
- ```entry.php``` The entry point for all the request on the platform (redirected with mod_rewrite)
- ```README.md``` Basic informations about Ommp

## Module structure
A module must respect the structure defined below and implments the required functions. This is required to have a simple integrations of the modules in the platform.
__Warning:__ A module should never modify a file or a table which does not belong to it!

A module is represented by it's string id, it is a lowercase string containing only alphanumerals characters and underscores.
_Note:_ A module cannot have the following reserved names: ommp, moderation, registration, connection, settings, api or media.

A module example can be downloaded at https://github.com/ommp-app/example

#### Required files
- ```languages/``` Contains the JSON files for the translations
  Each file must have the following keys:
  - ```@name``` The name of the language
  - ```@author``` The author(s) of the translation
  - ```@description``` A brief description of the translation
  - ```@module_name``` The name of the module
  - ```@module_description``` The description of the module
  Each config and right key should have a translated name and descriptions.
  For example "foo.mykey" must have "foo.mykey#name" and "foo.mykey#descr".
- ```media/``` Contains all the static media files that can be used by the module
- ```pages/``` Contains all the HTML pages that can be displayed for the module
- ```defaults.json``` A JSON file containing the default value for the configs and rights to set during the installation (without the module name as a prefix). JSON must have the following keys:
  - "configurations": An object of all the configurations with their default values
  - "rights": An object with as keys the rights name and as value a list with three values corresponding to the default values for groups [administrators, classic users, visitors]. Note that all the other groups on the platform will inherit the rights of the "classic visitors" groups.
  - "protected": Optional, the same format as "rights" but to specify if the right is protected (a protected right cannot be modified while it is protected)
- ```install.sql``` The SQL code to install the module's tables in the database (_{PREFIX}_ will be replaces by the tables prefix before execution)
- ```meta.json``` A JSON file containing the module's metadata (_required properties below_)
  - ```id``` The string id of the module
  - ```version``` A positive integer representing the module version
  - ```requirement``` The minimum version of Ommp required
  - ```author``` The name(s) of the author(s)
  - ```website``` The website of the author
  - ```contact``` The contact email of the author
  - ```default_language``` The code of the language to use as default if the user's lang is not supported by the module
- ```module.php``` The file that will implements some functions needed to interract with the platform (_required functions below, xxx represents the module' string id_)
  - ```xxx_check_config``` A function to ckeck if a configuration value is correct
  - ```xxx_delete_user``` A function called when a user is being removed from the plateform to let the module delete all the related data
  - ```xxx_process_api``` A function to handle the API calls
  - ```xxx_process_page``` A function to generate the HTML content for the pages
  - ```xxx_url_handler``` A function called to handle special URLs, will be called before triggering a 404 error
- ```uninstall.sql``` The SQL code to remove the module's tables from the database (_{PREFIX}_ will be replaces by the tables prefix before execution)

#### Security requirements
For security reasons, the following rules must be followed when developping a module.

Always use POST requests in your forms that needs to be secured.

Every POST request must have a parameter "skh" containing the valid session key hmac. This can be done by adding the following line inside every form: ```<input type="hidden" name="skh" value="{U:SESSION_KEY_HMAC}" />```
Note that there is no need to add the skh when calling API from the given ```Api``` class.
The SKH is also available in the JavaScript variable ```ommp_session_key_hmac```.

## Configurations values
Below a list of all the default configurations for Ommp
| Name | Default value | Description |
|----|------|-----|
| ommp.name | _Defined during installation_ | The name of the platform |
| ommp.description | _Defined during installation_ | The description of the platform |
| ommp.mail_sender_name | _Defined during installation_ | The name of the sender for the emails |
| ommp.mail_sender | _Defined during installation_ | The email address of the sender for the emails |
| ommp.contact_email | _Defined during installation_ | The contact email of the platform (can be the same as _ommp.mail_sender_) |
| ommp.domain | _Defined during installation_ | The domain name where OMMP is installed |
| ommp.scheme | _Defined during installation ("http" or "https")_ | The scheme used to connect to the site |
| ommp.dir | _Defined during installation_ | The installation of OMMP in the domain |
| ommp.homepage | homepage | The name of the module to display on platform homepage |
| ommp.og_image | {S:SCHEME}://{S:DOMAIN}{S:DIR}media/ommp/images/og-image.jpg | The path of the image to use as Open Graph preview |
| ommp.session_duration | 2592000 | The maximum lifetime of a connection session in seconds (default is one month) |
| ommp.cookie_user | ommp_user | The name of the cookie that will contains the user id |
| ommp.cookie_session | ommp_session | The name of the cookie that will contains the session key |
| ommp.cache_lifetime | 31536000 | The duration of the browser cache for static files in seconds (default is one year) |
| ommp.recaptcha_site | _Empty_ | The reCAPTCHA site key |
| ommp.recaptcha_secret | _Empty_ | The reCAPTCHA secret key |
| registration.open | 1 | Define if the registrations are opened or not |
| registration.google_recaptcha | 0 | Does the registration have to be protected by reCAPTCHA v2 |
| connection.google_recaptcha | 0 | Does the connection have to be protected by reCAPTCHA v2 |

## Groups
OMMP provides a system to manage users by assign them into groups. Every users in a group can have all the rights granted to this group. A user can be a member of multiple groups.  
There is three default groups when you install OMMP:
- the administrators who have all the rights by default
- the classic users who are the default group assigned for new users
- the visitors group who is only for the visitors browsing the site wihtout being connected
You cannot delete these two groups, but you can edit their rights (be careful when editing administrator's rights).  
New users are by default assigned to the classic users group.  

## Rights
The rights are boolean values (true/false) used to manage the permissions of the groups over the platform and the different modules.  
All the users in a group have the same rights. If a user have rights with different values from different groups, the true value will be used.  
  
All the modules have at least two rights named _module\_id.use_ and _module\_id.use\_media_ to decide if the members of this group can use respectively the module and its media.  
For example, the registration module have "true" for this value only for the visitors, because we don't want the others users to access this module.  
These mandatory rights does not needs to have a translation entry.  

Note that each right is prefixed by the module string id (like the configurations).

## Installation steps
1. Check connection to MySQL
2. Install the modules
3. Fill the configurations defined during installation
4. Create the admin user

## TO DO
- [ ] Validation by email for registrations
- [ ] Password recuperation
- [x] Rights
- [x] User groups
- [ ] "homepage" module
- [x] Configurations
- [x] User administration
- [ ] Update a module
- [x] User settings
- [ ] GDPR export
- [ ] Reset module settings and rights
- [ ] Choose a default language for the platform