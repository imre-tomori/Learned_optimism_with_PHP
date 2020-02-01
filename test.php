<?php

require 'connect.inc.php';
require 'simple_html_dom.php';
header('Content-type: text/html; charset=utf-8');

/*
foreach($teams as $team_long => $team_short) {
	echo $team_short.'<br>';						
	$query = "SELECT id, date FROM $team_short WHERE date like '2012%'";
	if($result = mysql_query($query)) {
		while($update_date = mysql_fetch_array($result)) {
			
			$month_day = substr($update_date[1], 4).'<br>';
			$query2 = "UPDATE $team_short SET date='2013$month_day' WHERE id=$update_date[0]";
			if($result2 = mysql_query($query2)) {
					echo 'OK';
			}	else { echo mysql_error(); }
		}
	} else { echo mysql_error(); }

}



$finished_date = '2013-10-12';

$finished_profit_stream = 0;
$query2 = "SELECT m.date, mf.profit FROM matches m INNER JOIN money_flow mf ON m.game_id=mf.id WHERE date<='$finished_date'";
if($result2 = mysql_query($query2)) {
	while($profits = mysql_fetch_array($result2)) {
		echo $profits[0].'/'.$profits[1].'<br>';
		$finished_profit_stream += $profits[1];
	}
} else { echo mysql_error(); }

echo $finished_profit_stream;

*/

/*
	echo $date_to_use = date('Y-m-d', time()).'<br>';
	echo $date_lookback = date('Y-m-d', strtotime('-42 days')).'<br>';
	echo strtotime('2014-04-26').'||'.time().'<br>';
	
	$date = '2013-03-20';
	echo $date_lookback = date('Y-m-d', strtotime("$date-42 days"));
	
	echo '<hr>';
	
	$team='fla';
	$limit=12;
	$limit_doubled = 2 * $limit;
	
	$query = "SELECT date, total_score ".
		"FROM ( ".
					"SELECT date, total_score ".
					"FROM $team ".
					"ORDER BY date DESC ".
					"LIMIT $limit_doubled ".
				") AS double_range ".
		"WHERE total_score IS NOT NULL AND date < '$date_to_use' ".
		"ORDER BY date DESC ".
		"LIMIT $limit ";
		
	if($result = mysql_query($query)) {
	
	while($scores = mysql_fetch_array($result)) {
		echo $scores[0].'||'.$scores[1].'<br>';	
		
	}
	} else {
	echo mysql_error();
	}
*/

/*
$result = array();
$weights = array(1 => 0.38, 2 => 0.38, 0 => 0.24);

for($i=0;$i<=10000;$i++) {

 $rand = (float)rand()/(float)getrandmax();
 foreach ($weights as $value => $weight) {
    if ($rand < $weight) {
       $result[$i] = $value;
       break;
    }
    $rand -= $weight;
  }

}

print_r(array_count_values($result));

$date_string='Wed Oct 2, 2013';

echo '<br>';
echo date("Y-m-d", strtotime($date_string));
*/

/*
//Update matches table with new team ids.
$query = 'SELECT * FROM matches';

if($result = mysqli_query(DBi::$conn, $query)) {
	while($match = mysqli_fetch_assoc($result)) {
		if($match['visitor'] == 'fla') {
			$query2 = "UPDATE matches SET visitor='team_fla' WHERE game_id=".$match['game_id'];
			if(!mysqli_query(DBi::$conn, $query2)) {
				echo mysqli_error(DBi::$conn);
			}
		}
		if($match['home'] == 'fla') {
			$query2 = "UPDATE matches SET home='team_fla' WHERE game_id=".$match['game_id'];
			if(!mysqli_query(DBi::$conn, $query2)) {
				echo mysqli_error(DBi::$conn);
			}
		}
	}
	
} else {
	echo mysqli_error(DBi::$conn);	
}
*/

/*---------------------------------------------------------------------------

Crawl oddsportal.com for historical odds
http://www.oddsportal.com/hockey/usa/nhl-2014-2015/results/#/page/1/

Multiple sites:
<div id="pagination">
Search for the last <a> in above div and read out x-page attribute. Gives us the last page.


function crawl_oddsportal_for_odds() {
	
	$odds_season = '2013-2014';
	$page_links = array();
	
	$first_page = file_get_html( 'http://www.oddsportal.com/hockey/usa/nhl-'.$odds_season.'/results');
	
	if(!$first_page) {
		echo 'GET HTML FALSE <br>';
	} else {
		
		//$last_page = $first_page->find('a[x-page]', -1) ;
		foreach( $first_page->find('a') as $page_link) {
			//$page_links = $page_link->plaintext;
			echo $page_link->href;
		}
		
		//print_r($page_links);
	
		//$page_count = $last_page->x-page;
		
		/*foreach($page_count as $count){
			echo $count.'<br>';	
		}*/
		
		/*
		for ($i=1; $i=$page_count; $i++) {	
			$html_odds = file_get_html( 'http://www.oddsportal.com/hockey/usa/nhl-'.$odds_season.'/results/#/page/'.$i.'/');
			
			if(!$html_odds) {
				echo 'GET HTML FALSE <br>';
			}
			
			$games_list = array();
			
			
			foreach($html_odds->find('table[class=data schedTbl] tbody tr') as $odds) {
				
			}
		}
		
	
	}

}
*/

/*
$odds_season = '2013-14';
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
		
		echo '<br>';
		
		if($line_number % 2 == 0) {
			$visitor_team = $current_team;
			$visitor_score = $current_score;
			$visitor_odds = $current_odds;
			$visitor_overtime = $current_overtime;
		} else {
			echo $line_number.' | ';
			$save_to_db = false;
			if(substr($current_odds, 0, 1) == '-') {
				if($current_odds >= -130) {
					$save_to_db = true;
				}
			} elseif (substr($visitor_odds, 0, 1) == '-')  {
				if($visitor_odds >= -130) {
					$save_to_db = true;
				}
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
				
				if($date_to_use >= '2013-10-01' && $date_to_use <= '2014-04-13') {
						
					echo $date_to_use;
					
					$query = "INSERT INTO matches(date, visitor, home) VALUES ('$date_to_use', '$visitor_team', '$current_team')";
					
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
					
				}
			}
			
		}
				
		
	}
	
	$line_number++;

}
*/

/*
//http://www.hockeydb.com/
//http://www.hockeydb.com/ihdb/stats/leagues/seasons/nhl19272013.html

//function crawl_team_rosters($season) {}

//season 2012-2013
$season = '2013';
$base_link = 'http://www.hockeydb.com/ihdb/stats/leagues/seasons/nhl1927'.$season.'.html';

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
					
					$team_roster_handle = fopen('rosters/'.$teams[$team_name].'.txt', "w");
					foreach($team_members as $member) {
						//Save only last name
						$name_parts = array();
						$name_parts = explode(' ', $member);
						fwrite($team_roster_handle, end($name_parts)."\n");
					}
					fclose($team_roster_handle);
					echo 'Roster updated for team: '.$team_name.'<br>';
					
				} else {
					echo 'GET HTML FALSE | TEAM ROSTER <br>';
				}
				//Need to remove break after dev!
				//break;
			}
	}
		
} else {
	echo 'GET HTML FALSE | SEASON STANDINGS <br>';
}

*/

 // Delete OP and total scores, to create a DB for manual input:

/*

foreach($teams as $team) {
	
	$query = "UPDATE $team SET perm=NULL, perv=NULL, pers=NULL, total_score=NULL";
	if(!mysqli_query(DBi::$conn, $query)) {
		echo mysqli_error(DBi::$conn);	
	}

}

*/



?>

