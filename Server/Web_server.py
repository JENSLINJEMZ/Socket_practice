import socket 
ip = "0.0.0.0"
port = 4443
server = socket.socket(socket.AF_INET,socket.SOCK_STREAM)
server.bind((ip,port))
server.listen(5)
print("[*] Server Started..")
while True:
    browser,address = server.accept()
    print(f"Connected from: {address}")

    data = browser.recv(4096).decode()
    file = data.split("\r\n")[0]
    path = file.split()[1]
    if not path:
        break
    if path == "/":
         page = "index.html"
         status = "HTTP/1.1 200 OK"
    elif path == "/home":
        page = "home.html"
        status = "HTTP/1.1 200 OK"
    else:
        page = None
        status = "HTTP/1.1 404 Not Found"

    
    if page:
        with open(page , "r", encoding="utf-8") as p:
            body = p.read()
    else:
        body = """
                <html>
                <body>
                <h1>404 Not Found</h1>
                </body>
                </html>
            """


    response = (
        f"{status}\r\n"
        "Content-Type: text/html\r\n"
        f"Content-Length: {len(body.encode())}\r\n"
        "Connection: close\r\n"
        "\r\n"
        f"{body}")
    
    browser.sendall(response.encode())
    server.close()
    browser.close()
    break

server.close()
browser.close()
