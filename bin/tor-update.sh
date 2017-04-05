#!/bin/bash -x

# The exit list is disabled - use https://svn.torproject.org/svn/check/trunk/cgi-bin/TorBulkExitList.py
exit 0

{
echo "UPDATE smf_proxy_exit_ips SET proxy_name = 'Tor-old' WHERE proxy_name = 'Tor';"
wget -t 1 -T 5 -O - "http://check.torproject.org/cgi-bin/TorBulkExitList.py?ip=$(gethostip -d davegymer.com)" | tee /dev/fd/2 | grep -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' | while read ip; do
        echo "REPLACE smf_proxy_exit_ips (proxy_exit_ip, proxy_name) VALUES ('$ip', 'Tor');"
done
echo "DELETE FROM smf_proxy_exit_ips WHERE proxy_name = 'Tor-old';"
} | mysql --user=gizmo71_smf --password=r0manf0rum gizmo71_smf
