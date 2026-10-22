besogo.makeTransformation = function()
{
  var transformation = [];

  transformation.hFlip = false;
  transformation.vFlip = false;
  transformation.rotate = false;
  transformation.invertColors = false;
  
  transformation.apply = function(position, size)
  {
    let result = [];
    result.x = position.x;
    result.y = position.y;
    if (this.hFlip)
      result.x = size.x - position.x + 1;
    if (this.vFlip)
      result.y = size.y - position.y + 1;
    if (this.rotateClockwise)
      [result.x, result.y] = [size.x - result.y + 1, result.x];
	if (this.rotateCounterClockwise)
      [result.y, result.x] = [size.y - result.x + 1, result.y];
    return result;
  }

  // Undoes apply: the steps are reverted in the opposite order.
  transformation.applyInverse = function(position, size)
  {
    let result = [];
    result.x = position.x;
    result.y = position.y;
    if (this.rotateCounterClockwise)
      [result.x, result.y] = [size.y - result.y + 1, result.x];
    if (this.rotateClockwise)
      [result.x, result.y] = [result.y, size.x - result.x + 1];
    if (this.vFlip)
      result.y = size.y - result.y + 1;
    if (this.hFlip)
      result.x = size.x - result.x + 1;
    return result;
  }

  transformation.applyOnColor = function(color)
  {
    if (this.invertColors)
      return -color;
    return color;
  }
  return transformation;
}
