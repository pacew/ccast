<?php

$anon_ok = 1;
require_once ("common.php");

$extra_scripts .= "<script type='text/javascript' src='hello.js'></script>\n";


pstart ();

$body .= "<div>\n";
$body .= mklink ("google test", "/CastVideos-chrome");
$body .= "</div>\n";

$body .= "<div>\n";
$body .= mklink ("text test", "hello.php");
$body .= "</div>\n";

$body .= "<div>\n";
$body .= mklink ("google cast console",
		 "https://cast.google.com/publish/#/overview");
$body .= "</div>\n";

$body .= "<div>\n";
$url = sprintf ("%s/CastHelloText-chrome/receiver.html",
		$_SERVER['ssl_url']);

$body .= mklink ($url, $url);
$body .= "</div>\n";


$body .= "<div>\n";
$body .= "<form method='get' action='JavaScript:update();'>\n";
$body .= "<input id='input' class='border' type='text'"
	."  size='30' onwebkitspeechchange='transcribe(this.value)'"
	."  x-webkit-speech/>\n";
$body .= "</form>\n";

pfinish ();

