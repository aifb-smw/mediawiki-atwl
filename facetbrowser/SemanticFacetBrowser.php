<?php

if( !defined( 'MEDIAWIKI' ) ) {
	die("Not an entry point.\n");
}

$sfbgIP = dirname(__FILE__) . '/';
$sfbgScriptPath = $atwQgScriptPath . '/facetbrowser/jscss';

require_once( $sfbgIP . 'SFB_Settings.php' ); // sets some defaults


$wgExtensionMessagesFiles['SemanticFacetBrowser'] = $sfbgIP . 'SFB.i18n.php';
$wgDebugLogGroups[] = 'logs/askthewiki.log';
$wgAutoloadClasses['FacetedAskPage'] = $sfbgIP . 'specials/SFB_SpecialAsk.php'; // extends Special:Ask
$wgSpecialPages['Ask'] = 'FacetedAskPage';


$wgAjaxExportList[] = 'FacetedAskPage::getAjaxResult';
