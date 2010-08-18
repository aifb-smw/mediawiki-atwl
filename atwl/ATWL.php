<?php

if( !defined( 'MEDIAWIKI' ) ) {
	die("Not an entry point.\n");
}

$atwEnableLogging = true;

$atwIP = dirname(__FILE__) . '/';

$wgExtensionFunctions[] = 'wfATWLSetup';
$wgAutoloadClasses['SpecialATWL'] = $atwIP. 'SpecialATWL.php';
$wgAutoloadClasses['FacetedAskPage'] = $atwIP . 'ATW_SpecialAsk.php'; // extends Special:Ask
$wgSpecialPages['AskTheWiki'] = 'SpecialATWL';
$wgSpecialPages['Ask'] = 'FacetedAskPage'; 

if (!isset($atwgShowFacets)) {
	$atwgShowFacets = true;
}

function wfATWLSetup() {
	global $wgAutoloadClasses, $wgHooks, $wgAjaxExportList;
	global $atwIP;	

	$wgAutoloadClasses['ATWQueryTree'] = $atwIP . 'ATWQueryTree.php';
	$wgAutoloadClasses['ATWQueryNode'] = $atwIP . 'ATWQueryTree.php';
	$wgAutoloadClasses['ATWKeywordData'] = $atwIP . 'ATWQueryTree.php';
	$wgAutoloadClasses['ATWKeyword'] = $atwIP . 'ATWQueryTree.php';
	$wgAutoloadClasses['ATWKeywordStore'] = $atwIP . 'ATWKeywordStore.php';
	$wgAutoloadClasses['ATWCategoryStore'] = $atwIP . 'ATWCategoryStore.php';
	
	$wgAjaxExportList[] = 'ATWCategoryStore::ajaxGetFacets';
	
	
}
