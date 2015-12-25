var applicationID = 'FC3FEC62';
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
};

function stop_click() {
    console.log ("stop");
    session.stop(function () {console.log ("stopped");}, onError);
}

function sendMessage (message) {
    if (session == null) {
	chrome.cast.requestSession (function (e) {
	    session = e;
	    sendMessage2 (message);
	});
    } else {
	sendMessage2 (message);
    }
}

function sendMessage2 (message) {
    console.log ("sending " + message);
    session.sendMessage(namespace, message, nop, onError);
}

function do_submit () {
    var val = $("#sender_data").val();
    sendMessage (val);
}

$(function () {
    $("#sender_form").submit(do_submit);
    $("#stop_button").click (stop_click);
});
