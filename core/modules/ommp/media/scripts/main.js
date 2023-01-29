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
                callback(JSON.parse(this.responseText));
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
 * @param {*} callback The function to call after upload (response from server will be passed as a parameter)
 */
function createFileUpload(id, file, buttonValue, url, callback) {
	// Create form and controls
	$('#' + id).html('<form method="post" action="" enctype="multipart/form-data"><input type="file" class="form-control" style="width:70%;display:inline-block;" type="text" id="file-' + id + '" name="file-' + id + '" />' +
	'<input type="button" class="btn pt-1 pb-1 mt-2 ms-2 me-2 btn-light" style="vertical-align:baseline;" value="' + escapeHtml(buttonValue) + '" id="upload-' + id + '"></form>');
	// Manage file upload
	$('#upload-' + id).click(() => {
		var fd = new FormData();
		var files = $('#file-' + id)[0].files[0];
		fd.append(file, files);
		fd.append('skh', ommp_session_key_hmac);
		$.ajax({
			url: url,
			type: 'post',
			data: fd,
			contentType: false,
			processData: false,
			success: callback,
		});
	});
}