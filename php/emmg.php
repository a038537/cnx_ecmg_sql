<?php
error_reporting(E_ALL);
include 'config.php';
include './functions.php';
global $config;

ecco( "Conax EMM-Generator\n\n\n" );

$service_port = $config['muxport'];
$address = $config['muxaddress'];

/* Einen TCP/IP-Socket erzeugen. */
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    ecco( "socket_create() fehlgeschlagen: Grund: " . socket_strerror(socket_last_error()) . "\n" );
	exit;
} else {
    ecco( "OK.\n" );
}

ecco( "Versuche, zu '$address' auf Port '$service_port' zu verbinden ..." );
$result = socket_connect($socket, $address, $service_port);
if ($result === false) {
    ecco( "socket_connect() fehlgeschlagen.\nGrund: ($result) " . socket_strerror(socket_last_error($socket)) . "\n");
	exit;
} else {
    ecco( "OK.\n");
}

$in = '';
$out = '';

    /* SEND UNIQUE EMM TO ALL CARDS IN SUBSCRIPTION */
    mysql_query(con, "
		SELECT abo.ppua, abo.`bos`, abo.`eos`,abo.acc, providers.providername, cards.ppsa, providers.chid FROM neovision.providers 
		join neovision.abo 
		join neovision.cards \
        ON providers.chid = abo.chid and cards.ppua = abo.ppua and cards.deleted = 0;
		"));

	/* SEND SHARED EMM TO ALL CARDS IN SUBSCRIPTION */
    mysql_query(con, "SELECT DISTINCT ppsa FROM neovision.cards JOIN neovision.abo WHERE cards.deleted = 0 AND cards.ppua = abo.ppua;")

    /* DELETE OLD SUBSCRIPTIONS */
    mysql_query(con, "DELETE FROM neovision.abo WHERE abo.eos < (NOW() - INTERVAL 2 MONTH);")

		
	/* UPDATE ECM-KEYS */
    mysql_query(con, "UPDATE neovision.ecmg_keys SET ecmg_keys.ecmkey = md5(rand()*1001), ecmg_keys.modified = (NOW() + INTERVAL 1 MONTH)
                    WHERE ecmg_keys.modified < (NOW() - INTERVAL 1 MONTH);")

	/* READ SYSTEMKEY */
	mysql_query(con, "SELECT systemkey FROM emmg_systemkey where id=1");
	
	mysql_query(con, "SELECT ecmkey FROM ecmg_keys where id=20");
	mysql_query(con, "SELECT ecmkey FROM ecmg_keys where id=21");

ecco( "send to MUX ...");
socket_write($socket, $in, strlen($in));
ecco( "OK.\n");

ecco( "got from MUX:\n\n");
while ($out = socket_read($socket, 2048)) {
    ecco( $out);
}

ecco( "Close socket...");
socket_close($socket);
ecco( "OK.\n\n");
?>