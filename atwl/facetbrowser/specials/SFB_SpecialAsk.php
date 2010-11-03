<?php

class FacetedAskPage extends SMWAskPage {
	public function __construct() {
		parent::__construct();
		
	}
	
	function execute( $p ) {
		global $wgOut, $wgRequest, $atwgPrintoutsMustExist, $wgUseAjax, $atwgShowFacets, $wgContLang;
		global $wgArticlePath;
		
		wfLoadExtensionMessages( 'SemanticFacetBrowser' );	
		
		$queryString = urldecode($wgRequest->getText('sksquery'));
		$basePath = str_replace('$1', "Special:KeywordSearch", $wgArticlePath);
		$uglyUrls = strstr($basePath, '?');
		$params = ($uglyUrls ? "&" : "?") . "redirect=no&q=$queryString";

		if ($queryString) {	
			$wgOut->addHTML("This is the first result for your query <i>'".
						$queryString ."'</i>.  <a href='".
						$basePath . $params .
						"'>Choose another interpretation</a>");
		}

		// soo hacky but easier than preventing automatic jQuery url-encoding
//		$wgRequest->setVal('q', preg_replace('/%2B/', '+', $wgRequest->getText('q')));

		@parent::execute($p);
		
		if ($wgRequest->getText('eq') == 'yes')
			return;				

		
				
		if ($wgRequest->getText('SFBAjax') == '1') {
			$wgOut->disable();
			echo $wgOut->getHTML();
			return;
		}
		
		
		
		
		// get the printout properties and labels
		$printouts = array();
		foreach ($this->m_printouts as $p) {
			preg_match("/.*\:(.*)\:(.*)\:.*\:.*/", $p->getHash(), $matches);
			$printouts[$matches[2]] = $matches[1];
		}


		// extract page names in query subject from processed querystring
		$catNs = $wgContLang->getNsText( NS_CATEGORY );
		preg_match("/^\[\[(.+?)\]\]/", $this->m_querystring, $matches);
		$pages = array();
		foreach (explode("|", $matches[1]) as $page) {
			if (strpos(strtolower($page), strtolower("$catNs:")) !== 0) {
				$pages[] = $page;
			}
		}

		if (!$pages) {				
			// extract category names from processed querystring
			preg_match_all("/\[\[$catNs:(.+?)\]\]/i", $this->m_querystring, $matches);
			$cats = $matches[1];
		
			// get an array of all properties, with name, key (spaces replaced with _), whether checked, and how many instances for these categories
			$facets = CPMCategoryStore::fetchAllMultiple($cats, $printouts);
		} else {
			$facets = CPMCategoryStore::fetchAllMultiplePages($pages, $printouts);
		}

		
		$wgOut->addInlineScript('var facets = ' . json_encode($facets) . ';'. "\n" .
					'var printoutsMustExist = '.($atwgPrintoutsMustExist?1:0) . "\n" .
					'var queryString = "'.str_replace('"', '\"', $this->m_querystring).'";'  . "\n" .
					'var wgUseAjax = '.($wgUseAjax ? 1:0). ';' );
		
		/*
		if ($wgRequest->getCheck('atwQueryString')) {
			SpecialATWL::log('choice '.$wgRequest->getText('choice').', '.$this->m_querystring . implode(
				array_map(function($po) {return " ?".$po->getLabel();}, $this->m_printouts)) .' ('.$wgRequest->getText('atwQueryString').')');
		}
		*/
		
		$wgOut->addHTML('<div id="atwQfacetbox"><div style="float: right;">'.
						'<div id="atwQfacetsbutton" width="50px" height="100px">Show facets</div>'.
						'<div id="atwQfacettable">'.$this->getFacetsTableHTML($facets).'</div></div></div>');					
		$this->addScripts();
	}
	
	function getFacetsTableHTML(array &$facets, $height = '300px') {
		$m = '<div style="overflow: scroll; overflow-x: hidden; height: '.$height.';">'.
			 '<table class="smwtable" id="facetstable"><tr><th></th><th>'.
			 wfMsg('atwl_askfacets_property').'</th><th>'.wfMsg('sfb_occurrences').'</th></tr>';

		foreach ($facets as $f) {
			$m .= "<tr><td><input type='checkbox' id='po-{$f['key']}' onChange=".
				  "\"toggleFacet('{$f['key']}')\" ".($f['checked']?"checked":"").">".
				  "</td><td>{$f['name']}</td><td><span class='smwsortkey'>{$f['count']}".
				  "</span>{$f['count']}</td></tr>\n";
		}
		
		return $m . '</table></div>';
	}
	
	/**
	 * includes the jQuery , and ATW_facets.js, and ATW_Ask.css
	 */
	function addScripts() {
		global $wgOut, $wgVersion, $smwgJQueryIncluded, $smwgScriptPath, $sfbgScriptPath;
		
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
		
		//$wgOut->addStyle( $sfbgScriptPath . '/ATW_Ask.css' );
		//add styles like this:
		$wgOut->addLink( array(
			'rel' => 'stylesheet',
			'type' => 'text/css',
			'media' => "screen, projection, print",
			'href' => $sfbgScriptPath . '/ATW_Ask.css'
		));
		
		$wgOut->addScriptFile( $sfbgScriptPath . '/ATW_facets.js' );		
		
	}
	
	public function getAjaxResult($p) {
		global $wgOut;
		parent::execute($p);
		$wgOut->disable();
		echo $wgOut->getHTML();
	}
}
