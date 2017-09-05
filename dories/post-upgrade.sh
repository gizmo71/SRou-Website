#!/bin/zsh

. ./common.sh

# Gets nobbled by the upgrade script.
cat <<EOF | mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} -vvv ${SROU_DB_PREFIX}smf
UPDATE smf_themes SET value = replace(value, '/core', '/default');
REPLACE INTO smf_themes (id_member, id_theme, variable, value) VALUES
-- SRou
	(0, 4, 'header_logo_url', '/images/srou-pops.gif'),
	(0, 4, 'srou_home', '/'),
-- UKGTR
	(0, 3, 'header_logo_url', '/images/ukgtr-gvw.jpg'),
	(0, 3, 'srou_home', '/smf/index.php?action=LM2R&group=7&board=4'),
-- UKGTL
	(0, 5, 'header_logo_url', '/images/ukgtl-shark.gif'),
	(0, 5, 'srou_home', '/smf/index.php?action=LM2R&group=13&board=19'),
-- UKiR
	(0, 33, 'header_logo_url', '/smf/Themes/ukir/ukir.jpg'),
	(0, 33, 'srou_home', '/smf/index.php?action=LM2R&group=229&board=71'),
-- UKPnG
	(0, 34, 'header_logo_url', '/smf/Themes/ukpng/ukpng.jpg'),
	(0, 34, 'srou_home', '/smf/index.php?action=LM2R&group=213&board=69'),
-- UKGPL
	(0, 6, 'header_logo_url', '//www.ukgpl.com/images/ukgpl.jpg'),
	(0, 6, 'srou_home', '//${SROU_HOST_UKGPL}');
UPDATE smf_boards JOIN _map_board_themes ON id_board = board SET id_theme = theme;
UPDATE smf_settings SET value = '1,3,4,5,6,33,34' WHERE variable IN ('enableThemes', 'knownThemes');
REPLACE INTO smf_settings (variable, value) VALUES
	('theme_guests', '4'),
	('minimize_files', '0'),
	('force_ssl', '2'),
	('subject_toggle', '1');
EOF

git status
