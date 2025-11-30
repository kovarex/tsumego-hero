class TagConnectionsEdit
{
	tags = [];
	allTags = [];
	unapprovedTags = [];
	tagsGivesHint = [];
	idTags = [];
	popularTags = [];

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
					this.draw();
				}
			});
	}

	remove(tsumegoID, tagName)
	{
		$.ajax(
			{
				url: '/tagConnection/remove/' + tsumegoID + '/' + tagName,
				type: 'POST',
				success: (response) =>
				{
					let newTags = [];
					for(let i=0; i < this.tags.length; i++)
						if (this.tags[i] !== tagName)
							newAllTags.push(this.tags[i]);
					this.tags = newTags;
					this.allTags.push(tagName);
					this.draw();
				}
			});
	}

	draw()
	{
		$(".tag-list").html("");
		if(this.tags.length > 0)
			$(".tag-list").append("Tags: ");
		for(let i = 0;i < this.tags.length; i++)
		{
			let tagLink = 'href="/tag_names/view/' + this.idTags[i]+'"';
			let tagLinkId = 'id="'+makeIdValidName(this.tags[i])+'"';
            let tagLinkClass = this.tagsGivesHint[i] == 1 ? 'tag-gives-hint ' : '';
			$(".tag-list").append('<a '+tagLink+' class="' + tagLinkClass + '" '+tagLinkId+'>' + this.tags[i] + '</a>');
			if (i < this.tags.length - 1)
				if(this.tagsGivesHint[i] == 1)
					$(".tag-list").append('<p class="tag-gives-hint">, </p>');
				else
					$(".tag-list").append('<p class="tag-comma">, </p>');
		}

		$(".add-tag-list-popular").html("");
		$(".add-tag-list-popular").append("Add tag: ");
		for (let i=0; i < this.popularTags.length; i++)
		{
			$(".add-tag-list-popular").append('<a class="add-tag-list-anchor" id="' + makeIdValidName(this.popularTags[i])+'">'
				+ this.popularTags[i]+'</a>');
			if (i < this.popularTags.length-1)
				$(".add-tag-list-popular").append(', ');
		}
		$(".add-tag-list-popular").append(' <a class="add-tag-list-anchor" id="open-more-tags">[more]</a>');

		$(".add-tag-list").html("");
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
}
