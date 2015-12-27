if (window.sender_args == null || sender_args['cast_app_id'] == null) {
    console.log ("sender_args not set");
}

var applicationID = sender_args['cast_app_id'];
console.log (applicationID);
var namespace = 'urn:x-cast:com.google.cast.sample.helloworld';
var session = null;

if (chrome.cast && chrome.cast.isAvailable) {
    console.log ("fast load");
    initializeCastApi ();
} else {
    window['__onGCastApiAvailable'] = function(loaded, errorInfo) {
	if (loaded) {
	    console.log ("delayed load");
	    initializeCastApi();
	} else {
	    console.log(errorInfo);
	}
    };
}

function initializeCastApi() {
  var sessionRequest = new chrome.cast.SessionRequest(applicationID);
  var apiConfig = new chrome.cast.ApiConfig(sessionRequest, sessionListener, receiverListener);

  chrome.cast.initialize(apiConfig, onInitSuccess, onError);
};

function onInitSuccess() {
  console.log("onInitSuccess");
}

function onError(message) {
  console.log("onError: "+JSON.stringify(message));
}

function nop () { }

function receiverListener(e) {
    if( e === 'available' ) {
	console.log ("receiver found");
    } else {
	console.log ("receiver list empty");
    }
}

function sessionListener(e) {
  console.log('New session ID:' + e.sessionId);
  session = e;
  session.addUpdateListener(sessionUpdateListener);  
  session.addMessageListener(namespace, receiverMessage);
}

function sessionUpdateListener(isAlive) {
  var message = isAlive ? 'Session Updated' : 'Session Removed';
  message += ': ' + session.sessionId;
    console.log (message);
  if (!isAlive) {
    session = null;
  }
};

function receiverMessage(namespace, message) {
    console.log ("receiverMessage: "+namespace+", "+message);
    try {
	var msg = JSON.parse (message);
	console.log (msg);
	if (msg)
	    do_msg (msg);
    } catch (e) {
	console.log (["json parse error", e]);
    }
};

var receiver_ipaddr;

function do_msg (msg) {
    console.log (["do_msg", msg]);

    if (msg.op == "receiver_ipaddr") {
	receiver_ipaddr = msg.ipaddr;
	var url = "http://" + receiver_ipaddr + ":9222";
	console.log ("receiver_ipaddr " + url);
	$("#receiver_console_link").attr ("href", url);
	$("#receiver_console_link").html(url);

    }
}

function stop_click() {
    console.log ("stop");
    session.stop(function () {console.log ("stopped");}, onError);
}

function sendMessage (msg) {
    if (session == null) {
	chrome.cast.requestSession (function (e) {
	    session = e;
	    sendMessage2 (JSON.stringify (msg));
	});
    } else {
	sendMessage2 (JSON.stringify (msg));
    }
}

function sendMessage2 (message) {
    console.log ("sending " + message);
    session.sendMessage(namespace, message, nop, onError);
}

function do_submit () {
    var msg = {
	"op": "set_text",
	"val": $("#sender_data").val()
    };
    sendMessage (msg);
}

function reload_receiver_click () {
    console.log ("reload receiver");
    var msg = { "op": "reload_receiver" };
    sendMessage (msg);
}

function game_left_click () {
    console.log ("game left click");
    var msg = {
	"op": "game",
	"val": "left"
    };
    sendMessage (msg);
}

$(function () {
    $("#sender_form").submit(do_submit);
    $("#stop_button").click (stop_click);
    $("#reload_receiver_button").click (reload_receiver_click);
    $("#game_left_button").click (game_left_click);
});
