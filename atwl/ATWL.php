<?php

if( !defined( 'MEDIAWIKI' ) ) {
	die("Not an entry point.\n");
}

define( 'ASKTHEWIKI', 1 );

$wgExtensionCredits['specialpage'][] = array(
   'name' => 'AskTheWiki - ',
   'author' => array('[http://mediawiki.org/wiki/User:Michael_A._White Michael White]', '[http://www.aifb.kit.edu/web/Daniel_M._Herzig/en Daniel Herzig]'), 
   'url' => 'http://www.aifb.kit.edu/web/Wissensmanagement', 
   'version' => 0.5, 
   'descriptionmsg' => 'atwl_atwldescription'
);

$atwgIP = dirname(__FILE__) . '/';
$atwgScriptPath = $wgScriptPath . '/extensions/atwl';

require_once( $atwgIP . "facetbrowser/SemanticFacetBrowser.php" );
require_once( $atwgIP . "keywordsearch/SemanticKeywordSearch.php" );
require_once( $atwgIP . "propertymap/CategoryPropertyMap.php" );

//require_once( $atwgIP . "srfautomator/ ... " );
