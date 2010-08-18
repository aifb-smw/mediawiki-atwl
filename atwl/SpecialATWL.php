<?php

define( 'ATW_CAT',   0 ); // category - [[Category:X]]
define( 'ATW_PAGE',  1 ); // page - [[X]]
define( 'ATW_PROP',  2 ); // property - [[X:Value]]
define( 'ATW_VALUE', 3 ); // value - [[Property:X]]
define( 'ATW_COMP',  4 ); // comparator - [[Property:[<>!~]Value]]
define( 'ATW_WILD',  5 ); // wildcard - [[Property:*]]
define( 'ATW_NUM',   6 ); // number (may be useful for use with comparators) - [[Property:<X]]
define( 'ATW_OR',    7 ); // for disjunctions, i.e. [[Property:X]] OR [[Property:Y]]
define( 'ATW_CNCPT', 8 ); // concept - [[Concept:X]]
define( 'ATW_INIT',  9 ); // represents the beginning of the query string

class SpecialATWL extends SpecialPage {
	
	public function __construct() {
		parent :: __construct('AskTheWiki');
	}

	public function execute($p) {
		global $wgOut, $wgRequest, $wgJsMimeType, $smwgResultFormats, $srfgFormats;
		global $atwKwStore, $atwCatStore, $atwComparators;
		wfProfileIn('ATWL:execute');
		
		$atwKwStore = new ATWKeywordStore();		
		$atwCatStore = new ATWCategoryStore();
		
		//todo: move these somewhere else
		$atwComparatorsEn = array('lt' => 'less than',
								  'gt' => 'greater than',
								  'not' => 'not',
								  'like' => 'like' );
								  
		$atwComparators = array_merge( array("<", ">", "<=", ">="), $atwComparatorsEn);		
		
		$wgOut->addStyle( '../extensions/SemanticMediaWiki/skins/SMW_custom.css' );
		$wgOut->addStyle( '../extensions/atwl/extensions/atwl/ATW_main.css' );
		//$wgOut->addScript( '<script type="text/javascript" src="../extensions/atwl/extensions/atwl/ATW_main.js"></script>' );
		$wgOut->addScript( '<script type="text/javascript" src="../extensions/SemanticMediaWiki/skins/SMW_sorttable.js"></script>');	
			
		$spectitle = $this->getTitleFor("AskTheWiki");
		
		$queryString = $wgRequest->getText('q');
		
		$wgOut->setHTMLtitle("Ask The Wiki".($queryString?": interpretations for \"$queryString\"":""));

		// query input textbox form
		$m = '<form method="get" action="'. $spectitle->escapeLocalURL() .'">' .
		     '<input size="50" type="text" name="q" value="'.str_replace('"', '\"', $queryString).'" />' .
		     '<input type="submit" value="Submit" /> </form>';
		$wgOut->addHTML($m);
		
		if ($queryString) {
			$this->log("query: $queryString");
			$qp = new ATWQueryTree( $queryString );
			$wgOut->addHTML( "Step 2: choose interpretation" );
			$wgOut->addHTML( $qp->outputInterpretations() ); 			
		} else {			
			$wgOut->addHTML( "Step 1: enter keywords" );
		}

		wfProfileOut('ATWL:execute');
	}
	
	/**
	 * takes $interpretation, an ordered array of ATWKeyword objects
	 * and $params and $format, which are passed directly to SMWQueryProcessor::createQuery.
	 * returns a query object based.
	 */
	public function getAskQuery($interpretation, $format = 'broadtable', $params = null ) {
		global $atwComparators;
		
		// set to true once we encounter a property not followed by a value or comparator		
		$printoutMode = false; 
		
		$queryString = "";
		$printouts = array();	
		$selectCount = 0;	
		
		for ($i = 0; $i<count($interpretation); $i++) {
			$nextType = @$interpretation[$i+1]->type;		
			$prevType = @$interpretation[$i-1]->type;	
			$prevKeyword = @$interpretations[$i-1]->keyword;
			$kw = $interpretation[$i];
			
			if ($interpretation[$i]->type == ATW_PROP && ($nextType == ATW_PROP || !$nextType) ) {
				$printoutMode = true;			
			}
			
			if ($kw->type == ATW_CAT || $kw->type == ATW_CNCPT || $kw->type == ATW_PAGE) {
				$selectCount++;
			}
			
			if ($kw->type == ATW_CAT) {
				$queryString .= "[[Category:{$kw->keyword}]]";
			} else if ($kw->type == ATW_CNCPT) {
				$queryString .= "[[Concept:{$kw->keyword}]]";
			} else if ($kw->type == ATW_PAGE) {
				$queryString .= ($prevType == ATW_INIT ? "[[" : "") . "{$kw->keyword}]]";
			} else if ($kw->type == ATW_PROP) {
				$printouts[] = "?{$kw->keyword}";
				$queryString .= "[[{$kw->keyword}::" . ($printoutMode?"+]]":"");
			} else if ($kw->type == ATW_COMP) {		
										
				if ( in_array($kw->keyword, array("<", "<=", $atwComparators['lt'])) ) {
					$queryString .= "<";
				} else if ( in_array($kw->keyword, array(">", ">=", $atwComparators['gt'])) ) {
					$queryString .= ">";
				} else if ( $kw->keyword == $atwComparators['not'] ) {
					$queryString .= "!";
				} else if ( $kw->keyword == $atwComparators['like'] ) {
					$queryString .= "~";		
				}
												
			} else if ($kw->type == ATW_VALUE) {
				$queryString .= ($prevType == ATW_COMP && $prevKeyword == $atwComparators['like'])
								? "*{$kw->keyword}*]]" : $kw->keyword."]]";								
			} else if ($kw->type == ATW_WILD) {
				$queryString .= "+]]";
			} else if ($kw->type == ATW_NUM) {
				$queryString .= "{$kw->keyword}]]";
			}
		}
		
		if ($selectCount == 0) {
			$queryString = "[[Category:*]]" . $queryString;
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
		
		return array('content' => $result, 'link' => $res->getQueryLink() );		
	}
	
	function log($string) {
		global $atwEnableLogging, $atwIP;
		
		if (!$atwEnableLogging) return;
		
		//opens the file in append mode
		if ($fh = fopen($atwIP.'atw.log', 'a')) {
			@fwrite($fh, date("Y-m-d H:m:s")." - ".session_id(). $string."\n");
			fclose($fh);	
		}	
	}
}



