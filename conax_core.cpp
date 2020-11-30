#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <cstring>
#include "conax_core.h"

#include "aes.h"
#include <mysql.h>


uint8_t syskey[16];
uint8_t omac[16];
uint8_t enckey[16];

uint8_t key20[16];
uint8_t key21[16];
uint8_t datum[2];


union _key{
    uint8_t _bkey[16];
    uint32_t _ukey[4];
};

uint8_t emmbuffer[64] ={
0x12, 0x3E, 0x82, 0x70, 0x3B, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x70, 0x32, 0x64, 0x10,
0x00, 0x00, 0x00, 0x00, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC,
0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC,
0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC, 0xCC
};


uint8_t dec2bcd_r(uint16_t dec)
{
    return (dec) ? ((dec2bcd_r( dec / 10 ) << 4) + (dec % 10)) : 0;
}

uint16_t _emmdate(int inday,int inmon, int inyear){


    union _CNX_DATE _conaxdate;

    _conaxdate._bit._tenyear = (inyear - 1990) / 10;
    _conaxdate._bit._years = (inyear - 1990) % 10;
    _conaxdate._bit._day = inday;
    _conaxdate._bit._month = inmon;

    return _conaxdate._outdate;
}


uint16_t _conax_datum(uint8_t debug){
    time_t rawtime; // = time(NULL);
    struct tm *ltm;// = localtime(&rawtime);

    rawtime = time(NULL);
    ltm = localtime(&rawtime);

    union _CNX_DATE _conaxdate;
    if(debug == 1){
        printf("Local-time: %02d.%02d.%04d %02d:%02d:%02d ", ltm->tm_mday, ltm->tm_mon +1 , ltm->tm_year + 1900, ltm->tm_hour, ltm->tm_min, ltm->tm_sec);
        ltm->tm_isdst ? printf("DST\n\n") : printf("\n\n");
    }
    _conaxdate._bit._tenyear = ((ltm->tm_year + 1900) - 1990) / 10;
    _conaxdate._bit._years = ((ltm->tm_year + 1900) - 1990) % 10;
    _conaxdate._bit._day = ltm->tm_mday;
    _conaxdate._bit._month = ltm->tm_mon +1;

    return _conaxdate._outdate;
}

uint16_t _conax_time(void){
    time_t rawtime; // = time(NULL);
    struct tm *ltm;// = localtime(&rawtime);

    union _zeit{
        uint16_t _16;
        uint8_t _8[2];
    };

    union _zeit _xx;
    rawtime = time(NULL);
    ltm = localtime(&rawtime);
    _xx._8[1] = dec2bcd_r(ltm->tm_min);
    _xx._8[0] = dec2bcd_r(ltm->tm_sec);
    return _xx._16;
}
void finish_with_error(MYSQL *con)
{
  fprintf(stderr, "%s\n", mysql_error(con));
  mysql_close(con);
  exit(1);
}

void read_syskey_sql(uint8_t debug,const char *database,const char *user,const char *pass,const char *dbname){
    MYSQL *con = mysql_init(NULL);
    if (con == NULL){
        fprintf(stderr, "mysql_init() failed\n");
        exit(1);
    }

    if (mysql_real_connect(con, database, user, pass, dbname, 0, NULL, 0) == NULL){
        finish_with_error(con);
    }

    if (mysql_query(con, "SELECT systemkey FROM emmg_systemkey where id=1")){
        finish_with_error(con);
    }

    MYSQL_RES *result = mysql_store_result(con);

    if (result == NULL){
        finish_with_error(con);
    }

    int num_fields = mysql_num_fields(result);

    MYSQL_ROW row;
    row = mysql_fetch_row(result);

    if(debug ==1){
        printf("Systemkey:\n");
    }

    for(int idx = 0;idx < 16;idx++){
        sscanf((const char*)row[0]+idx*2,"%02hhx",&syskey[idx]);
        if(debug ==1){
            printf("%02X ",syskey[idx]);
        }
    }
    if(debug ==1){
        printf("\n");
    }
    mysql_free_result(result);
    mysql_close(con);
}

void read_keys_sql(uint8_t debug,const char *database,const char *user,const char *pass,const char *dbname){
    MYSQL *con = mysql_init(NULL);
    MYSQL_RES *result;
    MYSQL_ROW row;

    if (con == NULL){
        fprintf(stderr, "mysql_init() failed\n");
        exit(1);
    }

    if (mysql_real_connect(con, database, user, pass, dbname, 0, NULL, 0) == NULL){
        finish_with_error(con);
    }

    if (mysql_query(con, "SELECT ecmkey FROM ecmg_keys where id=20")){
        finish_with_error(con);
    }

     result = mysql_store_result(con);

    if (result == NULL){
        finish_with_error(con);
    }

    row = mysql_fetch_row(result);

    if(debug ==1){
        printf("Key 20:\n");
    }

    for(int idx = 0;idx < 16;idx++){
        sscanf((const char*)row[0]+idx*2,"%02hhx",&key20[idx]);
        if(debug ==1){
            printf("%02X ",key20[idx]);
        }
    }
    if(debug ==1){
        printf("\n");
    }

    if (mysql_query(con, "SELECT ecmkey FROM ecmg_keys where id=21")){
        finish_with_error(con);
    }
    result = mysql_store_result(con);
    if (result == NULL){
        finish_with_error(con);
    }
    row = mysql_fetch_row(result);
    if(debug ==1){
        printf("Key 21:\n");
    }

    for(int idx = 0;idx < 16;idx++){
        sscanf((const char*)row[0]+idx*2,"%02hhx",&key21[idx]);
        if(debug ==1){
            printf("%02X ",key21[idx]);
        }
    }
    if(debug ==1){
        printf("\n");
    }

    mysql_free_result(result);
    mysql_close(con);
}

void generate_ecm(uint8_t *chid, uint8_t *buffernew,uint8_t *access,uint8_t keynum,uint8_t *cw0 ,uint8_t *cw1,uint8_t debug){

    union _kkk{
        uint16_t _16date;
        uint8_t _8date[2];
    };
    union _kkk _xx;
    union _kkk _yy;
    uint8_t omac[16];
    uint8_t odd=0;

    time_t rawtime; // = time(NULL);
    struct tm *ltm;// = localtime(&rawtime);
    rawtime = time(NULL);
    ltm = localtime(&rawtime);

    odd = (ltm->tm_mon+1) % 2;

    _xx._16date = _conax_datum(debug);
    _yy._16date = _conax_time();
    if(debug == 1){
        printf("CONAX-DATE: %04X\n\n",_xx._16date);
    }
    buffernew[0x02] = 0x44;
    buffernew[0x03] = 0x70;
    buffernew[0x04] = 0x42;
    buffernew[0x05] = 0x64;
    buffernew[0x06] = odd ? 0x21:0x20;//keynum;

    union _ecm{
        uint8_t _in[0x30]; //0x30 without OMAC
        uint32_t _xt[6][2];
    } ecm;

    memset(ecm._in,0xCC,0x30);

    int idx = 0;
    ecm._in[idx] = 0x20; idx++;
    ecm._in[idx] = 0x04; idx++;
    ecm._in[idx] = _xx._8date[1];idx++;
    ecm._in[idx] = _xx._8date[0];idx++;
    ecm._in[idx] = _yy._8date[1];idx++;
    ecm._in[idx] = _yy._8date[0];idx++;

    ecm._in[idx] = 0x40;idx++;
    ecm._in[idx] = 0x0f;idx++;

    for(int i=0;i<8;i++){ecm._in[idx] = cw1[i];idx++;}
    for(int i=0;i<8;i++){ecm._in[idx] = cw0[i];idx++;}
    ecm._in[idx] = 0x22;idx++;
    ecm._in[idx] = 0x04;idx++;
    for(int i=0;i<4;i++){ecm._in[idx] = access[i+1];idx++;}
    ecm._in[idx] = 0x21;idx++;
    ecm._in[idx] = 0x02;idx++;
    ecm._in[idx] = chid[0];idx++; //chid
    ecm._in[idx] = chid[1];idx++;
    AES_CMAC(odd ? key21: key20,ecm._in,0x30,omac);
    aes_cbc(odd ? key21: key20,ecm._in,0x30);

    for(int i =0;i<0x30;i++){buffernew[0x07+i] = ecm._in[i];} //+ 39
    for(int i =0;i<16;i++){buffernew[55+i]= omac[i];}

}

uint8_t generate_emm(char *in,uint8_t *out,uint8_t debug){
    uint32_t ppua,ppsa;//,temp[2][2];
    int day,mon,year,day2,mon2,year2;
    uint32_t acc;
    char name[20] ={0};
    uint32_t sid;
    uint8_t pp[4],idx;
    uint16_t time = _conax_time();

    union _emm{
        uint8_t _in[0x30];
        uint32_t _xt[6][2];
    } emm;


    memcpy(&out[0],&emmbuffer[0],64);

        if(in[0] == 0x55){
            sscanf(in,"U:%d S:%d-%d-%d E:%d-%d-%d A:%08x N:%s SA:%d ID:%04x",&ppua,&year,&mon,&day,&year2,&mon2,&day2,&acc,&name[0],&ppsa,&sid);
            if(debug == 1){
                printf("UNIQUE EMM\n");
                printf("Serial-No:   %d PPUA: %08X\n",ppua,ppua);
                printf("Start-date:  %02d.%02d.%d ",day,mon,year);
                printf("End-date: %02d.%02d.%d ",day2,mon2,year2);
                printf("Access Criteria: %08X\n",acc);
                printf("PPSA Update: %08X ",ppsa);
                printf("SERVICE-ID: %04X ",sid);
            }

            /* FILL EMM BUFFER */
            idx = 16;
            out[idx] = time >> 8 & 0xff;idx++;
            out[idx] = time & 0xff;idx++;
            out[idx] = 0xA0;idx++;
            out[idx] = 0x00;idx++;
            out[8] = out[idx] = ppua >> 24 & 0xff;idx++;
            out[9] = out[idx] = ppua >> 16 & 0xff;idx++;
            out[10] = out[idx] = ppua >> 8 & 0xff;idx++;
            out[11] = out[idx] =  ppua & 0xff;idx++;
            out[idx] = 0xA0;idx++;
            out[idx] = 0x03;idx++;
            out[idx] = _emmdate(day,mon,year) >> 8 & 0xff;idx++;
            out[idx] = _emmdate(day,mon,year) & 0xff;idx++;
            out[idx] = _emmdate(day2,mon2,year2) >> 8 & 0xff;idx++;
            out[idx] = _emmdate(day2,mon2,year2) & 0xff;idx++;
            out[idx] = 0xA0;idx++;
            out[idx] = 0x04;idx++;
            out[idx] = acc >> 24 & 0xff;idx++;
            out[idx] = acc >> 16 & 0xff;idx++;
            out[idx] = acc >> 8 & 0xff;idx++;
            out[idx] = acc & 0xff;idx++;
            if(strlen(name)>=1){
                    if(debug == 1){
                        printf("Name: %s\n",name);
                    }
                    out[idx] = 0xA0;idx++;
                    out[idx] = 0x10;idx++;
                    memset(&out[idx],0x20,0x0f);
                    memcpy(&out[idx],&name[0],strlen(name));
                    idx += 15;
            }
            out[idx] = 0xA0;idx++;
            out[idx] = 0x02;idx++;
            out[idx] = ppsa >> 24 & 0xff;idx++;
            out[idx] = ppsa >> 16 & 0xff;idx++;
            out[idx] = ppsa >> 8 & 0xff;idx++;
            out[idx] = ppsa & 0xff;idx++;
            out[idx] = 0xA0;idx++;
            out[idx] = 0x01;idx++;
            out[idx] = sid >> 8 & 0xff;idx++;
            out[idx] = sid & 0xff;idx++;

            memset(&name,0,20);

            pp[0] = ppua >> 24 & 255;
            pp[1] = ppua >> 16 & 255;
            pp[2] = ppua >> 8 & 255;
            pp[3] = ppua & 255;

            AES_CMAC(syskey,pp,0x4,enckey);
            if(debug == 1){
                printf("\nENC-KEY: ");
                for(int i=0;i<16;i++){
                    printf("%02x ",enckey[i]);
                }
                printf("\n");
            }
            }

        if(in[0] == 0x47){

            sscanf(in,"G:%d",&ppsa);
            if(debug ==1){
                printf("SHARED EMM\n");
                printf("Shared Address: %d PPSA: %08X\n",ppsa,ppsa);
            }
            idx = 16;
            out[idx] = time >> 8 & 0xff;idx++;
            out[idx] = time & 0xff;idx++;
            out[idx] = 0xA0;idx++;
            out[idx] = 0x02;idx++;
            out[8] = out[idx] = ppsa >> 24 & 0xff;idx++;
            out[9] = out[idx] = ppsa >> 16 & 0xff;idx++;
            out[10] = out[idx] = ppsa >> 8 & 0xff;idx++;
            out[11] = out[idx] =  ppsa & 0xff;idx++;
            out[idx] = 0xA0;idx++;
            out[idx] = 0x20;idx++;
            memcpy(&out[idx],key20,16);
            idx += 16;
            out[idx] = 0xA0;idx++;
            out[idx] = 0x21;idx++;
            memcpy(&out[idx],key21,16);

            pp[0] = ppsa >> 24 & 255;
            pp[1] = ppsa >> 16 & 255;
            pp[2] = ppsa >> 8 & 255;
            pp[3] = ppsa & 255;

            AES_CMAC(syskey,pp,0x4,enckey);

            if(debug ==1){
                printf("\nENC-KEY: ");
                for(int i=0;i<16;i++){
                    printf("%02x ",enckey[i]);
                }
                printf("\n");
            }

        }
            memcpy(&emm._in[0],&out[16],0x30);

            AES_CMAC(enckey,emm._in,0x30,omac);
            if(debug ==1){
                printf("OMAC:    ");
                for(int i=0;i<16;i++){
                    printf("%02x ",omac[i]);
                }
                printf("\n");
            }

            for(int i=0;i<16;i++){
                out[64+i] = omac[i];
            }



            aes_cbc(enckey,emm._in,0x30);
            memcpy(&out[16],&emm._in[0],0x30);
            if(debug == 1){
                for(int i = 0;i < 16;i++){printf("___");}
                printf("\n");
                for(int i = 0;i < 64;i++){printf("%02x ",out[i]); if((i%16)==15)printf("\n");}
                printf("\n");
                for(int i = 0;i < 16;i++){printf("___");}
                printf("\n\n");
            }
    return 64;
}

