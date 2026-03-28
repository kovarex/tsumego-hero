besogo.makeNodeHashTable = function()
{
  var nodeHashTable = [];
  nodeHashTable.table = [];

  nodeHashTable.push = function(node)
  {
    var hash = node.getHash();
    if (!this.table[hash])
      this.table[hash] = []
    this.table[hash].push(node);
  }

  nodeHashTable.erase = function(node)
  {
    var hash = node.getHash();
    var hashPoint = this.table[hash];
    if (!hashPoint)
      throw new Error('Node to be removed not found.');

    for (let i = 0; i < hashPoint.length; ++i)
      if (hashPoint[i] == node)
      {
        hashPoint.splice(i, 1);
        return;
      }
    throw new Error('Node to be removed not found.');
  }

  nodeHashTable.getSameNode = function(node)
  {
    var hash = node.getHash();
    var hashPoint = this.table[hash];
    if (!hashPoint)
      return null;
    for (let i = 0; i < hashPoint.length; ++i)
      if (node.samePositionAs(hashPoint[i]))
        return hashPoint[i];
    return null;
  }

  nodeHashTable.getSameNodeWithHash = function(node, hash, stoneCount)
  {
    var hashPoint = this.table[hash];
    if (!hashPoint)
      return null;
    for (let i = 0; i < hashPoint.length; ++i)
    {
      if (stoneCount !== undefined && hashPoint[i].stoneCount !== stoneCount)
        continue;
      if (node.samePositionAs(hashPoint[i]))
        return hashPoint[i];
    }
    return null;
  }

  nodeHashTable.hasHash = function(hash)
  {
    var hashPoint = this.table[hash];
    return hashPoint && hashPoint.length > 0;
  }
  
  nodeHashTable.size = function()
  {
    let result = 0;
    for (var index in this.table)
      result += this.table[index].length;
    return result;
  }

  return nodeHashTable;
}
