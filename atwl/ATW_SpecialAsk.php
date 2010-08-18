<?php

class FacetedAskPage extends SMWAskPage {
	function execute( $p ) {
		parent::execute($p);
		
		global $wgUseAjax, $wgOut, $wgRequest, $atwgShowFacets;
		
		global $atwCatStore;
		$atwCatStore = new ATWCategoryStore();
		
		if ($wgRequest->getCheck('atwQueryString')) {
			SpecialATWL::log('choice '.$wgRequest->getText('choice').', '.$this->m_querystring . implode(
				array_map(function($po) {return " ?".$po->getLabel();}, $this->m_printouts)) .' ('.$wgRequest->getText('atwQueryString').')');
		}
				
		if (!$atwgShowFacets)
			return;
		
		$this->addScripts();
		
		// extract category names from processed querystring
		preg_match_all("/\[\[Category:(.+?)\]\]/i", $this->m_querystring, $matches);
		$cats = $matches[1];
		foreach ($cats as $c) {
			$atwCatStore->fetchAll($c);
		}
		
		$poLabels = array_map(
			function($po) {	return $po->getLabel(); },
			$this->m_printouts
		);
		
		$wgOut->addHTML('<div class="ui-widget-content" id="facetbox"><div id="facetsbutton" width="50px" height="100px">Show facets......</div><div id="facetoptions">test<br/>test<br/>test<br/></div><div id="facettable">'.ATWCategoryStore::getFacetsHTML($cats, $poLabels).'</div></div>');
		
	}
	
	/**
	 * includes the jQuery and jQuery UI libraries, and ATW_facets.js, and ATW_Ask.css
	 */
	function addScripts() {
		global $wgOut, $wgVersion, $atwIP;
		
		$wgOut->addStyle( '../extensions/atwl/extensions/atwl/ATW_Ask.css' );
		
		//$wgOut->addScript( '<script type="text/javascript" src="../extensions/SemanticMediaWiki/skins/SMW_sorttable.js"></script>');	
		
		//include jQuery
		if (version_compare( $wgVersion, '1.16', '>=' )) {
			$wgOut->includeJQuery(); 
		} else {
			$wgOut->addScript( '<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>' );
		}
		
		//include jQuery UI for draggable facets box
		//$wgOut->addScript( '<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.4/jquery-ui.min.js"></script>' );
		
		//todo: make this not depend on file_get_contents (some hosts disable it)
		$wgOut->addScript( '<script type="text/javascript">'.file_get_contents($atwIP."ATW_facets.js").'</script>' );
	}
}
