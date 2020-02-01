<?php

require 'global_vars.inc.php';


//Connecting to database

/*** Deprecated 
$link = mysql_connect($server, $username, $password);
if(!@$link || !@mysql_select_db($database_name)) {
	die('Could not connect!');
} 
***/

class DBi {
    public static $conn;
}

DBi::$conn = mysqli_connect($server, $username, $password, $database_name);

if(!DBi::$conn) {
	die('Could not connect!');
} 

/*** Deprecated
if(!mysql_query("SET character_set_results = 'utf8', character_set_client = 'utf8', character_set_connection = 'utf8', character_set_database = 'utf8', character_set_server = 'utf8'", DBi::$conn)) {
	echo 'Character set, can\'t be set';
}
***/

if(!mysqli_set_charset(DBi::$conn, 'utf8')) {
	echo 'Character set, can\'t be set';
}

//Create array with matching team name and abbreviation.

$teams = array();
$team_names = file('teams/team_names.txt');
$team_abbr = file('teams/team_abbrevations.txt');
$i = 0;

foreach($team_names as $team_name) {
	$team_name_trimmed = trim($team_name);
	$team_abbr_trimmed = trim($team_abbr[$i]);
	$teams[$team_name_trimmed] = $team_abbr_trimmed;
	$i++;
}

//$teams = array('Tampa Bay Lightning' => 'TB', 'Pittsburgh Penguins' => 'PP', 'Philadelphia Flyers' => 'PF', 'Boston Bruins' => 'BB', 'Vancouver Canucks' => 'VC', 'Chicago Blackhawks' => 'CB', 'Los Angeles Knights' => 'LA', 'Dallas Stars' => 'DS', 'New York Islanders' => 'NI');

/*
Creating base tables:
	- 'scores': Stores the OPscore avarage values for each team, which we use for determining the more optimist/pessimist team.
				We store the averages of the last x games played. Where x=$global_limit and x=all games.
	- 'matches': Stores the matches that we want to bet on(games with even odds), with game id, date and odds.
	- 'results': Connection with 'matches' table on 'game_id'. Stores the OPscores for both team, and for what to bet on. Also the result of the game.
	- 'money_flow': Contains the changes in our capital after every finished match we bet on.
*/

$query = "CREATE TABLE IF NOT EXISTS scores(team VARCHAR(8), score_last DOUBLE, score_all DOUBLE, PRIMARY KEY (team))";
if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }

$query = "CREATE TABLE IF NOT EXISTS matches(game_id INT NOT NULL AUTO_INCREMENT, date DATE NOT NULL, visitor VARCHAR(8), home VARCHAR(8), odds_v DOUBLE, odds_h DOUBLE, odds_d DOUBLE, odds_us DOUBLE, PRIMARY KEY(game_id))";
if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }

$query = "CREATE TABLE IF NOT EXISTS results(game_id INT NOT NULL AUTO_INCREMENT, visitor_score DOUBLE, home_score DOUBLE, to_bet_on INT, result VARCHAR(1), overtime BOOLEAN, PRIMARY KEY(game_id))";
if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }

$query = "CREATE TABLE IF NOT EXISTS money_flow(id INT AUTO_INCREMENT, profit INT, base_capital INT, current_capital INT, PRIMARY KEY(id))";
if(mysqli_query(DBi::$conn, $query)) {
	
	// Use id=-1 as a place holder for the current capital value. Ignore creation if already created.
	$query = "INSERT IGNORE INTO money_flow(id, base_capital, current_capital) VALUES(-1, $default_capital, $default_capital)";
	if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
} else {
	 echo mysqli_error(DBi::$conn);
}

$query = "CREATE TABLE IF NOT EXISTS test_limits_stat(us_odds DOUBLE, match_limit INT, game_id INT, win_ind BOOLEAN, PRIMARY KEY (us_odds, match_limit, game_id))";
if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }

/*
	Fill up table 'scores' with team names. Create individual tables for each team.
	Team tables contain information about previously played games - Evaluated games (date, won or loss, quotation of the players review and the calculated scores based on these quotes). 
*/

foreach($teams as $team) {
				
	$handle = fopen('rosters/'.$team.'.txt', "a");
	fclose($handle);
	
				
	$query = "SELECT team FROM scores WHERE team='$team'";
	if($result = mysqli_query(DBi::$conn, $query)) {
		if(!mysqli_num_rows($result)) {
			
			$query = "INSERT INTO scores(team) VALUES ('$team')";
			if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
			
			$query = "CREATE TABLE IF NOT EXISTS $team(id INT NOT NULL AUTO_INCREMENT, date DATE NOT NULL, win_loss VARCHAR(1), report VARCHAR(24000), perm DOUBLE, perv DOUBLE ,pers DOUBLE, total_score DOUBLE, PRIMARY KEY (id))";
			if(!mysqli_query(DBi::$conn, $query)) { echo mysqli_error(DBi::$conn); }
		}
	} else {
		echo mysqli_error(DBi::$conn);	
	}
}


?>