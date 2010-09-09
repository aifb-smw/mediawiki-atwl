<?php

$sksgLabelers = array();

//used by Labeler::getLabels
$sksgLabelers[] = "exampleCallback";

/**
 * labels printouts for vcard if appropriate
 */
function fooExampleCallback($cats, $props) {
	if (!in_array($cats, "Person"))
		return false;
		
	$firstname = "firstname|vorname|имя";
	$lastname = "last\s*name|surname|nachname|фамилия";
	// ... more fields
	
	
	$po = array();
	$count = 0;
	foreach ($props as $p) {
		if (preg_match("/$firstname/i", $p)) {
			$po[$p] = 'firstname';
			$count++;
		} else if (preg_match("/$lastname/i", $p)) {
			$po[$p] = 'lastname';
			$count++;
		} else {
			$po[$p] = $p;
		}
	}
	
	if ($count != 2) {
		return false;
	} else {
		return array('printouts' => $po, 'format' => 'vcard');
	}
}
