<?php
/*
 todo: 
  + add a facet overlay on Special:Ask
  
  x add Concept support
  + add "email professor"-type query support
  x add subject-less query support (i.e. over all pages)
  + improving ranking heuristic
  + improve interpretation selection appearance

 in ATWKeywordStore:
  + use Lucene for category, property names
  + implement category->property table
    - hooks
  
  in SpecialATWL:
  x consider possible entry points for any intelligent format selection / property mapping algorithm
  * 
  * 
  * 

should a query like "homepage" translate to
[[Category:*]] ?Homepage
or
[[Category:*]] [[Homepage::+]] ? Homepage 		[chose this option]
?

*/


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
 * flattens the tree as valid possible interpretations
 * provides the ability to order interpretations by likelihood of
 * being correct.
 */
class ATWQueryTree {
	protected $root;
	
	// a keyword of type key must be followed by one that has a type in value	
	public static $atwExpectTypes = array(
		ATW_INIT	=> array(ATW_CAT, ATW_CNCPT, ATW_PAGE, ATW_PROP),
		ATW_CAT		=> array(ATW_CAT, ATW_CNCPT, ATW_PROP),
		ATW_CNCPT	=> array(ATW_CAT, ATW_CNCPT, ATW_PROP),
		ATW_COMP 	=> array(ATW_VALUE), // array(ATW_VALUE, ATW_NUM)
		ATW_PROP 	=> array(ATW_PAGE, ATW_VALUE, ATW_WILD, ATW_COMP, ATW_PROP), // also removed ATW_NUM here
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
		
		$this->enumeratePaths();
		$this->prune();
		$this->rank();
	}
	
	/**
	 * prints a debug output of the structure
	 */
	public function outputInterpretations() {
		global $atwCatStore;
		// return "<pre>".print_r($this, true)."</pre>";
		
		if (count($this->paths) == 0) {
			return "There were no valid interpretations for the query <em>{$this->queryString}</em>";
		}
		
		$count = 0;
		$m = "<ul class='choices'>";
		foreach ($this->paths as $path) {

			$query = SpecialATWL::getAskQuery($path);
			$mainlabel = $query['mainlabel'];
			
			$query = $query['result'];
			$result = SpecialATWL::getAskQueryResult($query);
			$errorString = $result['errorstring'];
			$link = $result['link'];
			$result = $result['content'];
			
			if ($errorString != '' || $result == '') {
				continue;
			}
			
			$count++;
			$link = $link->getURL()."&showFacets=true&eq=no&choice=$count&atwQueryString={$this->queryString}&format=atwtable&mainlabel=$mainlabel";
			$m .= "<li><a href='$link'>";
			/*<tt>";
			$m .= str_replace("]][[", "]] [[", $query->getQueryString()) . "  ";
			$m .= implode(" ", array_map(function ($q){ return '?'.$q->getHTMLText();}, $query->getExtraPrintouts()));
			$m .= " </tt>
			*/
			$m .= "{$result}</a></li>";
		}
		$m .= "</ul>";
		
		$intro = "<ul><li>Your search returned $count interpretations.</li><li>Choose the interpretation that fits your needs best by clicking on foo.</li><li>Note: You can add and remove properties in the next step.</li></ul>";
		return $intro . $m;
	}
	
	/**
	 * returns the flattened trees as an array of paths (query interpretations)
	 */
	protected function enumeratePaths() {
		$this->paths = $this->paths($this->root, array(ATW_INIT));
	}
	
	/**
	 * recursively gets an array of possible interpretations 
	 * for a node and its descendants. respects $atwExpectTypes
	 */
	protected function paths(&$node, $expectTypes) {
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
					foreach ($this->paths($child, $a) as $intr) {
						if (in_array($intr[0]->type, ATWQueryTree::$atwExpectTypes[$type])) {
							$ret[] = array_merge(array(new ATWKeyword($node->current->kwString, $type)), $intr);
						}
					}				
				}
			}			
		}
		
		return $ret;
		
	}
	
	/**
	 * removes certain impossible interpretations.  Most of this type of functionality
	 * is handled already when the paths are being found
	 */
	protected function prune() {
		/*
		for ($i=0; $i<count($this->paths); $i++) {
			$poMode = false; //printout mode
			for ($j=0; $j<count($this->paths[$i]); $j++) {
				$curType = $this->paths[$i][$j]->type;
				$nextType = @$this->paths[$i][$j+1]->type;
				
				$poMode = $poMode || ($curType == ATW_PROP && (!$nextType || $nextType == ATW_PROP));
				if ($poMode && $curType != ATW_PROP) {
					unset($this->paths[$i]);
					break;
				}
			}
		}
		*/
		
	}
	
	protected function rank() {
		$scored = array();
		foreach ($this->paths as $path) {
			$scored[] = array($path, $this->score($path));
		}
		
		usort($scored, 
			function($a, $b) {
				if ($a[1] == $b[1]) 
					return 0;
				else
					return $a[1] > $b[1] ? -1 : 1;
			}
		);
		
		//create_function('$a,$b', 'if ($a[1] == $b[1]) return 0; else return $a[1] > $b[1] ? -1 : 1;'));
		
		$this->paths = array_map(create_function('$p', 'return $p[0];'), $scored);

	}
	
	/**
	 * returns an estimate of the likelihood that $path is a useful query interpretation
	 */
	protected function score(&$path) {
		global $atwCatStore;
		
		$score = 0.0;
		
		// first, if there are multiple selected categories, get the concordance.
		// the fact that we don't do anything similar in the case that a page, not a category,
		// is the selected item gives a desirable bias to interpretations that have categories
		
		$cats = array();
		foreach ($path as $kwObj) {
			if ($kwObj->type == ATW_CAT) {
				$cats[] = $kwObj->keyword;
			}
		}
				
		if (count($cats) > 1) {
			$score += $atwCatStore->overlap($cats);
		}
		
		// add the average concordance with the first category 
		// of all of the properties in the query interpretation
		if (!empty($cats) && $firstCat = $cats[0]) {		// for simplicity we only test overlap with first category
			$total = $n = (float)0;
			
			for ($i=0; $i<count($path); $i++) {
				if ($path[$i]->type == ATW_PROP) {
					$n++;
					if (@$path[$i+1]->type == ATW_PAGE) {
						$total += $atwCatStore->propertyRating($firstCat, $path[$i]->keyword, 'rel');
					} else if (in_array(@$path[$i+1]->type, array(ATW_VALUE, ATW_COMP))) {
						$total += $atwCatStore->propertyRating($firstCat, $path[$i]->keyword, 'att');
					} else {
						$total += $atwCatStore->propertyRating($firstCat, $path[$i]->keyword, 'all');
					}
				}
			}
				
			$score += @pow($total/$n,2);
			
		} else { 	// a page, not a category, is selected
			$page = $path[0]->keyword;
			
			//todo: add code here
		}
		
		return $score;
		//return $z++;
		
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
			
			if (array_intersect($child->current->types, $nextExpect) && ($child->children || !$nextRemaining)) {
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
		
		//$this->kwString = strtolower(implode(" ", $keywords));	
		$this->kwString = implode(" ", $keywords);	
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
			
		if ( $atwKwStore->isConcept($this->kwString) )
			$this->types[] = ATW_CNCPT;
			
		if ( $atwKwStore->isProperty($this->kwString) )
			$this->types[] = ATW_PROP;
		
		if ( $atwKwStore->isPage($this->kwString) ) 
			$this->types[] = ATW_PAGE;
						
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
