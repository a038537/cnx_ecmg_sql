<?php
require_once 'lib/CryptLib/bootstrap.php'; 

function aes_cmac(&$in,&$key){
	$hasher = new CryptLib\MAC\Implementation\CMAC;
	$cmac = $hasher->generate($in,$key); 
	return $cmac; 
};

function hexdump(&$in){
	return bin2hex($in)."\n";
};

function aes_cbc(&$in){
	//key = get database key20 or key21 depending on month odd/even
	$key = hex2bin('2b7e151628aed2a6abf7158809cf4f3c');
	$iv = hex2bin('00000000000000000000000000000000');
	$cipher="AES-128-CBC";
	$message_padded = $in;
	if (strlen($message_padded) % 16) {
		$message_padded = str_pad($message_padded,
        strlen($message_padded) + 16 - strlen($message_padded) % 16, "\xCC");
	}
	echo bin2hex($message_padded)."\n";
	$mac = aes_cmac($message_padded,$key);
	return substr(openssl_encrypt($message_padded, $cipher, $key, $options=OPENSSL_RAW_DATA, $iv),0,-16).$mac;
};

function get_date(){
	$cnxdate = floor((date('Y') - 1990) / 10);
	$cnxdate = $cnxdate << 5;
	$cnxdate = $cnxdate | date('j');
 	$cnxdate = $cnxdate << 4;
	$cnxdate = $cnxdate | (date('Y') - 1990) % 10;
	$cnxdate = $cnxdate << 4;
	$cnxdate = $cnxdate | date('n');
	return dechex($cnxdate).date('is');
};

function get_ecm(){
	$cw1 = '1112131415161718';
	$cw0 = '0102030405060708';
	$acc = '1000001F';
	$chid = '1010';
	$accn = substr('00000000',0,-(strlen($acc))).$acc;

	$ecm = aes_cbc(hex2bin("2004".get_date()."400F".$cw1.$cw0."2102".$chid."2204".$accn));
	return hexdump($ecm);
	
};

function parse(&$in){
	$text = "OK => ";

	//$array = unpack('s*',$in);
	
		if(($in[1] == chr(0x0200)) and ($in[3] == chr(0x0100)) ){
			echo " channel setup \n";
		}
		if ($in[1] == chr(0x0201)){
			echo " Stream setup \n";
		}

	return $text;
};


?>
