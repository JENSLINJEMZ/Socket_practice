from datetime import datetime
import socket

now = datetime.now()
time = now.strftime("%I:%M:%S %P")
server_soc = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
host = "0.0.0.0"
port = 4444
server_soc.bind((host,port))
server_soc.listen(1)

client , address = server_soc.accept()
client.send(f"{time}".encode())
server_soc.close()
client.close()
