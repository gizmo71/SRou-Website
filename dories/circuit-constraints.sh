#!/bin/zsh

. ./common.sh

MYSQL="mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} ${SROU_DB_PREFIX}lm2"

cat <<EOF | ${=MYSQL} -vvv
SELECT id_circuit_location, full_name FROM lm2_circuit_locations WHERE latitude_n = 0 AND longitude_e IS NULL;

SHOW CREATE TABLE lm2_sim_circuits;
ALTER TABLE lm2_sim_circuits
	DROP FOREIGN KEY lm2_sim_circuits_ibfk_1;
ALTER TABLE lm2_sim_circuits
	ADD CONSTRAINT lm2_sim_circuits_ibfk_1 FOREIGN KEY (circuit) REFERENCES lm2_circuits (id_circuit) ON UPDATE CASCADE ON DELETE CASCADE;
SHOW CREATE TABLE lm2_sim_circuits;

SHOW CREATE TABLE lm2_circuits;
ALTER TABLE lm2_circuits
	DROP FOREIGN KEY lm2_circuits_ibfk_1;
ALTER TABLE lm2_circuits
	ADD CONSTRAINT lm2_circuits_ibfk_1 FOREIGN KEY circuit_location (circuit_location) REFERENCES lm2_circuit_locations (id_circuit_location) ON UPDATE CASCADE ON DELETE CASCADE;
SHOW CREATE TABLE lm2_circuits;
EOF
