<?php
// Copyright 2009 Simon Zerner, Toby Zerner
// This file is part of esoTalk. Please see the included license file for usage information.

// Classes: This file contains all base classes which are extended throughout esoTalk...

class Pluggable {

function __call($method_name, $parameters, &$return)
{
	echo $method_name;
}


}

overload("Pluggable");

?>