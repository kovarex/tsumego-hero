<?php
$besogoJs = [
	'besogo', 'transformation', 'treeProblemUpdater', 'nodeHashTable',
	'editor', 'gameRoot', 'status', 'svgUtil', 'cookieUtil',
	'parseSgf', 'loadSgf', 'saveSgf', 'boardDisplay', 'coord',
	'toolPanel', 'filePanel', 'controlPanel', 'commentPanel',
	'treePanel', 'diffInfo', 'scaleParameters',
];
foreach ($besogoJs as $file)
{
	$path = WWW_ROOT . 'besogo/js/' . $file . '.js';
	$v = file_exists($path) ? filemtime($path) : '0';
	echo '<script src="/besogo/js/' . $file . '.js?v=' . $v . '"></script>' . "\n";
}
