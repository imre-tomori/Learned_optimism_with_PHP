<?php 

require 'connect.inc.php';
require 'func.inc.php';
require 'global_vars.inc.php';

header('Content-type: text/html; charset=utf-8');


// ------------------------------------------------------------------ //
//Mark the matching words with x's.

function hide($report_array, $p_match_sort) {
	
$count = 0;
					
foreach ($report_array as $rword) {
	$rword_trimmed = trim($rword,  ' .,()!?�\"“”\'');
	$found = false;
	
	foreach ($p_match_sort as $sorted) {
		if($rword_trimmed == $sorted) {
			$found = true;
			break;
		}
	}
		
	if($found) {
		$new_array[$count] = $rword;
	} else {
		$c=0;
		$xs = '';
		while($c<strlen($rword_trimmed)) {
			$xs .= 'x';
			$c++;
		}
		$new_array[$count] = $xs; 
	}
	$count++;
}

$output = implode(' ', $new_array);

return $output;	

}

// ------------------------------------------------------------------ //
// Compare each word from parameter 1 array with words in parameter 2 array.
// Return an array of the words that are in both parameter arrays.

function p_match($report_array, $p_array) {

	$match = array();
	
	foreach ($report_array as $rword) {
		$rword_trimmed = trim($rword, ' .,()!?�"“”\'');
			for($count=0;$count<count($p_array);$count++) {
				if($rword_trimmed == trim($p_array[$count])) {
					array_push($match, $rword_trimmed);
					break;
				}
			}
		
	}

	return $match;	
	
}

// ------------------------------------------------------------------ //
// Rearrange scores considering inverses and the win_loss indicator.

function rearrange_scores($p_scores, $win_loss, $switch_inverse_logic) {
	
	if(@$distortion_check) {
		$rearranged[4] = 0;
		$loop_value = 4;
	} else {
		$rearranged[3] = 0;
		$loop_value = 3;
	}
					
	for($i=0;$i<$loop_value;$i++) {
		
		$divide = 0;
		$p = 0;
		$inv = 0;
	
		if($p_scores[$i] != 0) {
			if($p_scores[$i+$loop_value] != 0) {		
				$divide = 2;
				$p = 7;
				$inv = 7;
			} else {	
				$divide = 1;
				$p = 7;
				$inv = 0;
			}
		} elseif($p_scores[$i+$loop_value] != 0) {
					$divide = 1;
					$p = 0;
					$inv = 7;
				}
				 	
			if($divide) {
			
				if(($win_loss == 'l' && !$switch_inverse_logic) || ($win_loss == 'w' && $switch_inverse_logic)) {
						$rearranged[$i] = ($p_scores[$i]+($inv-$p_scores[$i+$loop_value]))/$divide;
				} elseif(($win_loss == 'w' && !$switch_inverse_logic) || ($win_loss == 'l' && $switch_inverse_logic)) {
						$rearranged[$i] = ($p-$p_scores[$i]+($p_scores[$i+$loop_value]))/$divide;
				}
				
			} elseif($i<$loop_value) {
				$rearranged[$i] = 'NULL';
				$rearranged[$loop_value]++;
			}
		
	}
	
	return $rearranged;
	
}

//---------------------------------------------------------------------

function db_input($team, $date, $win_loss, $report, $rearranged, $total_score, $limit, $update) {

	$already_evaluated = date_check($team, $date);
	
	if($update) {
		
		$query = "UPDATE $team SET perm=$rearranged[0] , perv=$rearranged[1] , pers=$rearranged[2] , total_score=$total_score WHERE date='$date'";
		if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
	
	}elseif(!$already_evaluated) {
		
		$query = 'INSERT INTO '.$team.' (id, date, win_loss, report, perm, perv, pers, total_score) VALUES (NULL, \''.$date.'\', \''.$win_loss.'\', "'.$report.'", '.$rearranged[0].', '.$rearranged[1].', '.$rearranged[2].', '.$total_score.')';
		if(mysqli_query(DBi::$conn, $query)) {
				calculate_scores($team, 0);
				calculate_scores($team, $limit);
		} else {
			echo mysqli_error(DBi::$conn);
		}
		
	} else {
		global $error_message;
		$error_message = 'Match had been already evaluated!';
	}
		
}

// ------------------------------------------------------------------ //

function calculate_partial_scores($team, $date, $win_loss, $report, $distortion_check, $update) {
	
	//permanence (maradandóság)
	$per[0] = file('dimensions/permanence.txt');
	$per[4] = file('dimensions/permanence_inv.txt');
	
	//pervasiveness (elterjedtség)
	$per[1] = file('dimensions/pervasiveness.txt');
	$per[5] = file('dimensions/pervasiveness_inv.txt');
	
	//personalization (megszemélyesítés)
	$per[2] = file('dimensions/personalization.txt');
	$per[6] = file('dimensions/personalization_inv.txt');			
	
	//distortion (torzítás)
	$per[3] = file('dimensions/dist_neg.txt');				
	$per[7] = file('dimensions/dist_pos.txt');
	
	$report_array = explode(' ', $report);
	$p_match_sort = array();
	$match_all = 0;
	global $switch_inverse_logic;	
	
	if($distortion_check) {	
		$loop_value = 8;
	} else {
		$loop_value = 6;
	}					
	
	for($i=0;$i<$loop_value;$i++) {
		
		// Compare the words in the text with the words in the files and retrieve only the ones that match for each dimension.
		// Can contain duplicate worlds!
		$p_match[$i] = p_match($report_array, $per[$i]);
		
		// Don't collect distortion matches. Distortion added separately to total score.
		if($i != 3 || $i != 7) {

			// Collect only unique values for the whole report in $p_match_sort. 
			foreach ($p_match[$i] as $p) {
				$found = false;
				if($p) {
					foreach ($p_match_sort as $sorted) {
						if($p == $sorted) {
							$found = true;
							break;
						}
					}
				}
				
				if(!$found) {
					array_push($p_match_sort, $p);
				}		
			}
			
		}
	
	}
	
	// Count the number of unique matching words in the report text into $match_all.
	foreach ($report_array as $rword) {
		$rword_trimmed = trim($rword, ' .,()!?�"“”\'');
		foreach ($p_match_sort as $sorted) {
			if ($rword_trimmed == $sorted) {
				$match_all++;
			}
		}
	}	
	
	// Calculate strength of dimension by comparing matched words in dimension to total matched words. Value between 0-7.
	if($match_all) {
		for($i=0;$i<$loop_value;$i++) {						
			$p_scores[$i] = round((count($p_match[$i])/($match_all)) / (1/7));
		}
		//Rearrange scores considering inverses and the win_loss indicator.
		$rearranged = rearrange_scores($p_scores, $win_loss, $switch_inverse_logic);
	} else {
		$rearranged = array('NULL','NULL','NULL',3);
	}
	
	// $rearranged[3] holds the value of the non-evaluable dimensions.
	$multiply_by = 0;
	
	if($distortion_check) {
		
		//If no value for any dimension, set total score to NULL.
		if($rearranged[4] == 3) {
			$total_score = 'NULL';
		} elseif($rearranged[3] == 'NULL') {
				//Calculate total score weighted with dimensions not equal to NULL.
				$multiply_by = 3/(3-$rearranged[4]);
				$total_score = ($rearranged[0]+$rearranged[1]+$rearranged[2])*$multiply_by;
			} else {
				//If distortion value is valid (not Null), calculate total score according to this value.
				//Percentage can be also set:
				//		 100% - total score will be equal to distortion value.
				//			0% - distortion will be neglected.
				$multiply_by = 3/(3-$rearranged[4]);
				$total_score = (($rearranged[0]+$rearranged[1]+$rearranged[2])*$multiply_by) * (1 - $distortion_percentage) + ($rearranged[3]*3) * $distortion_percentage;
			}	
			
	} else {
		
		//If no value for any dimension, set total score to NULL.
		if($rearranged[3] == 3) {
			$total_score = 'NULL';
		} else {
			//Calculate total score weighted with dimensions not equal to NULL.
			$multiply_by = 3/(3-$rearranged[3]);
			$total_score = ($rearranged[0]+$rearranged[1]+$rearranged[2])*$multiply_by;
			}
		
	}
	
	$save_report = str_replace('"', "", $report);
	global $global_limit;
							
	db_input($team, $date, $win_loss, $save_report, $rearranged, $total_score, $global_limit, $update);
	
	$message_values = array('perm' => $rearranged[0], 'perv' => $rearranged[1], 'pers' => $rearranged[2], 'multiple_by' => $multiply_by, 'total_score' => $total_score, 'report_array' => $report_array, 'p_match_sort' => $p_match_sort);
	
	return $message_values;
	
}

//	------------------------------------------------------------------ //

function refresh_team_scores($team) {
	
	global $global_limit;
		
	$query = "SELECT date, win_loss, report FROM $team";
	if($result = mysqli_query(DBi::$conn, $query)){
		while ($games = mysqli_fetch_assoc($result)) {
			calculate_partial_scores($team, $games['date'], $games['win_loss'], $games['report'], @$distortion_check, 1);
		}
	} else { echo mysqli_error(DBi::$conn); }
	
	calculate_scores($team, $global_limit);
	calculate_scores($team, 0);
	
}

// ------------------------------------------------------------------- //

// Variable to set Delete last entry to hidden.
$delete_active = false;

$distortion_check = false;
$switch_inverse_logic = false;

if(@$_POST['distortion_check']) {
	$distortion_check = true;
}

if(@$_POST['distortion_percentage']) {
	$distortion_percentage = $_POST['distortion_percentage'];
}

if(@$_POST['switch_inverse_logic']) {
	$switch_inverse_logic = true;
}

// Evaluate button
if(@$_POST['evaluate']) {
	
	if(isset($_POST['report'])) {
		$report = strtolower(trim($_POST['report'], ' .,()!?�\''));
		
		if(!empty($report)) {
	
			if(isset($_POST['win_loss'])) {	
				$win_loss = $_POST['win_loss'];
				
				if(!empty($win_loss)) {
					
					if(isset($_POST['winner_team']) || isset($_POST['looser_team'])) {
						
						if(isset($_POST['date'])) {
							$date = $_POST['date'];
							
							if(!empty($date)) {
																			
								$winner_team = '';
								$looser_team = '';
								
								if(isset($_POST['winner_team'])) {
									$winner_team = $_POST['winner_team'];
								}
								
								if(isset($_POST['looser_team'])) {						
									$looser_team = $_POST['looser_team'];
								}						
							
								if(isset($_POST['winner_team']) && isset($_POST['looser_team'])) {	
									
									// Sort report if 'Sort' checkbox set.	
									if(@$_POST['sort'] == 'on') {
										if($winner_team != $looser_team) {
										
													$winner_report_sorted = sort_report($report, $winner_team);
													$looser_report_sorted = sort_report($report, $looser_team);
													
													$message_values1 = calculate_partial_scores($winner_team, $date, 'w', $winner_report_sorted, $distortion_check, 0);
													$message_values2 = calculate_partial_scores($looser_team, $date, 'l', $looser_report_sorted, $distortion_check, 0);
						
													$delete_active = true;
							
											
										} else { $error_message = 'You can\'t choose the same teams!'; }
										
									} else { $error_message = 'With two teams you can\'t go without sorting!'; }
							
								} else {
									
									// If only one team selected get it into $only_team.
									if(isset($_POST['winner_team'])) {
										$only_team = $winner_team;
									} else {
										$only_team = $looser_team;
									}
									
									// Sort report if 'Sort' checkbox set.
									if(@$_POST['sort'] == 'on') {
										$report_sorted = sort_report($report, $only_team);
									} else {
									 	$report_sorted = $report;
									}
									
									$message_values1 = calculate_partial_scores($only_team, $date, $win_loss, $report_sorted, $distortion_check, 0);
									$delete_active = true;
									
								}
								
							} else {
								$error_message = 'Please select a date!';
							}
						}				
					} else { $error_message =  'Please select at least one team!'; }
								
				}			
			}
		
						
		} else {
			$error_message = 'Please fill in some text to evaluate!';
		}	
 	}
}

if(@$_POST['delete']) {
	
	if(isset($_POST['teams_delete'])) {
		$prev_teams = $_POST['teams_delete'];
		if(!empty($team)) {
			
			$error_message = 'Deleted line(s) from the following(s): ';
			foreach($prev_teams as $team) {
				if(!empty($team)) {
					$query = "DELETE FROM $team ORDER BY id DESC LIMIT 1";
					if(mysqli_query(DBi::$conn, $query)){
						$error_message .= array_search($team, $teams).' ';
						calculate_scores($team, 0);
						calculate_scores($team, $global_limit);
					} else {
						echo 'Delete failed! mysqli error!';
						echo mysqli_error(DBi::$conn);
					}
				}
			}
			
		}
	}
}

/*
if(@$_POST['last_date']) {
	if(isset($_POST['teams'])) {
		$team = $_POST['teams'];
		if(!empty($team)) {
			
			$query = "SELECT date FROM $team ORDER BY date DESC LIMIT 1";
			if($result = mysqli_query(DBi::$conn, $query)) {
				if(mysqli_num_rows($result)) {
					$date_last = mysqli_fetch_assoc($result);
					$date = $date_last['date'];
				} else {
					$error_message = 'No match evaluated yet!';
				}
			} else {
				echo mysqli_error(DBi::$conn);
			}
			
		}
	}
}
*/

if(@$_POST['refresh']) {
	
	if(isset($_POST['refresh_teams_scores'])) {
		$teams_to_refresh = $_POST['refresh_teams_scores'];
		
		if($teams_to_refresh) {
			foreach($teams_to_refresh as $team_to_refresh) {
				refresh_team_scores($team_to_refresh);
			}
		} else {
			
			foreach($teams as $team) {
				refresh_team_scores($team);
			}
			
		}
	}
	
}
	


if(@$_POST['sort_name']) {
	$sort_by = 1;
}

if(@$_POST['sort_last']) {
	$sort_by = 0;
}

if(@$_POST['sort_all']) {
	$sort_by = 2;
}


?>

<html>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" href="css/style.css">
	<link rel="stylesheet" href="css/style_evaluate.css">
	<link rel="stylesheet" href="css/menu.css">
</head>

<body>
	
<div id="main-wrapper">

<?php include 'menu.html'; ?>

<div id="left">
<section id="display-scores">
	<ul id="p-scores">
		<li>Permanence score: <?php echo @$message_values1['perm'].' | '.@$message_values2['perm']; ?></li>
		<li>Pervasiveness score: <?php echo @$message_values1['perv'].' | '.@$message_values2['perv']; ?></li>
		<li>Personalization score: <?php echo @$message_values1['pers'].' | '.@$message_values2['pers']; ?></li>
	</ul>
	
	<ul id="total-scores">
		<li><?php if(@$_POST['evaluate']) {
							echo 'Multiple by: '.@$message_values1['multiple_by'].'x | '.@$message_values2['multiple_by'].'x';
						} else {
							echo 'Multiple by: '; 
						}
			?>
		</li>
		<li>Total score: <strong><?php echo @$message_values1['total_score'].' | '.@$message_values2['total_score']; ?></strong></li>
	</ul>
	
</section>

<section id="error-message"><?php echo @$error_message; ?></section>

<section class="main_form">
	<form action="evaluate.php" method="POST">		
		
		Text to be evaluated: <br>
		<textarea name="report" cols="60" rows="12"><?php echo @$report; ?></textarea> <br> <br>
		
		Date: <input type="date" name="date" value="<?php echo @$date; ?>" />
		<input type="submit" name="evaluate" value="Evaluate" /> 
		<input type="submit" name="delete" value="Delete last entry" <?php if(!$delete_active) { echo 'disabled'; } ?> />
		<!-- <input type="submit" name="last_date" value="Last date" /> --> <br>
		<br> <br>
		
		<table>
			<tr>
				<th>Winner team</th>
				<th>Looser team</th>
			<tr>
			<tr>
				<td>
					<select name="winner_team" size="12">
						<?php
							$db_update_date = mktime(0, 0, 0, 1, 1, 1970);
							foreach($teams as $team_long => $team_short) {
								if(@$winner_team == $team_short) {
									$selected = ' selected';
								} else {
									$selected = '';
								}
								echo '<option value="'.$team_short.'"'.$selected.'>'.$team_long.'</option>';
								
								$query = "SELECT date FROM $team_short ORDER BY date DESC LIMIT 1";
								if($result = mysqli_query(DBi::$conn, $query)) {
									while($update_date = mysqli_fetch_array($result)) {
										$date_compare = strtotime($update_date[0]);
										if($date_compare > $db_update_date) {
											$db_update_date = $date_compare;
										}
									}
								} else { echo mysqli_error(DBi::$conn); }
							}
						?>
					</select>
				</td>
				<td>
					<select name="looser_team" size="12">
						<?php
							foreach($teams as $team_long => $team_short) {
								if(@$looser_team == $team_short) {
									$selected = ' selected';
								} else {
									$selected = '';
								}
								echo '<option value="'.$team_short.'"'.$selected.'>'.$team_long.'</option>';
							}
						?>
					</select>
				</td>
			</tr>
		</table>
		<br> <br>

		

		
		Winner: <br> <textarea cols="60" rows="6" disabled><?php echo @hide($message_values1['report_array'], $message_values1['p_match_sort']); ?></textarea> <br> <br>
		Looser: <br> <textarea cols="60" rows="6" disabled><?php echo @hide($message_values2['report_array'], $message_values2['p_match_sort']); ?></textarea> <br> <br>
	
		Options:<br>	
		Summary after 'Win': <input type="radio" name="win_loss" value="w" /> or 
				'Loss': <input type="radio" name="win_loss" value="l" checked /> <br>
		* Win/Loss indicators only taken into account when only one team selected.<br>
		'Sort (Only uncheck if text already sorted!)': <input type="checkbox" name="sort" value="on" checked /><br>
		'Distort (Check if you want to distort the results with the 4th dimension)': <input type="checkbox" name="distortion_check" value="on" /><br>
		'Distortion percentage:'		
		<select name="distortion_percentage">
			<?php
				for($i=0; $i<=100; $i+=5) {
					$decimalization = $i/100;
					echo '<option value="'.$decimalization.'" >'.$i.'%</option>';	
				}
			?>
		</select>		
		<br>
		'Switch inverse logic': <input type="checkbox" name="switch_inverse_logic" value="on" /><br>
		
		<br> <br>
		
		<input type="text" name="teams_delete[]" value="<?php echo @$winner_team; ?>" hidden />
		<input type="text" name="teams_delete[]" value="<?php echo @$looser_team; ?>" hidden />
</section>
</div>
<section class="scores">
	<?php
	
	if(@$sort_by) {
		if($sort_by == 1) {
			$query = "SELECT * FROM scores ORDER BY team";
		} elseif($sort_by == 2) {
			$query = "SELECT * FROM scores ORDER BY score_all";
		}
	} else {
		$query = "SELECT * FROM scores ORDER BY score_last";
	}
	
	if($result = mysqli_query(DBi::$conn, $query)) {
		
		echo '<table><tr><th>Team<input type="submit" name="sort_name" value=" " class="sort-buttons" /></th><th>Score (last '.$global_limit.')<input type="submit" name="sort_last" value=" " class="sort-buttons" /></th><th>Score (all)<input type="submit" name="sort_all" value=" " class="sort-buttons" /></th></tr>';
		while($team_scores = mysqli_fetch_assoc($result)) {
			echo '<tr><td class="teams-main">'.array_search($team_scores['team'], $teams).'</td><td>'.round($team_scores['score_last'], 2).'</td><td>'.round($team_scores['score_all'], 2).'</td></tr>';
		}
		echo '</table><br>';
		
	} else {
		echo mysqli_error(DBi::$conn);
	}
										
	?>
	
		<input type="submit" name="refresh" value="Refresh scores" />
		<select name="refresh_teams_scores[]" multiple>
			<?php
				foreach($teams as $team_long => $team_short) {
					echo '<option value="'.$team_short.'">'.$team_long.'</option>';
				}			
				echo '<option value="0"> - All teams - </option>';
			?>						
		</select>
	</form>
</section>

<footer>
	<p id="db-update">Database updated until:	<?php	echo date('Y-m-d', $db_update_date);	?></p>
</footer>

</div>

	
</body>

</html>