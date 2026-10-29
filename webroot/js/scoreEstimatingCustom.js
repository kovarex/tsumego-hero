//The player's answer to a score estimating problem, changed by its buttons until it is submitted
function displayScoreEstimatingResult(change)
{
	if (!hasChosen)
		adjustScore($("#ScoreEstimatingSE"), change);
}

//A score like "B+6.5" in a text input: 'b' and 'w' set who wins, '+' and '-' move it by half a point
function adjustScore(input, change)
{
	let value = input.val();
	let winner = value.slice(0, 2) == "B+" || value.slice(0, 2) == "W+" ? value.slice(0, 2) : "";
	let points = winner ? value.slice(2) : value;
	if (change == "b" || change == "w")
	{
		input.val((change == "b" ? "B+" : "W+") + points);
		return;
	}
	if (points == "")
		points = 0;
	if (is_numeric(points))
	{
		points = parseFloat(points);
		if (change == "+")
			points += .5;
		else if (points > 0)
			points -= .5;
	}
	input.val(winner + points);
}

function is_numeric(str){
  if(typeof str != "string") return false // we only process strings!  
  return !isNaN(str) && // use type coercion to parse the _entirety_ of the string (`parseFloat` alone does not do this)...
    !isNaN(parseFloat(str)) // ...and ensure strings of whitespace fail
}
