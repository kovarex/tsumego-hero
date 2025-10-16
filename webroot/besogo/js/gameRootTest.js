besogo.addTest("GameRoot", "Empty", function()
{
  let root = besogo.makeGameRoot();
  CHECK_EQUALS(root.children.length, 0);
  CHECK_EQUALS(root.virtualChildren.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 0);
});

besogo.addTest("GameRoot", "OneMove", function()
{
  let root = besogo.makeGameRoot();
  root.registerMove(5, 5);
  CHECK_EQUALS(root.children.length, 1);
  CHECK_EQUALS(root.children.length, 1);
  CHECK_EQUALS(root.virtualChildren.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 1);
});

besogo.addTest("GameRoot", "RemoveOneChild", function()
{
  let root = besogo.makeGameRoot();
  let child = root.registerMove(5, 5);
  child.destroy();
  CHECK_EQUALS(root.children.length, 0);
  CHECK_EQUALS(root.virtualChildren.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 0);
});

besogo.addTest("GameRoot", "RemoveVariation", function()
{
  let root = besogo.makeGameRoot();
  let child = root.registerMove(5, 5);
  CHECK_EQUALS(root.nodeHashTable.size(), 1);

  child.registerMove(6, 6);
  CHECK(child.hasChildIncludingVirtual());
  CHECK_EQUALS(root.children.length, 1);
  CHECK_EQUALS(child.children.length, 1);
  CHECK_EQUALS(root.nodeHashTable.size(), 2);
  child.destroy();
  
  CHECK_EQUALS(root.children.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 0);
});


besogo.addTest("GameRoot", "TwoOrderOfMovesLeadToTheSameNode", function()
{
  let root = besogo.makeGameRoot();
  let finalChild = root.registerMove(1, 1).registerMove(1, 2).registerMove(2, 1).registerMove(2, 2);
  let otherOrder = root.registerMove(2, 1).registerMove(2, 2).registerMove(1, 1);
  
  CHECK_EQUALS(otherOrder.virtualChildren.length, 1);
  CHECK(otherOrder.virtualChildren[0].target == finalChild);
  CHECK_EQUALS(finalChild.virtualParents.length, 1);
  CHECK(finalChild.virtualParents[0] == otherOrder);
});