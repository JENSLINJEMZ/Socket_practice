import socket 

ip = "0.0.0.0"
port = 4444

server = socket.socket(socket.AF_INET,socket.SOCK_STREAM)
server.bind((ip,port))
server.listen(5)
while True:
    browser,address = server.accept()

    data = browser.recv(4096).decode()
    file = data.split("\r\n")[0]
    f = file.split()[1]
    if f == "/home":
        page = "home.html"
        status = "HTTP/1.1 200 OK"
    if page:
        with open("home.html", "r", encoding="utf-8") as p:
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


