/*
	TCP Echo server example in winsock
	Live Server on port 8888
*/
#include<stdio.h>
#include<winsock2.h>
//#pragma comment(lib, "ws2_32.lib") //Winsock Library

#include "parser.h"

int tx = 0;
char txbuffer[255];
uint8_t debug = 0;

HANDLE hConsole = GetStdHandle(STD_OUTPUT_HANDLE);


int main(int argc , char *argv[])
{

    unsigned int port = 0;
      if(argc < 2){
        fprintf(stderr,"Error! No args defined! Exiting...\n");
        fprintf(stderr,"Usage: conax-emmg --port [val] --ip [val] [--debug]\n");
        return -1;
     }


     for(int i = 1;i<argc;i++){
        if(!strcmp(argv[i],"--port")){
            port=strtoul(argv[i+1],NULL,0);
        }
        if(!strcmp(argv[i],"--debug")){
            debug = 1;
        }
     }

    if(port == 0){
        fprintf(stderr,"Error! No Port defined! Exiting...\n");
        return -1;
    }



    setup(debug);
	WSADATA wsa;
	SOCKET master , new_socket , client_socket[30] , s;
	struct sockaddr_in server, address;
	int max_clients = 30 , activity, addrlen, i, valread;
//	char *message = "ECHO Daemon v1.0 \r\n";

	//size of our receive buffer, this is string length.
	int MAXRECV = 1024;
	//set of socket descriptors
    fd_set readfds;
	//1 extra for null character, string termination
	//unsigned char buffer[1024]
	char *buffer;
	buffer =  (char*) malloc((MAXRECV + 1) * sizeof(char));

	for(i = 0 ; i < 30;i++)
	{
		client_socket[i] = 0;
	}

	printf("\nInitialising Winsock...");
	if (WSAStartup(MAKEWORD(2,2),&wsa) != 0)
	{
		printf("Failed. Error Code : %d",WSAGetLastError());
		exit(EXIT_FAILURE);
	}

	printf("Initialised.\n");

	//Create a socket
	if((master = socket(AF_INET , SOCK_STREAM , 0 )) == INVALID_SOCKET)
	{
		printf("Could not create socket : %d" , WSAGetLastError());
		exit(EXIT_FAILURE);
	}

	printf("Socket created.\n");

	//Prepare the sockaddr_in structure
	server.sin_family = AF_INET;
	server.sin_addr.s_addr = INADDR_ANY;
	server.sin_port = htons( port );

	//Bind
	if( bind(master ,(struct sockaddr *)&server , sizeof(server)) == SOCKET_ERROR)
	{
		printf("Bind failed with error code : %d" , WSAGetLastError());
		exit(EXIT_FAILURE);
	}

	puts("Bind done");

	#ifdef CONAX_CORE
	SetConsoleTextAttribute(hConsole, 10);
	puts("CONAX CORE INITIALIZED");
	#endif // CONAX_CORE

	//Listen to incoming connections
	listen(master , 3);

	//Accept and incoming connection
	puts("Let's get ready to scramble......");
    SetConsoleTextAttribute(hConsole, 7);
	addrlen = sizeof(struct sockaddr_in);

	while(TRUE)
    {
        //clear the socket fd set
        FD_ZERO(&readfds);

        //add master socket to fd set
        FD_SET(master, &readfds);

        //add child sockets to fd set
        for (  i = 0 ; i < max_clients ; i++)
        {
            s = client_socket[i];
            if(s > 0)
            {
                FD_SET( s , &readfds);
            }
        }

        //wait for an activity on any of the sockets, timeout is NULL , so wait indefinitely
        activity = select( 0 , &readfds , NULL , NULL , NULL);

        if ( activity == SOCKET_ERROR )
        {
            printf("select call failed with error code : %d" , WSAGetLastError());
			exit(EXIT_FAILURE);
        }

        //If something happened on the master socket , then its an incoming connection
        if (FD_ISSET(master , &readfds))
        {
            if ((new_socket = accept(master , (struct sockaddr *)&address, (int *)&addrlen))<0)
            {
                perror("accept");
                exit(EXIT_FAILURE);
            }

            //inform user of socket number - used in send and receive commands
            if(debug == 1){
                printf("New connection , socket fd is %d , ip is : %s , port : %d \n" , new_socket , inet_ntoa(address.sin_addr) , ntohs(address.sin_port));
            }
            //send new connection greeting message
            /*
            if( send(new_socket, message, strlen(message), 0) != strlen(message) )
            {
                perror("send failed");
            }
            */
            if(debug == 1){
                puts("Welcome message sent successfully");
            }
            //add new socket to array of sockets
            for (i = 0; i < max_clients; i++)
            {
                if (client_socket[i] == 0)
                {
                    client_socket[i] = new_socket;
                    if(debug == 1){
                        printf("Adding to list of sockets at index %d \n" , i);
                    }
                    break;
                }
            }
        }

        //else its some IO operation on some other socket :)
        for (i = 0; i < max_clients; i++)
        {
            s = client_socket[i];
			//if client presend in read sockets
            if (FD_ISSET( s , &readfds))
            {
                //get details of the client
				getpeername(s , (struct sockaddr*)&address , (int*)&addrlen);

				//Check if it was for closing , and also read the incoming message
				//recv does not place a null terminator at the end of the string (whilst printf %s assumes there is one).
                valread = recv( s , buffer, MAXRECV, 0);

				if( valread == SOCKET_ERROR)
				{
					int error_code = WSAGetLastError();
					if(error_code == WSAECONNRESET)
					{
						//Somebody disconnected , get his details and print
						printf("Host disconnected unexpectedly , ip %s , port %d \n" , inet_ntoa(address.sin_addr) , ntohs(address.sin_port));

						//Close the socket and mark as 0 in list for reuse
						closesocket( s );
						client_socket[i] = 0;
					}
					else
					{
						printf("recv failed with error code : %d" , error_code);
					}
				}
				if ( valread == 0)
                {
                    //Somebody disconnected , get his details and print
                    printf("Host disconnected , ip %s , port %d \n" , inet_ntoa(address.sin_addr) , ntohs(address.sin_port));

                    //Close the socket and mark as 0 in list for reuse
                    closesocket( s );
                    client_socket[i] = 0;
                }

                //Echo back the message that came in
                else
                {
					//add null character, if you want to use with printf/puts or other string handling functions
					//buffer[valread] = '\0';
					SetConsoleTextAttribute(hConsole, 7);
					if(debug == 1){
                        printf("Incoming Message from: %s Port: %d \n\n>>" , inet_ntoa(address.sin_addr) , ntohs(address.sin_port));
					}
                    SetConsoleTextAttribute(hConsole, 10);
                    if(debug == 1){
                        for(i =0;i < buffer[4]+5;i++){printf("%02X ",buffer[i]&0xff);}
                    }
                    SetConsoleTextAttribute(hConsole, 7);

                    parse(buffer,valread,txbuffer,tx,debug);



                    if (tx == 1){
                            if(debug == 1){
                                printf("Send answer to host: %s Port: %d \n<<" , inet_ntoa(address.sin_addr) , ntohs(address.sin_port));
                                SetConsoleTextAttribute(hConsole, 12);
                                for(i =0;i < ((txbuffer[4]+5) & 0xff);i++){printf("%02X ",txbuffer[i]&0xff);}
                                SetConsoleTextAttribute(hConsole, 7);
                                printf("\n\n\n");
                            }
                            send( s , txbuffer , ((txbuffer[4]+5) &0xff ) , 0 );
                            tx = 0;
                    }
                    if(debug == 1){
                        printf("\n\n\n");
                    }
                }

            }
        }
    }

	closesocket(s);
	WSACleanup();

	return 0;
}
