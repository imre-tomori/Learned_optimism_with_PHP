<?php

/*--------------------------------------------------
--- Global variables ---
---------------------------------------------------*/

$server = 'localhost';
$username = 'root';
$password = 'pwd';

$database_name = 'opscore_db';


$default_capital = 10000;	//Starting amount for the simulation. 
$percentage = 0.05;	//Percentage to invest in every new bet from current capital. 

$global_limit = 12;	//OPscore to be used from the last X games played by team. 
$global_difference = 0.5;	//Difference needed between two teams OPscores, to decide winner. 

$error_message = '<br>';	//Default value for error message.

$conditional_extra_score = 0.5; //Used in opscore_test2

?>