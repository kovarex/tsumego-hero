<?php
	if(!Auth::isLoggedIn() || !Auth::isAdmin())
		echo '<script type="text/javascript">window.location.href = "/";</script>';

	echo '<div class="homeRight" style="width:40%">';
	$adminActivityRenderer->render();
	echo '</div>';

	echo '<div class="homeLeft" style="text-align:left;border-right:1px solid #a0a0a0;width:60%">';
		$sgfProposalsRenderer->render();
		if($tagNames!=null){
			echo '<h3 style="margin:15px 0;">Tag Names (' . $tagNamesTotal . ')</h3>';
			echo $this->Pagination->render($tagNamesPage, $tagNamesPagesTotal, 'tagnames_page');
			echo '<table border="0" class="tagnames-adminpanel">';
			for($i=0; $i<count($tagNames); $i++){
				echo '<tr>';
					echo '<td>'.$tagNames[$i]['Tag']['user'].' created a new tag: <a href="/tag_names/view/'.$tagNames[$i]['Tag']['id'].'">'
					.$tagNames[$i]['Tag']['name'].'</a></td>';
					echo '<td>';
					if(Auth::getUserID() != $tags[$i]['TagConnection']['user_id']){
						echo '<a class="new-button-default2" id="tagname-accept'.$i.'">Accept</a>
						<a class="new-button-default2 tag-submit-button" id="tagname-submit'.$i.'" href="/users/adminstats?accept=true&tag_id='
						.$tagNames[$i]['Tag']['id'].'&hash='.md5(Auth::getUserID()).'">Submit (1)</a>
						<a class="new-button-default2" id="tagname-reject'.$i.'">Reject</a>';
					}
					echo '</td>';
				echo '</tr>';
			}
			echo '</table>';
			echo $this->Pagination->render($tagNamesPage, $tagNamesPagesTotal, 'tagnames_page');
			echo '<hr>';
		}
		if($requestDeletion!=null){
			echo '<table border="0">';
			for($i=0; $i<count($requestDeletion); $i++){
				echo '<tr>';
				echo '<td>'.$requestDeletion[$i]['User']['name'].' has requested account deletion.</td>';
				echo '<td><a class="new-button-default2" href="/users/adminstats?delete='.($requestDeletion[$i]['User']['id']*1111)
				.'&hash='.md5($requestDeletion[$i]['User']['name']).'">Delete Account</a></td>';
				echo '</tr>';
			}
			echo '</table><hr>';
		}
		$tagConnectionProposalsRenderer->render();

	echo '</div>';
	echo '<div style="clear:both;"></div>';
?>

<script>
	var tooltipSgfs = window.tooltipSgfs || [];
	let tagList = "null";
	let tagNameList = "null";
	let proposalList = "null";
	let submitCount = 0;

	<?php if($refreshView) echo 'window.location.href = "/sets/view/'.$set['Set']['id'].'";'; ?>

	<?php
		for($h=0; $h<count($tagNames); $h++){
			echo '$("#tagname-accept'.$h.'").click(function() {
				$("#tagname-submit'.$h.'").show();
				$("#tagname-accept'.$h.'").hide();
				$("#tagname-reject'.$h.'").hide();
				tagNameList = tagNameList + "-" + "a'.$tagNames[$h]['Tag']['id'].'";
				setCookie("tagNameList", tagNameList);
				submitCount++;
				$(".tag-submit-button").html("Submit ("+submitCount+")");
			});';
			echo '$("#tagname-reject'.$h.'").click(function() {
				$("#tagname-submit'.$h.'").show();
				$("#tagname-accept'.$h.'").hide();
				$("#tagname-reject'.$h.'").hide();
				tagNameList = tagNameList + "-" + "r'.$tagNames[$h]['Tag']['id'].'";
				setCookie("tagNameList", tagNameList);
				submitCount++;
				$(".tag-submit-button").html("Submit ("+submitCount+")");
			});';
		}
	?>
</script>
