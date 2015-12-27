var last_sender_id;

window.onload = function() {
    cast.receiver.logger.setLevelValue(0);
    window.castReceiverManager = cast.receiver.CastReceiverManager.getInstance();
    console.log('Starting Receiver Manager');
        
    // handler for the 'ready' event
    castReceiverManager.onReady = function(event) {
        console.log('Received Ready event: ' + JSON.stringify(event.data));
        window.castReceiverManager.setApplicationState("Application status is ready...");
    };
        
    // handler for 'senderconnected' event
    castReceiverManager.onSenderConnected = function(event) {
        console.log('Received Sender Connected event: ' + event.data);
        console.log(window.castReceiverManager.getSender(event.data).userAgent);
    };
        
    // handler for 'senderdisconnected' event
    castReceiverManager.onSenderDisconnected = function(event) {
        console.log('Received Sender Disconnected event: ' + event.data);
        if (window.castReceiverManager.getSenders().length == 0) {
	    window.close();
	}
    };
        
    // handler for 'systemvolumechanged' event
    castReceiverManager.onSystemVolumeChanged = function(event) {
        console.log('Received System Volume Changed event: ' + event.data['level'] + ' ' +
		    event.data['muted']);
    };
    
    // create a CastMessageBus to handle messages for a custom namespace
    window.messageBus =
        window.castReceiverManager.getCastMessageBus(
            'urn:x-cast:com.google.cast.sample.helloworld');
    
    // handler for the CastMessageBus message event
    window.messageBus.onMessage = function(event) {
	last_sender_id = event.senderId;
	var msg_str = event.data;
	console.log (msg_str);
	var msg = null;
	try {
	    msg = JSON.parse (msg_str);
	} catch (e) {
	    console.log (["rcv parse error", e]);
	}

	if (msg.op == "set_text") {
            displayText(msg.val);
            window.messageBus.send(event.senderId, msg.val);
	} else if (msg.op == "reload_receiver") {
	    console.log ("reload_receiver");
	    window.location = window.location.href;
	} else if (msg.op == "game") {
	    console.log ("game msg");
	    rapp_rcv_msg (msg);
	}
    };

    // initialize the CastReceiverManager with an application status message
    window.castReceiverManager.start({statusText: "Application is starting"});
    console.log('Receiver Manager started');
};

// utility function to display the text message in the input field
function displayText(text) {
    console.log(text);
    document.getElementById("message").innerHTML=text;
    window.castReceiverManager.setApplicationState(text);
    get_my_ip_addr ();
};

/* http://stackoverflow.com/questions/20194722/can-you-get-a-users-local-lan-ip-address-via-javascript */
function get_my_ip_addr () {
    window.RTCPeerConnection = window.RTCPeerConnection
	|| window.mozRTCPeerConnection
	|| window.webkitRTCPeerConnection;   //compatibility for firefox and chrome
    var pc = new RTCPeerConnection({iceServers:[]}),
	noop = function(){};      
    pc.createDataChannel("");    //create a bogus data channel

    // create offer and set local description
    pc.createOffer(pc.setLocalDescription.bind(pc), noop);    
    pc.onicecandidate = function(ice){  //listen for candidate events
	if(!ice || !ice.candidate || !ice.candidate.candidate)  return;
	console.log (ice.candidate.candidate);
	var myIP = /([0-9]{1,3}(\.[0-9]{1,3}){3}|[a-f0-9]{1,4}(:[a-f0-9]{1,4}){7})/.exec(ice.candidate.candidate)[1];
	console.log('my IP: ', myIP);   
	console.log(["last sender id", last_sender_id]);
	if (last_sender_id) {
	    var msg = {};
	    msg.op = "receiver_ipaddr";
	    msg.ipaddr = myIP;
            window.messageBus.send(last_sender_id, JSON.stringify (msg));
	}
	pc.onicecandidate = noop;
    };
}
