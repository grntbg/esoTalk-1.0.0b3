<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Plugins view: displays a list of plugins and their settings.

if (!defined("IN_ESOTALK")) exit;
?>

<fieldset>
	<legend>Basic settings</legend>
	
	
	<form action='<?php echo makeLink("admin", "settings"); ?>' id='basicSettings' method='post'>
	<input type='hidden' name='token' value='<?php echo $_SESSION["token"]; ?>'/>
	
	
	<ul class='form settingsForm'>

	<li><label>Forum title</label>
	<div><input type='text' class='text' name='forumTitle' value='<?php echo $config["forumTitle"]; ?>'/></div></li>

	<li><label>Default forum language<br/></label>
	<div><select name='forumLanguage'><?php
foreach ($this->languages as $v)
	echo "<option value='$v'" . ($config["language"] == $v ? " selected='selected'" : "") . ">$v</option>";	
	?></select><br/><small>Upload languages packs to the <code>languages/</code> folder to see them here.</small></div></li>

	<li><label class='checkbox'>Use friendly URLs</label>
	<div><input type='checkbox' class='checkbox' name='useFriendlyURLs' value='1'<?php echo !empty($config["useFriendlyURLs"]) ? " checked='checked'" : ""; ?>/></div></li>

	<li><label></label> <span class='button'><input type='submit' name='saveSettings' value='Save changes'/></span></li>
	
	</ul>
	
</fieldset>

<fieldset>
	<legend>Forum logo</legend>
	
	
	<p class='msg info'>Your forum logo appears in the header of your forum, to the left of the title. Careful not to upload a logo too big, or the header will be stretched!</p>
	
	<form action='<?php echo makeLink("admin", "settings"); ?>' id='settingsLogo' method='post' enctype='multipart/form-data'>
	<input type='hidden' name='token' value='<?php echo $_SESSION["token"]; ?>'/>
	
	
	<ul class='form logoForm'>
		
		<li>
			<label>Current logo</label>
			<div><img src='<?php echo $this->esoTalk->skin->getForumLogo(); ?>'/></div>
		</li>

		<li>
		<label for='upload' class='radio'>
		<input type='radio' class='radio' value='upload' name='logo[type]' id='upload'/>
		Upload a logo from your computer</label>
		<input name='logoUpload' type='file' class='text' size='20' onchange='getById("upload").checked="true"'/>
		</li>

		<li>
		<label for='url' class='radio'>
		<input type='radio' class='radio' value='url' name='logo[type]' id='url'/>
		Enter the web address of a logo</label>
		<input name='logo[url]' type='text' class='text' onkeypress='getById("url").checked="true"' value=''/>
		</li>

		<li>
		<label for='none' class='radio'>
		<input type='radio' class='radio' value='none' name='logo[type]' id='none'/>
		Use default logo</label>
		</li>

		<li><label></label> <div><input type='checkbox' class='checkbox' name='resizeLogo' value='1'/> Automatically resize my logo</div></li>
		<li><label></label> <span class='button'><input type='submit' name='changeLogo' value='Change logo'/></span></li>


	

	
	</ul>
	
	</form>
	
</fieldset>