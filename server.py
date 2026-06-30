import socket

server_soc = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

host = "0.0.0.0"
port = 4444

server_soc.bind((host,port))

server_soc.listen(1)
print(f"[*] Server listening on {host}:{port}")
print(f"[*] Waiting for connections...")

client_soc , address = server_soc.accept()
client_soc.send('Hello Client'.encode())
while True:
    data = client_soc.recv(1024).decode()
    print("Client: ",data)
    message = input("Enter message: ")
    if data  == "quit":
        client_soc.send("Bye client!".encode())
        client_soc.close()
        break
    else:
        client_soc.send(message.encode())
    
