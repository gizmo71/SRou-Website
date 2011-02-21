#!/bin/bash

{
echo "UPDATE smf_proxy_exit_ips SET proxy_name = 'Tor-old' WHERE proxy_name = 'Tor';"
wget -O - "http://check.torproject.org/cgi-bin/TorBulkExitList.py?ip=$(gethostip -d davegymer.com)" | grep -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' | while read ip; do
        echo "REPLACE smf_proxy_exit_ips (proxy_exit_ip, proxy_name) VALUES ('$ip', 'Tor');"
done
echo "DELETE FROM smf_proxy_exit_ips WHERE proxy_name = 'Tor-old';"
} | mysql --user=gizmo71_smf --password=r0manf0rum gizmo71_smf
