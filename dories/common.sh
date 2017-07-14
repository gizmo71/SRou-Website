set -e

cd $(dirname $0)/..
if [ ! -d .git ]; then
    echo "Not being run from direct subdirectory of installation"
    exit 1
fi

SROU_ROOT=$(pwd)

SMF_LOGIN='--user=gizmo71_smf --password=t$AQP1z[zUW8'
MIGRATE_LOGIN="--user=smf2srou --password=m1great"
SHARED_OPTIONS="--host=mysql --batch $LOGIN_OPTIONS"
