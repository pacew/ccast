#! /usr/bin/env python

import cherrypy
from ws4py.server.cherrypyserver import WebSocketPlugin, WebSocketTool
from ws4py.websocket import WebSocket

import json
import readline
import sys
import threading
import time

import config

response_lock = threading.Lock()

class JRConsoleWS(WebSocket):
    logfile = None

    def opened(self):
        CLIThread.instance = self
        WebSocket.opened(self)
        self.logfile = open("/tmp/jrconsole.log", "a")
        self.logfile.write("\n")
        print "Found connection from", self.peer_address
        response_lock.release()

    def xreceived_message(self, message):
        print "Received message:", message
        self.send(json.dumps({"type": "message", "data": "received"}), False)
        print "Sent a reply"

    def received_message(self, message):
        try:
            response = json.loads(message.data)
            if response["type"] == "log":
                self.logfile.write(response["data"] + "\n")
                self.logfile.flush()
            elif response["type"] == "evaluated":
                print "=", response["data"]
                response_lock.release()
            elif response["type"] == "empty":
                pass
            else:
                print "! unknown response type", response["type"], ", data:", response["data"]
        except Exception as e:
            print "!", type(e), e.message
            response_lock.release()

class CLIThread(threading.Thread):
    running = True
    instance = None

    def __init__(self, jrserver):
        threading.Thread.__init__(self)
        self.jrserver = jrserver

    def run(self):
        while True:
            response_lock.acquire()
            try:
                line = raw_input("> ").strip()
            except EOFError:
                print "Got EOF, shutting down"
                self.command("quit")
                CLIThread.running = False
#                self.jrserver.shutdown()
                return
            self.toeval(line)

    def send(self, typename, msg):
        self.instance.send(json.dumps({"type": typename, "data": msg}), False)

    def toeval(self, msg):
        self.send("toeval", msg)

    def command(self, msg):
        self.send("command", msg)

class Root(object):
    @cherrypy.expose
    def index(self):
        handler = cherrypy.request.ws_handler

def setup():
    cherrypy.config.update({"server.socket_host": "0.0.0.0",
                            "server.socket_port": 7923,
                            "server.ssl_module": "pyopenssl",
                            "server.ssl_certificate": config.crt,
                            "server.ssl_private_key": config.keyfile,
                            "server.ssl_certificate_chain": config.pem})
#                            })
    WebSocketPlugin(cherrypy.engine).subscribe()
    cherrypy.tools.websocket = WebSocketTool()

    return CLIThread(None)

def main():

    response_lock.acquire()

    clithread = setup()

    clithread.start()

    cherrypy.quickstart(Root(), '/', config={'/': {'tools.websocket.on': True,
                                                   'tools.websocket.handler_cls': JRConsoleWS}})

#    server.timeout = 1
    print "Waiting for connection from a Web page."
#    while CLIThread.running:
#        server.handle_request()

if __name__ == "__main__":
    main()
