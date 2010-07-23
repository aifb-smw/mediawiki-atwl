<?php


/**
 * provides access to information about what properties pages of a category have.
 * this is used for ordering query interpretations and for displaying facets.
 * currently queries the database directly; we will want to make our own table and update it using hooks
 * as evidenced by the massive SQL query currently existing
 */
class ATWCategoryStore {
	protected $store, $db;
	
	public function __construct() {
		$this->db =& wfGetDB(DB_SLAVE);
	}
	
	/** 
	 * returns an array of property name => number of occurrences
	 * for pages in category $categoryname
	 */
	public function fetch($categoryname) {	
		if (isset($this->store[$categoryname])) {
			return $this->store[$categoryname];
		}
		
		//todo: make this work on subcategories	
		$smw_ids = $this->db->tableName('smw_ids');
		$categorylinks = $this->db->tableName('categorylinks');
		$smw_atts2 = $this->db->tableName('smw_atts2');
		$smw_rels2 = $this->db->tableName('smw_rels2');
		$page = $this->db->tableName('page');
		
		//select cl.cl_to, s.smw_sortkey from categorylinks cl, page p, smw_ids s2, smw_atts2 a, smw_ids s where cl.cl_from = p.page_id and p.page_title = s2.smw_title and s2.smw_id = a.s_id and a.p_id = s.smw_id order by rand() limit 50;

		
		// attributes
		$sql = "SELECT s.smw_sortkey AS property ".
					"FROM $categorylinks cl, $page p, $smw_ids s2, $smw_atts2 a, $smw_ids s ".
					"WHERE cl.cl_from = p.page_id AND p.page_title = s2.smw_title ".
						"AND s2.smw_id = a.s_id AND a.p_id = s.smw_id ".
						"AND cl.cl_to = '".$this->db->strencode(ucfirst(str_replace("\s","_",$categoryname)))."'";
		
		$res = $this->db->query($sql);
		$ret = array();
		while ($row = $this->db->fetchObject($res)) {
			@$ret[$row->property]['atts'] += 1;
			@$ret[$row->property]['total'] += 1;			
		}
				
		$this->db->freeResult($res);
		
		// relations
		$sql = str_replace($smw_atts2, $smw_rels2, $sql);
		
		$res = $this->db->query($sql);
		while ($row = $this->db->fetchObject($res)) {
			@$ret[$row->property]['rels'] += 1;
			@$ret[$row->property]['total'] += 1;			
		}
				
		$this->db->freeResult($res);
		
		uasort($ret, create_function('$a, $b', 
						'if ($a["total"] == $b["total"]) return 0; else return $a["total"] < $b["total"] ? 1 : -1;'));
		
		$this->store[$categoryname] = $ret;
		return $ret;			
	}
	
}
