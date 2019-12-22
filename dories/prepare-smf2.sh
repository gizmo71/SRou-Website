#!/bin/zsh

. ./common.sh

cat <<EOF | mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} ${SROU_DB_PREFIX}smf
delete from smf_themes where id_member > 0;
delete from smf_themes where variable = 'header_logo_url' and id_theme in (4, 5, 6, 33, 34, 3);
delete from smf_log_banned;
delete from smf_log_actions;
delete from smf_log_errors;
delete from smf_log_floodcontrol;
delete from smf_log_httpBL;
delete from smf_log_online;
delete from smf_log_search_subjects;
INSERT IGNORE INTO smf_log_search_subjects (word, ID_TOPIC) VALUES ('fake', '0');
delete from smf_log_search_results;
delete from smf_log_topics;
delete from smf_sessions;
CREATE OR REPLACE TABLE _map_board_themes SELECT ID_BOARD AS board, ID_THEME AS theme FROM smf_boards;
UPDATE smf_members SET birthdate = '0001-01-01' WHERE birthdate LIKE '%-00' OR birthdate LIKE '%-00-%';
EOF

cd public_html.srou/www/smf

if false; then
	wget -O - https://download.simplemachines.org/index.php/smf_2-1-rc2_upgrade.tar.bz2 | bzip2 -d | tar xvf -
else
	(cd ~/SMF2.1 && tar c -f - --exclude ".*" .) | tar xvf -
	mv -v other/upgrade* .
	rm -rfv agreement.txt DCO.txt *.ico other
	
fi

rm -rf Packages
mkdir Packages
touch Packages/installed.list
cp -v ~/smf-mods/srou-smf-*.tar.gz Packages/

touch db_last_error.php

chmod 0755 .

git checkout smf2

for themedir in $(find Themes/* -type d -maxdepth 0) ../../../public_html.ukgpl/smf-theme; do
	mkdir -pv $themedir/scripts
done

#php -f ./upgrade.php -- --debug --no-maintenance

cat <<EOF

Now run the upgrade script, then login and install the prefix mod. Then run post-upgrade.sh.

EOF
