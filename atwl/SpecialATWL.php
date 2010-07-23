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
		global $atwStore, $atwComparators;
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
		$atwStore = new ATWStore();		
		
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

class ATWKeyword {
	public function __construct($keyword, $type) {
		$this->keyword = $keyword;
		$this->type = $type;
	}
}

/**
 * takes the keyword string, creates an ATWQueryNode tree,
 * and provides the ability to order interpretations by likelihood of
 * being correct.
 */
class ATWQueryTree {
	protected $root;
	
	// a keyword of type key must be followed by one that has a type in value	
	public static $atwExpectTypes = array(
		ATW_INIT	=> array(ATW_CAT, ATW_PAGE),
		ATW_CAT		=> array(ATW_CAT, ATW_PROP),
		ATW_COMP 	=> array(ATW_VALUE, ATW_NUM),
		ATW_PROP 	=> array(ATW_PAGE, ATW_VALUE, ATW_NUM, ATW_WILD, ATW_COMP),
		ATW_OR 		=> array(ATW_PROP),
		ATW_PAGE	=> array(ATW_PROP),
		ATW_WILD	=> array(ATW_PROP, ATW_OR),
		ATW_VALUE	=> array(ATW_PROP, ATW_OR),
		ATW_NUM		=> array(ATW_PROP, ATW_OR),
	);	
	
	public function __construct($string) {
		$this->queryString = $string;
		$keywords = preg_split( "/\s+/", $string );		
		$this->root = new ATWQueryNode( array(""), $keywords );
		
		$this->interpretations = $this->getInterpretations($this->root);
		//$this->prune();
	}
	
	/**
	 * prints a debug output of the structure
	 */
	public function testOutput() {
		// return "<pre>".print_r($this, true)."</pre>";
		
		if (count($this->interpretations) == 0) {
			return "There were no valid interpretations for the query <em>{$this->queryString}</em>";
		}
		
		/*
		$m .= "<pre>".print_r($this->root, true)."</pre>";
		$m .= "<pre>".print_r($this->interpretations, true)."</pre>";
		*/
		$i = 0;
		$m = "";
		foreach ($this->interpretations as $intr) {

			$m .= "<p>Interpretation ".++$i.": ";
			
			//$m .= @SpecialATWL::getAskQueryResultHTML($intr);
			
			
			$m .= "<ul>";
			foreach ($intr as $kwObj) {
				$t = $kwObj->type;
				if ($t == ATW_INIT) continue;
				$m .= "<li>{$kwObj->keyword}: ";

				if ($t == ATW_PAGE) $m .= "page";
				else if ($t == ATW_CAT) $m .= "category";
				else if ($t == ATW_PROP) $m .= "property";
				else if ($t == ATW_VALUE) $m .= "property value";
				else if ($t == ATW_COMP) $m .= "comparator";
				else if ($t == ATW_WILD) $m .= "wildcard";
				else if ($t == ATW_NUM) $m .= "number";
				else if ($t == ATW_OR) $m .= "OR (disjunction)";
				$m .= "</li>";		
			}
			$m .= "</ul>";
			
			
			$m .= "</p>";
		}
		return $m;
	}
	
	/**
	 * helper function for interpretations() that starts it off with root.
	 * returns an array of ATWKeyword arrays representing interpretations
	 */
	protected function getInterpretations() {
		return $this->interpretations($this->root, array(ATW_INIT));
	}
	
	/**
	 * recursively gets an array of possible interpretations 
	 * for a node and its descendants. respects $atwExpectTypes
	 */
	protected function interpretations(&$node, $expectTypes) {
		//echo "<pre>"; print_r($node); echo "</pre>";
		$ret = array();	
		if (empty($node->children)) { 		// base case
			foreach ($node->current->types as $t) {
				$ret[] = array(new ATWKeyword($node->current->kwString, $t));
			}
			return $ret;
		}
		
		foreach ($node->current->types as &$type) {
			foreach ($node->children as &$child) {
				if ($a = array_intersect($child->current->types, ATWQueryTree::$atwExpectTypes[$type])) {
					foreach ($this->interpretations($child, $a) as $intr) {
						if (in_array($intr[0]->type, ATWQueryTree::$atwExpectTypes[$type])) {
							$ret[] = array_merge(array(new ATWKeyword($node->current->kwString, $type)), $intr);
						}
					}
					
				}
			}			
		}
		
		return $ret;
		
	}
}

/**
 * A 'node' in the query interpretation tree
 * takes an array $current of the current query component being worked on, which
 * is mostly useful for when we are recursively making an array out of the tree.
 * takes an array $remaining of the following components in the query string
 * and creates a child node for all valid splits of that component
 * i.e. for ATWQueryNode(array("course"), array("professor year foo bar")),
 * if both the pages (or category, property, or something else) "professor" and "professor year" exist
 * there will be child nodes created for "professor": "year foo bar" and "professor year" : "foo bar"
 */
class ATWQueryNode {
	public $current;
	public $children;	
	
	function __construct($current, $remaining) {			
		$this->current = new ATWKeywordData( $current );
		$this->children = array();
		
		// the possible types of the next keyword given the current keywords' valid types
		$nextExpect = array();				
		foreach (ATWQueryTree::$atwExpectTypes as $type => $validfollowers) {			
			if (in_array($type, $this->current->types)) {
				$nextExpect = array_merge($nextExpect, $validfollowers);
			}
		}			
		
		// populate array of valid children
		for ($i=1; $i <= count($remaining); $i++) {
			$nextCurrent = array_slice($remaining, 0, $i);
			$nextRemaining = array_slice($remaining, $i);
			
			$child = new ATWQueryNode( $nextCurrent, $nextRemaining );
			
			if (array_intersect($child->current->types, $nextExpect)) {
				$this->children[] = $child;
			}

		}
	}
}

/**
 * Accesses and stores data about a string, such as whether it is 
 * a valid page, category, property, or property value
 */
class ATWKeywordData {
	public $types;
	
	public function __construct($keywords) {
		global $atwStore, $atwComparators;
		
		$this->kwString = strtolower(implode(" ", $keywords));		
		$this->types = array();
		
		// the order of these statements influences the order of interpretations, to a degree.
		// for example, because ATW_CAT is first, <Category> <Property> will come before
		// <Page> <Property>
		
		if ( $this->kwString == "" ) {
			$this->types[] = ATW_INIT;
			return;
		}
		
		if ( $this->kwString == "*" ) {
			$this->types[] = ATW_WILD;
			return;
		}
		
		if ( $atwStore->isCategory($this->kwString) )
			$this->types[] = ATW_CAT;
		
		if ( $atwStore->isPage($this->kwString) ) 
			$this->types[] = ATW_PAGE;
			
		if ( $atwStore->isProperty($this->kwString) )
			$this->types[] = ATW_PROP;
			
		if ( in_array($this->kwString, $atwComparators) )
			$this->types[] = ATW_COMP;
			
		if ( $atwStore->isPropertyValue($this->kwString) )
			$this->types[] = ATW_VALUE;
			
		if ( is_numeric($this->kwString) )
			$this->types[] = ATW_NUM;
			
		if ( $this->kwString == "or" )
			$this->types[] = ATW_OR;
		
	}
	
	public function isValid() {
		return (count($this->types) > 0);
	}
}

/**
 * Provides functions for looking up whether strings correspond to
 * the titles of existing pages, categories, properties, and values,
 * and a cache for the results of these queries to prevent duplicating
 * queries.
 * 
 * We will want to implement a Lucene index, to get rid of the expensive
 * database queries.
 */
class ATWStore {
	protected $pages, $categories, $properties, $values;
	protected $db;
	
	public function __construct() {
		$this->db =& wfGetDB(DB_SLAVE);
	}	
	
	/**
	 * returns whether $string is a valid page title
	 * and stores result in $pages
	 */
	public function isPage($string) {
		if (isset($this->pages[$string]))
			return $this->pages[$string];
			
 		$smw_ids = $this->db->tableName('smw_ids');
 		
 		// todo: join on pages so we don't get results for
 		// property values with no pages
 		$query = "SELECT s.smw_id FROM $smw_ids s " .
				 "WHERE s.smw_sortkey = '" . $this->db->strencode(ucfirst($string)) ."'" .
				    "AND s.smw_namespace = 0";
				    
		if ($res = $this->db->query($query)) {
			$this->pages[$string] = ($row = $this->db->fetchObject($res)) ? true : false;
		}
		
		$this->db->freeResult($res);
		
		return $this->pages[$string];
	}
	
	/**
	 * returns whether $string is a valid category title
	 * and stores result in $categories
	 */	
	public function isCategory($string) {
		if (isset($this->categories[$string]))
			return $this->categories[$string];
		
		$smw_ids = $this->db->tableName('smw_ids');
 		
 		//todo: check if CONVERT works with other DBMSes
 		$query = "SELECT s.smw_id FROM $smw_ids s " .
				 "WHERE s.smw_sortkey = '" . $this->db->strencode(ucfirst($string)) ."'" .
				    "AND s.smw_namespace = 14";
				    
		if ($res = $this->db->query($query)) {
			$this->categories[$string] = ($row = $this->db->fetchObject($res)) ? true : false;
		}	
		
		$this->db->freeResult($res);		
		return $this->categories[$string];
		
	}
	
	/**
	 * returns whether $string is a valid property name
	 * and stores result in $properties
	 */
	public function isProperty($string) {
		if (isset($this->properties[$string]))
			return $this->properties[$string];
		
		$smw_ids = $this->db->tableName('smw_ids');
 		
 		$query = "SELECT s.smw_id FROM $smw_ids s " .
				 "WHERE s.smw_sortkey = '" . $this->db->strencode(ucfirst($string)) ."'" .
				    "AND s.smw_namespace = 102";
				    
		if ($res = $this->db->query($query)) {
			$this->properties[$string] = ($row = $this->db->fetchObject($res)) ? true : false;
		}
		
		$this->db->freeResult($res);		
		return $this->properties[$string];
	}
	
	/**
	 * returns whether $string is a valid property value
	 * and stores result in $values
	 */
	public function isPropertyValue($string) {
		// todo: this needs to account for strings with units in them
		
		if (isset($this->values[$string]))
			return $this->values[$string];
		
		$smw_atts2 = $this->db->tableName('smw_atts2');
 		
 		$query = "SELECT s.s_id FROM $smw_atts2 s " .
				 "WHERE s.value_xsd = '" . $this->db->strencode($string) ."'" .
					"OR s.value_xsd = '" . $this->db->strencode(ucfirst($string)) ."'";
				    
		if ($res = $this->db->query($query)) {
			$this->values[$string] = ($row = $this->db->fetchObject($res)) ? true : false;
		}
		
		$this->db->freeResult($res);		
		return $this->values[$string];
	}
}


