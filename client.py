import socket

ip = "0.0.0.0"
port = 4444

client = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

client.connect((ip,port))
data = client.recv(1024).decode()
print("server",data)


while True:
    
    message = input("Enter message: ")
    if message  == "quit":
        client.send(message.encode())
        client.close()
        break
    else:
        client.send(message.encode())