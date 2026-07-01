import socket
server_soc = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
host = "0.0.0.0"
port = 4444
server_soc.bind((host,port))
server_soc.listen(1)

client , address = server_soc.accept()

while True:
    data = client.recv(1024).decode()
    client.send(f"Convert: {data.upper()}".encode())
server_soc.close()
client.close()


