<?php

/**
 * Provides functions for looking up whether strings correspond to
 * the titles of existing pages, categories, properties, and values,
 * and a cache for the results of these queries to prevent duplicating
 * queries.
 * 
 * We will want to implement a Lucene index, to get rid of the expensive
 * database queries.
 */
class SKSKeywordStore {
	protected $pages, $categories, $properties, $values;
	protected $db;
	
	public function __construct() {
		$this->db =& wfGetDB(DB_SLAVE);
	}	
	
	/**
	 * returns whether $string is a valid page title and stores result in $pages
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
	 * returns whether $string is a valid category title and stores result in $categories
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
	 * returns whether $string is a valid property name and stores result in $properties
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
	 * returns whether $string is a valid concept title and stores result in $concepts
	 */	
	public function isConcept($string) {
		if (isset($this->concepts[$string]))
			return $this->concepts[$string];
		
		$smw_ids = $this->db->tableName('smw_ids');
		$smw_conc2 = $this->db->tableName('smw_conc2');
 		
 		$query = "SELECT s.smw_id FROM $smw_ids s, $smw_conc2 c " .
				 "WHERE s.smw_id = c.s_id ".
					"AND s.smw_sortkey = '" . $this->db->strencode(ucfirst($string)) ."'";
		
		if ($res = $this->db->query($query)) {
			$this->concepts[$string] = ($row = $this->db->fetchObject($res)) ? true : false;
		}
		
		$this->db->freeResult($res);
		return $this->concepts[$string];
	}
	
	/**
	 * returns whether $string is a valid property value and stores result in $values
	 */
	public function isPropertyValue($string) {
		// todo: this needs to account for strings with units in them
		// we also might just want to make it always return true
		
		if (is_numeric($string))
			return true;
		
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
