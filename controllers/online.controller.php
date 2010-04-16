<?php
// Copyright 2010 Toby Zerner, Simon Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Online controller: fetches a list of members currently online, ready to be displayed in the view.

if (!defined("IN_ESOTALK")) exit;

class OnlineController extends Controller {
	
var $view = "online.view.php";

function init()
{
	global $language, $config;
	
	// Set the title and make sure this page isn't indexed.
	$this->title = $language["Online members"];
	$this->esoTalk->addToHead("<meta name='robots' content='noindex, noarchive'/>");
	
	// Fetch a list of members who have been logged in the members table as 'online' in the last
	// $config["userOnlineExpire"] seconds.
	$this->online = $this->esoTalk->db->query("SELECT memberId, name, avatarFormat, IF(color>{$this->esoTalk->skin->numberOfColors},{$this->esoTalk->skin->numberOfColors},color), account, lastSeen, lastAction FROM {$config["tablePrefix"]}members WHERE UNIX_TIMESTAMP()-{$config["userOnlineExpire"]}<lastSeen ORDER BY lastSeen DESC");
	$this->numberOnline = $this->esoTalk->db->numRows($this->online);
}
	
}

?>