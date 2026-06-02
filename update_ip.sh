#!/bin/sh
# Wait for network and default route to get established on boot
for i in $(seq 1 30); do
    IP=$(ip route | grep default | awk '{print $3}')
    if [ ! -z "$IP" ]; then
        echo "$IP" > /home/fody/moodle-4.5.11/moodle-4.5.11/local/competency_report/host_ip.txt
        chown fody:fody /home/fody/moodle-4.5.11/moodle-4.5.11/local/competency_report/host_ip.txt
        exit 0
    fi
    sleep 1
done
