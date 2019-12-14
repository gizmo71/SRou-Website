#!/bin/zsh -e

. ./common.sh

# Gets nobbled by the upgrade script.
#TODO: remove all the theme stuff and put it back in the templates.
cat <<EOF | mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} -vvv ${SROU_DB_PREFIX}smf
UPDATE smf_themes SET value = replace(value, '/core', '/default');
REPLACE INTO smf_themes (id_member, id_theme, variable, value) VALUES
	(0, 4, 'header_logo_url', '/images/srou-pops.gif'),
	(0, 3, 'header_logo_url', '/images/ukgtr-gvw.jpg'),
	(0, 5, 'header_logo_url', '/images/ukgtl-shark.gif'),
	(0, 33, 'header_logo_url', '/smf/Themes/ukir/ukir.jpg'),
	(0, 34, 'header_logo_url', '/smf/Themes/ukpng/ukpng.jpg'),
	(0, 6, 'header_logo_url', '//${SROU_HOST_UKGPL}/images/ukgpl.jpg');
UPDATE smf_boards JOIN _map_board_themes ON id_board = board SET id_theme = theme;
SET @themes = '1,3,4,5,6,33,34';
REPLACE INTO smf_settings (variable, value) VALUES
	('theme_guests', '4'),
	('minimize_files', '0'),
	('force_ssl', '2'),
	('subject_toggle', '1'),
	('enableThemes', @themes),
	('knownThemes', @themes);
EOF

git status
