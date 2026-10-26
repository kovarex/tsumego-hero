besogo.makeNodeHashTable = function()
{
  var nodeHashTable = [];
  // A map, as the hashes are big or negative numbers, which an array turns into string keys on every lookup
  nodeHashTable.table = new Map();

  nodeHashTable.push = function(node)
  {
    var hash = node.getHash();
    if (!this.table.has(hash))
      this.table.set(hash, []);
    this.table.get(hash).push(node);
  }

  nodeHashTable.erase = function(node)
  {
    var hashPoint = this.table.get(node.getHash());
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
    var hashPoint = this.table.get(node.getHash());
    if (!hashPoint)
      return null;
    for (let i = 0; i < hashPoint.length; ++i)
      if (node.samePositionAs(hashPoint[i]))
        return hashPoint[i];
    return null;
  }
  
  nodeHashTable.size = function()
  {
    let result = 0;
    for (let hashPoint of this.table.values())
      result += hashPoint.length;
    return result;
  }

  return nodeHashTable;
}
