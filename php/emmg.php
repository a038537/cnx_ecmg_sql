<?php
error_reporting(E_ALL);
include 'config.php';
include './functions.php';
global $config;

ecco( "Conax EMM-Generator\n\n\n" );

$service_port = $config['muxport'];
$address = $config['muxaddress'];
$interval = $config['interval'];

/* Einen TCP/IP-Socket erzeugen. */
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    ecco( "socket_create() Error: Reason: " . socket_strerror(socket_last_error()) . "\n" );
	exit;
} else {
    ecco( "OK.\n" );
}

ecco( "Trying to connect '$address' at Port '$service_port' ..." );
$result = socket_connect($socket, $address, $service_port);
if ($result === false) {
    ecco( "socket_connect() Error.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n");
	exit;
} else {
    ecco( "OK.\n");
}


readkeys();
emm_setup($socket);
$timer = time()+900;

while(1==1){
	
	emm_unique($socket);
	emm_shared($socket);
	
	if($timer < time()){
		dbclean();
		readkeys();
		$timer = time()+900;
	};
	
	sleep($interval);
};

ecco( "Close socket...");
socket_close($socket);
ecco( "OK.\n\n");
?>