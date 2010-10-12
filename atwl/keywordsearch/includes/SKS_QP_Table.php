<?php

/**
 * Extends the default SMW table query printer to de-escape HTML in table headers
 */
class SKSTableResultPrinter extends SMWTableResultPrinter {
	
	protected function getResultText( $res, $outputmode ) {
		$tmp = $this->mFormat;
		$this->mFormat = 'broadtable';
		
		$result = parent::getResultText( $res, $outputmode);
		
		// de-escape html escape sequences within the table headers
		return preg_replace_callback(
			'/\<th\>.+?\<\/th\>/', 
			create_function('$a', 'return htmlspecialchars_decode($a[0]);'),
			$result
		);
		
		$this->mFormat = $tmp;
	}
}

