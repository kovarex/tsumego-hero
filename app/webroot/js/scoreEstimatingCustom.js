
function displayScoreEstimatingResult(c){
	let chars = "";
	let num = 0;
	let v = "";
	if(!hasChosen){
		if(c=="b"){
			v = $("#ScoreEstimatingSE").val();
			if(v.slice(0,2)=="B+" || v.slice(0,2)=="W+"){
				chars = "B+";
				num = v.slice(2);
			}else{
				chars = "B+";
				num = v;
			}
			$("#ScoreEstimatingSE").val(chars+num);
		}else if(c=="w"){
			v = $("#ScoreEstimatingSE").val();
			if(v.slice(0,2)=="B+" || v.slice(0,2)=="W+"){
				chars = "W+";
				num = v.slice(2);
			}else{
				chars = "W+";
				num = v;
			}
			$("#ScoreEstimatingSE").val(chars+num);
		}else if(c=="+"){
			
			v = $("#ScoreEstimatingSE").val();
			if(v.slice(0,2)=="B+" || v.slice(0,2)=="W+"){
				chars = v.slice(0,2);
				num = v.slice(2);
			}else{
				chars = "";
				num = v;
			}
			if(num=="")
				num=0;
			if(is_numeric(num)){
				num = parseFloat(num);
				num += .5;
			}
			$("#ScoreEstimatingSE").val(chars+num);
		}else if(c=="-"){
			v = $("#ScoreEstimatingSE").val();
			if(v.slice(0,2)=="B+" || v.slice(0,2)=="W+"){
				chars = v.slice(0,2);
				num = v.slice(2);
			}else{
				chars = "";
				num = v;
			}
			if(num=="")
				num=0;
			if(is_numeric(num)){
				num = parseFloat(num);
				if(num>0)
					num -= .5;
			}
			$("#ScoreEstimatingSE").val(chars+num);
		}
	}
}

function is_numeric(str){
  if(typeof str != "string") return false // we only process strings!  
  return !isNaN(str) && // use type coercion to parse the _entirety_ of the string (`parseFloat` alone does not do this)...
    !isNaN(parseFloat(str)) // ...and ensure strings of whitespace fail
}
	