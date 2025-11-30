class TagConnectionsEdit
{
	tags = [];
	allTags = [];
	approvedInfo = {};
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
					this.allTags = this.allTags.filter(tag => tag.name !== tagName);
					this.popularTags = this.popularTags.filter(tag => tag.name !== tagName);
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

	updateTagToAddList(id, source)
	{
		$("." + id).html("");
		$("." + id).append("Add tag: ");
		const html = source
		.map(tag =>
			tag.isAdded ?
				`<span class="add-tag-list-anchor">${tag.name}</span>` :
				`<a class="add-tag-list-anchor" id="${makeIdValidName(tag.name)}">${tag.name}</a>`
		).join(', ');
		$("." + id).append(html);
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

		this.updateTagToAddList('add-tag-list-popular', this.popularTags);
		$(".add-tag-list-popular").append(' <a class="add-tag-list-anchor" id="open-more-tags">[more]</a>');

		this.updateTagToAddList('add-tag-list', this.allTags);
		$(".add-tag-list").append(' <a class="add-tag-list-anchor" href="/tag_names/add">[Create new tag]</a>');

		if (problemSolved)
			$(".tag-gives-hint").css("display", "inline");
	}
}
