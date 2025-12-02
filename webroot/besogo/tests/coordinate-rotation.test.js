/**
 * Tests for besogo coordinate rotation and transformation functions.
 * 
 * These tests verify that coordinates are correctly transformed when 
 * the board orientation changes. The key insight is that:
 * 
 * - Coordinates are stored as (x, y) where x is column (1-19), y is row (1-19)
 * - Western notation uses letters A-T (skipping I) for columns, numbers 1-19 for rows
 * - Board can be viewed from 4 corners: top-left, top-right, bottom-left, bottom-right
 * 
 * Transformation rules:
 * - top-left: (x, y) → (x, y) (no change)
 * - top-right: (x, y) → (boardSize - x + 1, y) (flip horizontal)
 * - bottom-left: (x, y) → (x, boardSize - y + 1) (flip vertical)
 * - bottom-right: (x, y) → (boardSize - x + 1, boardSize - y + 1) (flip both)
 */

describe('Coordinate System', () => {
  describe('besogo.coord.western', () => {
    let labels;
    
    beforeAll(() => {
      labels = besogo.coord.western(19, 19);
    });
    
    test('should create x labels from A to T (skipping I)', () => {
      expect(labels.x[1]).toBe('A');
      expect(labels.x[2]).toBe('B');
      expect(labels.x[8]).toBe('H');
      expect(labels.x[9]).toBe('J');  // I is skipped
      expect(labels.x[10]).toBe('K');
      expect(labels.x[19]).toBe('T');
    });
    
    test('should create y labels from 19 down to 1', () => {
      expect(labels.y[1]).toBe('19');
      expect(labels.y[2]).toBe('18');
      expect(labels.y[10]).toBe('10');
      expect(labels.y[19]).toBe('1');
    });
    
    test('column 17 should be R (common mistake: thinking q=Q)', () => {
      // SGF letter 'q' maps to coordinate 17, which is letter R in Western notation
      // Because Western notation skips 'I'
      expect(labels.x[17]).toBe('R');
    });
    
    test('column 16 should be Q', () => {
      expect(labels.x[16]).toBe('Q');
    });
  });
});

describe('Coordinate Transformation', () => {
  const boardSize = 19;
  
  /**
   * Transform coordinates based on board corner orientation.
   * This is the pure function we want to test.
   */
  function transformCoordinate(x, y, corner) {
    let newX = x, newY = y;
    
    switch (corner) {
      case 'top-left':
        // No transformation
        break;
      case 'top-right':
        newX = boardSize - x + 1;  // Flip horizontal
        break;
      case 'bottom-left':
        newY = boardSize - y + 1;  // Flip vertical
        break;
      case 'bottom-right':
        newX = boardSize - x + 1;  // Flip both
        newY = boardSize - y + 1;
        break;
    }
    
    return { x: newX, y: newY };
  }
  
  describe('transformCoordinate from top-left', () => {
    test('top-left → top-left should not change coordinates', () => {
      const result = transformCoordinate(17, 3, 'top-left');
      expect(result).toEqual({ x: 17, y: 3 });
    });
    
    test('top-left → top-right should flip horizontally', () => {
      // R17 (17, 3) should become C17 (3, 3)
      // Because 19 - 17 + 1 = 3, and labels.x[3] = 'C'
      const result = transformCoordinate(17, 3, 'top-right');
      expect(result).toEqual({ x: 3, y: 3 });
    });
    
    test('top-left → bottom-left should flip vertically', () => {
      // R17 (17, 3) should become R3 (17, 17)
      const result = transformCoordinate(17, 3, 'bottom-left');
      expect(result).toEqual({ x: 17, y: 17 });
    });
    
    test('top-left → bottom-right should flip both', () => {
      // R17 (17, 3) should become C3 (3, 17)
      const result = transformCoordinate(17, 3, 'bottom-right');
      expect(result).toEqual({ x: 3, y: 17 });
    });
  });
  
  describe('center point transformation', () => {
    test('center point should remain at center regardless of orientation', () => {
      const centerX = 10, centerY = 10;
      
      expect(transformCoordinate(centerX, centerY, 'top-left')).toEqual({ x: 10, y: 10 });
      expect(transformCoordinate(centerX, centerY, 'top-right')).toEqual({ x: 10, y: 10 });
      expect(transformCoordinate(centerX, centerY, 'bottom-left')).toEqual({ x: 10, y: 10 });
      expect(transformCoordinate(centerX, centerY, 'bottom-right')).toEqual({ x: 10, y: 10 });
    });
  });
  
  describe('corner transformations', () => {
    test('top-right corner (19, 1) transformations', () => {
      // Original: T19 (x=19, y=1)
      expect(transformCoordinate(19, 1, 'top-left')).toEqual({ x: 19, y: 1 });  // T19
      expect(transformCoordinate(19, 1, 'top-right')).toEqual({ x: 1, y: 1 });   // A19
      expect(transformCoordinate(19, 1, 'bottom-left')).toEqual({ x: 19, y: 19 }); // T1
      expect(transformCoordinate(19, 1, 'bottom-right')).toEqual({ x: 1, y: 19 }); // A1
    });
  });
});

describe('Path Display Formatting', () => {
  /**
   * Convert a coordinate path to Western display format.
   * Path is stored as [target, parent, grandparent, ...] (reverse chronological)
   * Display should be ancestor→target (chronological)
   */
  function formatPathDisplay(pathCoords, corner) {
    const boardSize = 19;
    const labels = besogo.coord.western(boardSize, boardSize);
    
    // Transform coordinates based on corner
    const transformed = pathCoords.map(function(coord) {
      let x = coord[0], y = coord[1];
      
      switch (corner) {
        case 'top-right':
          x = boardSize - x + 1;
          break;
        case 'bottom-left':
          y = boardSize - y + 1;
          break;
        case 'bottom-right':
          x = boardSize - x + 1;
          y = boardSize - y + 1;
          break;
      }
      
      return [x, y];
    });
    
    // Convert to Western notation
    const display = transformed.map(function(coord) {
      return labels.x[coord[0]] + labels.y[coord[1]];
    });
    
    // Reverse for chronological display
    display.reverse();
    
    return display.join('→');
  }
  
  describe('Single position display', () => {
    test('R17 at top-left should display as R17', () => {
      const path = [[17, 3]];  // R17 in SGF coords
      expect(formatPathDisplay(path, 'top-left')).toBe('R17');
    });
    
    test('R17 at top-right should display as C17', () => {
      // 19 - 17 + 1 = 3, labels.x[3] = 'C'
      const path = [[17, 3]];
      expect(formatPathDisplay(path, 'top-right')).toBe('C17');
    });
    
    test('R17 at bottom-left should display as R3', () => {
      const path = [[17, 3]];
      expect(formatPathDisplay(path, 'bottom-left')).toBe('R3');
    });
    
    test('R17 at bottom-right should display as C3', () => {
      // Flip both: x = 3, y = 17 → C3
      const path = [[17, 3]];
      expect(formatPathDisplay(path, 'bottom-right')).toBe('C3');
    });
  });
  
  describe('Multi-move path display', () => {
    test('Two-move path R17→Q17 at top-left', () => {
      // Path stored as [target, parent] = [[16, 3], [17, 3]]
      // Should display as R17→Q17 (ancestor→target)
      const path = [[16, 3], [17, 3]];
      expect(formatPathDisplay(path, 'top-left')).toBe('R17→Q17');
    });
    
    test('Two-move path should flip horizontally at top-right', () => {
      // R17→Q17 should become C17→D17
      // R(17) → 19-17+1=3 → C, Q(16) → 19-16+1=4 → D
      const path = [[16, 3], [17, 3]];
      expect(formatPathDisplay(path, 'top-right')).toBe('C17→D17');
    });
    
    test('Three-move path should maintain order', () => {
      // Path: move 1 at (17,3), move 2 at (16,3), move 3 at (15,4)
      // Stored as [target, parent, grandparent] = [[15,4], [16,3], [17,3]]
      // Display: R17→Q17→P16
      const path = [[15, 4], [16, 3], [17, 3]];
      expect(formatPathDisplay(path, 'top-left')).toBe('R17→Q17→P16');
    });
  });
});

describe('SGF to Western Coordinate Mapping', () => {
  /**
   * SGF uses letters a-z (lowercase) for coordinates 1-26.
   * charToNum converts: a→1, b→2, ..., z→26
   */
  function charToNum(c) {
    if (c >= 'A' && c <= 'Z') {
      return c.charCodeAt(0) - 'A'.charCodeAt(0) + 27;
    } else {
      return c.charCodeAt(0) - 'a'.charCodeAt(0) + 1;
    }
  }
  
  test('SGF q should map to coordinate 17', () => {
    expect(charToNum('q')).toBe(17);
  });
  
  test('SGF p should map to coordinate 16', () => {
    expect(charToNum('p')).toBe(16);
  });
  
  test('SGF c should map to coordinate 3', () => {
    expect(charToNum('c')).toBe(3);
  });
  
  test('Western R is coordinate 17 (because I is skipped)', () => {
    const labels = besogo.coord.western(19, 19);
    expect(labels.x[17]).toBe('R');
  });
  
  test('SGF qc = Western R17', () => {
    // SGF B[qc] means x=17 (q), y=3 (c)
    // Western: column R (17th letter skipping I), row 17 (19-3+1=17)
    const labels = besogo.coord.western(19, 19);
    const x = charToNum('q');  // 17
    const y = charToNum('c');  // 3
    expect(labels.x[x]).toBe('R');
    expect(labels.y[y]).toBe('17');
  });
  
  test('SGF pc = Western Q17', () => {
    // SGF W[pc] means x=16 (p), y=3 (c)
    const labels = besogo.coord.western(19, 19);
    const x = charToNum('p');  // 16
    const y = charToNum('c');  // 3
    expect(labels.x[x]).toBe('Q');
    expect(labels.y[y]).toBe('17');
  });
});
