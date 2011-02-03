#!/bin/sh

echo "Content-Type: text/plain"
echo

exec 2>&1

set -x

cd ~/public_ftp/backup || exit 1

SHARED_OPTIONS="--user=gizmo71_backup --password=ju5t1nca5e"
BIG_SMF_TABLES="messages topics personal_messages pm_recipients"
for db in lm2 smf ukgpl cpg; do
	EXTRA=
	if [ $db = smf ]; then
		for table in $BIG_SMF_TABLES; do EXTRA="$EXTRA --ignore-table=gizmo71_smf.smf_${table}"; done 
	fi
	mysqldump --no-data --opt --disable-keys $SHARED_OPTIONS gizmo71_$db | gzip -9v >arvixe_${db}_schema.sql.gz
	mysqldump --routines --no-create_info --no-data $SHARED_OPTIONS gizmo71_$db | sed -e "s/DELIMITER ;;/DELIMITER \$\$/g" | gzip -9v >arvixe_${db}_routines.sql.gz
	mysqldump --no-create_info --opt --disable-keys --single_transaction $SHARED_OPTIONS $EXTRA gizmo71_$db | gzip -9v >arvixe_${db}_data.sql.gz
done

for table in $BIG_SMF_TABLES; do
	if [ $table = messages ]; then
		rm arvixe_smf_messages_*-*_data.sql.gz
		chunk_size=10000
		absolute_max_id=2000000
		max_id=${chunk_size}
		while [ $max_id -le $absolute_max_id ]; do
			min_id=$[max_id-$chunk_size+1]
			dumpname="$(printf 'arvixe_smf_%s_%07d-%07d_data.sql.gz' "${table}" ${min_id} ${max_id})"
			mysqldump --no-create_info --opt --disable-keys $SHARED_OPTIONS --where="id_msg BETWEEN $min_id AND $max_id" gizmo71_smf smf_${table} | gzip -9v >$dumpname
			max_id=$[$max_id+$chunk_size]
			if [ $(du -b $dumpname | cut -f1) -lt 1000 ]; then
				break
			fi
		done
	else
		mysqldump --no-create_info --opt --disable-keys $SHARED_OPTIONS gizmo71_smf smf_${table} | gzip -9v >arvixe_smf_${table}_data.sql.gz
	fi
done

ls -l

#gzip -dNvf gplshared*.sql.gz
#gzip -dNvf ukgpl*.sql.gz

date

#SHARED_OPTIONS="--user=gizmo71_gizmo71 --password=haddock69 -D gizmo71_ukgpl"
#echo "Importing shared structure..."
#mysql -B $SHARED_OPTIONS -e "source gplshared-structure.sql"
#echo "Importing shared data..."
#mysql -B $SHARED_OPTIONS -e "source gplshared-data.sql"
#echo "Importing UKGPL structure..."
#mysql -B $SHARED_OPTIONS -e "source ukgpl-structure.sql"
#echo "Importing UKGPL structure..."
#mysql -B $SHARED_OPTIONS -e "source ukgpl-data.sql"

echo "$(date): all done"

#gzip -9v gplshared*.sql
#gzip -9v ukgpl*.sql