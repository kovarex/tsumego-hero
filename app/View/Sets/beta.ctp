	<?php
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			if($_SESSION['loggedInUser']['User']['premium']<1 && $_SESSION['loggedInUser']['User']['isAdmin']<1){
				echo '<script type="text/javascript">window.location.href = "/";</script>';
			}
		}else{
			echo '<script type="text/javascript">window.location.href = "/";</script>';
		}
	?>
	<div align="center" class="display1" style="padding-top:10px;">
	<div id="sandbox">
	<h4>Admin Panel</h4>
		<div align="left">
		Collections that you find here are not yet published and need to be checked for improvement.
		While solving them, please look for misplays and bugs.<br><br>
		<?php
		if(isset($_SESSION['loggedInUser'])){if($_SESSION['loggedInUser']['User']['isAdmin']>0){
		?>
			There are 4 tasks for admins: Accept activities, answer comments, modify problems and create new problems.
			Here is a compact guide how to do it (1 page): <a class="historyLink2" href="/files/Admin-Guide.pdf" target="_blank">Admin-Guide.pdf</a>
			<br><br>
			And here is the detailed older version (9 pages): <a class="historyLink2" href="/files/Admin-Guide-Details.pdf" target="_blank">Admin-Guide-Details.pdf</a>
			<br><br>
			<table width="100%">
			<tr>
			<td>
				<a href="/sets/create">Create Set</a>
			</td>
			<td>
				<a href="/sets/remove">Delete Set</a>
			</td>
			</tr>
			<tr>
			<td>
				<a href="/sets/beta2">Show Removed Sets</a>
			</td>
			<td>
			</td>
			</tr>
			</table>
		
		<?php }} ?>
		</div>
	</div>
	<div align="center" class="set-index display1">
	<?php 
	for($i=0; $i<count($setsNew); $i++){
		if($setsNew[$i]['amount'] == 1){
			$problems = 'problem';
			$tilde = '';
		}else{
			$problems = 'problems';
			$tilde = '~';
		}
		$partition = '';
		$partitionLink = '';
		if($setsNew[$i]['solved'] != 0)
			$isZero = '';
		else
			$isZero = 'display:none;';
		echo '<a href="/sets/view/'.$setsNew[$i]['id'].$partitionLink.'" class="box1link">
			<div class="box1 box1topic topic-box'.$setsNew[$i]['id'].'" 
				style="background-color:'.$setsNew[$i]['color'].';background-image: linear-gradient(rgba(169, 169, 169, 0.30), rgba(0, 0, 0, 0.35));">';
			if($setsNew[$i]['solved']>=100)
				echo '<div class="collection-completed">completed</div>';
			echo '<div class="collection-top">'.$setsNew[$i]['name'].$partition.'</div>';
			echo '<div class="collection-middle-left">'.$setsNew[$i]['amount'].' '.$problems.'</div>';
			echo '<div class="collection-middle-right">'.$tilde.$setsNew[$i]['difficulty'].'</div>';
			echo '<div class="collection-bottom">
				<div class="number" id="number'.$i.'">0</div>
					<div align="left" class="reward-bar-container">
						<div id="account-bar-wrapper2">
							<div id="account-bar2">
								<div id="xp-bar2">
									<div class="xp-bar-empty"></div>
									<div id="xp-bar-fill2'.$i.'" class="xp-bar-fill-c4" style="width: 5%; transition: 0.6s;'.$isZero.'">
										<div id="xp-increase-fx2'.$i.'">
											<div id="xp-increase-fx-flicker2'.$i.'">
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</a>';
	}
	?>
	</div>
	<div>
	<?php
	echo 'The sandbox contains '.$overallCounter.' problems.';
	?>
	</div>
	<br><br>
	<div class="accessList">
	Admins: 
	<?php
		for($i=0; $i<count($admins); $i++){
			echo $admins[$i];
			if($i<count($admins)-1) echo ', ';
		}
	?>
	<br><br>
	</div>
	</div>
	
	<script>
		<?php
		for($i=0; $i<count($setsNew); $i++){
			echo 'animateNumber'.$i.'(0, '.$setsNew[$i]['solved'].', .6);';
			echo 'function animateNumber'.$i.'(start, end, duration) {
				const element = document.getElementById("number'.$i.'");
				const range = end - start;
				const increment = range / (duration * 60); 
				const decimalIndex = end.toString().indexOf(".");
				const dx = decimalIndex >= 0 ? end.toString().length - decimalIndex - 1 : 0;
				let currentNumber = start;
				const step = () => {
					let randomDecimal = "";
					if(dx===1){
						randomDecimal = "."+Math.floor(Math.random() * 9);
					}else if(dx===2){
						randomDecimal = "."+(Math.floor(Math.random() * 90) + 10);
					}
					currentNumber += increment;
					if (currentNumber < end) {
							element.textContent = Math.floor(currentNumber) + randomDecimal + "%";
							requestAnimationFrame(step);
					} else {
							element.textContent = end + "%"; 
					}
				};
				requestAnimationFrame(step);
			}';
			echo 'animateBar'.$i.'('.$setsNew[$i]['solved'].');';
			echo 'function animateBar'.$i.'(percent){
				$("#xp-increase-fx2'.$i.'").css("display","inline-block");
				$("#xp-bar-fill2'.$i.'").css("box-shadow", "-5px 0px 10px #fff inset");
				$("#xp-bar-fill2'.$i.'").css("width", 0+"%");
				$("#xp-increase-fx2'.$i.'").hide();
				$("#xp-bar-fill2'.$i.'").css({"-webkit-transition":"all 0.6s ease","box-shadow":""});
				
				$("#xp-bar-fill2'.$i.'").css({"width":percent+"%"});
				$("#xp-bar-fill2'.$i.'").css("-webkit-transition","all 0.6s ease");
				$("#xp-increase-fx2'.$i.'").fadeIn(0);
				$("#xp-bar-fill2'.$i.'").css({"-webkit-transition":"all 0.6s ease","box-shadow":""});
				setTimeout(function(){
					$("#xp-increase-fx-flicker2'.$i.'").fadeOut(600);
					$("#xp-bar-fill2'.$i.'").css({"-webkit-transition":"all 0.6s ease","box-shadow":""});
				},600);
			}';
		}	
		?>
	
	</script>
	<style>
		.box1:hover {
				background-image: linear-gradient(
					rgba(200, 200, 200, 0.3),
					rgba(33, 33, 33, 0.35)
				) !important;
			}
		.set-search {
			display: flex;
			font-weight: 800;
			padding: 6px 10px;
			margin: 5px;
			border-radius: 5px;
			<?php if($lightDark=='light'){ ?>
				background: #555;
				color: #fff;
			<?php }else{ ?>
				background: #222;
				color: #bfc1c4;
			<?php } ?>
		}
		.xp-bar-fill-c4 {
			position: relative;
			left: 0;
			background-color: #bbbbbb;
			background-image: linear-gradient(#f5f5f5, #9b9b9b);
			height:5px;
			border-radius:3px;
			z-index: 2;
		}
		.number {
			animation: fadeIn 1s ease-in-out;
		}
		@keyframes fadeIn {
			0% {opacity: 0;}
			100% {opacity: 1;}
		}
		@keyframes fadeIn2 {
			0% {opacity: 0;}
			100% {opacity: .9;}
		}
	</style>
