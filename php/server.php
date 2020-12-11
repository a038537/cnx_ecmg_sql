<?php
include './config.php';
include './functions.php';
global $config;
error_reporting(~E_NOTICE);
set_time_limit (0);

$address = $config['bindaddress'];
$port = $config['bindport'];
$max_clients = $config['max_clients'];


if(!($sock = socket_create(AF_INET, SOCK_STREAM, 0)))
{
	$errorcode = socket_last_error();
    $errormsg = socket_strerror($errorcode);
    
    die("Couldn't create socket: [$errorcode] $errormsg \n");
}

ecco( "Socket created \n" );

//Set Socket options for re-use blocked ports & address
if ( ! socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1)) 
{
    ecco(  socket_strerror(socket_last_error($sock)) ); 
    exit;
}

// Bind the source address
if( !socket_bind($sock, $address , $port) )
{
	$errorcode = socket_last_error();
    $errormsg = socket_strerror($errorcode);
    
    die("Could not bind socket : [$errorcode] $errormsg \n");
}

ecco( "Socket bind OK \n" );

if(!socket_listen ($sock , 10))
{
	$errorcode = socket_last_error();
    $errormsg = socket_strerror($errorcode);
    
    die("Could not listen on socket : [$errorcode] $errormsg \n");
}

ecco(  "Socket listen OK \n" );

ecco(  "Waiting for incoming connections... \n" );

//array of client sockets
$client_socks = array();

//array of sockets to read
$read = array();

//start loop to listen for incoming connections and process existing connections
while (true) 
{
	//prepare array of readable client sockets
	$read = array();
	
	//first socket is the master socket
	$read[0] = $sock;
	
	//now add the existing client sockets
    for ($i = 0; $i < $max_clients; $i++)
    {
        if($client_socks[$i] != null)
		{
			$read[$i+1] = $client_socks[$i];
		}
    }
    
	//now call select - blocking call
    if(socket_select($read , $write , $except , null) === false)
	{
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);
    
		die("Could not listen on socket : [$errorcode] $errormsg \n");
	}
    
    //if ready contains the master socket, then a new connection has come in
    if (in_array($sock, $read)) 
	{
        for ($i = 0; $i < $max_clients; $i++)
        {
            if ($client_socks[$i] == null) 
			{
                $client_socks[$i] = socket_accept($sock);
                
                //display information about the client who is connected
				if(socket_getpeername($client_socks[$i], $address, $port))
				{
					ecco(  "Client $address : $port is now connected to us. \n" );
				}
				
				//Send Welcome message to client
				//$message = "Welcome to php socket server version 1.0 \n";
				//$message .= "Enter a message and press enter, and i shall reply back \n";
				//socket_write($client_socks[$i] , $message);
				readkeys();
				break;
            }
        }
    }

    //check each client if they send any data
    for ($i = 0; $i < $max_clients; $i++)
    {
		if (in_array($client_socks[$i] , $read))
		{
			$input = socket_read($client_socks[$i] , 1024);
            
            if ($input == "quit\r\n" or $input == NULL) 
			{
				//return;
				//zero length string meaning disconnected, remove and close the socket
				unset($client_socks[$i]);
				@socket_close($client_socks[$i]);
				ecco ("socket closed\n");
            }

			if(date('i')%5 == 0 and $gotkey == 0){
				readkeys();
				//echo "READKEYS!\n\n\n";
				$gotkey = 1;
			} else if(date('i')%5 != 0){
				$gotkey = 0;
			};
			
			if ($input == "llik\r\n"){
				unset($client_socks[$i]);
				@socket_close($client_socks[$i]);
				exit;
			}
			ecco(  "------------------------------------------------\nincoming data...\n" );
			ecco(  hexdump($input) );
			
			$output = parse($input);
			
			if ($output == "close_ch") 
			{
				unset($client_socks[$i]);
				@socket_close($client_socks[$i]);
				ecco ("socket closed\n");
            } else {
				//send response to client
				socket_write($client_socks[$i] , $output);
				ecco(  "Sending output to client \n" );
				ecco(  hexdump($output) );
			}
		}
    }
}

?>
