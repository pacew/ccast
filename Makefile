CFLAGS = -g -Wall
LIBS = -lwebsockets

all: wss

WSS_OBJS = wss.o
wss: $(WSS_OBJS)
	$(CC) $(CFLAGS) -o wss $(WSS_OBJS) $(LIBS)

