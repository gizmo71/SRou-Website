#!/bin/zsh -xe

if [ $(git rev-parse --abbrev-ref HEAD) != master ]; then
	echo "You MUST be on the master branch (preferably with no local changes) before recreating SMF1."
	exit 1
fi

. ./restore-database.sh

echo 'TODO: not from Boxfish, but from SRouProd.'
exit 1

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
