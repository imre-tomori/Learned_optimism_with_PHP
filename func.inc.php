<?php

require 'global_vars.inc.php';

/*
	Split paragraph at quotation marks to find the comment part of a sentence.
	The part after the quote contains the name of the commenter.
	Counter has the number of the quotation marks. 	
*/

function rec_sort($paragraph, $pos) {
	
	$quotation_marks = array('"', '“', '”', '``', '\'\'');
	$i = 0;
	$new_pos = FALSE;
	
	// $new_pos holds the position of the first quotation mark in the sentence. 
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
		// If quotation mark found, search for the next one from there, offset 1.
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
		// When no more quotation marks, set values to:
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

/*
	Splits the report to paragraphs, then gets the quote and the commenter part from rec_sort function, paragraph by paragraph.
	Searches a name from the team roster in the commenter part, if found	adds the comment to $to_evaluate.
	So all paragraphs quotes will be evaluated if person from right team has been quoted.
*/

function sort_report($report, $team) {
	
	if(is_array($report)){
		//If report array, we assume it is already split (retrieved so by the web crawler).
		$paragraphs = $report;
	} else {
		//Split report by new line.
		$paragraphs = explode("\n", $report);
	}
	
	$comment_details = array();
	
	foreach($paragraphs as $paragraph) {
		
//		echo '<br><b>Inside sort_report paragraph</b><br>';
//		print_r($paragraph);
	
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
	
	//Check if player from evaluated team has been quoted. If yes add comment $to_evaluate.
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


/* -------------------------------------------------------
--- True if date is unique ---
----------------------------------------------------------*/

function date_check($table, $date, $where = NULL) {

$already_evaluated = false;

if($where) {
	$query = "SELECT date FROM $table WHERE $where";
} else {
	$query = "SELECT date FROM $table";
}

if($result = mysqli_query(DBi::$conn, $query)) {
	
	while($dates = mysqli_fetch_assoc($result)) {
		if($date == $dates['date']) {
			$already_evaluated = true;
			break;
		}
	}
	
} else {
	echo mysqli_error(DBi::$conn);
}

return $already_evaluated;

}

/*---------------------------------------------------------------
--- Calculating the last $limit total scores for $team, from $team table.
--- If date supplied, calculation uses evaluated games before that date,
--- if not, then current date will be used. The table 'scores' only
--- will be updated, if no date is supplied.
----------------------------------------------------------------*/

function calculate_scores($team, $limit, $date = 'NULL', $inverse = 'NULL') {
	
		
	if($date) {
		$date_to_use = $date;
	} else {
		$date_to_use = date('Y-m-d', time());
	}
	
	//$date_lookback = date('Y-m-d', strtotime("$date_to_use-42 days"));
	
	/*
	Since some evaluated games conclude in NULL values, we need to expand the selection to double of the actual limit.
	Example: If limit=12, than we choose the last 12 not zero scores from the last 24 games.
	This ensures that we get enough values, but not to old ones in our calculation when having lot of NULL scores. 
	*/
	
	
	if($limit) {
		$limit_doubled = $limit * 2;
		
		$query = "SELECT total_score ".
					"FROM ( ".
								"SELECT date, total_score ".
								"FROM $team ".
								"WHERE date < '$date_to_use' ".
								"ORDER BY date DESC ".
								"LIMIT $limit_doubled ".
							") AS double_range ".
					"WHERE total_score IS NOT NULL ".
					"ORDER BY date DESC ".
					"LIMIT $limit";
		
		//$query = "SELECT total_score FROM (SELECT date, total_score	FROM $team WHERE date < '$date_to_use' ORDER BY date DESC LIMIT $limit_doubled) AS double_range WHERE total_score IS NOT NULL ORDER BY date DESC LIMIT $limit";
		$score_type = 'score_last';
	} else {
		$query = "SELECT total_score FROM $team WHERE total_score IS NOT NULL AND date < '$date_to_use'";
		$score_type = 'score_all';
	}
	
	
	
	if($result = mysqli_query(DBi::$conn, $query)) {
			$score_sum = 0;
			$num_rows = mysqli_num_rows($result);
			
		// Only calculate if we have at leas last $limit number of evaluated games.
		
						
				if($num_rows) {
					$indicator = true;
					
					if($limit && $num_rows != $limit) {
						$indicator = false;
					}
					
					if($indicator) {
						
						if($inverse) {
							while($scores = mysqli_fetch_assoc($result)) {
								$score_sum += 21 - $scores['total_score']; 
							}
						} else {
							while($scores = mysqli_fetch_assoc($result)) {
								$score_sum += $scores['total_score']; 
							}
						}
					
						$score_all = $score_sum/$num_rows;
						
					} else {
					$score_all = 'NULL';
					}
					
					
				} else {
					$score_all = 'NULL';
				}
				
				if($date == 'NULL') {
					$query = "UPDATE scores SET $score_type=$score_all WHERE team='$team'";
					if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
				}
				
			
		
	} else {
		echo mysqli_error(DBi::$conn);
	}
	
	return $score_all;
		
}

/*----------------------------------------------------------------------
--- Calculate the team(s) to bet on ---
--- 1 for visitor, 2 for home, 0 for both, -1 when score(s) are NULL ---
----------------------------------------------------------------------*/

function to_bet_on($visitor_score, $home_score) {
	
	if($visitor_score !== 'NULL' && $home_score !== 'NULL') {
		$difference = abs($visitor_score - $home_score);
		global $global_difference;
		
		if($difference > $global_difference) {
			if($visitor_score < $home_score) {
				$bet_on = 1;
			} else {
				$bet_on = 2;
			}
		} else {
			$bet_on = 0;
		}
	} else {
		$bet_on = -1;
	}
	
	return $bet_on;
}

/*-------------------------------------------------------
--- Compare calculated tipp ($to_bet_on) with actual result and give back profit if $bet and $odds are supplied. ---
--- Return values:
--- $win[0] == 1 won bet
--- $win[0] == 0 lost bet
--- $win[1] == +/- profit
-------------------------------------------------------*/

function compare($to_bet_on, $result, $overtime, $bet = NULL, $odds = NULL) {
	
	if(!$overtime) {
		if($to_bet_on == $result) {
			$win[0] = 1;
			if($result == 1) {
				$win[1] = $bet*$odds[0]-$bet;
			} elseif($result == 2) {
				$win[1] = $bet*$odds[1]-$bet;
			}
		} else {
			$win[0] = 0;
			$win[1] = $bet * (-1);
		}
	} elseif(!$to_bet_on) {
		$win[0] = 1;
		$win[1] = $bet*$odds[2]-$bet;
	} else {
		$win[0] = 0;
		$win[1] = $bet * (-1);
	}
	
	return $win;

}

/*---------------------------------------------------------------
--- Calculates and updates the bet and capitals according to the correct base,
--- to table 'money_flow' to record with id=-1.
----------------------------------------------------------------*/

function update_bet($current_capital_update = NULL) {
	
	$caps = array();
	
	$query = "SELECT game_id FROM results WHERE result IS NULL AND to_bet_on<>-1";
	if($result = mysqli_query(DBi::$conn, $query)) {
		$group_number = 1;
		if($current_capital_update || $group_number = mysqli_num_rows($result)) {
				
			$query2 = "SELECT profit, base_capital, current_capital FROM money_flow WHERE id=-1";
			if($result2 = mysqli_query(DBi::$conn, $query2)) {
	
				$previous_capital = mysqli_fetch_row($result2);
				if($current_capital_update) {
					$previous_capital[2] = $current_capital_update;
				}				
				
				/*
				 If current capital reaches out of range [base_capital/2..base_capital*2],
				 then it changes accordingly.
				*/ 
												
				$prev_cap_double = 2*$previous_capital[1];
				$prev_cap_half = $previous_capital[1]/2;
				
				if($previous_capital[2] >= $prev_cap_double) {
					$base_capital = $prev_cap_double;
				} elseif($previous_capital[2] <= $previous_capital[1]) {
					$base_capital = $prev_cap_half;
				} else {
					$base_capital = $previous_capital[1];
				}
				
				global $percentage;
				
				// Current bet is divided between all matches uploaded, but not bet on yet ($group_number).
				
				$bet_currently = $base_capital * $percentage / $group_number;
				
				$caps[0] = $bet_currently;
				$caps[1] = $base_capital;
				$caps[2] = $previous_capital[2];
				
				$query3 = "UPDATE money_flow SET profit=$caps[0], base_capital=$caps[1], current_capital=$caps[2] WHERE id=-1";

				if(!mysqli_query(DBi::$conn, $query3)) { echo mysqli_error(DBi::$conn); }
					
			}	else {
				echo mysqli_error(DBi::$conn);
			}
				
		}										
	
	} else {
		echo mysqli_error(DBi::$conn);
	}
	
	return $caps;
	
}
		
?>