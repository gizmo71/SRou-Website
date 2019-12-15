#!/bin/zsh

. ./common.sh

# Remove the first one when doing this for real!
cat <<EOF | mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} ${SROU_DB_PREFIX}smf
update smf_settings set value = REPLACE(value, 'SMF1 on the ', 'SMF2 on the ') where variable = 'news';
--update smf_settings set value = CONCAT('{"1":"', value, '"}') where variable = 'attachmentUploadDir';
--update smf_boards set name = CONCAT('2_', name);
--update smf_messages set subject = CONCAT('2_', subject);
delete from smf_themes where id_member > 0;
delete from smf_themes where variable = 'header_logo_url' and id_theme in (4, 5, 6, 33, 34, 3);
delete from smf_log_banned;
delete from smf_log_actions;
delete from smf_log_errors;
delete from smf_log_floodcontrol;
delete from smf_log_httpBL;
delete from smf_log_online;
delete from smf_log_search_subjects;
INSERT INTO smf_log_search_subjects (word, ID_TOPIC) VALUES ('fake', '0');
delete from smf_log_search_results;
delete from smf_log_topics;
delete from smf_sessions;
CREATE TABLE _map_board_themes SELECT ID_BOARD AS board, ID_THEME AS theme FROM smf_boards;
UPDATE smf_members SET birthdate = '0001-01-01' WHERE birthdate LIKE '%-00' OR birthdate LIKE '%-00-%';
EOF

cd public_html.srou/www/smf

wget -O - https://download.simplemachines.org/index.php/smf_2-1-rc2_upgrade.tar.bz2 | bzip2 -d | tar xvf -

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
