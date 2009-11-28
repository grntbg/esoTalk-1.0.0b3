<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Plugins controller: controls the toggling of plugins and plugin installation.

if (!defined("IN_ESOTALK")) exit;

class plugins extends Controller {
	
var $view = "plugins.view.php";
var $plugins = array();

// Get all the plugins into an array and perform any plugin-related actions.
function init()
{
	// Non-admins aren't allowed here!
	if (!$this->esoTalk->user["admin"]) redirect("");
	
	global $language, $config;
	$this->title = $language["Plugins"];
	
	// If the 'add a new plugin' form has been submitted, attempt to install the uploaded plugin.
	if (isset($_FILES["installPlugin"]) and $this->esoTalk->validateToken(@$_POST["token"])) $this->installPlugin();
	
	// Get the installed plugins and their details by reading the plugins/ directory.
	if ($handle = opendir("plugins")) {
	    while (false !== ($file = readdir($handle))) {
		
			// Make sure the plugin is valid, and set up its class.
	        if ($file[0] != "." and is_dir("plugins/$file") and file_exists("plugins/$file/plugin.php") and (include_once "plugins/$file/plugin.php") and class_exists($file)) {
				$plugin = new $file;
				$plugin->esoTalk =& $this->esoTalk;
				
				// Has the settings form for this plugin been submitted?
				if (isset($_POST["saveSettings"]) and $_POST["plugin"] == $plugin->id and $this->esoTalk->validateToken($_POST["token"]))
					$plugin->saveSettings();
				
				// Add the plugin to the installed plugins array.
				$this->plugins[$plugin->id] = array(
					"loaded" => in_array($file, $config["loadedPlugins"]),
					"name" => $plugin->name,
					"version" => $plugin->version,
					"description" => $plugin->description,
					"author" => $plugin->author,
					"settings" => $plugin->settings()
				);
			}
			
	    }
	    closedir($handle);
	}
	ksort($this->plugins);
	
	// Toggle a plugin if necessary.
	if (!empty($_GET["toggle"]) and $this->esoTalk->validateToken(@$_GET["token"]) and $this->togglePlugin($_POST["id"]))
		redirect("plugins");
}

// Run AJAX actions.
function ajax()
{
	global $config;
	
	switch ($_POST["action"]) {
		
		// Toggle a plugin.
		case "toggle":
			if (!$this->esoTalk->validateToken(@$_POST["token"])) return;
			$this->togglePlugin(@$_POST["id"]);
	}
}

// Toggle a plugin.
function togglePlugin($plugin)
{
	if (!$plugin) return false;
	global $config;
	
	// If the plugin is currently enabled, take it out of the loaded plugins array.
	$k = array_search($plugin, $config["loadedPlugins"]);
	if ($k !== false) unset($config["loadedPlugins"][$k]);
	
	// Otherwise, if it's not enabled, add it to the array.
	elseif ($k === false) $config["loadedPlugins"][] = $plugin;
	
	// Strip out duplicate and non-existing plugins from the array.
	$config["loadedPlugins"] = array_unique($config["loadedPlugins"]);
	foreach ($config["loadedPlugins"] as $k => $v) {
		if (!array_key_exists($v, $this->plugins)) unset($config["loadedPlugins"][$k]);
	}
	
	// Write the config/plugins.php file.
	if (!writeConfigFile("config/plugins.php", '$config["loadedPlugins"]', (array)$config["loadedPlugins"])) {
		$this->esoTalk->message("notWritable", false, "config/plugins.php");
		return false;
	}
	
	return true;
}

// Install an uploaded plugin.
function installPlugin()
{
	// If the uploaded file has any errors, don't proceed.
	if ($_FILES["installPlugin"]["error"]) {
		$this->esoTalk->message("invalidPlugin");
		return false;
	}
	
	// Temorarily move the uploaded plugin into the plugins directory so that we can read it.
	if (!move_uploaded_file($_FILES["installPlugin"]["tmp_name"], "plugins/{$_FILES["installPlugin"]["name"]}")) {
		$this->esoTalk->message("notWritable", false, "plugins/");
		return false;
	}
	
	// Unzip the plugin. If we can't, show an error.
	if (!($files = unzip("plugins/{$_FILES["installPlugin"]["name"]}", "plugins/"))) $this->esoTalk->message("invalidPlugin");
	else {
		
		// Loop through the files in the zip and make sure it's a valid plugin.
		$directories = 0; $pluginFound = false;
		foreach ($files as $k => $file) {
			
			// Strip out annoying Mac OS X files!
			if (substr($file["name"], 0, 9) == "__MACOSX/" or substr($file["name"], -9) == ".DS_Store") {
				unset($files[$k]);
				continue;
			}
			
			// If the zip has more than one base directory, it's not a valid plugin.
			if ($file["directory"] and substr_count($file["name"], "/") < 2) $directories++;
			
			// Make sure there's an actual plugin file in there.
			if (substr($file["name"], -10) == "plugin.php") $pluginFound = true;
		}
		
		// OK, this plugin in valid!
		if ($pluginFound and $directories == 1) {
			
			// Loop through plugin files and write them to the plugins directory.
			$error = false;
			foreach ($files as $k => $file) {
				
				// Make a directory if it doesn't exist!
				if ($file["directory"] and !is_dir("plugins/{$file["name"]}")) mkdir("plugins/{$file["name"]}");
				
				// Write a file.
				elseif (!$file["directory"]) {
					if (!writeFile("plugins/{$file["name"]}", $file["content"])) {
						$this->esoTalk->message("notWritable", false, "plugins/{$file["name"]}");
						$error = true;
						break;
					}
				}
			}
			
			// Everything copied over correctly - success!
			if (!$error) $this->esoTalk->message("pluginAdded");
		}
		
		// Hmm, something went wrong. Show an error.
		else $this->esoTalk->message("invalidPlugin");
	}
	
	// Delete the temporarily uploaded plugin file.
	unlink("plugins/{$_FILES["installPlugin"]["name"]}");
}
	
}

?>