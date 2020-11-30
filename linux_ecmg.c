/*
	Irdeto ECMG for Linux
*/

#include <stdio.h>
#include <string.h>   //strlen
#include <stdlib.h>
#include <errno.h>
#include <unistd.h>   //close
#include <arpa/inet.h>    //close
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <sys/time.h> //FD_SET, FD_ISSET, FD_ZERO macros
#include <stdint.h>
#include <time.h>

#include "parser.h"

int tx;
char txbuffer[188];

uint8_t debug = 0;
int timer =0;

#define TRUE   1
#define FALSE  0

int main(int argc , char *argv[])
{

    int opt = TRUE;
    unsigned int port = 0;
    int master_socket , addrlen , new_socket , client_socket[30] , max_clients = 30 , activity, i , valread , sd;
	int max_sd;
    struct sockaddr_in address;

    const char *database = "127.0.0.1";
	const char *dbname = "neovision";
	const char *user = "admin";
	const char *pass = "password";

	 if(argc < 2){
        fprintf(stderr,"Error! No args defined! Exiting...\n");
        fprintf(stderr,"Usage: conax-emmg --port [val] --ip [val] [--debug]\n");
        return -1;
     }

     for(int i = 1;i<argc;i++){
        if(!strcmp(argv[i],"--muxport")){
            port=strtoul(argv[i+1],NULL,0);
        }
        if(!strcmp(argv[i],"--gubed")){
            debug = 1;
        }
        if(!strcmp(argv[i],"--dbip")){
            database=argv[i+1];
        }
        if(!strcmp(argv[i],"--dbuser")){
            user=argv[i+1];
        }
        if(!strcmp(argv[i],"--dbpass")){
            pass=argv[i+1];
        }
        if(!strcmp(argv[i],"--dbname")){
            dbname=argv[i+1];
        }
        if(!strcmp(argv[i],"--help")){
            printf("usage: conax-ecmg --muxport 1234 --dbip 192.168.1.1  --dbuser admin --dbpass password\n\n");
            printf("ARGS:\n\t--muxport\tPORT for MUX\n");
            printf("\t--dbip\t\tIP-ADDRESS of SQL-Database\n");
            printf("\t--dbuser\tDATABASE Username [default: admin]\n");
            printf("\t--dbpass\tDATABASE Password [default: password]\n");
            printf("\t--dbname\tNAME of CAS-TABLE in Database [default: neovision]\n");
        }
     }



    if(port == 0){
        fprintf(stderr,"Error! No Port defined! Exiting...\n");
        return -1;
    }

    char buffer[1025];  //data buffer of 1K

    setup(debug,database,user,pass,dbname);

    //set of socket descriptors
    fd_set readfds;

    //initialise all client_socket[] to 0 so not checked
    for (i = 0; i < max_clients; i++)
    {
        client_socket[i] = 0;
    }

    //create a master socket
    if( (master_socket = socket(AF_INET , SOCK_STREAM , 0)) == 0)
    {
        perror("socket failed");
        exit(EXIT_FAILURE);
    }

    //set master socket to allow multiple connections , this is just a good habit, it will work without this
    if( setsockopt(master_socket, SOL_SOCKET, SO_REUSEADDR, (char *)&opt, sizeof(opt)) < 0 )
    {
        perror("setsockopt");
        exit(EXIT_FAILURE);
    }

    //type of socket created
    address.sin_family = AF_INET;
    address.sin_addr.s_addr = INADDR_ANY;
    address.sin_port = htons( port );

    //bind the socket to localhost port 8888
    if (bind(master_socket, (struct sockaddr *)&address, sizeof(address))<0)
    {
        perror("bind failed");
        exit(EXIT_FAILURE);
    }
	printf("Conax ECMG on port %d \n", port);

    //try to specify maximum of 3 pending connections for the master socket
    if (listen(master_socket, 3) < 0)
    {
        perror("listen");
        exit(EXIT_FAILURE);
    }

    //accept the incoming connection
    addrlen = sizeof(address);
    puts("Let's get ready to scramble ...");

	while(TRUE)
    {

        //clear the socket set
        FD_ZERO(&readfds);

        //add master socket to set
        FD_SET(master_socket, &readfds);
        max_sd = master_socket;

        //add child sockets to set
        for ( i = 0 ; i < max_clients ; i++)
        {
            //socket descriptor
			sd = client_socket[i];

			//if valid socket descriptor then add to read list
			if(sd > 0)
				FD_SET( sd , &readfds);

            //highest file descriptor number, need it for the select function
            if(sd > max_sd)
				max_sd = sd;
        }

        //wait for an activity on one of the sockets , timeout is NULL , so wait indefinitely
        activity = select( max_sd + 1 , &readfds , NULL , NULL , NULL);

        if ((activity < 0) && (errno!=EINTR))
        {
            printf("select error");
        }

        //If something happened on the master socket , then its an incoming connection
        if (FD_ISSET(master_socket, &readfds))
        {
            if ((new_socket = accept(master_socket, (struct sockaddr *)&address, (socklen_t*)&addrlen))<0)
            {
                perror("accept");
                exit(EXIT_FAILURE);
            }

            //inform user of socket number - used in send and receive commands
			if(debug == 1){
				printf("New connection , socket fd is %d , ip is : %s , port : %d \n" , new_socket , inet_ntoa(address.sin_addr) , ntohs(address.sin_port));
			}

            //add new socket to array of sockets
            for (i = 0; i < max_clients; i++)
            {
                //if position is empty
				if( client_socket[i] == 0 )
                {
                    client_socket[i] = new_socket;
					if(debug == 1){
						printf("Adding to list of sockets as %d\n" , i);
					}
					break;
                }
            }
        }

        //else its some IO operation on some other socket :)
        for (i = 0; i < max_clients; i++)
        {
            sd = client_socket[i];

            if (FD_ISSET( sd , &readfds))
            {
                //Check if it was for closing , and also read the incoming message
                if ((valread = read( sd , buffer, 1024)) == 0)
                {
                    //Somebody disconnected , get his details and print
                    getpeername(sd , (struct sockaddr*)&address , (socklen_t*)&addrlen);
					if(debug == 1){
						printf("Host disconnected , ip %s , port %d \n" , inet_ntoa(address.sin_addr) , ntohs(address.sin_port));
					}
                    //Close the socket and mark as 0 in list for reuse
                    close( sd );
                    client_socket[i] = 0;
                }

                //Echo back the message that came in
                else
                {
			if(debug == 1){
				printf("Incoming Message from: %s Port: %d \n>>" , inet_ntoa(address.sin_addr) , ntohs(address.sin_port));
				for(i =0;i < buffer[4]+5;i++){printf("%02X ",buffer[i]&0xff);}
			}
		if(timer <= time(0)){
            setup(debug,database,user,pass,dbname);
            timer = (time(0)+120);
        }
			parse(buffer,valread,txbuffer,tx,debug);

		if (tx == 1){
							if(debug == 1){
								printf("Send answer to host: %s Port: %d \n<<" , inet_ntoa(address.sin_addr) , ntohs(address.sin_port));
								for(i =0;i < ((txbuffer[4]+5) & 0xff) ;i++){printf("%02X ",txbuffer[i]&0xff);}
								printf("\n\n\n");
							}
                            send( sd , txbuffer , ((txbuffer[4]+5) & 0xff) , 0 );
                            tx = 0;
                    }
					if(debug == 1){
						printf("\n\n\n");
					}
                }
            }
        }
    }

    return 0;
}
