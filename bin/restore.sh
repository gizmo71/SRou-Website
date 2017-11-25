#!/bin/sh -x

if [ $# != 1 ]; then
	echo "Usage: $0 DBSUFFIX"
	exit 1;
fi

#FIXME: get these from dories/common.sh, or just junk this script.
SHARED_OPTIONS="--user=FIXME --password=FIXME"

{
	ls -1 arvixe_$1_schema.sql && ls -1 arvixe_$1_routines.sql && ls -1 arvixe_$1*_data.sql || exit 1
} | while read data; do
	mysql $SHARED_OPTIONS FIXME$1 --execute="\\. $data" || break
done
