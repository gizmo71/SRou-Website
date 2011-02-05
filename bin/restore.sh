#!/bin/sh

if [ $# != 1 ]; then
	echo "Usage: $0 DBSUFFIX"
	exit 1;
fi

SHARED_OPTIONS="--user=gizmo71_smf --password=r0manf0rum"

{
	ls -1 hostican_$1_schema.sql && ls -1 hostican_$1_routines.sql && ls -1 hostican_$1*_data.sql || exit 1
} | while read data; do
	mysql $SHARED_OPTIONS gizmo71_$1 --execute="\\. $data" || break
done