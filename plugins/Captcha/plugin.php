<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Captcha plugin: Provides image verification during the join process to prevent bots from joining the forums

if (!defined("IN_ESOTALK")) exit;

class Captcha extends Plugin {
	
var $id = "Captcha";
var $name = "Captcha";
var $version = "1.0.0";
var $description = "Provides image verification during the join process to prevent bots from joining the forums";
var $author = "esoTalk team";
var $defaultConfig = array(
	"numberOfCharacters" => 3
);

function init()
{
	parent::init();
	
	// Check for gd plugin
	if (!extension_loaded("gd") and !extension_loaded("gd2")) return false;
	
	// Define language and messages
	$this->esoTalk->addLanguage("Are you human", "Are you human?");
	$this->esoTalk->addLanguage("Type the letters you see", "Type the letters you see in the image");
	$this->esoTalk->addLanguage("Can't make it out", "Can't make it out? <a href='%s'>Try another one!</a>");
	$this->esoTalk->addMessage("captchaError", "warning", "Oops, you got it wrong! Try again with this combination.");
	
	if ($this->esoTalk->action == "join") $this->esoTalk->controller->addHook("init", array($this, "initCaptchaForm"));
}

// Add the captcha fieldset and input to the join form
function initCaptchaForm(&$join)
{
	global $language;
	$join->addFieldset("areYouHuman", $language["Are you human"], 900);
	$join->addToForm("areYouHuman", array(
		"id" => "captcha",
		"html" => "<label style='width:20em'>{$language["Type the letters you see"]}<br/><small>" . sprintf($language["Can't make it out"], "javascript:$(\"captchaImg\").src=$(\"captchaImg\").src.split(\"?\")[0]+\"?\"+(new Date()).getTime();void(0)") . "</small></label> <div><input id='captcha' name='join[captcha]' type='text' class='text' tabindex='500'/><br/><img src='plugins/Captcha/captchaImg.php' style='margin-top:3px' id='captchaImg' alt='Captcha'/></div>",
		"validate" => array($this, "validateCaptcha"),
		"required" => true
	));
}

// Validate captcha input
function validateCaptcha($input)
{
	if ($_SESSION["captcha"] != md5($input) or !$input) return "captchaError";
}

function settings()
{
	global $config, $language;
	
	// Define language
	$this->esoTalk->addLanguage("Sample captcha image", "Sample captcha image");
	$this->esoTalk->addLanguage("Show another one", "Show another one");
	$this->esoTalk->addLanguage("Number of characters", "Number of characters");
	
	// Update captcha settings
	if (isset($_POST["Captcha"])) {
		$config["Captcha"]["numberOfCharacters"] = max(1, min(10, (int)$_POST["Captcha"]["numberOfCharacters"]));
		writeConfigFile("config/Captcha.php", '$config["Captcha"]', $config["Captcha"]);
		$this->esoTalk->message("changesSaved");
	}

	// Generate settings html
	$settingsHtml = "<form action='" . curLink() . "' method='post'>
	<ul class='form'>
	<li><label>{$language["Sample captcha image"]}<br/><small><a href='javascript:$(\"captchaImg\").src=$(\"captchaImg\").src.split(\"?\")[0]+\"?\"+(new Date()).getTime();void(0)'>{$language["Show another one"]}</a></small></label> <img src='plugins/Captcha/captchaImg.php?" . time() . "' id='captchaImg' alt='Captcha'/></li>
	<li><label>{$language["Number of characters"]}</label> <input name='Captcha[numberOfCharacters]' type='text' class='text' value='{$config["Captcha"]["numberOfCharacters"]}'/></li>
	<li><label></label> " . $this->esoTalk->skin->button(array("value" => $language["Save changes"], "name" => "Captcha[submit]")) . "</li>
	</ul>
	</form>";
	
	return $settingsHtml;
}

}

?>