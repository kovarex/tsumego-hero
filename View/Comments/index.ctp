<script src ="/js/previewBoard.js"></script>
<?php 
	if(!isset($_SESSION['loggedInUser']))
		echo '<script type="text/javascript">window.location.href = "/";</script>';

	$empty = false;
	$num1 = 0;
	$idToken1 = 0;
	$idToken2 = 0;
	if($comments==null) $empty = true;
	for($i=0; $i<10; $i++){
		if(is_numeric($comments[$i]['Comment']['status'])) $comments[$i]['Comment']['textAnswer'] = 'false';
		else{
			$comments[$i]['Comment']['textAnswer'] = $comments[$i]['Comment']['status'];	
			$comments[$i]['Comment']['status'] = 100;
		}
	}
	if(count($comments)!=11){
		$num1 = $comments[0]['Comment']['counter']+9;
		for($j=0; $j<10; $j++){
			if($j==1) $idToken2 = $comments[$j]['Comment']['id'];
		}
		if($idToken2==0) $idToken2 = $Comments[0]['Comment']['id'] -1;
	}else{
		for($j=0; $j<10; $j++){
			if($j==1) $idToken2 = $comments[$j]['Comment']['id'];
			$num1 = $comments[$j]['Comment']['counter'];
			$idToken1 = $comments[$j]['Comment']['id'];
		}
	}
?>

<div class="imp">
<?php if($_SESSION['loggedInUser']['User']['isAdmin'] > 0){ ?>
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
						if($_SESSION['loggedInUser']['User']['isAdmin']==1){
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
						if($_SESSION['loggedInUser']['User']['isAdmin']==1){
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
		<?php 
			for($j=0; $j<10; $j++){
				if(isset($comments[$j]['Comment']['user_name'])){
					if($j<100) $display = " ";
					else $display = 'style="display:none;"';
					echo '<div class="sandboxComment" id="comment'.$j.'" '.$display.'>';
					if($comments[$j]['Comment']['solved']==1 || $_SESSION['loggedInUser']['User']['isAdmin']>0){
						$commentColor = 'commentBox1';
						for($a=0; $a<count($admins); $a++)
							if($comments[$j]['Comment']['user_name']==$admins[$a]['User']['name'])
								$commentColor = 'commentBox2';
						if($comments[$j]['Comment']['set_id']!=null){
							$sid = '?sid='.$comments[$j]['Comment']['set_id'];
							$QorA = '&';
						}else{
							$sid = '';
							$QorA = '?';
						}
						echo '<table class="sandboxTable2" width="100%" border="0">';
							echo '
								<tr>
								<td width="73%">		
								<div style="padding-bottom:7px;"><b>#'.$comments[$j]['Comment']['counter'].'</b> | ';
								if($comments[$j]['Comment']['num']!=null){
									echo '<a href="/tsumegos/play/'.$comments[$j]['Comment']['tsumego_id'].$sid.$QorA.'search=topics">
										'.$comments[$j]['Comment']['set'].' '.$comments[$j]['Comment']['set2'].' - '.$comments[$j]['Comment']['num'].'
									</a>';
								}else{
									echo '<i>Deleted problem.</i> <a href="/comments/remove/'.$comments[$j]['Comment']['id'].'?token='.md5($comments[$j]['Comment']['id']).'" target="_blank">[remove comment]</a>';
								}
								echo '<br>
								</div>
								<div class="'.$commentColor.'"> 
								'.$comments[$j]['Comment']['user_name'].':<br>
								'.$comments[$j]['Comment']['message'].'</div>';
								
								if($comments[$j]['Comment']['status']!=0 && $comments[$j]['Comment']['status']!=97 && $comments[$j]['Comment']['status']!=98 && $comments[$j]['Comment']['status']!=96){
									echo '<div class="commentAnswer"><div style="padding-top:7px;"></div>'.$comments[$j]['Comment']['admin_name'].':<br>';
									if($comments[$j]['Comment']['status']==1) echo 'Your moves have been added.<br>';
									else if($comments[$j]['Comment']['status']==2) echo 'Your file has been added.<br>';
									else if($comments[$j]['Comment']['status']==3) echo 'Your solution has been added.<br>';
									else if($comments[$j]['Comment']['status']==4) echo 'I disagree with your comment.<br>';
									else if($comments[$j]['Comment']['status']==5) echo 'Provide sequence.<br>';
									else if($comments[$j]['Comment']['status']==6) echo 'Resolved.<br>';
									else if($comments[$j]['Comment']['status']==7) echo 'I couldn\'t follow your comment.<br>';
									else if($comments[$j]['Comment']['status']==8) echo 'You seem to try sending non-SGF-files.<br>';
									else if($comments[$j]['Comment']['status']==9) echo 'You answer is inferior to the correct solution.<br>';
									else if($comments[$j]['Comment']['status']==10) echo 'I disagree with your comment. I added sequences.<br>';
									else if($comments[$j]['Comment']['status']==11) echo 'I don\'t know.<br>';
									else if($comments[$j]['Comment']['status']==12) echo 'I added sequences.<br>';
									else if($comments[$j]['Comment']['status']==13) echo 'You are right, but the presented sequence is more interesting.<br>';
									else if($comments[$j]['Comment']['status']==14) echo 'I didn\'t add your file.<br>';
									else if($comments[$j]['Comment']['status']==100) echo $comments[$j]['Comment']['textAnswer'];
									else echo $comments[$j]['Comment']['status'];
									
									echo '</div>';
								}
								echo '</td>
								<td class="sandboxTable2time" align="right">'.$comments[$j]['Comment']['created'].'</td>';
							echo '</tr>';
							if($comments[$j]['Comment']['num']!=null){
								echo '<tr>
								<td colspan="2">
									<div width="100%">
										<div align="center">
											<li id="naviElement0" class="set'.$comments[$j]['Comment']['user_tsumego'].'1" style="float:left;margin-top:14px;">
												<a id="tooltip-hover'.$j.'" class="tooltip" href="/tsumegos/play/'.$comments[$j]['Comment']['tsumego_id'].$sid.$QorA
												.'search=topics">'.$comments[$j]['Comment']['num']
												.'<span><div id="tooltipSvg'.$j.'"></div></span></a>
												
											</li>
										</div>
									</div>
								</td>
							</tr>';
							}else{
								echo '<tr>
								<td colspan="2">
									<div width="100%">
										<div align="center">
											
										</div>
									</div>
								</td>
							</tr>';
							}
						echo '</table>';
					}else{
						if($comments[$j]['Comment']['set_id']!=null){
							$sid = '?sid='.$comments[$j]['Comment']['set_id'];
							$QorA = '&';
						}else{
							$sid = '';
							$QorA = '?';
						}
						echo '<table class="sandboxTable2" width="100%" border="0">';
							echo '
								<tr>
								<td width="73%">		
								<div><b>#'.$comments[$j]['Comment']['counter'].'</b> | 
								<a href="/tsumegos/play/'.$comments[$j]['Comment']['tsumego_id'].$sid.$QorA.'search=topics">
									'.$comments[$j]['Comment']['set'].' '.$comments[$j]['Comment']['set2'].' - '.$comments[$j]['Comment']['num'].'
								</a><br>
								<div class="commentAnswer" style="color:#5e5e5e;"><div style="padding-top:14px;"></div>[You need to solve this problem to see the comment]</div>
								</div>';
								
								echo '</td>
								<td class="sandboxTable2time" align="right">'.$comments[$j]['Comment']['created'].'</td>';
							echo '</tr>';
							echo '
								<tr>
									<td colspan="2">
										<div width="100%">
											<div align="center">
												<li id="naviElement0" class="set'.$comments[$j]['Comment']['user_tsumego'].'1" style="float:left;margin-top:14px;">
													<a href="/tsumegos/play/'.$comments[$j]['Comment']['tsumego_id'].$sid.$QorA.'search=topics">'.$comments[$j]['Comment']['num'].'</a>
													
												</li>
											</div>
										</div>
									</td>
								</tr>
							';
						echo '</table>';
					}
					echo '</div>';
					if($j<100) $display = " ";
					else $display = 'style="display:none;"';
					echo '<div id="space'.$j.'" '.$display.'">';
					echo '<br>';
					echo '</div>';
				}
			}
		?>
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
	<?php
		$yourempty = false;
		$yournum1 = 0;
		$youridToken1 = 0;
		$youridToken2 = 0;
		if($yourComments==null) $yourempty = true;
		for($i=0; $i<10; $i++){
			if(is_numeric($yourComments[$i]['Comment']['status'])) $yourComments[$i]['Comment']['textAnswer'] = 'false';
			else{
				$yourComments[$i]['Comment']['textAnswer'] = $yourComments[$i]['Comment']['status'];	
				$yourComments[$i]['Comment']['status'] = 100;
			}
		}
		if(count($yourComments)!=11){
			$yournum1 = $yourComments[0]['Comment']['counter']+9;
			for($j=0; $j<10; $j++){
				if($j==1) $youridToken2 = $yourComments[$j]['Comment']['id'];
			}
			if($youridToken2==0) $youridToken2 = $yourComments[0]['Comment']['id'] -1;
		}else{
			for($j=0; $j<10; $j++){
				if($j==1) $youridToken2 = $yourComments[$j]['Comment']['id'];
				$yournum1 = $yourComments[$j]['Comment']['counter'];
				$youridToken1 = $yourComments[$j]['Comment']['id'];
			}
		}
	?>
	<td width="50%">
		<p class="title4">Your Comments</p>
		<div class="new1" width="100%" style="margin-bottom:15px;">
			<div align="center">
				<br>
				<?php 
					if(!$yourempty){
						if($yourfirstPage && count($yourComments)<11) ;
						else{
							$moreparams = '';
							if($paramcommentid!=0) $moreparams = 'direction='.$paramdirection.'&index='.$paramindex.'&comment-id='.$paramcommentid.'&';
							if($yourindex!=0) echo '<a href="/comments?'.$moreparams.'your-direction=prev&your-index='.($yournum1-20).'&your-comment-id='.$youridToken2.'" class="new-button" >prev. page</a>';
							else echo '<a class="new-button-inactive" >prev. page</a>';
							if(count($yourComments)==11) echo '<a href="/comments?'.$moreparams.'your-direction=next&your-index='.$yournum1.'&your-comment-id='.$youridToken1.'" class="new-button" >next page</a>';
							else echo '<a class="new-button-inactive" >next page</a>';
						}
					}else{
						echo 'There are no comments.';
					}
				?>
				<br><br>
			</div>	
		</div>
		<?php 
			
			for($j=0; $j<10; $j++){
				if(isset($yourComments[$j]['Comment']['user_name'])){
					if($j<100) $yourdisplay = " ";
					else $yourdisplay = 'style="display:none;"';
					$commentColor = 'commentBox1';
					for($a=0; $a<count($admins); $a++){
						if($yourComments[$j]['Comment']['user_name']==$admins[$a]['User']['name']) $commentColor = 'commentBox2';
					}
					if($yourComments[$j]['Comment']['set_id']!=null){
						$sid = '?sid='.$yourComments[$j]['Comment']['set_id'];
						$QorA = '&';
					}else{
						$sid = '';
						$QorA = '?';
					}
					echo '<div class="sandboxComment" id="comment'.$j.'" '.$yourdisplay.'>
						<table class="sandboxTable2" width="100%" border="0">';
							echo '<tr>
								<td width="73%">		
								<div style="padding-bottom:7px;"><b>#'.$yourComments[$j]['Comment']['counter'].'</b> | 
								<a href="/tsumegos/play/'.$yourComments[$j]['Comment']['tsumego_id'].$sid.$QorA.'search=topics">
									'.$yourComments[$j]['Comment']['set'].' '.$yourComments[$j]['Comment']['set2'].' - '.$yourComments[$j]['Comment']['num'].'
								</a><br>
								
								</div>
								<div class="'.$commentColor.'"> 
								'.$yourComments[$j]['Comment']['user_name'].':<br>
								'.$yourComments[$j]['Comment']['message'].'</div>';
								
								if($yourComments[$j]['Comment']['status']!=0 && $yourComments[$j]['Comment']['status']!=97 && $yourComments[$j]['Comment']['status']!=98 && $comments[$j]['Comment']['status']!=96){
									echo '<div class="commentAnswer"><div style="padding-top:7px;"></div>'.$yourComments[$j]['Comment']['admin_name'].':<br>';
									if($yourComments[$j]['Comment']['status']==1) echo 'Your moves have been added.<br>';
									else if($yourComments[$j]['Comment']['status']==2) echo 'Your file has been added.<br>';
									else if($yourComments[$j]['Comment']['status']==3) echo 'Your solution has been added.<br>';
									else if($yourComments[$j]['Comment']['status']==4) echo 'I disagree with your comment.<br>';
									else if($yourComments[$j]['Comment']['status']==5) echo 'Provide sequence.<br>';
									else if($yourComments[$j]['Comment']['status']==6) echo 'Resolved.<br>';
									else if($yourComments[$j]['Comment']['status']==7) echo 'I couldn\'t follow your comment.<br>';
									else if($yourComments[$j]['Comment']['status']==8) echo 'You seem to try sending non-SGF-files.<br>';
									else if($yourComments[$j]['Comment']['status']==9) echo 'You answer is inferior to the correct solution.<br>';
									else if($yourComments[$j]['Comment']['status']==10) echo 'I disagree with your comment. I added sequences.<br>';
									else if($yourComments[$j]['Comment']['status']==11) echo 'I don\'t know.<br>';
									else if($yourComments[$j]['Comment']['status']==12) echo 'I added sequences.<br>';
									else if($yourComments[$j]['Comment']['status']==13) echo 'You are right, but the presented sequence is more interesting.<br>';
									else if($yourComments[$j]['Comment']['status']==14) echo 'I didn\'t add your file.<br>';
									else if($yourComments[$j]['Comment']['status']==99) echo 'Your comment has been removed.<br>';
									else if($yourComments[$j]['Comment']['status']==100) echo $yourComments[$j]['Comment']['textAnswer'];
									else echo $yourComments[$j]['Comment']['status'];
									
									echo '</div>';
								}
								echo '</td>
								<td class="sandboxTable2time" align="right">'.$yourComments[$j]['Comment']['created'].'</td>';
							echo '</tr>';
							echo '
								<tr>
									<td colspan="2">
										<div width="100%">
											<div align="center">
												<li id="naviElement0" class="set'.$yourComments[$j]['Comment']['user_tsumego'].'1" style="float:left;margin-top:14px;">
													<a id="tooltip-hover'.(99+$j).'" class="tooltip" href="/tsumegos/play/'.$yourComments[$j]['Comment']['tsumego_id'].$sid.$QorA.'search=topics">'.$yourComments[$j]['Comment']['num']
													.'<span><div id="tooltipSvg'.(99+$j).'"></div></span></a>
												</li>
											</div>
										</div>
									</td>
								</tr>
							';
						echo '</table>';
					echo '</div>';
					if($j<100) $yourdisplay = " ";
					else $yourdisplay = 'style="display:none;"';
					echo '<div id="space'.$j.'" '.$yourdisplay.'">';
					echo '<br>';
					echo '</div>';
				}
			}
		?>
	<div width="100%">
		<div align="center">
			<?php 
				if(!$yourempty){
					if($yourfirstPage && count($yourComments)<11) ;
					else{
						$moreparams = '';
						if($paramcommentid!=0) $moreparams = 'direction='.$paramdirection.'&index='.$paramindex.'&comment-id='.$paramcommentid.'&';
						if($yourindex!=0) echo '<a href="/comments?'.$moreparams.'your-direction=prev&your-index='.($yournum1-20).'&your-comment-id='.$youridToken2.'" class="new-button" >prev. page</a>';
						else echo '<a class="new-button-inactive" >prev. page</a>';
						if(count($yourComments)==11) echo '<a href="/comments?'.$moreparams.'your-direction=next&your-index='.$yournum1.'&your-comment-id='.$youridToken1.'" class="new-button" >next page</a>';
						else echo '<a class="new-button-inactive" >next page</a>';
					}
				}
				
			?>
			<br><br>
			
		</div>	
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
			let loadMore = 10;
			$('#more-button').click(function(e){
				e.preventDefault();
				let counter = 0;
				while(counter<10){
					$('#comment'+loadMore).fadeIn();
					$('#space'+loadMore).fadeIn();
					loadMore++;
					counter++;
				}
			});
			
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
			let tooltipSgfs = [];
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
			for($i=0; $i<10; $i++){
				if(!isset($tooltipInfo[$i][0]))
					$tooltipInfo[$i][0] = 0;
				if(!isset($tooltipInfo[$i][1]))
					$tooltipInfo[$i][1] = 0;	
				if(!isset($tooltipBoardSize[$i]))
					$tooltipBoardSize[$i] = 19;	
				echo 'createPreviewBoard('.$i.', tooltipSgfs['.$i.'], '.$tooltipInfo[$i][0].', '.$tooltipInfo[$i][1].', '.$tooltipBoardSize[$i].');';
			}
			?>
			let tooltipSgfs2 = [];
			<?php
			for($a=0; $a<count($tooltipSgfs2); $a++){
				echo 'tooltipSgfs2['.$a.'] = [];';
				for($y=0; $y<count($tooltipSgfs2[$a]); $y++){
					echo 'tooltipSgfs2['.$a.']['.$y.'] = [];';
					for($x=0; $x<count($tooltipSgfs[$a][$y]); $x++){
						echo 'tooltipSgfs2['.$a.']['.$y.'].push("'.$tooltipSgfs2[$a][$x][$y].'");';
					}
				}
			}
			for($i=0; $i<10; $i++){
				if(!isset($tooltipInfo2[$i][0]))
					$tooltipInfo2[$i][0] = 0;
				if(!isset($tooltipInfo2[$i][1]))
					$tooltipInfo2[$i][1] = 0;	
				if(!isset($tooltipBoardSize2[$i]))
					$tooltipBoardSize2[$i] = 19;		
				echo 'createPreviewBoard('.(99+$i).', tooltipSgfs2['.$i.'], '.$tooltipInfo2[$i][0].', '.$tooltipInfo2[$i][1].', '.$tooltipBoardSize2[$i].');';
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


