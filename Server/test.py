import socket 

ip = "0.0.0.0"
port = 4443

server = socket.socket(socket.AF_INET,socket.SOCK_STREAM)
server.bind((ip,port))
server.listen(5)
print("[*] Server Started..")

browser,address = server.accept()
print(f"Connected from: {address}")

data = browser.recv(4096).decode()
file = data.split("\r\n")
request_line = file[0]

method, path, version = request_line.split()

if path == "/login":
    print("working")

server.close()
browser.close()


