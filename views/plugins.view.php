<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Plugins view: displays a list of plugins and their settings.

if (!defined("IN_ESOTALK")) exit;
?>

<?php // If there are installed plugins to display...
if (count($this->plugins)): ?>

<fieldset id='plugins'>
<legend><?php echo $language["Installed plugins"]; ?></legend>

<script type='text/javascript'>
// <![CDATA[
// Toggle whether a plugin is enabled or not.
function toggleEnabled(id, enabled) {
	Ajax.request({
		"url": esoTalk.baseURL + "ajax.php?controller=plugins",
		"post": "action=toggle&id=" + encodeURIComponent(id) + "&enabled=" + (enabled ? "1" : "0"),
		"success": function() {
			document.getElementById("ext-" + id).className = "plugin" + (enabled ? " enabled" : "");
		}
	});
}
// Toggle the visibility of a plugin's settings.
function toggleSettings(id) {
	for (var i in plugins) {
		if (plugins[i] != id && getById("ext-" + plugins[i] + "-settings") && getById("ext-" + plugins[i] + "-settings").showing)
			hide(getById("ext-" + plugins[i] + "-settings"), {animation: "verticalSlide"});
	}
	toggle(getById("ext-" + id + "-settings"), {animation: "verticalSlide"});
}
var plugins = [];
// ]]>
</script>

<ul>
	
<?php // Loop through each plugin and output its information.
foreach ($this->plugins as $k => $plugin): ?>
<li id='ext-<?php echo $k; ?>' class='plugin<?php if ($plugin["loaded"]): ?> enabled<?php endif; ?>'>
<div class='controls'>
<?php if (!empty($plugin["settings"])): ?><a href='javascript:toggleSettings("<?php echo $k; ?>");void(0)'><?php echo $language["settings"]; ?></a><?php endif; ?>
</div>
<input type='checkbox' class='checkbox'<?php if ($plugin["loaded"]): ?> checked='checked'<?php endif; ?> id='ext-<?php echo $k; ?>-checkbox' name='plugins[<?php echo $k; ?>]' value='1' onclick='toggleEnabled("<?php echo $k; ?>", this.checked);'/>
<noscript><div style='display:inline'><a href='<?php echo makeLink("plugins", "?toggle=$k", "&token={$_SESSION["token"]}"); ?>'><?php echo $plugin["loaded"] ? "Deactivate" : "Activate"; ?></a></div></noscript>	
<label for='ext-<?php echo $k; ?>-checkbox' class='checkbox'><strong><?php echo $plugin["name"]; ?></strong></label>
<small><?php printf($language["version"], $plugin["version"]); ?> <?php printf($language["author"], $plugin["author"]); ?></small> <small><?php echo $plugin["description"]; ?></small>

<?php // Output plugin settings.
if (!empty($plugin["settings"])): ?>
<div id='ext-<?php echo $k; ?>-settings' class='settings'>
<form action='<?php echo makeLink("plugins"); ?>' method='post'>
<input type='hidden' name='plugin' value='<?php echo $k; ?>'/>
<input type='hidden' name='token' value='<?php echo $_SESSION["token"]; ?>'/>
<?php echo $plugin["settings"]; ?>
</form>
</div>
<?php endif; ?>
<script type='text/javascript'>// <![CDATA[
plugins.push("<?php echo $k; ?>");
<?php if (!empty($plugin["settings"])):
	if (@$_POST["plugin"] != $k): ?>hide(getById("ext-<?php echo $k; ?>-settings"));<?php
	else: ?>getById("ext-<?php echo $k; ?>-settings").showing = true;<?php endif;
endif; ?> 
// ]]></script>
</li>
<?php endforeach; ?>

</ul>
</fieldset>

<?php // Otherwise if there are no plugins installed, show a message.
else: ?>
<?php echo $this->esoTalk->htmlMessage("noPluginsInstalled"); ?>
<?php endif; ?>

<?php // Add a new plugin form. ?>
<fieldset id='addPlugin'>
<legend><?php echo $language["Add a new plugin"]; ?></legend>
<?php echo $this->esoTalk->htmlMessage("downloadPlugins", "http://esotalk.com/plugins"); ?>
<form action='<?php echo makeLink("plugins"); ?>' method='post' enctype='multipart/form-data'>
<input type='hidden' name='token' value='<?php echo $_SESSION["token"]; ?>'/>
<ul class='form'>
<li><label><?php echo $language["Upload a plugin"]; ?></label> <input name='installPlugin' type='file' class='text' size='20'/></li>
<li><label></label> <?php echo $this->esoTalk->skin->button(array("value" => $language["Add plugin"])); ?></li>
</ul>
</form>
</fieldset>