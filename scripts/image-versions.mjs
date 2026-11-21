import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join, relative, sep } from 'node:path';

// The version of every image of webroot: a short hash of its content, which the pages put after its
// address (/img/hp1.png?v=1a2b3c4d), so a browser keeps an image for a year (webroot/.htaccess) and
// asks for it again exactly when it changed. vite.config.js writes them into the Vite manifest, where
// src/Utility/Asset.php reads them, and into a script that gives imageUrl() to the scripts of the pages.

export const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico'];

// Not the site's own images: the forum serves its own, the bundles are versioned by their names,
// the covers of the collections are named by their content (src/Utility/SetImage.php), and the
// change stamps aren't images
const SKIPPED = ['forums', 'dist', 'dist-next', 'img/sets', 'live'];

const IMAGE = new RegExp(`\\.(${IMAGE_EXTENSIONS.join('|')})$`, 'i');

// The hash of an image. A png or jpg with a .webp beside it is served as that .webp to a browser
// that takes one (webroot/.htaccess), so its version covers both.
function versionOf(path)
{
	const hash = createHash('md5').update(readFileSync(path));
	const webp = path.replace(/\.(png|jpe?g)$/i, '.webp');
	if (webp !== path && existsSync(webp))
		hash.update(readFileSync(webp));
	return hash.digest('hex').slice(0, 8);
}

// {'img/hp1.png': '1a2b3c4d', ...}: the path of every image under webroot, relative to it, with its version
export function imageVersions(webroot)
{
	const versions = {};
	const walk = directory =>
	{
		for (const entry of readdirSync(directory, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name)))
		{
			const path = join(directory, entry.name);
			const name = relative(webroot, path).split(sep).join('/');
			if (SKIPPED.includes(name))
				continue;
			if (entry.isDirectory())
				walk(path);
			else if (IMAGE.test(entry.name))
				versions[name] = versionOf(path);
		}
	};
	walk(webroot);
	return versions;
}

// The script of the pages: the versions and imageUrl(), which the other scripts call for the address of an image.
// It holds only the images the scripts name, those right in img/ and besogo/img/ under their own names
// (no .webp, which only the server picks), as every page loads it.
export function imageVersionsScript(versions)
{
	const named = Object.fromEntries(Object.entries(versions).filter(([path]) => /^(img|besogo\/img)\/[^/]+$/.test(path) && !path.endsWith('.webp')));
	return `//The versions of the images (scripts/image-versions.mjs)
var imageVersions = ${JSON.stringify(named)};
//The address of an image of webroot with its version, so the browser keeps it for a year: imageUrl('/img/hp1.png')
function imageUrl(path)
{
	var version = imageVersions[path.replace(/^\\//, '')];
	return version ? path + '?v=' + version : path;
}
`;
}

// The CSS with the version after the address of every image of webroot it names (url(/img/x.png)),
// which happens before Vite hashes the bundle, so a changed image gives the bundle a new name too
export function versionCssImages(css, versions)
{
	return css.replace(/url\(\s*(["']?)(\/[^"')?#]+)\1\s*\)/g, (match, quote, path) =>
	{
		const version = versions[path.slice(1)];
		return version ? `url(${quote}${path}?v=${version}${quote})` : match;
	});
}
