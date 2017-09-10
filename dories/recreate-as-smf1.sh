#!/bin/zsh

. ./common.sh

if [ $(git rev-parse --abbrev-ref HEAD) != master ]; then
	echo "You MUST be on the master branch (preferably with no local changes) before recreating SMF1."
	exit 1
fi

(
	for db in smf lm2 ukgpl views; do
		cat <<-EOF
			DROP DATABASE IF EXISTS ${SROU_DB_PREFIX}$db;
			CREATE DATABASE ${SROU_DB_PREFIX}$db DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
			GRANT ALL ON ${SROU_DB_PREFIX}$db.* TO '${SROU_DB_PREFIX}smf'@'%';
		EOF
	done
) | mysql ${=SHARED_OPTIONS} ${=MIGRATE_LOGIN}

for type in 0 1 2; do for db in smf lm2 ukgpl views; do
	sleep 2 # Give replication a chance to work
	sort =(ssh boxfish "ls -1 /var/backup/boxfish/boxfish_${db}_${type}_*.sql.gz") | while read sql; do
		echo "** Processing $(basename $sql)..."
		DB_HOST="--host mysql"
		ssh boxfish "zcat $sql" </dev/null | sed --regexp-extended -e "s/gizmo71_(smf|lm2)/${SROU_DB_PREFIX}\1/g" \
			-e "s/(DEFAULT CHARSET=|CHARACTER SET )latin1([; ])/\1utf8\2/g" \
			-e "s%https?://(www\.)simracing\.org\.uk%https://${SROU_HOST_WWW}%g" \
			-e "s%https?://replays\.simracing\.org\.uk%https://${SROU_HOST_REPLAY}%g" \
			-e "s%https?://downloads\.simracing\.org\.uk%https://${SROU_HOST_WWW}/downloads%g" \
			-e "s%https?://(www\.)?ukgpl\.com%https://${SROU_HOST_UKGPL}%g" |
			mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} ${=DB_HOST} ${SROU_DB_PREFIX}${db}
		echo "FLUSH LOGS;" | mysql ${=SHARED_OPTIONS} ${=MIGRATE_LOGIN} ${=DB_HOST}
	done
done; done

(
	mysqlshow ${=SHARED_OPTIONS/--batch/} ${=SMF_LOGIN} ${SROU_DB_PREFIX}smf "mkp*" | grep mkp_ | cut -d' ' -f2 | grep -v mkp_pages | while read mkp; do
		echo "DROP TABLE $mkp;"
	done
	ROOT_PATH_RE='^/.*(/public_html\.(?:srou|ukgpl).*)$'
	for table in settings themes; do
		echo -E "UPDATE smf_$table SET value = REGEXP_REPLACE(value, '$ROOT_PATH_RE', '${SROU_ROOT}\\\\1') WHERE value REGEXP '$ROOT_PATH_RE';"
	done
#TODO: remove these when we do it for real
	echo "UPDATE smf_settings SET value = CONCAT('SMF1 on the Dories in $SROU_ROOT', CHAR(10), value) WHERE variable = 'news';"
	echo "UPDATE smf_members SET realName = REVERSE(realName), hideEmail = 0, emailAddress =
		CASE id_member WHEN 2 THEN 'micra.geo@yahoo.com' WHEN 3 THEN 'dgymer23@ford.com' ELSE 'smf2test@simracing.org.uk' END;"
) | mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} ${SROU_DB_PREFIX}smf

rm -rf www public_html.ukgpl
rm -rf www public_html.srou && mkdir public_html.srou

cd public_html.srou

# --exclude="500*"
(cd $HOME/boxfish/public_html.srou && tar -c -f - --exclude="smf/Packages/backups/*.tar.gz" --exclude='mkportal/cache/*.rss' www) | tar xvf -
(cd $HOME/boxfish/public_html.srou && tar -c -f - --exclude="*/*.zip" replays) | tar xvf -
(cd $HOME/boxfish/public_html.srou && tar -c -f - downloads) | tar xvf -

cd www/smf

#sed <$HOME/boxfish/public_html.srou/www/smf/Settings.php >Settings.php \
#    -e s"/maintenance = 0/maintenance = 1/" \
# Get annoying warnings with PHP 5.5 and above. Should be fixed in SMF 2.1.
for file in index SSI; do
#	sed <$HOME/boxfish/public_html.srou/www/smf/${file}.php >${file}.php -e s"/E_ALL/E_ALL \& ~E_DEPRECATED \& ~E_NOTICE/"
done

cd $SROU_ROOT
(cd $HOME/boxfish && tar -c -f - public_html.ukgpl) | tar xvf -

git status

cat <<EOF

Now run prepare-smf2.sh.

EOF
