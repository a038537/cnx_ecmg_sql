<?php
require_once 'lib/CryptLib/bootstrap.php'; 
$GLOBALS['key'];

function readkeys(){
$servername = "10.24.10.78";
$username = "root";
$password = "gftty2478";
$dbname = "neovision";

	$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
  die("Connection failed: " . mysqli_connect_error());
}
//echo "Connected successfully";

	if(date('n')%2){
		$sql = "SELECT ecmkey FROM ecmg_keys where id = 21";
	} else {
		$sql = "SELECT ecmkey FROM ecmg_keys where id = 20";
	};

	$result = $conn->query($sql);
	$row = $result->fetch_assoc();
	$GLOBALS['key'] = hex2bin($row['ecmkey']);

mysqli_close($conn);
	
};

function aes_cmac(&$in,&$key){
	$hasher = new CryptLib\MAC\Implementation\CMAC;
	$cmac = $hasher->generate($in,$key); 
	return $cmac; 
};

function hexdump(&$in){
	for($i=0;$i < strlen($in);$i++){
		$val .= bin2hex($in[$i]).' ';
			if($i % 16 == 15){
				$val .= "\n";
			};
	};
	return $val."\n\n";
	//return bin2hex($in)."\n";
};

function aes_cbc(&$in){
	global $key;
	$key = $GLOBALS['key'];
	$iv = hex2bin('00000000000000000000000000000000');
	$cipher="AES-128-CBC";
	$message_padded = $in;
	if (strlen($message_padded) % 16) {
		$message_padded = str_pad($message_padded,
        strlen($message_padded) + 16 - strlen($message_padded) % 16, "\xCC");
	}
	//echo bin2hex($message_padded)."\n";
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
	return hex2bin(dechex($cnxdate).date('is')); //!!!
};

function get_ecm(&$chid,&$cw0,&$cw1,&$acc,&$cpnum){

	if(hexdec(bin2hex($cpnum)) & 1){
		$header = hex2bin('817044704264');
	} else {
		$header = hex2bin('807044704264');
	};
	
	if(date('n')%2){
		$header .= hex2bin('21');
	} else {
		$header .= hex2bin('20');
	};
	
	$ecm = hex2bin('2004').get_date().hex2bin('400f').$cw1.$cw0.hex2bin('2102').$chid.hex2bin('2204').$acc;
	$ecm = $header.aes_cbc($ecm);
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
        	//printf("channel-id: ");
            $i = $i+3;
            $chid = chr($data[$i]).chr($data[$i+1]);
            //echo bin2hex($chid)." ";
            $i++;
        };
        if($val == 0x1){
        	//printf("SuperCas-ID: ");
            $i = $i+3;
            $casid = chr($data[$i]).chr($data[$i+1]).chr($data[$i+2]).chr($data[$i+3]);
            //echo bin2hex($casid)." ";
            $i+2;
        };
        if($val == 0xf){
        	//printf("Stream-ID: ");
            $i = $i+3;
            $strid = chr($data[$i]).chr($data[$i+1]);
            //echo bin2hex($strid)." ";
            $i++;
        }; 
        if($val == 0x19){
        	//printf("ECM-ID: ");
            $i = $i+3;
            $ecmid = chr($data[$i]).chr($data[$i+1]);
            //echo bin2hex($ecmid)." ";
            $i++;
        };        	
        if($val == 0x10){
        	//printf("CP-duration: ");
            $i = $i+3;
            $dura = chr($data[$i]).chr($data[$i+1]);
            //echo bin2hex($dura)." ";
            $i++;
        };
        if($val == 0x12){
        	//printf("CP-num: ");
            $i = $i+3;
            $cpnum = chr($data[$i]).chr($data[$i+1]);
            //echo bin2hex($cpnum)." ";
            $i++;
        };
		if($val == 0x0d){
        	//printf("Access-criteria: ");
			$acc = '';
            $i++;
   	        $acclen = $data[$i] << 8 | $data[$i+1] & 0xFFFF;
			//printf('%d ',$acclen);
            $i=$i+2;
            for($j=0;$j<$acclen;$j++){
            	$acc = $acc.chr($data[$i]);
                $i++;
            };
            //echo bin2hex($acc)." ";
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
            echo "Channel-ID:    ".bin2hex($chid)."\n";
			echo "Stream-ID:     ".bin2hex($strid)."\n";
			echo "Acs-criteria:  ".bin2hex($acc)."\n";
			echo "CP-No:         ".bin2hex($cpnum)."\n";
			echo "CW0:           ".bin2hex($cw0)."\n";
            echo "CW1:           ".bin2hex($cw1)."\n";
			echo "Used enc-key:  ".bin2hex($GLOBALS['key'])."\n\n";

return hex2bin('02020200D2').hex2bin('000E0002').$chid.hex2bin('000F0002').$strid.hex2bin('00120002').$cpnum.hex2bin('001500BC475FFF1000').get_ecm($chid,$cw0,$cw1,$acc,$cpnum);

};

if($data['typ'] == 0x0104){
	return hex2bin('020105000C000E0002').$chid.hex2bin('000F0002').$strid;
};

if($data['typ'] == 0x0004){
	return "close_ch";
};
	echo "\n";
};
?>
