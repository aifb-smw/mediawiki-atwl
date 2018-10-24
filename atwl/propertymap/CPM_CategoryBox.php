<?php

class CPMCategoryBox {
	
	public function getCategoryBoxText($title) {
		if ($title->getNamespace() != NS_CATEGORY) {
			$ret = '';
		} else {
			$ret = print_r(CPMCategoryStore::getFacets($title->getText()), true);	
		}
		
		echo $ret;
		return $ret;		
	}
	
	public function onSkinAfterContent( &$data, $skin ) {
		global $wgOut, $wgTitle;
		print_r($wgOut);
		echo $wgTitle->getText();
		echo "bbb";
		
		echo "aaaaaa";
		
		$data .= "foooooo";
		
		if ( isset( $wgOut->mCPMCategoryBoxText ) ) {
			$data .= $wgOut->mCPMCategoryBoxText;
		}
		
		return true;		
	}
	
	public function onOutputPageParserOutput( $outputpage, $parseroutput ) {
		
		$outputpage->mCPMCategoryBoxText = CPMCategoryBox::getCategoryBoxText($outputpage->getTitle());
		
		echo "z";
		return true;		
		
	}
}
