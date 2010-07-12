<?php

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
		
		$qp = new ATWQueryParseTree( $queryString );
		$wgOut->addHTML( $qp->testOutput() );

		wfProfileOut('ATWL:execute');
	}
}

class ATWKWInterpretation {
	public function __construct($keyword, $type) {
		$this->keyword = $keyword;
		$this->type = $type;
	}
}

/**
 * takes the keyword string, creates an ATWQueryParseNode tree,
 * and provides the ability to order interpretations by likelihood of
 * being correct.
 */
class ATWQueryParseTree {
	protected $root;
	
	public function __construct($string) {
		$this->queryString = $string;
		$keywords = preg_split( "/\s+/", $string );		
		$this->root = new ATWQueryParseNode( "", $keywords );
		
		$this->interpretations = $this->getInterpretations($this->root);
		$this->prune();
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
		
		foreach ($this->interpretations as $intr) {
			$m .= "<p>Interpretation ".++$i.": <ul>";
			foreach ($intr as $kwObj) {
				$m .= "<li>{$kwObj->keyword}: ";
				$t = $kwObj->type;
				if ($t == ATWKeywordData::PAGE) $m .= "page";
				else if ($t == ATWKeywordData::CATEGORY) $m .= "category";
				else if ($t == ATWKeywordData::PROPERTY) $m .= "property";
				else if ($t == ATWKeywordData::VALUE) $m .= "property value";
				else if ($t == ATWKeywordData::COMPARATOR) $m .= "comparator";
				else if ($t == ATWKeywordData::WILDCARD) $m .= "wildcard";
				else if ($t == ATWKeywordData::NUMBER) $m .= "number";
				$m .= "</li>";
			}
			$m .= "</ul></p>";
		}
		return $m;
	}
	
	/**
	 * recursively gets an array of possible interpretations 
	 * for a node and its descendants
	 */
	protected function getInterpretations(&$node) {
		// get different interpretations of the current node's query string
		// i.e. if the same string can be both a page, category, property, etc.
		foreach ($node->current->types as $t) {
			$own[] = new ATWKWInterpretation($node->current->kwString, $t);
		}
		
		// get an array of interpretations for the set of descendant/following
		// query string components
		$desc = array();
		if (count($node->children) > 0) {
			foreach ($node->children as $c) {
				$desc[] = $this->getInterpretations($c);
			}
		}
		
		// handle base case (leaf with no children)
		if (count($node->children) == 0) {
			foreach ($own as $o) {
				$r[] = array($o); //it expects descendants to be an array of arrays, not an array
			}
			return $r;
		}
		
		// handle root case (kwString = "")
		if (count($node->current->kwString) == 0) {
			return $desc;
		}		

		
		// todo: make this clearer
		foreach ($own as $o) {
			foreach ($desc as $d) {
				foreach ($d as $e) { // I don't know why I had to add this extra level of depth, it should have worked without it //todo: understand
					if ($o->keyword != "") {
						$ret[] = array_merge(array($o), $e);
					} else {
						$ret[] = $e;
					}
				}
			}
		}
		
		return $ret;
	}
	
	/**
	 * removes certain impossible interpretations, including:
	 *   no categories or pages exist
	 *   a comparator is last
	 *   a wildcard after a non-property
	 */
	protected function prune() {
		if (!isset($this->interpretations)) {
			$this->getInterpretations();
		}
		
		
		foreach ($this->interpretations as $i => $intr) {
			
			$hasCatOrPage = false;
			
			foreach ($intr as $j => $kwObj) {
				$curKw = $kwObj->keyword;
				$nextKw = @$intr[$j+1]->keyword;
				$curType = $kwObj->type;
				$nextType = @$intr[$j+1]->keyword;
				
				// is a wildcard after a non-property?
				if ($nextType == ATWKeywordData::WILDCARD && $curType != ATWKeywordData::PROPERTY) {
					unset($this->interpretations[$i]);
					break;						
				}
				
				// test whether a category or page exists
				$hasCatOrPage = ($hasCatOrPage || 
					in_array($curType, array(ATWKeywordData::PAGE, ATWKeywordData::CATEGORY)));				 
			}
			
			
			if (!$hasCatOrPage) 	// no category or page
				unset($this->interpretations[$i]);			
			
			if ($curType == ATWKeywordData::COMPARATOR)		// a comparator is last
				unset($this->interpretations[$i]);			
			
		}
	}
}

/**
 * A 'node' in the query interpretation tree
 * takes an array $current of the current query component being worked on, which
 * is mostly useful for when we are recursively making an array out of the tree.
 * takes an array $remaining of the following components in the query string
 * and creates a child node for all valid splits of that component
 * i.e. for ATWQueryParseNode(array("course"), array("professor year foo bar")),
 * if both the pages (or category, property, or something else) "professor" and "professor year" exist
 * there will be child nodes created for "professor": "year foo bar" and "professor year" : "foo bar"
 */
class ATWQueryParseNode {
	public $current;
	public $children;	
	
	function __construct($current, $remaining) {
		$this->current = new ATWKeywordData( $current );
		$this->children = array(); //base case
		
		if (!$this->current->isValid()) {
			$this->valid = false;
			return;
		}
		
		for ($i=1; $i <= count($remaining); $i++) {			
			$nextCurrent = array_slice($remaining, 0, $i);
			$nextRemaining = array_slice($remaining, $i);
			$child = new ATWQueryParseNode( $nextCurrent, $nextRemaining );
			if ($child->current->isValid()) {
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
	
	const PAGE = 0;
	const CATEGORY = 1;
	const PROPERTY = 2;
	const VALUE = 3;
	const COMPARATOR = 4;
	const WILDCARD = 5;
	const NUMBER = 6; // for possible use with comparators when a property tends to have a numerical value
	
	public function __construct($keywords) {
		global $atwStore, $atwComparators;
		
		$kwString = strtolower( @implode(" ", $keywords) );
		$this->kwString = $kwString;
		
		if ( $atwStore->isPage($kwString) ) 
			$this->types[] = self::PAGE;
			
		if ( $atwStore->isCategory($kwString) )
			$this->types[] = self::CATEGORY;
			
		if ( $atwStore->isProperty($kwString) )
			$this->types[] = self::PROPERTY;
			
		if ( $atwStore->isPropertyValue($kwString) )
			$this->types[] = self::VALUE;
		
		if ( $kwString == "*" )
			$this->types[] = self::WILDCARD;
			
		if ( in_array($kwString, $atwComparators) )
			$this->types[] = self::COMPARATOR;
			
		if ( is_numeric($kwString) )
			$this->types[] = self::NUMBER;
		
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
				 "WHERE LOWER(CONVERT(s.smw_sortkey USING latin1)) = '" . $this->db->strencode($string) ."'" .
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
				 "WHERE LOWER(CONVERT(s.smw_sortkey USING latin1)) = '" . $this->db->strencode($string) ."'" .
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
				 "WHERE LOWER(CONVERT(s.smw_sortkey USING latin1)) = '" . $this->db->strencode($string) ."'" .
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
				 "WHERE LOWER(CONVERT(s.value_xsd USING latin1)) = '" . $this->db->strencode($string) ."'";
				    
		if ($res = $this->db->query($query)) {
			$this->values[$string] = ($row = $this->db->fetchObject($res)) ? true : false;
		}
		
		$this->db->freeResult($res);		
		return $this->values[$string];
	}
}


