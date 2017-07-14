#!/bin/zsh

. ./common.sh

if [ $(git rev-parse --abbrev-ref HEAD) != master ]; then
    echo "You MUST be on the master branch (preferably with no local changes) before recreating SMF1."
    exit 1
fi

(
	# Percona: SET GLOBAL validate_password_policy = 'LOW';
	# and on the end of create user: PASSWORD EXPIRE NEVER
	# then afterwards: SET GLOBAL validate_password_policy = 'MEDIUM';
	cat <<-EOF
		DROP USER IF EXISTS 'gizmo71_smf'@'%', 'gizmo71_backup'@'%';
		CREATE USER 'gizmo71_smf'@'%'    IDENTIFIED BY '${SROU_DB_PASSWD}'
	                  , 'gizmo71_backup'@'%' IDENTIFIED BY 'ju5t1nca5e';
	EOF

	for db in smf lm2 ukgpl views; do
		cat <<-EOF
			DROP DATABASE IF EXISTS ${SROU_DB_PREFIX}$db;
			CREATE DATABASE ${SROU_DB_PREFIX}$db DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
			GRANT ALL ON ${SROU_DB_PREFIX}$db.* TO 'gizmo71_smf'@'%';
			GRANT SELECT, LOCK TABLES, SHOW VIEW, CREATE TEMPORARY TABLES ON ${SROU_DB_PREFIX}$db.* TO 'gizmo71_backup'@'%';
		EOF
	done
) | mysql ${=SHARED_OPTIONS} ${=MIGRATE_LOGIN}

DB_HOSTS=(plaice skate)
for type in 0 1 2; do for db in smf lm2 ukgpl views; do
	sleep 2 # Give replication a chance to work
	sort =(ssh boxfish "ls -1 /var/backup/boxfish/boxfish_${db}_${type}_*.sql.gz") | while read sql; do
		echo "** Processing $(basename $sql)..."
		DB_HOST="--host $DB_HOSTS[$(($RANDOM % $#DB_HOSTS + 1))]" # Alternate(ish) to avoid filling binary log on one server.
		ssh boxfish "zcat $sql" </dev/null | sed -e "s/gizmo71_\(smf\|lm2\)/${SROU_DB_PREFIX}\1/g" |
		    mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} ${=DB_HOST} ${SROU_DB_PREFIX}${db}
		echo "FLUSH LOGS;" | mysql ${=SHARED_OPTIONS} ${=MIGRATE_LOGIN} ${=DB_HOST}
	done
done; done

(
	mysqlshow ${=SHARED_OPTIONS/--batch/} ${=SMF_LOGIN} ${SROU_DB_PREFIX}smf "mkp*" | grep mkp_ | cut -d' ' -f2 | grep -v mkp_pages | while read mkp; do
		echo "DROP TABLE $mkp;"
	done
#TODO: remove this when we do it for real
	echo "UPDATE smf_settings SET value = CONCAT('SMF1 on the Dories', CHAR(10), value) WHERE variable = 'news';"
#TODO: how to do the QA versions?
	for url2s in www.simracing.org.uk www.ukgpl.com; do
		for table in settings themes; do
			echo "UPDATE smf_$table SET value = REPLACE(value, 'http://$url2s', 'https://$url2s') WHERE value LIKE '%http://$url2s%';"
		done
	done
#TODO: at some point we'll need a better way to identify the source folder...
	echo "UPDATE smf_themes SET value = REPLACE(value, '/home/gizmo71/', '${SROU_ROOT}/') WHERE variable = 'theme_dir';"
) | mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} ${SROU_DB_PREFIX}smf

rm -rf www public_html.ukgpl
rm -rf www public_html.srou && mkdir public_html.srou

cd public_html.srou

# --exclude="500*"
(cd $HOME/boxfish/public_html.srou && tar -c -f - --exclude="smf/Packages/backups/*.tar.gz" --exclude='mkportal/cache/*.rss' www) | tar xvf -
(cd $HOME/boxfish/public_html.srou && tar -c -f - --exclude="*/*.zip" replays) | tar xvf -
(cd $HOME/boxfish/public_html.srou && tar -c -f - downloads) | tar xvf -
(cd $HOME/boxfish && tar -c -f - public_html.ukgpl) | (cd $HOME && tar xvf -)

cd www/smf

#sed <$HOME/boxfish/public_html.srou/www/smf/Settings.php >Settings.php \
#    -e s"/maintenance = 0/maintenance = 1/" \
# Get annoying warnings with PHP 5.5 and above. Should be fixed in SMF 2.1.
for file in index SSI; do
#	sed <$HOME/boxfish/public_html.srou/www/smf/${file}.php >${file}.php -e s"/E_ALL/E_ALL \& ~E_DEPRECATED \& ~E_NOTICE/"
done

cd $SROU_ROOT

git status

cat <<EOF

Now run prepare-smf2.sh.

EOF
