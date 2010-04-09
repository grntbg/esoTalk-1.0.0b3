<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Search view: displays tag and gambit clouds and includes the search form and results.

if (!defined("IN_ESOTALK")) exit;
?>
<?php $this->fireEvent("beforeRenderTagCloud"); ?>

<div id='tagArea'>
<p id='tags'><?php
// Echo the most common tags
ksort($this->tagCloud);
foreach ($this->tagCloud as $k => $v) {
	echo "<a href='" . makeLink("search", "?q2=" . urlencode(desanitize((!empty($_SESSION["search"]) ? "{$_SESSION["search"]} + " : "") . "{$language["gambits"]["tag:"]}$k"))) . "' class='$v'>" . str_replace(" ", "&nbsp;", $k) . "</a> ";
}
?></p>

<?php $this->fireEvent("afterRenderTagCloud"); ?>

<p id='gambits'><?php
// Echo the gambits alphabetically
ksort($this->gambitCloud);
foreach ($this->gambitCloud as $k => $v) {
	echo "<a href='" . makeLink("search", "?q2=" . urlencode(desanitize((!empty($_SESSION["search"]) ? "{$_SESSION["search"]} + " : "") . $k))) . "' class='$v'>" . str_replace(" ", "&nbsp;", $k) . "</a> ";
}
?></p>
</div>

<?php $this->fireEvent("beforeRenderSearchForm"); ?>

<?php include $this->esoTalk->skin->getView("searchForm.inc.php"); ?> 

<?php $this->fireEvent("afterRenderSearchForm"); ?>

<div id='searchResults'>
<?php include $this->esoTalk->skin->getView("searchResults.inc.php"); ?>
</div>

<?php $this->fireEvent("afterRenderSearchResults"); ?>

<script type='text/javascript'>
// <![CDATA[
Search.currentSearch = '<?php if (isset($_SESSION["search"])) echo addslashes(desanitize($_SESSION["search"])); ?>';
Search.init();
// ]]>
</script>