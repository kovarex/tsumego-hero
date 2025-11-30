class TagConnectionsEdit
{
	tags = [];
	allTags = [];
	tagsGivesHint = [];
	idTags = [];
	popularTags = [];
	tsumegoID;
	isAdmin;

	constructor(tsumegoID, isAdmin)
	{
		this.tsumegoID = tsumegoID;
		this.isAdmin = isAdmin;
	}

	add(tagName)
	{
		$.ajax(
			{
				url: '/tagConnection/add/' + this.tsumegoID + '/' + tagName,
				type: 'POST',
				success: (response) =>
				{
					this.allTags = this.allTags.filter(tag => tag.name !== tagName);
					this.popularTags = this.popularTags.filter(tag => tag.name !== tagName);
					this.tags.push({name: tagName, isMyUnapproved: !this.isAdmin});
					this.draw();
				}
			});
	}

	remove(tagName)
	{
		$.ajax(
			{
				url: '/tagConnection/remove/' + this.tsumegoID + '/' + tagName,
				type: 'POST',
				success: (response) =>
				{
					this.allTags.push({name: tagName, isAdded: false});
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
		this.tags.forEach((tag, i) =>
		{
			let tagLink = 'href="/tag_names/view/' + this.idTags[i]+'"';
			let tagLinkId = 'id="'+makeIdValidName(tag.name)+'"';
            let tagLinkClass = this.tagsGivesHint[i] == 1 ? 'tag-gives-hint ' : '';
			$(".tag-list").append('<a '+tagLink+' class="' + tagLinkClass + '" '+tagLinkId+'>' + tag.name + '</a>');
			if (tag.isMyUnapproved)
				$(".tag-list").append(` <button onclick="tagConnectionsEdit.remove('${tag.name}');">x</button>`);
			if (i < this.tags.length - 1)
				if(this.tagsGivesHint[i] == 1)
					$(".tag-list").append('<p class="tag-gives-hint">, </p>');
				else
					$(".tag-list").append('<p class="tag-comma">, </p>');
		});

		this.updateTagToAddList('add-tag-list-popular', this.popularTags);
		$(".add-tag-list-popular").append(' <a class="add-tag-list-anchor" id="open-more-tags">[more]</a>');

		this.updateTagToAddList('add-tag-list', this.allTags);
		$(".add-tag-list").append(' <a class="add-tag-list-anchor" href="/tag_names/add">[Create new tag]</a>');

		if (problemSolved)
			$(".tag-gives-hint").css("display", "inline");
	}
}
