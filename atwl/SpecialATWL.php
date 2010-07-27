<?php

define( 'ATW_CAT', 0 ); // category - [[Category:X]]
define( 'ATW_PAGE', 1 ); // page - [[X]]
define( 'ATW_PROP', 2 ); // property - [[X:Value]]
define( 'ATW_VALUE', 3 ); // value - [[Property:X]]
define( 'ATW_COMP', 4 ); // comparator - [[Property:[<>!~]Value]]
define( 'ATW_WILD', 5 ); // wildcard - [[Property:*]]
define( 'ATW_NUM', 6 ); // number (may be useful for use with comparators) - [[Property:<X]]
define( 'ATW_OR', 7 ); // for disjunctions, i.e. [[Property:X]] OR [[Property:Y]]
define( 'ATW_INIT', 8 ); // represents the beginning of the query string

class SpecialATWL extends SpecialPage {
	
	public function __construct() {
		parent :: __construct('AskTheWiki');
	}

	public function execute($p) {
		global $wgOut, $wgRequest, $wgJsMimeType, $smwgResultFormats, $srfgFormats;
		global $atwKwStore, $atwCatStore, $atwComparators;
		
		//todo: move these somewhere else
		$atwComparators = array("less than", "greater than", "<", ">", "<=", ">=", "not", "like");		
		$atwKwStore = new ATWKeywordStore();		
		$atwCatStore = new ATWCategoryStore();
		
		wfProfileIn('ATWL:execute');
		
		$wgOut->addStyle( '../extensions/SemanticMediaWiki/skins/SMW_custom.css' );
		$wgOut->addStyle( '../extensions/atwl/extensions/atwl/ATW_main.css' );
		$wgOut->addScript( '<script type="text/javascript" src="../extensions/SemanticMediaWiki/skins/SMW_sorttable.js"></script>');	
			
		$spectitle = $this->getTitleFor("AskTheWiki");
		
		$queryString = $wgRequest->getText('q');
		$passed = $wgRequest->getText('x');
		$format = $wgRequest->getText('format');
		
		$wgOut->setHTMLtitle("Ask The Wiki".($queryString?": interpretations for \"$queryString\"":""));
		
		if ($passed == '') {
			// query input textbox form
			$m = '<form method="get" action="'. $spectitle->escapeLocalURL() .'">';
			$m .= '<input size="50" type="text" name="q" value="'.str_replace('"', '\"', $queryString).'" />';
			$m .= '<input type="submit" value="Submit" />';
			$m .= '</form>';
			$wgOut->addHTML($m);
			
			if ($queryString == '') {
				$wgOut->addHTML( "Step 1: enter keywords" );
			} else {
				$qp = new ATWQueryTree( $queryString );
				$wgOut->addHTML( $qp->testOutput() ); 
			}
		} else {
			$rawparams = SMWInfolink::decodeParameters( $passed, true );
			
			SMWQueryProcessor::processFunctionParams( $rawparams, $querystring, $params, $printouts);
			$params['format'] = $format ? $format : 'broadtable';
			$params['limit'] = 20;
			
			$queryobj = SMWQueryProcessor::createQuery( $querystring, $params, SMWQueryProcessor::SPECIAL_PAGE , $params['format'], $printouts );
			
			$res = smwfGetStore()->getQueryResult( $queryobj );			
			$printer = SMWQueryProcessor::getResultPrinter( $params['format'], SMWQueryProcessor::SPECIAL_PAGE );
			$query_result = $printer->getResult( $res, $params, SMW_OUTPUT_HTML );
			$wgOut->addHTML($query_result);
			
		}

		wfProfileOut('ATWL:execute');
	}
	
	/**
	 * takes $interpretation, an ordered array of ATWKeyword objects
	 * and $params and $format, which are passed directly to SMWQueryProcessor::createQuery.
	 * returns a query object based.
	 */
	public function getAskQuery($interpretation, $format = 'broadtable', $params = null ) {
		
		// set to true once we encounter a property not followed by a value or comparator		
		$printoutMode = false; 
		
		$queryString = "";
		$printouts = array();		
		
		for ($i = 0; $i<count($interpretation); $i++) {
			$nextType = @$interpretation[$i+1]->type;		
			$prevType = @$interpretation[$i-1]->type;	
			$prevKeyword = @$interpretations[$i-1]->keyword;
			$kw = $interpretation[$i];
			
			if ($interpretation[$i]->type == ATW_PROP && ($nextType == ATW_PROP || !$nextType) ) {
				$printoutMode = true;			
			}
			
			if ($kw->type == ATW_CAT) {
				$queryString .= "[[Category:{$kw->keyword}]]";
			} else if ($kw->type == ATW_PAGE) {
				$queryString .= ($prevType == ATW_INIT ? "[[" : "") . "{$kw->keyword}]]";
			} else if ($kw->type == ATW_PROP) {
				$printouts[] = "?{$kw->keyword}";
				if (!$printoutMode) {
					$queryString .= "[[{$kw->keyword}::";
				}
			} else if ($kw->type == ATW_COMP) {
				
				if ( in_array($kw->keyword, array("less than", "<", "<=")) )
					$queryString .= "<";
				else if ( in_array($kw->keyword, array("greater than", ">", ">=")) )
					$queryString .= ">";
				else if ( $kw->keyword == "not" )
					$queryString .= "!";
				else if ( $kw->keyword == "like" )
					$queryString .= "~";		
								
			} else if ($kw->type == ATW_VALUE) {
				$queryString .= ($prevType == ATW_COMP && $prevKeyword == "like")
								? "*{$kw->keyword}*]]" : $kw->keyword."]]";								
			} else if ($kw->type == ATW_WILD) {
				$queryString .= "+]]";
			} else if ($kw->type == ATW_NUM) {
				$queryString .= "{$kw->keyword}]]";
			}
		}
		
		$rawparams = array_merge(array($queryString), $printouts);
		
		SMWQueryProcessor::processFunctionParams( $rawparams, $querystring, $params, $printouts);
		$params['format'] = $format;
		$params['limit'] = 5;
		
		return SMWQueryProcessor::createQuery( $querystring, $params, SMWQueryProcessor::SPECIAL_PAGE , $params['format'], $printouts );
	}
	
	/**
	 * takes an ordered array of ATWKeyword objects
	 * and returns an Ask query string
	 */
	public function getAskQueryResult($queryobj, $format = 'broadtable', $params = array()) {
		
		$res = smwfGetStore()->getQueryResult( $queryobj );
		
		$printer = SMWQueryProcessor::getResultPrinter( $format, SMWQueryProcessor::SPECIAL_PAGE );
		$query_result = $printer->getResult( $res, $params, SMW_OUTPUT_HTML );
		if ( is_array( $query_result ) ) {
			$result = $query_result[0];
		} else {
			$result = $query_result;
		}
		
		//$result .= $res->hasFurtherResults() ? "has further results" : "";
		
		return array('content' => $result, 'link' => $res->getQueryLink());		
	}
}



