<?php

$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['SpecialATWL'] = $dir . 'SpecialATWL.php';
$wgSpecialPages['ATWL'] = 'SpecialATWL';
