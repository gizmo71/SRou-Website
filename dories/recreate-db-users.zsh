#!/bin/zsh -xe

. ./common.sh

(
	# Percona: SET GLOBAL validate_password_policy = 'LOW';
	# and on the end of create user: PASSWORD EXPIRE NEVER
	# then afterwards: SET GLOBAL validate_password_policy = 'MEDIUM';
	cat <<-EOF
		DROP USER IF EXISTS '${SROU_DB_PREFIX}smf'@'%';
		CREATE USER '${SROU_DB_PREFIX}smf'@'%' IDENTIFIED BY '$(cat cfg/smf-db.password)';
	EOF
) | mysql ${=SHARED_OPTIONS} ${=MIGRATE_LOGIN}
