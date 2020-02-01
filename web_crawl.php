<?php

require 'connect.inc.php';
require 'func.inc.php';
require 'global_vars.inc.php';
require 'simple_html_dom.php';

header('Content-type: text/html; charset=utf-8');

/*----------------------------------------------------------------------------
--- Retrieves information about the games for one season per team. 
--- Calculates only final games.
----------------------------------------------------------------------------*/

function crawl_games_per_season($param_season, $param_gameType, $param_team) {
	
	$base_link = 'http://www.nhl.com/ice/schedulebyseason.htm';
		
	$html_games = file_get_html("$base_link?season=$param_season&gameType=$param_gameType&team=$param_team");
	
	if(!$html_games) {
		echo 'GET HTML FALSE <br>';
	}
	
	$games_list = array();
	
	
	foreach($html_games->find('table[class=data schedTbl] tbody tr') as $match) {
		
		//Don't take into account where column is less than 6, filtering out header and blank rows.
		if(count($match->find('td')) == 6) {
			
			 //Need to check if it's the FINAL result by checking if it starts with 'FINAL:'.
			 //Additionally read out teams involved, determine winner/loser, and if there has been overtime.
			 
			 $item_result = $match->find('td.tvInfo', 0)->plaintext;
			 $item_result_split = explode(' ', $item_result);
			 
			 
			 if($item_result_split[1] == 'FINAL:') {
			 	
				$item['final'] = $item_result_split[1];
				$item['visiting_team'] = $item_result_split[3];
				$item['visiting_team_score'] = trim($item_result_split[4], '()');
				$item['home_team'] = $item_result_split[8];
				$item['home_team_score'] = trim($item_result_split[9], '()');
				$item['overtime'] = $item_result_split[10];
				 
				$item_date = $match->find('td.date .skedStartDateSite', 0)->plaintext;
				$item['date'] = date("Y-m-d", strtotime($item_date));
				 
				//No need for retrieve teams directly, because we need to analyze result string above, and retrieve more information.
				//$item['visiting_team'] = $match->find('td.team', 0)->plaintext;
				//$item['home_team'] = $match->find('td.team', 1)->plaintext;
				 
				//Read out the link to the "Recap" article.
				$item['recap'] = $match->find('td.skedLinks a', 0)->href;
				 
				$games_list[] = $item;
				
			}
		
		}
	
	}
	
	return $games_list;

}

/*----------------------------------------------------------------------------
--- Retrieves the report for one game and one team from NHL.com, uses
--- sort_report to determine the relevant parts and updates the database.
----------------------------------------------------------------------------*/

function crawl_report_per_games($recap_link, $team, $sort_report = NULL) {
	
	$html_recap = file_get_html($recap_link);
	foreach($html_recap->find('div[class=contentPad article] p') as $recap_paragraph){
		$report_paragraphs[] = $recap_paragraph->plaintext;
	}

	// Some Recap reports doesn't seem to contain <p> tags.
	// In this case we get all the div in a string and compile it further.
	// The sorting algorithim can handle strings and arrays as well. 
	if(empty($report_paragraphs)) {
		foreach($html_recap->find('div[class=contentPad article]') as $recap_paragraph){
			$report_paragraphs_empty[] = $recap_paragraph->plaintext;
		}
		
		if($sort_report) {		
			for($i=0; $i<count($report_paragraphs_empty); $i++){
				$report_paragraphs_empty[$i] = html_entity_decode($report_paragraphs_empty[$i]);
			}
			$report_paragraphs = $report_paragraphs_empty[0];
			$report_to_evaluate = sort_report($report_paragraphs, $team);
		} else {
			$report_to_evaluate = $report_paragraphs_empty[0];
		}
		

	} else {
	
		if($sort_report) {
			// If <p> tags found continue with decoding tags.
			for($i=0; $i<count($report_paragraphs); $i++){
				$report_paragraphs[$i] = html_entity_decode($report_paragraphs[$i]);
			}
			$report_to_evaluate = sort_report($report_paragraphs, $team);
		} else {
			$report_to_evaluate = '';
			foreach ($report_paragraphs as $paragraph) {
			$report_to_evaluate .= $paragraph; 
			}
		}
	
	}

	//print_r($report_paragraphs);
	
	return $report_to_evaluate;
	
	/*
	$query = "INSERT INTO $team(id, report) VALUES(NULL, $report_to_evaluate)";
	
	if(mysqli_query(DBi::$conn, $query)) {
		echo 'Updated';
	} else {
		echo mysqli_error(DBi::$conn);
	}
	*/
}

/*----------------------------------------------------------------------------
--- Get archived games results from file, where odds where close.
----------------------------------------------------------------------------*/

function get_archieved_games_results($odds_season) {
	
//$odds_season = '2013-14';

/* odds_limit is used to check if the games is an even match. If not, we don't bother using it. 
	The odds archive files contains multiple odds notations for a two way situation, including
	the US odds (like -125). The US odds is only available for the favorite team, so only need
	to check one end of the	range. Ex.: -130 is ~1.76 in decimal notation, so it should fit in 
	our range of 1.75 - 2.1.  
*/
//$odds_limit = -130;
$odds_archive = file('odds_archive/nhl odds '.$odds_season.'.csv');

$line_number = 1;

foreach($odds_archive as $odds_line) {
	
	if($line_number != 1) {
		
		$odds_columns = explode(',', $odds_line);
		
		$i=0;
						
		foreach($odds_columns as $column) {
			switch($i) {
				case 0:
					$current_date = $column;
				case 3:
					$current_team = $column;
				case 7:
					$current_score = $column;	
				case 8:
					$current_odds = $column;	
				case 10:
					$current_overtime = $column;	
			}
			$i++;
						
		}
		
		if($line_number % 2 == 0) {
			$visitor_team = $current_team;
			$visitor_score = $current_score;
			$visitor_odds = $current_odds;
			$visitor_overtime = $current_overtime;
		} else {

		
//			$save_to_db = false;
			$save_to_db = true;
			if(substr($current_odds, 0, 1) == '-') {
				/*if($current_odds >= $odds_limit) {
					$save_to_db = true;
				}*/
				$odds_to_use = $current_odds;
			} elseif (substr($visitor_odds, 0, 1) == '-')  {
				/*if($visitor_odds >= $odds_limit) {
					$save_to_db = true;
				}*/
				$odds_to_use = $visitor_odds;
			}
			
			
			if($save_to_db) {
				
				$date_length = strlen($current_date);
				if($date_length == 4) {
					$date_year = substr($odds_season, 0, 4);
					$date_month = substr($current_date, 0, $date_length-2);
				} else {
				 	$date_year = '20'.substr($odds_season, 5, 2);
				 	$date_month = '0'.substr($current_date, 0, $date_length-2);
				 }	
					
				
				$date_day = substr($current_date, $date_length-2, 2);
				$date_to_use = $date_year.'-'.$date_month.'-'.$date_day;
				
				//if($date_to_use >= '2013-10-01' && $date_to_use <= '2014-04-13') {
						
					$message_value[] = $date_to_use.' | '.$visitor_team.'-'.$current_team;
					
					$query = "INSERT INTO matches(date, visitor, home, odds_us) VALUES ('$date_to_use', '$visitor_team', '$current_team', '$odds_to_use')";
					
					if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
					
					$game_id = mysqli_insert_id(DBi::$conn);
					
					if($visitor_score > $current_score) {
						$result = 1;
					} else {
						$result = 2;
					}
					
					//echo 'Visitor OT'.$visitor_overtime.'|';
					//echo 'Home OT'.$current_overtime.'<br>';
					
					if($visitor_overtime == 1 || $current_overtime == 1) {
						$overtime = 1;
					} else {
						$overtime = 0;
					}
					
					//echo 'OT:'.$overtime.'<br>';
					$query2 = "INSERT INTO results(game_id, result, overtime) VALUES ($game_id, $result, $overtime)";
					
					if(!mysqli_query(DBi::$conn, $query2)) { echo mysqli_error(DBi::$conn); }
					
				//}
			}
			
		}
				
		
	}
	
	$line_number++;

}

}

/*----------------------------------------------------------------------------
--- Retrieve roster for the season for all teams, from hockeydb.com
----------------------------------------------------------------------------*/

function crawl_team_rosters($season) {

$base_link = 'http://www.hockeydb.com/ihdb/stats/leagues/seasons/nhl1927'.$season.'.html';
global $teams;

$html_season_standings = file_get_html($base_link);

if($html_season_standings) {
	
	//Retrieve the team links
	foreach($html_season_standings->find('table[class=sortable autostripe] tbody tr') as $team_stats){
			if($team_link = $team_stats->find('td a', 0)) {
				$team_name = $team_link->plaintext;
				$team_href = $team_link->href;
				
				//Retrieve the players.
				if($html_team_roster = file_get_html("http://www.hockeydb.com/ihdb/stats/leagues/seasons/$team_href")) {
					
					$team_members = array();
					//Coaches
					$team_coach_section = $html_team_roster->find('div.tablebg div div', 4);
					foreach($team_coach_section->find('a') as $team_coach) {
						$team_members[] = $team_coach->plaintext;
					}
					
					//Players	
					foreach($html_team_roster->find('div.tablebg table[class=sortable autostripe]') as $team_player_section) {
						foreach($team_player_section->find('tbody tr') as $team_player_stats) {
							if($team_player = $team_player_stats->find('td a', 0)) {
								$team_members[] = $team_player->plaintext;
							}
						}
					}
					
					//Upload data to files in the rosters directory.
					
					if($team_name == 'Arizona Coyotes') {
						$team_roster_handle = fopen('rosters/team_ari.txt', "w");
					} else {
						$team_roster_handle = fopen('rosters/'.$teams[$team_name].'.txt', "w");
					}
					foreach($team_members as $member) {
						//Save only last name
						$name_parts = array();
						$name_parts = explode(' ', $member);
						fwrite($team_roster_handle, end($name_parts)."\n");
					}
					fclose($team_roster_handle);
					$message_value[] = 'Roster updated for team: '.$team_name.'<br>';
					
				} else {
					$message_value[] = 'GET HTML FALSE | TEAM ROSTER <br>';
				}
				//Need to remove break after dev!
				//break;
			}
	}
		
} else {
	$message_value[] = 'GET HTML FALSE | SEASON STANDINGS <br>';
}

}

/*----------------------------------------------------------------------------
--- Start of main program.
----------------------------------------------------------------------------*/

//$team_name_translation = array('cbs' => 'cbj', 'los' => 'lak', 'miw' => 'min', 'mon' => 'mtl', 'tam' => 'tbl', 'was' => 'wsh', 'phx' => 'ari');

$message_value = array();

if(@$_POST['retrieve_rosters']) {
	if(!empty(@$_POST['param_season'])) {
		$param_season = $_POST['param_season'];
		$season = substr($param_season, 4);
		
		crawl_team_rosters($season);
		
	} else {
		$message_value[] = 'Select a season!<br>';	
	}
	
}


if(@$_POST['archieved_games']) {
	if(!empty(@$_POST['param_season'])) {
		$param_season = $_POST['param_season'];
		$season = substr($param_season, 0, 4).'-'.substr($param_season, 6);
		
		get_archieved_games_results ($season);
		
	} else {
		$message_value[] = 'Select a season!<br>';	
	}
	
}

if(@$_POST['sort_report']) {
	$sort_report = true;
} else {
	$sort_report = false;	
}

if(@$_POST['retrieve_data']) {
	
	if(!empty(@$_POST['param_season'])) {
		$param_season = $_POST['param_season'];
		
			if(!empty(@$_POST['param_teams'])) {
				$param_teams = $_POST['param_teams'];
				
				foreach($param_teams as $param_team) {
					
					
					//Regular season: 2
					$param_gameType = '2';
					
					$games_list = crawl_games_per_season($param_season, $param_gameType, substr($param_team, 5));
					
					// Display the retrieved games list.
					/*
						$idx = 1;
						$columns = array('date', 'final', 'visiting_team', 'visiting_team_score', 'home_team', 'home_team_score', 'overtime', 'recap');
							
						if($games_list) {
							echo '<table>';
							foreach($games_list as $game){
								echo '<tr>';
								echo '<td>'.$idx.'</td>';
								foreach($columns as $col){		
									echo '<td>'.$game[$col].'</td>';		
								}
								echo '</tr>';
								$idx++;
							}
							echo '</table>';
						} else {
							echo 'Tag Not found';
						}
					*/
					
					$idx2 = 1;
					
					foreach($games_list as $game_list) {
						
						//if($idx2 == 2) {
							
							$report_to_evaluate = crawl_report_per_games($game_list['recap'], $param_team, $sort_report);
							if($sort_report) {
								$report_to_evaluate_replaced = str_replace('"', "", $report_to_evaluate);
							} else {
								$report_to_evaluate_replaced = $report_to_evaluate;
							}
							//echo $report_to_evaluate_replaced.'<br>';
							
							/*
							echo 'Visiting team:'.$game_list['visiting_team'].'<br>';
							echo 'Visiting team score:'.$game_list['visiting_team_score'].'<br>';
							echo 'Home team:'.$game_list['home_team'].'<br>';
							echo 'Home team score:'.$game_list['home_team_score'].'<br>';
							echo 'Parameter team:'.substr($param_team, 5).'<br>';
							*/
							
							if($game_list['visiting_team_score'] > $game_list['home_team_score']) {
								//echo 'Visiting team score higher<br>';
								if(strtolower($game_list['visiting_team']) == substr($param_team, 5)) {
									$win_loss = 'w';
								} else {
									$win_loss = 'l';
								}
							} else {
								//echo 'Home team score higher<br>';
								if(strtolower($game_list['visiting_team']) == substr($param_team, 5)) {
									$win_loss = 'l';
								} else {
									$win_loss = 'w';
								}
							}
							
							$query = 'INSERT INTO '.$param_team.' (id, date, win_loss, report) VALUES (NULL, \''.$game_list['date'].'\', \''.$win_loss.'\', "'.$report_to_evaluate_replaced.'")';
							//$query = "INSERT INTO $param_team (id, date, win_loss, report) VALUES (NULL, "$game_list['date']", $win_loss, $report_to_evaluate)";
							
							
							if(!mysqli_query(DBi::$conn, $query)) {
								echo mysqli_error(DBi::$conn);
							} else {
								$message_value[] = $param_season.'->'.$param_team.'->'.$idx2.'->Update done';
							}
							
						//}
						$idx2++;		
					}
					
				}
				
			} else {
				$message_value[] = 'Select one or more team(s)!<br>'; 	
			}
			
		} else {
			$message_value[] = 'Select a season!<br>';	
		}
}

?>

<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" href="css/style.css">
	<link rel="stylesheet" href="css/menu.css">
	<link rel="stylesheet" href="css/style_web_crawl.css">
</head>

<body>

<div id="main-wrapper">

<?php include 'menu.html'; ?>

<section class="main_form">

	<form action="web_crawl.php" method="POST">
	
	<section id="parameters">
			<div class="season">
				<h3>Season:</h3>
				<select name="param_season" size="09">
				<?php
					for($year=2007; $year<=2015; $year++) {
							$next_year = $year + 1;
							echo '<option value="';
							echo $year.$next_year.'">';
							echo $year.'-';
							echo $next_year;
							echo '</option><br>';
					}
				?>
				</select>
			</div>
			<div class="team">
				<h3>Select team to update table:</h3>
				<select name="param_teams[]" size="12" multiple>
				<?php
					foreach($teams as $team_long => $team_short) { 
						if($team_short == @$_POST['param_teams']) {
							echo '<option value="'.$team_short.'" selected>'.$team_long.'</option><br>';
						} else {
							echo '<option value="'.$team_short.'">'.$team_long.'</option><br>';
						}
					}
				?>
				</select>
			</div>

		</section>
		
		<p>Sort report before storing: <input type="checkbox" name="sort_report" checked /></p>
		<p>1. Update team members (per season)*: <input type="submit" name="retrieve_rosters" value="Retrieve rosters" /></p>
		<p>2. Retrieve report data from NHL.com (per season, per team)*: <input type="submit" name="retrieve_data" value="Retrieve reports" /></p>
		<p>3. Get archived games with close odds (per season)*: <input type="submit" name="archieved_games" value="Retrieve archieved games" /></p>
		<p>4. Refresh team scores on 'Evaluate' page.</p>
		<p>5. Test limits.</p>
		<br>
		<p>*Only if database is not already updated from archives.</p>
		</form>
		
		<?php
			foreach(@$message_value as $message) {
				echo $message.'<br>';
			}
		?>
	
</section>

</div>

</body>

</html>