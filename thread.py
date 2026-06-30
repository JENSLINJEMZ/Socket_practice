import threading
import time 
def test():
    for i in range(5):
        print(f"start {i}")
        time.sleep(0.5)
        print(f"end {i}")
        time.sleep(0.5)

threads = []
for i in range(3):
    t = threading.Thread(target=test)
    threads.append(t)
    t.start()

for t in threads:
    t.join()