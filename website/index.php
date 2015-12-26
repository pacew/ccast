<?php

$anon_ok = 1;
require_once ("common.php");


$extra_scripts .= "<script type='text/javascript' src='cast-send.js'>"
	."</script>\n";


pstart ();

$csrf_safe = 1;

$tick = get_ticker ();
$body .= sprintf ("<div>tick = %d</div>\n", $tick);

$body .= "<div>\n";
$body .= mklink ("google cast console",
		 "https://cast.google.com/publish/#/overview");
$body .= "</div>\n";

$body .= "<div>\n";
$url = sprintf ("%sreceiver.php", $_SERVER['ssl_url']);
$body .= mklink ($url, $url);
$body .= "</div>\n";

$body .= "<div>\n";
$body .= "<form method='get' action='#' id='sender_form'>\n";
$body .= "<input id='sender_data' type='text' size='30' />\n";
$body .= "</form>\n";

$body .= "<input id='stop_button' type='button' value='Stop' />\n";


$body .= "</div>\n";

$body .= "<div>\n";
$body .= "<a id='receiver_console_link' href='#' target='_blank'>"
	." receiver console</a>\n";
$body .= "</div>\n";

pfinish ();

