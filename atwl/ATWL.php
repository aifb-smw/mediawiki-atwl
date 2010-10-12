<?php

if( !defined( 'MEDIAWIKI' ) ) {
	die("Not an entry point.\n");
}

define( 'ASKTHEWIKI', 1 );

$wgExtensionCredits['specialpage'][] = array(
   'name' => 'Ask The Wiki',
   'author' => array('[http://mediawiki.org/wiki/User:Michael_A._White Michael White]', 'Daniel Herzig'), 
   'url' => '', 
   'descriptionmsg' => 'atwl_atwldescription'
);

$atwgIP = dirname(__FILE__) . '/';
$atwgScriptPath = $wgScriptPath . '/extensions/atwl';

require_once( $atwgIP . "facetbrowser/SemanticFacetBrowser.php" );
require_once( $atwgIP . "keywordsearch/SemanticKeywordSearch.php" );
require_once( $atwgIP . "propertymap/CategoryPropertyMap.php" );

//require_once( $atwgIP . "srfautomator/ ... " );
