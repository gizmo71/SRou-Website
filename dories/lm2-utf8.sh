#!/bin/zsh

. ./common.sh

MYSQL="mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} ${SROU_DB_PREFIX}lm2"

# We can't simply convert each table as a whole because of foreign key constraints.

(
COLLATION=utf8_general_ci
cat <<EOF
ALTER DATABASE ${SROU_DB_PREFIX}lm2 CHARACTER SET utf8 COLLATE ${COLLATION};
EOF
cat <<EOF | while read table columns; do
lm2_circuit_locations location_url
lm2_circuits layout_name
lm2_cars car_name
lm2_classes class_description
lm2_driver_details driving_name
lm2_event_ballasts eb_name
lm2_event_groups short_desc long_desc full_desc
lm2_incidents description
lm2_iso3166 iso3166_name
lm2_manufacturers manuf_name manuf_url
lm2_money notes
lm2_penalties description
lm2_penalty_groups penalty_group_desc
lm2_reg_classes description
lm2_registered_drivers name
lm2_reports report_summary
lm2_retirement_reasons reason_desc
lm2_scoring_schemes scoring_scheme_name
lm2_sim_cars vehicle team file notes
lm2_sim_circuits sim_name
lm2_sim_mods mod_desc
lm2_sims sim_name sim_name_short
lm2_team_drivers audit_what
lm2_teams team_name url
lm2_tyres tyre_description url
lm2_wu_conditions condition_text
lm2_circuit_locations brief_name full_name
lm2_circuits layout_notes
COLLATION utf8_bin
lm2_sim_drivers lobby_name driving_name
EOF
	if [ "$table" = "COLLATION" ]; then
		COLLATION=${columns}
		continue;
	fi
	create=$(${=MYSQL} -e "SHOW CREATE TABLE ${table};")
	for column in ${=columns}; do
		echo -n "ALTER TABLE ${table} MODIFY"
		echo ${create} | grep \`${column}\` | head -1 | sed -r -e "s/\s+(CHARACTER SET|COLLATE) \S+//g
s/\s+(UNIQUE|PRIMARY) KEY//
s/ (text|varchar\([0-9]+\))/ \1 CHARACTER SET utf8 COLLATE ${COLLATION}/
s/,$/;/"
	done
done
) | ${=MYSQL} -vvv
