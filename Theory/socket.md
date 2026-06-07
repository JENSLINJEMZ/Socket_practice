# Module 1: Python Socket Programming

## The Engineer's Mental Model First

Before we write a single line of code, you need to understand what's actually happening on the wire — because when something breaks at 2am, theory is what saves you.

---

# What Is a Socket?

A **socket** is an endpoint of a two-way communication link between two programs running on a network. Think of it like a phone jack in a wall — the jack itself doesn't carry conversation, but it's the interface between your phone and the telephone network.

```text
Application Layer
      │
      ▼
┌─────────────┐
│   SOCKET    │  ◄── Your program talks to this
│  (API/File  │
│  Descriptor)│
└─────────────┘
      │
      ▼
┌─────────────┐
│  TCP / UDP  │  ◄── OS handles this
└─────────────┘
      │
      ▼
┌─────────────┐
│     IP      │
└─────────────┘
      │
      ▼
┌─────────────┐
│  Ethernet   │
└─────────────┘
      │
      ▼
   [Wire]
```

The operating system gives your program a **file descriptor** (an integer like `4` or `7`) that represents the socket. Every read/write operation on that integer goes through the kernel's TCP/IP stack and out onto the network. You never touch raw packets — the socket API abstracts that for you.

---

# How TCP Communication Works Internally

TCP is a **connection-oriented**, **reliable**, **ordered**, **byte-stream** protocol.

Let's unpack each word:

* **Connection-oriented** → before data flows, both sides must agree to communicate
* **Reliable** → every byte sent is acknowledged; lost packets are retransmitted
* **Ordered** → bytes arrive in the order they were sent (sequence numbers)
* **Byte-stream** → no message boundaries; it's a pipe of bytes

---

## The Three-Way Handshake

This happens before your application sends a single byte of data:

```text
CLIENT                          SERVER
  │                               │
  │──── SYN (seq=100) ───────────►│   "I want to connect, my seq starts at 100"
  │                               │
  │◄─── SYN-ACK (seq=300,        │   "OK, my seq starts at 300, I got your 100"
  │          ack=101) ────────────│
  │                               │
  │──── ACK (ack=301) ───────────►│   "Got it, connection established"
  │                               │
  │         [DATA FLOWS]          │
  │                               │
```

* **SYN** → Synchronize sequence numbers
* **ACK** → Acknowledgement number (next expected byte)
* **seq** → Sequence number (tracks byte ordering)

The sequence numbers matter because if packets arrive out of order, TCP uses them to reassemble the stream correctly. This is the "reliable ordered" part.

---

## Connection Teardown (4-Way)

```text
CLIENT                          SERVER
  │                               │
  │──── FIN ─────────────────────►│   "I'm done sending"
  │◄─── ACK ──────────────────────│   "Got it"
  │◄─── FIN ──────────────────────│   "I'm also done"
  │──── ACK ─────────────────────►│   "Got it, bye"
  │                               │
```

---

# Ports: What They Are and Why They Exist

An IP address gets a packet to the right **machine**. A port gets it to the right **application** on that machine.

```text
IP Address = Apartment Building Address
Port       = Apartment Number

192.168.1.10:80  → Web server application
192.168.1.10:22  → SSH daemon
192.168.1.10:443 → HTTPS server
```

### Port Ranges

* **0–1023** → Well-known/System ports (requires root/admin)
* **1024–49151** → Registered ports
* **49152–65535** → Ephemeral/Dynamic ports (OS assigns these to clients)

When your browser connects to `google.com:443`, your OS assigns your browser a random ephemeral port like `52841`.

The full 4-tuple that uniquely identifies the connection:

```text
(src_ip, src_port, dst_ip, dst_port)
(192.168.1.5, 52841, 142.250.80.46, 443)
```

---

# Client/Server Architecture

```text
┌──────────────────────────────────────────────────────┐
│                      SERVER                          │
│                                                      │
│  1. Create socket                                    │
│  2. Bind to IP:Port                                  │
│  3. Listen for connections   ◄── Passive open        │
│  4. Accept connection        ◄── Blocks here         │
│  5. Recv/Send data                                   │
│  6. Close connection                                 │
└──────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────┐
│                      CLIENT                          │
│                                                      │
│  1. Create socket                                    │
│  2. Connect to server IP:Port  ◄── Active open       │
│  3. Send/Recv data             ◄── Triggers handshake│
│  4. Close connection                                 │
└──────────────────────────────────────────────────────┘
```

The server **binds** to a well-known port and **waits**. The client **initiates**.

This asymmetry is fundamental — it's the same model used by every TCP-based protocol:

* HTTP
* SSH
* FTP
* SMTP
* HTTPS
* MySQL
* Redis

Everything follows this structure.

---

# Building the TCP Server

```python
# tcp_server.py

import socket  # Python's interface to the OS socket API

# ── Step 1: Create the socket object ──────────────────────────────────────────
# socket.AF_INET     → Address Family: IPv4 (use AF_INET6 for IPv6)
# socket.SOCK_STREAM → Socket Type: TCP (use SOCK_DGRAM for UDP)
#
# This call asks the OS kernel to create a new socket and hand us a file
# descriptor. Nothing is bound or listening yet.
server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

# ── Step 2: Set socket options ─────────────────────────────────────────────────
# SOL_SOCKET   → option level: generic socket options
# SO_REUSEADDR → allows rebinding to a port that's in TIME_WAIT state
#
# Without this, if you stop and restart your server quickly, you'll get:
# "OSError: [Errno 98] Address already in use"
# This happens because the OS keeps the port in TIME_WAIT for ~60 seconds
# after the connection closes (it's waiting for stray packets to expire).
# SO_REUSEADDR bypasses this wait.
server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)

# ── Step 3: Bind to an address and port ───────────────────────────────────────
# '0.0.0.0' means "listen on ALL network interfaces on this machine"
#
#   - If your machine has eth0 (192.168.1.10) and lo (127.0.0.1),
#     binding to 0.0.0.0 means both interfaces will accept connections.
#
#   - Binding to '127.0.0.1' would restrict to loopback only (local only).
#
#   - Binding to '192.168.1.10' would restrict to that specific NIC.

HOST = '0.0.0.0'
PORT = 9999

server_socket.bind((HOST, PORT))

# The bind() call tells the OS:
# "this file descriptor owns this IP:Port"

# ── Step 4: Start listening ───────────────────────────────────────────────────
# The argument (5) is the BACKLOG — how many pending connections the OS will
# queue before refusing new ones.
#
# These are connections that completed the 3-way handshake but haven't been
# accept()ed by your app yet.
#
# Think of it like a waiting room:
# max 5 clients can sit waiting before the OS starts rejecting new attempts.
server_socket.listen(5)

print(f"[*] Server listening on {HOST}:{PORT}")
print(f"[*] Waiting for connections...")

# ── Step 5: Accept connections (blocking call) ────────────────────────────────
# accept() BLOCKS here — the process sleeps until a client connects.
#
# When a client completes the 3-way handshake, accept() returns:
#
#   - client_socket → NEW socket object for this connection
#   - address       → (client_ip, client_port)
#
# IMPORTANT:
# The original server_socket keeps listening.
# The new client_socket is dedicated to this client only.
client_socket, address = server_socket.accept()

print(f"[+] Connection accepted from {address[0]}:{address[1]}")

# ── Step 6: Receive data ───────────────────────────────────────────────────────
# recv(1024) reads UP TO 1024 bytes.
#
# TCP is a byte stream — there are NO message boundaries.
# One recv() does not equal one send().
#
# Data arrives as BYTES, not strings.
data = client_socket.recv(1024)

print(f"[*] Received: {data.decode('utf-8')}")

# ── Step 7: Send a response ───────────────────────────────────────────────────
# sendall() ensures ALL bytes are transmitted.
response = "Hello from the server! Connection successful.\n"

client_socket.sendall(response.encode('utf-8'))

# ── Step 8: Close connections ─────────────────────────────────────────────────
client_socket.close()
server_socket.close()

print("[*] Connection closed.")
```

---

# Building the TCP Client

```python
# tcp_client.py

import socket

# ── Step 1: Create socket ──────────────────────────────────────────────────────
# Same parameters as the server — IPv4 + TCP.
client_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

# ── Step 2: Define where to connect ───────────────────────────────────────────
SERVER_IP = '127.0.0.1'
SERVER_PORT = 9999

# ── Step 3: Connect to the server ─────────────────────────────────────────────
# connect() triggers the THREE-WAY HANDSHAKE.
#
# At the OS level:
#
#   1. Send SYN
#   2. Receive SYN-ACK
#   3. Send ACK
#
# connect() BLOCKS until:
#   - handshake completes
#   - connection fails
#   - timeout occurs
client_socket.connect((SERVER_IP, SERVER_PORT))

print(f"[+] Connected to {SERVER_IP}:{SERVER_PORT}")

# ── Step 4: Send data ──────────────────────────────────────────────────────────
message = "Hello from the client!"

client_socket.sendall(message.encode('utf-8'))

print(f"[*] Sent: {message}")

# ── Step 5: Receive response ──────────────────────────────────────────────────
response = client_socket.recv(1024)

print(f"[*] Server replied: {response.decode('utf-8')}")

# ── Step 6: Close the socket ──────────────────────────────────────────────────
client_socket.close()

print("[*] Connection closed.")
```

---

# Running It — What to Expect

## Terminal 1 — Start the server first

```bash
python3 tcp_server.py
```

Expected output:

```text
[*] Server listening on 0.0.0.0:9999
[*] Waiting for connections...
```

---

## Terminal 2 — Run the client

```bash
python3 tcp_client.py
```

Expected output:

```text
[+] Connected to 127.0.0.1:9999
[*] Sent: Hello from the client!
[*] Server replied: Hello from the server! Connection successful.
[*] Connection closed.
```

---

## Back in Terminal 1

You should now see:

```text
[+] Connection accepted from 127.0.0.1:52841
[*] Received: Hello from the client!
[*] Connection closed.
```

---

# Verify With `ss` or `netstat`

Before running the client:

```bash
ss -tnlp | grep 9999
```

Expected output:

```text
LISTEN  0  5  0.0.0.0:9999  0.0.0.0:*  users:(("python3",pid=12345,fd=3))
```

---

After connecting the client:

```bash
ss -tnp | grep 9999
```

Expected output:

```text
ESTAB  0  0  127.0.0.1:9999   127.0.0.1:52841
ESTAB  0  0  127.0.0.1:52841  127.0.0.1:9999
```

This shows the **4-tuple** we talked about.

The connection appears twice:

* once from the server's perspective
* once from the client's perspective

---

# Common Mistakes and How to Debug Them

## ❌ ConnectionRefusedError

Cause:

* Server isn't running
* Wrong IP or port

Fix:

```bash
ss -tnlp | grep 9999
```

Start the server first.

---

## ❌ OSError: [Errno 98] Address already in use

Cause:

* Previous socket still in `TIME_WAIT`

Fix:

* Use `SO_REUSEADDR`
* Wait ~60 seconds

---

## ❌ Received empty bytes `b''`

Cause:

* The peer closed the connection

Fix:

Always check:

```python
if data == b'':
    print("Connection closed")
```

---

## ❌ Partial data received

Cause:

* TCP is a stream
* One `send()` ≠ one `recv()`

Fix:

Use:

* length prefixes
* delimiters
* receive loops

---

## ❌ UnicodeDecodeError

Cause:

* Trying to decode binary data as UTF-8

Fix:

Only decode when payload is text.

---

# What's Happening at the Packet Level

```text
CLIENT (52841)                          SERVER (9999)
      │                                      │
      │──[TCP SYN seq=x]───────────────────►│
      │◄─[TCP SYN-ACK seq=y ack=x+1]────────│
      │──[TCP ACK ack=y+1]─────────────────►│
      │                                      │
      │──[TCP PSH "Hello from client!"]────►│
      │◄─[TCP ACK]───────────────────────────│
      │                                      │
      │◄─[TCP PSH "Hello from server!"]──────│
      │──[TCP ACK]─────────────────────────►│
      │                                      │
      │──[TCP FIN]─────────────────────────►│
      │◄─[TCP ACK]───────────────────────────│
      │◄─[TCP FIN]───────────────────────────│
      │──[TCP ACK]─────────────────────────►│
```

You can inspect packets live with:

```bash
sudo tcpdump -i lo -n port 9999 -v
```

Run this while executing your scripts.

You'll see:

* SYN
* ACK
* PSH
* FIN

packets in real time.

---

# Exercises

## Exercise 1 — Uppercase Echo Server

Modify the server so it:

1. receives client data
2. converts it to uppercase
3. sends it back

Example:

Client sends:

```text
hello
```

Server responds:

```text
HELLO
```

---

## Exercise 2 — Error Handling

Run the client without the server.

Catch the exception:

```python
ConnectionRefusedError
```

and print a clean error message instead of crashing.

---

## Exercise 3 — Observe Socket States

While the server is waiting in `accept()`:

```bash
ss -tnlp | grep 9999
```

Observe:

```text
LISTEN
```

Then connect the client and observe:

```text
ESTABLISHED
```

---

## Exercise 4 — Multiple Messages

Modify the client:

```python
for i in range(3):
    client_socket.sendall(...)
```

Modify the server to receive all 3 messages.

Observe:

* messages may merge together
* boundaries are not preserved

This demonstrates that TCP is a **stream protocol**, not a message protocol.

---

# Mini Quiz

Answer these before continuing.

1. What does `SO_REUSEADDR` protect against and why is it important during development?

2. What is the backlog parameter in `listen(5)` and what happens when it fills up?

3. Why does `recv(1024)` not guarantee exactly 1024 bytes?

4. What's the difference between `send()` and `sendall()`?

5. What happens if the client connects to a port that's LISTENING but the server hasn't called `accept()` yet?

---

# Next Module Preview

In the next module you'll build:

* a persistent TCP server
* multiple-client handling
* threaded connection handling
* proper receive loops
* message framing
* graceful disconnect handling

This is the foundation of:

* web servers
* chat servers
* SSH daemons
* multiplayer game servers
* reverse shells
* proxies

