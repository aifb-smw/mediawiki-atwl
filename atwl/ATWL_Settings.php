<?php

// set defaults, if user has not set them in LocalSettings.php

$atwgExampleQueries = isset($atwgExampleQueries) ? $atwgExampleQueries : array(
	'tool status license GPL homepage', 
	'person email homepage',
	'Semantic Web events 2008 has location country USA',
	'Germany population',
	'population less than 100000'
);

// enable logging of queries and interpretation choices
$atwgEnableLogging = true;

// show facets on Special:Ask when the user clicks from Special:AskTheWiki
$atwgShowFacets = true;

// show facets on Special:Ask even when the user clicked from elsewhere
$atwgAlwaysShowFacets = false;  // not yet implemented (simple)

// if this is true, then [[property::+]] is added to the query for every ?property
$atwgPrintoutsMustExist = true;

// if this is true, then a keyword intrepreted as [[property::value]] adds a ?Property statement
$atwgPrintoutConstrainedProperties = true;



$atwgLabelers = array();

//used by Labeler::getLabels
$atwgLabelers[] = "exampleCallback";

/**
 * labels printouts for vcard if appropriate
 */
function exampleCallback($cats, $props) {
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
