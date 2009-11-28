<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Debug plugin: Shows programming debug information for administrators.

if (!defined("IN_ESOTALK")) exit;

class Debug extends Plugin {

var $id = "Debug";
var $name = "Debug";
var $version = "1.0.0";
var $description = "Shows programming debug information for administrators";
var $author = "esoTalk team";
var $defaultConfig = array(
	"showToNonAdmins" => false
);

var $start;
var $queryTimer;
var $log = "";

function Debug()
{
	// Set verboseFatalErrors to true.
	global $config;
	$config["verboseFatalErrors"] = true;
	
	// Start the page load timer.
	$this->start = $this->microtimeFloat();
	if (empty($_SESSION["queries"]) or !is_array($_SESSION["queries"])) $_SESSION["queries"] = array();
	
	parent::Plugin();
}

function init()
{
	parent::init();
	
	// Add hooks to be run before and after database queries.
	$this->esoTalk->addHook("beforeDatabaseQuery", array($this, "startQueryTimer"));
	$this->esoTalk->addHook("afterDatabaseQuery", array($this, "addQuery"));
	$this->esoTalk->addLanguage("seconds", "seconds");
	
	// If this is an AJAX request, add a hook to add debug information to the returned JSON array.
	if (defined("AJAX_REQUEST")) {
		$this->esoTalk->addHook("ajaxFinish", array($this, "addInformationToAjaxResult"));
		return;
	}
	
	// Add language definitions, scripts, and stylesheets.
	$this->esoTalk->addLanguage("Debug information", "Debug information");
	$this->esoTalk->addLanguage("Page loaded in", "Page loaded in just over <strong><span id='debugLoadTime'>%s</span> seconds</strong>");
	$this->esoTalk->addLanguage("MySQL queries", "MySQL queries");
	$this->esoTalk->addLanguage("POST + GET + FILES information", "POST + GET + FILES information");
	$this->esoTalk->addLanguage("SESSION + COOKIE information", "SESSION + COOKIE information");
	$this->esoTalk->addLanguage("Update debug information for background AJAX requests", "Update debug information for background AJAX requests");
	$this->esoTalk->addScript("plugins/Debug/debug.js", 1000);
	$this->esoTalk->addCSS("plugins/Debug/debug.css");
	
	// Add a hook to the bottom of the page, where we'll output the debug information!
	$this->esoTalk->addHook("pageEnd", array($this, "renderDebug"));
}

// Plugin settings: whether or not to show debug information to non-administrators.
function settings()
{
	global $config, $language;
	
	// Add language definitions.
	$this->esoTalk->addLanguage("Show debug information to non-administrators", "Show debug information to non-administrators");

	// Generate settings panel HTML.
	$settingsHTML = "<ul class='form'>
 	<li><label for='Debug_showToNonAdmins' class='checkbox'>{$language["Show debug information to non-administrators"]}</label> <input id='Debug_showToNonAdmins' name='Debug[showToNonAdmins]' type='checkbox' class='checkbox' value='1' " . ($config["Debug"]["showToNonAdmins"] ? "checked='checked'" : "") . "/></li>
	<li><label></label> " . $this->esoTalk->skin->button(array("value" => $language["Save changes"], "name" => "saveSettings")) . "</li>
	</ul>";
	
	return $settingsHTML;
}

// Save the plugin settings.
function saveSettings()
{
	global $config;
	$config["Debug"]["showToNonAdmins"] = (bool)@$_POST["Debug"]["showToNonAdmins"];
	writeConfigFile("config/Debug.php", '$config["Debug"]', $config["Debug"]);
	$this->esoTalk->message("changesSaved");
}

// Add the debug information to the JSON array which is returned from an AJAX request.
function addInformationToAjaxResult($esoTalk, &$result)
{
	global $config, $language;
	
	// Don't proceed if the user is not permitted to see the debug information!
	if (empty($esoTalk->user["admin"]) and !$config["Debug"]["showToNonAdmins"]) return;
	
	// Add the debug information to the $result array.
	$result["queries"] = "";
	foreach ($_SESSION["queries"] as $query)
		$result["queries"] .= "<li>" . sanitize($query[0]) . " <small>(" . $query[1] . " {$language["seconds"]})</small></li>";
	$end = $this->microtimeFloat();
	$time = round($end - $this->start, 4);
	$result["queriesCount"] = count($_SESSION["queries"]);
	$result["loadTime"] = $time;
	$result["debugPost"] = sanitize(print_r($_POST, true));
	$result["debugGet"] = sanitize(print_r($_GET, true));
	$result["debugFiles"] = sanitize(print_r($_FILES, true));
	$result["debugSession"] = sanitize(print_r($_SESSION, true));
	$result["debugCookie"] = sanitize(print_r($_COOKIE, true));
	$result["log"] = sanitize($this->log);
	$_SESSION["queries"] = array();
}

function microtimeFloat()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

// Start the query timer so we can work out how long it took when it finished.
function startQueryTimer($esoTalk, $query)
{
	$this->queryTimer = $this->microtimeFloat();
}

// Work out how long the query took to run and add it to the log.
function addQuery($esoTalk, $query)
{
	$_SESSION["queries"][] = array($query, round($this->microtimeFloat() - $this->queryTimer, 4));
}

// Add something to the AJAX log.
function log()
{
	$args = func_get_args();
	foreach ($args as $arg) {
		if (is_array($arg)) $log = print_r($arg, true);
		else $log = (string)$arg;
		$this->log .= "$log\n";
	}
}

// Render the debug box at the bottom of the page.
function renderDebug($esoTalk)
{
	global $config, $language;
	
	// Don't proceed if the user is not permitted to see the debug information!		
	if (empty($esoTalk->user["admin"]) and !$config["Debug"]["showToNonAdmins"]) return;
	
	// Stop the page loading timer.
	$end = $this->microtimeFloat();
	$time = round($end - $this->start, 4);
		
	echo "<div id='debug'>
<h2>{$language["Debug information"]} <small>" . sprintf($language["Page loaded in"], $time) . "</small> <small style='float:right'><input type='checkbox' class='checkbox' id='debugUpdateBackground' value='1' checked='checked' onchange='Ajax.debugUpdateBackground=this.checked'/> <label for='debugUpdateBackground' class='checkbox'>{$language["Update debug information for background AJAX requests"]}</label></small></h2>";
	
	// MySQL queries.
	echo "<h3><a href='#' onclick='toggle(getById(\"debugQueries\"), {animation:\"verticalSlide\"});return false'>{$language["MySQL queries"]} (<span id='debugQueriesCount'>" . count($_SESSION["queries"]) . "</span>)</a></h3>
	<ul id='debugQueries' class='fixed'>";
	if (!count($_SESSION["queries"])) echo "<li></li>";
	else foreach ($_SESSION["queries"] as $query) echo "<li>" . sanitize($query[0]) . " <small>(" . $query[1] . " {$language["seconds"]})</small></li>";
	$_SESSION["queries"] = array();
	
	// POST + GET + FILES information.
	echo "</ul>
	<h3><a href='#' onclick='toggle(getById(\"debugPostGetFiles\"), {animation:\"verticalSlide\"});return false'>{$language["POST + GET + FILES information"]}</a></h3>
	<div id='debugPostGetFiles'>
	<p style='white-space:pre' class='fixed' id='debugPost'>\$_POST = ";
	echo sanitize(print_r($_POST, true));
	echo "</p><p style='white-space:pre' class='fixed' id='debugGet'>\$_GET = ";
	echo sanitize(print_r($_GET, true));
	echo "</p><p style='white-space:pre' class='fixed' id='debugFiles'>\$_FILES = ";
	echo sanitize(print_r($_FILES, true));
	echo "</p>
	</div>";
	
	// SESSION + COOKIE information.
	echo "<h3><a href='#' onclick='toggle(getById(\"debugSessionCookie\"), {animation:\"verticalSlide\"});return false'>{$language["SESSION + COOKIE information"]}</a></h3>
	<div id='debugSessionCookie'><p style='white-space:pre' class='fixed' id='debugSession'>\$_SESSION = ";
	echo sanitize(print_r($_SESSION, true));
	echo "</p><p style='white-space:pre' class='fixed' id='debugCookie'>\$_COOKIE = ";
	echo sanitize(print_r($_COOKIE, true));
	echo "</p></div>
	</div>";
	
	// Hide all panels by default.
	echo "<script type='text/javascript'>
	// <![CDATA[
	hide(getById(\"debugQueries\")); hide(getById(\"debugPostGetFiles\")); hide(getById(\"debugSessionCookie\"));
	// ]]>
	</script>";
}

}

?>