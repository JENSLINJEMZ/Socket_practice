from datetime import datetime
import socket
import time
server_soc = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
host = "0.0.0.0"
port = 4444
server_soc.bind((host,port))
server_soc.listen(1)

client , address = server_soc.accept()

while True:
    tim = datetime.now().strftime("%I:%M:%S %P")
    client.send((tim + "\r").encode())
    time.sleep(1)
server_soc.close()
client.close()


