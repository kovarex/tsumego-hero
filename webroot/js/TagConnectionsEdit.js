class TagConnection
{
	name;
	isPopular;
	id;
	isAdded;
	isApproved;
	isMine;
	isHint;

	constructor(props)
	{
		Object.assign(this, props);
	}
}

class TagConnectionsEdit
{
	tags = [];
	tsumegoID;
	isAdmin;
	editActivated = false;
	problemSolved;

	constructor({tsumegoID, isAdmin, problemSolved, tags})
	{
		this.tsumegoID = tsumegoID;
		this.isAdmin = isAdmin;
		this.problemSolved = problemSolved;
		this.tags = tags;
	}

	add(tagName)
	{
		$.ajax(
			{
				url: '/tagConnection/add/' + this.tsumegoID + '/' + tagName,
				type: 'POST',
				success: (response) =>
				{
					const tag = this.tags.find(tag => tag.name === tagName);
					tag.isAdded = true;
					tag.isApproved = this.isAdmin;
					this.draw();
				}
			});
	}

	remove(tagToRemove)
	{
		$.ajax(
			{
				url: '/tagConnection/remove/' + this.tsumegoID + '/' + tagToRemove.name,
				type: 'POST',
				success: (response) =>
				{
					this.allTags.push({name: tag.name, isAdded: false});
					if (tagToRemove.isPopular)
						this.popularTags.push();
					this.tags = this.tagsfilter(tag => tag.name != tagToRemove.name);
					this.draw();
				}
			});
	}

	updateTagList()
	{
		const html = this.tags
			.filter(tag =>
				tag.isAdded &&
				(tag.isApproved || tag.isMine) &&
				(this.editActivated || !tag.isHint))
			.map((tag, i) => {
				const tagLink = `href="/tag_names/view/${tag.id}"`;
				const tagLinkId = `id="${makeIdValidName(tag.name)}"`;
				let part = `<a ${tagLink} ${tagLinkId}>${tag.name}</a>`;
				if ((tag.isMine && !tag.isApproved) || this.isAdmin)
					part += ` <button onclick="tagConnectionsEdit.remove('${tag.name}');">x</button>`;
				return part;
			})
			.join(", ");
		$(".tag-list").html(html.length > 0 ? ("Tags: " + html) : "");
	}

	updateTagToAddList(id, popular)
	{
		$("." + id).html("");
		$("." + id).append("Add tag: ");
		const html = this.tags
			.filter(tag => !popular || tag.isPopular)
			.map(tag =>
				tag.isAdded ?
					`<span class="add-tag-list-anchor">${tag.name}</span>` :
					`<a class="add-tag-list-anchor" id="${makeIdValidName(tag.name)}" onclick="tagConnectionsEdit.add('${tag.name}');">${tag.name}</a>`
			).join(', ');
		$("." + id).append(html);
		if (popular)
			$("." + id).append(' <a class="add-tag-list-anchor" id="open-more-tags">[more]</a>');
		else
			$("." + id).append(' <a class="add-tag-list-anchor" href="/tag_names/add">[Create new tag]</a>');
	}

	draw()
	{
		this.updateTagList();
		this.updateTagToAddList('add-tag-list-popular', true);
		this.updateTagToAddList('add-tag-list', false);
	}
}
