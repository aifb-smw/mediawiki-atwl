<?php

if( !defined( 'MEDIAWIKI' ) ) {
	die("Not an entry point.\n");
}

$sfbgIP = dirname(__FILE__) . '/';
require_once( $sfbgIP . 'SFB_Settings.php' ); // sets some defaults

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Semantic Facet Browser',
	'author' => array('[http://mediawiki.org/wiki/User:Michael_A._White Michael White]', 'Daniel Herzig'), 
	'url' => '', 
	'descriptionmsg' => 'atwl_atwldescription'
);
$wgExtensionMessagesFiles['SemanticFacetBrowser'] = $sfbgIP . 'SFB.i18n.php';
$wgDebugLogGroups[] = 'logs/askthewiki.log';

$wgAutoloadClasses['FacetedAskPage'] = $sfbgIP . 'specials/SFB_SpecialAsk.php'; // extends Special:Ask
$wgSpecialPages['Ask'] = 'FacetedAskPage'; 



$wgAjaxExportList[] = 'FacetedAskPage::getAjaxResult';
