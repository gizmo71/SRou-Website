set -e

cd $(dirname $0)/..
if [ ! -d .git ]; then
    echo "Not being run from direct subdirectory of installation"
    exit 1
fi

test ! -d /etc/httpd/conf.d || eval $(grep -E 'SetEnv\s+SROU_' $(grep -l "SetEnv SROU_ROOT $(pwd)" /etc/httpd/conf.d/*.conf) |
    sed -re 's/^\s+SetEnv\s+//' |
    while read name value; do echo $name=$value; done
)
set | grep SROU

SMF_LOGIN="--user=${SROU_DB_PREFIX}smf --password=$(cat cfg/smf-db.password)"
MIGRATE_LOGIN=$(cat cfg/migrate-login.options)
SHARED_OPTIONS="--host=${SROU_DB_HOST} --batch"
