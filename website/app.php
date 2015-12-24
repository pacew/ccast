<?php

function html_head () {
	$ret = "<meta http-equiv='Content-Type'"
		." content='text/html; charset=utf-8' />\n";

	$ret .= "<link rel='shortcut icon' type='image/x-icon'"
		." href='/favicon.jpg' />\n";

	$ret .= sprintf ("<link rel='stylesheet'"
			 ." href='/style.css?s=%s' type='text/css' />\n",
			 get_cache_defeater ());

	return ($ret);
}


function banner_header () {
	global $login;

	$ret = "";

	global $conf_key;

	$ret .= "<div class='banner'>";
	$ret .= sprintf ("<strong>%s</strong>\n",
			 mklink ($_SERVER['site_name'], "/"));

	$ret .= " ";

	if (isset ($login)) {
		if ($login->email) {
			$ret .= h($login->email);
			$ret .= " ";
			$ret .= mklink ("logout", "/logout.php");
		} else {
			$ret .= mklink ("login", "/login.php");
		}
	}

	$ret .= "</div>\n";


	return ($ret);
}

function main_nav () {
	global $pstart_args, $login;
	$ret = "";

	$ret .= "<div id='nav'>\n";
	$ret .= " <div id='nav-inner'>\n";

	$ret .= "<ul class='nav'>\n";
	$ret .= sprintf ("<li>%s</li>\n", mklink ("home", "/"));
	$ret .= "</ul>\n";

	$ret .= "<div style='clear:both'></div>\n";
	$ret .= " </div>\n";
	$ret .= "</div>\n";
	return ($ret);
}

function sidebar () {
	return ("");
}

function banner_footer () {
	$ret = "";

	$ret .= "<div id='footer'>\n"
		."  <div id='footer-inner'>\n"
		."    <div id='footer-content'>\n";
	$ret .= "authorized use only";
	$ret .= "     </div>\n"
		."  </div>\n"
		."</div>\n";

	$ret .= "<script type='text/javascript' src='/jquery-2.1.3.min.js'>"
		."</script>"
		."<script type='text/javascript' src='/scripts.js'>"
		."</script>";

	return ($ret);
}

