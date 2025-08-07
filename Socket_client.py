import socket
# 1. Import the socket library
# 2. Create a socket object (TCP)
client_socket = socket.socket()
Host = "172.27.240.1"
Port = 2006
# 3. Connect to server at 'localhost' and port 12345
client_socket.connect((Host,Port))
# 4. Start an infinite loop:
while True:
    data = input("You: ")
#   Ask for input from user (client message)
#   – Send the message to the server using .send()
    client_socket.send(data.encode())
#   – Receive the reply from server using .recv()
    server_data = client_socket.recv(1024).decode()
#   – Decode and print the reply
    print(f"Server: {server_data}")
# 5. Close the connection using .close()
client_socket.close()