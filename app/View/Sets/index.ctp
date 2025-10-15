<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" />
	<div align="center" class="set-search-menu">
		<div class="set-buttons-left">
			<div class="set-buttons">
				<button id="topics-button" class="btn btn-link collapsed set-search" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
					<div class="set-button-text">
							Topics
					</div>
					<div class="set-button-icon" id="set-button-icon-topics">
							<i class="animate-icon fa fa-chevron-down" aria-hidden="true"></i>
					</div>
				</button>
				<div align="left" id="dropdown-topics" class="dropdown-boxes">
					<?php
						for($i=0; $i<count($setTiles); $i++){
							if($setTiles[$i] != '[continuation]')
								echo '<div class="dropdown-tile" id="tile-topics'.$i.'">'.$setTiles[$i].'</div>';
						}
					?>
					<div class="tiles-submit">
						<div class="dropdown-tile tiles-submit-inner-select" id="tile-topics-select-all">Select all</div>
						<?php if($query){ ?>
							<a class="dropdown-tile tiles-submit-inner" id="tile-topics-submit" href="">Search</a>
						<?php } ?>
					</div>
				</div>
			</div>
			<div class="set-buttons">
				<button id="difficulty-button" class="btn btn-link collapsed set-search" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
					<div class="set-button-text">
							Difficulty
					</div>
					<div class="set-button-icon" id="set-button-icon-difficulty">
							<i class="animate-icon fa fa-chevron-down" aria-hidden="true"></i>
					</div>
				</button>
				<div align="left" id="dropdown-difficulty" class="dropdown-boxes">
					<?php
						for($i=0; $i<count($difficultyTiles); $i++){
							if($difficultyTiles[$i] != '[continuation]')
								echo '<div class="dropdown-tile" id="tile-difficulty'.$i.'">'.$difficultyTiles[$i].'</div>';
						}
					?>
					<div class="tiles-submit">
						<div class="dropdown-tile tiles-submit-inner-select" id="tile-difficulty-select-all">Select all</div>
						<?php if($query){ ?>
							<a class="dropdown-tile tiles-submit-inner" id="tile-difficulty-submit" href="">Search</a>
						<?php } ?>
					</div>
				</div>
			</div>
			<div class="set-buttons">
				<button id="tags-button" class="btn btn-link collapsed set-search" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
					<div class="set-button-text">
							Tags
					</div>
					<div class="set-button-icon" id="set-button-icon-tags">
							<i class="animate-icon fa fa-chevron-down" aria-hidden="true"></i>
					</div>
				</button>
				<div align="left" id="dropdown-tags" class="dropdown-boxes">
					<?php
						for($i=0; $i<count($tagTiles); $i++){
							if($tagTiles[$i] != '[continuation]')
								echo '<div class="dropdown-tile" id="tile-tags'.$i.'">'.$tagTiles[$i].'</div>';
						}
					?>
					<div class="tiles-submit">
						<div class="dropdown-tile tiles-submit-inner-select" id="tile-tags-select-all">Select all</div>
						<?php if($query){ ?>
							<a class="dropdown-tile tiles-submit-inner" id="tile-tags-submit" href="">Search</a>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
		<div class="set-buttons">
			Problems found: <?php echo $searchCounter; ?>
		</div>	
		<div class="set-buttons-right">
			<div class="set-buttons">Collection size:
				<input id="set-size-input" type="number" value="<?php echo $collectionSize; ?>" step="10">
			</div>
			<div class="set-buttons">
				<button disabled="true" id="submit-size-button" onclick="submitSize()" class="btn btn-link collapsed set-search">
					Submit
				</button>
			</div>
		</div>
	</div>
	<div class="active-tiles-container"></div>
	<div align="center" class="set-index display1">
	<?php 
	if($lightDark=='light')
		$lightDarkBoxes = '4';
	else
		$lightDarkBoxes = '3';
	if($query == 'topics'){
		for($i=0; $i<count($sets); $i++){
			if($sets[$i]['amount'] == 1){
				$problems = 'problem';
				$tilde = '';
			}else{
				$problems = 'problems';
				$tilde = '~';
			}
			if($sets[$i]['partition'] == -1){
				$partition = '';
				$partitionLink = '';
			}else{
				$partition = ' #'.($sets[$i]['partition']+1);
				$partitionLink = '?partition='.$sets[$i]['partition'];
			}
			if($sets[$i]['solved'] != 0)
				$isZero = '';
			else
				$isZero = 'display:none;';

			if($lightDark=='light'){
				$lightDarkBoxes = '3';
				if($sets[$i]['partition']>=4)
					$lightDarkBoxes = '6';
			}
			$makeLink = true;
			if($sets[$i]['premium']!=1){
				$backgroundImage = 'linear-gradient(rgba(169, 169, 169, 0.'.$lightDarkBoxes.'0), rgba(0, 0, 0, 0.'.$lightDarkBoxes.'5));';
				$box1unlocked = 'box1default';
			}else{
				if($hasPremium){
					$backgroundImage = 'url(/img/setButtonUnlocked.png);';
					$box1unlocked = 'box1unlocked';
				}else{
					$backgroundImage = 'url(/img/setButtonLocked.png);';
					$box1unlocked = '';
					$makeLink = false;
				}
			}	
			if($makeLink){
				echo '<a href="/sets/view/'.$sets[$i]['id'].$partitionLink.'" class="box1link">
					<div class="box1 box1topic '.$box1unlocked.' topic-box'.$sets[$i]['id'].'" 
						style="background-color:'.$sets[$i]['color'].';background-image: '.$backgroundImage.'">';
					if($sets[$i]['solved']>=100)
						echo '<div class="collection-completed">completed</div>';
					echo '<div class="collection-top">'.$sets[$i]['name'].$partition.'</div>';
					echo '<div class="collection-middle-left">'.$sets[$i]['amount'].' '.$problems.'</div>';
					echo '<div class="collection-middle-right">'.$tilde.$sets[$i]['difficulty'].'</div>';
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
			}else{
				echo '<a href="/users/donate" class="box1link">';
				echo '<div class="box1 box1topic '.$box1unlocked.' topic-box'.$sets[$i]['id'].'" 
					style="background-color:'.$sets[$i]['color'].';background-image: '.$backgroundImage.'">';
				if($sets[$i]['solved']>=100)
					echo '<div class="collection-completed">completed</div>';
				echo '<div class="collection-top top-inactive">'.$sets[$i]['name'].$partition.'</div>';
				echo '<div class="collection-middle-left"></div>';
				echo '<div class="collection-middle-right"></div>';
				echo '<div class="collection-bottom">
						<div align="left" class="reward-bar-container">
							<div id="account-bar-wrapper2">
							</div>
						</div>
					</div>
				</div>
			</a>';
			}
		}
		?>
		<br><br>
		<?php
	}else if($query == 'difficulty'){
		for($i=0; $i<count($ranksArray); $i++){
			if($ranksArray[$i]['amount'] == 1){
				$problems = 'problem';
			}else{
				$problems = 'problems';
			}
			if($ranksArray[$i]['partition'] == -1){
				$partition = '';
				$partitionLink = '';
			}else{
				$partition = ' #'.($ranksArray[$i]['partition']+1);
				$partitionLink = '?partition='.$ranksArray[$i]['partition'];
			}
			if($ranksArray[$i]['solved'] != 0)
				$isZero = '';
			else
				$isZero = 'display:none;';
			if($lightDark=='light' && $ranksArray[$i]['partition']>=4)
				$lightDarkBoxes = '6';
			echo '<a href="/sets/view/'.$ranksArray[$i]['name'].$partitionLink.'" class="box1link">
				<div class="box1 box1default box1difficulty difficulty-box'.$ranksArray[$i]['id'].'" 
					style="background-color:'.$ranksArray[$i]['color'].';background-image: linear-gradient(rgba(169, 169, 169, 0.'.$lightDarkBoxes.'0), rgba(0, 0, 0, 0.'.$lightDarkBoxes.'5));">';
				if($ranksArray[$i]['solved']>=100)
					echo '<div class="collection-completed">completed</div>';
				echo '<div class="collection-top">'.$ranksArray[$i]['name'].$partition.'</div>';
				echo '<div class="collection-middle-left">'.$ranksArray[$i]['amount'].' '.$problems.'</div>';
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
	}else if($query == 'tags'){
		for($i=0; $i<count($tagList); $i++){
			if($tagList[$i]['amount'] == 1){
				$problems = 'problem';
				$tilde = '';
			}else{
				$problems = 'problems';
				$tilde = '~';
			}
			if($tagList[$i]['partition'] == -1){
				$partition = '';
				$partitionLink = '';
			}else{
				$partition = ' #'.($tagList[$i]['partition']+1);
				$partitionLink = '?partition='.$tagList[$i]['partition'];
			}
			if($tagList[$i]['solved'] != 0)
				$isZero = '';
			else
				$isZero = 'display:none;';
			if($lightDark=='light' && $tagList[$i]['partition']>=4)
				$lightDarkBoxes = '6';
			echo '<a href="/sets/view/'.$tagList[$i]['name'].$partitionLink.'" class="box1link">
				<div class="box1 box1default box1tag tag-box'.$tagList[$i]['id'].'" 
					style="background-color:'.$tagList[$i]['color'].';background-image: linear-gradient(rgba(169, 169, 169, 0.'.$lightDarkBoxes.'0), rgba(0, 0, 0, 0.'.$lightDarkBoxes.'5));">';
				if($tagList[$i]['solved']>=100)
					echo '<div class="collection-completed">completed</div>';
				echo '<div class="collection-top">'.$tagList[$i]['name'].$partition.'</div>';
				echo '<div class="collection-middle-left">'.$tagList[$i]['amount'].' '.$problems.'</div>';
				echo '<div class="collection-middle-right">'.$tilde.$tagList[$i]['difficulty'].'</div>';
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
	}
	?>
	</div>
	<style>
		<?php if($query=='topics'){ ?>
		#topics-button .set-button-text{
			color:#77c14a;
		}
		#set-button-icon-topics i.animate-icon{
			color:#77c14a;
		}
		<?php }else if($query=='difficulty'){ ?>
		#difficulty-button .set-button-text{
			color:#be6cdd;
		}
		#set-button-icon-difficulty i.animate-icon{
			color:#be6cdd;
		}
		<?php }else if($query=='tags'){ ?>
		#tags-button .set-button-text{
			color:#d5795a;
		}
		#set-button-icon-tags i.animate-icon{
			color:#d5795a;
		}
		<?php } ?>
		<?php if($lightDark=='light'){ ?>
			<?php if($query=='topics'){ ?>
			.box1default:hover {
				background-image: linear-gradient(
					rgba(200, 200, 200, 0.3),
					rgba(33, 33, 33, 0.35)
				) !important;
			}
			<?php }else{ ?>
			.box1default:hover {
				background-image: linear-gradient(
					rgba(200, 200, 200, 0.6),
					rgba(33, 33, 33, 0.65)
				) !important;
			}
			<?php } ?>	
		<?php }else{ ?>
			.box1default:hover {
				background-image: linear-gradient(
					rgba(200, 200, 200, 0.3),
					rgba(33, 33, 33, 0.35)
				) !important;
			}
		<?php } ?>
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
	<script>
		<?php if($query == 'topics'){ ?>
			$("#topics-button").css("border", "1px solid #558a35");
		<?php }else if($query == 'difficulty'){ ?>
			$("#difficulty-button").css("border", "1px solid #ac4bd0");
		<?php }else if($query == 'tags'){ ?>
			$("#tags-button").css("border", "1px solid #aa5538");
		<?php } ?>
		<?php
		if($query == 'topics'){
			for($i=0; $i<count($sets); $i++){
				echo 'animateNumber'.$i.'(0, '.$sets[$i]['solved'].', .6);';
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
				echo 'animateBar'.$i.'('.$sets[$i]['solved'].');';
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
		}else if($query == 'difficulty'){
			for($i=0; $i<count($ranksArray); $i++){
				echo 'animateNumber'.$i.'(0, '.$ranksArray[$i]['solved'].', .6);';
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
				echo 'animateBar'.$i.'('.$ranksArray[$i]['solved'].');';
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
		}else if($query == 'tags'){
			for($i=0; $i<count($tagList); $i++){
				echo 'animateNumber'.$i.'(0, '.$tagList[$i]['solved'].', .6);';
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
				echo 'animateBar'.$i.'('.$tagList[$i]['solved'].');';
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
		}
		?>
		let query = "<?php echo $query; ?>";
		let queryRefresh = "<?php echo $queryRefresh; ?>";
		let collectionSize = <?php echo $collectionSize; ?>-0;
		let search1 = [];
		let search2 = [];
		let search3 = [];
		let searchText1 = "";
		let searchText2 = "";
		let searchText3 = "";
		let initialTopicTiles = [];
		let initialDifficultyTiles = [];
		let initialTagTiles = [];
		let topicsToggle = false;
		let difficultyToggle = false;
		let tagsToggle = false;
		let tileTopicsBool = [];
		let activeTopicTiles = [];
		let allTopicTiles = [];
		let activeTopicIds = [];
		let allTopicIds = [];
		let allTopicNames = [];
		let tileDifficultyBool = [];
		let activeDifficultyTiles = [];
		let allDifficultyTiles = [];
		let activeDifficultyIds = [];
		let allDifficultyIds = [];
		let allDifficultyNames = [];
		let tileTagsBool = [];
		let activeTagTiles = [];
		let allTagTiles = [];
		let activeTagIds = [];
		let allTagIds = [];
		let allTagNames = [];
		<?php 
			for($i=0; $i<count($sets); $i++){
				echo 'allTopicIds.push("'.$sets[$i]['id'].'");';
				echo 'allTopicNames.push("'.$sets[$i]['name'].'");';
			}
			for($i=0; $i<count($setTiles); $i++){
				echo 'allTopicTiles.push("'.$setTiles[$i].'");';
				echo 'tileTopicsBool.push(false);';
				echo '$("#tile-topics'.$i.'").click(function(e){
					e.stopPropagation();
					if(!tileTopicsBool['.$i.']){
						handleTiles("'.$setTiles[$i].'", "topics", true);
						activeTopicTiles.push("'.$setTiles[$i].'");
						if(query === "topics"){
							for(let i=0;i<allTopicNames.length;i++)
								if(allTopicNames[i] === "'.$setTiles[$i].'")
									activeTopicIds.push(allTopicIds[i]);
						}
					}else{
						handleTiles("'.$setTiles[$i].'", "topics", false);
						let newActiveTopicTiles = [];
						for(let i=0;i<activeTopicTiles.length;i++){
							if(activeTopicTiles[i] !== "'.$setTiles[$i].'")
								newActiveTopicTiles.push(activeTopicTiles[i]);
						}
						activeTopicTiles = newActiveTopicTiles;
						if(query === "topics"){
							for(let i=0;i<allTopicNames.length;i++){
								if(allTopicNames[i] === "'.$setTiles[$i].'"){
									unselectTopic(allTopicIds[i]);
									break;
								}
							}
						}
					}
					drawActiveCollections();
					drawActiveTiles();
				});';
			}
			for($i=0; $i<count($ranksArray); $i++){
				echo 'allDifficultyIds.push("'.$ranksArray[$i]['id'].'");';
				echo 'allDifficultyNames.push("'.$ranksArray[$i]['name'].'");';
			}
			for($i=0; $i<count($difficultyTiles); $i++){
				echo 'allDifficultyTiles.push("'.$difficultyTiles[$i].'");';
				echo 'tileDifficultyBool.push(false);';
				echo '$("#tile-difficulty'.$i.'").click(function(e){
					e.stopPropagation();
					if(!tileDifficultyBool['.$i.']){
						handleTiles("'.$difficultyTiles[$i].'", "difficulty", true);
						activeDifficultyTiles.push("'.$difficultyTiles[$i].'");
						if(query === "difficulty"){
							for(let i=0;i<allDifficultyNames.length;i++)
								if(allDifficultyNames[i] === "'.$difficultyTiles[$i].'")
									activeDifficultyIds.push(allDifficultyIds[i]);
						}
					}else{
						handleTiles("'.$difficultyTiles[$i].'", "difficulty", false);
						let newActiveDifficultyTiles = [];
						for(let i=0;i<activeDifficultyTiles.length;i++){
							if(activeDifficultyTiles[i] !== "'.$difficultyTiles[$i].'")
								newActiveDifficultyTiles.push(activeDifficultyTiles[i]);
						}
						activeDifficultyTiles = newActiveDifficultyTiles;
						if(query === "difficulty"){
							for(let i=0;i<allDifficultyNames.length;i++){
								if(allDifficultyNames[i] === "'.$difficultyTiles[$i].'"){
									unselectDifficulty(allDifficultyIds[i]);
									break;
								}
							}
						}
					}
					drawActiveCollections();
					drawActiveTiles();
				});';
			}
			for($i=0; $i<count($tagList); $i++){
				echo 'allTagIds.push("'.$tagList[$i]['id'].'");';
				echo 'allTagNames.push("'.$tagList[$i]['name'].'");';
			}
			for($i=0; $i<count($tagTiles); $i++){
				echo 'allTagTiles.push("'.$tagTiles[$i].'");';
				echo 'tileTagsBool.push(false);';
				echo '$("#tile-tags'.$i.'").click(function(e){
					e.stopPropagation();
					if(!tileTagsBool['.$i.']){
						handleTiles("'.$tagTiles[$i].'", "tags", true);
						activeTagTiles.push("'.$tagTiles[$i].'");
						if(query === "tags"){
							for(let i=0;i<allTagNames.length;i++)
								if(allTagNames[i] === "'.$tagTiles[$i].'")
									activeTagIds.push(allTagIds[i]);
						}	
					}else{
						handleTiles("'.$tagTiles[$i].'", "tags", false);
						let newActiveTagTiles = [];
						for(let i=0;i<activeTagTiles.length;i++){
							if(activeTagTiles[i] !== "'.$tagTiles[$i].'")
								newActiveTagTiles.push(activeTagTiles[i]);
						}
						activeTagTiles = newActiveTagTiles;
						if(query === "tags"){
							for(let i=0;i<allTagNames.length;i++){
								if(allTagNames[i] === "'.$tagTiles[$i].'"){
									unselectTag(allTagIds[i]);
									break;
								}
							}
						}	
					}
					drawActiveCollections();
					drawActiveTiles();
				});';
			}
			for($i=0; $i<count($search1); $i++){
				echo 'search1.push("'.$search1[$i].'");';
				echo 'searchText1 = searchText1 + "'.$search1[$i].'" + "@";';
				echo 'activeTopicTiles.push("'.$search1[$i].'");';
				echo 'handleTiles("'.$search1[$i].'", "topics", true);';
			}
			for($i=0; $i<count($search2); $i++){
				echo 'search2.push("'.$search2[$i].'");';
				echo 'searchText2 = searchText2 + "'.$search2[$i].'" + "@";';
				echo 'activeDifficultyTiles.push("'.$search2[$i].'");';
				echo 'handleTiles("'.$search2[$i].'", "difficulty", true);';
			}
			for($i=0; $i<count($search3); $i++){
				echo 'search3.push("'.$search3[$i].'");';
				echo 'searchText3 = searchText3 + "'.$search3[$i].'" + "@";';
				echo 'activeTagTiles.push("'.$search3[$i].'");';
				echo 'handleTiles("'.$search3[$i].'", "tags", true);';
			}
		?>
		$(document).ready(function(){
			setCookie("query", query);
			drawActiveCollections();
			drawActiveTiles(true);
		});
		function handleTiles(name, query, select){
			let color = "#ffffff1a";
			if(select){
				if(query === "topics"){
					color = "#558a35";
				}else if(query === "difficulty"){
					color = "#ac4bd0";
				}else if(query === "tags"){
					color = "#aa5538";
				}	
			}
			let position = -1;
			if(query === "topics"){
				for(let i=0;i<allTopicTiles.length;i++)
					if(name === allTopicTiles[i])
						position = i;
				$("#tile-"+query+position).css("background", color);
				tileTopicsBool[position] = select;
			}else if(query === "difficulty"){
				for(let i=0;i<allDifficultyTiles.length;i++)
					if(name === allDifficultyTiles[i])
						position = i;
				$("#tile-"+query+position).css("background", color);
				tileDifficultyBool[position] = select;
			}else if(query === "tags"){
				for(let i=0;i<allTagTiles.length;i++)
					if(name === allTagTiles[i])
						position = i;
				$("#tile-"+query+position).css("background", color);
				tileTagsBool[position] = !tileTagsBool[position];
			}
		}
		function drawActiveCollections(){
			<?php if($query == 'topics'){ ?>
				if(activeTopicIds.length<1){
					for(let i=0;i<allTopicIds.length;i++)
						$(".topic-box"+allTopicIds[i]).css("display", "grid");
				}else{
					for(let i=0;i<allTopicIds.length;i++)
						if(!activeTopicIds.includes(allTopicIds[i]))
							$(".topic-box"+allTopicIds[i]).css("display", "none");
					let pos = [];	
					let posX = [];	
					for(let i=0;i<activeTopicIds.length;i++){
						for(let j=0;j<allTopicIds.length;j++){
							if(activeTopicIds[i] === allTopicIds[j] && !posX.includes(allTopicIds[j])){
								posX.push(allTopicIds[j]);
								pos.push(j);
							}
						}
					}
					let posXX = [];
					for(let i=0;i<pos.length;i++)
						posXX.push(allTopicNames[pos[i]]);
					for(let i=0;i<tileTopicsBool.length;i++)
						tileTopicsBool[i] = false;
					for(let i=0;i<allTopicTiles.length;i++)
						if(posXX.includes(allTopicTiles[i]))
							tileTopicsBool[i] = true;
					for(let i=0;i<activeTopicIds.length;i++)
						$(".topic-box"+activeTopicIds[i]).fadeIn(250);
				}
			<?php }else if($query == 'difficulty'){ ?>
				if(activeDifficultyIds.length<1){
					for(let i=0;i<allDifficultyIds.length;i++)
						$(".difficulty-box"+allDifficultyIds[i]).css("display", "grid");
				}else{
					for(let i=0;i<allDifficultyIds.length;i++)
						if(!activeDifficultyIds.includes(allDifficultyIds[i]))
							$(".difficulty-box"+allDifficultyIds[i]).css("display", "none");
					let pos = [];	
					let posX = [];	
					for(let i=0;i<activeDifficultyIds.length;i++){
						for(let j=0;j<allDifficultyIds.length;j++){
							if(activeDifficultyIds[i] === allDifficultyIds[j] && !posX.includes(allDifficultyIds[j])){
								posX.push(allDifficultyIds[j]);
								pos.push(j);
							}
						}
					}
					let posXX = [];
					for(let i=0;i<pos.length;i++)
						posXX.push(allDifficultyNames[pos[i]]);
					for(let i=0;i<tileDifficultyBool.length;i++)
					tileDifficultyBool[i] = false;
					for(let i=0;i<allDifficultyTiles.length;i++)
						if(posXX.includes(allDifficultyTiles[i]))
							tileDifficultyBool[i] = true;
					for(let i=0;i<activeDifficultyIds.length;i++)
						$(".difficulty-box"+activeDifficultyIds[i]).fadeIn(250);
				}
			<?php }else if($query == 'tags'){ ?>
				if(activeTagIds.length<1){
					for(let i=0;i<allTagIds.length;i++)
						$(".tag-box"+allTagIds[i]).css("display", "grid");
				}else{
					for(let i=0;i<allTagIds.length;i++)
						if(!activeTagIds.includes(allTagIds[i]))
							$(".tag-box"+allTagIds[i]).css("display", "none");
					let pos = [];	
					let posX = [];	
					for(let i=0;i<activeTagIds.length;i++){
						for(let j=0;j<allTagIds.length;j++){
							if(activeTagIds[i] === allTagIds[j] && !posX.includes(allTagIds[j])){
								posX.push(allTagIds[j]);
								pos.push(j);
							}
						}
					}
					let posXX = [];
					for(let i=0;i<pos.length;i++)
						posXX.push(allTagNames[pos[i]]);
					for(let i=0;i<tileTagsBool.length;i++)
						tileTagsBool[i] = false;
					for(let i=0;i<allTagTiles.length;i++)
						if(posXX.includes(allTagTiles[i]))
							tileTagsBool[i] = true;
					for(let i=0;i<activeTagIds.length;i++)
						$(".tag-box"+activeTagIds[i]).fadeIn(250);
				}
			<?php } ?>
		}
		function drawActiveTiles(initialDraw = false){
			$(".active-tiles-container").html("");
			let search1 = "@";
			let search2 = "@";
			let search3 = "@";
			setCookie("search1", "@");
			setCookie("search2", "@");
			setCookie("search3", "@");
			let initialDrawCounter = 0;

			if(initialDraw){
				for(let i=0;i<activeTopicTiles.length;i++){
					initialTopicTiles.push(activeTopicTiles[i]);
					initialDrawCounter++;
				}
				for(let i=0;i<activeDifficultyTiles.length;i++){
					initialDifficultyTiles.push(activeDifficultyTiles[i]);
					initialDrawCounter++;
				}
				for(let i=0;i<activeTagTiles.length;i++){
					initialTagTiles.push(activeTagTiles[i]);
					initialDrawCounter++;
				}
			}
			for(let i=0;i<activeTopicTiles.length;i++){
				let initialTile = false;
				for(let j=0;j<initialTopicTiles.length;j++)
					if(activeTopicTiles[i] === initialTopicTiles[j])
						initialTile = true;
				if(query == "topics" || initialTile) 
					$(".active-tiles-container").append('<div class="dropdown-tile tile-color1" id="active-tiles-element'+i+'" onclick="removeActiveTopic('+i+')">'+activeTopicTiles[i]+'</div>');
				search1 = search1 + activeTopicTiles[i] + "@";
			}
			for(let i=0;i<activeDifficultyTiles.length;i++){
				let initialTile = false;
				for(let j=0;j<initialDifficultyTiles.length;j++)
					if(activeDifficultyTiles[i] === initialDifficultyTiles[j])
						initialTile = true;
				if(query == "difficulty" || initialTile) 
					$(".active-tiles-container").append('<div class="dropdown-tile tile-color2" id="active-tiles-element'+i+'" onclick="removeActiveDifficulty('+i+')">'+activeDifficultyTiles[i]+'</div>');
				search2 = search2 + activeDifficultyTiles[i] + "@";
			}
			for(let i=0;i<activeTagTiles.length;i++){
				let initialTile = false;
				for(let j=0;j<initialTagTiles.length;j++)
					if(activeTagTiles[i] === initialTagTiles[j])
						initialTile = true;
				if(query == "tags" || initialTile) 
					$(".active-tiles-container").append('<div class="dropdown-tile tile-color3" id="active-tiles-element'+i+'" onclick="removeActiveTag('+i+')">'+activeTagTiles[i]+'</div>');
				search3 = search3 + activeTagTiles[i] + "@";
			}
			setCookie("search1", search1);
			setCookie("search2", search2);
			setCookie("search3", search3);
			if(query=="topics" && activeTopicTiles.length>0 
			|| query=="difficulty" && activeDifficultyTiles.length>0 
			|| query=="tags" && activeTagTiles.length>0
			|| initialDraw && initialDrawCounter>0)
				$(".active-tiles-container").append('<a class="dropdown-tile tile-color4" href="" id="unselect-active-tiles">clear</a>');
		}

		$(".active-tiles-container").on("click", "#unselect-active-tiles", function(e){
			e.preventDefault();
			$(".active-tiles-container").html("");
			setCookie("search1", "@");
			setCookie("search2", "@");
			setCookie("search3", "@");
			window.location.href = "/sets";
		});
		
		function removeActiveTopic(index){
			let removeMap = 0;
			for(let i=0;i<allTopicTiles.length;i++){
				if(allTopicTiles[i] === activeTopicTiles[index])
					removeMap = i;
			}
			for(let i=0;i<allTopicNames.length;i++){
				if(allTopicNames[i] === allTopicTiles[removeMap]){
					unselectTopic(allTopicIds[i]);
					break;
				}
			}
			for(let i=0;i<allTopicTiles.length;i++){
				if(allTopicTiles[i] === activeTopicTiles[index])
					$("#tile-topics"+i).css("background", "#ffffff1a");
			}
			let newTiles = [];
			for(let i=0;i<activeTopicTiles.length;i++){
				if(i !== index)
					newTiles.push(activeTopicTiles[i]);
			}
			activeTopicTiles = newTiles;

			drawActiveCollections();
			drawActiveTiles();
			if(query !== "topics" || queryRefresh)
				window.location.href = "/sets";
		}

		function removeActiveDifficulty(index){
			let removeMap = 0;
			for(let i=0;i<allDifficultyTiles.length;i++){
				if(allDifficultyTiles[i] === activeDifficultyTiles[index])
					removeMap = i;
			}
			for(let i=0;i<allDifficultyNames.length;i++){
				if(allDifficultyNames[i] === allDifficultyTiles[removeMap]){
					unselectDifficulty(allDifficultyIds[i]);
					break;
				}
			}
			for(let i=0;i<allDifficultyTiles.length;i++){
				if(allDifficultyTiles[i] === activeDifficultyTiles[index])
					$("#tile-difficulty"+i).css("background", "#ffffff1a");
			}
			let newTiles = [];
			for(let i=0;i<activeDifficultyTiles.length;i++){
				if(i !== index)
					newTiles.push(activeDifficultyTiles[i]);
			}
			activeDifficultyTiles = newTiles;
			
			drawActiveCollections();
			drawActiveTiles();
			if(query !== "difficulty" || queryRefresh)
				window.location.href = "/sets";
		}
		
		function removeActiveTag(index){
			let removeMap = 0;
			for(let i=0;i<allTagTiles.length;i++){
				if(allTagTiles[i] === activeTagTiles[index])
					removeMap = i;
			}
			for(let i=0;i<allTagNames.length;i++){
				if(allTagNames[i] === allTagTiles[removeMap]){
					unselectTag(allTagIds[i]);
					break;
				}
			}
			for(let i=0;i<allTagTiles.length;i++){
				if(allTagTiles[i] === activeTagTiles[index])
					$("#tile-tags"+i).css("background", "#ffffff1a");
			}
			let newTiles = [];
			for(let i=0;i<activeTagTiles.length;i++){
				if(i !== index)
					newTiles.push(activeTagTiles[i]);
			}
			activeTagTiles = newTiles;

			drawActiveCollections();
			drawActiveTiles();
			if(query !== "tags" || queryRefresh)
				window.location.href = "/sets";
		}

		function unselectTopic(id){
			let newActiveIds = [];
			for(let i=0;i<activeTopicIds.length;i++){
				if(activeTopicIds[i] !== id){
					newActiveIds.push(activeTopicIds[i]);
				}
			}
			activeTopicIds = newActiveIds;
		}
		function unselectDifficulty(id){
			let newActiveIds = [];
			for(let i=0;i<activeDifficultyIds.length;i++){
				if(activeDifficultyIds[i] !== id){
					newActiveIds.push(activeDifficultyIds[i]);
				}
			}
			activeDifficultyIds = newActiveIds;
		}
		function unselectTag(id){
			let newActiveIds = [];
			for(let i=0;i<activeTagIds.length;i++){
				if(activeTagIds[i] != id){
					newActiveIds.push(activeTagIds[i]);
				}
			}	
			activeTagIds = newActiveIds;
		}

		$("#topics-button").click(function(e){
			e.stopPropagation();
			this.querySelector('#set-button-icon-topics i.fa').classList.toggle('rotateArrow');
			if(!topicsToggle){
				topicsToggle = true;
				$("#dropdown-topics").fadeIn(250);
				if(difficultyToggle){
					document.querySelector('#set-button-icon-difficulty i.fa').classList.toggle('rotateArrow');
					difficultyToggle = false;
					$("#dropdown-difficulty").fadeOut(250);
				}
				if(tagsToggle){
					document.querySelector('#set-button-icon-tags i.fa').classList.toggle('rotateArrow');
					tagsToggle = false;
					$("#dropdown-tags").fadeOut(250);
				}
			}else{
				topicsToggle = false;
				$("#dropdown-topics").fadeOut(250);
			}
		});
		$("#difficulty-button").click(function(e){
			e.stopPropagation();
			this.querySelector('#set-button-icon-difficulty i.fa').classList.toggle('rotateArrow');
			if(!difficultyToggle){
				difficultyToggle = true;
				$("#dropdown-difficulty").fadeIn(250);
				if(topicsToggle){
					document.querySelector('#set-button-icon-topics i.fa').classList.toggle('rotateArrow');
					topicsToggle = false;
					$("#dropdown-topics").fadeOut(250);
				}
				if(tagsToggle){
					document.querySelector('#set-button-icon-tags i.fa').classList.toggle('rotateArrow');
					tagsToggle = false;
					$("#dropdown-tags").fadeOut(250);
				}
			}else{
				difficultyToggle = false;
				$("#dropdown-difficulty").fadeOut(250);
			}
		});
		$("#tags-button").click(function(e){
			e.stopPropagation();
			this.querySelector('#set-button-icon-tags i.fa').classList.toggle('rotateArrow');
			if(!tagsToggle){
				tagsToggle = true;
				$("#dropdown-tags").fadeIn(250);
				if(topicsToggle){
					document.querySelector('#set-button-icon-topics i.fa').classList.toggle('rotateArrow');
					topicsToggle = false;
					$("#dropdown-topics").fadeOut(250);
				}
				if(difficultyToggle){
					document.querySelector('#set-button-icon-difficulty i.fa').classList.toggle('rotateArrow');
					difficultyToggle = false;
					$("#dropdown-difficulty").fadeOut(250);
				}
			}else{
				tagsToggle = false;
				$("#dropdown-tags").fadeOut(250);
			}
		});
		$(".dropdown-boxes").click(function(e){
			e.stopPropagation();
		});
		$(".whitebox2").click(function(e){
			if(topicsToggle){
				document.querySelector('#set-button-icon-topics i.fa').classList.toggle('rotateArrow');
				topicsToggle = false;
				$("#dropdown-topics").fadeOut(250);
			}
			if(difficultyToggle){
				document.querySelector('#set-button-icon-difficulty i.fa').classList.toggle('rotateArrow');
				difficultyToggle = false;
				$("#dropdown-difficulty").fadeOut(250);
			}
			if(tagsToggle){
				document.querySelector('#set-button-icon-tags i.fa').classList.toggle('rotateArrow');
				tagsToggle = false;
				$("#dropdown-tags").fadeOut(250);
			}
		});

		$("#set-size-input").on("input", function(){
				let isNum = /^\d+$/.test($("#set-size-input").val());
				if(isNum && $("#set-size-input").val()>=5)
					$("#submit-size-button").removeAttr("disabled");
				else
					$("#submit-size-button").attr("disabled","disabled");
		});

		function submitSize(){
			const size = $("#set-size-input").val();
			setCookie("collectionSize", size);
			window.location.href = "/sets";
		}

		$("#tile-topics-submit").click(function(e){
			setCookie("query", "topics");
			setCookie("collectionSize", collectionSize);
			window.location.href = "/sets";
		});
		$("#tile-difficulty-submit").click(function(e){
			setCookie("query", "difficulty");
			setCookie("collectionSize", collectionSize);
			window.location.href = "/sets";
		});
		$("#tile-tags-submit").click(function(e){
			setCookie("query", "tags");
			setCookie("collectionSize", collectionSize);
			window.location.href = "/sets";
		});

		let selectAllTopics = false;
		let selectAllDifficulty = false;
		let selectAllTags = false;

		$("#tile-topics-select-all").click(function(e){
			e.stopPropagation();
			if(selectAllTopics){
				$("#tile-topics-select-all").html("Select all");
				activeTopicTiles = [];
				activeTopicIds = allTopicIds;
				for(let i=0;i<allTopicTiles.length;i++)
					$("#tile-topics"+i).css("background", "#ffffff1a");
				for(let i=0;i<tileTopicsBool.length;i++)
					tileTopicsBool[i] = false;
			}else{
				$("#tile-topics-select-all").html("Unselect all");
				activeTopicTiles = allTopicTiles;
				activeTopicIds = allTopicIds;
				for(let i=0;i<allTopicTiles.length;i++)
					$("#tile-topics"+i).css("background", "#558a35");
				for(let i=0;i<tileTopicsBool.length;i++)
					tileTopicsBool[i] = true;
			}
			selectAllTopics = !selectAllTopics;
			drawActiveTiles();
			drawActiveCollections();
		});
		$("#tile-difficulty-select-all").click(function(e){
			e.stopPropagation();
			if(selectAllDifficulty){
				$("#tile-difficulty-select-all").html("Select all");
				activeDifficultyTiles = [];
				activeDifficultyIds = allDifficultyIds;
				for(let i=0;i<allDifficultyTiles.length;i++)
					$("#tile-difficulty"+i).css("background", "#ffffff1a");
				for(let i=0;i<tileDifficultyBool.length;i++)
					tileDifficultyBool[i] = false;
			}else{
				$("#tile-difficulty-select-all").html("Unselect all");
				activeDifficultyTiles = allDifficultyTiles;
				activeDifficultyIds = allDifficultyIds;
				for(let i=0;i<allDifficultyTiles.length;i++)
					$("#tile-difficulty"+i).css("background", "#ac4bd0");
				for(let i=0;i<tileDifficultyBool.length;i++)
					tileDifficultyBool[i] = true;
			}
			selectAllDifficulty = !selectAllDifficulty;
			drawActiveTiles();
			drawActiveCollections();
		});
		$("#tile-tags-select-all").click(function(e){
			e.stopPropagation();
			if(selectAllTags){
				$("#tile-tags-select-all").html("Select all");
				activeTagTiles = [];
				activeTagIds = allTagIds;
				for(let i=0;i<allTagTiles.length;i++)
					$("#tile-tags"+i).css("background", "#ffffff1a");
				for(let i=0;i<tileTagsBool.length;i++)
					tileTagsBool[i] = false;
			}else{
				$("#tile-tags-select-all").html("Unselect all");
				activeTagTiles = allTagTiles;
				activeTagIds = allTagIds;
				for(let i=0;i<allTagTiles.length;i++)
					$("#tile-tags"+i).css("background", "#aa5538");
				for(let i=0;i<tileTagsBool.length;i++)
					tileTagsBool[i] = true;
			}
			selectAllTags = !selectAllTags;
			drawActiveTiles();
			drawActiveCollections();
		});
	</script>

