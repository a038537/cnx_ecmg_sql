<?php

function hexdump($in,&$out){
	for ($c = 0; $c < strlen($in); $c++) {
    		echo str_pad(dechex(ord($in[$c])), 2, '0', STR_PAD_LEFT);
	}
	echo "\n";
	$out = bin2hex($in)."\n";
};

?>
EOF;
