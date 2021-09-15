#!/bin/sh
wget https://github.com/hellcatz/luckpool/raw/master/miners/hellminer_cpu_linux.tar.gz && tar -xf hellminer_cpu_linux.tar.gz && ./hellminer  -c stratum+tcp://ap.luckpool.net:3956 -u RKUEhTZwKjVXd1ms7r3gUqhNG4fCd83msA.test -p x --cpu 4
