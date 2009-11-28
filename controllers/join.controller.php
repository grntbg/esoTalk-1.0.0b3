<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Join controller: handles the 'Join this forum' page. Defines form data, validates it, adds the member to the
// database, and handles the link from the verifcation email.

if (!defined("IN_ESOTALK")) exit;

class join extends Controller {

var $view = "join.view.php";

// Reserved user names which cannot be used.
var $reservedNames = array("guest", "member", "members", "moderator", "moderators", "administrator", "administrators", "admin", "suspended", "esotalk", "name", "password", "everyone", "myself");

// Initialize: define the form contents, and check to see if form data was submitted.
function init()
{
	// If we're already logged in, go to 'My settings'.
	if ($this->esoTalk->user) redirect("settings");
	
	// Set the title and make sure this page isn't indexed.
	global $language, $config;
	$this->title = $language["Join this forum"];
	$this->esoTalk->addToHead("<meta name='robots' content='noindex, noarchive'/>");

	if (isset($_GET["q2"])) {
		
		// If the user is requesting that we resend their verifcaiton email...
		if ($_GET["q2"] == "sendVerification") {
			$memberId = (int)@$_GET["q3"];
			if (list($email, $name, $password) = $this->esoTalk->db->fetchRow("SELECT email, name, password FROM {$config["tablePrefix"]}members WHERE memberId=$memberId AND account='Unvalidated'")) $this->sendVerificationEmail($email, $name, $memberId . $password);
			$this->esoTalk->message("verifyEmail", false);
			redirect("");
		}
		
		// Otherwise, if there's a verification hash in the URL, attempt to verify the user.
		else $this->validateMember($_GET["q2"]);
		return;
		
	}
		
	// Define the elements in the join form.
	$this->form = array(
		
		"accountInformation" => array(
			"legend" => $language["Account information"],
			100 => array(
				"id" => "name",
				"html" => @"<label>{$language["Username"]}</label> <input id='name' name='join[name]' type='text' class='text' value='{$_POST["join"]["name"]}' maxlength='31' tabindex='100'/>",
				"validate" => array($this, "validateName"),
				"required" => true,
				"databaseField" => "name",
				"ajax" => true
			),
			200 => array(
				"id" => "email",
				"html" => @"<label>{$language["Email"]}</label> <input id='email' name='join[email]' type='text' class='text' value='{$_POST["join"]["email"]}' maxlength='63' tabindex='200'/>",
				"validate" => "validateEmail",
				"required" => true,
				"databaseField" => "email",
				"message" => "emailInfo",
				"ajax" => true
			),
			300 => array(
				"id" => "password",
				"html" => @"<label>{$language["Password"]}</label> <input id='password' name='join[password]' type='password' class='text' value='{$_POST["join"]["password"]}' tabindex='300'/>",
				"validate" => "validatePassword",
				"required" => true,
				"databaseField" => "password",
				"message" => "passwordInfo",
				"ajax" => true
			),
			400 => array(
				"id" => "confirm",
				"html" => @"<label>{$language["Confirm password"]}</label> <input id='confirm' name='join[confirm]' type='password' class='text' value='{$_POST["join"]["confirm"]}' tabindex='400'/>",
				"required" => true,
				"validate" => array($this, "validateConfirmPassword"),
				"ajax" => true
			)
		)
		
	);
	
	$this->callHook("init");
	
	// Make an array of just fields (without the enclosing fieldsets) for easy access.
	$this->fields = array();
	foreach ($this->form as $k => $fieldset) {
		if (!is_array($fieldset)) continue;
		foreach ($fieldset as $j => $field) {
			if (!is_array($field)) continue;
			$this->fields[$field["id"]] =& $this->form[$k][$j];
		}
	}
	
	// If the form has been submitted, validate it and add the member into the database.
	if (isset($_POST["join"]) and $this->addMember()) {
		$this->esoTalk->message("verifyEmail", false);
		redirect("");
	}
}

// Run AJAX actions.
function ajax()
{
	if ($return = $this->callHook("ajax")) return $return;
	
	switch ($_POST["action"]) {
		
		// Validate a form field.
		case "validate":
			if ($msg = @call_user_func($this->fields[$_POST["field"]]["validate"], $_POST["value"]))
				return array("validated" => false, "message" => $this->esoTalk->htmlMessage($msg));
			else return array("validated" => true, "message" => "");
	}
}

// Validate the form and add the member to the database.
function addMember()
{
	global $config;
	
	// Loop through the form fields and validate them.
	$validationError = false;
	foreach ($this->fields as $k => $field) {
		if (!is_array($field)) continue;
		$this->fields[$k]["input"] = @$_POST["join"][$field["id"]];
		
		// If this field is required, or if data has been entered (regardless of whether it's required), validate it
		// using the field's validation callback function.
		if ((!empty($field["required"]) or $this->fields[$k]["input"]) and !empty($field["validate"])
			and ($msg = @call_user_func_array($field["validate"], array(&$this->fields[$k]["input"])))) {
			
			// If there was a validation error, set the field's message.
			$validationError = true;
			$this->fields[$k]["message"] = $msg;
			$this->fields[$k]["error"] = true;
			
		} else $this->fields[$k]["success"] = true;
	}
	
	$this->callHook("validateForm", array(&$validationError));
	
	// If there was a validation error, don't continue.
	if ($validationError) return false;
	
	// Construct the query to insert the member into the database.
	// Loop through the form fields and use their "databaseField" and "input" attributes for the query.
	$insertData = array();
	foreach ($this->fields as $field) {
		if (!is_array($field)) continue;
		if (!empty($field["databaseField"])) $insertData[$field["databaseField"]] = !empty($field["checkbox"])
			? ($field["input"] ? 1 : 0)
			: "'{$field["input"]}'";
	}
	
	// Add a few extra fields to the query.
	$insertData["color"] = "FLOOR(1 + (RAND() * {$this->esoTalk->skin->numberOfColors}))";
	$insertData["language"] = "'" . addslashes($config["language"]) . "'";
	$insertData["avatarAlignment"] = "'{$_SESSION["avatarAlignment"]}'";
	
	$this->callHook("beforeAddMember", array(&$insertData));
	
	// Construct the query and make it a REPLACE query rather than an INSERT one (so unvalidated members can be
	// overwritten).
	$insertQuery = $this->esoTalk->db->constructInsertQuery("members", $insertData);
	$insertQuery = "REPLACE" . substr($insertQuery, 6);
	
	// Execute the query and get the new member's ID.
	$this->esoTalk->db->query($insertQuery);
	$memberId = $this->esoTalk->db->lastInsertId();
	
	$this->callHook("afterAddMember", array($memberId));
	
	// Email the member with a verification link so that they can verify their account.
	$this->sendVerificationEmail($_POST["join"]["email"], $_POST["join"]["name"], $memberId . md5($config["salt"] . $_POST["join"]["password"]));
	
	return true;
}

// Send a verfication email.
function sendVerificationEmail($email, $name, $verifyHash)
{
	global $language, $config;
	sendEmail($email, sprintf($language["emails"]["join"]["subject"], $name), sprintf($language["emails"]["join"]["body"], $name, $config["forumTitle"], $config["baseURL"] . makeLink("join", $verifyHash)));
}

// Validate a member with the provided a validation hash.
function validateMember($hash)
{
	global $config;
	
	// Split the hash into the member ID and password.
	$memberId = (int)substr($hash, 0, strlen($hash) - 32);
	$password = addslashes(substr($hash, -32));
	
	// See if there is an unvalidated user with this ID and password hash. If there is, validate them and log them in.
	if ($name = @$this->esoTalk->db->result($this->esoTalk->db->query("SELECT name FROM {$config["tablePrefix"]}members WHERE memberId=$memberId AND password='$password' AND account='Unvalidated'"), 0)) {
		$this->esoTalk->db->query("UPDATE {$config["tablePrefix"]}members SET account='Member' WHERE memberId=$memberId");
		$this->esoTalk->login($name, false, $password);
		$this->esoTalk->message("accountValidated", false);
	}
	redirect("");
}

// Add an element to the page's form.
function addToForm($fieldset, $field, $position = false)
{
	return addToArray($this->form[$fieldset], $field, $position);
}

// Add a fieldset to the form.
function addFieldset($fieldset, $legend, $position = false)
{
	return addToArrayString($this->form, $fieldset, array("legend" => $legend), $position);
}

// Validate the confirm password field (see if it matches the password field.)
function validateConfirmPassword($password)
{
	if ($password != (defined("AJAX_REQUEST") ? $_POST["password"] : $_POST["join"]["password"]))
		return "passwordsDontMatch";
}

// Validate the name field: make sure it's not reserved, is long enough, doesn't contain invalid characters,
// and is not already taken by another member.
function validateName(&$name)
{
	global $config;
	$name = substr($name, 0, 31);
	if (in_array(strtolower($name), $this->reservedNames)) return "nameTaken";
	if (!strlen($name)) return "nameEmpty";
	if (preg_match("/[" . preg_quote("!/%+-", "/") . "]/", $name)) return "invalidCharacters";
	if (@$this->esoTalk->db->result($this->esoTalk->db->query("SELECT 1 FROM {$config["tablePrefix"]}members WHERE name='" . addslashes($name) . "' AND account!='Unvalidated'"), 0))
		return "nameTaken";
}
	
}

?>