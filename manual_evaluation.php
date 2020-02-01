<?php

require 'connect.inc.php';
header('Content-type: text/html; charset=utf-8');

/*
 Manual entry evaluation START
 Determine the dimensionality of words based on manual entries.
*/


$database_name2 = 'opscore_manual';
DBi::$conn = mysqli_connect($server, $username, $password, $database_name2);

if(!DBi::$conn) {
	die('Could not connect!');
} 


function dim_word_collector($manual_entry_dim, $report_word, $manual_entry_dim_score, $counter, $word_last_saved) {
		
		global $word_last_saved;
		$key_found = false;
		foreach ($manual_entry_dim as $entry_key => $entry_value) {	
			if($report_word == $entry_key) {
				$key_found = true;
				break;
			}
		}
		
		if(!$key_found) {
			$manual_entry_dim[$report_word] = array();
			array_push($manual_entry_dim[$report_word], $manual_entry_dim_score);
			$word_last_saved[$report_word] = $counter;
		} elseif($word_last_saved[$report_word] != $counter) {
			array_push($manual_entry_dim[$report_word], $manual_entry_dim_score);
			$word_last_saved[$report_word] = $counter;	
		}
	
	return $manual_entry_dim;
}

function avarage_manual_scores($manual_entry_dim) {
	
	foreach ($manual_entry_dim as $word_key => $word_value) {
		
		$value_sum = 0;
		$avg_counter = 0;
		
		foreach ($manual_entry_dim[$word_key] as $word_keys_value) {
			$value_sum += $word_keys_value;
			$avg_counter++; 
		}
		//$manual_entry_dim_avg[$word_key] = array();
		//array_push($manual_entry_dim_avg[$word_key], ($value_sum/$avg_counter));
		if($avg_counter >= 6) {
			$manual_entry_dim_avg[$word_key] = ($value_sum/$avg_counter);
		}
		
	}

	return $manual_entry_dim_avg;
	
}

function write_to_file($manual_entry_dim_avg, $dim_file_handle, $dim_file_handle_inv) {

/*			
		foreach ($manual_entry_dim_avg as $man_avg_key => $man_avg_val) {

				if($man_avg_val >= 5) {
					fwrite($dim_file_handle,"$man_avg_key\n");
				}

		}
		
*/		
		foreach ($manual_entry_dim_avg as $man_avg_key => $man_avg_val) {			
			if($man_avg_val >= 4) {
				fwrite($dim_file_handle,"$man_avg_key\n");
			} else {
				fwrite($dim_file_handle_inv,"$man_avg_key\n");	
			}
		}
		
		
		fclose($dim_file_handle);
		fclose($dim_file_handle_inv);
		
		echo "'File in location: $dim_file_handle created!<br>";		
//		echo "'File in location: $dim_file_handle_inv created!<br>";	
		
}	

$counter = 0;

//$teams = array('team_ana');
$manual_entry_perm = array();
$manual_entry_perv = array();
$manual_entry_pers = array();
//$win_loss_ind = array('w','l');
		
//foreach ($win_loss_ind as $wl_ind) {
	foreach ($teams as $team) {
		//$query = "SELECT * FROM $team WHERE win_loss='$wl_ind' AND (perm IS NOT NULL OR perv IS NOT NULL OR pers IS NOT NULL)";
		$query = "SELECT * FROM $team WHERE perm IS NOT NULL OR perv IS NOT NULL OR pers IS NOT NULL";
		
			if($result = mysqli_query(DBi::$conn, $query)) {
				while($manual_entry_row = mysqli_fetch_assoc($result)) {
					$counter++;
					//echo $team.' | '.$counter.'<br>';
					
					$manual_entry_row_report = explode(' ', $manual_entry_row['report']);
					
					foreach ($manual_entry_row_report as $report_word) {
						
						$report_word_trimmed = trim($report_word, ' .,()!?�"“”\'');
					
						/*
						$manual_entry_perm = dim_word_collector($manual_entry_perm, $report_word_trimmed, $manual_entry_row['perm'], $counter, $word_last_saved);
						$manual_entry_perv = dim_word_collector($manual_entry_perv, $report_word_trimmed, $manual_entry_row['perv'], $counter, $word_last_saved);
						$manual_entry_pers = dim_word_collector($manual_entry_pers, $report_word_trimmed, $manual_entry_row['pers'], $counter, $word_last_saved);
						*/
										
						// Perm
						//Determine if word already exists in array.						
						$key_found_perm = false;
						foreach ($manual_entry_perm as $entry_key => $entry_value) {	
							if($report_word_trimmed == $entry_key) {
								$key_found_perm = true;
								break;
							}
						}
						
						// Initialize if word not yet saved, and save perm score with it.
						// This also means, one word will only be saved once from one report.
						if(!$key_found_perm) {
							$manual_entry_perm[$report_word_trimmed] = array();
							array_push($manual_entry_perm[$report_word_trimmed], $manual_entry_row['perm']);
							$word_last_saved_perm[$report_word_trimmed] = $counter;
						//$word_last_saved_perm is used to compare with $counter so we can add multiple values for the same word,
						// if they are from different games.
						} elseif($word_last_saved_perm[$report_word_trimmed] != $counter) {
							array_push($manual_entry_perm[$report_word_trimmed], $manual_entry_row['perm']);
							$word_last_saved_perm[$report_word_trimmed] = $counter;	
						}
		
						
						// Pers
						$key_found_pers = false;
						foreach ($manual_entry_pers as $entry_key => $entry_value) {	
							if($report_word_trimmed == $entry_key) {
								$key_found_pers = true;
								break;
							}
						}
						
						if(!$key_found_pers) {
							$manual_entry_pers[$report_word_trimmed] = array();
							array_push($manual_entry_pers[$report_word_trimmed], $manual_entry_row['pers']);
							$word_last_saved_pers[$report_word_trimmed] = $counter;
						} elseif($word_last_saved_pers[$report_word_trimmed] != $counter) {
							array_push($manual_entry_pers[$report_word_trimmed], $manual_entry_row['pers']);
							$word_last_saved_pers[$report_word_trimmed] = $counter;	
						}
						
						
						
						// Perv
						$key_found_perv = false;
						foreach ($manual_entry_perv as $entry_key => $entry_value) {	
							if($report_word_trimmed == $entry_key) {
								$key_found_perv = true;
								break;
							}
						}
						
						if(!$key_found_perv) {
							$manual_entry_perv[$report_word_trimmed] = array();
							array_push($manual_entry_perv[$report_word_trimmed], $manual_entry_row['perv']);
							$word_last_saved_perv[$report_word_trimmed] = $counter;
						} elseif($word_last_saved_perv[$report_word_trimmed] != $counter) {
							array_push($manual_entry_perv[$report_word_trimmed], $manual_entry_row['perv']);
							$word_last_saved_perv[$report_word_trimmed] = $counter;	
						}
						
					
						
					}
					
				}
				
		
				
			} else {
				echo mysqli_error(DBi::$conn);
			}	
		
	}

		
		echo 'Permanence:<br><hr>';
		echo '<pre>';
		print_r($manual_entry_perm);
		echo '</pre>';
/*		
		echo 'Pervasiveness:<br><hr>';
		echo '<pre>';
		print_r($manual_entry_perv);
		echo '</pre>';
		
		echo 'Personalisation:<br><hr>';
		echo '<pre>';
		print_r($manual_entry_pers);
		echo '</pre>';
*/


		$manual_entry_perm_avg = avarage_manual_scores($manual_entry_perm);
		$manual_entry_perv_avg = avarage_manual_scores($manual_entry_perv);
		$manual_entry_pers_avg = avarage_manual_scores($manual_entry_pers);
		
		
		echo 'Permanence averaged:<br><hr>';
		echo '<pre>';
		print_r($manual_entry_perm_avg);
		echo '</pre>';
/*		
		echo 'Pervasiveness averaged:<br><hr>';
		echo '<pre>';
		print_r($manual_entry_perv_avg);
		echo '</pre>';
		
		echo 'Personalization averaged:<br><hr>';
		echo '<pre>';
		print_r($manual_entry_pers_avg);
		echo '</pre>';
*/		

/*
		if($wl_ind == 'w') {
			$perm_file_handle = fopen('dimensions/manual_evaluation/permanence.txt', 'w');
			$perv_file_handle = fopen('dimensions/manual_evaluation/pervasiveness.txt', 'w');
			$pers_file_handle = fopen('dimensions/manual_evaluation/personalization.txt', 'w');
		
		} elseif($wl_ind == 'l') {
			$perm_file_handle = fopen('dimensions/manual_evaluation/permanence_inv.txt', 'w');
			$perv_file_handle = fopen('dimensions/manual_evaluation/pervasiveness_inv.txt', 'w');
			$pers_file_handle = fopen('dimensions/manual_evaluation/personalization_inv.txt', 'w');
		}
*/
		$perm_file_handle = fopen('dimensions/manual_evaluation/permanence.txt', 'w');
		$perv_file_handle = fopen('dimensions/manual_evaluation/pervasiveness.txt', 'w');
		$pers_file_handle = fopen('dimensions/manual_evaluation/personalization.txt', 'w');
		$perm_file_handle_inv = fopen('dimensions/manual_evaluation/permanence_inv.txt', 'w');
		$perv_file_handle_inv = fopen('dimensions/manual_evaluation/pervasiveness_inv.txt', 'w');
		$pers_file_handle_inv = fopen('dimensions/manual_evaluation/personalization_inv.txt', 'w');
			
		write_to_file ($manual_entry_perm_avg, $perm_file_handle, $perm_file_handle_inv);
		write_to_file ($manual_entry_perv_avg, $perv_file_handle, $perv_file_handle_inv);
		write_to_file ($manual_entry_pers_avg, $pers_file_handle, $pers_file_handle_inv);			

//}
/*
 Manual entry evaluation END 
*/

?>