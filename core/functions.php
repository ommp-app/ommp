<?php
/**
 * Online Module Management Platform
 * 
 * Contains multiple misc fonctions
 * 
 * @author  The OMMP Team
 * @version 1.0 
 */

/**
 * Sends an email in HTML format
 * 
 * @param string $to
 *      The recipient of the email
 * @param string $subject
 *      The subject of the email
 * @param string $content
 *      The HTML content of the email
 * 
 * @return boolean
 *      TRUE if the email has been sent
 *      FALSE else
 * 
 */
function sendMail($to, $subject, $content) {
    global $config;
    $content = wordwrap($content, 70, "\r\n");
    $headers = 
        'MIME-Version: 1.0' . "\r\n" .
        'Content-type: text/html; charset=utf-8' . "\r\n" .
        'From: '.$config->get('ommp.mail_sender_name').' <' . $config->get('ommp.mail_sender') . '>' . "\r\n" .
        'Reply-To: ' . $config->get('ommp.contact_email') . "\r\n";
    return @mail($to, $subject, $content, $headers);
}

/**
 * Display a JSON and exit
 * 
 * @param array $data
 *      The PHP array to transform into JSON
 */
function output_json($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Escape HTML characters and template variables
 * 
 * @param string $code
 *      The string to prepare
 * 
 * @return string
 *      The escaped string
 */
function htmlvarescape($code) {
    $code = htmlspecialchars($code);
    return str_replace("{", "&lbrace;", $code);
}

/**
 * Generates a random string
 * 
 * @source
 *      https://stackoverflow.com/a/4356295
 */
function random_str($length=32, $characters='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Returns the server file size limit in bytes based on the PHP upload_max_filesize
 * 
 * @source
 *      https://stackoverflow.com/a/25370978
 * 
 * @return int
 *      The maximum size of a file that can be uploaded
 */
function file_upload_max_size() {
    static $max_size = -1;
    if ($max_size < 0) {
        // Start with post_max_size.
        $post_max_size = parse_size(ini_get('post_max_size'));
        if ($post_max_size > 0) {
            $max_size = $post_max_size;
        }
        // If upload_max_size is less, then reduce. Except if upload_max_size is
        // zero, which indicates no limit.
        $upload_max = parse_size(ini_get('upload_max_filesize'));
        if ($upload_max > 0 && $upload_max < $max_size) {
            $max_size = $upload_max;
        }
    }
    return $max_size;
}

/**
 * Convert a byte size from human readable to just byte
 * 
 * @param string $size
 *      The string representing the size with a unit
 * 
 * @source
 *      https://stackoverflow.com/a/25370978
 * 
 * @return int
 *      The size in bytes
 */
function parse_size($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
    $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
    if ($unit) {
        // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    } else {
        return round($size);
    }
}

/**
 * Return the size of a folder in bytes
 * @param string $dir
 *      The directory to scan
 * @return int
 *      The size in bytes of all the files inside the folder and sub-folders
 */
function folder_size($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : folder_size($each);
    }
    return $size;
}

/**
 * Output the thumbnail of an image from a file
 * Supports JPEG, PNG and GIF
 * 
 * @param string $file
 * 		The image file
 * @param int $max_size
 * 		The maximum size of the image
 * @param int $jpeg_quality
 *      The quality for the JPEG thumbnails between 0 and 100 (optional, default is 100)
 * 
 * @return boolean
 * 		TRUE if the image has been printed
 * 		FALSE in case of error
 */
function get_image_thumbnail($file, $max_size, $jpeg_quality=100) {

    // Reads the image metadata
    $size = getimagesize($file);
    $width = $size[0];
    $height = $size[1];
    $exif = @exif_read_data($file);

    // Reads the image
    $png = FALSE;
    switch ($size['mime']) {
        case 'image/jpg':
        case 'image/jpeg':
            $image = imagecreatefromjpeg($file);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($file);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($file);
            imagealphablending($image, true);
            $png = TRUE;
            break;
        default:
            return FALSE;
    }

    // Handle orientation and mirror
    if (!empty($exif['Orientation'])) {
        $orientation = $exif['Orientation'];
        // Correct mirror
        if (in_array($orientation, [2, 4, 5, 7])) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        // Correct rotation
        switch ($orientation) {
            case 3:
            case 4:
                $image = imagerotate($image, 180, 0);
                break;
            case 5:
            case 6:
            case 7:
            case 8:
                $image = imagerotate($image, (in_array($orientation, [5, 8]) ? 1 : -1) * 90, 0);
                $temp = $width;
                $width = $height;
                $height = $temp;
                break;
        }
    }

    // Check if we try to display image bigger than it is
    if ($max_size >= max($width, $height)) {
        return FALSE;
    }

    // Compute new dimension
    if ($width > $height) {
        $new_width = $max_size;
        $new_height = $max_size / $width * $height;
    } else {
        $new_height = $max_size;
        $new_width = $max_size / $height * $width;
    }

    // Check sizes
    if ($new_width <= 1 || $new_height <= 1) {
        return FALSE;
    }

    // Create new image
    $new_image = imagecreatetruecolor($new_width, $new_height);
    if ($png) {
        // Handle transparency
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
    }
    // Resize the image
    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // Print thumbnail
    if ($png) {
        header('Content-Type: image/png');
        imagepng($new_image, NULL, 0); // Output as PNG for transparency
    } else {
        header('Content-Type: image/jpeg');
        imagejpeg($new_image, NULL, $jpeg_quality); // Output as JPEG for bandwidth saving
    }

    // Return success
    return TRUE;

}

/**
 * Copy a directory and all the sub-elements
 * @source
 *      https://www.geeksforgeeks.org/copy-the-entire-contents-of-a-directory-to-another-directory-in-php/
 * @param string $src
 *      The source directory
 * @param string $dst
 *      The destination
 * @return boolean
 *      TRUE if success
 *      FALSE if failure of at least one file copy
 */
function dir_copy($src, $dst) {
    // open the source directory
    $dir = opendir($src);
    // Make the destination directory if not exist
    @mkdir($dst);
    // Loop through the files in source directory
    $result = TRUE;
    while ($file = readdir($dir)) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                // Recursively calling custom copy function
                // for sub directory
                $result &= dir_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                $result &= copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
    return $result;
}

/**
 * Get the mime type of a file, with extention management
 * 
 * @param string $file
 *      The file to check
 * 
 * @return string
 *      The mime type of the file
 */
function better_mime_type($file) {

    // Check extensions for files that PHP does not recognize every times
    $types = [
        "css" => "text/css",
        "js" => "text/javascript",
        "txt" => "text/plain"
    ];
    $extension = substr($file, strrpos($file, ".") + 1);
    if (isset($types[$extension])) {
        return $types[$extension];
    }

    // For all others, return the detected mime type
    return mime_content_type($file);
    
}

/**
 * Check if all the keys are set in an array
 * 
 * @param array $array
 *      The array to check in
 * @param array $keys
 *      The keys to check in the array
 * 
 * @return boolean
 *      TRUE if all the keys are in the array
 *      FALSE else
 */
function check_keys($array, $keys) {
    foreach ($keys as $key) {
        if (!isset($array[$key])) {
            return FALSE;
        }
    }
    return TRUE;
}

/**
 * Checks if a valid reCaptcha v2 was sent
 * 
 * @return boolean
 *      TRUE if a valid reCaptcha was sent
 *      FALSE else
 */
function recaptcha_is_valid() {
    global $config;
    if (!isset($_POST['g-recaptcha-response'])) {
        return FALSE;
    }
    $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($config->get("ommp.recaptcha_secret")) . '&response=' . urlencode($_POST['g-recaptcha-response']);
    $response = file_get_contents($url);
    $response_keys = json_decode($response, true);
    return isset($response_keys['success']) && $response_keys['success'];
}

/**
 * Checks if a string starts with another
 * 
 * @param string $string The string into we want to search
 * @param string $startString The string we want to search
 * 
 * @return boolean
 * 		TRUE if $string starts with $startString
 * 		FALSE else
 */
function startsWith($string, $startString) {
    return substr($string, 0, strlen($startString)) === $startString;
}

/**
 * Checks if a string ends with another
 * 
 * @param string $string The string into we want to search
 * @param string $endString The string we want to search
 * 
 * @return boolean
 * 		TRUE if $string ends with $endString
 * 		FALSE else
 */
function endsWith($string, $endString) {
    $len = strlen($endString);
    return $len == 0 || substr($string, -$len) === $endString;
}

/**
 * Removes a directory and all its content
 * 
 * @source
 * 		https://stackoverflow.com/a/3338133
 * 
 * @param string $dir
 * 		The directory to remove
 */
function rrmdir($dir) { 
	if (is_dir($dir)) {
		$objects = scandir($dir);
			foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object)) {
					rrmdir($dir. DIRECTORY_SEPARATOR .$object);
				} else {
					unlink($dir. DIRECTORY_SEPARATOR .$object);
				}
			}
		}
		rmdir($dir);
	}
}