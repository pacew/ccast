function rapp_init () {
    $("body").html ("<div>hello</div>");
}

function rapp_rcv_msg (msg) {
    if (msg.val == "left") {
	$("body").html ("got left");
    } else {
	$("body").html (JSON.stringify (msg));
    }
}

$(function () {
    rapp_init ();
});
