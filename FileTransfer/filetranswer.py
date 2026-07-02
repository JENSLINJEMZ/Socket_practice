import socket
host = "0.0.0.0"
port = 4444
file = "image.jpg"
server_soc = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
server_soc.bind((host,port))
server_soc.listen(1)

client , address = server_soc.accept()
client.send(file.encode())
try:
    with open(file,"rb") as f:
        while True:
            data = f.read(1024)
            if not data:
                break
            client.sendall(data)
    print("file sended...")
except Exception as e:
    print(e)

server_soc.close()
client.close()