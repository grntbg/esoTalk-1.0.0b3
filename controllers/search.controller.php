<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Search controller: Performs a search with gambits, and gets the tag cloud.

if (!defined("IN_ESOTALK")) exit;

class search extends Controller {

var $view = "search.view.php";
var $tagCloud = array();
var $searchString = "";

// Fields to select, conditions, orders, and more.
var $select = array();
var $from = array();
var $conditions = array();
var $orderBy = array();
var $limit;
var $reverse = false;

// Gambit arrays.
var $gambitCloud = array();
var $aliases = array();
var $gambits = array();

// Results.
var $results = array();
var $resultsTable = array();
var $numberOfConversations = 0;

function Search()
{
	if (isset($_POST["search"])) redirect("search", "?q2=" . urlencode(desanitize($_POST["search"])));
}

// Initialise: define the gambits, set up some of the search components, set up the page, and perform the search!
function init()
{
	global $config, $language;
	
	// Add the default gambits to the collection.
	// Each gambit is made up of a function and an eval() condition that is used to determine if a search "term" matches
	// the gambit.
	$this->gambits = array_merge($this->gambits, array(
		array(array($this, "gambitStarred"), 'return $term == strtolower($language["gambits"]["starred"]);'),
		array(array($this, "gambitDraft"), 'return $term == strtolower($language["gambits"]["draft"]);'),
		array(array($this, "gambitTag"), 'return strpos($term, strtolower($language["gambits"]["tag:"])) === 0;'),
		array(array($this, "gambitPrivate"), 'return $term == strtolower($language["gambits"]["private"]);'),
		array(array($this, "gambitSticky"), 'return $term == strtolower($language["gambits"]["sticky"]);'),
		array(array($this, "gambitLocked"), 'return $term == strtolower($language["gambits"]["locked"]);'),
		array(array($this, "gambitAuthor"), 'return strpos($term, strtolower($language["gambits"]["author:"])) === 0;'),
		array(array($this, "gambitContributor"), 'return strpos($term, strtolower($language["gambits"]["contributor:"])) === 0;'),
		array(array($this, "gambitActive"), 'return preg_match($language["gambits"]["gambitActive"], $term, $this->matches);'),
		array(array($this, "gambitHasNPosts"), 'return preg_match($language["gambits"]["gambitHasNPosts"], $term, $this->matches);'),
		array(array($this, "gambitOrderByPosts"), 'return $term == strtolower($language["gambits"]["order by posts"]);'),
		array(array($this, "gambitOrderByNewest"), 'return $term == strtolower($language["gambits"]["order by newest"]);'),
		array(array($this, "gambitUnread"), 'return $term == strtolower($language["gambits"]["unread"]);'),
		array(array($this, "gambitRandom"), 'return $term == strtolower($language["gambits"]["random"]);'),
		array(array($this, "gambitReverse"), 'return $term == strtolower($language["gambits"]["reverse"]);'),
		array(array($this, "gambitMoreResults"), 'return $term == strtolower($language["gambits"]["more results"]);'),
		array(array($this, "fulltext"), 'return $term;'),
	));
	
	// Add the default gambits to the gambit cloud: gambit text => css class to apply.
	$this->gambitCloud += array(
		$language["gambits"]["active last ? hours"] => "s4",
		$language["gambits"]["active last ? days"] => "s5",
		$language["gambits"]["active today"] => "s2",
		$language["gambits"]["author:"] . $language["gambits"]["member"] => "s5",
		$language["gambits"]["contributor:"] . $language["gambits"]["member"] => "s5",
		$language["gambits"]["dead"] => "s4",
		$language["gambits"]["has replies"] => "s2",
		$language["gambits"]["has &gt;10 posts"] => "s4",
		$language["gambits"]["locked"] => "s4 lockedText",
		$language["gambits"]["more results"] => "s2",
		$language["gambits"]["order by newest"] => "s4",
		$language["gambits"]["order by posts"] => "s2",
		$language["gambits"]["random"] => "s5",
		$language["gambits"]["reverse"] => "s4",
		$language["gambits"]["sticky"] => "s2 stickyText",
	);
	// Only show the contributor:myself and author:myself gambits if there is a user logged in.
	if ($this->esoTalk->user) {
		$this->gambitCloud += array(
			$language["gambits"]["contributor:"] . $language["gambits"]["myself"] => "s4",
			$language["gambits"]["author:"] . $language["gambits"]["myself"] => "s2",
			$language["gambits"]["draft"] => "s1 draftText",
			$language["gambits"]["private"] => "s1 privateText",
			$language["gambits"]["starred"] => "s1 starredText",
			$language["gambits"]["unread"] => "s1"
		);
	}
	
	// Add default aliases. An alias is a string of text which is just shorthand for a more complex gambit.
	$this->aliases += array(
		$language["gambits"]["active today"] => $language["gambits"]["active 1 day"],
		$language["gambits"]["has replies"] => $language["gambits"]["has &gt; 1 post"],
		$language["gambits"]["has no replies"] => $language["gambits"]["has 0 posts"],
		$language["gambits"]["dead"] => $language["gambits"]["active &gt; 30 day"]
	);
	
	// Define the columns of the search results table.
	if ($this->esoTalk->user) $this->resultsTable[] = array("class" => "star", "content" => "columnStar");
	if (!empty($config["showAvatarThumbnails"])) $this->resultsTable[] = array("class" => "avatar", "content" => "columnAvatar");
	$this->resultsTable[] = array("title" => $language["Conversation"], "content" => "columnConversation");
	$this->resultsTable[] = array("title" => $language["Posts"], "class" => "posts", "content" => "columnPosts");
	$this->resultsTable[] = array("title" => $language["Started by"], "class" => "author", "content" => "columnAuthor");
	$this->resultsTable[] = array("title" => $language["Last reply"], "class" => "lastPost", "content" => "columnLastReply");
	
	// Mark all conversations as read if requested.
	if (isset($_GET["markAsRead"]) and $this->esoTalk->user and !defined("AJAX_REQUEST"))
	 	$this->markAllConversationsAsRead();
	
	// Construct the SELECT and FROM parts of the final query that gets the result details.
	$markedAsRead = !empty($this->esoTalk->user["markedAsRead"]) ? $this->esoTalk->user["markedAsRead"] : "0";
	$memberId = $this->esoTalk->user ? $this->esoTalk->user["memberId"] : 0;
	$this->select = array("c.conversationId AS id", "c.title AS title", "c.slug AS slug", "c.sticky AS sticky", "c.private AS private", "c.locked AS locked", "c.posts AS posts", "sm.name AS startMember", "c.startMember AS startMemberId", "sm.avatarFormat AS avatarFormat", "c.startTime AS startTime", "lpm.name AS lastPostMember", "c.lastPostMember AS lastPostMemberId", "c.lastPostTime AS lastPostTime", "GROUP_CONCAT(t.tag ORDER BY t.tag ASC SEPARATOR ', ') AS tags", "(IF(c.lastPostTime IS NOT NULL,c.lastPostTime,c.startTime)>$markedAsRead AND (s.lastRead IS NULL OR s.lastRead<c.posts)) AS unread", "s.starred AS starred", "CONCAT(" . implode(",',',", $this->esoTalk->labels) . ") AS labels");
	$this->from = array(
		"{$config["tablePrefix"]}conversations c",
		"LEFT JOIN {$config["tablePrefix"]}tags t USING (conversationId)",
		"LEFT JOIN {$config["tablePrefix"]}status s ON (s.conversationId=c.conversationId AND s.memberId=$memberId)",
		"INNER JOIN {$config["tablePrefix"]}members sm ON (c.startMember=sm.memberId)",
		"LEFT JOIN {$config["tablePrefix"]}members lpm ON (c.lastPostMember=lpm.memberId)"
	);
		
	if (!defined("AJAX_REQUEST")) {
	
		// Assign the latest search to a session variable.
		if (isset($_GET["q"])) $_GET["q2"] = $_GET["q"];
		$this->searchString = $_SESSION["search"] = @$_GET["q2"];
		
		// Add JavaScript language definitions and variables.
		$this->esoTalk->addLanguageToJS("Starred", "Unstarred", array("gambits", "member"), array("gambits", "tag:"), array("gambits", "more results"));
		$this->esoTalk->addVarToJS("updateCurrentResultsInterval", $config["updateCurrentResultsInterval"]);
		$this->esoTalk->addVarToJS("checkForNewResultsInterval", $config["checkForNewResultsInterval"]);
		
		// Add a link to the RSS feed in the bar.
		$this->esoTalk->addToBar("right", "<a href='" . makeLink("feed") . "' id='rss'><span>{$language["RSS"]}</span></a>", 500);
		
		// Update the user's last action.
		$this->esoTalk->updateLastAction("");
		
		// Get the most common tags from the tags table and assign them a text-size class based upon their frequency.
		$result = $this->esoTalk->db->query("SELECT tag, COUNT(tag) AS count FROM {$config["tablePrefix"]}tags GROUP BY tag ORDER BY count DESC LIMIT {$config["numberOfTagsInTagCloud"]}");
		$tags = array();
		if ($rows = $this->esoTalk->db->numRows($result)) {
			for ($i = 1; list($tag) = $this->esoTalk->db->fetchRow($result); $i++) {
				$this->tagCloud[$tag] = "s" . ceil($i * (5 / $rows));
				if ($i < 10) $tags[] = $tag;
			}
		}
		
		// Add meta tags to the header and the "Mark all conversations as read" link to the footer.
		$this->esoTalk->addToHead("<meta name='keywords' content='" . implode(",", $tags) . "'/>");
		if (count($tags)) {
			list($lastTag) = array_splice($tags, count($tags) - 1, 1);
			$this->esoTalk->addToHead("<meta name='description' content='" . sprintf($language["forumDescription"], $config["forumTitle"], implode(", ", $tags), $lastTag) . "'/>");
		}
		if ($this->esoTalk->user) $this->esoTalk->addToFooter("<a href='" . makeLink("?markAsRead") . "'>{$language["Mark all conversations as read"]}</a>");
		
		// If this is not technically the homepage (if it's a search page) the we don't want it to be indexed.
		if (@$_GET["q1"] == "search") $this->esoTalk->addToHead("<meta name='robots' content='noindex, noarchive'/>");
				
	}
	
	$this->callHook("init");
	
	// Last, but definitely not least... perform the search!
	if (!defined("AJAX_REQUEST")) $this->results = $this->doSearch($this->searchString);
}

// Update the "markedAsRead" field in the user's database row to the current time.
// Any conversations with last activity before this time will be regarded as "read".
function markAllConversationsAsRead()
{
	global $config;
	$this->esoTalk->db->query("UPDATE {$config["tablePrefix"]}members SET markedAsRead=" . time() . " WHERE memberId={$this->esoTalk->user["memberId"]}");
	$this->esoTalk->user["markedAsRead"] = $_SESSION["user"]["markedAsRead"] = time();
}

// Register a custom gambit:
// $text is the text that will appear in the gambit cloud.
// $class is the CSS className that will be applied to the text.
// $function is the function to be called if the gambit is detected
// 		(called with call_user_func($function, $gambit, $negate))
// $condition is the eval() code to be run to see if the gambit is in the search string. eg. 'return $v == "sticky";'
function registerGambit($text, $class, $function, $condition)
{
	$this->gambitCloud[$text] = $class;
	$this->gambits[] = array($function, $condition);
}

// Apply a condition to the search results.
// This takes effect when collecting conversation IDs through the following query:
// SELECT DISTINCT conversationId FROM $table WHERE $condition
function condition($table, $condition, $negate = false)
{
	$condition = "($condition)";
	if (in_array(array($table, $condition, $negate), $this->conditions)) return;
	$this->conditions[] = array($table, $condition, $negate);
}

// Apply an order to the search results.
function orderBy($order)
{
	$this->orderBy[] = $order;
}

// Apply a limit to the search results.
function limit($limit)
{
	$this->limit = $limit;
}

// Add an expression to the "select" part of the query which gets conversation details.
function select($expression)
{
	$this->select[] = $expression;
}

// Add a table or a JOIN clause to the "from" part of the query which gets conversation details.
function addTable($table)
{
	$this->from[] = $table;
}

// Add an array of words to be highlighted in the search results and also after clicking through to a conversations.
function highlight($wordList)
{
	foreach ($wordList as $k => $v) {
		if (!$v or in_array($v, $_SESSION["highlight"])) continue;
		$_SESSION["highlight"][] = $v;
	}
}

// Deconstruct a search query and construct a list of conversation IDs that fulfill it.
function getConversationIDs($search = "")
{
	global $config, $language;
	
	// Add some preliminary conditions to the search results.
	// These make sure conversations that the user isn't allowed to see are filtered out.
	if (!$this->esoTalk->user) $this->condition("conversations", "c.posts!=0 AND c.private=0");
	else $this->condition("conversations", "c.startMember={$this->esoTalk->user["memberId"]} OR (c.posts>0 AND (c.private=0 OR EXISTS (SELECT allowed FROM {$config["tablePrefix"]}status WHERE conversationId=c.conversationId AND memberId IN ('{$this->esoTalk->user["account"]}',{$this->esoTalk->user["memberId"]}) AND allowed=1)))");
	
	// Process the search string into individial terms, but only keep the first 10 terms!
	$terms = !empty($search) ? explode("+", strtolower(str_replace("-", "+!", trim($search, " +-")))) : array();
	$terms = array_slice($terms, 0, 10);
	
	// Take each term, match it with a gambit, and execute the gambit's function.
	foreach ($terms as $term) {

		// Are we dealing with a negated search term, ie. prefixed with a "!"?
		$term = trim($term);
		if ($negate = ($term[0] == "!")) $term = trim($term, "! ");

		// If the term is an alias, translate it into the appropriate gambit.
		if (array_key_exists($term, $this->aliases)) $term = $this->aliases[$term];

		// Find a matching gambit by evaluating each gambit's condition.
		foreach ($this->gambits as $gambit) {
			list($function, $condition) = $gambit;
			if (eval($condition)) {
				call_user_func_array($function, array(&$this, $term, $negate));
				break;
			}
		}
	}
	
	// If an order for the search results has not been specified, apply a default.
	// For guests, order by sticky and then last post time.
	// For members, order by sticky+unread and then last post time.
	if (!count($this->orderBy)) {
		if (!$this->esoTalk->user) {
			$this->orderBy("c.sticky DESC");
			$this->orderBy("c.lastPostTime DESC");
		} else {
			$this->orderBy("IF(c.sticky AND ((SELECT lastRead FROM {$config["tablePrefix"]}status s WHERE conversationId=c.conversationId AND s.memberId={$this->esoTalk->user["memberId"]}) IS NULL OR (SELECT lastRead FROM {$config["tablePrefix"]}status s WHERE conversationId=c.conversationId AND s.memberId={$this->esoTalk->user["memberId"]})<c.posts),1,0) DESC");
			$this->orderBy("c.lastPostTime DESC");
		}
	}
	
	// Now we need to loop through the conditions and run them as queries one-by-one. When a query returns a selection
	// of conversation IDs, subsequent queries are restricted to filtering those conversation IDs.
	$goodConversationIds = $badConversationIds = array();
	$conversationConditions = array();
	$idCondition = "";
	foreach ($this->conditions as $v) {
		list($table, $condition, $negate) = $v;
		
		// If the condition is based on the conversations table, we'll save it for inclusion in the final query.
		if ($table == "conversations") {
			$conversationConditions[] = $condition;
			continue;
		}
		
		// Construct a query that will find conversation IDs that meet the condition.
		$prefix = strpos($table, "conversations c") !== false ? "c." : "";
		$query = "SELECT DISTINCT {$prefix}conversationId FROM {$config["tablePrefix"]}{$v[0]} WHERE $condition $idCondition";
	
		// Get the list of conversation IDs so that the next condition can use it in its query.
		$result = $this->esoTalk->db->query($query);
		$ids = array();
		while (list($conversationId) = $this->esoTalk->db->fetchRow($result)) $ids[] = $conversationId;
		
		// If this condition is negated, then add the IDs to the list of bad conversations.
		// If the condition is not negated, set the list of good conversations to the IDs, provided there are some.
		if ($negate) $badConversationIds = array_merge($badConversationIds, $ids);
		elseif (count($ids)) $goodConversationIds = $ids;
		else return false;
		
		// Strip bad conversation IDs from the list of good conversation IDs.
		if (count($goodConversationIds)) {
			$goodConversationIds = array_diff($goodConversationIds, $badConversationIds);
			if (!count($goodConversationIds)) return false;
		}
		
		// This will be the condition for the next query that restricts or eliminates conversation IDs.
		if (count($goodConversationIds))
			$idCondition = " AND conversationId IN (" . implode(",", $goodConversationIds) . ")";
		elseif (count($badConversationIds))
			$idCondition = " AND conversationId NOT IN (" . implode(",", $badConversationIds) . ")";
	}
	
	// Reverse the order if necessary - swap DESC and ASC.
	if ($this->reverse) {
		foreach ($this->orderBy as $k => $v)
			$this->orderBy[$k] = strtr($this->orderBy[$k], array("DESC" => "ASC", "ASC" => "DESC"));
	}
	
	// Set a default limit if none has previously been set.
	if (!$this->limit) $this->limit = $config["results"] + 1;
	
	// Collect the query components...
	$conditions = $idCondition ? array_merge($conversationConditions, array(substr($idCondition, 5))) : $conversationConditions;
	$components = array(
		"select" => array("c.conversationId"),
		"from" => array("{$config["tablePrefix"]}conversations c"),
		"where" => $conditions,
		"orderBy" => $this->orderBy,
		"limit" => $this->limit
	);
	
	$this->callHook("getConversationIds", array(&$components));
	
	// ...and construct and execute the query!
	$query = $this->esoTalk->db->constructSelectQuery($components);
	$result = $this->esoTalk->db->query($query);
	
	// Collect the final set of conversation IDs and return it.
	$conversationIds = array();
	while (list($conversationId) = $this->esoTalk->db->fetchRow($result)) $conversationIds[] = $conversationId;
	return count($conversationIds) ? $conversationIds : false;
}

// Perform a search and return results.
function doSearch($search = "")
{
	global $config;
	
	// Reset highlighted keywords.
	$_SESSION["highlight"] = array();
	
	// If they are searching for something, take some flood control measures.
	if ($search and $config["searchesPerMinute"] > 0) {
	
		// If we have a record of their searches in the session, check how many searches they've performed in the last 
		// minute.
		if (!empty($_SESSION["searches"])) {
			// Clean anything older than 60 seconds out of the searches array.
			foreach ($_SESSION["searches"] as $k => $v) {
				if ($v < time() - 60) unset($_SESSION["searches"][$k]);
			}
			// Have they performed >= $config["searchesPerMinute"] searches in the last minute? If so, don't continue.
			if (count($_SESSION["searches"]) >= $config["searchesPerMinute"]) {
				$this->esoTalk->message("waitToSearch", true, array(60 - time() + min($_SESSION["searches"])));
				return;
			}
		}
		
		// However, if we don't have a record in the session, use the MySQL searches table.
		else {
			// Get the user's IP address.
			$ip = (int)ip2long($_SESSION["ip"]);
			// Have they performed >= $config["searchesPerMinute"] searches in the last minute?
			if ($this->esoTalk->db->result("SELECT COUNT(*) FROM {$config["tablePrefix"]}searches WHERE ip=$ip AND searchTime>UNIX_TIMESTAMP()-60", 0) >= $config["searchesPerMinute"]) {
				$this->esoTalk->message("waitToSearch", true, 60);
				return;
			}
			// Log this search in the searches table.
			$this->esoTalk->db->query("INSERT INTO {$config["tablePrefix"]}searches (ip, searchTime) VALUES ($ip, UNIX_TIMESTAMP())");
			// Proactively clean the searches table of searches older than 60 seconds.
			$this->esoTalk->db->query("DELETE FROM {$config["tablePrefix"]}searches WHERE searchTime<UNIX_TIMESTAMP()-60");
		}
		
		// Log this search in the session array.
		if (!isset($_SESSION["searches"]) or !is_array($_SESSION["searches"])) $_SESSION["searches"] = array();
		$_SESSION["searches"][] = time();
		
	}
	
	// Get the conversation IDs that match the search terms.
	if (!$conversationIds = $this->getConversationIDs($search)) return;
	$conversationIds = implode(",", $conversationIds);
	
	// Construct a query to get details for all of the specified conversations.
	$components = array(
		"select" => $this->select,
		"from" => $this->from,
		"where" => "c.conversationId IN ($conversationIds)",
		"groupBy" => "c.conversationId",
		"orderBy" => "FIELD(c.conversationId,$conversationIds)"
	);
	
	$this->callHook("beforeGetResults", array(&$components));
	
	// Put the query together and execute it.
	$query = $this->esoTalk->db->constructSelectQuery($components);
	$result = $this->esoTalk->db->query($query);
	
	// Put the details of the conversations into an array to be displayed in the view.
	$results = array();
	$conversationsToDisplay = $this->limit == ($config["results"] + 1) ? $config["results"] : $config["moreResults"];
	if ($this->numberOfConversations = $this->esoTalk->db->numRows($result)) {
		for ($i = 0; $i < $conversationsToDisplay and ($conversation = $this->esoTalk->db->fetchAssoc($result)); $i++)
			$results[] = $conversation;
	}
	
	$this->callHook("afterGetResults", array(&$results));
	
	return $results;
}

// Run AJAX actions.
function ajax()
{
	global $config, $language;
	
	if ($return = $this->callHook("ajax", null, true)) return $return;
	
	switch (@$_POST["action"]) {
		
		// Perform a search and return the results HTML.
		case "search":
			$this->view = "searchResults.inc.php";
			$this->searchString = $_SESSION["search"] = $_POST["query"];
			$this->results = $this->doSearch($this->searchString);
			ob_start();
			$this->render();
			return ob_get_clean();
			break;
		
		// Update the current resultset details (unread, last post details, post count.)
		case "updateCurrentResults":
		
			// Work out which conversations we need to get details for (according to $_POST["conversationIds"].)
			$conversationIds = explode(",", $_POST["conversationIds"]);
			foreach ($conversationIds as $k => $v) if (!($conversationIds[$k] = (int)$v)) unset($conversationIds[$k]);
			$conversationIds = implode(",", array_unique($conversationIds));
			if (!$conversationIds) return;
			
			// We're going to run a query to get the details of all specified conversations.
			$markedAsRead = !empty($this->esoTalk->user["markedAsRead"]) ? $this->esoTalk->user["markedAsRead"] : "0";
			$memberId = $this->esoTalk->user ? $this->esoTalk->user["memberId"] : 0;
			$allowedPredicate = !$this->esoTalk->user ? "c.posts>0 AND c.private=0" : "c.startMember={$this->esoTalk->user["memberId"]} OR (c.posts>0 AND (c.private=0 OR s.allowed OR (SELECT allowed FROM {$config["tablePrefix"]}status WHERE conversationId=c.conversationId AND memberId='{$this->esoTalk->user["account"]}')))";
			$query = "SELECT c.conversationId, (IF(c.lastPostTime IS NOT NULL,c.lastPostTime,c.startTime)>$markedAsRead AND (s.lastRead IS NULL OR s.lastRead<c.posts)) AS unread, lpm.name AS lastPostMember, c.lastPostMember AS lastPostMemberId, c.lastPostTime AS lastPostTime, c.posts AS posts, s.starred AS starred
				FROM {$config["tablePrefix"]}conversations c
				LEFT JOIN {$config["tablePrefix"]}status s ON (s.conversationId=c.conversationId AND s.memberId=$memberId)
				LEFT JOIN {$config["tablePrefix"]}members lpm ON (c.lastPostMember=lpm.memberId)
				WHERE c.conversationId IN ($conversationIds) AND ($allowedPredicate)";
			$result = $this->esoTalk->db->query($query);
			
			// Loop through these conversations and construct an array of details to return in JSON format.
			$conversations = array();
			while (list($id, $unread, $lastPostMember, $lastPostMemberId, $lastPostTime, $postCount, $starred) = $this->esoTalk->db->fetchRow($result)) {
				$conversations[$id] = array(
					"unread" => !$this->esoTalk->user or $unread,
					"lastPostMember" => "<a href='" . makeLink("profile", $lastPostMemberId) . "'>$lastPostMember</a>",
					"lastPostTime" => relativeTime($lastPostTime),
					"postCount" => $postCount,
					"starred" => (int)$starred
				);
			}
			
			return array("conversations" => $conversations, "statistics" => $this->esoTalk->getStatistics());
			break;
		
		// Check for differing results to the current resultset (i.e. new conversations) and notify the user if there is
		// new activity.
		case "checkForNewResults":
			
			$this->searchString = $_POST["query"];
			
			// If the "random" gambit is in the search string, then don't go any further (because the results will 
			// obviously differ!)
			$terms = $this->searchString ? explode("+", strtolower(str_replace("-", "+!", trim($this->searchString, " +-")))) : array();
			foreach ($terms as $v) {
				if (trim($v) == $language["gambits"]["random"]) return array("newActivity" => false);
			}
			
			// Search flood control - if the user has performed >= $config["searchesPerMinute"] searches in the last 
			// minute, don't bother checking for new results.
			if ($this->searchString and $config["searchesPerMinute"] > 0) {
				// Check the session record of searches if it exists.
				if (!empty($_SESSION["searches"])) {
					foreach ($_SESSION["searches"] as $k => $v) {
						if ($v < time() - 60) unset($_SESSION["searches"][$k]);
					}
					if (count($_SESSION["searches"]) >= $config["searchesPerMinute"]) return array("newActivity" => false);
				// Otherwise, check the database.
				} else {
					$ip = (int)ip2long($_SESSION["ip"]);
					if ($this->esoTalk->db->result("SELECT COUNT(*) FROM {$config["tablePrefix"]}searches WHERE ip=$ip AND searchTime>UNIX_TIMESTAMP()-60", 0) >= $config["searchesPerMinute"]) return array("newActivity" => false);
				}
			}
			
			// Get a list of conversation IDs that match the search string.
			$this->limit($config["results"]);
			$newConversationIds = $this->getConversationIDs($this->searchString);
			
			// Get an array of conversationId's are in the current resultset.
			$conversationIds = explode(",", $_POST["conversationIds"]);
			foreach ($conversationIds as $k => $v) if (!($conversationIds[$k] = (int)$v)) unset($conversationIds[$k]);
			$conversationIds = array_unique($conversationIds);

			// Get the difference of the two sets of conversationId's.
			if (!is_array($newConversationIds) or !is_array($conversationIds)) return array("newActivity" => false);
			$diff = array_diff($newConversationIds, $conversationIds);
			return array("newActivity" => count($diff));
	}
	
}

// Gambit functions.

// Unread gambit: get conversations that are unread.
function gambitUnread(&$search, $term, $negate)
{
	global $config;
	if (!$this->esoTalk->user) return false;
	$markedAsRead = !empty($this->esoTalk->user["markedAsRead"]) ? $this->esoTalk->user["markedAsRead"] : "NULL";
	$lastRead = "(SELECT lastRead FROM {$config["tablePrefix"]}status s WHERE conversationId=c.conversationId AND s.memberId={$this->esoTalk->user["memberId"]})";
	$search->condition("conversations", ($negate ? "NOT " : "") . "(IF(c.lastPostTime IS NOT NULL,c.lastPostTime,c.startTime)>$markedAsRead AND ($lastRead IS NULL OR $lastRead<c.posts))");
}

// Private gambit: get private conversations.
function gambitPrivate(&$search, $term, $negate)
{
	$search->condition("conversations", "c.private=" . ($negate ? "0" : "1"));
}

// Starred gambit: get starred conversations.
function gambitStarred(&$search, $term, $negate)
{
	$id = $this->esoTalk->user ? $this->esoTalk->user["memberId"] : "0";
	$search->condition("status", "memberId=$id AND starred=1", $negate);
}

// Tag gambit: get conversations with a specific tag.
function gambitTag(&$search, $term, $negate)
{
	global $language;
	$term = trim(substr($term, strlen($language["gambits"]["tag:"])));
	$search->condition("tags", "tag='$term'", $negate);
}

// Active gambit: get conversations active in the specified time period.
function gambitActive(&$search, $term, $negate)
{
	global $language;
	switch ($search->matches["c"]) {
		case $language["gambits"]["minute"]: $search->matches["b"] *= 60; break;
		case $language["gambits"]["hour"]: $search->matches["b"] *= 3600; break;
		case $language["gambits"]["day"]: $search->matches["b"] *= 86400; break;
		case $language["gambits"]["week"]: $search->matches["b"] *= 604800; break;
		case $language["gambits"]["month"]: $search->matches["b"] *= 2626560; break;
		case $language["gambits"]["year"]: $search->matches["b"] *= 31536000;
	}
	$search->matches["a"] = (!$search->matches["a"] or $search->matches["a"] == $language["gambits"]["last"]) ? "<=" : str_replace(array("&gt;", "&lt;"), array(">", "<"), $search->matches["a"]);
	if ($negate) {
		switch ($search->matches["a"]) {
			case "<": $search->matches["a"] = ">="; break;
			case "<=": $search->matches["a"] = ">"; break;
			case ">": $search->matches["a"] = "<="; break;
			case ">=": $search->matches["a"] = "<";
		}
	}
	$search->condition("conversations", "UNIX_TIMESTAMP() - {$search->matches["b"]} {$search->matches["a"]} IF(c.lastPostTime IS NOT NULL,c.lastPostTime,c.startTime)");
}

// Author gambit: get conversations with a particular author.
function gambitAuthor(&$search, $term, $negate)
{
	global $config, $language;
	$term = trim(substr($term, strlen($language["gambits"]["author:"])));
	if ($term == $language["gambits"]["myself"]) $term = $search->esoTalk->user["name"];
	$search->condition("conversations", "c.startMember" . ($negate ? "!" : "") . "=(SELECT memberId FROM {$config["tablePrefix"]}members WHERE name='$term')");
}

// Contributor gambit: get conversations which contain posts by a particular member.
function gambitContributor(&$search, $term, $negate)
{
	global $config, $language;
	$term = trim(substr($term, strlen($language["gambits"]["contributor:"])));
	if ($term == $language["gambits"]["myself"]) $term = $search->esoTalk->user["name"];
	$search->condition("posts", "memberId=(SELECT memberId FROM {$config["tablePrefix"]}members WHERE name='$term')", $negate);
}

// More results gambit: bump up the limit to display more results.
function gambitMoreResults(&$search, $term, $negate)
{
	global $config;
	if (!$negate) $search->limit($config["moreResults"]);
}

// Draft gambit: get conversations which are drafts or contain a draft for the logged in user.
function gambitDraft(&$search, $term, $negate)
{
	$id = $this->esoTalk->user ? $this->esoTalk->user["memberId"] : "0";
	$search->condition("status", "memberId=$id AND draft IS NOT NULL", $negate);
}

// Posts gambit: get conversations with a particular number of posts.
function gambitHasNPosts(&$search, $term, $negate)
{
	$search->matches["a"] = (!$search->matches["a"]) ? "=" : htmlspecialchars_decode($this->matches["a"]);
	if ($negate) {
		switch ($search->matches["a"]) {
			case "<": $search->matches["a"] = ">="; break;
			case "<=": $search->matches["a"] = ">"; break;
			case ">": $search->matches["a"] = "<="; break;
			case ">=": $search->matches["a"] = "<"; break;
			case "=": $search->matches["a"] = "!=";
		}
	}
	$search->condition("conversations", "posts {$this->matches["a"]} {$this->matches["b"]}");
}

// Order by posts gambit: order the conversations by the number of posts.
function gambitOrderByPosts(&$search, $term, $negate)
{
	$search->orderBy("c.posts " . ($negate ? "ASC" : "DESC"));
}

// Order by newest gambit: order the conversations by their creation time.
function gambitOrderByNewest(&$search, $term, $negate)
{
	$search->orderBy("c.startTime " . ($negate ? "ASC" : "DESC"));
}

// Sticky gambit: get conversations which are stickied.
function gambitSticky(&$search, $term, $negate)
{
	$search->condition("conversations", "sticky=" . ($negate ? "0" : "1"));
}

// Random gambit: order the conversations randomly.
function gambitRandom(&$search, $term, $negate)
{
	if (!$negate) $search->orderBy("RAND()");
}

// Reverse gambit: reverse the order of the conversations.
function gambitReverse(&$search, $term, $negate)
{
	if (!$negate) $search->reverse = true;
}

// Locked gambit: get conversations which are locked.
function gambitLocked(&$search, $term, $negate)
{
	$search->condition("conversations", "locked=" . ($negate ? "0" : "1"));
}

// Fulltext gambit: get conversations which contain posts containing particular keywords.
function fulltext(&$search, $term, $negate)
{
	$term = str_replace("&quot;", '"', $term);
	$search->condition("posts", "MATCH (title, content) AGAINST ('$term' IN BOOLEAN MODE)", $negate);
	
	// Add the keywords in $term to be highlighted. Make sure we keep ones "in quotes" together.
	$words = array();
	if (preg_match_all('/"(.+?)"/', $term, $matches)) {
		$words += $matches[1];
		$term = preg_replace('/".+?"/', '', $term);
	}
	$words = array_unique(array_merge($words, explode(" ", $term)));
	$search->highlight($words);
}

}

?>