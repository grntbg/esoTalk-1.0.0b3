<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Classes: This file contains all base classes which are extended throughout esoTalk...

class Pluggable {
	
var $className;

function __call($methodName, $parameters)
{
	global $esoTalk;
	
	$callableMethod = "x$methodName";
	
	if (!method_exists($this->className, $callableMethod)) trigger_error("The '$this->className' object does not have a '$callableMethod' method.", "E_USER_ERROR");
	
	foreach ($esoTalk->plugins as $plugin) {
		if (method_exists($plugin, $this->className . "_" . $methodName . "_before"))
			call_user_func_array(array($plugin, $this->className . "_" . $methodName . "_before"), $parameters);
	}
	
	foreach ($esoTalk->plugins as $plugin) {
		if (method_exists($plugin, $this->className . "_" . $methodName . "_override")) {
			$return = call_user_func_array(array($plugin, $this->className . "_" . $methodName . "_override"), $parameters);
			$overridden = true;
			break;
		}
	}
	
	if (empty($overridden)) $return = call_user_func_array(array($this, $callableMethod), $parameters);
	
	
	foreach ($esoTalk->plugins as $plugin) {
		if (method_exists($plugin, $this->className . "_" . $methodName . "_after"))
			call_user_func_array(array($plugin, $this->className . "_" . $methodName . "_after"), $parameters);
	}	
	
	return $return;
}


}

?>