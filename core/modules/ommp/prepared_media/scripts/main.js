class Api {

    constructor() {}

    /**
     * Prepare parameters for POST request
     * @param {*} parameters A JSON of parameters
     * @returns A string representing the form-urlencoded parameters
     */
    static prepareParameters(parameters) {
        var urlEncodedDataPairs = []
        for (var key in parameters) {
            urlEncodedDataPairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(parameters[key]));
        }
        return urlEncodedDataPairs.join('&').replace(/%20/g, '+');
    }

    /**
     * Send a request to the API
     * @param {*} module The name of the module
     * @param {*} action The action to perform
     * @param {*} params A JSON of parameters for the given action
     * @param {*} callback The callback function
     */
    static apiRequest(module, action, params, callback) {
        params['skh'] = ommp_session_key_hmac;
        var preparedParams = Api.prepareParameters(params);
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                // Try to decode JSON
                try {
                    var json = JSON.parse(this.responseText);
                } catch (e) {
                    console.error(e);
                    notifError('{JS:L:INVALID_API_JSON}', '{JS:L:ERROR}');
                    return;
                }
                // Try to execute callback
                try {
                    callback(json);
                } catch (e) {
                    console.error(e);
                    notifError('{JS:L:API_CALLBACK_ERROR}', '{JS:L:ERROR}');
                    return;
                }
            }
        };
        xhttp.open("POST", ommp_dir + 'api/' + module + '/' + action + '?r=' + Math.random(), true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send(preparedParams);
    }

}

/**
 * Escape the HTML special charaters in a string
 * @param {*} text The string to escape
 * @returns The escaped text
 */
function escapeHtml(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

/**
 * Escape a text to be put in an HTML property
 * @param {*} text The text to escape
 * @param {*} js Should we escape simple quote for JavaScript strings? (optional, default is false)
 * @return The escaped string
 */
function escapeHtmlProperty(text, js=false) {
    var map = {
        '"': '&quot;',
        '&': '&amp;'
    }, re = /["&]/g;
    if (js) {
        map["'"] = "\\'";
        re = /["'&]/g
    }
    return text.replace(re, m => map[m]);
}

/**
 * Displays a notification on the screen
 * @param {*} body The HTML content of the notification
 * @param {*} header The header of the notification
 */
function notif(body, header=null) {
    bootstrap.showToast({
        body: body,
        header: header,
        position: 'bottom-0 end-0'
    });
}

/**
 * Displays an error notification on the screen
 * @param {*} body The HTML content of the notification
 * @param {*} header The header of the notification
 */
function notifError(body, header=null) {
    bootstrap.showToast({
        body: body,
        toastClass: 'text-bg-danger',
        header: header,
        position: 'bottom-0 end-0'
    });
}

/**
 * Displays a prompt with two buttons
 * @param {*} body The HTML content of the prompt
 * @param {*} button1 The content of the button 1
 * @param {*} button2 The content of the button 2
 * @param {*} callback1 The callback on button 1 click
 * @param {*} callback2 The callback on button 2 click
 * @param {*} header The header of the notification
 */
function promptChoice(body, button1, button2, callback1, callback2, header=null) {
    var toast = bootstrap.showToast({
        body: '<p>' + body + '</p><div><button class="btn btn-primary me-1 btn-sm" data-bs-dismiss="toast">' + escapeHtml(button1) + '</button><button class="btn btn-secondary btn-sm" data-bs-dismiss="toast">' + escapeHtml(button2) +'</button></div>',
        delay: Infinity,
        header: header,
        position: 'bottom-0 end-0'
    });
    toast.element.querySelector(".btn-primary").addEventListener("click", callback1);
    toast.element.querySelector(".btn-secondary").addEventListener("click", callback2);
}

/**
 * Format the name of a user to display
 * 
 * @param {*} username The username
 * @param {*} longname The long name of the user
 * @param {*} long Should we display the username after the long name? Optional, default is false
 * @param {*} escape Should we escape HTML characters? Optional, default is true
 */
function formatUsername(username, longname, long=false, escape=true) {
	var result = long && longname != '' ? (longname + ' (' + username + ')') : (longname == '' ? username : longname);
	return escape ? escapeHtml(result) : result;
}

/**
 * Create a file upload form
 * 
 * @param {*} id The id of the container element
 * @param {*} file The name of the file for the POST request
 * @param {*} buttonValue The text to use in the upload button
 * @param {*} url The URL of the page that will receive the file
 * @param {*} callback The function to call after upload (XHR response and status of the request will be passed as parameters)
 * @param {*} parameters Additional parameters to the POST request (optional, empty object by default)
 */
function createFileUpload(id, file, buttonValue, url, callback, parameters={}) {
	// Create form and controls
	$('#' + id).html('<form method="post" action="" enctype="multipart/form-data"><input type="file" class="form-control" style="width:70%;display:inline-block;" type="text" id="file-' + id + '" name="file-' + id + '" />' +
	'<input type="button" class="btn pt-1 pb-1 mt-2 ms-2 me-2 btn-light" style="vertical-align:baseline;" value="' + escapeHtml(buttonValue) + '" id="upload-' + id + '" />' +
    '<span id="upload-percent-' + id +'" style="display:none;" class="ms-4">0 %<span></form>');
	// Manage file upload
	$('#upload-' + id).click(() => {
		// Prepare form
        var fd = new FormData();
		var files = $('#file-' + id)[0].files[0];
		fd.append(file, files);
		fd.append('skh', ommp_session_key_hmac);
        for (const [key, value] of Object.entries(parameters)) {
            fd.append(key, value);
        }
        // Prepare upload status
        $('#upload-' + id).hide();
        $('#upload-percent-' + id).show();
		$.ajax({
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = (evt.loaded / evt.total) * 100;
                        $('#upload-percent-' + id).html(Math.floor(percentComplete) + ' %');
                    }
                }, false);
                return xhr;
            },
            beforeSend: (xhr, settings) => {
                // Check the file size
                if (settings.data.get(file).size > parseInt('{JS:S:MAX_UPLOAD}')) {
                    // Display error
                    notifError('{JS:L:FILE_TOO_LARGE_FOR_UPLOAD}', '{JS:L:ERROR}');
                    // Clean uploader
                    createFileUpload(id, file, buttonValue, url, callback, parameters);
                    // Cancel upload
                    return false;
                }
            },
			url: url,
			type: 'post',
			data: fd,
			contentType: false,
			processData: false,
			complete: (result, status) => {
                // Clean uploader
                createFileUpload(id, file, buttonValue, url, callback, parameters);
                // Call callback
                callback(result, status);
            },
		});
	});
}

/**
 * Internal fonction to detect the click outside the popup
 * WARNING: Not to be called directly
 * @param {*} event The click event
 */
function popupClickDetect(event) {
    // If user clicks inside the popup, do nothing
    if (document.querySelector('#popup-container').contains(event.target)) {
        return;
    }
    // If user clicks outside the popup, hide it!
    closePopup();
};

/**
 * Internal fonction to detect escape touch press to close the popup
 * WARNING: Not to be called directly
 * @param {*} event The keyup event
 */
function popupEscapeDetect(event) {
    if (event.key == 'Escape') {
        closePopup();
    }
}

/**
 * Displays a popup
 * @param {*} title The title of the popup (not escaped)
 * @param {*} content The HTML content of the popup (not escaped)
 * @param {*} center Center the content? (optional, default is false)
 * @param {*} onClose A callback function to call when the popup is closed (optional)
 */
function popup(title, content, center=false, onClose=null) {
    // Close the popup if opened
    closePopup();
	// Create the popup
    $('body').append('<div id="popup"><div id="popup-container"><div id="popup-title">' + title +
    ' <img src="{JS:S:DIR}media/ommp/images/close.svg" alt="[X]" title="{JS:L:CLOSE}" onclick="closePopup();" /></div><div id="popup-content"' + (center ? ' style="text-align:center;"' : '') + '>' + content + '</div></div></div>');
    // Detect all clicks on the document
    setTimeout(() => {
        document.addEventListener('click', popupClickDetect);
        document.addEventListener('keyup', popupEscapeDetect);
    }, 100); // Wait before activating the listeners because they can be triggered immediatly if they are set right now
    // Save the close callback
    popupCloseCallback = onClose;
}

/**
 * Close the openend popup
 */
function closePopup() {
    // Execute the callback before closing (if the function need to use content from the popup)
    if (popupCloseCallback !== null) {
        popupCloseCallback();
        // Clear the callback
        popupCloseCallback = null;
    }
    // Close the popup
	$('#popup').remove();
    document.removeEventListener('click', popupClickDetect);
    document.removeEventListener('click', popupEscapeDetect);
}

/**
 * Return the value of a cookie based on its name
 * @param {*} cname The name of the cookie
 * @returns The value of the cookie
 * @couce https://www.w3schools.com/js/js_cookies.asp
 */
function getCookie(cname) {
    let name = cname + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for (let i = 0; i <ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}

// Fix negative modulo (thanks JavaScript)
Number.prototype.mod = function(n) {
	return ((this % n) + n) % n;
}

// Function to call on popup close
let popupCloseCallback = null;