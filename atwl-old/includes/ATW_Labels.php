<?php


class Labeler {
	/**
	 * takes an array of category names and property names
	 * returns an array (
	 * 		'printouts' => array( properties => labels),
	 * 		'format'	=> correct results format
	 * );
	 * 
	 * or false if no improved labeling was found
	 */
	public function getLabels(array $cats, array $props) {
		global $atwgLabelers;
		
		foreach ($atwgLabelers as $callback) {
			if ($result = call_user_func($callback, $cats, $props)) {
				return $result;
			}
		}
		
		return false;	
	}
}
	
