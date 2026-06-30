import socket

ip = "0.0.0.0"
port = 4444

client = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

client.connect((ip,port))

client.send("hello server".encode())

data = client.recv(1024).decode()
print("server",data)
client.close()