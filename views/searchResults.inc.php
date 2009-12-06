<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Search results: displays a table of search results using columns defined in the search controller.

if (!defined("IN_ESOTALK")) exit;
?>
<table cellspacing='0' cellpadding='2' class='c'>
<thead>
<tr><?php foreach ($this->resultsTable as $column): ?><th<?php if (!empty($column["class"])): ?> class='<?php echo $column["class"]; ?>'<?php endif; ?>><?php echo !empty($column["title"]) ? $column["title"] : "&nbsp;"; ?></th><?php endforeach; ?></tr>
<tr id='newResults' style='display:none'><td colspan='<?php count($this->resultsTable); ?>'><?php echo $this->esoTalk->htmlMessage("newSearchResults"); ?></td></tr>
</thead>
<tbody id='conversations'>

<?php
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