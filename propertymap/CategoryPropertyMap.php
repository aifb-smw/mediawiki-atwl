<?php

if( !defined( 'MEDIAWIKI' ) ) {
	die("Not an entry point.\n");
}

$dir = dirname(__FILE__) . '/';

$wgExtensionCredits['other'][] = array(
	'name' => 'Semantic Category-Property Map',
	'author' => array('[http://mediawiki.org/wiki/User:Michael_A._White Michael White]'), 
	'url' => '', 
	'descriptionmsg' => 'atwl_atwldescription'
);

$wgAutoloadClasses['CPMCategoryStore'] = $dir . 'CPM_CategoryStore.php';

// a box displayed like the facet box that displays the number of occurences of each property on a category page

/*
$wgAutoloadClasses['CPMCategoryBox'] = $dir . 'CPM_CategoryBox.php';
$wgHooks['OutputPageParserOutput'][] = 'CPMCategoryBox::onOutputPageParserOutput';
$wgHooks['SkinAfterContent'][] = 'CPMCategoryBox::onSkinAfterContent';
*/
