<?php

// The stuff which is removed should run automatically when importing/moderating/adjusting results as appropriate.

class StandingsGenerator {
	var $lm2_db_prefix;
	var $temp_db_prefix;
	var $champ_id_clause;

	var $tables = array(
		"event_points" => array(
			'keys' => array("championship", "id", "event_entry"),
			'data' => array("points", "position", "is_dropped", "ep_penalty_points"),
			'debug' => array(),
			'temp_fields' => array('champ_points_lost' => 'DECIMAL(6,1)', 'tokens' => 'DECIMAL(4,1)')
		),
		"championship_points"=>array(
			'keys' => array("championship", "id"),
			'data' => array("points", "position", "champ_points_lost"),
			'debug' => array("tie_breaker", "tokens", "single_car"),
			'temp_fields' => array()
		),
	);

	var $ukgpls18tokensPredicate = "max_tokens < 0";

	function StandingsGenerator() {
		global $lm2_db_prefix;

		$this->lm2_db_prefix = $lm2_db_prefix;
		$this->temp_db_prefix = "{$this->lm2_db_prefix}TEMP_";
		$this->champ_id_clause = "CASE champ_type WHEN 'T' THEN {$this->lm2_db_prefix}event_entries.team WHEN 'M' THEN manuf ELSE member END";
	}

	function generate_standings() {
		global $guest_member_id;

		$start = microtime_float();
		
		echo "<P>Making temporary points tables...";

		foreach ($this->tables as $table => $fields) {
			lm2_query("
				CREATE TEMPORARY TABLE {$this->temp_db_prefix}$table
				LIKE {$this->lm2_db_prefix}$table
				" , __FILE__, __LINE__);
			foreach ($fields['temp_fields'] as $name => $type) {
				lm2_query("ALTER TABLE {$this->temp_db_prefix}$table ADD ($name $type)", __FILE__, __LINE__);
			}
//XXX: do we need to drop any unwanted indexes?
		}

		$this->update_drivers_and_teams();
		$this->update_penalty_points();

		echo "<P>Generating positions...</P>\n";

		$this->update_cached_exclusion_flags();
		$this->ukgplS18tokens();
		reset_unadjusted_positions(null);
		$this->set_positions_lost();
		$this->set_class_positions();

		$this->calculate_round_numbers();

		// At this point, we've finished doing updates the events and entries and are ready to start on the points.

		echo "<P>Preparing skeleton positions rows...";

		//TODO: is there anything else we can usefully index on?
		global $lm2_penalty_points_clause;
		lm2_query("
			CREATE TEMPORARY TABLE {$this->temp_db_prefix}positions
			(INDEX (id_event_entry), INDEX (event))
			AS SELECT id_event_entry
			, {$this->champ_id_clause} AS id
			, car
			, 0 AS id_rank
			, event
			, id_championship AS championship
			, max_rank
"/*TODO: do we need to do the 'H' thing here? Do any H races /have/ pos penalties? */."
			, IFNULL(IF(event_status <> 'H', race_pos_penalty, NULL), 0) + race_pos AS adjusted_race_pos
			, race_pos AS position
			, IFNULL(rating, 100.0) AS rating
			, race_pos_penalty
			, scoring_type AS scoring_type_c
			, car_change_penalty AS penalty
			, free_car_changes
			, {$this->lm2_db_prefix}events.event_date AS event_date
			, ballast_driver
			, 'you ugly fat pig of a FIXME' AS fat_bastard
			, retirement_reason = -2 AS disco
			, IF(penalty_group_mode <> 'S', (SELECT SUM($lm2_penalty_points_clause) FROM {$this->lm2_db_prefix}penalties WHERE event_entry = id_event_entry), NULL) AS entry_penalty_points
			, (SELECT SUM(points_lost) FROM {$this->lm2_db_prefix}penalties WHERE event_entry = id_event_entry AND IFNULL(penalty_champ_type, champ_type) = champ_type) AS champ_points_lost
			, minimum_distance
			FROM {$this->lm2_db_prefix}event_entries
			JOIN {$this->lm2_db_prefix}events ON event = id_event AND event_type = 'C' AND NOT is_protected_c AND event_status <> 'H'
			JOIN {$this->lm2_db_prefix}event_group_tree ON {$this->lm2_db_prefix}event_group_tree.contained = {$this->lm2_db_prefix}events.event_group
			JOIN {$this->lm2_db_prefix}championships ON {$this->lm2_db_prefix}championships.event_group = {$this->lm2_db_prefix}event_group_tree.container
			JOIN {$this->lm2_db_prefix}event_groups ON {$this->lm2_db_prefix}championships.event_group = id_event_group
			JOIN {$this->lm2_db_prefix}penalty_groups USING (penalty_group)
			JOIN {$this->lm2_db_prefix}scoring_schemes ON id_scoring_scheme = scoring_scheme
			JOIN {$this->lm2_db_prefix}sim_cars ON id_sim_car = sim_car
			JOIN {$this->lm2_db_prefix}cars ON id_car = car
			LEFT JOIN {$this->temp_db_prefix}rounds ON round_championship = id_championship AND round_event = id_event
			LEFT JOIN {$this->lm2_db_prefix}car_ratings ON id_car = rated_car AND rating_scoring_scheme = id_scoring_scheme
			WHERE member <> $guest_member_id AND driver_type IS NULL
			AND IFNULL(round_no, 1) <= IFNULL(rounds, 1)
			AND {$this->champ_id_clause} IS NOT NULL
			AND car_class_c REGEXP CONCAT('^(',{$this->lm2_db_prefix}championships.class,')\$')
			AND IFNULL(reg_class,'') REGEXP CONCAT('^(',IFNULL(reg_class_regexp,''),')\$')
			", __FILE__, __LINE__);

		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}rounds", __FILE__, __LINE__);

		echo " lap minima...";

//TODO: not for historic stuff?
		lm2_query("
			CREATE TEMPORARY TABLE {$this->temp_db_prefix}minlaps
			(INDEX (championship, event))
			AS SELECT championship
			, event
			, IFNULL(IF(minimum_distance < 0, CEILING(MAX(race_laps) * -minimum_distance), FLOOR(MAX(race_laps) * minimum_distance)), -1) AS minimum_laps
			FROM {$this->temp_db_prefix}positions
			JOIN {$this->lm2_db_prefix}event_entries USING (id_event_entry, event)
			WHERE minimum_distance IS NOT NULL
			GROUP BY championship, event
			", __FILE__, __LINE__);

		// Drop any positions which don't meet the criteria.
		$query = lm2_query("
			DELETE FROM {$this->temp_db_prefix}positions
			USING {$this->temp_db_prefix}positions
			JOIN {$this->lm2_db_prefix}event_entries USING (id_event_entry, event)
			JOIN {$this->temp_db_prefix}minlaps USING (championship, event)
			WHERE IFNULL(race_laps, -1) < minimum_laps
			", __FILE__, __LINE__);

		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}minlaps", __FILE__, __LINE__);

		echo " grouping...";

		// Rank each ID group (for the team championships).
		lm2_query("SET @pos = -1", __FILE__, __LINE__);
		lm2_query("SET @id = -1", __FILE__, __LINE__);
		lm2_query("SET @event = -1", __FILE__, __LINE__);
		lm2_query("SET @champ = -1", __FILE__, __LINE__);
		lm2_query("UPDATE {$this->temp_db_prefix}positions
			SET id_rank = (@pos := IF(@id = id AND @event = event AND @champ = championship, @pos + 1,
				((@id := id) + (@event := event) + (@champ := championship)) * 0 + 1))
			WHERE adjusted_race_pos IS NOT NULL
			ORDER BY championship, event, id, adjusted_race_pos, race_pos_penalty
			" , __FILE__, __LINE__);

		lm2_query("DELETE FROM {$this->temp_db_prefix}positions WHERE id_rank > max_rank", __FILE__, __LINE__);

		echo " ranking...";

		// Now go through and order each group.
		lm2_query("SET @pos = -1", __FILE__, __LINE__);
		lm2_query("SET @event = -1", __FILE__, __LINE__);
		lm2_query("SET @champ = -1", __FILE__, __LINE__);
		lm2_query("UPDATE {$this->temp_db_prefix}positions
			SET position = IF(adjusted_race_pos IS NULL, NULL, (@pos := IF(event = @event AND @champ = championship, @pos + 1, ((@event := event) + (@champ := championship)) * 0 + 1)))
			ORDER BY championship, event, adjusted_race_pos, race_pos_penalty
			" , __FILE__, __LINE__);

		echo " car changes...";

		// Set score penalty for changing car.
//XXX: Danger, Will Robinson! Suspect that the order of evaluation here is ferking us up... @changes is a bit suspect... THIS IS A GENERAL PROBLEM!
		lm2_query("SET @car = -1", __FILE__, __LINE__);
		lm2_query("SET @id = -1", __FILE__, __LINE__);
		lm2_query("SET @champ = -1", __FILE__, __LINE__);
		lm2_query("SET @changes = 999", __FILE__, __LINE__);
		lm2_query("UPDATE {$this->temp_db_prefix}positions
			SET fat_bastard = CONCAT('@id=', @id, ', @champ=', @champ, ', @car=', @car, ', @free=', @changes)
			, penalty = IF(@champ <> championship OR @id <> id
				     , NULL * (@champ := championship) * (@id := id) * (@changes := IFNULL(free_car_changes, 999))
				     , IF(@car <> car, IF(@changes > 0, NULL * (@changes := @changes - 1), penalty), NULL)
				) + 0 * (@car := car)
			WHERE penalty IS NOT NULL
			ORDER BY championship, id, id_event_entry, event_date
			", __FILE__, __LINE__);

		echo " slaves...";

		lm2_query("
			CREATE TEMPORARY TABLE {$this->temp_db_prefix}slaves
			AS SELECT id_championship AS championship, id_event_entry, master_pos.position AS master_position
			FROM {$this->lm2_db_prefix}championships
			JOIN {$this->temp_db_prefix}positions AS master_pos ON championship = champ_master
			", __FILE__, __LINE__);
		lm2_query("
			UPDATE {$this->temp_db_prefix}positions
			JOIN {$this->lm2_db_prefix}championships ON id_championship = championship
			JOIN {$this->lm2_db_prefix}event_entries USING (id_event_entry)
			LEFT JOIN {$this->temp_db_prefix}slaves USING (championship, id_event_entry)
			SET position = master_position
			WHERE champ_master IS NOT NULL
			", __FILE__, __LINE__);
		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}slaves", __FILE__, __LINE__);

		echo " RP...";

		lm2_query("
			UPDATE {$this->temp_db_prefix}positions
			JOIN {$this->lm2_db_prefix}championships ON id_championship = championship
			JOIN {$this->lm2_db_prefix}event_entries USING (id_event_entry)
			SET position = race_pos
			WHERE slave_to_race_pos
			", __FILE__, __LINE__);

		// And finally insert the results back into the event[_entry]_points table. One version for each scoring_type:

		echo " traditional...";

		// First, the 'standard' championships.
		lm2_query("
			INSERT INTO {$this->temp_db_prefix}event_points
			(championship, event_entry, position, points, id, is_protected_c, ep_penalty_points, champ_points_lost, tokens)
			SELECT championship
			, id_event_entry
			, {$this->lm2_db_prefix}points_schemes.position
			, CEILING(points * IFNULL(1.0 - penalty, 1.0) * IFNULL(ballast_bonus * ballast_driver / 100.0 + 1.0, 1.0))
			, id
			, is_protected
			, entry_penalty_points
			, champ_points_lost
			, IF({$this->temp_db_prefix}positions.position IS NOT NULL, IF(disco, rating / 2.0, rating), NULL)
			FROM {$this->temp_db_prefix}positions
			JOIN {$this->lm2_db_prefix}championships ON id_championship = championship AND scoring_type_c = 'T'
			JOIN {$this->lm2_db_prefix}scoring_schemes ON id_scoring_scheme = scoring_scheme
			JOIN {$this->lm2_db_prefix}event_groups ON id_event_group = event_group
			LEFT JOIN {$this->lm2_db_prefix}points_schemes ON id_points_scheme = points_scheme
			AND {$this->temp_db_prefix}positions.position = {$this->lm2_db_prefix}points_schemes.position
			" , __FILE__, __LINE__);

		echo " cumulative...";

		// Second, the 'cumulative weighted' championships.
		lm2_query("SET @event = -1", __FILE__, __LINE__);
		lm2_query("SET @champ = -1", __FILE__, __LINE__);
		lm2_query("SET @ratingsum = -1.0", __FILE__, __LINE__);
		lm2_query("SET @finisherssum = -1", __FILE__, __LINE__);
		$STARTED = "race_laps IS NOT NULL";
		$FUDGE_FACTOR = "1";
		$RACE_ENTRANT_POINTS = "(@finisherssum := IF(@event = {$this->temp_db_prefix}positions.event AND @champ = championship, @finisherssum + 1, 1 + 0 * ((@event := {$this->temp_db_prefix}positions.event) + (@champ := championship) + (@ratingsum := 0.0))))";
		lm2_query("
			INSERT INTO {$this->temp_db_prefix}event_points
			(championship, event_entry, position, points, id, is_protected_c, ep_penalty_points, champ_points_lost)
			SELECT championship
			, {$this->temp_db_prefix}positions.id_event_entry
			, position
			, CEILING(IF($STARTED
			           , 0 * ($RACE_ENTRANT_POINTS) + IF(position IS NULL
			                                           , IF(excluded_c, NULL, 1)
			                                           , @finisherssum + ($FUDGE_FACTOR * (@ratingsum / rating)) + 0 * (@ratingsum := @ratingsum + rating)
			                                          )
			           , NULL) * IFNULL(1.0 - penalty, 1.0))
			, id
			, is_protected_c
			, entry_penalty_points
			, champ_points_lost
			FROM {$this->temp_db_prefix}positions
			JOIN {$this->lm2_db_prefix}event_entries USING (id_event_entry)
			WHERE scoring_type_c = 'C'
			ORDER BY {$this->temp_db_prefix}positions.event, championship, IFNULL(position, 999) DESC
			" , __FILE__, __LINE__);

		echo " average...";

		// Next, the 'average' championships. These were used in UKGPL for teams from S4 to S9; S9 had an additional single car factor of 70%.
		$s9nonDiscoPoints = "SUM(IF(disco, 0, points))";
		$s9nonDiscoCount = "SUM(IF(disco, 0, IF(zeros_count = 0, {$this->temp_db_prefix}positions.position IS NOT NULL, points > 0)))";
		$s9noScoreDiscoCount = "SUM(IF(disco AND IFNULL(points, 0) = 0, 0, 1))";
		$s9discoPoints = "MAX(IF(disco, points, 0))";
		lm2_query("
			INSERT INTO {$this->temp_db_prefix}event_points
			(championship, event_entry, position, points, id, is_protected_c, champ_points_lost)
			SELECT championship
			, id_event_entry
			, NULL
			, IF($s9nonDiscoPoints > 0, $s9nonDiscoPoints / $s9nonDiscoCount, $s9discoPoints)
			  * IF($s9noScoreDiscoCount = 1, IFNULL(single_car_penalty, 1.0), 1.0)
			, {$this->temp_db_prefix}positions.id
			, is_protected
			, SUM(champ_points_lost)
			FROM {$this->temp_db_prefix}positions
			JOIN {$this->lm2_db_prefix}championships ON id_championship = championship AND scoring_type_c = 'A'
			JOIN {$this->lm2_db_prefix}scoring_schemes ON id_scoring_scheme = scoring_scheme
			JOIN {$this->lm2_db_prefix}event_groups ON id_event_group = event_group
			LEFT JOIN {$this->lm2_db_prefix}points_schemes ON id_points_scheme = points_scheme
				AND {$this->temp_db_prefix}positions.position = {$this->lm2_db_prefix}points_schemes.position
			GROUP BY event, id
			" , __FILE__, __LINE__);

		echo " composite...";

		lm2_query("
			CREATE TEMPORARY TABLE {$this->temp_db_prefix}composite_points AS
			SELECT event_entry AS comp_event_entry
			, SUM(points) AS comp_points
			, MAX(champ_points_lost) AS comp_champ_points_lost
			, id AS comp_id
			, is_protected_c
			, target_champ
			FROM {$this->temp_db_prefix}event_points
			JOIN {$this->lm2_db_prefix}champ_composit ON source_champ = championship
			GROUP BY target_champ, event_entry, id, is_protected_c
			", __FILE__, __LINE__);
		lm2_query("
			INSERT INTO {$this->temp_db_prefix}event_points
			(championship, event_entry, position, points, id, is_protected_c, champ_points_lost)
			SELECT championship
			, comp_event_entry
			, NULL
			, comp_points
			, comp_id
			, is_protected_c
			, comp_champ_points_lost
			FROM {$this->temp_db_prefix}positions
			JOIN {$this->temp_db_prefix}composite_points
			       ON comp_id = id AND championship = target_champ AND id_event_entry = comp_event_entry
			ON DUPLICATE KEY UPDATE points = points + comp_points
			" , __FILE__, __LINE__);

		echo " done</P>\n";

		echo "<P>Dropping scores...</P>\n";

		lm2_query("CREATE TEMPORARY TABLE {$this->temp_db_prefix}droppers"
			. " (INDEX (d_id, d_championship), INDEX(d_event))"
			. " AS SELECT best, id AS d_id, id_championship AS d_championship, event AS d_event, IFNULL(SUM(points), -1) AS event_total, -1 AS rank"
			. " FROM {$this->lm2_db_prefix}championships"
			. ", {$this->lm2_db_prefix}event_entries"
			. ", {$this->temp_db_prefix}event_points"
			. " WHERE championship = id_championship"
			. " AND id_event_entry = event_entry"
			. " AND best IS NOT NULL"
			. " GROUP BY id, championship, event"
			, __FILE__, __LINE__);
		lm2_query("SET @id = -1", __FILE__, __LINE__);
		lm2_query("SET @champ = -1", __FILE__, __LINE__);
		lm2_query("SET @pos = -1", __FILE__, __LINE__);
		lm2_query("UPDATE {$this->temp_db_prefix}droppers"
			. " SET rank = (@pos := IF(@id = d_id AND @champ = d_championship, @pos + 1, ((@id := d_id) + (@champ := d_championship)) * 0 + 1))"
			. " ORDER BY d_championship, d_id, event_total DESC"
			, __FILE__, __LINE__);
		lm2_query("
			UPDATE {$this->temp_db_prefix}droppers
			, {$this->temp_db_prefix}event_points
			, {$this->lm2_db_prefix}event_entries
			SET is_dropped = rank > best
			WHERE d_id = id
			AND d_championship = championship
			AND d_event = event
			AND id_event_entry = event_entry
			" , __FILE__, __LINE__);
		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}droppers", __FILE__, __LINE__);

		echo "<P>Generating championship points and standings...";

		// Now tally up all the championships at once.
		lm2_query("
			INSERT INTO {$this->temp_db_prefix}championship_points
			(championship, points, position, id, is_protected_c, champ_penalty_points, champ_points_lost, tokens, single_car)
			SELECT championship
			, SUM(IF(is_dropped, NULL, points))
			, NULL" /* Position */ . "
			, id
			, MIN({$this->lm2_db_prefix}event_entries.is_protected_c)
			, SUM(ep_penalty_points)
			, SUM({$this->temp_db_prefix}event_points.champ_points_lost)
			, SUM(tokens)
			, IF(COUNT(DISTINCT car) = 1, car, NULL)
			FROM {$this->lm2_db_prefix}event_entries
			JOIN {$this->temp_db_prefix}event_points ON id_event_entry = event_entry
			JOIN {$this->lm2_db_prefix}championships ON championship = id_championship
			JOIN {$this->lm2_db_prefix}scoring_schemes ON id_scoring_scheme = scoring_scheme
			JOIN {$this->temp_db_prefix}positions USING (championship, id, id_event_entry)
			GROUP BY championship, id
			", __FILE__, __LINE__);

		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}positions", __FILE__, __LINE__);

		echo " adding tokens...";

		lm2_query("
			UPDATE {$this->temp_db_prefix}championship_points
			JOIN {$this->lm2_db_prefix}championships ON championship = id_championship
			JOIN {$this->lm2_db_prefix}scoring_schemes ON id_scoring_scheme = scoring_scheme
			SET points = points - points * ((tokens - max_tokens) * overspend_penalty / 10000.0)
			WHERE overspend_penalty IS NOT NULL AND tokens > max_tokens
			", __FILE__, __LINE__);

		lm2_query("
			UPDATE {$this->temp_db_prefix}championship_points
			LEFT JOIN {$this->temp_db_prefix}ukgpls18_tokens USING (championship, id)
			SET {$this->temp_db_prefix}championship_points.tokens = {$this->temp_db_prefix}ukgpls18_tokens.tokens
			WHERE championship IN (
				SELECT DISTINCT id_championship
				FROM {$this->lm2_db_prefix}championships
				JOIN {$this->lm2_db_prefix}scoring_schemes ON id_scoring_scheme = scoring_scheme
				WHERE {$this->ukgpls18tokensPredicate}
			)
			", __FILE__, __LINE__);
		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}ukgpls18_tokens", __FILE__, __LINE__);
			
		echo " and subtracting points penalties...</P>\n";

		lm2_query("
			UPDATE {$this->temp_db_prefix}championship_points
			SET points = IFNULL(points, 0) - champ_points_lost
			WHERE champ_points_lost IS NOT NULL
			", __FILE__, __LINE__);

		// Finally, update actual championship positions.
		lm2_query("SET @pos = 0", __FILE__, __LINE__);
		lm2_query("SET @rank = 0", __FILE__, __LINE__);
		lm2_query("SET @points = -2", __FILE__, __LINE__);
		lm2_query("SET @champ = -1", __FILE__, __LINE__);
		lm2_query("UPDATE {$this->temp_db_prefix}championship_points"
			. " SET position = (@pos := IF(@champ = championship, @pos + 1, ((@champ := championship) + (@points := -2)) * 0 + 1)) * 0"
			. " + (@rank := IF(@points <> IFNULL(points, -1), (@points := IFNULL(points, -1)) * 0 + @pos, @rank))"
			. " ORDER BY championship, points DESC", __FILE__, __LINE__);

		echo "<P>Breaking championship ties...";

		// Tie breaking rules. First, SRou's.

		$ties = $this->break_ties(null, 'S');
		if ($ties)
			$this->break_ties("-COUNT(*)", 'S');
		if ($ties)
			$this->break_ties("MIN(IFNULL(race_pos_class, 999))", 'S');
		if ($ties)
			$this->break_ties("MIN(IFNULL(race_pos, 999))", 'S');
		if ($ties)
			$this->break_ties("MIN(IFNULL(qual_pos_class, 999))", 'S');
		if ($ties)
			$this->break_ties("MIN(IFNULL(qual_pos, 999))", 'S');
		$max_pos = 6;
		for ($i = 1; $ties && $i < $max_pos; ++$i) {
			$ties = $this->break_ties("-SUM(race_pos_class = $i)", 'S', -999, 999);
		}
		for ($i = 1; $ties && $i < $max_pos; ++$i) {
			$ties = $this->break_ties("-SUM(race_pos = $i)", 'S', -999, 999);
		}
		for ($i = 1; $ties && $i < $max_pos; ++$i) {
			$ties = $this->break_ties("-SUM(qual_pos_class = $i)", 'S', -999, 999);
		}
		for ($i = 1; $ties && $i < $max_pos; ++$i) {
			$ties = $this->break_ties("-SUM(qual_pos = $i)", 'S', -999, 999);
		}
		echo " <I>$ties unbroken SRou ties</I>...";

		$this->show_unbroken_ties();

		// And now, UKGPL's drivers.

		$ties = $this->break_ties(null, 'U');
		if ($ties)
			$ties = $this->break_ties("champ_penalty_points", 'U', -1); // -1 because NULL is better than 0.
		$max_pos = 20;
		for ($i = 1; $ties && $i < $max_pos; ++$i) {
			$ties = $this->break_ties("-SUM(race_pos = $i)", 'U', -999, 999);
		}
		if ($ties)
			$ties = $this->break_ties("champ_points_lost", 'U', -1); // -1 because NULL is better than 0.
		if ($ties)
			$ties = $this->break_ties("tokens", 'U');
		//XXX: pre-dropped scores
		// Earliest best result - works but was never implemented in UKGPL!
//		for ($i = 1; $ties && $i < $max_pos; ++$i) {
//			$ties = $this->break_ties("MIN(IF(race_pos = $i, UNIX_TIMESTAMP(event_date), NULL))", 'U', "UNIX_TIMESTAMP('2071-10-25')", "UNIX_TIMESTAMP('1971-10-25')");
//		}
		echo " <I>$ties unbroken UKGPL drivers ties</I>...";

		$this->show_unbroken_ties();

		// Finally, UKGPL's teams.

		$ties = $this->break_ties(null, 'T');
		if ($ties)
			$ties = $this->break_ties("champ_points_lost", 'T', -1); // -1 because NULL is better than 0.
		if ($ties)
			$ties = $this->break_ties("champ_penalty_points", 'T', -1); // -1 because NULL is better than 0.
		echo " <I>$ties unbroken UKGPL teams ties</I>...";

		$this->show_unbroken_ties();

		echo " done</P>\n";

		/*FIXME: add in drivers who haven't raced. Preferably above with a LEFT JOIN...
		  Perhaps even add in from the appropriate member group(s) with reserve status; might eliminate the need for a driver list,
		  though it would prevent the groups from ever being cleaned up. */

		echo "<P>Updating event points cache...</P>\n";

		//TODO: rewrite to use a subquery.
		//TODO: don't do protected events.
		//XXX: consider doing this after everything else rather than in here. What exactly do we use this field for?
		lm2_query("CREATE TEMPORARY TABLE {$this->temp_db_prefix}event_points2"
			. " (UNIQUE (event))"
			. " AS SELECT id_event AS event, IFNULL(COUNT(id), 0) > 0 AS points"
			. " FROM {$this->lm2_db_prefix}events"
			. " LEFT JOIN {$this->lm2_db_prefix}event_entries ON id_event = event" //TODO: do we need to join this table?
			. " LEFT JOIN {$this->temp_db_prefix}event_points ON id_event_entry = event_entry"
			. " GROUP BY id_event",
			__FILE__, __LINE__);
		lm2_query("
			UPDATE {$this->lm2_db_prefix}events
			JOIN {$this->temp_db_prefix}event_points2 ON id_event = event
			JOIN {$this->lm2_db_prefix}event_groups ON id_event_group = event_group
			SET points_c = points
			WHERE NOT is_protected
			", __FILE__, __LINE__);
		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}event_points2", __FILE__, __LINE__);

		// Okay, done the main thing, but not yet copied data back.

		foreach ($this->tables as $table => $fields) {
			foreach ($fields['temp_fields'] as $name => $type) {
				lm2_query("ALTER TABLE {$this->temp_db_prefix}${table} DROP {$name}" , __FILE__, __LINE__);
			}
			
			lm2_query("DELETE FROM {$this->lm2_db_prefix}{$table} WHERE NOT is_protected_c", __FILE__, __LINE__);
			lm2_query("INSERT INTO {$this->lm2_db_prefix}{$table}
				SELECT * FROM {$this->temp_db_prefix}{$table}", __FILE__, __LINE__);
		}

		echo "<P>Updating lap records...</P>\n";

		// Finally, update the lap records.
		lm2_query("DELETE FROM {$this->lm2_db_prefix}lap_records", __FILE__, __LINE__);
		$this->make_lap_records('race_best_lap_time', 'R');
		$this->make_lap_records('qual_best_lap_time', 'Q');

		$end = microtime_float();
		if (($ms = ($end - $start) * 1000.0) >= 1) {
			printf("<P>Standings generation complete. Took %dms.</P>\n", $ms);
		}
	}

	function calculate_round_numbers() {
		echo "</P>\n<P>Working out round numbers...</P>\n";

		lm2_query("CREATE TEMPORARY TABLE {$this->temp_db_prefix}rounds
			(INDEX (round_event, round_championship))
			AS SELECT id_championship AS round_championship
			, id_event AS round_event
			, event_date
			, -1 AS round_no" . /* Rounds are numbered from 1 as the most recent to n as the first. */ "
			, entries_c AS entries
			FROM {$this->lm2_db_prefix}events
			, {$this->lm2_db_prefix}championships" . /* Join with only those which need doing... */ "
			, {$this->lm2_db_prefix}event_group_tree
			, {$this->lm2_db_prefix}event_entries
			WHERE entries_c > 0
			AND NOT is_protected_c
			AND rounds IS NOT NULL
			AND event_type = 'C'
			AND event = id_event
			AND {$this->lm2_db_prefix}championships.event_group = {$this->lm2_db_prefix}event_group_tree.container
			AND {$this->lm2_db_prefix}event_group_tree.contained = {$this->lm2_db_prefix}events.event_group
			GROUP BY id_championship, id_event
			", __FILE__, __LINE__);

		lm2_query("SET @round = -1", __FILE__, __LINE__);
		lm2_query("SET @champ = -1", __FILE__, __LINE__);
		lm2_query("UPDATE {$this->temp_db_prefix}rounds"
			. " SET round_no = (@round := IF(@champ = round_championship, @round + 1,"
			. " ((@champ := round_championship)) * 0 + 1))"
			. " ORDER BY round_championship, event_date DESC, round_event"
			, __FILE__, __LINE__);
	}

	function show_unbroken_ties() {
		echo "<!-- Remaining ties:\n";
		$query = lm2_query("
			SELECT * FROM {$this->temp_db_prefix}champ_ties
			JOIN {$this->lm2_db_prefix}championships ON t_champ = id_championship
			LEFT JOIN {$this->lm2_db_prefix}event_groups ON id_event_group = event_group
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			echo print_r($row, true) . "\n";
		}
		mysql_free_result($query);
		echo "-->\n";

		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}champ_ties", __FILE__, __LINE__);
	}

	function update_drivers_and_teams() {
		echo "<P>Updating driver list...";

//TODO: why do this? We don't actually use the drivers table... Removing it would remove the need for 'live' changes to the method.
		rebuild_driver_cache();

		echo " team associations...";

		lm2_query("
			UPDATE {$this->lm2_db_prefix}event_entries
			JOIN {$this->lm2_db_prefix}events ON id_event = event
			LEFT JOIN {$this->lm2_db_prefix}teams ON team = id_team
			SET team = (
				SELECT {$this->lm2_db_prefix}team_drivers.team
				FROM {$this->lm2_db_prefix}team_drivers
				LEFT JOIN {$this->lm2_db_prefix}event_group_tree
				ON {$this->lm2_db_prefix}team_drivers.event_group = container
				WHERE ({$this->lm2_db_prefix}events.event_group = contained OR {$this->lm2_db_prefix}team_drivers.event_group IS NULL)
				AND (date_to IS NULL OR date_to > event_date)
				AND date_from < event_date
				AND {$this->lm2_db_prefix}event_entries.member = {$this->lm2_db_prefix}team_drivers.member
				ORDER BY IFNULL(depth, 999)
				LIMIT 1
			)
			WHERE event_status <> 'H' AND NOT is_protected_c AND IFNULL(team_is_fake, 0) <> 1
			", __FILE__, __LINE__);

		echo " done</P>\n";
	}

	function update_penalty_points() {
		global $penalty_points_clause;

		echo "<P>Updating penalty points...";

		// Note that we cannot avoid a temporary table of some sort (even if it's implicit) because MySQL cannot update a table which is used in the FROM clause of a subquery.

//TODO: remove the nulling and left join when updating?
		lm2_query("UPDATE {$this->lm2_db_prefix}event_entries
			SET penalty_points = NULL
			WHERE NOT is_protected_c AND event NOT IN (
				SELECT id_event FROM {$this->lm2_db_prefix}events WHERE event_status = 'H')
			", __FILE__, __LINE__);
		lm2_query("CREATE TEMPORARY TABLE {$this->temp_db_prefix}totting_penalties
			(INDEX (penalty_group, member), INDEX (totting_active_after), INDEX (totting_active_until))
			AS SELECT member
			, penalty_group
			, SUM($penalty_points_clause) AS totting_ycp
			, report_published AS totting_active_after
			, DATE_ADD(report_published, INTERVAL penalty_group_months MONTH) AS totting_active_until
			FROM {$this->lm2_db_prefix}penalties
			JOIN {$this->lm2_db_prefix}event_entries ON id_event_entry = event_entry AND IFNULL(victim_report, 'Y') = 'Y'
			JOIN {$this->lm2_db_prefix}events ON id_event = event AND event_status IN ('O', 'H') AND report_published IS NOT NULL
			JOIN {$this->lm2_db_prefix}event_groups ON id_event_group = event_group
			JOIN {$this->lm2_db_prefix}penalty_groups pg USING (penalty_group)
			GROUP BY penalty_group, member, report_published
			HAVING totting_ycp IS NOT NULL
			", __FILE__, __LINE__);
		lm2_query("CREATE TEMPORARY TABLE {$this->temp_db_prefix}active_penalty_points
			(UNIQUE (event_entry))
			SELECT id_event_entry AS event_entry
			, SUM(totting_ycp) AS active
			FROM {$this->lm2_db_prefix}event_entries
			JOIN {$this->lm2_db_prefix}events ON id_event = event AND event_status <> 'H'
			JOIN {$this->lm2_db_prefix}event_groups ON id_event_group = event_group AND NOT is_protected
			JOIN {$this->temp_db_prefix}totting_penalties USING (penalty_group, member)
			WHERE event_date > totting_active_after AND event_date <= totting_active_until
			GROUP BY id_event_entry
			", __FILE__, __LINE__);
		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}totting_penalties", __FILE__, __LINE__);
		lm2_query("UPDATE {$this->lm2_db_prefix}event_entries
			JOIN {$this->temp_db_prefix}active_penalty_points ON id_event_entry = event_entry
			SET penalty_points = active
			", __FILE__, __LINE__);
		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}active_penalty_points", __FILE__, __LINE__);

		echo " and extra places lost</P>\n";

		lm2_query("UPDATE {$this->lm2_db_prefix}penalties
			JOIN {$this->lm2_db_prefix}event_entries ON id_event_entry = event_entry
			JOIN {$this->lm2_db_prefix}events ON id_event = event
			JOIN {$this->lm2_db_prefix}event_groups ON id_event_group = event_group
			JOIN {$this->lm2_db_prefix}penalty_groups USING (penalty_group)
			SET extra_positions_lost = 
				IF(CASE penalty_group_mode
					WHEN 'S' THEN positions_lost IS NOT NULL AND penalty_type = 'P'
					WHEN 'U' THEN penalty_type IN ('W', 'P')
					ELSE NULL
					END
				, IF(penalty_points > 2, penalty_points / 2 - 1, NULL)
				, NULL
				)
			WHERE NOT is_protected_c
			", __FILE__, __LINE__);
	}

	function ukgplS18tokens() {
		echo "<P>Stateful tokens...</P>\n";

		lm2_query("CREATE TEMPORARY TABLE {$this->temp_db_prefix}ukgpls18_tokens
			(UNIQUE (championship, id))
			AS SELECT championship, id, tokens
			FROM {$this->lm2_db_prefix}championship_points
			WHERE 0
			", __FILE__, __LINE__);

		($query = lm2_query("
			SELECT id_championship, event_group, scoring_scheme, COUNT(id_event) AS events
			FROM {$this->lm2_db_prefix}championships
			JOIN {$this->lm2_db_prefix}scoring_schemes ON id_scoring_scheme = scoring_scheme
			JOIN {$this->lm2_db_prefix}event_groups ON id_event_group = event_group
			JOIN {$this->lm2_db_prefix}events USING (event_group)
			WHERE {$this->ukgpls18tokensPredicate}
			AND NOT is_protected
			GROUP BY id_championship, event_group, scoring_scheme
			", __FILE__, __LINE__)) || die("failed to read championships");
		while ($row = mysql_fetch_assoc($query)) {
//**/		echo "<P><I>" . print_r($row, true) . "</I></P>\n";
			$this->ukgplS18tokensForOneGroup($row['id_championship'], $row['event_group'], $row['scoring_scheme'], $row['events']);
		}
		mysql_free_result($query);
	}

	function ukgplS18tokensForOneGroup($championship, $event_group, $scoring_scheme, $events) {
		global $guest_member_id;

//**/		printf("%f events, halved to %g", $events, $events / 2);
		$eventId = null;
		$eventIndex = 0;
		$balances = array();
		($query = lm2_query("
			SELECT id_event, id_event_entry, member, rating, max_tokens
			FROM {$this->lm2_db_prefix}events
			JOIN {$this->lm2_db_prefix}event_entries ON event = id_event
			JOIN {$this->lm2_db_prefix}sim_cars ON id_sim_car = sim_car
			JOIN {$this->lm2_db_prefix}car_ratings ON rating_scoring_scheme = $scoring_scheme AND rated_car = car
			JOIN {$this->lm2_db_prefix}scoring_schemes ON rating_scoring_scheme = id_scoring_scheme
			WHERE event_group = $event_group
			AND member <> $guest_member_id AND driver_type IS NULL
			AND race_laps IS NOT NULL
			ORDER BY event_date
			", __FILE__, __LINE__)) || die("failed to read entries");
		while ($row = mysql_fetch_assoc($query)) {
			if ($eventId != $row['id_event']) {
				$eventId = $row['id_event'];
//**/				echo "<BR/><B>Round " . ++$eventIndex . "</B>";
			}

			if (!array_key_exists($row['member'], $balances)) {
				$balances[$row['member']] = -$row['max_tokens'] * ($eventIndex < $events / 2 ? 2 : 1); // Initial allocation
			}
$row['preBALANCE'] = $balances[$row['member']];
			$balances[$row['member']] += 10; // Starting bonus.
$row['startBALANCE'] = $balances[$row['member']];

			$balances[$row['member']] -= $row['rating'];
$row['postBALANCE'] = "{$balances[$row['member']]}";
			if ($balances[$row['member']] < 0) {
$row['postBALANCE'] = "<SPAN STYLE='color: red'>{$row['postBALANCE']}<SPAN>";
				$balances[$row['member']] = 0;
				lm2_query("UPDATE {$this->lm2_db_prefix}event_entries
					SET excluded_c = 'Y'
					WHERE id_event_entry = {$row['id_event_entry']}
					", __FILE__, __LINE__);
			}

//**/			echo "<br/><tt>" . print_r($row, true) . "</tt>\n";
		}
		mysql_free_result($query);

		foreach ($balances as $id => $tokens) {
			lm2_query("INSERT INTO {$this->temp_db_prefix}ukgpls18_tokens
				(championship, id, tokens)
				VALUES ($championship, $id, $tokens)
				", __FILE__, __LINE__);
		}
	}
	
	function update_cached_exclusion_flags() {
		lm2_query("UPDATE {$this->lm2_db_prefix}event_entries
			JOIN {$this->lm2_db_prefix}events ON event = id_event
			SET excluded_c = IF(event_status IN ('O', 'H') AND id_event_entry IN (
				SELECT event_entry
				FROM {$this->lm2_db_prefix}penalties
				WHERE excluded = 'Y'
			), 'Y', 'N')
			WHERE NOT is_protected_c
			", __FILE__, __LINE__);
	}

	function set_positions_lost() {
		//TODO: rewrite to remove the temporary table and do it with a subquery.

		lm2_query("
			UPDATE {$this->lm2_db_prefix}event_entries
			SET race_pos_penalty = 0
			WHERE NOT is_protected_c
			" , __FILE__, __LINE__);
/*Hmm
SELECT event_entry, count(*) AS howmany, GROUP_CONCAT(extra_positions_lost, ',') AS epl
FROM lm2_penalties GROUP BY event_entry
HAVING howmany > 1 AND epl IS NOT NULL
ORDER BY 1, 2
*/			
		lm2_query("
			CREATE TEMPORARY TABLE {$this->temp_db_prefix}positions_lost
			(UNIQUE (event_entry))
			AS SELECT event_entry
			, SUM(IFNULL(positions_lost, 0) + IFNULL(extra_positions_lost, 0)) AS positions_lost
			FROM {$this->lm2_db_prefix}event_entries
			JOIN {$this->lm2_db_prefix}penalties ON id_event_entry = event_entry AND IFNULL(victim_report, 'Y') = 'Y'
			JOIN {$this->lm2_db_prefix}incidents ON id_incident = incident
			JOIN {$this->lm2_db_prefix}events ON {$this->lm2_db_prefix}incidents.event = id_event
			WHERE NOT is_protected_c AND event_status IN ('O', 'H')
			GROUP BY event_entry
			", __FILE__, __LINE__);
		lm2_query("
			UPDATE {$this->lm2_db_prefix}event_entries, {$this->temp_db_prefix}positions_lost
			SET race_pos_penalty = positions_lost
			WHERE id_event_entry = event_entry
			", __FILE__, __LINE__);
		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}positions_lost", __FILE__, __LINE__);
	}

	function set_class_positions() {
		echo "<P>Updating classes and class positions...";

		//XXX: strongly consider moving the protection flag from event_groups to championships, and keying event_entries updates off event status.
		//TODO: rewrite to not use the subselect.
		lm2_query("
			UPDATE {$this->lm2_db_prefix}event_entries
			SET qual_pos_class = NULL
			, race_pos_class = NULL
			, car_class_c = IFNULL((
				SELECT car_class
				FROM {$this->lm2_db_prefix}car_class_c
				JOIN {$this->lm2_db_prefix}sim_cars USING (car)
				WHERE id_sim_car = sim_car
				AND (SELECT event_group FROM {$this->lm2_db_prefix}events WHERE id_event = event) = event_group
			), '-')
			WHERE NOT is_protected_c
			", __FILE__, __LINE__);

		echo " cleared...";
		//FIXME: consider restricting this to championship classes only...
		lm2_query("
			CREATE TEMPORARY TABLE {$this->temp_db_prefix}class_positions
			(UNIQUE (event_entry))
			AS SELECT id_event_entry AS event_entry
			, start_pos * 0 AS start_pos_class
			, start_pos
			, qual_pos * 0 AS qual_pos_class
			, qual_pos
			, race_pos * 0 AS race_pos_class
			, race_pos
			, race_best_lap_pos * 0 AS race_best_lap_pos_class
			, race_best_lap_pos
			, car_class_c AS class
			, event
			FROM {$this->lm2_db_prefix}event_entries
			WHERE NOT is_protected_c
			", __FILE__, __LINE__);
		$this->class_rank('race');
		$this->class_rank('race_best_lap');
		$this->class_rank('qual');
		$this->class_rank('start');

		echo " temporary table built...";
		lm2_query("
			UPDATE {$this->lm2_db_prefix}event_entries, {$this->temp_db_prefix}class_positions
			SET " . $this->class_pos_copy('qual_pos_class') . "
			, " . $this->class_pos_copy('start_pos_class') . "
			, " . $this->class_pos_copy('race_pos_class') . "
			, " . $this->class_pos_copy('race_best_lap_pos_class') . "
			WHERE id_event_entry = event_entry
			", __FILE__, __LINE__);

		echo " done</P>\n";
		lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}class_positions", __FILE__, __LINE__);
	}

	function class_rank($field) {
		lm2_query("SET @pos = -1", __FILE__, __LINE__);
		lm2_query("SET @event = NULL", __FILE__, __LINE__);
		lm2_query("SET @class = NULL", __FILE__, __LINE__);
		lm2_query("
			UPDATE {$this->temp_db_prefix}class_positions
			SET {$field}_pos_class = (@pos := (IF((@event = event AND @class = class), @pos, ((@event := event) + (@class := class)) * 0) + 1))
			WHERE {$field}_pos IS NOT NULL
			ORDER BY event, class, {$field}_pos ASC
			", __FILE__, __LINE__);
	}

	function make_lap_records($time_field, $rec_type) {
		global $guest_member_id;

//XXX: consider whether to record records with the {unknown} class...
		lm2_query("INSERT INTO {$this->lm2_db_prefix}lap_records"
			. " (record_class, sim, record_lap_time, record_circuit, record_mph, lap_record_type)"
			. " SELECT car_class_c AS record_class"
			. ", {$this->lm2_db_prefix}events.sim"
			. ", MIN($time_field)"
			. ", {$this->lm2_db_prefix}sim_circuits.circuit"
			. ", (length_metres / 1609.3) / (MIN($time_field) / 3600.0)"
			. ", " . sqlString($rec_type)
			. " FROM {$this->lm2_db_prefix}event_entries"
			. ", {$this->lm2_db_prefix}events"
			. ", {$this->lm2_db_prefix}sim_circuits"
			. " WHERE id_event = event"
			. " AND id_sim_circuit = sim_circuit"
			. " AND $time_field > 0"
			. " AND member <> $guest_member_id AND driver_type IS NULL"
			. " AND event_type <> 'F'"
			. " GROUP BY car_class_c, sim, circuit",
			__FILE__, __LINE__);
	}

	// Returns true if any ties were found. Clause should be lowest number for best rank.
	function break_ties($clause, $mode, $nullValue = 9999999, $dummyBreaker = -9999999) {
		$mode || die("no mode");

		if (!is_null($clause)) {
			$sql = "
				SELECT id_championship AS brk_champ, position AS old_pos, -1 AS rank, id AS brk_id, IFNULL($clause, $nullValue) AS breaker
				FROM {$this->temp_db_prefix}champ_ties
				JOIN {$this->temp_db_prefix}championship_points ON t_champ = championship AND position = t_pos
				JOIN {$this->lm2_db_prefix}championships ON id_championship = championship
				JOIN {$this->lm2_db_prefix}event_groups ON id_event_group = {$this->lm2_db_prefix}championships.event_group
				JOIN {$this->lm2_db_prefix}event_group_tree ON {$this->lm2_db_prefix}championships.event_group = {$this->lm2_db_prefix}event_group_tree.container
				JOIN {$this->lm2_db_prefix}events ON {$this->lm2_db_prefix}event_group_tree.contained = {$this->lm2_db_prefix}events.event_group
				JOIN {$this->lm2_db_prefix}event_entries
					ON id_event = event AND IFNULL(reg_class,'') REGEXP CONCAT('^(',IFNULL(reg_class_regexp,''),')\$')
					AND car_class_c REGEXP CONCAT('^(',{$this->lm2_db_prefix}championships.class,')\$')
				JOIN {$this->lm2_db_prefix}sim_cars ON id_sim_car = sim_car
				JOIN {$this->lm2_db_prefix}cars ON id_car = car
				WHERE {$this->champ_id_clause} = id
				GROUP BY championship, position, id
			"; // Kept seperate for EXPLAIN purposes.
//if ($mode == 'U') explain($sql); //XXX: remove!
			lm2_query("CREATE TEMPORARY TABLE {$this->temp_db_prefix}champ_tie_breakers AS $sql", __FILE__, __LINE__);
			lm2_query("SET @old_pos = -1", __FILE__, __LINE__);
			lm2_query("SET @pos = -1", __FILE__, __LINE__);
			lm2_query("SET @rank = 0", __FILE__, __LINE__);
			lm2_query("SET @breaker = $dummyBreaker", __FILE__, __LINE__);
			lm2_query("SET @champ = -1", __FILE__, __LINE__);
			lm2_query("
				UPDATE {$this->temp_db_prefix}champ_tie_breakers
				SET rank = (@pos := IF(@champ = brk_champ AND @old_pos = old_pos, @pos + 1, ((@champ := brk_champ) + (@breaker := $dummyBreaker)) * 0 + (@old_pos := old_pos))) * 0
				+ (@rank := IF(@breaker <> breaker, (@breaker := breaker) * 0 + @pos, @rank))
				ORDER BY brk_champ, old_pos, breaker
				", __FILE__, __LINE__);
			lm2_query("UPDATE {$this->temp_db_prefix}champ_tie_breakers, {$this->temp_db_prefix}championship_points
				SET position = rank, tie_breaker = CONCAT(" . sqlString($clause . " = ") . ", breaker)
				WHERE old_pos <> rank AND brk_champ = championship AND id = brk_id
				", __FILE__, __LINE__);
			lm2_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}champ_tie_breakers", __FILE__, __LINE__);
		}

		lm2_query("DROP TEMPORARY TABLE IF EXISTS {$this->temp_db_prefix}champ_ties", __FILE__, __LINE__);
		lm2_query("
			CREATE TEMPORARY TABLE {$this->temp_db_prefix}champ_ties
			(INDEX (t_champ, t_pos)) AS
			SELECT championship AS t_champ, position AS t_pos, COUNT(*) AS tied
			FROM {$this->temp_db_prefix}championship_points
			JOIN {$this->lm2_db_prefix}championships ON id_championship = championship
			WHERE tie_break_mode = '$mode'
			GROUP BY championship, position
			HAVING tied > 1
			", __FILE__, __LINE__);
		$rows = mysql_affected_rows();
echo "<!-- $clause - $rows -->\n";
		return $rows;
	}

	function class_pos_copy($field) {
		return "{$this->lm2_db_prefix}event_entries.$field = IF({$this->temp_db_prefix}class_positions.$field = 0, NULL, {$this->temp_db_prefix}class_positions.$field)";
	}

}

function explain($sql) {
	echo "<PRE>Explain $sql\n\n";
	$query = lm2_query("EXPLAIN $sql", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		echo print_r($row) . "\n";
	}
	mysql_free_result($query);
	echo "</PRE>\n";
}

ob_end_flush();

//global $inhibitTimings; $inhibitTimings = 10;
(new StandingsGenerator())->generate_standings();
?>
