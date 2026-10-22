"""Loopback-only SMTP fixture. It never forwards or delivers messages."""
import socketserver
from pathlib import Path

CAPTURE = Path("/tmp/ccs-smtp-capture.txt")
CAPTURE.write_bytes(b"")

class SMTPHandler(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b"220 localhost test SMTP\r\n")
        data = None
        while line := self.rfile.readline():
            if data is not None:
                if line.rstrip(b"\r\n") == b".":
                    with CAPTURE.open("ab") as output:
                        output.write(b"".join(data) + b"\n")
                    data = None
                    self.wfile.write(b"250 accepted locally\r\n")
                else:
                    data.append(line)
                continue
            command = line.upper()
            if command.startswith(b"EHLO"):
                self.wfile.write(b"250-localhost\r\n250 SIZE 1048576\r\n")
            elif command.startswith((b"HELO", b"MAIL FROM:", b"RCPT TO:", b"RSET", b"NOOP")):
                self.wfile.write(b"250 OK\r\n")
            elif command.startswith(b"DATA"):
                data = []
                self.wfile.write(b"354 End with dot\r\n")
            elif command.startswith(b"QUIT"):
                self.wfile.write(b"221 Bye\r\n")
                return
            else:
                self.wfile.write(b"502 Not implemented\r\n")

class SMTPServer(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True

with SMTPServer(("127.0.0.1", 25252), SMTPHandler) as server:
    server.serve_forever()
