<?php

require_once ("/opt/slimstk/slimstkext.php");
slimstk_init ();

$prev_flash = @$_SESSION['flash'];
$_SESSION['flash'] = "";

$dbg_file = NULL;
function dbg ($str) {
}


$urandom_chars = "0123456789abcdefghijklmnopqrstuvwxyz";
$urandom_chars_len = strlen ($urandom_chars);

function generate_urandom_string ($len) {
	global $urandom_chars, $urandom_chars_len;
	$ret = "";

	$f = fopen ("/dev/urandom", "r");

	for ($i = 0; $i < $len; $i++) {
		$c = ord (fread ($f, 1)) % $urandom_chars_len;
		$ret .= $urandom_chars[$c];
	}
	fclose ($f);
	return ($ret);
}

$cache_defeater = NULL;
function get_cache_defeater () {
	global $cache_defeater, $devel_mode;

        if (($val = @$cache_defeater) == NULL) {
		if (($val = @file_get_contents ("commit")) == NULL)
			$val = generate_urandom_string (8);
		$cache_defeater = $val;
	}

        return ($val);
}

function flash ($str) {
	$_SESSION['flash'] .= $str;
}

function make_absolute ($rel) {
	if (preg_match (':^http:', $rel))
		return ($rel);

	if (preg_match (':^/:', $rel)) {
		$abs = sprintf ("http%s://%s%s",
				@$_SERVER['HTTPS'] == "on" ? "s" : "",
				$_SERVER['HTTP_HOST'], // may include port
				$rel);
		return ($abs);
	}

	$abs = @$_SERVER['SCRIPT_URI'];
	$abs = preg_replace (':[^/]*$:', "", $abs);
	if (! preg_match (':/$:', $abs))
		$abs .= "/";
	$abs .= $rel;
	return ($abs);
}

function redirect ($target) {
	$target = make_absolute ($target);

	if (session_id ())
		session_write_close ();
	do_commits ();
	if (ob_list_handlers ())
	ob_clean ();
	header ("Location: $target");
	exit ();
}

function fatal ($str = "error") {
	echo ("fatal: " . htmlentities ($str));
	exit();
}

function h($val) {
	return (htmlentities ($val, ENT_QUOTES, 'UTF-8'));
}

function fix_target ($path) {
	$path = preg_replace ('/\&/', "&amp;", $path);
	return ($path);
}

/*
 * use this to conditionally insert an attribute, for example,
 * if $class may contain a class name or an empty string, then do:
 * $body .= sprintf ("<div %s>", mkattr ("class", $class));
 *
 * it is safe to use more than once in the same expression:
 * $body .= sprintf( "<div %s %s>", mkattr("class",$c), mkattr("style",$s));
 */
function mkattr ($name, $val) {
	if (($val = trim ($val)) == "")
		return ("");
	return (sprintf ("%s='%s'",
			 htmlspecialchars ($name, ENT_QUOTES),
			 htmlspecialchars ($val, ENT_QUOTES)));
}

function mail_link ($email) {
	return (sprintf ("<a href='mailto:%s'>%s</a>",
			 fix_target ($email), h($email)));
}

function mklink ($text, $target) {
	if (trim ($text) == "")
		return ("");
	if (trim ($target) == "")
		return (h($text));
	return (sprintf ("<a href='%s'>%s</a>",
			 fix_target ($target), h($text)));
}

function mklink_class ($text, $target, $class) {
	if (trim ($text) == "")
		return ("");

	$attr_href = "";
	$attr_class = "";

	if (trim ($target) != "")
		$attr_href = sprintf ("href='%s'", fix_target ($target));

	if ($class != "")
		$attr_class = sprintf ("class='%s'", $class);

	return (sprintf ("<a %s %s>%s</a>",
			 $attr_href, $attr_class, h($text)));
}

function mklink_nw ($text, $target) {
	if (trim ($text) == "")
		return ("");
	if (trim ($target) == "")
		return (h($text));
	return (sprintf ("<a href='%s' target='_blank'>%s</a>",
			 fix_target ($target), h($text)));
}

function make_confirm ($question, $button, $args) {
	global $request_uri;
	$ret = "";
	$ret .= sprintf ("<form action='%s' method='post'>\n",
			 $request_uri['path']);
	$ret .= csrf_token ();
	foreach ($args as $name => $val) {
		$ret .= sprintf ("<input type='hidden'"
				 ." name='%s' value='%s' />\n",
				 h($name), h ($val));
	}
	$ret .= h($question);
	$ret .= sprintf (" <input type='submit' value='%s' />\n", h($button));
	$ret .= "</form>\n";
	return ($ret);
}

function mktable ($hdr, $rows) {
	$ncols = count ($hdr);
	foreach ($rows as $row) {
		$c = count ($row);
		if ($c > $ncols)
			$ncols = $c;
	}

	if ($ncols == 0)
		return ("");

	$ret = "";
	$ret .= "<table class='boxed'>\n";
	$ret .= "<thead>\n";
	$ret .= "<tr class='boxed_pre_header'>";
	$ret .= sprintf ("<td colspan='%d'></td>\n", $ncols);
	$ret .= "</tr>\n";

	if ($hdr) {
		$ret .= "<tr class='boxed_header'>\n";

		$colidx = 0;
		if ($ncols == 1)
			$class = "lrth";
		else
			$class = "lth";
		foreach ($hdr as $heading) {
			if (is_array ($heading)) {
				$c = $heading[0];
				$v = $heading[1];
			} else {
				$c = "";
				$v = $heading;
			}

			$ret .= sprintf ("<th class='%s %s'>%s</th>",
					 $class, $c, $v);

			$colidx++;
			$class = "mth";
			if ($colidx + 1 >= $ncols)
				$class = "rth";
		}
		$ret .= "</tr>\n";
	}
	$ret .= "</thead>\n";

	$ret .= "<tfoot>\n";
	$ret .= sprintf ("<tr class='boxed_footer'>"
			 ."<td colspan='%d'></td>"
			 ."</tr>\n",
			 $ncols);
	$ret .= "</tfoot>\n";

	$ret .= "<tbody>\n";

	$rownum = 0;
	foreach ($rows as $row) {
		$this_cols = count ($row);

		if ($this_cols == 0)
			continue;

		if (is_object ($row)) {
			switch ($row->type) {
			case 1:
				$c = "following_row ";
				$c .= $rownum & 1 ? "odd" : "even";
				$ret .= sprintf ("<tr class='%s'>\n", $c);
				$ret .= sprintf ("<td colspan='%d'>",
						 $ncols);
				$ret .= $row->val;
				$ret .= "</td></tr>\n";
				break;
			}
			continue;
		}

		$rownum++;
		$ret .= sprintf ("<tr class='%s'>\n",
				 $rownum & 1 ? "odd" : "even");

		for ($colidx = 0; $colidx < $ncols; $colidx++) {
			if($ncols == 1) {
				$class = "lrtd";
			} else if ($colidx == 0) {
				$class = "ltd";
			} else if ($colidx < $ncols - 1) {
				$class = "mtd";
			} else {
				$class = "rtd";
			}

			$col = @$row[$colidx];

			if (is_array ($col)) {
				$c = $col[0];
				$v = $col[1];
			} else {
				$c = "";
				$v = $col;
			}
			$ret .= sprintf ("<td class='%s %s'>%s</td>\n",
					 $class, $c, $v);
		}

		$ret .= "</tr>\n";
	}

	if (count ($rows) == 0)
		$ret .= "<tr><td>(empty)</td></tr>\n";

	$ret .= "</tbody>\n";
	$ret .= "</table>\n";

	return ($ret);
}

function make_option ($val, $curval, $desc)
{
	global $body;

	if ($val == $curval)
		$selected = "selected='selected'";
	else
		$selected = "";

	$body .= sprintf ("<option value='%s' $selected>", h($val));
	$body .= h ($desc);
	$body .= "</option>\n";
}

function make_option2 ($val, $curval, $desc)
{
	$ret = "";

	if ($val == $curval)
		$selected = "selected='selected'";
	else
		$selected = "";

	$ret .= sprintf ("<option value='%s' $selected>", $val);
	if (trim ($desc))
		$ret .= h ($desc);
	else
		$ret .= "&nbsp;";
	$ret .= "</option>\n";

	return ($ret);
}

/* ================================================================ */

$pstart_args = (object)NULL;
$pstart_args->plain = 0;
$pstart_args->insert_html_head_template = 1;
$pstart_args->insert_banner_header_template = 1;
$pstart_args->insert_sidebar_template = 0;
$pstart_args->insert_banner_footer_template = 1;
$pstart_args->content_wrapper = 1;
$pstart_args->alpha_wrapper = 1;
$pstart_args->nocache = 0;
$pstart_args->body_id = "";
$pstart_args->body_class = "";

$pstart_args->page_header = (object)NULL;
$pstart_args->page_header->page_title_html = "";
$pstart_args->page_header->page_description_html = "";
$pstart_args->page_header->page_keywords_html = "";

$pstart_args->facebook = (object)NULL;
$pstart_args->facebook->meta_title_html = "";
$pstart_args->facebook->meta_description_html = "";
$pstart_args->facebook->meta_type_html = "";
$pstart_args->facebook->meta_url_html = "";
$pstart_args->facebook->meta_image_html = "";
$pstart_args->facebook->meta_site_name_html = "";
//probably use 100001286451339 for meta_admins;
$pstart_args->facebook->meta_admins_html = "";

$pstart_args->extra_head = "";

$pstart_args->body = (object)NULL;
$pstart_args->body->fluid_layout = 0;

$pstart_args->require_password = (object)NULL;
$pstart_args->require_password->password = "";
$pstart_args->require_password->password_seq = 1;

$pstart_args->main_nav_override = "";

function pstart () {
	ob_start ();
	global $body;
	$body = "";
}

function pstart_nocache () {
	global $pstart_args;
	$pstart_args->nocache = 1;
	pstart ();
}

$extra_scripts = "";

function google_analytics () {
	if (@$_SERVER['devel_mode'])
		return ("");

	$acct = ""; 

	$ret = "<script type='text/javascript'>\n"
		."var _gaq = _gaq || [];\n"
		."_gaq.push(['_setAccount', '$acct']);\n"
		."_gaq.push(['_setDomainName', 'none']);\n"
		."_gaq.push(['_setAllowLinker', true]);\n"
		."_gaq.push(['_trackPageview']);\n"
		."(function() {\n"
		."if (document.location.port && document.location.port != 80){"
		."   return;"
		."}\n"
		."var ga = document.createElement('script');"
		." ga.type = 'text/javascript';"
		." ga.async = true;"
		." ga.src = ('https:' == document.location.protocol"
		."            ? 'https://ssl' : 'http://www')"
		."  + '.google-analytics.com/ga.js';"
		." var s = document.getElementsByTagName('script')[0];"
		."  s.parentNode.insertBefore(ga, s);"
		." })();\n"
		." </script>\n";
	return ($ret);
}

function pfinish () {
	global $body;

	global $pstart_args;
	if ($pstart_args->nocache) {
		header ("Cache-Control: no-store, no-cache, must-revalidate,"
			." post-check=0, pre-check=0");
		header ("Pragma: no-cache");
		header ("Expires: Thu, 19 Nov 1981 08:52:00 GMT");
	}

	$ajax = 0;

	if (isset ($_SERVER['HTTP_X_REQUESTED_WITH']))
		$ajax = 1;

	if ($ajax) {
		echo ($body);
		exit ();
	}

	$ret = "";

	$ret .= "<!DOCTYPE html PUBLIC"
		." '-//W3C//DTD XHTML 1.0 Transitional//EN'"
		." 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>"
		."\n";
	$ret .= "<html xmlns='http://www.w3.org/1999/xhtml'>\n";
	$ret .= "<head>\n";

	if ($pstart_args->insert_html_head_template)
		$ret .= html_head ();

	if($pstart_args->extra_head)
		$ret .= $pstart_args->extra_head;

	if ($pstart_args->page_header->page_title_html)
		$ret .= sprintf ("<title>%s</title>\n",
				 $pstart_args->page_header->page_title_html);
	if ($pstart_args->page_header->page_description_html) {
		$ret .= sprintf ("<meta name='description'"
				 ." content='%s' />\n",
				 $pstart_args->page_header
				 ->page_description_html);
	}
	if ($pstart_args->page_header->page_keywords_html) {
		$ret .= sprintf ("<meta name='keywords'"
				 ." content='%s' />\n",
				 $pstart_args->page_header
				 ->page_keywords_html);
	}
	if ($pstart_args->facebook->meta_title_html) {
		$ret .= sprintf ("<meta property='og:title'"
				 ." content='%s' />\n",
				 $pstart_args->facebook->meta_title_html);
	}
	if ($pstart_args->facebook->meta_description_html) {
		$ret .= sprintf ("<meta property='og:description'"
				 ." content='%s' />\n",
				 $pstart_args->facebook->meta_description_html);
	}
	if ($pstart_args->facebook->meta_type_html) {
		$ret .= sprintf ("<meta property='og:type'"
				 ." content='%s' />\n",
				 $pstart_args->facebook->meta_type_html);
	}
	if ($pstart_args->facebook->meta_url_html) {
		$ret .= sprintf ("<meta property='og:url'"
				 ." content='%s' />\n",
				 $pstart_args->facebook->meta_url_html);
	}
	if ($pstart_args->facebook->meta_image_html) {
		$ret .= sprintf ("<meta property='og:image'"
				 ." content='%s' />\n",
				 $pstart_args->facebook->meta_image_html);
	}
	if ($pstart_args->facebook->meta_site_name_html) {
		$ret .= sprintf ("<meta property='og:site_name'"
				 ." content='%s' />\n",
				 $pstart_args->facebook->meta_site_name_html);
	}
	if ($pstart_args->facebook->meta_admins_html) {
		$ret .= sprintf ("<meta property='og:admins'"
				 ." content='%s' />\n",
				 $pstart_args->facebook->meta_admins_html);
	}

	$ret .= "</head>\n";

	$c = "";
	if(@$pstart_args->body_class)
		$c = sprintf("class='%s'",$pstart_args->body_class);	
	$i = "";
	if(@$pstart_args->body_id)
		$i = sprintf("id='%s'",$pstart_args->body_id);	
	
	$ret .= "<body $c $i>\n";
	
	$rp = $pstart_args->require_password;	
	if ($rp->password && $rp->password_seq)
		require_password($rp->password,$rp->password_seq);

	$ret .= "<div id='container' class='line'>\n";

	$container_class = "";
	if($pstart_args->body->fluid_layout)
		$container_class = sprintf("class='%s'","fluid");

	$ret .= "<div id='container-inner' $container_class>\n";

	if ($pstart_args->insert_banner_header_template)
		$ret .= banner_header ();

	if ($pstart_args->content_wrapper) {
		$ret .= "<div id='content'>\n"
		     ."<div id='content-inner'>\n";
	}

	$ret .= main_nav ();

	if ($pstart_args->alpha_wrapper) {
		$alpha_class = "";
		if(!$pstart_args->insert_sidebar_template)
			$alpha_class = sprintf("class='%s'","no-sidebar");
		
		$ret .= "<div id='alpha' $alpha_class>\n";             
		$ret .= "<div id='alpha-inner'>\n";
	}

	global $prev_flash;
	if ($prev_flash) {
		$ret .= "<div class='flash_alert'>\n";
		$ret .= $prev_flash;
		$ret .= "</div>\n";
	}

	echo ($ret);


	global $body;
	echo ($body);

	$ret = "";

	if ($pstart_args->alpha_wrapper) {
		$ret .= "</div>\n"
		     . "</div>\n";
	}

	if ($pstart_args->insert_sidebar_template) {
		$ret .= sidebar ();
	}       

	if ($pstart_args->content_wrapper) {
		$ret .= "</div>\n"
		     . "</div>\n";
	}

	if ($pstart_args->insert_banner_footer_template)
		$ret .= banner_footer ();

	global $extra_scripts;
	$ret .= $extra_scripts;

	global $extra_javascript;
	if (@$extra_javascript) {
		$ret .= "<script type='text/javascript'>\n";
		$ret .= $extra_javascript;
		$ret .= "</script>\n";
	}

	/* end container and container inner*/
	$ret .= "</div>\n";
	$ret .= "</div>\n";
	
	$ret .= "</body>\n"
		."</html>\n";

	echo ($ret);

	do_commits ();

	if (session_id ())
		session_write_close ();
	exit ();
}

function html_parse ($html_frag) {
	libxml_use_internal_errors (true);
	$doc = new DOMDocument ();

	$html = "<!DOCTYPE html PUBLIC"
		." '-//W3C//DTD XHTML 1.0 Transitional//EN'"
		." 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>"
		."<html xmlns='http://www.w3.org/1999/xhtml'>"
		."<head>"
		."<meta http-equiv='Content-Type'"
		." content='text/html; charset=utf-8' />"
		."<title></title>"
		."</head>"
		."<body>"
		."<div>";
	$html .= $html_frag;
	$html .= "</div></body></html>\n";
	
	$doc->loadHTML ($html);
	return ($doc);
}
	
function html_get ($doc) {
	$xpath = new DOMXPath ($doc);
	$b = $xpath->query ("/html/body/div");
	return ($doc->saveXml ($b->item(0)));
}

function html_get_xpath ($doc, $expr) {
	$xpath = new DOMXPath ($doc);
	$node = $xpath->query ($expr);
	if (! $node)
		return (NULL);
	$val = $node->item(0);
	if (! $val)
		return (NULL);
	return ($doc->saveXml ($val));
}

function find_first_tag ($doc, $tag) {
	return (html_get_xpath ($doc, "//".$tag));
}

function require_password ($user_password, $desired_password_seq) {
	/*
	 * strip query parameters so the password
	 * won't be visible if we redirect
	 */
	$url = preg_replace('/\?.*/', '',$_SERVER['REQUEST_URI']);

	if(isset($user_password)) {
		if (@$_REQUEST['preview_password'] == $user_password) {
			$_SESSION['password_seq'] = $desired_password_seq;
			redirect ($url);
		}
	}

	if (@$_SESSION['password_seq'] == $desired_password_seq)
		return;

	$ret = "";
	$ret .= sprintf ("<form action='%s' method='post' />", h($url));
	$ret .= "<input type='hidden' name='login' value='1' />";
	$ret .= "Password required: ";
	$ret .= "<input type='password' name='preview_password' />";
	$ret .= "<input type='submit' name='submit' value='Submit' />";
	$ret .= "</form>";
	
	echo ($ret);
	exit ();
}

function do_tidy ($raw_html, $tag_for_error = "") {
	$html = "<!DOCTYPE html PUBLIC"
		." '-//W3C//DTD XHTML 1.0 Transitional//EN'"
		." 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'"
		.">"
		."<html xmlns='http://www.w3.org/1999/xhtml'>"
		."<head>"
		."<meta http-equiv='Content-Type'"
		." content='text/html; charset=utf-8' />"
		."<title></title>"
		."</head>"
		."<body>";
	$html .= $raw_html;
	$html .= "\n</body></html>\n";

	$config = array ();
	$config['indent'] = 1;
	$config['indent-spaces'] = 4;
	$config['indent-attributes'] = 1;
	$config['wrap'] = 120;
	$config['gnu-emacs'] = 1;
	$config['literal-attributes'] = 1;
	$config['output-xhtml'] = 1;
	$config['quote-nbsp'] = 1;
	$config['show-errors'] = 10;
	$config['vertical-space'] = 1;

	// $config['TidyCharEncoding'] = "utf8";

	$config['show-body-only'] = 1;
	$config['force-output'] = 1;
	$config['quiet'] = 1;

	$tidy = new tidy;
	$tidy->parseString ($html, $config, 'utf8');
	$tidy->cleanRepair ();
	$tidy->diagnose ();

	if ($tidy->errorBuffer) {
		global $tidy_errs;
		if (! isset ($tidy_errs))
			$tidy_errs = "";
		if ($tag_for_error) {
			$tidy_errs .= sprintf ("<p>errors in %s</p>\n",
					       h($tag_for_error));
		}
		$tidy_errs .= "<pre>\n";
		$tidy_errs .= htmlentities ($tidy->errorBuffer, ENT_QUOTES,
					    'UTF-8');
		$tidy_errs .= "</pre>\n";
	}

	return (trim ($tidy));
}

function safe_contents ($filename) {
	if (! file_exists ($filename)) {
		echo ("$filename does not exit\n");
		exit ();
	}
	$tidy_errs = "";
	$html = file_get_contents ($filename);
	$safe_html = do_tidy ($html);
	if ($tidy_errs) {
		echo (sprintf ("validation errors for %s\n", $filename));
		echo ($tidy_errs);
		exit ();
	}

	return ($safe_html);
}

function nice_prefix ($str, $limit = 50) {
	$str = preg_replace ("/[ \t\r\n]+/", " ", $str);
	$str = trim ($str);
	if (strlen ($str) < $limit)
		return ($str);

	$str = substr ($str, 0, $limit);
	$str = preg_replace ("/ [^ ]*$/", "", $str);

	$str .= " ...";

	return ($str);
}

function getvar ($var, $defval = "") {
	global $vars_cache;
	if (! isset ($vars_cache)) {
		$vars_cache = array ();
		$q = query ("select var, val from vars");
		while (($r = fetch ($q)) != NULL) {
			if ($r->val)
				$vars_cache[$r->var] = $r->val;
			else
				$vars_cache[$r->var] = "";
		}
	}

	if (isset ($vars_cache[$var]))
		return ($vars_cache[$var]);
	return ($defval);
}

function setvar ($var, $val) {
	global $vars_cache;

	getvar ($var);

	if ($val == NULL)
		$val = "";

	if (isset ($vars_cache[$var])) {
		if (strcmp ($vars_cache[$var], $val) != 0) {
			query ("update vars set val = ? where var = ?",
			       array ($val, $var));
		}
	} else {
		query ("insert into vars (var, val) values (?, ?)",
		       array ($var, $val));
	}
	$vars_cache[$var] = $val;
}

function file_put_atomic ($filename, $val) {
	$tname = tempnam (dirname ($filename), "TMP");
	if (($f = fopen ($tname, "w")) == NULL) {
		unlink ($tname);
		return (-1);
	}
	fwrite ($f, $val);
	fclose ($f);
	rename ($tname, $filename);
	return (0);
}


function parse_number ($str) {
	$str = preg_replace ('/[^-.0-9]/', '', $str);
	return (0 + $str);
}

function wiki_get ($tag) {
	$wp = (object)NULL;
	$wp->tag = $tag;

	$q = query ("select wiki_id, content"
		    ." from wiki"
		    ." where tag = ?",
		    $tag);
	if (($r = fetch ($q)) == "") {
		$wp->wiki_id = 0;
		$wp->content = "";
	} else {
		$wp->wiki_id = 0 + $r->wiki_id;
		$wp->content = $r->content;
	}
	return ($wp);
}

function wiki_format ($wp, $return_to = "") {
	$ret = "";

	$ret .= "<div class='wiki_block'>\n";
	$ret .= $wp->content;
	$ret .= " ";

	$ret .= wiki_edit_link ($wp, $return_to);

	$ret .= "</div>\n";

	return ($ret);
}

function wiki_edit_link ($wp, $return_to = "") {
	$t = sprintf ("/wiki_edit.php?tag=%s&return_to=%s",
		      rawurlencode ($wp->tag),
		      rawurlencode ($return_to));
	return (mklink_class ("wiki", $t, "wiki_edit_link"));
}

function require_https () {
	if (@$_SERVER['HTTPS'] != "on") {
		if (! isset ($_SERVER['ssl_url'])) {
			echo ("invalid SSL configuration");
			exit ();
		}
		$prefix = rtrim ($_SERVER['ssl_url'], '/');
		$suffix = ltrim ($_SERVER['REQUEST_URI'], '/');
		$t = sprintf ("%s/%s", $prefix, $suffix);
		redirect ($t);
	}
}

function csrf_token () {
	if (($csrf_key_base64 = getsess ("csrf_key")) == "") {
		$csrf_key = openssl_random_pseudo_bytes (16);
		$csrf_key_base64 = base64_encode ($csrf_key);
		putsess ("csrf_key", $csrf_key_base64);
	} else {
		$csrf_key = base64_decode ($csrf_key_base64);
	}
	
	$msg = time ();
	$prefix16 = openssl_random_pseudo_bytes (16);
	$clear = $prefix16 . $msg;

	$iv = openssl_random_pseudo_bytes (16);

	$encrypted = openssl_encrypt ($clear, "aes-128-cbc", $csrf_key,
				      0, $iv);

	$arr = array (base64_encode ($iv), base64_encode ($encrypted));
	$csrf_token = json_encode ($arr);

	return (sprintf ("<input type='hidden'"
			 ." name='csrf_token' value='%s' />\n",
			 h(base64_encode ($csrf_token))));
}

function csrf_valid () {
	$csrf_token = base64_decode (@$_REQUEST['csrf_token']);
	$arr = @json_decode ($csrf_token);

	$iv = @base64_decode ($arr[0]);
	$encrypted = @base64_decode ($arr[1]);

	if ($iv == "" || $encrypted == "")
		return (0);

	if (($csrf_key = base64_decode (getsess ("csrf_key"))) == "")
		return (0);

	$clear = openssl_decrypt ($encrypted, "aes-128-cbc", $csrf_key,
				  0, $iv);
	$msg = substr ($clear, 16); /* remove prefix16 */
	
	$start_time = intval ($msg);
	$now = time ();
	$limit = 8 * 3600; /* in seconds */
	if ($start_time <= $now && $now <= $start_time + $limit)
		return (1);

	return (0);
}

$csrf_safe = 0;

function csrf_check () {
	if (! csrf_valid ()) {
		global $body;
		$body .= "form submission timeout ..."
			." please go back, reload, and try again";

		putsess ("csrf_token", NULL);
		pfinish ();
	}
	global $csrf_safe;
	$csrf_safe = 1;
}

function twocol ($varname, $desc, $curval = "", $attrs = NULL) {
	$ret = "";
	$ret .= sprintf ("<tr><th>%s</th><td>", h($desc));
	$extra_attrs = "";
	if ($attrs) {
		foreach ($attrs as $name => $val) {
			$extra_attrs .= sprintf (" %s='%s'",
						 htmlspecialchars ($name,
								   ENT_QUOTES,
								   "UTF-8"),
						 htmlspecialchars ($val,
								   ENT_QUOTES,
								   "UTF-8"));
		}
	}
	$ret .= sprintf ("<input type='text' name='%s' value='%s' %s />\n",
			 h($varname), h($curval), $extra_attrs);
	$ret .= "</td></tr>\n";
	return ($ret);
}

