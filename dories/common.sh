set -e

cd $(dirname $0)/..
if [ ! -d .git ]; then
    echo "Not being run from direct subdirectory of installation"
    exit 1
fi

eval $(grep -E 'SetEnv\s+SROU_' $(grep -l "SetEnv SROU_ROOT $(pwd)" /etc/httpd/conf.d/7[12]*.conf) |
    sed -re 's/\s+SetEnv\s+//' |
    while read name value; do echo $name=$value; done
)
set | grep SROU

SMF_LOGIN="--user=${SROU_DB_PREFIX}smf --password=${SROU_DB_PASSWD}"
MIGRATE_LOGIN="--user=smf2srou --password=m1great"
SHARED_OPTIONS="--host=mysql --batch $LOGIN_OPTIONS"
