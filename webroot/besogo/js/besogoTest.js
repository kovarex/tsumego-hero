// Tests for besogo.js utility functions

besogo.addTest('Besogo', 'ParseSizeSquare', function() {
	let result = besogo.parseSize('19');
	CHECK_EQUALS(19, result.x, 'x should be 19');
	CHECK_EQUALS(19, result.y, 'y should be 19');
});

besogo.addTest('Besogo', 'ParseSizeSmallSquare', function() {
	let result = besogo.parseSize('9');
	CHECK_EQUALS(9, result.x, 'x should be 9');
	CHECK_EQUALS(9, result.y, 'y should be 9');
});

besogo.addTest('Besogo', 'ParseSizeNonSquare', function() {
	let result = besogo.parseSize('9:13');
	CHECK_EQUALS(9, result.x, 'x should be 9');
	CHECK_EQUALS(13, result.y, 'y should be 13');
});

besogo.addTest('Besogo', 'ParseSizeSquareColonFormat', function() {
	let result = besogo.parseSize('19:19');
	CHECK_EQUALS(19, result.x, 'x should be 19');
	CHECK_EQUALS(19, result.y, 'y should be 19');
});

besogo.addTest('Besogo', 'ParseSizeMinimum', function() {
	let result = besogo.parseSize('1');
	CHECK_EQUALS(1, result.x, 'x should be 1');
	CHECK_EQUALS(1, result.y, 'y should be 1');
});

besogo.addTest('Besogo', 'ParseSizeMaximum', function() {
	let result = besogo.parseSize('52');
	CHECK_EQUALS(52, result.x, 'x should be 52');
	CHECK_EQUALS(52, result.y, 'y should be 52');
});

besogo.addTest('Besogo', 'ParseSizeZeroDefaultsTo19', function() {
	let result = besogo.parseSize('0');
	CHECK_EQUALS(19, result.x, 'Invalid 0 should default x to 19');
	CHECK_EQUALS(19, result.y, 'Invalid 0 should default y to 19');
});

besogo.addTest('Besogo', 'ParseSizeOverMaxDefaultsTo19', function() {
	let result = besogo.parseSize('53');
	CHECK_EQUALS(19, result.x, 'Over-max should default x to 19');
	CHECK_EQUALS(19, result.y, 'Over-max should default y to 19');
});

besogo.addTest('Besogo', 'ParseSizeNonNumericDefaultsTo19', function() {
	let result = besogo.parseSize('abc');
	CHECK_EQUALS(19, result.x, 'Non-numeric should default x to 19');
	CHECK_EQUALS(19, result.y, 'Non-numeric should default y to 19');
});

besogo.addTest('Besogo', 'ParseSizeUndefinedDefaultsTo19', function() {
	let result = besogo.parseSize(undefined);
	CHECK_EQUALS(19, result.x, 'Undefined should default x to 19');
	CHECK_EQUALS(19, result.y, 'Undefined should default y to 19');
});

besogo.addTest('Besogo', 'ParseSizeNonSquareAsymmetric', function() {
	let result = besogo.parseSize('13:9');
	CHECK_EQUALS(13, result.x, 'x should be 13');
	CHECK_EQUALS(9, result.y, 'y should be 9');
});
