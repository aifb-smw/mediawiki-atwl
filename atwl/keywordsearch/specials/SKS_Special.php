<?php

class SKSSpecialPage extends SpecialPage {
	
	public function __construct() {
		parent :: __construct('KeywordSearch');
	}

	public function execute($p) {
		global $wgOut, $wgRequest, $smwgResultFormats, $srfgFormats;
		global $atwKwStore, $atwCatStore, $atwComparators, $smwgIP, $smwgScriptPath, $wgScriptPath;
		global $sksgScriptPath;
		wfProfileIn('ATWL:execute');
		
		wfLoadExtensionMessages('SemanticKeywordSearch');
		
		$atwKwStore = new SKSKeywordStore();		
		$atwCatStore = new CPMCategoryStore();
		
		//todo: move these somewhere else
		$atwComparatorsEn = array('lt' => 'less than',
								  'gt' => 'greater than',
								  'not' => 'not',
								  'like' => 'like' );
								  
		$atwComparators = array_merge( array("<", ">", "<=", ">="), $atwComparatorsEn);		
		
		$wgOut->addStyle( $smwgScriptPath . '/skins/SMW_custom.css' );
		$wgOut->addStyle( $sksgScriptPath . '/css/ATW_main.css' );
		$wgOut->addScriptFile( $smwgScriptPath . '/skins/SMW_sorttable.js' );			
			
		$spectitle = $this->getTitleFor("KeywordSearch");
		
		$queryString = $wgRequest->getText('q');
		
		$wgOut->setHTMLtitle("Semantic keyword search".($queryString?": interpretations for \"$queryString\"":""));

		// query input textbox form
		$m = '<form method="get" action="'. $spectitle->escapeLocalURL() .'">' .
		     '<input size="50" type="text" name="q" value="'.str_replace('"', '\"', $queryString).'" />' .
		     '<input type="submit" value="Submit" /> </form>';
		$wgOut->addHTML($m);
		
		if ($queryString) {
			$this->log("query: $queryString");
			$qp = new SKSQueryTree( $queryString );
			$wgOut->addHTML( wfMsg('sks_chooseinterpretation') );
			$wgOut->addHTML( $this->outputInterpretations($qp->paths) ); 			
		} else {			
			global $sksgExampleQueries;
			
			$wgOut->addHTML( wfMsg('sks_enterkeywords') );
			
			if ($sksgExampleQueries) {
				$wgOut->addHTML( '<p>' . wfMsg('sks_forexample') );
				
				$wgOut->addHTML( '<ul>' . 
					implode(array_map(
						create_function('$q', 'return "<li><a href=\'?q=$q\'>$q</a></li>";'),
						$sksgExampleQueries
					))
				);
			}
		}

		wfProfileOut('ATWL:execute');
	}
	
	/**
	 * takes $interpretation, an ordered array of SKSKeyword objects
	 * and $params and $format, which are passed directly to SMWQueryProcessor::createQuery.
	 * returns a query object based.
	 */
	public function getAskQuery($interpretation, $format = 'skstable', $params = null ) {
		global $sksgPrintoutsMustExist, $sksgPrintoutConstrainedProperties, $atwComparators;
		
		global $wgContLang, $smwgContLang;
		
		$smwNs = $smwgContLang->getNamespaces();
		// $propNs = $smwNs[SMW_NS_PROPERTY];  //not needed
		$conceptNs = $smwNs[SMW_NS_CONCEPT];
		$catNs = $wgContLang->getNsText ( NS_CATEGORY );
		
		// set to true once we encounter a property not followed by a value or comparator
		// but we set it back if needed, to support queries like "tool license gpl status"		
		$printoutMode = false; 
		
		$queryString = "";
		$printouts = array();	
		$selectCount = 0;	
		$cats = array();
		$concepts = array();
		$attributes = array(); // used for mainlabel
		$mainlabel = "";
		
		$currentAttribute = "";
		for ($i = 0; $i<count($interpretation); $i++) {
			$nextType = @$interpretation[$i+1]->type;		
			$prevType = @$interpretation[$i-1]->type;	
			$prevKeyword = @$interpretations[$i-1]->keyword;
			$kw = $interpretation[$i];
			
			if ($kw->type == ATW_PROP && ($nextType == ATW_PROP || !$nextType) ) {
				$printoutMode = true;			
			} else {
				$printoutMode = false;
			}
			
			if ($kw->type == ATW_CAT || $kw->type == ATW_CNCPT || $kw->type == ATW_PAGE) {
				$selectCount++;
			}
			
			if ($kw->type == ATW_CAT) {
				$queryString .= "[[$catNs:{$kw->keyword}]]";
				$cats[] = ucfirst($kw->keyword);
			} else if ($kw->type == ATW_CNCPT) {
				$queryString .= "[[$conceptNs:{$kw->keyword}]]";
				$concepts[] = ucfirst($kw->keyword);
			} else if ($kw->type == ATW_PAGE) {
				$queryString .= ($prevType == ATW_INIT ? "[[" : "") . "{$kw->keyword}]]";
				if ($prevType == ATW_PROP) {
					$currentAttribute .= $kw->keyword;
				}
			} else if ($kw->type == ATW_PROP) {
				if ($sksgPrintoutConstrainedProperties || $printoutMode || $nextType == ATW_COMP) {
					$printouts[] = "?{$kw->keyword}";
				}
				if ($nextType == ATW_VALUE || $nextType == ATW_NUM || $nextType == ATW_WILD || 
					$nextType == ATW_COMP || $nextType == ATW_PAGE) {
					$currentAttribute .= ucfirst($kw->keyword) . ': ';
				}
				if ($sksgPrintoutsMustExist) {
					$queryString .= "[[{$kw->keyword}::" . ($printoutMode?"+]]":"");
				}
			} else if ($kw->type == ATW_COMP) {		
										
				if ( in_array($kw->keyword, array("<", "<=", $atwComparators['lt'])) ) {
					$queryString .= "<";
					$currentAttribute .= '<';
				} else if ( in_array($kw->keyword, array(">", ">=", $atwComparators['gt'])) ) {
					$queryString .= ">";
					$currentAttribute .= '>';
				} else if ( $kw->keyword == $atwComparators['not'] ) {
					$queryString .= "!";
					$currentAttribute .= '!';
				} else if ( $kw->keyword == $atwComparators['like'] ) {
					$queryString .= "~";	
					$currentAttribute .= '~';	
				}
												
			} else if ($kw->type == ATW_VALUE) {
				$queryString .= ($prevType == ATW_COMP && $prevKeyword == $atwComparators['like'])
								? "*{$kw->keyword}*]]" : $kw->keyword."]]";	
				$currentAttribute .= ' '.$kw->keyword;							
			} else if ($kw->type == ATW_WILD) {
				$queryString .= "+]]";
				$currentAttribute .= '*'; // todo: change to 'All'?
			} else if ($kw->type == ATW_NUM) {
				$queryString .= "{$kw->keyword}]]";
				$currentAttribute .= $kw->keyword;
			}
			
			if (($kw->type == ATW_VALUE || $kw->type == ATW_NUM || $kw->type == ATW_WILD || 
				($kw->type == ATW_PAGE && $prevType != ATW_PAGE && $prevType != ATW_CAT && $prevType != ATW_INIT))
				&& ($nextType == ATW_PROP || !$nextType)) {
				$attributes[] = $currentAttribute;
				$currentAttribute = "";
			}
		}
		
		if ($selectCount == 0) {
			$queryString = "[[$catNs:*]]" . $queryString;
		}
		
		$mainlabel = implode('; ', array_merge($concepts, $cats));
		foreach ($attributes as $a) {
			$mainlabel .= '<br/>  <b>•</b> ' . $a;
		}
		
		$rawparams = array_merge(array($queryString), $printouts);
		$rawparams['mainlabel'] = $mainlabel;		
		
		SMWQueryProcessor::processFunctionParams( $rawparams, $querystring, $params, $printouts);
		$params['format'] = $format;
		$params['limit'] = 5;
		
		return array(
			'result' => SMWQueryProcessor::createQuery( $querystring, $params, SMWQueryProcessor::SPECIAL_PAGE , $params['format'], $printouts ),
			'mainlabel' => $mainlabel
		);
	}
	
	public function getAskQueryResult($queryobj, $format = 'skstable', $params = array()) {
		$res = smwfGetStore()->getQueryResult( $queryobj );
		
		$printer = SMWQueryProcessor::getResultPrinter( $format, SMWQueryProcessor::SPECIAL_PAGE );
		$query_result = $printer->getResult( $res, $params, SMW_OUTPUT_HTML );
		if ( is_array( $query_result ) ) {
			$result = $query_result[0];
		} else {
			$result = $query_result;
		}
		
		$errorString = $printer->getErrorString( $res );
		
		return array('errorstring' => $errorString, 'content' => $result, 'link' => $res->getQueryLink() );		
	}
	
	/**
	 * prints a debug output of the structure
	 */
	public function outputInterpretations($paths) {
		global $atwCatStore, $wgScriptPath;
		
		if (count($paths) == 0) {
			return wfMsg('sks_no_valid_interpretations', $this->queryString);
		}
		
		$count = 0;
		$m = "<ul class='choices'>";
		foreach ($paths as $path) {
			$query = $this->getAskQuery($path);
			$mainlabel = $query['mainlabel'];
			
			$query = $query['result'];
			$result = $this->getAskQueryResult($query);
			$errorString = $result['errorstring'];
			$link = $result['link']->getURL()."&eq=no&format=skstable&mainlabel=$mainlabel";
			$result = $result['content'];			
			
			$result = preg_replace_callback("/\<tr\>(.+?)\<\/th\>/si",
				create_function('$m',
					'return "<tr>$m[1]<a href=\"'.$link.'\">'.
					'<img style=\"float:right;\" '.
						'src=\"'.$wgScriptPath.'/extensions/'. 
						(defined('ASKTHEWIKI') ? 'atwl/keywordsearch' : 'SemanticKeywordSearch').
						'/magnifier.png\"></a></th>";'
				),
				$result
			);
			
			if ($errorString || !$result) {
				continue;
			}
			
			$count++;
			$m .= "<li>{$result}</a></li>";
		}
		$m .= "</ul>";
		
		$intro = '<ul><li>'.
			wfMsg('sks_n_interpretations_' .($count==1 ? 'singular' : 'plural'), $count).
			'</li><li>' . wfMsg('sks_choose_the_interpretation') . '</li><li>'.
			wfMsg('sks_addremove_intr').'</li></ul>';
		return $intro . $m;
	}
	
	
	public function log($string) {
		global $sksgEnableLogging;
		
		if ($sksgEnableLogging) {
			wfDebugLog( 'AskTheWiki', $string );
		}
	}
	
}



