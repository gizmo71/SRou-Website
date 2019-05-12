set -e
set -x

cd $(dirname $0)/..
if [ ! -d .git ]; then
    echo "Not being run from direct subdirectory of installation"
    exit 1
fi

#eval $(grep -E 'SetEnv\s+SROU_' $(grep -l "SetEnv SROU_ROOT $(pwd)" /etc/httpd/conf.d/*.conf) |
#    sed -re 's/^\s+SetEnv\s+//' |
#    while read name value; do echo $name=$value; done
#)
#set | grep SROU

SMF_LOGIN="--user=${SROU_DB_PREFIX}smf --password=$(cat cfg/smf-db.password)"
MIGRATE_LOGIN="--user=root --password=${MYSQL_ROOT_PASSWORD}"
SHARED_OPTIONS="--host=${SROU_DB_HOST} --batch"
