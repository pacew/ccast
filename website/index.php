<?php

$anon_ok = 1;
require_once ("common.php");

$app_db = array ();

/* first elt is the google account where the app id was created */
$app_db['https://k.pacew.org:10898/receiver.php'] =
	array ("pace.willisson@gmail.com", "FC3FEC62");

$app_db['https://k.pacew.org:7884/receiver.php'] =
	array ("pace.willisson@gmail.com", "C116E951");

$app_db['https://k.pacew.org:7882/receiver.php'] =
	array ("ericwillisson@gmail.com", "1FC4F174");

$app_db['http://k.pacew.org:7883/receiver.php'] =
	array ("ericwillisson@gmail.com", "A7CE4000");

$app_db['https://d6.pacew.org:7884/receiver.php'] =
	array ("pace.willisson@gmail.com", "1BF75029");

pstart ();
$csrf_safe = 1;

$receiver_url = sprintf ("%sreceiver.php", $_SERVER['ssl_url']);

if (($app_info = @$app_db[$receiver_url]) == NULL) {
	$body .= "<h1>missing application id</h1>\n";
	$body .= "<p>\n";
	$body .= "go to ";
	$url = "https://cast.google.com/publish/#/overview";
	$body .= mklink_nw ($url, $url);
	$body .= " then click Add New Application, then Custom Receiver.\n";
	$body .= "Give a name of your choice and for the url, enter:\n";
	$body .= "</p>\n";
	$body .= "<p>\n";
	$body .= sprintf ("<input type='text' readonly='readonly'"
			  ." size='50' value='%s' />\n",
			  h($receiver_url));
	$body .= "</p>\n";
	$body .= "<p>\n";
	$body .= "After you Save, you'll get an application id.  Add it"
		." to the array \$app_db at the top of index.php\n";
	$body .= "</p>\n";
	pfinish ();
}

$tick = get_ticker ();
$body .= sprintf ("<div>tick = %d</div>\n", $tick);

$body .= "<div>\n";
$body .= mklink_nw ("google cast console",
		 "https://cast.google.com/publish/#/overview");
$body .= "</div>\n";

$body .= "<div>\n";
$body .= mklink_nw ($receiver_url, $receiver_url);
$body .= "</div>\n";

$body .= "<div>\n";
$body .= "<form method='get' action='#' id='sender_form'>\n";
$body .= "<input id='sender_data' type='text' size='30' />\n";
$body .= "</form>\n";

$body .= "<input id='stop_button' type='button' value='Stop' />\n";

$body .= "<input id='reload_receiver_button' type='button'"
	." value='Reload receiver' />\n";
$body .= "<input id='reconnect_button' type='button'"
	." value='Reconnect to jrconsole' />\n";

$body .= "<input id='game_up_button' type='button'"
	." value='up' />\n";
$body .= "<input id='game_down_button' type='button'"
	." value='down' />\n";
$body .= "<div id='mousectl'></div>\n";

$body .= "</div>\n";

$body .= "<div>\n";
$body .= "<a id='receiver_console_link' href='#' target='_blank'>"
	." receiver console</a>\n";
$body .= "</div>\n";


$sender_args = array ();
$sender_args['cast_app_id'] = $app_info[1];

$extra_scripts .= "<script type='text/javascript'>\n";
$extra_scripts .= sprintf ("var sender_args = %s;\n",
			   json_encode ($sender_args));
$extra_scripts .= sprintf ("var wss_url = %s;\n",
			   json_encode ($_SERVER['wss_url']));
$extra_scripts .= "</script>\n";

$extra_scripts .= "<script type='text/javascript' src='cast-send.js'>"
	."</script>\n";


$extra_scripts .= "<script type='text/javascript' src='wsclient.js' >"
	."</script>\n";


pfinish ();

