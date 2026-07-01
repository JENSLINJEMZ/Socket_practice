import socket
import threading
server_soc = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

host = "0.0.0.0"
port = 4444
connected_clients = 0
lock = threading.Lock()
server_soc.bind((host,port))
server_soc.listen(5)

print(f"[*] Server listening on {host}:{port}")
print(f"[*] Waiting for connections...")
def server():
    global connected_clients
    try:
        while True:
            client_soc , address = server_soc.accept()
            with lock:
                connected_clients += 1
            print(f"Connected from {address[0]}:{address[1]}")
            print(f"Clients count:{connected_clients}")
            client_soc.send('Hello Client'.encode())
            while True:
                data = client_soc.recv(1024).decode()
                print(f"Client {address[0]}:{address[1]}: ",data)
                if not data:
                    with lock:
                        connected_clients -=1
                        print(f"{address[0]} Disconected")
                        print(f"Clients count:{connected_clients}")
                        client_soc.close()
                        break
                if data.lower() == "quit":
                    with lock:
                        if connected_clients == 1:
                            print("Last client requested shutdown.")
                            client_soc.send(b"Server shutting down...")
                            connected_clients -= 1
                            print(f"Clients count:{connected_clients}")
                            client_soc.close()
                            server_soc.close()
                            return
                        else:
                            print(f"{address[0]} disconnected.")
                            connected_clients -= 1
                            client_soc.send(b"Bye client!")
                            print(f"Clients count:{connected_clients}")
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
