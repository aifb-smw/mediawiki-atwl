<?php

$wgExtensionFunctions[] = 'wfATWLSetup';

if( !defined( 'MEDIAWIKI' ) ) {
	die("Not an entry point.\n");
}

$dir = dirname(__FILE__) . '/';
$wgSpecialPages['AskTheWiki'] = 'SpecialATWL';
$wgAutoloadClasses['SpecialATWL'] = $dir . 'SpecialATWL.php';


function wfATWLSetup() {
	global $wgAutoloadClasses, $wgHooks;
	
	$dir = dirname(__FILE__) . '/';
	

	$wgAutoloadClasses['ATWQueryTree'] = $dir . 'ATWQueryTree.php';
	$wgAutoloadClasses['ATWQueryNode'] = $dir . 'ATWQueryTree.php';
	$wgAutoloadClasses['ATWKeywordData'] = $dir . 'ATWQueryTree.php';
	$wgAutoloadClasses['ATWKeyword'] = $dir . 'ATWQueryTree.php';
	$wgAutoloadClasses['ATWKeywordStore'] = $dir . 'ATWKeywordStore.php';
	$wgAutoloadClasses['ATWCategoryStore'] = $dir . 'ATWCategoryStore.php';
	
	
}
