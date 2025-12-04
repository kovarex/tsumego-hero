<script src ="/js/previewBoard.js"></script>
<?php
	if(!Auth::isLoggedIn())
		echo '<script type="text/javascript">window.location.href = "/";</script>';

	$empty = false;
	$num1 = 0;
	$idToken1 = 0;
	$idToken2 = 0;
	if($comments==null) $empty = true;
	for($i=0; $i<10; $i++){
		if(is_numeric($comments[$i]['TsumegoComment']['status'])) $comments[$i]['TsumegoComment']['textAnswer'] = 'false';
		else{
			$comments[$i]['TsumegoComment']['textAnswer'] = $comments[$i]['TsumegoComment']['status'];
			$comments[$i]['TsumegoComment']['status'] = 100;
		}
	}
	if(count($comments)!=11){
		$num1 = $comments[0]['TsumegoComment']['counter']+9;
		for($j=0; $j<10; $j++){
			if($j==1) $idToken2 = $comments[$j]['TsumegoComment']['id'];
		}
		if($idToken2==0) $idToken2 = $comments[0]['TsumegoComment']['id'] -1;
	}else{
		for($j=0; $j<10; $j++){
			if($j==1) $idToken2 = $comments[$j]['TsumegoComment']['id'];
			$num1 = $comments[$j]['TsumegoComment']['counter'];
			$idToken1 = $comments[$j]['TsumegoComment']['id'];
		}
	}
?>

<div class="imp">
<?php if(Auth::isAdmin()){ ?>
	<div class="admin-panel-main-page" style="top:10px;left:540px">
		<ul>
			<li><a class="adminLink2" href="/users/adminstats">Activities</a></li>
		</ul>
	</div>
	<?php } ?>
	<table class="co-table" width="100%">
	<tr>
	<td width="50%">
		<p class="title4">Comments</p>
		<div class="new1" width="100%" style="margin-bottom:15px;">
			<div align="center">
				<br>
				<?php
					if(!$empty){
						if(Auth::isAdmin()){
							if(!isset($unresolved)){ $unresolved = 'false'; $unresolvedSet = 'false'; }
							else $unresolvedSet = 'true';
							if($unresolvedSet == 'false'){
								$unresolvedSet = '';
							}else{
								if($unresolved == 'false'){ $unresolvedSet = 'unresolved=false&'; $filter1=='false'; }
								elseif($unresolved == 'true'){ $unresolvedSet = 'unresolved=true&'; $filter1=='false'; }
							}
							}
						if(isset($filter1)){
							if($filter1=='true') $filter11='false';
							else $filter11='true';
							if($filter11=='false'){ $filterx='viewable'; $filterx2=''; }
							else{ $filterx='all'; $filterx2='2'; }
							echo '
							<div style="float:left;">
								<a href="/comments?filter='.$filter11.'" class="new-button'.$filterx2.'">'.$filterx.'</a>
							</div>';
							$filter1 = 'filter='.$filter1.'&';
						}else{
							echo '
							<div style="float:left;">
								<a href="/comments?filter=false" class="new-button">viewable</a>
							</div>';
							$filter1 = '';
						}
						if($firstPage && count($comments)<11);
						else{
							$moreparams = '';
							if($paramyourcommentid!=0) $moreparams = '&your-direction='.$paramyourdirection.'&your-index='.$paramyourindex.'&your-comment-id='.$paramyourcommentid;
							if($index!=0) echo '<a href="/comments?'.$unresolvedSet.$filter1.'direction=prev&index='.($num1-20).'&comment-id='.$idToken2.$moreparams.'" class="new-button" >prev. page</a>';
							else echo '<a class="new-button-inactive" >prev. page</a>';
							if(count($comments)==11) echo '<a href="/comments?'.$unresolvedSet.$filter1.'direction=next&index='.$num1.'&comment-id='.$idToken1.$moreparams.'" class="new-button" >next page</a>';
							else echo '<a class="new-button-inactive" >next page</a>';
						}
						if(Auth::isAdmin()){
							if($unresolved=='false'){
								echo '<div style="float:right;">
									<a href="/comments?unresolved=true" class="new-button2">all</a>
								</div>';
							}elseif($unresolved=='true'){
								echo '<div style="float:right;">
									<a href="/comments?unresolved=false" class="new-button">unresolved('.$comments3.')</a>
								</div>';
						}
						}else{
								echo '<div style="float:right;">
								<a href="" class="new-button-inactive3"></a>
								</div>';
						}
						}else{
						echo 'There are no comments to your solved problems.';
							}
				?>
				<br><br>
			</div>
		</div>
		<?php $yourComments->render(); ?>
	<div width="100%">
		<div align="center">
			<?php
				if(!$empty){
					if($firstPage && count($comments)<11);
					else{
						$moreparams = '';
						if($paramyourcommentid!=0) $moreparams = '&your-direction='.$paramyourdirection.'&your-index='.$paramyourindex.'&your-comment-id='.$paramyourcommentid;
						if($index!=0) echo '<a href="/comments?'.$unresolvedSet.$filter1.'direction=prev&index='.($num1-20).'&comment-id='.$idToken2.$moreparams.'" class="new-button" >prev. page</a>';
						else echo '<a class="new-button-inactive" >prev. page</a>';
						if(count($comments)==11) echo '<a href="/comments?'.$unresolvedSet.$filter1.'direction=next&index='.$num1.'&comment-id='.$idToken1.$moreparams.'" class="new-button" >next page</a>';
						else echo '<a class="new-button-inactive" >next page</a>';
					}
					}
			?>
			<br><br>
		</div>
	</div>
	</td>
	<td width="50%">
		<p class="title4">Your Comments</p>
		<div class="new1" width="100%" style="margin-bottom:15px;">
			<div align="center"></div>
		</div>
	<div width="100%">
		<div align="center"><br><br></div>
	</div>

	</td>
	</tr>
	</table>
</div>
<?php
//echo '<pre>';print_r($comments);echo '</pre>';
?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
		<script>
			let loadMore2 = 10;
			$('#more-button2').click(function(e){
				e.preventDefault();
				let counter2 = 0;
				while(counter2<10){
					$('#your-comment'+loadMore2).fadeIn();
					$('#your-space'+loadMore2).fadeIn();
					loadMore2++;
					counter2++;
					}
			});
			var tooltipSgfs = window.tooltipSgfs || [];
			<?php
			for($a=0; $a<count($tooltipSgfs); $a++){
				echo 'tooltipSgfs['.$a.'] = [];';
				for($y=0; $y<count($tooltipSgfs[$a]); $y++){
					echo 'tooltipSgfs['.$a.']['.$y.'] = [];';
					for($x=0; $x<count($tooltipSgfs[$a][$y]); $x++){
						echo 'tooltipSgfs['.$a.']['.$y.'].push("'.$tooltipSgfs[$a][$x][$y].'");';
					}
					}
					}
			?>
			var tooltipSgfs2 = window.tooltipSgfs2 || [];
		<?php
			for($a=0; $a<count($tooltipSgfs2); $a++){
				echo 'tooltipSgfs2['.$a.'] = [];';
				for($y=0; $y<count($tooltipSgfs2[$a]); $y++){
					echo 'tooltipSgfs2['.$a.']['.$y.'] = [];';
					for($x=0; $x<count($tooltipSgfs2[$a][$y]); $x++){
						echo 'tooltipSgfs2['.$a.']['.$y.'].push("'.$tooltipSgfs2[$a][$x][$y].'");';
						}
						}
						}
				?>
		</script>
		<style>
			.unresolved-tab-list{
				margin: 0;
				padding: 0;
						}
			.unresolved-tab-list li{
				display: inline-block;
				list-style-type: none;
				background-color:cadetblue;
				border-bottom: 3px solid  #858585;
				font-family: Verdana;
				text-transform: uppercase;
				letter-spacing: 0.2em;
						}
			.unresolved-tab-list li a{
				color:#f2f2f2;
				display: block;
				padding: 3px 10px 3px 10px;
				 text-decoration: none;
						}
			.unresolved-tab-list li.unresolved-active, .tab-list li:hover{
				background: #e5e5e5;
				border-bottom: 3px solid #e5e5e5;
						}
			.unresolved-tab-list li.unresolved-active a, .tab-list li a:hover{
				background: #e5e5e5;
				color:#666;

			}
			.unresolved-tab-panel{
				display: none;
				color:#666;
				background: #e5e5e5;
				min-height:150px;
				overflow: auto;
						}
			.unresolved-tab-panel.unresolved-active{
				display: block;

			}
			.unresolved-tab-list p{
				margin: 20px;
						}

		</style>

