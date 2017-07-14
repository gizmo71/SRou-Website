#!/bin/zsh

. ./common.sh

# Remove the first one when doing this for real!
cat <<EOF | mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} gizmo71_smf
update smf_settings set value = REPLACE(value, 'SMF1 on the ', 'SMF2 on the ') where variable = 'news';
--update smf_boards set name = CONCAT('2_', name);
--update smf_members set realName = CONCAT('2_', realName), emailAddress = 'gymer1971-smf2srou@yahoo.com', hideEmail = 0;
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
EOF

cd public_html.srou/www

mkdir smf.keep
mv -v smf/{Settings.php,avatars,attachments,Smileys} smf.keep/
rm -rf smf
mv -v smf.keep smf
git checkout -- smf

cd smf
(cd $HOME/dories/SMF2.1 && tar  --exclude=.git\* -c -f - .) | tar xvf -

touch Packages/installed.list
cp -v ~/dories/srou-smf-*.zip Packages/

touch db_last_error.php

cd other

# "IGNORE" was Removed from ALTER TABLE in MySQL 5.7.x. :-(
sed -i -e 's/ALTER IGNORE TABLE/ALTER TABLE/' upgrade*.sql
# ... and this...
#sed -i -e 's/\(nextSubStep(\$substep);\)///\1/' upgrade.php
# No way to set this from the command line
#sed -i -e 's/\(initialize_inputs();\)/$GLOBALS["db_type"] = "mysqli"; \1/' upgrade.php
#emacs upgrade_2-0_mysql.sql upgrade.php

rm -v *postgres*.sql
mv -v upgrade* ..

cd ..
rm -rf other
chmod 0755 .

git checkout smf2

#php -f ./upgrade.php -- --debug --no-maintenance

cat <<EOF

Now run the upgrade script, then login and install the prefix mod. Then run post-upgrade.sh.

EOF
