#!/bin/zsh -e

. ./common.sh

# We can't simply convert each table as a whole because of foreign key constraints.

cat <<EOF | mysql ${=SHARED_OPTIONS} ${=SMF_LOGIN} -vvv ${SROU_DB_PREFIX}lm2
ALTER DATABASE ${SROU_DB_PREFIX}lm2 CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE lm2_circuit_locations
	CHANGE brief_name brief_name VARCHAR(16) CHARACTER SET utf8 NOT NULL,
	CHANGE full_name full_name VARCHAR(64) CHARACTER SET utf8 NOT NULL;
ALTER TABLE lm2_circuits
	CHANGE layout_notes layout_notes VARCHAR(64) CHARACTER SET utf8 DEFAULT NULL COMMENT 'To help distinguish specific layouts';
EOF

echo "TODO:
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
lm2_sim_cars vehicle team number? file upgrade_code? notes
lm2_sim_circuits sim_name
lm2_sim_drivers driving_name lobby_name
lm2_sim_mods mod_desc
lm2_sims sim_name sim_name_short
lm2_team_drivers audit_who audit_what
lm2_teams team_name url created_by
lm2_tyres tyre_description url
lm2_wu_conditions condition_text
"
