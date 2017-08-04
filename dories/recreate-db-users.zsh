#!/bin/zsh

. ./common.sh

(
	# Percona: SET GLOBAL validate_password_policy = 'LOW';
	# and on the end of create user: PASSWORD EXPIRE NEVER
	# then afterwards: SET GLOBAL validate_password_policy = 'MEDIUM';
	cat <<-EOF
		DROP USER IF EXISTS 'gizmo71_smf'@'%', 'gizmo71_backup'@'%';
		DROP USER IF EXISTS '${SROU_DB_PREFIX}smf'@'%', '${SROU_DB_PREFIX}backup'@'%';
		CREATE USER '${SROU_DB_PREFIX}smf'@'%' IDENTIFIED BY '${SROU_DB_PASSWD}';
	EOF
) | mysql ${=SHARED_OPTIONS} ${=MIGRATE_LOGIN}
# No longer need '${SROU_DB_PREFIX}backup'@'%' IDENTIFIED BY 'ju5t1nca5e'
