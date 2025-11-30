class TagController
{
	tags = [];
	allTags = [];
	unapprovedTags = [];
	tagsGivesHint = [];
	idTags = [];
	popularTags = [];
	newTag = null;

	add(tsumegoID, tagName)
	{
		$.ajax(
			{
				url: '/tagConnection/add/' + tsumegoID + '/' + tagName,
				type: 'POST',
				success: (response) =>
				{
					let newAllTags = [];
					for(let i=0; i < this.allTags.length; i++)
						if (this.allTags[i] !== tagName)
							newAllTags.push(this.allTags[i]);
					this.allTags = newAllTags;
					this.tags.push(tagName);
					$(".tag-list").html("");
					$(".add-tag-list").html("");
					$(".add-tag-list-popular").html("");
					this.draw();
					$(".add-tag-list").hide();
					$(".add-tag-list-popular").hide();
				}
			});
	}

	draw()
	{
		if(this.tags.length>0)
			$(".tag-list").append("Tags: ");
		let foundNewTag = false;
		for(let i=0;i<this.tags.length;i++)
		{
			let isNewTag = '';
			if(this.tags[i] === this.newTag)
			{
				isNewTag = 'is-new-tag';
				foundNewTag = true;
			}
			else if(this.unapprovedTags[i]==0)
				isNewTag = 'is-new-tag';
			if (this.tagsGivesHint[i] == 1)
				isNewTag = 'tag-gives-hint '+isNewTag;
			let tagLink = 'href="/tag_names/view/' + this.idTags[i]+'"';
			let tagLinkId = 'id="'+makeIdValidName(this.tags[i])+'"';
			if(typeof this.idTags[i] === "undefined")
			{
				tagLink = '';
				tagLinkId = '';
			}
			$(".tag-list").append('<a '+tagLink+' class="'+isNewTag+'" '+tagLinkId+'>' + this.tags[i] + '</a>');
			if (i < this.tags.length-1)
			{
				if(this.tagsGivesHint[i] == 1)
					$(".tag-list").append('<p class="tag-gives-hint">, </p>');
				else if (!this.isLastComma(i, this.tagsGivesHint, this.tags))
					$(".tag-list").append('<p class="tag-comma">, </p>');
				else
					$(".tag-list").append('<p class="tag-gives-hint">, </p>');
			}
		}
		if(foundNewTag)
		{
			$(".tag-list").append(" ");
			$(".tag-list").append('<button id="undo-tags-button">x</button>');
			$("#undo-tags-button").show();
		}

		$(".add-tag-list-popular").append("Add tag: ");
		for (let i=0; i < this.popularTags.length; i++)
		{
			$(".add-tag-list-popular").append('<a class="add-tag-list-anchor" id="' + makeIdValidName(this.popularTags[i])+'">'
				+ this.popularTags[i]+'</a>');
			if (i < this.popularTags.length-1)
				$(".add-tag-list-popular").append(', ');
		}
		$(".add-tag-list-popular").append(' <a class="add-tag-list-anchor" id="open-more-tags">[more]</a>');

		$(".add-tag-list").append("Add tag: ");
		for (let i = 0; i < this.allTags.length; i++)
		{
			$(".add-tag-list").append('<a class="add-tag-list-anchor" id="' + makeIdValidName(this.allTags[i])+'">'
				+ this.allTags[i]+'</a>');
			if (i < this.allTags.length - 1)
				$(".add-tag-list").append(', ');
		}
		$(".add-tag-list").append(' <a class="add-tag-list-anchor" href="/tag_names/add">[Create new tag]</a>');
		if (problemSolved)
			$(".tag-gives-hint").css("display", "inline");
	}

	isLastComma(index, hints, tags)
	{
		if(index >= hints.length - 1)
			return this.newTag == null;
		for(let i = index + 1; i < hints.length; i++)
			if(hints[i] == 0)
				return false;
		return true;
	}
}
