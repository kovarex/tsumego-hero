/**
 * Set view page functionality
 */

/**
 * Initialize set cover image zoom on hover
 */
function initSetImageZoom()
{
	document.querySelectorAll('.set-image-zoom img').forEach(img =>
	{
		const link = img.closest('a');
		const setUrl = link ? link.href : null;
		let activeClone = null;

		img.addEventListener('mouseenter', e =>
		{
			if (activeClone)
				return; // Don't create duplicate if one exists

			const clone = e.target.cloneNode();

			// Get the position of the set description table/parent area
			const table = img.closest('table');
			const tableRect = table ? table.getBoundingClientRect() : {left: 0, width: 600};
			const centerX = tableRect.left + (tableRect.width / 2);

			clone.style.cssText = `position:fixed;top:50%;left:${centerX}px;transform:translate(-50%,-50%) scale(3);z-index:9999;cursor:pointer;transition:transform 0.3s;max-width:${tableRect.width * 0.8}px;max-height:90vh;border:3px solid #333;box-shadow:0 0 20px rgba(0,0,0,0.5)`;
			clone.className = 'zoom-preview';
			document.body.appendChild(clone);
			activeClone = clone;

			const removeClone = () =>
			{
				if (clone.parentNode)
					clone.remove();
				activeClone = null;
			};

			clone.addEventListener('mouseleave', removeClone);

			clone.addEventListener('click', () =>
			{
				if (setUrl)
					window.location.href = setUrl;
			});
		});

		img.addEventListener('mouseleave', () =>
		{
			setTimeout(() =>
			{
				if (activeClone && !activeClone.matches(':hover'))
				{
					activeClone.remove();
					activeClone = null;
				}
			}, 50);
		});
	});
}

// Initialize when DOM is ready
if (document.readyState === 'loading')
	document.addEventListener('DOMContentLoaded', initSetImageZoom);
else
	initSetImageZoom();
