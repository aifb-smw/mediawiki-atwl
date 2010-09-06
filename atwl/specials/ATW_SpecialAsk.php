<?php

class FacetedAskPage extends SMWAskPage {
	public function __construct() {
		parent::__construct();
		smwfLoadExtensionMessages( 'AskTheWiki' );
	}
	
	function execute( $p ) {
		global $wgOut, $wgRequest, $atwgPrintoutsMustExist, $wgUseAjax, $atwgShowFacets;
		
		@parent::execute($p);
		// AJAX action is handled at the end
		
		$po = array();
		foreach ($this->m_printouts as $p) {
			preg_match("/.*\:(.*)\:(.*)\:.*\:.*/", $p->getHash(), $matches);
			$po[str_replace(' ', '_', $matches[2])] = $matches[1];
		}
		
		$wgOut->addInlineScript('var facets = ' . json_encode($po) . ';'.
								"var printoutsMustExist = $atwgPrintoutsMustExist;" .
								"var queryString = '".str_replace("'", "\'", $this->m_querystring)."';"  .
								"var wgUseAjax = $wgUseAjax;" );
								
		if ($wgRequest->getText('atwajax') == 1) {
			$wgOut->disable();
			echo $wgOut->getHTML();
			return;
			//return $this->getAjaxResult($p);
		}
		
		global $atwCatStore;
		$atwCatStore = new ATWCategoryStore();
		
		if ($wgRequest->getCheck('atwQueryString')) {
			SpecialATWL::log('choice '.$wgRequest->getText('choice').', '.$this->m_querystring . implode(
				array_map(function($po) {return " ?".$po->getLabel();}, $this->m_printouts)) .' ('.$wgRequest->getText('atwQueryString').')');
		}
				
		if (!$atwgShowFacets || $wgRequest->getText('eq') == 'yes')
			return;
		
		$this->addScripts();
		
		// extract category names from processed querystring
		// todo: change to use language category
		preg_match_all("/\[\[Category:(.+?)\]\]/i", $this->m_querystring, $matches);
		$cats = $matches[1];
		foreach ($cats as $c) {
			$atwCatStore->fetchAll($c);
		}
		
		$poLabels = array_map(
			function($po) {	return $po->getLabel(); },
			$this->m_printouts
		);
		
		$wgOut->addHTML('<div id="facetbox"><div id="facetsbutton" width="50px" height="100px">Show facets......</div><div id="facettable">'.ATWCategoryStore::getFacetsHTML($cats, $poLabels).'</div></div>');
		

		
	}
	
	/**
	 * includes the jQuery and jQuery UI libraries, and ATW_facets.js, and ATW_Ask.css
	 */
	function addScripts() {
		global $wgOut, $wgVersion, $atwIP, $smwgJQueryIncluded, $smwgScriptPath, $atwgScriptPath;
		
		$wgOut->addStyle( '../extensions/atwl/jscss/ATW_Ask.css' );		
		
		//include jQuery
		if ( !$smwgJQueryIncluded ) {
			if ( method_exists( 'OutputPage', 'includeJQuery' ) ) {
				$wgOut->includeJQuery();
			} else if (version_compare( SMW_VERSION, '1.5.2', '>=')) {
				$wgOut->addScriptFile( $smwgScriptPath .'/libs/jquery-1.4.2.min.js' );
			} else {
				$wgOut->addScript( '<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>' );
			}
			$smwgJQueryIncluded = true;
		}
		
		//include jQuery UI for draggable facets box
		//$wgOut->addScript( '<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.4/jquery-ui.min.js"></script>' );
		
		$wgOut->addScriptFile( '/wiki/extensions/atwl/jscss/ATW_facets.js' );
	}
	
	public function getAjaxResult($p) {
		global $wgOut, $wgRequest;
		//echo $wgRequest->getText('po');
		parent::execute($p);
		$wgOut->disable();
		echo $wgOut->getHTML();
	}
}
