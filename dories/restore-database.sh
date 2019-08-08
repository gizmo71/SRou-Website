#!/bin/zsh -xe

. ./common.sh

(
	for db in smf lm2 ukgpl views; do
		cat <<-EOF
			PURGE BINARY LOGS BEFORE (NOW() - INTERVAL 5 MINUTE);
			DROP DATABASE IF EXISTS gizmo71_$db;
			CREATE DATABASE gizmo71_$db DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
			GRANT ALL ON gizmo71_$db.* TO 'gizmo71_smf'@'%';
		EOF
	done
	# Really, we should ensure no single table dump is too big for a single packet. But just in case...
    echo 'set global max_allowed_packet = 154857600;'
) | mysql ${=SHARED_OPTIONS} ${=MIGRATE_LOGIN}

SSH_DATADUMP="ssh -l gizmo plaice.aquarium.davegymer.org"
for type in 0 1 2 3 4 5; do for db in smf lm2 ukgpl views; do
	sleep 2 # Give replication a chance to work
	sort =(${=SSH_DATADUMP} "ls -1 /var/backup/mysql/srou-booby/mysql/gizmo71_${db}-${type}_*.sql.gz") | while read sql; do
		echo "** Processing $(basename $sql)..."
		DB_HOST="--host ${SROU_DB_HOST}"
		${=SSH_DATADUMP} "zcat $sql" </dev/null | sed --regexp-extended -e "s/(DEFAULT CHARSET=|CHARACTER SET )latin1([; ])/\1utf8\2/g" \
	-e "s/XX(!50001 CREATE ALGORITHM=\S+\s+)/Ignore \1 - stupid bugs in dump and restore of views /g" \
	-e "s/XX(!50013 DEFINER=\S+@\S+ SQL SECURITY INVOKER)/Ignore \1 - user isn't local /g" \
			-e "s%https?://(www\.)simracing\.org\.uk%https://${SROU_HOST_WWW}%g" \
			-e "s%https?://replays\.simracing\.org\.uk%https://${SROU_HOST_REPLAY}%g" \
			-e "s%https?://downloads\.simracing\.org\.uk%https://${SROU_HOST_WWW}/downloads%g" \
			-e "s%https?://(www\.)?ukgpl\.com%https://${SROU_HOST_UKGPL}%g" |
			mysql ${=SHARED_OPTIONS} ${=MIGRATE_LOGIN} ${=DB_HOST} gizmo71_${db}
		(
			echo "FLUSH LOGS;"
			echo "PURGE BINARY LOGS BEFORE (NOW() - INTERVAL 1 MINUTE);"
		) | mysql ${=SHARED_OPTIONS} ${=MIGRATE_LOGIN} ${=DB_HOST}
	done
done; done

if [ ${SROU_HOST_WWW} = wwwqa.simracing.org.uk ]; then
(
	echo "UPDATE smf_settings SET value = '1' WHERE smf_settings.variable = 'enableErrorLogging';"
	echo "UPDATE smf_settings SET value = CONCAT('SMF1 on the Dories in $SROU_ROOT', CHAR(10), value) WHERE variable = 'news';"
	echo "UPDATE smf_members SET realName = REPLACE(REVERSE(realName), ';930#&', '&#039;'), hideEmail = 0, emailAddress =
		CASE id_member WHEN 2 THEN 'micra.geo@yahoo.com' WHEN 3 THEN 'dgymer23@ford.com' ELSE 'smf2test@simracing.org.uk' END;"
) | mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} gizmo71_smf
fi
