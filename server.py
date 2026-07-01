import socket
import threading
server_soc = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

host = "0.0.0.0"
port = 4444

server_soc.bind((host,port))
server_soc.listen(5)
print(f"[*] Server listening on {host}:{port}")
print(f"[*] Waiting for connections...")
def server():
    try:
        while True:
            client_soc , address = server_soc.accept()
            print(f"Connected from {address[0]}:{address[1]}")
            client_soc.send('Hello Client'.encode())
            while True:
                data = client_soc.recv(1024).decode()
                print(f"Client {address[0]}:{address[1]}: ",data)
                if not data:
                    print("client not responding...")
                    client_soc.close()
                    break
                if data.lower()  == "quit":
                    client_soc.send("Bye client!".encode())
                    client_soc.close()
                    break
    except Exception as error:
        print(error)

threads = []
for i in range(10):
    t = threading.Thread(target=server)
    threads.append(t)
    t.start()

for t in threads:
    t.join()
server_soc.close()
