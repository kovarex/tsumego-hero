
function displayMultipleChoiceCustomResult(num){
	if(!hasChosen){
		$("#besogo-multipleChoice1").css("background-color", "#e0747f");
		$("#besogo-multipleChoice2").css("background-color", "#e0747f");
		$("#besogo-multipleChoice3").css("background-color", "#e0747f");
		$("#besogo-multipleChoice4").css("background-color", "#e0747f");
		
		if(num==customMultipleChoiceAnswer){
			displayResult("S");
			multipleChoiceEnabled = false;
			if(customMultipleChoiceAnswer==1)
				$("#besogo-multipleChoice1").css("background-color", "#3ecf78");
			else if(customMultipleChoiceAnswer==2)
				$("#besogo-multipleChoice2").css("background-color", "#3ecf78");
			else if(customMultipleChoiceAnswer==3)
				$("#besogo-multipleChoice3").css("background-color", "#3ecf78");
			else
				$("#besogo-multipleChoice4").css("background-color", "#3ecf78");
			if(mText!=""){
				$(".alertBanner").addClass("alertBannerCorrect");
				$(".alertBanner").html("Correct!<span class=\"alertClose\">x</span>");
				$('#multipleChoiceText').html(mText);
				$("#multipleChoiceAlerts").fadeIn(500);
			}
		}else{
			displayResult("F");
			multipleChoiceEnabled = false;
		}
		hasChosen = true;
	}
}
	