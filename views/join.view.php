<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Join view: displays an interface enabling users to sign up as members.

if (!defined("IN_ESOTALK")) exit;
?>
<form action='<?php echo makeLink("join"); ?>' method='post' id='join'>
	
<?php
// Loop through the fieldsets in the form...
foreach ($this->form as $id => $fieldset):
	if (is_array($fieldset)):
		echo "<fieldset id='$id'><legend>{$fieldset["legend"]}</legend><ul class='form'>";
		ksort($fieldset);
		
		// Loop through the fields in the fieldsets...
		foreach ($fieldset as $k => $field):
			if ($k === "legend") continue;
			if (is_array($field)):
				echo "<li>{$field["html"]} <div id='{$field["id"]}-message'>";
				if (!empty($field["message"])) echo $this->esoTalk->htmlMessage($field["message"]);
				echo "</div></li>";
 			else: echo $field; endif;
		endforeach;
		
		echo "</ul></fieldset>";
	else: echo $fieldset; endif;
endforeach;
?>

<p><?php echo $this->esoTalk->skin->button(array("id" => "joinSubmit", "value" => $language["Join this forum"], "class" => "big", "tabindex" => 1000)); ?></p>

<script type='text/javascript'>
// <![CDATA[
// Construct a JavaScript array of the fields in the form.
Join.fieldsValidated = {<?php
$fieldsValidated = array();
foreach ($this->fields as $field) {
	if (!empty($field["ajax"]))
		$fieldsValidated[] = "'{$field["id"]}':" . ((@$field["required"] and !@$field["success"]) ? "false" : "true");
}
echo implode(",", $fieldsValidated);
?>};
Join.init();
// ]]>
</script>

</form>