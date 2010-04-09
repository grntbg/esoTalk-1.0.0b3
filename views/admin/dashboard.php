<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Plugins view: displays a list of plugins and their settings.

if (!defined("IN_ESOTALK")) exit;
?>

<?php if ($latestVersion = $this->esoTalk->checkForUpdates() or $latestVersion = "1000") echo $this->esoTalk->htmlMessage("updatesAvailable", $latestVersion); ?>

<?php if (file_exists("install/")) echo $this->esoTalk->htmlMessage("removeFileWarning", "install/"); ?>

<?php if (file_exists("upgrade/")) echo $this->esoTalk->htmlMessage("removeFileWarning", "upgrade/"); ?>

<fieldset>
	<legend>Forum statistics!</legend>
	
	<ul class='form stats'>

	<li><label>Members</label>
	<div><?php echo $this->stats["members"]; ?></div></li>

	<li><label>Conversations</label>
	<div><?php echo $this->stats["conversations"]; ?></div></li>


	<li><label>Posts</label>
	<div><?php echo $this->stats["posts"]; ?></div></li>

	<li><label>New members in the past week</label>
	<div><?php echo $this->stats["newMembers"]; ?></div></li>

	<li><label>New conversations in the past week</label>
	<div><?php echo $this->stats["newConversations"]; ?></div></li>

	<li><label>New posts in the past week</label>
	<div><?php echo $this->stats["newPosts"]; ?></div></li>

	<li><label>Active members<br/><small>Members with more than 10 posts in the past month</small></label>
	<div><?php echo $this->stats["activeMembers"]; ?></div></li>
	
	<?php $this->fireEvent("forumStatistics"); ?>

	
	</ul>
	
</fieldset>

<fieldset>
	<legend>About your server</legend>
	
	<ul class='form stats'>

	<li><label>esoTalk version</label>
	<div><?php echo $this->server["esoTalkVersion"]; ?></div></li>

	<li><label>PHP version</label>
	<div><?php echo $this->server["phpVersion"]; ?></div></li>


	<li><label>MySQL version</label>
	<div><?php echo $this->server["mysqlVersion"]; ?></div></li>

	<?php $this->fireEvent("aboutServer"); ?>

	
	</ul>
</fieldset>