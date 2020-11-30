VERSION = 0.02
CFLAGS  = -Wall -O2 `mysql_config --cflags --libs`# -g -D_REENTRANT -DVERSION=\"$(VERSION)\"
LDFLAGS = -lm `mysql_config --cflags --libs`
OUT = $(shell uname -m)

CC      = /usr/bin/g++




OBJ = linux_ecmg.o conax_core.o parser.o aes.o

all: $(OBJ)
	$(CC) $(CFLAGS) -o conax-ecmg.$(OUT) $(OBJ) $(LDFLAGS)

%.o: %.cpp
	$(CC) $(CFLAGS) -c $<

clean:
	rm *.o *.$(OUT)
