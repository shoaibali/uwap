
/**
 * @package UWAP
 * @description The core UWAP javascript library communicates with the UWAP server using the REST API.
 * @author Andreas Åkre Solberg
 * @copyright UNINETT AS
 * @version 1.0
 */

<?php

require_once(dirname(dirname(dirname(__FILE__))) . '/lib/autoload.php');
header('Content-Type: application/javascript');

?>

UWAP = {};
UWAP.utils = {};
UWAP.utils.hostname = '<?php echo GlobalConfig::hostname(); ?>';
UWAP.utils.scheme = '<?php echo GlobalConfig::scheme(); ?>';

UWAP.utils.addQueryParam = function (url, key, value) {
	var delimiter = ((url.indexOf('?') != -1) ? '&' : '?');
	if (url.charAt(url.length-1) === '?') {
		delimiter = '';
	}
	return url + delimiter + encodeURIComponent(key) + '=' + encodeURIComponent(value);
};
UWAP.utils.goAndReturn = function(url) {
	console.log("About to redirect to: " + UWAP.utils.addQueryParam(url, 'return', document.URL));
	var base = UWAP.utils.scheme + '://core.' + UWAP.utils.hostname + '/';
	window.location = UWAP.utils.addQueryParam(url, 'return', document.URL);	
}

UWAP.utils.getURL = function(sub, path) {
	var base = UWAP.utils.scheme + '://' + sub + '.' + UWAP.utils.hostname + '';
	return base + path;
}

UWAP.token = null;

UWAP.messenger = {};
UWAP.messenger.send = function(msg) {
	if (UWAP.messenger.receiver) {
		UWAP.messenger.receiver(msg);
	} else {
		console.error("Could not deliver message from iframe, because listener was not setup.");
	}

};


var redirect_uri = window.location.protocol + '//' + window.location.hostname + 
	window.location.pathname;


console.log("REdirect URI is " + redirect_uri);

jso_configure({
	"uwap": {
		client_id: "app_test",
		authorization: "http://core.app.bridge.uninett.no/api/oauth/authorization",
		redirect_uri: redirect_uri,
	},
	"uwapvoot": {
		client_id: "voottest1",
		authorization: "http://core.app.bridge.uninett.no/api/oauth/authorization",
		redirect_uri: redirect_uri
	}
});



/**
 * A generic protocol request wrapper function.
 * @param  {string}   method        The HTTP Method to use. GET is default
 * @param  {string}   url           The relative URL
 * @param  {object}   data          Optionally an object to send.
 * @param  {object}   options       A set of options
 * @param  {Function} callback      Success callback
 * @param  {Function} errorcallback Error callback
 * @return {void}                 Returns undefined
 */
UWAP._request = function(method, url, data, options, callback, errorcallback) {
	method = method || 'GET';

	var ar = {
		type: method,
		url: url,
		dataType: 'json',
		jso_provider: "uwap",
		jso_allowia: true,
		success: function(result, textStatus, jqXHR) {
			// console.log('Response _request response reviced()');
			// console.log(result);
			if (result.status === 'ok') {
				if  (typeof callback === 'function') {
					callback(result.data);
				}
			} else {
				if  (typeof errorcallback === 'function') {
					errorcallback(result.message);
				}
				console.error('Data request error (server side): ' + result.message);
			}

		},
		error: function(err) {
			if  (typeof errorcallback === 'function') {
				errorcallback(err.responseText + '(' + err.status + ')');
			}
			console.error('Data request error (client side): ', err);
		}
	};

	if (data) {
		ar.data = JSON.stringify(data);
		ar.processData = false;
		ar.dataType = 'json';
	}

	if (typeof options === 'object') {
		for(var key in options) {
			if (options.hasOwnProperty(key)) {
				ar[key] = options[key];
			}
		}
	}
	try {
		$.oajax(ar);
	} catch(exception) {
		errorcallback(exception);
	}

};


UWAP.auth = {

	require: function (callbackSuccess) {
		UWAP._request(
			'GET', 
			UWAP.utils.getURL("core", "/api/userinfo"),
			null, null, callbackSuccess);		
	},
	check: function (callbackSuccess, callbackNo) {
		UWAP._request(
			'GET', 
			UWAP.utils.getURL("core", "/api/userinfo"),
			null, {
				"jso_allowia": false
			}, callbackSuccess, callbackNo);
	},

	// TODO: Upgrade to support OAUTH
	checkPassive: function (callbackSuccess, callbackNo) {

		$.getJSON('/_/api/auth.php', function(data, textStatus, jqXHR) {
			// console.log('Response auth check()');
			// console.log(data);
			if (data.status === 'ok') {
				callbackSuccess(data.user);
			} else {
				
				UWAP.messenger.receiver = function(msg) {

					if (msg.type === "passiveAuth" && msg.status === "success") {

						UWAP.auth.check(callbackSuccess, callbackNo)

					} else {
						callbackNo();
					}

					// console.log("Received response. Juhu ", msg);
					delete UWAP.messenger.receiver;
					// $("body iframe.uwap_messenger_iframe").remove();

				};
				$("body").prepend('<iframe class="uwap_messenger_iframe" style="display: none" src="/_/login?passive=true"></iframe>');

			}

		});

	}

};

UWAP.store = {
	save: function(object, callback, errorcallback) {
		UWAP._request(
			'POST', UWAP.utils.getURL("core", "/api/store"),
			{
				op: "save",
				object: object
			}, 
			null, callback, errorcallback);
	},
	remove: function(object, callback, errorcallback) {
		UWAP._request(
			'POST', UWAP.utils.getURL("core", "/api/store"),
			{
				op: "remove",
				object: object
			}, 
			null, callback, errorcallback);
	},
	queryOne: function(query, callback, errorcallback) {
		UWAP._request(
			'POST', UWAP.utils.getURL("core", "/api/store"),
			{
				op: "queryOne",
				query: query
			}, 
			null, callback, errorcallback);
	},
	queryList: function(query, callback, errorcallback) {
		UWAP._request(
			'POST', UWAP.utils.getURL("core", "/api/store"),
			{
				op: "queryList",
				query: query
			}, 
			null, callback, errorcallback);
	}
};



UWAP.groups = {
	listMyOwnGroups: function(callback, errorcallback) {
		UWAP._request(
		 	'GET', 
		 	'/_/api/groups.php/groups?filter=admin',
		 	null,
		 	null, callback, errorcallback);
	},
	listMyGroups: function(callback, errorcallback) {
		UWAP._request(
		 	'GET', 
		 	'/_/api/groups.php/groups',
		 	null,
		 	null, callback, errorcallback);
	},
	addGroup: function(object, callback, errorcallback) {
		UWAP._request(
		 	'POST', 
		 	'/_/api/groups.php/groups',
		 	object, 
		 	null, callback, errorcallback);
	},
	removeGroup: function(groupid, callback, errorcallback) {
		 UWAP._request(
		 	'DELETE', 
		 	'/_/api/groups.php/group/' + groupid,
		 	null,
		 	null, callback, errorcallback);
	},
	get: function(groupid, callback, errorcallback) {
		 UWAP._request(
		 	'GET', 
		 	'/_/api/groups.php/group/' + groupid,
		 	null,
		 	null, callback, errorcallback);
	},
	addMember: function(groupid, user, callback, errorcallback) {
		 UWAP._request(
		 	'POST', 
		 	'/_/api/groups.php/group/' + groupid + '/members',
		 	user, 
		 	null, callback, errorcallback);
	},
	removeMember: function(groupid, userid, callback, errorcallback) {
		 UWAP._request(
		 	'DELETE', 
		 	'/_/api/groups.php/group/' + groupid + '/member/' + userid,
		 	null,
		 	null, callback, errorcallback);
	}

};

UWAP.logs = {
	get: function(after, filters, callback, errorcallback) {

		filters = filters || [];
		// console.log("Fitlers", filters);
		UWAP._request(
		 	'GET', 
		 	'/_/api/logs.php?after=' + after + '&filters=' + encodeURIComponent(JSON.stringify(filters)),
		 	null,
		 	null, callback, errorcallback);
	}
}


UWAP.appconfig = {
	list: function(callback, errorcallback) {
		
		$.ajax({
			type: 'GET',
			url: '/_/api/appconfig.php/apps',
			dataType: 'json',
			// data: JSON.stringify({ "command": "on" }),
			// processData: false,
			success: function(result, textStatus, jqXHR) {
				// console.log('Response appconfig get()');
				// console.log(result);
				if (result.status === 'ok') {
					callback(result.data);
				} else {
					if  (typeof errorcallback === 'function') {
						errorcallback(result.message);
					}
					// console.log('Data request error (server side): ' + result.message);
				}

			},
			error: function(err) {
				if  (typeof errorcallback === 'function') {
					errorcallback(err);
				}
				// console.log('Data request error (client side): ' + err);
			}
		});

	},

	store: function(object, callback, errorcallback) {

		$.ajax({
			type: 'POST',
			url: '/_/api/appconfig.php/apps',
			dataType: 'json',
			contentType: "application/json",
			data: JSON.stringify(object),
			processData: false,
			success: function(result, textStatus, jqXHR) {
				// console.log('Response data save()');
				// console.log(result);
				if (result.status === 'ok') {
					callback(result.data);
				} else {
					if  (typeof errorcallback === 'function') {
						errorcallback(result.message);
					}
					// console.log('Data request error (server side): ' + result.message);
				}

			},
			error: function(err) {
				if  (typeof errorcallback === 'function') {
					errorcallback(err);
				}
				// console.log('Data request error (client side): ' + err);
			}
		});

	},
	updateStatus: function(id, object, callback, errorcallback) {
		 UWAP._request(
		 	'POST', 
		 	'/_/api/appconfig.php/app/' + id + '/status',
		 	object, 
		 	null, callback, errorcallback);
	},
	bootstrap: function(id, template, callback, errorcallback) {
		UWAP._request(
			'POST', 
			'/_/api/appconfig.php/app/' + id + '/bootstrap',
			template, 
			null, callback, errorcallback);
	},

	updateAuthzHandler: function(id, object, callback, errorcallback) {
		$.ajax({
			type: 'POST',
			url: '/_/api/appconfig.php/app/' + id + '/authorizationhandler/' + object.id,
			dataType: 'json',
			contentType: "application/json",
			data: JSON.stringify(object),
			processData: false,
			success: function(result, textStatus, jqXHR) {
				// console.log('Response data save()');
				// console.log(result);
				if (result.status === 'ok') {
					callback(result.data);
				} else {
					if  (typeof errorcallback === 'function') {
						errorcallback(result.message);
					}
					// console.log('Data request error (server side): ' + result.message);
				}

			},
			error: function(err) {
				if  (typeof errorcallback === 'function') {
					errorcallback(err);
				}
				// console.log('Data request error (client side): ' + err);
			}
		});

	},
	deleteAuthzHandler: function(appid, objectid, callback, errorcallback) {
		
		$.ajax({
			type: 'DELETE',
			url: '/_/api/appconfig.php/app/' + appid + '/authorizationhandler/' + objectid,
			dataType: 'json',
			contentType: "application/json",
			// data: JSON.stringify(object),
			// processData: false,
			success: function(result, textStatus, jqXHR) {
				// console.log('Response data save()');
				// console.log(result);
				if (result.status === 'ok') {
					callback(result.data);
				} else {
					if  (typeof errorcallback === 'function') {
						errorcallback(result.message);
					}
					// console.log('Data request error (server side): ' + result.message);
				}

			},
			error: function(err) {
				if  (typeof errorcallback === 'function') {
					errorcallback(err);
				}
				// console.log('Data request error (client side): ' + err);
			}
		});

	},
	check: function(id, callback, errorcallback) {

		$.getJSON('/_/api/appconfig.php/check/' + id, null, function(result, textStatus, jqXHR) {
			// console.log('Response apiconfig check');
			// console.log(result);
			if (result.status === 'ok') {
				callback(result.data);
			} else {
				if  (typeof errorcallback === 'function') {
					errorcallback(result.message);
				}
				// console.log('Data request error (server side): ' + result.message);
			}

		}, function(err) {
			if  (typeof errorcallback === 'function') {
				errorcallback(err);
			}
			// console.log('Data request error (client side): ' + err);
		});
	},
	get: function(id, callback, errorcallback) {

		$.getJSON('/_/api/appconfig.php/app/' + id, null, function(result, textStatus, jqXHR) {
			// console.log('Response data queryOne()');
			// console.log(result);
			if (result.status === 'ok') {
				callback(result.data);
			} else {
				if  (typeof errorcallback === 'function') {
					errorcallback(result.message);
				}
				// console.log('Data request error (server side): ' + result.message);
			}

		}, function(err) {
			if  (typeof errorcallback === 'function') {
				errorcallback(err);
			}
			// console.log('Data request error (client side): ' + err);
		});
	}
};

UWAP.applisting = {
	list: function(callback, errorcallback) {

		$.getJSON('/_/api/applisting.php', {}, function(result, textStatus, jqXHR) {
			// console.log('Response applisting get()');
			// console.log(result);
			if (result.status === 'ok') {
				callback(result.data);
			} else {
				if  (typeof errorcallback === 'function') {
					errorcallback(result.message);
				}
				// console.log('Data request error (server side): ' + result.message);
			}

		}, function(err) {
			if  (typeof errorcallback === 'function') {
				errorcallback(err);
			}
			// console.log('Data request error (client side): ' + err);
		});
	}
};

UWAP.data = {

	get: function (url, options, callback, errorcallback) {

		options = options || {};
		options.url = url;
		options.returnTo = window.location.href;



		$.getJSON('/_/api/data.php', {args: JSON.stringify(options)}, function(result, textStatus, jqXHR) {
			// console.log('Response data get()');
			// console.log(result);
			if (result.status === 'ok') {
				callback(result.data);
			} else if (result.status === 'redirect') {
				window.location.href = result.url;
			} else {
				if  (typeof errorcallback === 'function') {
					errorcallback(result.message);
				}
				// console.log('Data request error (server side): ' + result.message);
			}

		}, function(err) {
			if  (typeof errorcallback === 'function') {
				errorcallback(err);
			}
			// console.log('Data request error (client side): ' + err);
		});
	}

	
};


