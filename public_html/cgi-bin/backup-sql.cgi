#!/bin/bash -ex

TZ=GMT0BST
export TZ

#echo "Content-Type: text/plain"
#echo

date

# Crontab has to be saved manually as this script cannot find the command! :o
#crontab -l >boxfish-crontab.log || find / -name \*crontab\* -ls 2>/dev/null

#tar -C ~ -c -f - public_html.srou/smf2-code public_html.ukgpl/smf2 | gzip -9v >smf2-code.tgz

#cpg
SHARED_OPTIONS="--user=$1 --password=$2"
cat <<EOF | while read db big_tables
lm2 lm2_circuits= lm2_circuit_locations= lm2_championships= lm2_championship_points= lm2_events= lm2_event_entries=
smf smf_messages=id_msg smf_topics= smf_personal_messages=id_pm smf_pm_recipients=
ukgpl _map_drivers= _map_teams=
views
EOF
do
	rm -f boxfish_${db}_*.sql*
	EXTRA=
	if [ -n "$big_tables" ]; then
		for table in $big_tables; do EXTRA="$EXTRA --ignore-table=gizmo71_${db}.${table/=*/}"; done 
	fi
	mysqldump --no-data --opt --disable-keys $SHARED_OPTIONS gizmo71_${db} | sed -e 's/ DEFINER=`root`@`localhost`//' | gzip -9v >boxfish_${db}_0_schema.sql.gz
# Don't think we need this any more (not that we'got any routines anyway!): | sed -e "s/DELIMITER ;;/DELIMITER \$\$/g"
	mysqldump --routines --no-create_info --no-data $SHARED_OPTIONS gizmo71_${db} | gzip -9v >boxfish_${db}_1_routines.sql.gz
	mysqldump --no-create_info --complete_insert --tz-utc --opt --disable-keys --single_transaction $SHARED_OPTIONS $EXTRA gizmo71_${db} | gzip -9v >boxfish_${db}_2_data.sql.gz
	if [ -n "$big_tables" ]; then
		for table in $big_tables; do
			chunk_key=${table/*=/}
			table=${table/=*/}
			if [ -z "$chunk_key" ]; then
				mysqldump --no-create_info --complete_insert --opt --disable-keys $SHARED_OPTIONS gizmo71_${db} ${table} | gzip -9v >boxfish_${db}_2_${table}_data.sql.gz
			else
				chunk_size=10000
				absolute_max_id=2000000
				max_id=${chunk_size}
				while [ $max_id -le $absolute_max_id ]; do
					min_id=$[max_id-$chunk_size+1]
					dumpname="$(printf 'boxfish_%s_2_%s_%07d-%07d_data.sql.gz' "${db}" "${table}" ${min_id} ${max_id})"
					mysqldump --no-create_info --complete_insert --opt --disable-keys $SHARED_OPTIONS --where="${chunk_key} BETWEEN $min_id AND $max_id" gizmo71_${db} ${table} | gzip -9v >$dumpname
					max_id=$[$max_id+$chunk_size]
					if [ $(du -b $dumpname | cut -f1) -lt 1000 ]; then
						break
					fi
				done
			fi
		done 
	fi
done

ls -l

date

echo "$(date): all done"
