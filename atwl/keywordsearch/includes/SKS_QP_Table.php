<?php

/**
 * Extends the default SMW table query printer to de-escape HTML in table headers
 */
class SKSTableResultPrinter extends SMW\TableResultPrinter {

  protected function getResultText(SMWQueryResult $res, $outputmode) {
    $this->mFormat = 'broadtable';

    $result = parent::getResultText($res, $outputmode);

    // de-escape html escape sequences within the table headers
    return preg_replace_callback(
      '/\<th\>.+?\<\/th\>/',
      function($a) {
        return htmlspecialchars_decode($a[0]);
      },
      $result
    );
  }
}

