<?php 

$count = 0;
					
foreach ($report_array as $rword) {
	$rword_trimmed = trim($rword,  ' .,()!?\"\'');

	foreach ($common_words as $cword) {
		$cword_trimmed = trim($cword);
		if($rword_trimmed == $cword_trimmed) {
			$common = true;
			break;
		} else {
			$common = false;
		}
	}
	if(!$common) {
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

echo $output;

	

?>