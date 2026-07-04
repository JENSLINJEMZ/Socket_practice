import socket


ip = "127.0.0.1"
for port in range(100):
    client = socket.socket(socket.AF_INET,socket.SOCK_STREAM)
    result = client.connect_ex((ip,port))
    if result == 0:
        print(f"Port {port} is running")
        if port == 22:
            print("SSH detected try to  make a access or try to make a privlaage exclusion")