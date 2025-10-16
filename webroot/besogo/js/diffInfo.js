var DIFF_NO_CHANGE = 0;
var DIFF_ADDED = 1;
var DIFF_REMOVED = 2;

besogo.makeDiffInfo = function(type = DIFF_NO_CHANGE)
{
  var diffInfo = [];
  diffInfo.type = type;
  diffInfo.previousStatus = null;
  return diffInfo;
}

besogo.makeAddedMoveDiffInfo = function()
{
  return besogo.makeDiffInfo(DIFF_ADDED);
}

besogo.makeRemovedMoveDiffInfo = function()
{
  return besogo.makeDiffInfo(DIFF_REMOVED);
}
