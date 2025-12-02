/**
 * Jest Setup for Besogo Tests
 * 
 * This file creates a minimal mock environment that allows us to test
 * besogo JavaScript functions without modifying the source code.
 * 
 * The trick: JavaScript's dynamic nature lets us:
 * 1. Create the global 'besogo' object before loading scripts
 * 2. Load scripts using eval() or require() 
 * 3. Intercept and mock parts we don't need
 */

// Create minimal besogo global that scripts expect
global.besogo = {
  coord: {},
  boardParameters: {},
  scaleParameters: {},
  editor: null,
  dynamicCommentCoords: []
};

// Create jQuery mock (besogo uses jQuery for some DOM operations)
global.$ = global.jQuery = function(selector) {
  return {
    text: function(val) { return this; },
    attr: function(name, val) { return this; },
    html: function(val) { return this; },
    find: function(sel) { return this; },
    each: function(fn) { return this; },
    click: function(fn) { return this; },
  };
};

// Load the coordinate system functions - these are pure and testable
const fs = require('fs');
const path = require('path');

// Load coord.js - this contains the Western coordinate system
const coordScript = fs.readFileSync(path.join(__dirname, '../js/coord.js'), 'utf8');
eval(coordScript);
