#!/bin/zsh

. ./common.sh

cd public_html.srou/www/smf

if false; then
	wget -O - https://download.simplemachines.org/index.php/smf_2-1-rc2_upgrade.tar.bz2 | bzip2 -d | tar xvf -
elif false; then
	# https://www.simplemachines.org/community/index.php?topic=558451.0:
	wget -O /tmp/smf2.1_nightly_upgrade.zip http://0exclusive.de/smf/smf2.1_nightly_upgrade.zip
	unzip /tmp/smf2.1_nightly_upgrade.zip
else
	(cd ~/SMF2.1 && tar cf - --exclude *) | tar xvf -
	mv -v other/upgrade* .
	rm -rfv DCO.txt *.ico other
fi

rm -rf Packages
mkdir Packages
touch Packages/installed.list
cp -v ~/smf-mods/srou-smf-*.tar.gz Packages/

touch db_last_error.php

chmod 0755 .
