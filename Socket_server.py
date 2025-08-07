# 1. Import the socket library

# 2. Create a socket object (TCP)

# 3. Bind the socket to 'localhost' and port 12345

# 4. Set the socket to listen for 1 connection

# 5. Print "Server listening..."

# Wait for a client to connect using .accept()
# Print "Connected to ..." with client address

# 6. Start an infinite loop:
    # Receive data from client using .recv()
    # Decode and print the message
    # If no data received, break the loop
    # Ask for server input using input()
    # Send the input back to the client using .send()

# 7. Close the connection using .close()