<?php
// Copyright 2010 Toby Zerner, Simon Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Plugins view: displays a list of plugins and their settings.

if (!defined("IN_ESOTALK")) exit;
?>

<?php // Use an AJAX request to check for updates so that initial page loading isn't slow. ?>
<div id='updateMessage'></div>
<script type='text/javascript'>
// <![CDATA[
Ajax.request({
	"url": esoTalk.baseURL + "ajax.php?controller=admin&section=dashboard",
	"post": "action=checkForUpdates",
	"success": function() {
		if (this.result) getById("updateMessage").innerHTML = this.result;
		show(getById("updateMessage"), {animation: "verticalSlide"});
	}
})
// ]]>
</script>

<?php if (file_exists("install/")) echo $this->esoTalk->htmlMessage("removeDirectoryWarning", "install/"); ?>

<?php if (file_exists("upgrade/")) echo $this->esoTalk->htmlMessage("removeDirectoryWarning", "upgrade/"); ?>

<fieldset>
<legend><?php echo translate("Forum statistics"); ?></legend>
<ul class='form stats'>

<?php foreach ($this->stats as $k => $v): ?>
<li><label><?php echo translate($k); ?></label>
<div><?php echo $v; ?></div></li>
<?php endforeach; ?>
	
<?php $this->fireEvent("forumStatistics"); ?>
</ul>	
</fieldset>

<fieldset>
<legend><?php echo translate("Server information"); ?></legend>
<ul class='form stats'>

<?php foreach ($this->serverInfo as $k => $v): ?>
<li><label><?php echo translate($k); ?></label>
<div><?php echo $v; ?></div></li>
<?php endforeach; ?>

<?php $this->fireEvent("serverInformation"); ?>

</ul>
</fieldset>