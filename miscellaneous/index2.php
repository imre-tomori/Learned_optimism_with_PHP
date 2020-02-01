<?php 

require 'form.php';

function common_check($rword, $common_words) {
	
	$common = array(0,0);
	
	foreach ($common_words as $cword) {
		$cword_trimmed = trim($cword);
		if($rword == $cword_trimmed) {
			$common[1] = $common[1]+1;
			$common[0] = 1;
			break;
		}
	}
	
	return $common;
	
}	

// ------------------------------------------------------------------ //

function hide_common($report_array, $common_words) {
	
$count = 0;
					
foreach ($report_array as $rword) {
	$rword_trimmed = trim($rword,  ' .,()!?\"\'');
	
	$common = common_check($rword_trimmed, $common_words);
	
	if(!$common[0]) {
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

function p_match($report_array, $p_array, $common_words) {

	$report_array_count = count($report_array)-1;
	$match = 0;
	
	foreach ($report_array as $rword) {
		
		$rword_trimmed = trim($rword, ' .,()!?"\'');

		$common = common_check($rword_trimmed, $common_words);
				
		if(!$common[0]) {
			for($count=0;$count<count($p_array);$count++) {
				if($rword_trimmed == trim($p_array[$count])) {
					$match++;
					break;
				}
			}
		}
		
	}
	
	echo $report_array_count.' '.$common[1].' '.$match.'||';
	return round(($match/($report_array_count-$common[1])) / (1/7));	
	
}

// ------------------------------------------------------------------ //

function rearrange_scores($p_scores, $win_loss) {
	
	$rearranged[3] = 0;
					
	for($i=0;$i<3;$i++) {
		
		$divide = 0;
		$p = 0;
		$inv = 0;
	
		if($p_scores[$i] != 0) {
			if($p_scores[$i+3] != 0) {		
				$divide = 2;
				$p = 7;
				$inv = 7;
			} else {	
				$divide = 1;
				$p = 7;
				$inv = 0;
			}
		} elseif($p_scores[$i+3] != 0) {
					$divide = 1;
					$p = 0;
					$inv = 7;
				}
		
			if($divide) {
			
				if($win_loss == 'loss') {
						$rearranged[$i] = ($p_scores[$i]+($inv-$p_scores[$i+3]))/$divide;
				} else {
						$rearranged[$i] = ($p-$p_scores[$i]+($p_scores[$i+3]))/$divide;
				}
				
			} else {
				$rearranged[$i] = 0;
				$rearranged[3]++;
			}
		
	}
	
	return $rearranged;
	
}

// ------------------------------------------------------------------ //

if(isset($_POST['report'])) {
	$report = strtolower(trim($_POST['report']));
	
	if(!empty($report)) {

		if(isset($_POST['win_loss'])) {	
			$win_loss = $_POST['win_loss'];
			
			if(!empty($win_loss)) {
				
//				permanence (maradandóság)
				$per[0] = file('permanence.txt');
				$per[3] = file('permanence_inv.txt');
				
//				pervasiveness (elterjedtség)
				$per[1] = file('pervasiveness.txt');
				$per[4] = file('pervasiveness_inv.txt');
				
//				personalization (megszemélyesítés)
				$per[2] = file('personalization.txt');
				$per[5] = file('personalization_inv.txt');				
				
//				common words
				$common_words = file('common_words.txt');
				
				$report_array = explode(' ', $report);
				
				for($i=0;$i<6;$i++) {						
					$p_scores[$i] = p_match($report_array, $per[$i], $common_words);
				}
				
				echo 'p_scores:';
				print_r($p_scores);
				echo '<br>';
								
				$rearranged = rearrange_scores($p_scores, $win_loss);
								
				echo 'Permanence score: '.$rearranged[0].'<br>';
				echo 'Pervasiveness score: '.$rearranged[1].'<br>';
				echo 'Personalization score: '.$rearranged[2].'<br>';		
				echo '<hr>';
				
				if($rearranged[3] == 3) {
					$total_score = NULL;
					echo 'Score can\'t be included!<br>';
				} else {
						$total_score = ($rearranged[0]+$rearranged[1]+$rearranged[2])*(3/(3-$rearranged[3]));
						echo 'Multiple by: '.(3/(3-$rearranged[3])).'x<br>';
					} 	
				
				echo 'Total score: <strong>'.$total_score.'</strong><br><hr><br>';
				
			} else {
				echo 'Choose win or loss!';
			}
			
		} else {
			echo 'Please choose win or loss!';
		}
					
	} else {
		echo 'Please fill in some text to evaluate!<br><br>';
	}
}


?>

<!--

<form action="index2.php" method="POST">
	Text to be evaluated: <br><br>
	<textarea name="report" cols="60" rows="12"><?php echo @hide($report_array, $p_match_sort); ?></textarea> <br><br>
	After
	'Win': <input type="radio" name="win_loss" value="win" /> or 
	'Loss': <input type="radio" name="win_loss" value="loss" checked /> <br> <br>
	Date: <input type="date" name="date" /> <br> <br>
	
	<select name="teams">
		<option value="TB">Tampa Bay Lightning</option>
		<option value="DP">Detroit Penguines</option>
		<option value="PF">Philadelphia Flyers</option>
		<option value="BB">Boston Bruins</option>
		<option value="VC">Vancuver Canucks</option>
	</select>
	
	<input type="submit" value="Evaluate" />
</form>

-->