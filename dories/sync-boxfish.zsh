#!/bin/zsh -ex

#unsetopt NOMATCH

cd $HOME/boxfish
COMMON_OPTS="-rltgoi --delete --exclude=.svn --exclude=error_log --exclude=*~"
cat <<EOF | while read dir args; do
public_html.srou --exclude=*/smf/Packages/backups --exclude=smf2*
public_html.ukgpl
EOF
  rsync ${=COMMON_OPTS} ${=args} gizmo71@boxfish.aquarium.davegymer.org:${dir} $HOME/boxfish || exit 1
  sleep 1
done
