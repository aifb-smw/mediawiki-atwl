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
		parent :: __construct('ATWL');
	}

	public function execute($query = '') {
		global $wgOut, $wgRequest, $wgJsMimeType, $smwgResultFormats, $srfgFormats;
		global $atwKwStore, $atwCatStore, $atwComparators;
		wfProfileIn('ATWL:execute');
		
		$queryString = $wgRequest->getText('q');
		$spectitle = $this->getTitleFor("ATWL");
		
		$wgOut->setHTMLtitle("ATW Light: $queryString");
		
		$m = '<form method="get" action="'. $spectitle->escapeLocalURL() .'">';
		$m .= '<input size="50" type="text" name="q" value="'.str_replace('"', '\"', $queryString).'" />';
		$m .= '<input type="submit" value="Submit" />';
		$m .= '</form>';
		$wgOut->addHTML($m);
		
		$atwComparators = array("less than", "greater than", "<", ">", "<=", ">=", "not", "like");		
		$atwKwStore = new ATWKeywordStore();		
		$atwCatStore = new ATWCategoryStore();
		
		$qp = new ATWQueryTree( $queryString );
		$wgOut->addHTML( $qp->testOutput() );

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
			$prevType = @$interpretation[$i-1];
			$prevKeyword = @$interpretation[$i-1];
			
			$kw = $interpretation[$i];
			
			if ($prevType == ATW_PROP
				&& !in_array($kw->type, array(ATW_VALUE, 
				ATW_COMP, ATW_NUM)))
				$printoutMode = true;			
			
			if ($kw->type == ATW_CAT) {
				$queryString .= "[[Category:{$kw->keyword}]]";
			} else if ($kw->type == ATW_PAGE) {
				$queryString .= "[[{$kw->keyword}]]";
			} else if ($kw->type == ATW_PROP) {
				if ($printoutMode) {
					$printouts[] = "?{$kw->keyword}";
				} else {
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
			} 
		}
		
		$params['format'] = $format;
		
		return SMWQueryProcessor::createQuery( $queryString, $params, SMWQueryProcessor::SPECIAL_PAGE , $format, $printouts );
	}
	
	/**
	 * takes an ordered array of ATWKeyword objects
	 * and returns an Ask query string
	 */
	public function getAskQueryResultHTML($interpretation, $format = 'broadtable') {
		$queryobj = $this->getAskQuery($interpretation, $format);
		
		$res = smwfGetStore()->getQueryResult( $queryobj );
		
		$printer = SMWQueryProcessor::getResultPrinter( $format, SMWQueryProcessor::SPECIAL_PAGE );
		$query_result = $printer->getResult( $res, $params, SMW_OUTPUT_HTML );
		if ( is_array( $query_result ) ) {
			$result .= $query_result[0];
		} else {
			$result .= $query_result;
		}
		
		return $result;		
	}
}



