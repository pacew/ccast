<?php

require_once ("abase.php");
require_once ("app.php");

$fname = dirname ($_SERVER['DOCUMENT_ROOT'])."/sitelib.php";
if (file_exists ($fname)) {
	require_once ($fname);
}
