<?php 

require 'connect.inc.php';
require 'func.inc.php';
require 'global_vars.inc.php';

header('Content-type: text/html; charset=utf-8');

function rec_sort($paragraph, $pos) {
	
	$quotation_marks = array('"','“','”');
	$i = 0;
	$new_pos = FALSE;
	
	foreach($quotation_marks as $qmark) {
		$positions[$i] = strpos($paragraph, $qmark, $pos);
		
		if($positions[$i] !== FALSE) {
			if($new_pos === FALSE) {
				$new_pos = $positions[$i];
			} elseif($positions[$i] < $new_pos) {
				$new_pos = $positions[$i];
			}
		}
		$i++;
	}
	
	if($new_pos !== FALSE) {
		$pos_and_com = rec_sort($paragraph, $new_pos + 1);
		$pos_and_com['counter'] += 1;
		
		$comment = $pos_and_com['comment'];
		$commenter = $pos_and_com['commenter']; 
		
		if($pos_and_com['counter'] != 1) {
			$para_part = substr($paragraph, $new_pos, $pos_and_com['deeper_pos'] - $new_pos + 1);
			if($pos_and_com['counter'] % 2 != 0) {		
				$commenter = $para_part . ' ' . $commenter;
			} else {
				$comment = $para_part . ' ' . $comment;
				if($pos == 0) {
					$commenter = substr($paragraph, 0, $new_pos) . ' ' . $commenter;
				}
			}		
		} else {
			$commenter = substr($paragraph, $new_pos + 1) . ' ' . $commenter;
		}
		
	} else {
		$pos_and_com['counter'] = 0;
		$comment = '';
		$commenter = '';
	}
	
	$tmp = '';
	if(!$pos) {
		if($pos_and_com['counter'] % 2 != 0) {
			$tmp = $comment;
			$comment = $commenter;
			$commenter = $tmp;
		}
	}
	
	$return_values = array('counter' => $pos_and_com['counter'], 'deeper_pos' => $new_pos, 'comment' => $comment, 'commenter' => $commenter);
//	echo '<pre>';
	//	var_dump($return_values);
	//echo '</pre>';
	
	return $return_values;
}

//	-----------------------------------------------------------------	//

function sort_report($report, $team) {
	
	$paragraphs = explode("\n", $report);
	$comment_details = array();
	
	foreach($paragraphs as $paragraph) {
	
		$paragraph_no_tags = strip_tags($paragraph);
		$sorted_paragraph = rec_sort($paragraph_no_tags, 0);
					
		if($sorted_paragraph['counter']) {
			
			if(strlen($sorted_paragraph['commenter']) <= 1) {
				$count = count($comment_details);
				$comment_details[$count - 1]['comment'] .= $sorted_paragraph['comment'];
			} else {
				array_push($comment_details, $sorted_paragraph);
			}
			
		}

	}
	
	$team_members = file('rosters/'.$team.'.txt');
	
	$to_evaluate = '';
	foreach($comment_details as $comment_detail) {
		foreach($team_members as $member) {
			$member_trimmed = trim($member);
			if(stripos($comment_detail['commenter'], $member_trimmed) !== FALSE) {
				$to_evaluate .= $comment_detail['comment'];
				break;
			}
		}
	}
		
	return $to_evaluate;
}

// ------------------------------------------------------------------ //

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

function rearrange_scores($p_scores, $win_loss) {
	
	$rearranged[4] = 0;
					
	for($i=0;$i<4;$i++) {
		
		$divide = 0;
		$p = 0;
		$inv = 0;
	
		if($p_scores[$i] != 0) {
			if($p_scores[$i+4] != 0) {		
				$divide = 2;
				$p = 7;
				$inv = 7;
			} else {	
				$divide = 1;
				$p = 7;
				$inv = 0;
			}
		} elseif($p_scores[$i+4] != 0) {
					$divide = 1;
					$p = 0;
					$inv = 7;
				}
				 	
			if($divide) {
			
				if($win_loss == 'l') {
						$rearranged[$i] = ($p_scores[$i]+($inv-$p_scores[$i+4]))/$divide;
				} else {
						$rearranged[$i] = ($p-$p_scores[$i]+($p_scores[$i+4]))/$divide;
				}
				
			} elseif($i<4) {
				$rearranged[$i] = 'NULL';
				$rearranged[4]++;
			}
		
	}
	
	return $rearranged;
	
}

//---------------------------------------------------------------------

function db_input($team, $date, $win_loss, $report, $rearranged, $total_score, $limit, $update) {

	$already_evaluated = date_check($team, $date);
	
	if($update) {
		
		$query = "UPDATE $team SET perm=$rearranged[0] , perv=$rearranged[1] , pers=$rearranged[2] , total_score=$total_score WHERE date='$date'";
		if(!mysql_query($query)) { echo mysql_error(); }
	
	}elseif(!$already_evaluated) {
		
		$query = 'INSERT INTO '.$team.' (id, date, win_loss, report, perm, perv, pers, total_score) VALUES (NULL, \''.$date.'\', \''.$win_loss.'\', "'.$report.'", '.$rearranged[0].', '.$rearranged[1].', '.$rearranged[2].', '.$total_score.')';
		if(mysql_query($query)) {
				calculate_scores($team, 0);
				calculate_scores($team, $limit);
		} else {
			echo mysql_error();
		}
		
	} else {
		global $error_message;
		$error_message = 'Match had been already evaluated!';
	}
		
}

// ------------------------------------------------------------------ //

function calculate_partial_scores($team, $date, $win_loss, $report, $update) {
	
	//permanence (maradandóság)
	$per[0] = file('dimensions/permanence.txt');
	$per[4] = file('dimensions/permanence_inv.txt');
	
	//pervasiveness (elterjedtség)
	$per[1] = file('dimensions/pervasiveness.txt');
	$per[5] = file('dimensions/pervasiveness_inv.txt');
	
	//personalization (megszemélyesítés)
	$per[2] = file('dimensions/personalization.txt');
	$per[6] = file('dimensions/personalization_inv.txt');
	
	//distorsion (torzítás)
	$per[3] = file('dimensions/dist_neg.txt');				
	$per[7] = file('dimensions/dist_pos.txt');				
	
	$report_array = explode(' ', $report);
	$p_match_sort = array();
	$match_all = 0;													
	
	for($i=0;$i<8;$i++) {
		
		$p_match[$i] = p_match($report_array, $per[$i]);
	
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
	
	foreach ($report_array as $rword) {
		$rword_trimmed = trim($rword, ' .,()!?�"“”\'');
		foreach ($p_match_sort as $sorted) {
			if ($rword_trimmed == $sorted) {
				$match_all++;
			}
		}
	}	
	
	if($match_all) {
		for($i=0;$i<8;$i++) {						
			$p_scores[$i] = round((count($p_match[$i])/($match_all)) / (1/7));
		}
		$rearranged = rearrange_scores($p_scores, $win_loss);
	} else {
		$rearranged = array('NULL','NULL','NULL', 'NULL', 3);
	}
	
	$multiply_by = 0;
	if($rearranged[4] == 3) {
			$total_score = 'NULL';
	} elseif($rearranged[3] == 'NULL') {
			$multiply_by = 3/(3-$rearranged[4]);
			$total_score = ($rearranged[0]+$rearranged[1]+$rearranged[2])*$multiply_by;
		} else {
			$multiply_by = 3/(3-$rearranged[4]);
			$total_score = ($rearranged[0]+$rearranged[1]+$rearranged[2])*$multiply_by + $rearranged[3]*3/2;
		}
	
	$save_report = str_replace('"', "", $report);
	global $global_limit;
							
	db_input($team, $date, $win_loss, $save_report, $rearranged, $total_score, $global_limit, $update);
	
	$message_values = array('perm' => $rearranged[0], 'perv' => $rearranged[1], 'pers' => $rearranged[2], 'multiple_by' => $multiply_by, 'total_score' => $total_score, 'report_array' => $report_array, 'p_match_sort' => $p_match_sort);
	
	return $message_values;
	
}

//	------------------------------------------------------------------ //

$delete_active = false;

if(@$_POST['evaluate']) {
	
	if(isset($_POST['report'])) {
		$report = strtolower(trim($_POST['report'], ' .,()!?�\''));
		
		if(!empty($report)) {
	
			if(isset($_POST['win_loss'])) {	
				$win_loss = $_POST['win_loss'];
				
				if(!empty($win_loss)) {
					
					if(isset($_POST['teams'])) {
						$team = $_POST['teams'];
						
						if(!empty($team)) {
							
							if(isset($_POST['date'])) {
								$date = $_POST['date'];
								
								if(!empty($date)) {
									
									if(@$_POST['sort'] == 'sort') {
										$report_sorted = sort_report($report, $team);
									} else {
										$report_sorted = $report;
									}
									
									$message_values = calculate_partial_scores($team, $date, $win_loss, $report_sorted, 0);
									$delete_active = true;
																
								} else {
									$error_message = 'Please select a date!';
								}					
							
							}
							
						}
						
					}
								
				}
			}
						
		} else {
			$error_message = 'Please fill in some text to evaluate!';
		}
	}
}

if(@$_POST['delete']) {
	
	if(isset($_POST['team_delete'])) {
		$team = $_POST['team_delete'];
		if(!empty($team)) {
			
			$query = "DELETE FROM $team ORDER BY id DESC LIMIT 1";
			if(mysql_query($query)){
				$error_message = 'Deleted 1 line from '.array_search($team, $teams);
				calculate_scores($team, 0);
				calculate_scores($team, $global_limit);
			} else {
				echo 'Delete failed! MySQL error!';
				echo mysql_error();
			}
			
		}
	}
}

if(@$_POST['refresh']) {
	
	foreach($teams as $team) {
		
		$query = "SELECT date, win_loss, report FROM $team";
		if($result = mysql_query($query)){
			while ($games = mysql_fetch_assoc($result)) {
				calculate_partial_scores($team, $games['date'], $games['win_loss'], $games['report'], 1);
			}
		} else { echo mysql_error(); }
		
		calculate_scores($team, $global_limit);
		calculate_scores($team, 0);
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
		<li>Permanence score: <?php echo @$message_values['perm']; ?></li>
		<li>Pervasiveness score: <?php echo @$message_values['perv']; ?></li>
		<li>Personalization score: <?php echo @$message_values['pers']; ?></li>
	</ul>
	
	<ul id="total-scores">
		<li><?php if(@$_POST['evaluate']) {
							if(@$message_values['total_score'] === 'NULL') {
								echo 'Score can\'t be included!';
							}	else {
									echo 'Multiple by: '.@$message_values['multiple_by'].'x';
								}
						} else {
							echo 'Multiple by: '; 
						}
			?>
		</li>
		<li>Total score: <strong><?php echo @$message_values['total_score']; ?></strong></li>
	</ul>
	
</section>

<section id="error-message"><?php echo @$error_message; ?></section>

<section class="main_form">
	<form action="evaluate.php" method="POST">
		Text to be evaluated: <br>
		<textarea name="report" cols="60" rows="12"><?php echo @hide($message_values['report_array'], $message_values['p_match_sort']); ?></textarea> <br> <br>
		After
		'Win': <input type="radio" name="win_loss" value="w" /> or 
		'Loss': <input type="radio" name="win_loss" value="l" checked />
		'Sort': <input type="checkbox" name="sort" value="sort" checked />
		
		<br> <br>
		Date: <input type="date" name="date" value="<?php echo @$date; ?>" /> 
		
		<select name="teams">
			<?php
				foreach($teams as $team_long => $team_short) {
					if(@$team == $team_short) {
						$selected = ' selected';
					} else {
						$selected = '';
					}
					echo '<option value="'.$team_short.'"'.$selected.'>'.$team_long.'</option>';
				}
			?>
		</select>
		<br> <br>
		<input type="submit" name="delete" value="Delete last entry" <?php if(!$delete_active) { echo 'disabled'; } ?> />
		<input type="submit" name="evaluate" value="Evaluate" /> <br> <br>
		<input type="text" name="team_delete" value="<?php echo $team; ?>" hidden />
		
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
	
	if($result = mysql_query($query)) {
		
		echo '<table><tr><th>Team<input type="submit" name="sort_name" value=" " class="sort-buttons" /></th><th>Score (last '.$global_limit.')<input type="submit" name="sort_last" value=" " class="sort-buttons" /></th><th>Score (all)<input type="submit" name="sort_all" value=" " class="sort-buttons" /></th></tr>';
		while($team_scores = mysql_fetch_assoc($result)) {
			echo '<tr><td class="teams-main">'.array_search($team_scores['team'], $teams).'</td><td>'.round($team_scores['score_last'], 2).'</td><td>'.round($team_scores['score_all'], 2).'</td></tr>';
		}
		echo '</table><br>';
		
	} else {
		echo mysql_error();
	}
										
	?>
	
		<input type="submit" name="refresh" value="Refresh scores" />
	</form>
</section>

</div>
	
</body>

</html><?php 

require 'connect.inc.php';
require 'func.inc.php';
require 'global_vars.inc.php';

header('Content-type: text/html; charset=utf-8');

function rec_sort($paragraph, $pos) {
	
	$quotation_marks = array('"','“','”');
	$i = 0;
	$new_pos = FALSE;
	
	foreach($quotation_marks as $qmark) {
		$positions[$i] = strpos($paragraph, $qmark, $pos);
		
		if($positions[$i] !== FALSE) {
			if($new_pos === FALSE) {
				$new_pos = $positions[$i];
			} elseif($positions[$i] < $new_pos) {
				$new_pos = $positions[$i];
			}
		}
		$i++;
	}
	
	if($new_pos !== FALSE) {
		$pos_and_com = rec_sort($paragraph, $new_pos + 1);
		$pos_and_com['counter'] += 1;
		
		$comment = $pos_and_com['comment'];
		$commenter = $pos_and_com['commenter']; 
		
		if($pos_and_com['counter'] != 1) {
			$para_part = substr($paragraph, $new_pos, $pos_and_com['deeper_pos'] - $new_pos + 1);
			if($pos_and_com['counter'] % 2 != 0) {		
				$commenter = $para_part . ' ' . $commenter;
			} else {
				$comment = $para_part . ' ' . $comment;
				if($pos == 0) {
					$commenter = substr($paragraph, 0, $new_pos) . ' ' . $commenter;
				}
			}		
		} else {
			$commenter = substr($paragraph, $new_pos + 1) . ' ' . $commenter;
		}
		
	} else {
		$pos_and_com['counter'] = 0;
		$comment = '';
		$commenter = '';
	}
	
	$tmp = '';
	if(!$pos) {
		if($pos_and_com['counter'] % 2 != 0) {
			$tmp = $comment;
			$comment = $commenter;
			$commenter = $tmp;
		}
	}
	
	$return_values = array('counter' => $pos_and_com['counter'], 'deeper_pos' => $new_pos, 'comment' => $comment, 'commenter' => $commenter);
//	echo '<pre>';
	//	var_dump($return_values);
	//echo '</pre>';
	
	return $return_values;
}

//	-----------------------------------------------------------------	//

function sort_report($report, $team) {
	
	$paragraphs = explode("\n", $report);
	$comment_details = array();
	
	foreach($paragraphs as $paragraph) {
	
		$paragraph_no_tags = strip_tags($paragraph);
		$sorted_paragraph = rec_sort($paragraph_no_tags, 0);
					
		if($sorted_paragraph['counter']) {
			
			if(strlen($sorted_paragraph['commenter']) <= 1) {
				$count = count($comment_details);
				$comment_details[$count - 1]['comment'] .= $sorted_paragraph['comment'];
			} else {
				array_push($comment_details, $sorted_paragraph);
			}
			
		}

	}
	
	$team_members = file('rosters/'.$team.'.txt');
	
	$to_evaluate = '';
	foreach($comment_details as $comment_detail) {
		foreach($team_members as $member) {
			$member_trimmed = trim($member);
			if(stripos($comment_detail['commenter'], $member_trimmed) !== FALSE) {
				$to_evaluate .= $comment_detail['comment'];
				break;
			}
		}
	}
		
	return $to_evaluate;
}

// ------------------------------------------------------------------ //

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

function rearrange_scores($p_scores, $win_loss) {
	
	$rearranged[4] = 0;
					
	for($i=0;$i<4;$i++) {
		
		$divide = 0;
		$p = 0;
		$inv = 0;
	
		if($p_scores[$i] != 0) {
			if($p_scores[$i+4] != 0) {		
				$divide = 2;
				$p = 7;
				$inv = 7;
			} else {	
				$divide = 1;
				$p = 7;
				$inv = 0;
			}
		} elseif($p_scores[$i+4] != 0) {
					$divide = 1;
					$p = 0;
					$inv = 7;
				}
				 	
			if($divide) {
			
				if($win_loss == 'l') {
						$rearranged[$i] = ($p_scores[$i]+($inv-$p_scores[$i+4]))/$divide;
				} else {
						$rearranged[$i] = ($p-$p_scores[$i]+($p_scores[$i+4]))/$divide;
				}
				
			} elseif($i<4) {
				$rearranged[$i] = 'NULL';
				$rearranged[4]++;
			}
		
	}
	
	return $rearranged;
	
}

//---------------------------------------------------------------------

function db_input($team, $date, $win_loss, $report, $rearranged, $total_score, $limit, $update) {

	$already_evaluated = date_check($team, $date);
	
	if($update) {
		
		$query = "UPDATE $team SET perm=$rearranged[0] , perv=$rearranged[1] , pers=$rearranged[2] , total_score=$total_score WHERE date='$date'";
		if(!mysql_query($query)) { echo mysql_error(); }
	
	}elseif(!$already_evaluated) {
		
		$query = 'INSERT INTO '.$team.' (id, date, win_loss, report, perm, perv, pers, total_score) VALUES (NULL, \''.$date.'\', \''.$win_loss.'\', "'.$report.'", '.$rearranged[0].', '.$rearranged[1].', '.$rearranged[2].', '.$total_score.')';
		if(mysql_query($query)) {
				calculate_scores($team, 0);
				calculate_scores($team, $limit);
		} else {
			echo mysql_error();
		}
		
	} else {
		global $error_message;
		$error_message = 'Match had been already evaluated!';
	}
		
}

// ------------------------------------------------------------------ //

function calculate_partial_scores($team, $date, $win_loss, $report, $update) {
	
	//permanence (maradandóság)
	$per[0] = file('dimensions/permanence.txt');
	$per[4] = file('dimensions/permanence_inv.txt');
	
	//pervasiveness (elterjedtség)
	$per[1] = file('dimensions/pervasiveness.txt');
	$per[5] = file('dimensions/pervasiveness_inv.txt');
	
	//personalization (megszemélyesítés)
	$per[2] = file('dimensions/personalization.txt');
	$per[6] = file('dimensions/personalization_inv.txt');
	
	//distorsion (torzítás)
	$per[3] = file('dimensions/dist_neg.txt');				
	$per[7] = file('dimensions/dist_pos.txt');				
	
	$report_array = explode(' ', $report);
	$p_match_sort = array();
	$match_all = 0;													
	
	for($i=0;$i<8;$i++) {
		
		$p_match[$i] = p_match($report_array, $per[$i]);
	
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
	
	foreach ($report_array as $rword) {
		$rword_trimmed = trim($rword, ' .,()!?�"“”\'');
		foreach ($p_match_sort as $sorted) {
			if ($rword_trimmed == $sorted) {
				$match_all++;
			}
		}
	}	
	
	if($match_all) {
		for($i=0;$i<8;$i++) {						
			$p_scores[$i] = round((count($p_match[$i])/($match_all)) / (1/7));
		}
		$rearranged = rearrange_scores($p_scores, $win_loss);
	} else {
		$rearranged = array('NULL','NULL','NULL', 'NULL', 3);
	}
	
	$multiply_by = 0;
	if($rearranged[4] == 3) {
			$total_score = 'NULL';
	} elseif($rearranged[3] == 'NULL') {
			$multiply_by = 3/(3-$rearranged[4]);
			$total_score = ($rearranged[0]+$rearranged[1]+$rearranged[2])*$multiply_by;
		} else {
			$multiply_by = 3/(3-$rearranged[4]);
			$total_score = ($rearranged[0]+$rearranged[1]+$rearranged[2])*$multiply_by + $rearranged[3]/2;
		}
	
	$save_report = str_replace('"', "", $report);
	global $global_limit;
							
	db_input($team, $date, $win_loss, $save_report, $rearranged, $total_score, $global_limit, $update);
	
	$message_values = array('perm' => $rearranged[0], 'perv' => $rearranged[1], 'pers' => $rearranged[2], 'multiple_by' => $multiply_by, 'total_score' => $total_score, 'report_array' => $report_array, 'p_match_sort' => $p_match_sort);
	
	return $message_values;
	
}

//	------------------------------------------------------------------ //

$delete_active = false;

if(@$_POST['evaluate']) {
	
	if(isset($_POST['report'])) {
		$report = strtolower(trim($_POST['report'], ' .,()!?�\''));
		
		if(!empty($report)) {
	
			if(isset($_POST['win_loss'])) {	
				$win_loss = $_POST['win_loss'];
				
				if(!empty($win_loss)) {
					
					if(isset($_POST['teams'])) {
						$team = $_POST['teams'];
						
						if(!empty($team)) {
							
							if(isset($_POST['date'])) {
								$date = $_POST['date'];
								
								if(!empty($date)) {
									
									if(@$_POST['sort'] == 'sort') {
										$report_sorted = sort_report($report, $team);
									} else {
										$report_sorted = $report;
									}
									
									$message_values = calculate_partial_scores($team, $date, $win_loss, $report_sorted, 0);
									$delete_active = true;
																
								} else {
									$error_message = 'Please select a date!';
								}					
							
							}
							
						}
						
					}
								
				}
			}
						
		} else {
			$error_message = 'Please fill in some text to evaluate!';
		}
	}
}

if(@$_POST['delete']) {
	
	if(isset($_POST['team_delete'])) {
		$team = $_POST['team_delete'];
		if(!empty($team)) {
			
			$query = "DELETE FROM $team ORDER BY id DESC LIMIT 1";
			if(mysql_query($query)){
				$error_message = 'Deleted 1 line from '.array_search($team, $teams);
				calculate_scores($team, 0);
				calculate_scores($team, $global_limit);
			} else {
				echo 'Delete failed! MySQL error!';
				echo mysql_error();
			}
			
		}
	}
}

if(@$_POST['refresh']) {
	
	foreach($teams as $team) {
		
		$query = "SELECT date, win_loss, report FROM $team";
		if($result = mysql_query($query)){
			while ($games = mysql_fetch_assoc($result)) {
				calculate_partial_scores($team, $games['date'], $games['win_loss'], $games['report'], 1);
			}
		} else { echo mysql_error(); }
		
		calculate_scores($team, $global_limit);
		calculate_scores($team, 0);
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
		<li>Permanence score: <?php echo @$message_values['perm']; ?></li>
		<li>Pervasiveness score: <?php echo @$message_values['perv']; ?></li>
		<li>Personalization score: <?php echo @$message_values['pers']; ?></li>
	</ul>
	
	<ul id="total-scores">
		<li><?php if(@$_POST['evaluate']) {
							if(@$message_values['total_score'] === 'NULL') {
								echo 'Score can\'t be included!';
							}	else {
									echo 'Multiple by: '.@$message_values['multiple_by'].'x';
								}
						} else {
							echo 'Multiple by: '; 
						}
			?>
		</li>
		<li>Total score: <strong><?php echo @$message_values['total_score']; ?></strong></li>
	</ul>
	
</section>

<section id="error-message"><?php echo @$error_message; ?></section>

<section class="main_form">
	<form action="evaluate.php" method="POST">
		Text to be evaluated: <br>
		<textarea name="report" cols="60" rows="12"><?php echo @hide($message_values['report_array'], $message_values['p_match_sort']); ?></textarea> <br> <br>
		After
		'Win': <input type="radio" name="win_loss" value="w" /> or 
		'Loss': <input type="radio" name="win_loss" value="l" checked />
		'Sort': <input type="checkbox" name="sort" value="sort" checked />
		
		<br> <br>
		Date: <input type="date" name="date" value="<?php echo @$date; ?>" /> 
		
		<select name="teams">
			<?php
				foreach($teams as $team_long => $team_short) {
					if(@$team == $team_short) {
						$selected = ' selected';
					} else {
						$selected = '';
					}
					echo '<option value="'.$team_short.'"'.$selected.'>'.$team_long.'</option>';
				}
			?>
		</select>
		<br> <br>
		<input type="submit" name="delete" value="Delete last entry" <?php if(!$delete_active) { echo 'disabled'; } ?> />
		<input type="submit" name="evaluate" value="Evaluate" /> <br> <br>
		<input type="text" name="team_delete" value="<?php echo $team; ?>" hidden />
		
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
	
	if($result = mysql_query($query)) {
		
		echo '<table><tr><th>Team<input type="submit" name="sort_name" value=" " class="sort-buttons" /></th><th>Score (last '.$global_limit.')<input type="submit" name="sort_last" value=" " class="sort-buttons" /></th><th>Score (all)<input type="submit" name="sort_all" value=" " class="sort-buttons" /></th></tr>';
		while($team_scores = mysql_fetch_assoc($result)) {
			echo '<tr><td class="teams-main">'.array_search($team_scores['team'], $teams).'</td><td>'.round($team_scores['score_last'], 2).'</td><td>'.round($team_scores['score_all'], 2).'</td></tr>';
		}
		echo '</table><br>';
		
	} else {
		echo mysql_error();
	}
										
	?>
	
		<input type="submit" name="refresh" value="Refresh scores" />
	</form>
</section>

</div>
	
</body>

</html>