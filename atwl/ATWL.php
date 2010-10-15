<?php

if( !defined( 'MEDIAWIKI' ) ) {
	die("Not an entry point.\n");
}

define( 'ASKTHEWIKI', 1 );

$wgExtensionCredits['specialpage'][] = array(
   'name' => 'AskTheWiki - AskQ!',
   'author' => array('[http://mediawiki.org/wiki/User:Michael_A._White Michael White]', '[http://www.aifb.kit.edu/web/Daniel_M._Herzig/en Daniel Herzig]'), 
   'url' => 'http://www.aifb.kit.edu/web/Wissensmanagement', 
   'version' => 0.5, 
   'descriptionmsg' => 'atwl_atwldescription'
);

$atwQgIP = dirname(__FILE__) . '/';

//TODO: for some unknown reason the $wgScriptPath seems to be wrong if "pretty url" is used
$atwQgScriptPath = $wgScriptPath . '/portal/extensions/atwl';

require_once( $atwQgIP . "keywordsearch/SemanticKeywordSearch.php" );
require_once( $atwQgIP . "facetbrowser/SemanticFacetBrowser.php" );
require_once( $atwQgIP . "propertymap/CategoryPropertyMap.php" );

//require_once( $atwQgIP . "srfautomator/ ... " );
