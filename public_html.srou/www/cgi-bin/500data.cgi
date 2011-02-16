#!/bin/sh

TZ=GMT0BST
export TZ

LOG=/home/gizmo71/tmp/500.log

echo "Content-Type: text/plain"
echo

(
echo "--------- $(date) --------"
uptime
export
tail -25 /usr/local/apache/domlogs/gizmo71/srou.davegymer.com
fgrep gizmo71 /usr/local/apache/logs/error_log | tail -25
ls -l /usr/local/apache/domlogs/gizmo71
#ls -l /usr/local/apache/logs /usr/local/apache/domlogs /usr/local/apache
) | tee -a $LOG
