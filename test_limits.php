<?php

require 'connect.inc.php';
require 'func.inc.php';
require 'global_vars.inc.php';

$limits = array(3, 6, 9, 12, 16, 0);
$test_odds = array(2.5, 2.5, 3.8);
$us_odds = array(-110, -120, -130, -140, -150, -160, -170, -180, -220, -280, -360);

//$limits = array(9, 12);
//$test_odds = array(2.5, 2.5, 3.8);
//$us_odds = array(-130, -140);

// Initialize arrays.
foreach ($us_odds as $us_odd) {
	foreach ($limits as $limit) {
		
		$betted_matches[$us_odd][$limit] = 0;
		$won_matches[$us_odd][$limit] = 0;
		$test_capital[$us_odd][$limit] = $default_capital;
		
		foreach ($teams as $team) {
			$betted_matches_3D[$team][$us_odd][$limit] = 0;
			$won_matches_3D[$team][$us_odd][$limit] = 0;
		}
		
	}
}


if(isset($_POST['game_type'])) {
	$game_type = $_POST['game_type'];
} else {
	$game_type = 3;
}

if(isset($_POST['test_switch_inverse_logic'])) {
	$test_switch_inverse_logic = true;
} else {
	$test_switch_inverse_logic = false;
}

$test_limits_odds_handle = fopen('miscellaneous/test_limits_odds_full_stat.txt', "w");

/*  
if(isset($_POST['exclude_teams'])) {
	$teams_to_exclude = file('miscellaneous/excluded_teams.txt');
} else {
	$teams_to_exclude = array();
}
*/

if(isset($_POST['exclude_teams'])) {
	$exclude_teams = TRUE;
} else {
	$exclude_teams = FALSE;
}

if(@$_POST['exclusion_threshold']) {
	$exclusion_threshold = $_POST['exclusion_threshold'];
} else {
	$exclusion_threshold = 0;
}
	

if(@$_POST['submit_button']) {

foreach($us_odds as $us_odd) {

	// Select all matches that have results.
	$query = "SELECT * FROM matches m INNER JOIN results r ON m.game_id=r.game_id WHERE r.result IS NOT NULL AND m.odds_us >= $us_odd";

	if($result = mysqli_query(DBi::$conn, $query)) {
		
		while ($matches = mysqli_fetch_assoc($result)) {
			
			/*
			// Check if team should be excluded.
			$exclude = false;
			foreach ($teams_to_exclude as $excluded_team) {
				if($matches['visitor'] == $excluded_team || $matches['home'] == $excluded_team) {
					$exclude = true;
					break;					
				}
			}			
			*/
			
			//if(!$exclude) {
			
				foreach($limits as $limit) {
					
					$virtual_exclude = FALSE;
					
					if($exclude_teams) {
						// Calculate percentage of successful bets at this point in time for a team.
						if($betted_matches_3D[$matches['home']][$us_odd][$limit] && $betted_matches_3D[$matches['visitor']][$us_odd][$limit]) {
							$percentage_of_matches_3D[$matches['home']][$us_odd][$limit] = $won_matches_3D[$matches['home']][$us_odd][$limit]/$betted_matches_3D[$matches['home']][$us_odd][$limit];					
							$percentage_of_matches_3D[$matches['visitor']][$us_odd][$limit] = $won_matches_3D[$matches['visitor']][$us_odd][$limit]/$betted_matches_3D[$matches['visitor']][$us_odd][$limit];
							
							// If the percentage is under a threshold it should be excluded from the calculation.
							// In practice it's excluded only virtually since we need the calculation result for future reference.
							if($percentage_of_matches_3D[$matches['home']][$us_odd][$limit] < $exclusion_threshold || $percentage_of_matches_3D[$matches['visitor']][$us_odd][$limit] < $exclusion_threshold) {
								$virtual_exclude = TRUE;
							}					
						}

					}
					
					
					// Calculate scores and who to bet on for each match.
					$home_score = calculate_scores($matches['home'], $limit, $matches['date'], $test_switch_inverse_logic);
					$visitor_score = calculate_scores($matches['visitor'], $limit, $matches['date'], $test_switch_inverse_logic);
					$to_bet_on = to_bet_on($visitor_score, $home_score);
					
					
					$include_game = true;
					if($to_bet_on != -1) {
						// Don't include game if game type overtime, but match resulted without overtime.
						if($game_type == 0 && $to_bet_on != 0) {
							$include_game = false;
						}
						// Don't include game if game type 2-way, but match resulted with overtime.
						if($game_type == 2 && $to_bet_on == 0) {
							$include_game = false;
						}
					} else {
						$include_game = false;
					}
						
					
					if($include_game) {
						
						if(!$virtual_exclude) {
							// Count included game for each limit.
							$betted_matches[$us_odd][$limit]++;
						}
						// Count included game for each team as well.
						$betted_matches_3D[$matches['home']][$us_odd][$limit]++;
						$betted_matches_3D[$matches['visitor']][$us_odd][$limit]++;
						
						// Calculate profit.
						$win = compare($to_bet_on, $matches['result'], $matches['overtime'], $test_capital[$us_odd][$limit]*0.05, $test_odds);
						//$win = compare($to_bet_on, $matches['result'], $matches['overtime'], 500, $test_odds);
						
						// Count won matches.
						if($win[0]) {
							if(!$virtual_exclude) {
								$won_matches[$us_odd][$limit]++;
							}
							$won_matches_3D[$matches['home']][$us_odd][$limit]++;
							$won_matches_3D[$matches['visitor']][$us_odd][$limit]++;
						}
						
						if(!$virtual_exclude) {
							// Update capital.
							$test_capital[$us_odd][$limit] += $win[1];
						
							// Export all the games stats to a file for analysis.
							fwrite($test_limits_odds_handle, $us_odd.','.$limit.','.$matches['game_id'].','.$matches['date'].','.$matches['visitor'].','.$matches['home'].','.$matches['odds_us'].','.$win[0].','.$test_capital[$us_odd][$limit]."\n");
							
							if($_POST['save_to_test_limits_test']) {
								$match_game_id = $matches['game_id'];
								$query = "INSERT INTO test_limits_stat (us_odds, match_limit, game_id, win_ind) VALUES ($us_odd, $limit, $match_game_id, $win[0])";
								if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
							}			
						}
						
					}

				}
				
			//}

			
		}
		

	
	} else { echo mysqli_error(DBi::$conn); }
	


}

}

fclose($test_limits_odds_handle);


			
/*
foreach($teams as $team) {
	foreach ($us_odds as $us_odd) {
		foreach($limits as $limit) {
			var_dump($percentage_of_matches_3D[$team][$us_odd][$limit]);// = $won_matches_3D[$team][$us_odd][$limit]/$betted_matches_3D[$team][$us_odd][$limit]*100;
		}
	}
}
*/
/*
echo '<pre>';
var_dump($won_matches_3D);
echo '<hr>';
var_dump($betted_matches_3D);
echo '<hr>';
var_dump($percentage_of_matches_3D);
echo '</pre>';
*/
?>

<html>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" href="css/style.css">
	<link rel="stylesheet" href="css/menu.css">
</head>

<body>

<div id="main-wrapper">

<?php include 'menu.html'; ?>

	<form action="test_limits.php" method="POST">
	Game type:
		<select name="game_type">
			<option value="3">Tree-way</option>
			<option value="2">Two-way</option>
			<option value="0">Just overtime</option>
		</select>
		<input type="submit" name="submit_button" value="Submit" />
		<br>
	Switch Inverse logic:
		<input type="checkbox" name="test_switch_inverse_logic" />
		<br>
	Exclude low performing teams: 	
		<input type="checkbox" name="exclude_teams" />
	Exclusion threshold:		
		<select name="exclusion_threshold">
			<?php
				for($i=0; $i<=100; $i+=5) {
					$decimalization = $i/100;
					echo '<option value="'.$decimalization.'" >'.$i.'%</option>';	
				}
			?>
		</select>	
		<br>
	Save to test_limits_test db: 	
		<input type="checkbox" name="save_to_test_limits_test" />	
	</form>
	
	<table>

		<?php
		$test_limits_summary_handle = fopen('miscellaneous/test_limits_odds_summary.txt', "w");		
		foreach ($us_odds as $us_odd) {
		?>
		<tr></tr>
		<tr>
			<th colspan="5">>=<?php echo $us_odd ?></th>
		</tr>		
		<tr>
			<th>Limit</th>
			<th>Betted matches</th>
			<th>Won matches</th>
			<th>Percentage</th>
			<th>Capital</th>
		</tr>
		
		<?php
			foreach ($limits as $limit) {
				
				if($betted_matches[$us_odd][$limit]) {	
					$percentage = $won_matches[$us_odd][$limit]/$betted_matches[$us_odd][$limit]*100;
				} else {
					$percentage = 0;
				}
				
				echo '<tr>';
				echo '<td>'.$limit.'</td><td>'.$betted_matches[$us_odd][$limit].'</td><td>'.$won_matches[$us_odd][$limit].'</td><td>'.round($percentage, 2).'%</td><td>'.round($test_capital[$us_odd][$limit]).'</td>';
				echo '</tr>';
				
				// Export the summary to a file for analysis.
				fwrite($test_limits_summary_handle, $us_odd.','.$limit.','.$betted_matches[$us_odd][$limit].','.$won_matches[$us_odd][$limit].','.round($percentage, 2).','.$test_capital[$us_odd][$limit]."\n");
				
			}
		}
		fclose($test_limits_summary_handle);
		?>
	
	</table>
	


</div>

</body>

</html>