class RatingModeDifficultySelector
{

	 static levels =
	 [
		 { color: 'hsl(138, 47%, 50%)', text: 'very easy' },
		 { color: 'hsl(138, 31%, 50%)', text: 'easy' },
		 { color: 'hsl(138, 15%, 50%)', text: 'casual' },
		 { color: 'hsl(138, 0%, 47%)', text: 'regular' },
 		 { color: 'hsl(0, 31%, 50%)', text: 'challenging' },
 		 { color: 'hsl(0, 52%, 50%)', text: 'difficult' },
 		 { color: 'hsl(0, 66%, 50%)', text: 'very difficult' }
	];

	update()
	{
		let difficulty = this.slider.value;
		let levelInfo = RatingModeDifficultySelector.levels[difficulty - 1];
		this.sliderText.style.color = levelInfo.color;
		this.sliderText.textContent = levelInfo.text;
		this.slider.style.setProperty('--SliderColor', levelInfo.color);
	}

	constructor()
	{
		this.slider = document.querySelector('input[name=rangeInput]');
		this.sliderText = document.getElementById('sliderText')
		this.update();
		document.getElementById("rangeInput").addEventListener('change', (event) =>
		{
			setCookie('difficulty', event.target.value);
			this.update();
		});
	}

	sliderText;
	slider;
}
