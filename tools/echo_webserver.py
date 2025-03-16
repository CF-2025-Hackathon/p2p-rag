#!/usr/bin/env python3
from http.server import BaseHTTPRequestHandler, HTTPServer

class EchoHandler(BaseHTTPRequestHandler):
    def _handle_request(self):
        content_length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(content_length) if content_length > 0 else b''
        print(f"Request: {self.requestline}\nHeaders: {self.headers}\nBody: {body.decode() if body else ''}")
        self.send_response(200)
        self.send_header('Content-type', 'text/plain')
        self.end_headers()
        self.wfile.write(b'{}')
    
    def do_GET(self): self._handle_request()
    def do_POST(self): self._handle_request()
    def do_PUT(self): self._handle_request()
    def do_DELETE(self): self._handle_request()

HTTPServer(('', 9999), EchoHandler).serve_forever()
