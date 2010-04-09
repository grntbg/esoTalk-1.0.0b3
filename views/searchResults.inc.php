<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Search results: displays a table of search results using columns defined in the search controller.

if (!defined("IN_ESOTALK")) exit;
?>
<table cellspacing='0' cellpadding='2' class='c'>
<thead>
<tr><?php foreach ($this->resultsTable as $column): ?><th<?php if (!empty($column["class"])): ?> class='<?php echo $column["class"]; ?>'<?php endif; ?>><?php echo !empty($column["title"]) ? $column["title"] : "&nbsp;"; ?></th><?php endforeach; ?></tr>
<tr id='newResults' style='display:none'><td colspan='<?php echo count($this->resultsTable); ?>'><?php echo $this->esoTalk->htmlMessage("newSearchResults"); ?></td></tr>
</thead>
<tbody id='conversations'>

<?php
// Returns the HTML for the contents of a cell in the star column.
function columnStar(&$search, $conversation)
{
	return $search->esoTalk->htmlStar($conversation["id"], $conversation["starred"]);
}

// Returns the HTML for the contents of a cell in the avatar column.
function columnAvatar(&$search, $conversation)
{
	return "<img src='" . $search->esoTalk->getAvatar($conversation["startMemberId"], $conversation["avatarFormat"], "thumb") . "' alt='' class='thumb'/>";
}

// Returns the HTML for the contents of a cell in the conversation column (labels, title, tags.)
function columnConversation(&$search, $conversation)
{
	global $language;
	
	// $conversation["labels"] contains comma-separated values corresponding to each label in the $esoTalk->labels 
	// array (0 = label does not apply, 1 = label does apply.) Read this variable and output applied labels.
	$labels = explode(",", $conversation["labels"]); $i = 0; $labelsHtml = ""; $html = "";
	foreach ($search->esoTalk->labels as $k => $v) {
		if (@$labels[$i]) $labelsHtml .= "<span class='label $k'>{$language["labels"][$k]}</span> ";
		$i++;
	}
	if ($labelsHtml) $html .= "<span class='labels'>$labelsHtml</span>";
	
	// Output the conversation title.
	$html .= "<strong";
	if ($search->esoTalk->user and !$conversation["unread"]) $html .= " class='read'";
	$html .= "><a href='" . makeLink($conversation["id"], $conversation["slug"]) . "'>" . highlight($conversation["title"], $_SESSION["highlight"]) .  "</a></strong>";
	
	// A Jump to last/unread link, depending on the user.
	if ($search->esoTalk->user["name"] and $conversation["unread"]) $html .= "<small><a href='" . makeLink($conversation["id"], $conversation["slug"], "?start=unread") . "'>{$language["Jump to unread"]}</a></small>";
	else $html .= "<small><a href='" . makeLink($conversation["id"], $conversation["slug"], "?start=last") . "'>{$language["Jump to last"]}</a></small>";
	
	// And tags!
	$html .= "<br/><small class='tags'>{$conversation["tags"]}</small>";
	
	$search->fireEvent("getConversationColumn", array(&$html, $conversation));
	
	return $html;
}

// Returns the HTML for the contents of a cell in the post count column.
function columnPosts(&$search, $conversation)
{
	return "<span class='postCount p" . (($conversation["posts"] > 50) ? "1" : (($conversation["posts"] > 10) ? "2" : "3")) . "'>{$conversation["posts"]}</span>";
}

// Returns the HTML for the contents of a cell in the "started by" column.
function columnAuthor(&$search, $conversation)
{
	return "<a href='" . makeLink("profile", $conversation["startMemberId"]) . " '>{$conversation["startMember"]}</a><br/><small>" . relativeTime($conversation["startTime"]) . "</small>";
}

// Returns the HTML for the contents of a cell in the "last reply" column.
function columnLastReply(&$search, $conversation)
{
	$html = "<span class='lastPostMember'>";
	if ($conversation["posts"] > 1) $html .= "<a href='" . makeLink("profile", $conversation["lastPostMemberId"]) . "'>{$conversation["lastPostMember"]}</a>";
	$html .= "</span><br/><small class='lastPostTime'>";
	if ($conversation["posts"] > 1) $html .= relativeTime($conversation["lastPostTime"]);
	$html .= "</small>";
	return $html;
}


// If there are results, loop through the conversations and output a table row for each one.
if (count($this->results)):
foreach ($this->results as $conversation): ?>

<tr id='c<?php echo $conversation["id"]; ?>'<?php if ($conversation["starred"]): ?> class='starred'<?php endif; ?>>
<?php

// Loop through the columns defined in the search controller and echo the output of a callback function for the cell
// contents.
foreach ($this->resultsTable as $column): ?><td<?php if (!empty($column["class"])): ?> class='<?php echo $column["class"]; ?>'<?php endif; ?>><?php echo call_user_func_array($column["content"], array(&$this, $conversation)); ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach;
endif; ?>

</tbody>
</table>

<?php
// If there are no conversations, show a message.
if (!$this->numberOfConversations): echo $this->esoTalk->htmlMessage("noSearchResults");

// On the other hand, if there were too many results, show a 'show more' message.
elseif ($this->limit == $config["results"] + 1 and $this->numberOfConversations > $config["results"]): ?>
<div id='more'>
<?php echo $this->esoTalk->htmlMessage("viewMore", array(makeLink("search", urlencode(@$_SESSION["search"] . (@$_SESSION["search"] ? " + " : "") . "more results")))); ?>
</div>
<?php endif; ?>