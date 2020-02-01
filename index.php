<?php

require 'connect.inc.php';
require 'func.inc.php';
require 'global_vars.inc.php';

header('Content-type: text/html; charset=utf-8');

//Variable set to false as default. Used so last input match can be reversed.
$delete_last_active = false;


function calc_scores_and_bet ($team_visitor, $team_home, $date) {
	
	global $global_limit;
	
				$visitor_score = calculate_scores($team_visitor, $global_limit, $date);
				$home_score = calculate_scores($team_home, $global_limit, $date);
							
				$to_bet_on = to_bet_on($visitor_score, $home_score);
				
	return $result = array($visitor_score, $home_score, $to_bet_on); 
			
}
			
// Update 'results' table with teams scores and who to bet on for all matches.

function update_scores() {
	
	global $global_limit;
	
	$query = "SELECT game_id, date, visitor, home FROM matches";
	if($result = mysqli_query(DBi::$conn, $query)) {
		while($matches = mysqli_fetch_assoc($result)) {
			
			$scores_and_bet = calc_scores_and_bet($matches['visitor'], $matches['home'], $matches['date']);
				
			$query = 'UPDATE results SET visitor_score='.$scores_and_bet[0].', home_score='.$scores_and_bet[1].', to_bet_on='.$scores_and_bet[2].' WHERE game_id='.$matches['game_id'];
			if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
			
		}
	} else { echo mysqli_error(DBi::$conn); }	
	
}

/*** Input new match to 'matches' table after submitting this form.
First performs checks if selections are selected and if match is not already evaluated.
Also updates 'results' table with scores and who to bet on.
***/

if(@$_POST['new_game']) {
	
	if(isset($_POST['date'])) {
		$date = $_POST['date'];
		if(!empty($date)) {
			
			if(isset($_POST['team_visitor'])) {
				$team_visitor = $_POST['team_visitor'];
				
				if(isset($_POST['team_home'])) {
					$team_home = $_POST['team_home'];
					
					if(!empty($team_visitor)) {
						if(!empty($team_home)) {
							
							if(isset($_POST['odds_visitor']) && isset($_POST['odds_home']) && isset($_POST['odds_draw'])) {
								$odds_visitor = $_POST['odds_visitor'];
								$odds_home = $_POST['odds_home'];
								$odds_draw = $_POST['odds_draw'];
								
								if($team_home != $team_visitor) {
									$already_evaluated = date_check('matches', $date, "visitor='$team_visitor' AND home='$team_home'");
									
									if(!$already_evaluated) {
										$query = "INSERT INTO matches(date, visitor, home, odds_v, odds_h, odds_d) VALUES ('$date', '$team_visitor', '$team_home', $odds_visitor, $odds_home, $odds_draw)";
										if(!$matches = mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
										
										$game_id = mysqli_insert_id(DBi::$conn);
										
										$scores_and_bet = calc_scores_and_bet($team_visitor, $team_home, $date);
										
										$query = "INSERT INTO results(game_id, visitor_score, home_score, to_bet_on) VALUES ($game_id, $scores_and_bet[0], $scores_and_bet[1], $scores_and_bet[2])";
										if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
																			
										$delete_last_active = true;
										
									} else {
										$error_message = 'Match already in database!<br>';
									}
										
								} else {
									$error_message = 'You can\'t choose the same team!<br>';
								}
									
							} else {
								echo mysqli_error(DBi::$conn);
							}
										
						} else {
							$error_message = 'Please select a home team!';
						}
					}
				} 
			} else {
				$error_message = 'Please select the visitor team!<br>';
			}
			
		} else {
			$error_message = 'Please choose a date!<br>';
		}
	}
	
}

/***
	After games have been updated they show up in the Ongoing games queue.
	After matches finished we can update end results to 'results' table and calculate money flow.
***/

if(@$_POST['recent_games']) {
	
	if(isset($_POST['game_result'])) {
		$game_result = $_POST['game_result'];
		$group_number = count($game_result);
		if(!empty($game_result)) {
	
			$caps = update_bet();
			
			//Update results table with results
				
			foreach ($game_result as $result) {
				
				if($result) {
					$winner = substr($result, 0, 1);
					$recent_game_id = substr($result, 2);
	
					if(isset($_POST['overtime'])) {
						$overtime = $_POST['overtime'];
						$ot_game = false;
						
						foreach ($overtime as $ot) {
							if($recent_game_id == $ot) {
								$ot_game = true;
								break;
							}
						}
						
						if($ot_game) {
							$query = "UPDATE results SET result='$winner', overtime=1 WHERE game_id=$recent_game_id";
						} else {
							$query = "UPDATE results SET result='$winner', overtime=0 WHERE game_id=$recent_game_id";								
						}
														
					} else {
						$query = "UPDATE results SET result='$winner', overtime=0 WHERE game_id=$recent_game_id";
					}
					
					if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
				
				}
			}
			
			//Check if the bet has won, and calculate the profit.			
			
			$current_capital = 0;
			
			foreach ($game_result as $result) {
		
				if($result) {
					$winner = substr($result, 0, 1);
					$recent_game_id = substr($result, 2);
							
					$query2 = "SELECT to_bet_on, result, overtime FROM results WHERE game_id=$recent_game_id";
					if($result2 = mysqli_query(DBi::$conn, $query2)) {
						$compare = mysqli_fetch_row($result2);
						
						if($compare[0] != -1) {	
							$query = "SELECT odds_v, odds_h, odds_d FROM matches WHERE game_id=$recent_game_id";
							if($result3 = mysqli_query(DBi::$conn, $query)) {
								$odds = mysqli_fetch_row($result3);
								
								$compare_result = compare($compare[0], $compare[1], $compare[2], $caps[0], $odds);
								$profit = $compare_result[1];
								
								// At first game set base capital as current capital and add profit. From second time onwards, add profit to current capital.
								
								if(!$current_capital) {																							
									$current_capital = $caps[2] + $profit;
								} else {
									$current_capital += $profit;
								}
							
								$query = "INSERT INTO money_flow(id, profit, base_capital, current_capital) VALUES ($recent_game_id, $profit, $caps[1], $current_capital)";
								if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
								
								$query = "UPDATE money_flow SET current_capital=$current_capital WHERE id=-1";
								if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
							
							} else {
								echo mysqli_error(DBi::$conn);
							}
						} else {
								$query = "INSERT INTO money_flow(id, profit) VALUES ($recent_game_id, 0)";
								if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
						}
						
					} else {
						echo mysqli_error(DBi::$conn);
					}
							
				}
			}
		
			update_bet();	
		}	
	}
			
} 

// Deletes last inputted match. Only available if $delete_last_active is true.

if(@$_POST['delete_last']) {
			
	if(isset($_POST['game_id'])) {
		$game_id = $_POST['game_id'];
		if(!empty($game_id)) {
				
				$query1 = "DELETE matches, results FROM matches, results WHERE matches.game_id=$game_id AND results.game_id=$game_id";
				if(mysqli_query(DBi::$conn, $query1)){
					$error_message = 'Deleted 1 line from \'matches\' and \'results\'';
				} else {
					echo 'Delete failed! mysqli error!<br>';
					echo mysqli_error(DBi::$conn);
				}
			
		}	
	}
	
}

if(@$_POST['update_scores']) {
	update_scores();
}

update_bet();

?>

<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" href="css/style.css">
	<link rel="stylesheet" href="css/menu.css">
	<link rel="stylesheet" href="css/style_matches.css">
</head>

<body>
	
<div id="main-wrapper">

	<?php include 'menu.html'; ?>
	<form action="index.php" method="POST">
		<?php echo $error_message; ?>
				
		<section id="left">
			<div class="team">
				<h3>Visitor:</h3>
				<select name="team_visitor" size="12">
				<?php
					foreach($teams as $team_long => $team_short) { 
						if($team_short == @$_POST['team_visitor']) {
							echo '<option value="'.$team_short.'" selected>'.$team_long.'</option><br>';
						} else {
							echo '<option value="'.$team_short.'">'.$team_long.'</option><br>';
						}
					}
				?>
				</select>
			</div>
			<div class="team">
				<h3>Home:</h3>
				<select name="team_home" size="12">
				<?php
					foreach($teams as $team_long => $team_short) { 
						if($team_short == @$_POST['team_home']) {
							echo '<option value="'.$team_short.'" selected>'.$team_long.'</option><br>';
						} else {
							echo '<option value="'.$team_short.'">'.$team_long.'</option><br>';
						}
					}
				?>
				</select>
			</div>
		</section>
		
		<section id="right">
			<article id="odds">
				<h3>Odds:</h3>
				
				<table>
					<tr>
						<th>Visitor</th>
						<th>Home</th>
						<th>Draw</th>
					</tr>
					<tr>
						<td>
							<select name="odds_visitor">
								<?php													 
									for($i=2;$i<=2.8;$i+=0.05) {
										settype($i, 'string');
										if($i == @$_POST['odds_visitor']) {
											echo '<option value="'.$i.'" selected>'.$i.'</option>';
										} elseif($i == 2.5) {
											echo '<option value="'.$i.'" selected>'.$i.'</option>';
										} else {
											echo '<option value="'.$i.'">'.$i.'</option>';
										}
									}
								?>
							</select>
						</td>
						<td>
							<select name="odds_home">
								<?php
									for($i=2.00;$i<=2.8;$i+=0.05) {
										settype($i, 'string');
										if($i == @$_POST['odds_home']) {
											echo '<option value="'.$i.'" selected>'.$i.'</option>';
 										} elseif($i == 2.5) {
											echo '<option value="'.$i.'" selected>'.$i.'</option>';
										} else {
											echo '<option value="'.$i.'">'.$i.'</option>';
										}
									}
								?>
							</select>
						</td>
						<td>
							<select name="odds_draw">
								<?php
									for($i=3.50;$i<=4.5;$i+=0.05) {
										settype($i, 'string');
										if($i == @$_POST['odds_draw']) {
											echo '<option value="'.$i.'" selected>'.$i.'</option>';
										} elseif($i == 3.8) {
											echo '<option value="'.$i.'" selected>'.$i.'</option>';
										} else {
											echo '<option value="'.$i.'">'.$i.'</option>';
										}
									}
								?>
							</select>
						</td>
					</tr>
				</table>
			</article>	
			
			<article id="submit">
				<input type="date" name="date" value="<?php echo @$_POST['date']; ?>" />
				<input type="submit" name="new_game" value="Input" />
				<input type="text" name="game_id" value="<?php echo @$game_id; ?>" hidden /> <br>
				<input type="submit" name="delete_last" value="Delete last entry" <?php if(!$delete_last_active){ echo 'disabled'; } ?> />
				<input type="submit" name="update_scores" value="Update Scores" />
			</article>		
		</section>	
	</form>
	
	<section id="ongoing-games">
	
	<h3>Ongoing games</h3>
	
	<form action="index.php" method="POST">
	
	<table>
		<tr>
			<th>Date</th>
			<th>Visitor</th>
			<th>OP score</th>
			<th>Home</th>
			<th>OP score</th>
			<th>Result</th>
			<th>Overtime</th>
			<th>Bet</th>
		</tr>
		<?php
		$query = "SELECT m.game_id, m.date, m.visitor, m.home, r.visitor_score, r.home_score, r.to_bet_on FROM matches as m INNER JOIN results as r ON m.game_id=r.game_id WHERE r.result IS NULL ORDER BY m.date";
		if($result = mysqli_query(DBi::$conn, $query)){
			$query2 = "SELECT profit FROM money_flow WHERE id=-1";
			if($result2 = mysqli_query(DBi::$conn, $query2)) {
				$bet_current = mysqli_fetch_row($result2);
				
				while($ongoing_games = mysqli_fetch_assoc($result)) {
					$game_idnf = $ongoing_games['game_id'];
					$game_date = $ongoing_games['date'];
					$game_visitor = $ongoing_games['visitor'];
					$game_home = $ongoing_games['home'];
					$visitor_score = $ongoing_games['visitor_score'];
					$home_score = $ongoing_games['home_score'];
					
					$to_bet_on = $ongoing_games['to_bet_on'];
					$bet_on_visitor = '';
					$bet_on_home = '';	
					
					if($to_bet_on != -1) {
						if($to_bet_on) {
							if($to_bet_on == 1) {
								$bet_on_visitor = ' class="to-bet-on"';
							} elseif($to_bet_on == 2) {
								$bet_on_home = ' class="to-bet-on"';
							}
						} else {
							$bet_on_visitor = ' class="to-bet-on"';
							$bet_on_home = ' class="to-bet-on"';
						}
					}
									
					echo '<tr><td>'.$game_date.'</td>'.
							'<td'.$bet_on_visitor.'>'.array_search($game_visitor, $teams).'</td>'.
							'<td>'.round($visitor_score, 3).'</td>'.
							'<td'.$bet_on_home.'>'.array_search($game_home, $teams).'</td>'.
							'<td>'.round($home_score, 3).'</td>'.
							'<td><select name="game_result[]">'.
								'<option value=""></option>'.
								'<option value="1_'.$game_idnf.'">Visitor</option>'.
								'<option value="2_'.$game_idnf.'">Home</option>'.
							'</select></td>'.
							'<td><input type="checkbox" name="overtime[]" value="'.$game_idnf.'" /></td>'.
							'<td>'.$bet_current[0].'</td></tr>';
				}
			
			} else {
				echo mysqli_error(DBi::$conn);
			}
			
		} else {
			echo mysqli_error(DBi::$conn);
		}
		?>
	</table>
	
	<input type="submit" name="recent_games" value="Input results" />
	<input type="submit" name="recent_games_refresh" value="Refresh all results" disabled />
	
	</form>
	
	</section>
	
	<section id="finished-games">
	
	<h3>Finished games</h3>
	
	<table>
		<tr>
			<th>Date</th>
			<th>Visitor</th>
			<th>Home</th>
			<th>Profit</th>
			<th>Profit Stream</th>
		</tr>
		<?php 
			
		$query = "SELECT m.date, m.visitor, m.home, r.to_bet_on, r.result, r.overtime, mf.profit FROM matches m INNER JOIN results r ON m.game_id=r.game_id INNER JOIN money_flow mf ON r.game_id=mf.id WHERE r.result IS NOT NULL ORDER BY m.date DESC LIMIT 10";
		if($result = mysqli_query(DBi::$conn, $query)) {
			while($finished_games = mysqli_fetch_assoc($result)) {
				$finished_date = $finished_games['date'];
				$finished_visitor = $finished_games['visitor'];
				$finished_home = $finished_games['home'];
				$finished_to_bet_on = $finished_games['to_bet_on'];
				$finished_result = $finished_games['result'];
				$finished_ot = $finished_games['overtime'];
				$finished_profit = $finished_games['profit'];
				
				$finished_profit_stream = 0;
				$query2 = "SELECT m.date, mf.profit FROM matches m INNER JOIN money_flow mf ON m.game_id=mf.id WHERE date<='$finished_date'";
				if($result2 = mysqli_query(DBi::$conn, $query2)) {
					while($profits = mysqli_fetch_assoc($result2)) {
						$finished_profit_stream += $profits['profit'];
					}
				} else { echo mysqli_error(DBi::$conn); }
				
				$visitor_class = '';
				$home_class = '';
					
					if($finished_ot) {
						$visitor_class = ' class="finished-winner-teams"';
						$home_class = ' class="finished-winner-teams"'; 
					} elseif($finished_result == 1) {
						$visitor_class = ' class="finished-winner-teams"';
					} elseif($finished_result == 2) {
						$home_class = ' class="finished-winner-teams"';
					}
				
				if($finished_to_bet_on != -1) {
					$finished_winnner = compare($finished_to_bet_on, $finished_result, $finished_ot);
					if($finished_winnner[0]) {
						$finished_class = ' class="finished-winner"';
					} else {
						$finished_class = ' class="finished-looser"';
					}
				} else {
					$finished_class = ' class="finished-non-evaluatable"';
				}
				
				if($finished_profit_stream > 0) {
					$profit_stream_class = ' class="finished-profit-stream-positive"';
				} else {
					$profit_stream_class = ' class="finished-profit-stream-negative"';
				}
				
				echo '<tr'.$finished_class.'><td>'.$finished_date.'</td><td'.$visitor_class.'>'.array_search($finished_visitor, $teams).'</td><td'.$home_class.'>'.array_search($finished_home, $teams).'</td><td>'.$finished_profit.'</td><td'.$profit_stream_class.'>'.$finished_profit_stream.'</td></tr>';
				
			}
		} else {
			echo mysqli_error(DBi::$conn);
		}
		
		?>
	</table>
	</section>
	
	<section id="capital">
	
	<h3>Current capital</h3>
		<?php
			$query = "SELECT current_capital FROM money_flow";
			if($result = mysqli_query(DBi::$conn, $query)) {
				$main_capital = mysqli_fetch_row($result);
				echo '<p>'.$main_capital[0].'</p>';
			} else {
				echo mysqli_error(DBi::$conn);
			}
		?>
	</section>
	
</div>

</body>

</html>