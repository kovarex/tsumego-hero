<?php if(isset($refresh)) echo '<script>window.location.href = "/users/rewards";</script>'; ?>
<script>window.location.href = "/";</script>
<?php
  $color1 = 'black';
  $color2 = '#e5fc66';
?>
<div align="center">
<p class="title6">Rewards</p>
<br>
  Points: 
  <div class="number" id="number">0</div>
</div>
<br>
<div class="reward-table">
    <div class="reward-element2"></div>
    <div class="reward-element2">
      <div align="center">
        <div class="vl" style="border-left: 1px solid <?php echo $goalsColor[0] ?>;"></div>
      </div>
    </div>
    <div class="reward-element2">
      <div align="center">
        <div class="vl" style="border-left: 1px solid <?php echo $goalsColor[1] ?>;"></div>
      </div>
    </div>
    <div class="reward-element2">
      <div align="center">
        <div class="vl" style="border-left: 1px solid <?php echo $goalsColor[2] ?>;"></div>
      </div>
    </div>
    <div class="reward-element2">
      <div align="center">
        <div class="vl" style="border-left: 1px solid <?php echo $goalsColor[3] ?>;"></div>
      </div>
    </div>
  </div>
</div>
<div class="reward-bar-container">
  <div id="account-bar-wrapper2">
    <div id="account-bar2">
      <div id="xp-bar2">
          <div id="xp-bar-fill2" class="xp-bar-fill-c4" style="width: 5%; transition: 0.5s;">
            <div id="xp-increase-fx2">
              <div id="xp-increase-fx-flicker2">
              </div>
            </div>
          </div>
      </div>
    </div>
  </div>
</div>
<div class="reward-table">
    <div class="reward-element"></div>
    <div class="reward-element" style="border: 1px solid <?php echo $goalsColor[0] ?>;">
      <div align="center">
        <div class="reward-element-points">30 points</div>
        <div class="reward-element-description">Level up</div>
        <?php if($goals[0]){ ?>
          <?php if($uc['UserContribution']['reward1']==0){ ?>
            <a href="#">
              <img id="nr1" src="/img/hpL.png" title="Level up"
              onmouseover="hover1()" onmouseout="noHover1()" onclick="use1(); return false;">
            </a><br>
          <?php }else{ ?>
            <img src="/img/hpLused.png"><br>
          <?php } ?>
        <?php }else{ ?>
          <img src="/img/hpLx.png"><br>
        <?php } ?>
      </div>
    </div>
    <div class="reward-element" style="border: 1px solid <?php echo $goalsColor[1] ?>;">
      <div align="center">
        <div class="reward-element-points">60 points</div>
        <div class="reward-element-description">Rank up</div>
        <?php if($goals[1]){ ?>
          <?php if($uc['UserContribution']['reward2']==0){ ?>
            <a href="#">
              <img id="nr2" src="/img/hpR.png" title="Rank up"
              onmouseover="hover2()" onmouseout="noHover2()" onclick="use2(); return false;">
            </a><br>
          <?php }else{ ?>
            <img src="/img/hpRused.png"><br>
          <?php } ?> 
        <?php }else{ ?>
          <img src="/img/hpRx.png"><br>
        <?php } ?>
      </div>
    </div>
    <div class="reward-element" style="border: 1px solid <?php echo $goalsColor[2] ?>;">
      <div align="center">
        <div class="reward-element-points">90 points</div>
        <div class="reward-element-description">
          <?php 
            if($uc['UserContribution']['reward3']==0)
              echo 'The strongest Hero Power';
            else
              echo '<p style="font-size:14px;margin:0"><b>Revelation:</b> Solves a problem, but you don\'t get any reward.</p>';
          ?>
        </div>
        <?php if($goals[2]){ ?>
          <?php if($uc['UserContribution']['reward3']==0){ ?>
            <a href="#">
              <img id="nr3" src="/img/hpMystery.png" title="Revelation"
              onmouseover="hover3()" onmouseout="noHover3()" onclick="use3(); return false;">
            </a><br>
          <?php }else{ ?>
            <img src="/img/hp6used.png"><br>
          <?php } ?>
        <?php }else{ ?>
          <img src="/img/hpxxx.png"><br>
        <?php } ?>
      </div>
    </div>
    <div class="reward-element" style="border: 1px solid <?php echo $goalsColor[3] ?>;">
      <div align="center">
        <div class="reward-element-points">120 points</div>
        <div class="reward-element-description">Premium account</div>
          <?php if($_SESSION['loggedInUser']['User']['premium']==0){ ?>
            <?php if($goals[3]){ ?>
              <a href="#">
                <img id="nr4" src="/img/hpP.png" title="Premium account"
                onmouseover="hover4()" onmouseout="noHover4()" onclick="use4(); return false;">
              </a><br>
            <?php }else{ ?>
              <img src="/img/hpPx.png"><br>
            <?php } ?>
          <?php }else{ ?>
            <img src="/img/hpPused.png"><br>
          <?php } ?>  
      </div>
    </div>
  </div>
</div>

<script>
  let nr1Enabled = true;
  let nr2Enabled = true;
  let nr3Enabled = true;
  let nr4Enabled = true;
  <?php
    $max = 120;
    $percent = 0;
    if($uc['UserContribution']['score'] >= $max){
      $percent = 100;
    }else{
      $percent = $uc['UserContribution']['score']/$max*100;
    }
  ?>
  let percent = <?php echo $percent ?>;
  animateBar(percent);
  animateNumber(0, <?php echo $uc['UserContribution']['score']; ?>, 1);

  function animateBar(percent){
    $("#xp-increase-fx2").css("display","inline-block");
    $("#xp-bar-fill2").css("box-shadow", "-5px 0px 10px #fff inset");
    $("#xp-bar-fill2").css("width", 0+"%");
    $("#xp-increase-fx2").hide();
    $("#xp-bar-fill2").css({"-webkit-transition":"all 0.5s ease","box-shadow":""});
    
    $("#xp-bar-fill2").css({"width":percent+"%"});
    $("#xp-bar-fill2").css("-webkit-transition","all 1s ease");
    $("#xp-increase-fx2").fadeIn(0);
    $("#xp-bar-fill2").css({"-webkit-transition":"all 1s ease","box-shadow":""});
    setTimeout(function(){
      $("#xp-increase-fx-flicker2").fadeOut(500);
      $("#xp-bar-fill2").css({"-webkit-transition":"all 1s ease","box-shadow":""});
    },1000);
  }
  function animateNumber(start, end, duration) {
      const element = document.getElementById('number');
      const range = end - start;
      const increment = range / (duration * 60); 
      let currentNumber = start;
      const step = () => {
          currentNumber += increment;
          if (currentNumber < end) {
              element.textContent = Math.floor(currentNumber);
              requestAnimationFrame(step);
          } else {
              element.textContent = end; 
          }
      };
      requestAnimationFrame(step);
  }
  function use1(){
    if(nr1Enabled){
      nr1Enabled = false;
      $('#nr1').attr("src", "/img/hpLused.png");
      $("#nr1").css("cursor", "context-menu");
      window.location.href = "/users/rewards?action=<?php echo md5('level').'&token='.md5($uc['UserContribution']['score']); ?>";
    }
  }
  function use2(){
    if(nr2Enabled){
      nr2Enabled = false;
      $('#nr2').attr("src", "/img/hpRused.png");
      $("#nr2").css("cursor", "context-menu");
      window.location.href = "/users/rewards?action=<?php echo md5('rank').'&token='.md5($uc['UserContribution']['score']); ?>";
    }
  }
  function use3(){
    if(nr3Enabled){
      nr3Enabled = false;
      $('#nr3').attr("src", "/img/hp6used.png");
      $("#nr3").css("cursor", "context-menu");
      window.location.href = "/users/rewards?action=<?php echo md5('heropower').'&token='.md5($uc['UserContribution']['score']); ?>";
    }
  }
  function use4(){
    if(nr4Enabled){
      nr4Enabled = false;
      $('#nr4').attr("src", "/img/hpPused.png");
      $("#nr4").css("cursor", "context-menu");
      window.location.href = "/users/rewards?action=<?php echo md5('premium').'&token='.md5($uc['UserContribution']['score']); ?>";
    }
  }
  function hover1(){
		if(nr1Enabled) $('#nr1').attr("src", "/img/hpLh.png");
	}
	function noHover1(){
		if(nr1Enabled) $('#nr1').attr("src", "/img/hpL.png");
	}
  function hover2(){
		if(nr2Enabled) $('#nr2').attr("src", "/img/hpRh.png");
	}
	function noHover2(){
		if(nr2Enabled) $('#nr2').attr("src", "/img/hpR.png");
	}
  function hover3(){
		if(nr3Enabled) $('#nr3').attr("src", "/img/hpMysteryh.png");
	}
	function noHover3(){
		if(nr3Enabled) $('#nr3').attr("src", "/img/hpMystery.png");
	}
  function hover4(){
		if(nr4Enabled) $('#nr4').attr("src", "/img/hpPh.png");
	}
	function noHover4(){
		if(nr4Enabled) $('#nr4').attr("src", "/img/hpP.png");
	}
</script>
<style>
.vl {
  height: 37px;
  position:relative;
  left:0px;
  top:23px;
}
.reward-table{
  width:90%;
  margin:0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px; 
}
.reward-element{
  width: 155px;     
  height: 150px;    
  display: flex;
  justify-content: center; 
  align-items: center;     
  font-size: 20px;
  font-weight: bold;
  margin-top:36px;
  border-radius:5%;
}
.reward-element2{
  width: 155px;     
  height: 1px;    
  display: flex;
  justify-content: center; 
  align-items: center;
}
.reward-element-points{
  font-weight:800;
}
.reward-element-description{
  font-weight:400;
  height: 55px; 
}
.reward-bar-container{
  width: 78%;
  margin: 0 0 0 139px;
  height:5px;
}
#account-bar2 {
  margin: 0px auto;
}
#xp-increase-fx2 {
  display:none;
}
.xp-bar-fill-c4 {
  background-image: linear-gradient(to right, #e9cc2c, #e5fc66);
  height:5px;
}
#account-bar-xp-wrapper2 {
  width: 80%;
  margin: 0px 0px 0px 15px;
  color: white;
  font-family: "Arial Black", verdana, helvetica, arial, sans-serif;
  font-size: 14px;
  font-style: normal;
  font-variant: normal;
  font-weight: 600;
  letter-spacing: 1px;
  white-space: nowrap;
  border: 1px solid #ae922d;
  -moz-border-radius-bottomleft: 5px;
  -moz-border-radius-bottomright: 5px;
  border-radius-bottomleft: 5px;
  border-radius-bottomright: 5px;
  -moz-border-radius-bottomright: 5px;
  -moz-border-radius-bottomleft: 5px;
  border-bottom-right-radius: 5px;
  border-bottom-left-radius: 5px;
  -moz-border-radius-topright: 5px;
  -moz-border-radius-topleft: 5px;
  border-top-right-radius: 5px;
  border-top-left-radius: 5px;
}
#account-bar-xp2 {
  position: relative;
  margin: 3px 0px 0px 0px;
  color: white;
  font-family: "Arial Black", verdana, helvetica, arial, sans-serif;
  font-size: 14px;
  font-style: normal;
  font-variant: normal;
  font-weight: 600;
  letter-spacing: 1px;
  white-space: nowrap;
}
#account-bar-xp2 p {
  position: relative;
}
.number {
  font-size: 28px;
  font-weight: bold;
  animation: fadeIn 1s ease-in-out;
}
@keyframes fadeIn {
  0% {
      opacity: .1;
  }
  100% {
      opacity: 1;
  }
}
</style>