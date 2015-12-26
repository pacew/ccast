<?php

$anon_ok = 1;
require_once ("common.php");

$csrf_safe = 1;

$tick = get_ticker ();

$ret = "";

$ret .= "<!DOCTYPE html>\n" 
	."<html>\n"
	."<head>\n"
	."  <link rel='stylesheet' href='/rstyle.css' type='text/css' />\n"
	."  <title>Cast Hello Text</title>\n"
	."</head>\n"
	."<body>\n"
	;

$ret .= sprintf ("<h1>ticker = %d</h1>\n", $tick);

//$ret .= "<iframe width='640' height='480' src='https://example.com'></iframe>\n";
//$ret .= "<iframe width='640' height='480' src='https://k.pacew.org:10898/rollaball.html'></iframe>\n";



$ret .= "<div id='message'>Talk to me</div>\n";


$url = "//www.gstatic.com/cast/sdk/libs/receiver/2.0.0/cast_receiver.js";
$ret .= sprintf ("<script type='text/javascript' src='%s'></script>\n", h($url));

$ret .= "<script type='text/javascript' src='receiver.js'></script>\n";

$ret .= "</body>\n"
	."</html>\n";

echo ($ret);
