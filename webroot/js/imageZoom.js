//Hovering an .image-zoom image (the About page timeline) with a mouse shows it at its natural size, capped to the viewport
function initImageZoom()
{
	document.querySelectorAll('.image-zoom img').forEach(img =>
	{
		let preview = null;

		img.addEventListener('pointerenter', e =>
		{
			//On touch a tap would only flash the preview; a preview no bigger than the image would show nothing new
			if (preview || e.pointerType !== 'mouse' || img.naturalWidth <= img.clientWidth)
				return;
			preview = img.cloneNode();
			preview.removeAttribute('width');
			preview.removeAttribute('height');
			preview.removeAttribute('style');
			preview.className = 'image-zoom__preview';
			document.body.appendChild(preview);
		});

		img.addEventListener('pointerleave', () =>
		{
			preview?.remove();
			preview = null;
		});
	});
}

if (document.readyState === 'loading')
	document.addEventListener('DOMContentLoaded', initImageZoom);
else
	initImageZoom();
