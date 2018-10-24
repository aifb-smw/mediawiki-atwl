<?php

class FacetedAskPage extends SMWAskPage {
  public function __construct() {
    parent::__construct();

  }

  function execute($p) {
    global $wgOut, $wgRequest, $atwgPrintoutsMustExist, $wgUseAjax, $atwgShowFacets, $wgContLang, $help_m_printouts, $help_m_querystring;
    global $wgArticlePath;

    //wfLoadExtensionMessages( 'SemanticFacetBrowser' ); // https://www.mediawiki.org/wiki/WfLoadExtensionMessages

    $queryString = urldecode($wgRequest->getText('sksquery'));
    $basePath = str_replace('$1', "Special:KeywordSearch", $wgArticlePath);
    $uglyUrls = strstr($basePath, '?');
    $params = ($uglyUrls ? "&" : "?")."redirect=no&q=$queryString";

    if($queryString) {
      $wgOut->addHTML("This is the first result for your query <i>'".
        $queryString."'</i>.  <a href='".
        $basePath.$params.
        "'>Choose another interpretation</a>");
    }

    // soo hacky but easier than preventing automatic jQuery url-encoding
//		$wgRequest->setVal('q', preg_replace('/%2B/', '+', $wgRequest->getText('q')));

    @parent::execute($p);

    if($wgRequest->getText('eq') == 'yes')
      return;


    if($wgRequest->getText('SFBAjax') == '1') {
      $wgOut->disable();
      echo $wgOut->getHTML();
      return;
    }


    // get the printout properties and labels
    $printouts = array();
    $this->extractQueryParameters_help( $p );
    foreach($help_m_printouts as $p) {
      preg_match("/.*\:(.*)\:(.*)\:.*\:.*/", $p->getHash(), $matches);
      $printouts[$matches[2]] = $matches[1];
    }


    // extract page names in query subject from processed querystring
    $catNs = $wgContLang->getNsText(NS_CATEGORY);
    preg_match("/^\[\[(.+?)\]\]/", $help_m_querystring, $matches);
    $pages = array();
    foreach(explode("|", $matches[1]) as $page) {
      if(strpos(strtolower($page), strtolower("$catNs:")) !== 0) {
        $pages[] = $page;
      }
    }

    if(!$pages) {
      // extract category names from processed querystring
      preg_match_all("/\[\[$catNs:(.+?)\]\]/i", $help_m_querystring, $matches);
      $cats = $matches[1];

      // get an array of all properties, with name, key (spaces replaced with _), whether checked, and how many instances for these categories
      $facets = (new CPMCategoryStore())->fetchAllMultiple($cats, $printouts);
    } else {
      $facets = (new CPMCategoryStore())->fetchAllMultiplePages($pages, $printouts);
    }


    $wgOut->addInlineScript('var facets = '.json_encode($facets).';'."\n".
      'var printoutsMustExist = '.($atwgPrintoutsMustExist ? 1 : 0)."\n".
      'var queryString = "'.str_replace('"', '\"', $help_m_querystring).'";'."\n".
      'var wgUseAjax = '.($wgUseAjax ? 1 : 0).';');

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
  
  function extractQueryParameters_help( $p ) {
		global $smwgQMaxInlineLimit, $help_m_printouts, $help_m_querystring;

		$request = $this->getRequest();
		

		// First make all inputs into a simple parameter list that can again be parsed into components later.
		if ( $request->getCheck( 'q' ) ) { // called by own Special, ignore full param string in that case
			$query_val = $request->getVal( 'p' );

			if ( !empty( $query_val ) ) {
				// p is used for any additional parameters in certain links.
				$rawparams = SMWInfolink::decodeParameters( $query_val, false );
			}
			else {
				$query_values = $request->getArray( 'p' );

				if ( is_array( $query_values ) ) {
					foreach ( $query_values as $key => $val ) {
						if ( empty( $val ) ) {
							unset( $query_values[$key] );
						}
					}
				}

				// p is used for any additional parameters in certain links.
				$rawparams = SMWInfolink::decodeParameters( $query_values, false );

			}
		} else { // called from wiki, get all parameters
			$rawparams = SMWInfolink::decodeParameters( $p, true );
		}

		// Check for q= query string, used whenever this special page calls itself (via submit or plain link):
		$help_m_querystring = $request->getText( 'q' );
		
		if ( $hilf_m_querystring !== '' ) {
			$rawparams[] = $help_m_querystring;
		}
	
		// Check for param strings in po (printouts), appears in some links and in submits:
		$paramstring = $request->getText( 'po' );

		if ( $paramstring !== '' ) { // parameters from HTML input fields
			$ps = explode( "\n", $paramstring ); // params separated by newlines here (compatible with text-input for printouts)

			foreach ( $ps as $param ) { // add initial ? if omitted (all params considered as printouts)
				$param = trim( $param );

				if ( ( $param !== '' ) && ( $param { 0 } != '?' ) ) {
					$param = '?' . $param;
				}

				$rawparams[] = $param;
			}
		}

		list(  $help_m_querystring, $this->m_params,  $help_m_printouts ) = $this->getComponentsFromParameters_help($rawparams);

	}
 
   function getComponentsFromParameters_help( $reqParameters ) {

		$parameters = array();
		unset( $reqParameters['title'] );

		// Split ?Has property=Foo|+index=1 into a [ '?Has property=Foo', '+index=1' ]
		foreach ( $reqParameters as $key => $value ) {
			if (
				( $key !== '' && $key{0} == '?' && strpos( $value, '|' ) !== false ) ||
				( is_string( $value ) && $value !== '' && $value{0} == '?' && strpos( $value, '|' ) !== false ) ) {

				foreach ( explode( '|', $value ) as $k => $val ) {
					$parameters[] = $k == 0 && $key{0} == '?' ? $key . '=' . $val : $val;
				}
			} elseif ( is_string( $key ) ) {
				$parameters[$key] = $value;
			} else {
				$parameters[] = $value;
			}
		}

		// Now parse parameters and rebuilt the param strings for URLs.
		return SMWQueryProcessor::getComponentsFromFunctionParams( $parameters, false );
	}


  function getFacetsTableHTML(array &$facets, $height = '300px') {
    $m = '<div style="overflow: scroll; overflow-x: hidden; height: '.$height.';">'.
      '<table class="smwtable" id="facetstable"><tr><th></th><th>'.
      wfMessage('atwl_askfacets_property')->text().'</th><th>'.wfMessage('sfb_occurrences')->text().'</th></tr>';

    foreach($facets as $f) {
      $m .= "<tr><td><input type='checkbox' id='po-{$f['key']}' onChange=".
        "\"toggleFacet('{$f['key']}')\" ".($f['checked'] ? "checked" : "").">".
        "</td><td>{$f['name']}</td><td><span class='smwsortkey'>{$f['count']}".
        "</span>{$f['count']}</td></tr>\n";
    }

    return $m.'</table></div>';
  }

  /**
   * includes the jQuery , and ATW_facets.js, and ATW_Ask.css
   */
  function addScripts() {
    global $wgOut, $wgVersion, $smwgJQueryIncluded, $smwgScriptPath, $sfbgScriptPath;

    //include jQuery
    if(!$smwgJQueryIncluded) {
      $wgOut->addScript('<script src="//ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>');
      $smwgJQueryIncluded = true;
    }

    //include jQuery UI for draggable facets box
    //$wgOut->addScript( '<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.4/jquery-ui.min.js"></script>' );

    //$wgOut->addStyle( $sfbgScriptPath . '/ATW_Ask.css' );
    //add styles like this:
    $wgOut->addLink(array(
      'rel' => 'stylesheet',
      'type' => 'text/css',
      'media' => "screen, projection, print",
      'href' => $sfbgScriptPath.'/ATW_Ask.css'
    ));

    $wgOut->addScriptFile($sfbgScriptPath.'/ATW_facets.js');

  }

  public function getAjaxResult($p) {
    global $wgOut;
    parent::execute($p);
    $wgOut->disable();
    echo $wgOut->getHTML();
  }
}
