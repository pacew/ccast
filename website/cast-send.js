var applicationID = 'FC3FEC62';
var namespace = 'urn:x-cast:com.google.cast.sample.helloworld';
var session = null;

if (!chrome.cast || !chrome.cast.isAvailable) {
  setTimeout(initializeCastApi, 1000);
}

function initializeCastApi() {
  var sessionRequest = new chrome.cast.SessionRequest(applicationID);
  var apiConfig = new chrome.cast.ApiConfig(sessionRequest,
    sessionListener,
    receiverListener);

  chrome.cast.initialize(apiConfig, onInitSuccess, onError);
};

function onInitSuccess() {
  console.log("onInitSuccess");
}

function onError(message) {
  console.log("onError: "+JSON.stringify(message));
}

function onSuccess(message) {
  console.log("onSuccess: "+message);
}

function onStopAppSuccess() {
  console.log('onStopAppSuccess');
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

function receiverListener(e) {
  if( e === 'available' ) {
    console.log ("receiver found");
  }
  else {
    console.log ("receiver list empty");
  }
}

function stopApp() {
  session.stop(onStopAppSuccess, onError);
}

function sendMessage(message) {
  if (session!=null) {
    session.sendMessage(namespace, message, onSuccess.bind(this, "Message sent: " + message), onError);
  }
  else {
    chrome.cast.requestSession(function(e) {
        session = e;
        session.sendMessage(namespace, message, onSuccess.bind(this, "Message sent: " + message), onError);
      }, onError);
  }
}

function do_submit () {
    var val = $("#sender_data").val();
    sendMessage (val);
}

$(function () {
    $("#sender_form").submit(do_submit);
});
