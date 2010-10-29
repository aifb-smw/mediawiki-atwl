<?php

/**
 * provides access to information about what properties pages of a category have.
 * this is used for ordering query interpretations and for displaying facets.
 * currently queries the database directly; we will want to make our own table and update it using hooks
 * as evidenced by the massive SQL query currently existing
 */
class CPMCategoryStore {
	protected $store, $db;
	
	public function __construct() {
		$this->db =& wfGetDB(DB_SLAVE);
	}
	
	/** 
	 * returns an array of property name => number of occurrences
	 * for pages in category $categoryname
	 */
	public function fetchAll($categoryname, $limit = false) {	
		$this->db =& wfGetDB(DB_SLAVE);
		
		if (isset($this->store[$categoryname])) {
			return $this->store[$categoryname];
		}
			
		$smw_ids = $this->db->tableName('smw_ids');
		$categorylinks = $this->db->tableName('categorylinks');
		$smw_atts2 = $this->db->tableName('smw_atts2');
		$smw_rels2 = $this->db->tableName('smw_rels2');
		$page = $this->db->tableName('page');
		
		// attributes
		//todo: make this work on subcategories
		$sql = "SELECT s.smw_sortkey, COUNT(s.smw_sortkey) AS count ".
					"FROM $categorylinks cl, $page p, $smw_ids s2, $smw_atts2 a, $smw_ids s ".
					"WHERE cl.cl_from = p.page_id AND p.page_title = s2.smw_title ".
						"AND s2.smw_id = a.s_id AND a.p_id = s.smw_id ".
						"AND cl.cl_to = '".$this->db->strencode(ucfirst(str_replace("\s","_",$categoryname)))."' ".
					"GROUP BY s.smw_sortkey ORDER BY count DESC";
		
		if ($limit) $sql .= " LIMIT $limit";
		
		$res = $this->db->query($sql);
		$atts = $rels = $all = array();
		while ($row = $this->db->fetchObject($res)) {
			$atts[$row->smw_sortkey] = $row->count;
			$all[$row->smw_sortkey] = $row->count;
		}
				
		$this->db->freeResult($res);
		
		// relations
		$sql = str_replace($smw_atts2, $smw_rels2, $sql);
		
		$res = $this->db->query($sql);
		while ($row = $this->db->fetchObject($res)) {
			$rels[$row->smw_sortkey] = $row->count;	
			@$all[$row->smw_sortkey] += $row->count;
		}
				
		$this->db->freeResult($res);
		
		arsort($all);
		
		$this->store[$categoryname] = array('all' => $all, 'att' => $atts, 'rel' => $rels);
		return $this->store[$categoryname];			
	}
	
	// selectedProps is used to check selected facets, for use with json encoding
	public function fetchAllMultiple(array $categoryNames, $selectedProps = array()) {
		$props = array();
		foreach ($categoryNames as $c) {
			$res = self::fetchAll($c);
			foreach ($res['all'] as $propName => $count) { 
				@$props[$propName] += $count; 
			}
		}
		
		arsort($props);
		
		$ret = array();
		foreach ($props as $name => $count) {
			$ret[] = array(
				'name' => $name, 
				'key'  => str_replace(' ', '_', $name),
				'count' => $count, 
				'checked' => array_intersect(array($name, ucfirst($name)), array_keys($selectedProps)) ? true : false,
				'label' => @$selectedProps[$name]
			);
		}
		
		return $ret;
	}
	
	/**
	 * for use when calling from AJAX / results in general
	 */
	public function getFacets($categoryname, $offset=0, $limit= 10) {
		if (isset($this->store[$categoryname]) && count($this->store[$categoryname]) >= $offset + $limit) {
			return array_slice($this->store[$categoryname]['all'], $offset, $limit);
		} else {
			$facets = self::fetchAll($categoryname, $limit);
			return array_slice($facets['all'], $offset, $limit);			
		}		
	}

	/**
	 * gets all the properties for a page
	 */	
	public function fetchAllPage($pagename) {
		global $wgContLang;
		if (preg_match("/^(.+?)\:(.+)/", $pagename, $matches)) {
			$ns = $wgContLang->getNsIndex( $matches[1] );
			$title = $matches[2];
		} else {
			$ns = NS_MAIN;
			$title = $pagename;
		}
		
		$page = SMWWikiPageValue::makePage($title, $ns);
		$semdata = smwfGetStore()->getSemanticData($pagename);

		$all = array();
		foreach (smwfGetStore()->getProperties($page) as $property) {
			$all[$property->getText()] = 1;
			// actually it could be >1 if a page has more than one instance for a category
			// fix that later
		}
		
		// maybe add differentiation for rel/att properties later
		return array('all' => $all);
	}
	
	public function fetchAllMultiplePages($pagenames, $selectedProps=array() ) {
		$props = array();
		foreach ($pagenames as $p) {
			$res = self::fetchAllPage($p);
			foreach ($res['all'] as $propName => $count) {
				@$props[$propName] += $count;
			}
		}

		$ret = array();
		foreach ($props as $name => $count) {
			$ret[] = array(
				'name' => $name, 
				'key'  => str_replace(' ', '_', $name),
				'count' => $count, 
				'checked' => array_intersect(array($name, ucfirst($name)), array_keys($selectedProps)) ? true : false,
				'label' => @$selectedProps[$name]
			);
		}
		return $ret;
	
	}
	
	/**
	 * returns the percentage of pages in $category that have $property
	 * with a value as $type.  $type can be 'rel' (pages), 'att' (values), or 'all' (both)
	 */
	public function propertyRating($category, $property, $type = 'all') {
		$data = $this->fetchAll($category);
		$property = ucfirst($property);
		
		// based on number of pages with Modification date property, which should be all,
		// and regardless, is representative
		foreach ($data[$type] as $p => $num) {
			$count = $num;
			break;
		}
		
		$s = isset($data[$type][$property]) ? (float)$data[$type][$property] : (float)0;
		$c = (float)$count;
		return $s/$c;
	}
	
	/**
	 * attempts to guess the probability that categories in array $cats have the same types
	 * of items, i.e. they would be likely to appear adjacently in an Ask query
	 */
	public function overlap($cats) {
		$facets = array_map(array(&$this, "getFacets"), $cats);
		$intersection = call_user_func_array('array_intersect', $facets);
		return (float)pow((float)count($intersection)/20.0,2.0);		
	}
}
