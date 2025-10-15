// Import or create JGO namespace
var JGO = JGO || {};

JGO.BOARD = JGO.BOARD || {};

JGO.BOARD.medium = {
    textures: {
        black: '/medium/black34.png',
        white: '/medium/white34.png',
        shadow:'/medium/shadow.png',
        correct:'/medium/correct.png',
        incorrect:'/medium/incorrect.png'
    },

    // Margins around the board, both on normal edges and clipped ones
    margin: {normal: 30, clipped: 30},

    // Shadow color, blur and offset
    boardShadow: {color: '#ffe0a8', blur: 22.5, offX: 3.75, offY: 3.75},

    // Lighter border around the board makes it more photorealistic
    border: {color: 'rgba(255, 255, 255, 0.3)', lineWidth: 2},

    // Amount of "extra wood" around the grid where stones lie
    padding: {normal: 15, clipped: 7.5},

    // Grid color and size, line widths
    grid: {color: '#202020', x: 40, y: 40, smooth: 0,
        borderWidth: 1.35, lineWidth: 1.05},

    // Star point radius
    stars: {radius: 2.75},

    // Coordinate color and font
    coordinates: {color: '#808080', font: 'normal 15px sanf-serif'},

    // Stone radius  and alpha for semi-transparent stones
    stone: {radius: 18, dimAlpha:0.6},

    // Shadow offset from center
    shadow: {xOff: -1, yOff: 1},

    // Mark base size and line width, color and font settings
    mark: {lineWidth: 1.25, blackColor: 'white', whiteColor: 'black',
        clearColor: 'black', font: 'normal 18px sanf-serif'}
};

JGO.BOARD.mediumWalnut = JGO.util.extend(JGO.util.extend({}, JGO.BOARD.medium), {
    textures: {board: '/large/texture35.png', shadow: '/medium/shadow_dark.png'},
    boardShadow: {color: '#333'},
    grid: {color: '#101010', borderWidth: 1.6, lineWidth: 1.3}
});

JGO.BOARD.mediumBW = JGO.util.extend(JGO.util.extend({}, JGO.BOARD.medium), {
    textures: false
});
