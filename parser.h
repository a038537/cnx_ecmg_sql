#ifndef PARSER_H
#define PARSER_H
#include <stdint.h>

extern void setup(uint8_t debug,const char *database,const char *user,const char *pass,const char *dbname);
extern void parse(char *daten,uint8_t menge,char *zurueck,int& senden,uint8_t debug);

#endif // PARSER_H



