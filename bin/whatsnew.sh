#!/bin/zsh
cd ~
find bin public* \
	\( -name error_log -o -name .ftpquota \
	-o -wholename public_html.srou/www/smf/attachments \
	-o -wholename public_ftp/backup \
	-o -wholename public_html.davegymer.org/gallery/cpg15x/logs \
	-o -wholename public_html.srou/www/mkportal/cache/\*.rss \
	-o -name .svn \
	\) -prune -o \( -type f -mtime -8 -printf "%T+\t%p\n" \) | sort
