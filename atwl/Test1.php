<?php
class Test1 extends SpecialPage {
	
	// Pages to use in the artificial results
	protected $testpages = array("Artificial Intelligence", "Web Programming", "Algorithms");
	
	// label => property_name key-value pairs of what properties to print for a page
	protected $data = array("year" => "year",
							 "instructor" => "professor",
							 "prereqs" => "prerequisite");
							 
	// SRF to use
	protected $format;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent :: __construct('Test1');
	}
	
	public function getTestSMWQueryResult() {
		// construct the parameters needed to create an artificial SMWQueryResult	
		foreach ($this->testpages as $titleText) {
			$results[] = SMWWikiPageValue::makePageFromTitle( Title::newFromText($titleText) ); //SMWWikiPageValue
		}
		
		foreach ($this->data as $label => $property) {
			$printRequests[] = new SMWPrintRequest( SMWPrintRequest::PRINT_PROP, $label, SMWPropertyValue::makeUserProperty($property) );
		}
		
		//create the artificial SMWQueryResult
		return new SMWQueryResult( $printRequests, new SMWQuery(), $results, new SMWSQLStore2() );
	}

	public function execute($query = '') {
		global $wgOut, $wgRequest, $wgJsMimeType, $smwgResultFormats, $srfgFormats;
		wfProfileIn('Test1:execute');
		
		$this->format = $wgRequest->getText('format');
		if ($this->format == '') $this->format = 'broadtable';
		
		$allFormats = array_unique( array_merge( array_keys($smwgResultFormats), $srfgFormats ) );
		
		// I realize this sort of form submission is kind of ugly, but it's just a test
		$formText = "<form><select onChange=\"location = '?format=' + this.options[this.selectedIndex].value\">";		
		foreach ($allFormats as $f) {
			$formText .= "<option " . (($f == $this->format) ? "selected":"") . " value='$f'>$f</option>\n";
		}	
		$formText .= "</select></form>";
		
		$wgOut->addHTML($formText);	
		
		$queryResult = $this->getTestSMWQueryResult();
		$printer = SMWQueryProcessor::getResultPrinter( $this->format );
		
		$wgOut->addHTML( $printer->getResult( $queryResult, array(), SMW_OUTPUT_HTML ) );

		wfProfileOut('Test1:execute');
	}

}
