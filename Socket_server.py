# 1. Import the socket library
import socket

# 2. Create a socket object (TCP)

server_socket = socket.socket()
Host = "0.0.0.0"
Port = 2006

# 3. Bind the socket to 'localhost' and port 12345
server_socket.bind((Host,Port))

# 4. Set the socket to listen for 1 connection
server_socket.listen(1)

# 5. Print "Server listening..."
print("Server Listening....")

# Wait for a client to connect using .accept()
conn, addr = server_socket.accept()
# Print "Connected to ..." with client address
print(f"Connected to {addr}")

# 6. Start an infinite loop:
while True:
    data = conn.recv(1024).decode()
    # Receive data from client using .recv()
    # Decode and print the message
    print(f"{addr[0]}: {data}")
    # If no data received, break the loop
    # Ask for server input using input()
    # Send the input back to the client using .send()
    conn.send(input("Server: ").encode())

# 7. Close the connection using .close()
conn.close()