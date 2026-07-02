import socket
import os
host = "0.0.0.0"
port = 4444
server_soc = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
server_soc.bind((host,port))
server_soc.listen(1)
client , address = server_soc.accept()
client.send("Enter command: ".encode())

command = client.recv(1024).decode().strip()
if command == "exit":
    client.send("Exited...")
    server_soc.close()
    client.close()
elif not command:
    server_soc.close()
    client.close()
elif command == "pwd":
    client.send(os.getcwd().encode())
elif command == "whoami":
    client.send(os.getlogin().encode())
else:
    client.send("Commands: \n1.pwd \n2.whoami \n3.exit".encode())
server_soc.close()
client.close()

