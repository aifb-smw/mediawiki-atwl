<?php

/**
 *  A query interpretation consists of an array of ATWKeyword objects
 */
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
		$this->queryString = trim($string);
		$keywords = preg_split( "/\s+/", $this->queryString );		
		$this->root = new ATWQueryNode( array(""), $keywords );
		
		$this->interpretations = $this->getInterpretations();
	}
	
	/**
	 * prints a debug output of the structure
	 */
	public function testOutput() {
		global $atwCatStore;
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
				else if ($t == ATW_CAT) {
					$m .= "category";
					$m .= "<pre>".print_r($atwCatStore->fetch($kwObj->keyword), true)."</pre>";
				}
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
		global $atwKwStore, $atwComparators;
		
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
		
		if ( $atwKwStore->isCategory($this->kwString) )
			$this->types[] = ATW_CAT;
		
		if ( $atwKwStore->isPage($this->kwString) ) 
			$this->types[] = ATW_PAGE;
			
		if ( $atwKwStore->isProperty($this->kwString) )
			$this->types[] = ATW_PROP;
			
		if ( in_array($this->kwString, $atwComparators) )
			$this->types[] = ATW_COMP;
			
		if ( $atwKwStore->isPropertyValue($this->kwString) )
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
