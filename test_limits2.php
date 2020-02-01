<?php

require 'connect.inc.php';
require 'func.inc.php';
require 'global_vars.inc.php';

//$limits = array(3, 6, 9, 12, 16, 0);
$test_odds = array(2.5, 2.5, 3.8);
$test_percentages = array(0.38, 0.38, 0.24);

// Initialize arrays.
/*foreach ($limits as $limit) {
	
	$betted_matches[$limit] = 0;
	$won_matches[$limit] = 0;
	$test_capital[$limit] = $default_capital;
	
}*/

$test_capital = $default_capital;

if(isset($_POST['game_type'])) {
	$game_type = $_POST['game_type'];
} else {
	$game_type = 3;
}

// Select all matches that have results.
$query = "SELECT * FROM matches m INNER JOIN results r ON m.game_id=r.game_id WHERE r.result IS NOT NULL";

$betted_matches = 0;
$won_matches = 0;

if($result = mysqli_query(DBi::$conn, $query)) {

	while ($matches = mysqli_fetch_assoc($result)) {
		
//		foreach($limits as $limit) {
			
			// Calculate scores and who to bet on for each match.
			//$home_score = calculate_scores($matches['home'], $limit, $matches['date']);
			//$visitor_score = calculate_scores($matches['visitor'], $limit, $matches['date']);
			//$to_bet_on = to_bet_on($visitor_score, $home_score);
			
			//New random winner logic
			$weights = array(1 => 0.38, 2 => 0.38, 0 => 0.24);			
			
			 $rand = (float)rand()/(float)getrandmax();
			 foreach ($weights as $value => $weight) {
			    if ($rand < $weight) {
			       $to_bet_on = $value;
			       break;
			    }
			    $rand -= $weight;
			  }
			
						
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
				
				// Count included game for each limit.
				$betted_matches++;
				
				// Calculate profit.
				$win = compare($to_bet_on, $matches['result'], $matches['overtime'], $test_capital*0.05, $test_odds);
				//$win = compare($to_bet_on, $matches['result'], $matches['overtime'], 500, $test_odds);
				
				// Count won matches.
				if($win[0]) {
					$won_matches++;
				}
				
				// Update capital.
				$test_capital += $win[1];
				
			}
		
//		}

	}

} else { echo mysqli_error(DBi::$conn); }


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

	<table>
	
		<tr>
			<th>Limit</th>
			<th>Betted matches</th>
			<th>Won matches</th>
			<th>Percentage</th>
			<th>Capital</th>
		</tr>
		<?php
		
			//foreach ($limits as $limit) {
				
				if($betted_matches) {
					$percentage = $won_matches/$betted_matches*100;
				} else {
					$percentage = 0;
				}
				
				echo '<tr>';
				echo '<td>All</td><td>'.$betted_matches.'</td><td>'.$won_matches.'</td><td>'.round($percentage, 2).'%</td><td>'.round($test_capital).'</td>';
				echo '</tr>';
				
			//}
		
		?>
	
	</table>
	
	<form action="test_limits2.php" method="POST">
	Game type:
		<select name="game_type">
			<option value="3">Tree-way</option>
			<option value="2">Two-way</option>
			<option value="0">Just overtime</option>
		</select>
		<input type="submit" value="Submit" />
	</form>

</div>

</body>

</html>