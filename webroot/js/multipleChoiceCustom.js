
function displayMultipleChoiceCustomResult(num){
	if(!hasChosen){
		for (let answer = 1; answer <= 4; answer++)
			colorMultipleChoice(answer, "#e0747f");

		if(num==customMultipleChoiceAnswer){
			displayResult("S");
			colorMultipleChoice(customMultipleChoiceAnswer==1 ? 1 : customMultipleChoiceAnswer==2 ? 2 : customMultipleChoiceAnswer==3 ? 3 : 4, "#3ecf78");
			if(mText!=""){
				showMultipleChoiceBanner("toast__banner--success", 'Correct!<span class="toast__close">x</span>');
				showMultipleChoiceText(mText);
			}
		}else{
			displayResult("F");
		}
		hasChosen = true;
	}
}
