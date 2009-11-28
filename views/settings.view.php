<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Settings view: displays an interface where the user can change their avatar, color, password/email, and other
// settings.

if (!defined("IN_ESOTALK")) exit;
?>
<div id='settings'>

<fieldset id='appearance'>
<legend><?php echo $language["Appearance settings"]; ?></legend>

<div class='p <?php echo $this->esoTalk->user["avatarAlignment"] == "right" ? "r " : "l "; ?>c<?php echo $this->esoTalk->user["color"]; ?>' id='preview'>
<div class='parts'><div>
<div class='hdr'><div class='pInfo'><h3><?php echo $this->esoTalk->user["name"]; ?></h3></div></div>
<div class='body'>

<?php // Color palette. ?>
<div id='palette'><table cellspacing='0' cellpadding='0'><tr>
<?php for ($i = 1; $i <= $this->esoTalk->skin->numberOfColors; $i++): ?>
<td><a href='<?php echo makeLink("settings", "?changeColor=$i", "&token={$_SESSION["token"]}"); ?>' onclick='Settings.changeColor(<?php echo $i; ?>);return false' id='color-<?php echo $i; ?>' class='c<?php echo $i; ?><?php if ($this->esoTalk->user["color"] == $i) echo " selected"; ?>'></a></td>
<?php endfor; ?>
</tr></table></div>

<?php // Avatar selection form. ?>
<form action='<?php echo makeLink("settings"); ?>' id='settingsAvatar' method='post' enctype='multipart/form-data'>
<input type='hidden' name='token' value='<?php echo $_SESSION["token"]; ?>'/>
<ul class='form'>

<?php // Upload an avatar. ?>
<li>
<label for='upload' class='radio'>
<input type='radio' class='radio' value='upload' name='avatar[type]' id='upload'<?php if (@$_POST["avatar"]["type"] == "upload") echo " checked='checked'"; ?>/>
<?php echo $language["Upload an avatar"]; ?>
</label>
<input name='avatarUpload' type='file' class='text' size='20' onchange='getById("upload").checked="true"'/>
</li>

<?php // Get an avatar from a URL.
if (ini_get("allow_url_fopen")): ?>
<li>
<label for='url' class='radio'>
<input type='radio' class='radio' value='url' name='avatar[type]' id='url'<?php if (@$_POST["avatar"]["type"] == "url") echo " checked='checked'"; ?>/>
<?php echo $language["Enter the web address of an avatar"]; ?>
</label>
<input name='avatar[url]' type='text' class='text' onkeypress='getById("url").checked="true"' value='<?php if (!empty($_POST["avatar"]["url"])) echo $_POST["avatar"]["url"]; ?>'/>
</li>
<?php endif; ?>

<?php // Clear the avatar. ?>
<li>
<label for='none' class='radio'>
<input type='radio' class='radio' value='none' name='avatar[type]' id='none'<?php if (@$_POST["avatar"]["type"] == "none") echo " checked='checked'"; ?>/>
<?php echo $language["No avatar"]; ?>
</label>
</li>

<li><label></label> <?php echo $this->esoTalk->skin->button(array("name" => "changeAvatar", "value" => $language["Change avatar"])); ?></li>

</ul>
</form>

</div>
</div></div>
<div class='avatar'><img src='<?php
echo $this->esoTalk->getAvatar($this->esoTalk->user["memberId"], $this->esoTalk->user["avatarFormat"], $this->esoTalk->user["avatarAlignment"] == "right" ? "r" : "l"), "?", time();
?>' alt=''/></div>
<div class='clear'></div>
</div>

</fieldset>

<?php // Output a form with elements defined in the settings controller. ?>
<form action='<?php echo makeLink("settings"); ?>' method='post' enctype='multipart/form-data'>
<input type='hidden' name='token' value='<?php echo $_SESSION["token"]; ?>'/>

<?php
// Loop through the fieldsets in the form...
foreach ($this->form as $id => $fieldset):
	if (is_array($fieldset)):
		echo "<fieldset id='$id'>
<legend><a href='#' onclick='Settings.toggleFieldset(\"$id\");return false'>{$fieldset["legend"]}</a></legend>
<ul class='form' id='{$id}Form'>";
		ksort($fieldset);
		
		// Loop through the fields in the fieldsets...
		foreach ($fieldset as $k => $field):
			if ($k == "legend" or $k == "hidden") continue;
			if (is_array($field)):
				echo "<li>{$field["html"]}";
				if (!empty($field["message"])) echo $this->esoTalk->htmlMessage($field["message"]);
				echo "</li>";
 			else: echo $field; endif;
		endforeach;
		
		echo "</ul></fieldset>";
		if (!empty($fieldset["hidden"])):
			echo "<script type='text/javascript'>Settings.hideFieldset(\"$id\")</script>"; endif;
	else: echo $fieldset; endif;
endforeach;
?>
<?php echo $this->esoTalk->skin->button(array("value" => $language["Save changes"], "name" => "submit", "class" => "big submit")); ?>

</form>

<?php // Output the change my password or email form. ?>
<form action='<?php echo makeLink("settings"); ?>' method='post'>
<input type='hidden' name='token' value='<?php echo $_SESSION["token"]; ?>'/>
<fieldset id='settingsPassword'>
<legend><a href='#' onclick='Settings.toggleFieldset("settingsPassword");return false'><?php echo $language["Change my password or email"]; ?></a></legend>
<ul class='form' id='settingsPasswordForm'>
	
<li>
<label><?php echo $language["New password"]; ?> <small><?php echo $language["optional"]; ?></small></label> <input type='password' name='settingsPasswordEmail[new]' class='text' value='<?php echo @$_POST["settingsPasswordEmail"]["new"]; ?>'/>
<?php if (!empty($this->messages["new"])) echo $this->esoTalk->htmlMessage($this->messages["new"]); ?>
</li>

<li>
<label><small><?php echo $language["Confirm password"]; ?></small></label> <input type='password' name='settingsPasswordEmail[confirm]' class='text' value=''/>
<?php if (!empty($this->messages["confirm"])) echo $this->esoTalk->htmlMessage($this->messages["confirm"]); ?>
</li>

<li>
<label><?php echo $language["New email"]; ?> <small><?php echo $language["optional"]; ?></small></label> <input type='text' name='settingsPasswordEmail[email]' class='text' value='<?php echo @$_POST["settingsPasswordEmail"]["email"]; ?>'/>
<?php if (!empty($this->messages["email"])) echo $this->esoTalk->htmlMessage($this->messages["email"]); ?>
</li>

<li>
<label><?php echo $language["My current password"]; ?></label> <input type='password' name='settingsPasswordEmail[current]' class='text'/>
<?php if (!empty($this->messages["current"])) echo $this->esoTalk->htmlMessage($this->messages["current"]); ?>
</li>

<li><label></label> <?php echo $this->esoTalk->skin->button(array("value" => $language["Save changes"], "name" => "settingsPasswordEmail[submit]")); ?></li>

</ul></fieldset>
<?php if (!count($this->messages)): ?><script type='text/javascript'>Settings.hideFieldset("settingsPassword")</script><?php endif; ?>
</form>

</div>