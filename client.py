import socket

ip = "0.0.0.0"
port = 4444

client = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

client.connect((ip,port))



while True:
    data = client.recv(1024).decode()
    print("server",data)
    message = input("Enter message: ")
    if not data or message  == "quit":
        client.send(message.encode())
        client.close()
        break
    else:
        client.send(message.encode())