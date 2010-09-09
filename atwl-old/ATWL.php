<?php

if( !defined( 'MEDIAWIKI' ) ) {
	die("Not an entry point.\n");
}

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'AskTheWiki',
	'author' => array('[http://mediawiki.org/wiki/User:Michael_A._White Michael White]', 'Daniel Herzig'), 
	'url' => '', 
	'descriptionmsg' => 'atwl_atwldescription'
);

$atwIP = dirname(__FILE__) . '/';

require_once( $atwIP . 'ATWL_Settings.php' ); // sets some defaults

$atwgScriptPath = $atwIP . 'jscss/';

$wgExtensionMessagesFiles['AskTheWiki'] = $atwIP . 'ATWL.i18n.php';
$wgExtensionFunctions[] = 'wfATWLSetup';

$wgAutoloadClasses['ATWTableResultPrinter'] = $atwIP. 'includes/ATW_QP_Table.php';
$smwgResultFormats['atwtable'] = 'ATWTableResultPrinter';

$wgAutoloadClasses['SpecialATWL'] = $atwIP. 'specials/ATW_SpecialATWL.php';
$wgAutoloadClasses['FacetedAskPage'] = $atwIP . 'specials/ATW_SpecialAsk.php'; // extends Special:Ask
$wgSpecialPages['AskTheWiki'] = 'SpecialATWL';
$wgSpecialPages['Ask'] = 'FacetedAskPage'; 

$wgDebugLogGroups[] = 'logs/askthewiki.log';

function wfATWLSetup() {
	global $wgAutoloadClasses, $wgHooks, $wgAjaxExportList;
	global $atwIP, $atwgLabelers;	

	$wgAutoloadClasses['ATWQueryTree']     = $atwIP . 'includes/ATW_QueryTree.php';
	$wgAutoloadClasses['ATWQueryNode']     = $atwIP . 'includes/ATW_QueryTree.php';
	$wgAutoloadClasses['ATWKeywordData']   = $atwIP . 'includes/ATW_QueryTree.php';
	$wgAutoloadClasses['ATWKeyword']       = $atwIP . 'includes/ATW_QueryTree.php';
	$wgAutoloadClasses['ATWKeywordStore']  = $atwIP . 'includes/ATW_KeywordStore.php';
	$wgAutoloadClasses['ATWCategoryStore'] = $atwIP . 'includes/ATW_CategoryStore.php';
	
	//$wgAjaxExportList[] = 'ATWCategoryStore::ajaxGetFacets';	
	$wgAjaxExportList[] = 'FacetedAskPage::getAjaxResult';
}
