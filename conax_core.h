#ifndef CONAX_CORE
#define CONAX_CORE

#include <time.h>
#include <stdio.h>
#include <fstream>
#include <cstring>
#include <stdint.h>

union _CNX_DATE{
    uint16_t _outdate;
    struct{
        unsigned _month:4;
        unsigned _years:4;
        unsigned _day:5;
        unsigned _tenyear:3;
    }_bit;
};


using namespace std;
extern uint8_t generate_emm(char *in,uint8_t *out,uint8_t debug);
extern void generate_ecm(uint8_t *chid,uint8_t *buffernew,uint8_t *access,uint8_t keynum,uint8_t *cw0 ,uint8_t *cw1,uint8_t debug);
extern void read_syskey_sql(uint8_t debug,const char *database,const char *user,const char *pass,const char *dbname);
extern void read_keys_sql(uint8_t debug,const char *database,const char *user,const char *pass,const char *dbname);
#endif
