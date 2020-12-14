<?php
require_once 'lib/CryptLib/bootstrap.php'; 


function ecco($in){
	global $config;
	$echo = $config['echo'];
	if($echo){
		echo $in;
	};
};


function readkeys(){
	global $config;
	$servername = $config['servername']; 
	$username = $config['dbusername'];
	$password = $config['dbpass'];
	$dbname = $config['dbname'];

	$conn = mysqli_connect($servername, $username, $password, $dbname);

	// Check connection
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}

	$sql = "SELECT ecmkey FROM ecmg_keys where id = 20";
	$result = $conn->query($sql);
	$row = $result->fetch_assoc();
	$GLOBALS['key20'] = hex2bin($row['ecmkey']);

	$sql = "SELECT ecmkey FROM ecmg_keys where id = 21";
	$result = $conn->query($sql);
	$row = $result->fetch_assoc();
	$GLOBALS['key21'] = hex2bin($row['ecmkey']);
	
	$sql = "SELECT systemkey FROM emmg_systemkey where id=1";
	$result = $conn->query($sql);
	$row = $result->fetch_assoc();
	$GLOBALS['syskey'] = hex2bin($row['systemkey']);

	mysqli_close($conn);
	
};

function aes_cmac(&$in,&$key){
	$hasher = new CryptLib\MAC\Implementation\CMAC;
	$cmac = $hasher->generate($in,$key); 
	return $cmac; 
};

function hexdump(&$in){
	$val = '';
	for($i=0;$i < strlen($in);$i++){
		$val .= bin2hex($in[$i]).' ';
			if($i % 16 == 15){
				$val .= "\n";
			};
	};
	return $val."\n\n";
	//return bin2hex($in)."\n";
};

function predec(&$in){
	$in = dechex($in);
	$out = '';
	for($i=0;$i<8-strlen($in);$i++){
		$out .= "0";
	};
	return $out.$in;
};

function prehex(&$in){
	//$in = dechex($in);
	$out = '';
	for($i=0;$i<8-strlen($in);$i++){
		$out .= "0";
	};
	return $out.$in;
};

function padname(&$in){
	$len = strlen($in);
	while($len < 15){
		$in .= ' ';
		$len++;
	};
	return $in;
};


function aes_cbc(&$in,&$key){
	//global $key;
	//$key = $GLOBALS['key'];
	$iv = hex2bin('00000000000000000000000000000000');
	$cipher="AES-128-CBC";
	$message_padded = $in;
	if (strlen($message_padded) % 16) {
		$message_padded = str_pad($message_padded,
        strlen($message_padded) + 16 - strlen($message_padded) % 16, "\xCC");
	}
	//ecco(hexdump($message_padded));
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
	return hex2bin(dechex($cnxdate).date('is'));
};

function emm_date(&$in){
	$time = strtotime($in);
	$cnxdate = floor((date('Y',$time) - 1990) / 10);
	$cnxdate = $cnxdate << 5;
	$cnxdate = $cnxdate | date('j',$time);
 	$cnxdate = $cnxdate << 4;
	$cnxdate = $cnxdate | (date('Y',$time) - 1990) % 10;
	$cnxdate = $cnxdate << 4;
	$cnxdate = $cnxdate | date('n',$time);
	return hex2bin(dechex($cnxdate));//.date('is'));
};


function get_ecm(&$chid,&$cw0,&$cw1,&$acc,&$cpnum){

	if(hexdec(bin2hex($cpnum)) & 1){
		$header = hex2bin('817044704264');
	} else {
		$header = hex2bin('807044704264');
	};
	
	if(date('n')%2){
		$header .= hex2bin('21');
		$key = $GLOBALS['key21'];
	} else {
		$header .= hex2bin('20');
		$key = $GLOBALS['key20'];
	};
	$GLOBALS['key'] = $key;
	$ecm = hex2bin('2004').get_date().hex2bin('400f').$cw1.$cw0.hex2bin('2102').$chid.hex2bin('2204').$acc;
	$ecm = $header.aes_cbc($ecm,$key);
	return str_pad($ecm,254 - strlen($ecm),"\xFF");

};

function parse(&$in){
$ch_setup = 1;
$ch_test = 2;
$ch_status = 3;
$ch_close = 4;
$ch_error = 5;
$str_setup = 0x0101;
$cw_prov = 0x0201;

$data = unpack("Cver/ntyp/nlen/C*",$in);

    for($i=1;$i<sizeof($data)-2;$i++){
	        $val = $val << 8 & 0xFFFF;
			$val = ($val | $data[$i]) & 0xFFFF;
		if($val == 0x0e){
            $i = $i+3;
            $chid = chr($data[$i]).chr($data[$i+1]);
            $i++;
        };
        if($val == 0x1){
            $i = $i+3;
            $casid = chr($data[$i]).chr($data[$i+1]).chr($data[$i+2]).chr($data[$i+3]);
            $i+2;
        };
        if($val == 0xf){
            $i = $i+3;
            $strid = chr($data[$i]).chr($data[$i+1]);
            $i++;
        }; 
        if($val == 0x19){
            $i = $i+3;
            $ecmid = chr($data[$i]).chr($data[$i+1]);
            $i++;
        };        	
        if($val == 0x10){
            $i = $i+3;
            $dura = chr($data[$i]).chr($data[$i+1]);
            $i++;
        };
        if($val == 0x12){
            $i = $i+3;
            $cpnum = chr($data[$i]).chr($data[$i+1]);
            $i++;
        };
		if($val == 0x0d){
			$acc = '';
            $i++;
   	        $acclen = $data[$i] << 8 | $data[$i+1] & 0xFFFF;
            $i=$i+2;
			for($j=0;$j<4-$acclen;$j++){
				$acc .= chr(0);
			};
            for($j=0;$j<$acclen;$j++){
            	$acc = $acc.chr($data[$i]);
                $i++;
            };
            $i--;
        };
 		if($val == 0x14){
        	$cw ='';
            $i = $i+3;
   	        $odd = $data[$i] << 8 | $data[$i+1] & 0xFFFF;
            $i=$i+2;
            for($j=0;$j<8;$j++){
            	$cw = $cw.chr($data[$i]);
                $i++;
            };
            
            if($odd % 2 == 0){
            	$cw0 = $cw;
            } else {
            	$cw1 = $cw;
            };
            $i--;
        };
    };
	
if($data['typ'] === $ch_setup){
	return hex2bin('0200030051000E0002').$chid.hex2bin('00020001010016000200C80017000200C80003000200C80004000200C800050002FE0C00060002000000070002006400080002000000090002000A000A000101000B000102000C00020064');
};


if($data['typ'] === $str_setup){
	return hex2bin('0201030017000E0002').$chid.hex2bin('000f0002').$strid.hex2bin('00190002').$ecmid.hex2bin('0011000100');
};

if($data['typ'] == $cw_prov){
            ecco( "Channel-ID:    ".bin2hex($chid)."\n" );
			ecco( "Stream-ID:     ".bin2hex($strid)."\n" );
			ecco( "Access-crit.:  ".bin2hex($acc)."\n" );
			ecco( "CP-No:         ".bin2hex($cpnum)."\n" );
			ecco( "CW0:           ".bin2hex($cw0)."\n" );
            ecco( "CW1:           ".bin2hex($cw1)."\n" );
			ecco( "Used enc-key:  ".bin2hex($GLOBALS['key'])."\n\n" );

return hex2bin('02020200D2').hex2bin('000E0002').$chid.hex2bin('000F0002').$strid.hex2bin('00120002').$cpnum.hex2bin('001500BC475FFF1000').get_ecm($chid,$cw0,$cw1,$acc,$cpnum);

};

if($data['typ'] == 0x0104){
	return hex2bin('020105000C000E0002').$chid.hex2bin('000F0002').$strid;
};

if($data['typ'] == 0x0004){
	return "close_ch";
};
	ecco( "\n" );
};

function emm_setup(&$socket){
	ecco("Stream-Setup:\nSend:\n");
	$out = hex2bin('020011001300030002000100010004000000000002000101');
	ecco(hexdump($out));
	socket_write($socket, $out, strlen($out));
	$out = socket_read($socket, 2048);
	ecco("Received:\n");
	ecco(hexdump($out));
	ecco("Send:\n");
	$out = hex2bin('020111001F00030002000100040002000100010004000000000008000200000007000100');
	ecco(hexdump($out));
	socket_write($socket, $out, strlen($out));
	$out = socket_read($socket, 2048);
	ecco("Received:\n");
	ecco(hexdump($out));
	ecco("Send:\n");
	$out = hex2bin('020117001A0003000200010004000200010001000400000000000600020064');
	ecco(hexdump($out));
	socket_write($socket, $out, strlen($out));
	$out = socket_read($socket, 2048);
	ecco("Received:\n");
	ecco(hexdump($out));
};


function emm_unique(&$socket){
	global $config;
	$syskey = $GLOBALS['syskey'];

	$servername = $config['servername']; 
	$username = $config['dbusername'];
	$password = $config['dbpass'];
	$dbname = $config['dbname'];
	$conn = mysqli_connect($servername, $username, $password, $dbname);
	
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
	
	$sql = "
		SELECT abo.ppua, abo.`bos`, abo.`eos`,abo.acc, providers.providername, cards.ppsa, providers.chid FROM neovision.providers 
		join neovision.abo 
		join neovision.cards
        ON providers.chid = abo.chid and cards.ppua = abo.ppua and cards.deleted = 0;
	";
	
	$result = $conn->query($sql);
	while($row = $result->fetch_assoc()){
		$ts = hex2bin(date('is'));
		$ppua = hex2bin(predec($row['ppua']));
		$bos = emm_date($row['bos']);
		$eos = emm_date($row['eos']);
		$acc = hex2bin(prehex($row['acc']));
		$name = padname($row['providername']);
		$ppsa = hex2bin(predec($row['ppsa']));
		$chid = hex2bin($row['chid']);
		
		ecco("Unique EMM:\n");
		ecco("PPUA:               ".prehex($row['ppua'])."\n");
		ecco("Subscription start: ".$row['bos']."\n");
		ecco("Subscription end:   ".$row['eos']."\n");
		ecco("Access Criteria:    ".prehex($row['acc'])."\n");
		ecco("Provider:           ".$row['providername']."\n");
		ecco("Shared Address:     ".prehex($row['ppsa'])."\n");
		ecco("Channel-ID:         ".$row['chid']."\n");
		
		$emm = $ts."\xa0\x00".$ppua."\xa0\x03".$bos.$eos."\xa0\x04".$acc."\xa0\x10".$name."\xa0\x02".$ppsa."\xa0\x01".$chid;
		$key = aes_cmac($ppua,$syskey);
		$emm = aes_cbc($emm,$key);
		$emm = hex2bin('02021100DA0003000200010004000200010001000400000000000800020000000500BC475FFF100082704B000000').$ppua."\x70\x42\x64\x10".$emm;
		$emm = str_pad($emm,325 - strlen($emm),"\xFF");
		
		ecco("------------------------------------------------\n");
		ecco(hexdump($emm));
		socket_write($socket, $emm, strlen($emm));
	};
	mysqli_close($conn);
};

function emm_shared(&$socket){
	global $config;
	$syskey = $GLOBALS['syskey'];
	$servername = $config['servername']; 
	$username = $config['dbusername'];
	$password = $config['dbpass'];
	$dbname = $config['dbname'];
	$conn = mysqli_connect($servername, $username, $password, $dbname);
	
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
	
	$sql = "
		SELECT DISTINCT ppsa FROM neovision.cards JOIN neovision.abo WHERE cards.deleted = 0 AND cards.ppua = abo.ppua;
	";
	
	$result = $conn->query($sql);
	while($row = $result->fetch_assoc()){
		$ts = hex2bin(date('is'));
		$ppsa = hex2bin(predec($row['ppsa']));
	
		$emm = $ts."\xa0\x02".$ppsa."\xa0\x20".$GLOBALS['key20']."\xa0\x21".$GLOBALS['key21'];
	
		$key = aes_cmac($ppsa,$syskey);
		$emm = aes_cbc($emm,$key);
		$emm = hex2bin('02021100DA0003000200010004000200010001000400000000000800020000000500BC475FFF100082704B000000').$ppsa."\x70\x42\x64\x10".$emm;
		$emm = str_pad($emm,325 - strlen($emm),"\xFF");
		
		ecco("Shared EMM:\n");
		ecco("PPSA:   ".prehex($row['ppsa'])."\n");
		ecco("Key20:  ".bin2hex($GLOBALS['key20'])."\n");
		ecco("Key21:  ".bin2hex($GLOBALS['key21'])."\n");
		
		ecco("------------------------------------------------\n");
		ecco(hexdump($emm));
		socket_write($socket, $emm, strlen($emm));
	};
	mysqli_close($conn);
};

function dbclean(){
	global $config;
	$servername = $config['servername']; 
	$username = $config['dbusername'];
	$password = $config['dbpass'];
	$dbname = $config['dbname'];
	$conn = mysqli_connect($servername, $username, $password, $dbname);
	
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
	
	$sql = "
		DELETE FROM neovision.abo WHERE abo.eos < (NOW() - INTERVAL 2 MONTH);
	";
	$result = $conn->query($sql);

	$sql = "
		UPDATE neovision.ecmg_keys SET ecmg_keys.ecmkey = md5(rand()*1001), ecmg_keys.modified = (NOW() + INTERVAL 1 MONTH)
        WHERE ecmg_keys.modified < (NOW() - INTERVAL 1 MONTH);
		";
	$result = $conn->query($sql);	
	
	mysqli_close($conn);
};

?>
