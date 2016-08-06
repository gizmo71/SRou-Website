# .bash_profile

# Get the aliases and functions
if [ -f ~/.bashrc ]; then
	. ~/.bashrc
fi

# User specific environment and startup programs

PATH=$PATH:$HOME/bin
TZ=GMT0BST
SHELL=/bin/bash

export PATH
export TZ

~/bin/whatsnew.sh || echo "Can't run whatsnew.sh"
