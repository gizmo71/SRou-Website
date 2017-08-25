#!/bin/zsh

. ./common.sh

# We can't simply convert each table as a whole because of foreign key constraints.

cat <<EOF | mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} -vvv ${SROU_DB_PREFIX}lm2
ALTER DATABASE ${SROU_DB_PREFIX}lm2 CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE lm2_circuit_locations
	CHANGE brief_name brief_name VARCHAR(16) CHARACTER SET utf8 NOT NULL,
	CHANGE full_name full_name VARCHAR(64) CHARACTER SET utf8 NOT NULL;
EOF
