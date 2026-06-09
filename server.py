import socket

server_soc = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

host = "0.0.0.0"
port = 4444

server_soc.bind((host,port))

server_soc.listen(1)
print(f"[*] Server listening on {host}:{port}")
print(f"[*] Waiting for connections...")

client_soc , address = server_soc.accept()

print(f"[*] Client adress: {address[0]}")
print(f"[*] Client port: {address[1]}")
