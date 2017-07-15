#!/bin/zsh

. ./common.sh

(
	# Percona: SET GLOBAL validate_password_policy = 'LOW';
	# and on the end of create user: PASSWORD EXPIRE NEVER
	# then afterwards: SET GLOBAL validate_password_policy = 'MEDIUM';
	cat <<-EOF
		DROP USER IF EXISTS 'gizmo71_smf'@'%', 'gizmo71_backup'@'%';
		CREATE USER 'gizmo71_smf'@'%'    IDENTIFIED BY '${SROU_DB_PASSWD}'
	                  , 'gizmo71_backup'@'%' IDENTIFIED BY 'ju5t1nca5e';
	EOF
) | mysql ${=SHARED_OPTIONS} ${=MIGRATE_LOGIN}
