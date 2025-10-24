<?php
	if($this->Session->check('loggedInUser')){
		if($this->getLoggedInUserID()!=72 && $this->getLoggedInUserID()!=1543){
			echo '<script type="text/javascript">window.location.href = "/";</script>';
		}	
	}else{
		echo '<script type="text/javascript">window.location.href = "/";</script>';
	}
?>

<div align="center">
<?php
	//echo '<pre>';print_r($trs2);echo '</pre>';
?> 
<br>
<a href="/files/tsumego-hero-user-activities.csv">download</a>
<br><br>

<?php
	//echo '<pre>'; print_r($trs); echo '</pre>';
?>