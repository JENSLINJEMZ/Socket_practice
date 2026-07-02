import socket
host = "0.0.0.0"
port = 4444
server_soc = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
server_soc.connect((host,port))
filename = server_soc.recv(1024).decode()
try:
    with open(filename,"wb") as f:
        while True:
            data = server_soc.recv(1024)
            if not data:
                break
            f.write(data)
    print("File is received")
except Exception as e:
    print(e)

server_soc.close()