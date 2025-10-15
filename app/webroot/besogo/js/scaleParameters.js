besogo.makeScaleParameters = function(size)
{
  var scaleParameters = [];
  scaleParameters.hFlip = false;
  scaleParameters.vFlip = false;

  scaleParameters.clear = function(size)
  {
    this.size = structuredClone(size);
    this.lowest = structuredClone(size);
    this.highest = {x: 0, y: 0};
    this.orientation = '';
    this.boardCanvasSize = 'regular board';
    this.boardCoordSize = this.size.x;
  }

  scaleParameters.setupLimitsFromTree = function(node)
  {
    for (let i = 0; i < node.setupStones.length; ++i)
      if (node.setupStones[i])
        this.addPosition(node.toXY(i));
    if (node.move)
      this.addPosition(node.move);
    for (let i = 0; i < node.children.length; ++i)
      this.setupLimitsFromTree(node.children[i]);
  }

  scaleParameters.addPosition = function(position)
  {
    if (position.x < this.lowest.x)
      this.lowest.x = position.x;
    if (position.x > this.highest.x)
      this.highest.x = position.x;
    if (position.y < this.lowest.y)
      this.lowest.y = position.y;
    if (position.y > this.highest.y)
      this.highest.y = position.y
  }

  scaleParameters.distanceToRight = function()
  {
    return this.size.x - this.highest.x;
  }

  scaleParameters.distanceToLeft = function()
  {
    return this.lowest.x - 1;
  }

  scaleParameters.isOnTheRight = function()
  {
    return this.distanceToLeft() > this.distanceToRight();
  }

  scaleParameters.distanceToBottom = function()
  {
    return this.size.y - this.highest.y;
  }

  scaleParameters.distanceToTop = function()
  {
    return  this.lowest.y - 1;
  }

  scaleParameters.isOnBottom = function()
  {
    return this.distanceToTop() > this.distanceToBottom();
  }

  scaleParameters.normalizeToTopLeft = function()
  {
    if (this.isOnTheRight(this.size))
      this.hFlip = true;
    if (this.isOnBottom(this.size))
      this.vFlip = true;
    let transformation = besogo.makeTransformation();
    transformation.vFlip = this.vFlip;
    transformation.hFlip = this.hFlip;
    this.lowest = transformation.apply(this.lowest, this.size);
    this.highest = transformation.apply(this.highest, this.size);
    if (this.lowest.x > this.highest.x)
      this.highest.x = [this.lowest.x, this.lowest.x = this.highest.x][0]
    if (this.lowest.y > this.highest.y)
      this.highest.y = [this.lowest.y, this.lowest.y = this.highest.y][0]
  }

  scaleParameters.checkFullBoard = function()
  {
    if (this.distanceToRight() >= 10 || this.distanceToBottom() >= 10)
      return false;
    this.orientation = 'full-board';
    return true;
  }

  scaleParameters.prepareTransformation = function(transformation)
  {
    if (this.hFlip)
    {
      transformation.hFlip = !transformation.hFlip;
      this.hFlip = false;
    }
    if (this.vFlip)
    {
      transformation.vFlip = !transformation.vFlip;
      this.vFlip = false;
    }
  }

  scaleParameters.transform = function(root)
  {
    let transformation = besogo.makeTransformation();
    this.prepareTransformation(transformation);
    root.applyTransformationOnRoot(transformation);
  }
  
  scaleParameters.initializeRotation = function(type)
  {
    this.rotation = type;
  }

  scaleParameters.clear(size);

  return scaleParameters;
}
