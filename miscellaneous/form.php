<form action="index.php" method="POST">
	Text to be evaluated: <br><br>
	<textarea name="report" cols="60" rows="12"><?php echo @hide($report_array, $p_match_sort); ?></textarea> <br> <br>
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