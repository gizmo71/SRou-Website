update lm2_drivers
set gtr2_name = (select driving_name from 
lm2_sim_drivers, lm2_event_entries, lm2_events
where lm2_drivers.driver_member = lm2_event_entries.member
and lm2_sim_drivers.sim = 4
and id_event = event
and id_sim_drivers = sim_driver
order by event_date DESC
LIMIT 1)