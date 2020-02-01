<?php

require 'connect.inc.php';
require 'global_vars.inc.php';

$limits = array(3, 6, 9, 12, 16, 0);
$us_odds = array(-110, -120, -130, -140, -150, -160, -170, -180, -220, -280, -360);

foreach ($us_odds as $us_odd) {
	foreach ($limits as $limit) {
		
		$dinamic_percentage[$us_odd][$limit] = 0.0;
		$dinamic_betted_matches[$us_odd][$limit] = 0;
		$dinamic_won_matches[$us_odd][$limit] = 0;
		//$test_capital[$us_odd][$limit] = $default_capital;
		
	}
}

$max_us_odd = NULL;
$max_limit = NULL;
$ultimate_capital = $default_capital;
$ultimate_match_count = 0;
$ultimate_match_won = 0;

$query = "SELECT game_id FROM test_limits_stat ORDER BY game_id DESC LIMIT 1";
if($result = mysqli_query(DBi::$conn, $query)) {
	
	$last_row = mysqli_fetch_array($result);

	for($round=1; $round<=$last_row[0]; $round++) {
		
			$query = "SELECT * FROM test_limits_stat WHERE game_id=$round";
		
			if($result = mysqli_query(DBi::$conn, $query)) {
				
	//echo 'Start of round '.$round.'<br>';			
	
	
				while ($stats = mysqli_fetch_assoc($result)) {
						
						$dinamic_betted_matches[$stats['us_odds']][$stats['match_limit']]++;
							
						if($stats['win_ind']) {		
							$dinamic_won_matches[$stats['us_odds']][$stats['match_limit']]++;
						}
						
						$dinamic_percentage[$stats['us_odds']][$stats['match_limit']] = $dinamic_won_matches[$stats['us_odds']][$stats['match_limit']]/$dinamic_betted_matches[$stats['us_odds']][$stats['match_limit']];
						
						
						// Ultimate logic
						if($stats['us_odds'] == $max_us_odd[0] && $stats['match_limit'] == $max_limit[0]) {
							
							$ultimate_bet = $ultimate_capital*0.05;						
							
							if($stats['win_ind']) {
								$ultimate_match_won++;
								$ultimate_capital += ($ultimate_bet*2.5-$ultimate_bet); 							
							} else {
								$ultimate_capital -= $ultimate_bet;	
							}
							$ultimate_match_count++;
							
							echo 'Ultimate Round: '.$round;
							echo ' | ';
							
							echo 'US odd: '.$max_us_odd[0];
							echo ' | ';
							
							echo 'Limit: '.$max_limit[0];
							echo ' | ';
							
							echo 'Percentage: '.round($ultimate_match_won/$ultimate_match_count*100, 2).'%';
							echo ' | ';
							
							echo 'Capital: '.$ultimate_capital;
							echo '<br>';
						}
						
					
				}
	
	/*			
				echo '<pre>';
	var_dump($dinamic_percentage);
	echo '</pre>';
	
	*/
				// Ultimate logic to be used in next round.
				$max_us_odd = array_keys($dinamic_percentage, max($dinamic_percentage));
				$max_limit = array_keys($dinamic_percentage[$max_us_odd[0]], max($dinamic_percentage[$max_us_odd[0]]));											
				
				//echo 'Logic for next round: <br>';
				//echo $max_us_odd[0].'/'.$max_limit[0].'<br>';
			} else { echo mysqli_error(DBi::$conn); }
	
	}
	
	
		/*		
				$max_us_odd = array_keys($dinamic_percentage, max($dinamic_percentage));
				$max_limit = array_keys($dinamic_percentage[$max_us_odd[0]], max($dinamic_percentage[$max_us_odd[0]]));			
				
				echo '<pre>';
				var_dump($max_us_odd);
				echo '</pre>';
				echo '<hr>';
				
				echo '<pre>';
				var_dump($max_limit);
				echo '</pre>';
				echo '<hr>';
	*/

} else { echo mysqli_error(DBi::$conn); }

?>