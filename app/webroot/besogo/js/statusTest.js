besogo.addTest("Status", "None", function()
{
  let status = besogo.makeStatusSimple(STATUS_NONE);
  CHECK_EQUALS(status.blackFirst.type, STATUS_NONE);
});

besogo.addTest("Status", "SaveLoadBent4InTheCorner", function()
{
  let status = besogo.makeStatusSimple(STATUS_BENT_FOUR_IN_THE_CORNER);
  CHECK_EQUALS(status.str(), "BENT4");
  let statusLoaded = besogo.loadStatusFromString(status.str());
  CHECK_EQUALS(statusLoaded.str(), "BENT4");
  CHECK_EQUALS(statusLoaded.blackFirst.type, STATUS_BENT_FOUR_IN_THE_CORNER);
});

besogo.addTest("Status", "SaveLoadDeadInDoubleKo", function()
{
  let status = besogo.makeStatusSimple(STATUS_DEAD_IN_DOUBLE_KO);
  CHECK_EQUALS(status.str(), "DDKO");
  let statusLoaded = besogo.loadStatusFromString(status.str());
  CHECK_EQUALS(statusLoaded.str(), "DDKO");
  CHECK_EQUALS(statusLoaded.blackFirst.type, STATUS_DEAD_IN_DOUBLE_KO);
});

besogo.addTest("Status", "SaveLoadAliveInDoubleKo", function()
{
  let status = besogo.makeStatusSimple(STATUS_ALIVE_IN_DOUBLE_KO);
  CHECK_EQUALS(status.str(), "ADKO");
  let statusLoaded = besogo.loadStatusFromString(status.str());
  CHECK_EQUALS(statusLoaded.str(), "ADKO");
  CHECK_EQUALS(statusLoaded.blackFirst.type, STATUS_ALIVE_IN_DOUBLE_KO);
});

besogo.addTest("Status", "StatusKoThreatsSimple", function()
{
  let status1 = besogo.makeStatus();
  status1.setKo(0);
  CHECK_EQUALS(status1.str(), "KO+");

  let status2 = besogo.makeStatus();
  status2.setKo(-1);
  CHECK_EQUALS(status2.str(), "KO-");

  // regarldess of goal, ko takes first is better
  CHECK(status1.better(status2, GOAL_KILL));
  CHECK(!status2.better(status1, GOAL_KILL));

  CHECK(status1.better(status2, GOAL_LIVE));
  CHECK(!status2.better(status1, GOAL_LIVE));
});

besogo.addTest("Status", "StatusKoThreatsHigher", function()
{
  let status1 = besogo.makeStatus();
  status1.setKo(1);
  CHECK_EQUALS(status1.str(), "KO+2");

  let status2 = besogo.makeStatus();
  status2.setKo(0);
  CHECK_EQUALS(status2.str(), "KO+");

  CHECK(status1.better(status2));
  CHECK(!status2.better(status1));
});

besogo.addTest("Status", "StatusKoThreatsSaveLoad", function()
{
  for (let extraThreats = -3; extraThreats < 4; ++extraThreats)
  {
    let status1 = besogo.makeStatus();
    status1.setKo(extraThreats);
    let str = status1.str();
    let status2 = besogo.loadStatusFromString(str);
    CHECK_EQUALS(status2.blackFirst.extraThreats, extraThreats);
  }
});

besogo.addTest("Status", "SaveLoadKo", function()
{
  let status = besogo.makeStatusSimple(STATUS_KO);
  let str = status.str();
  let statusLoaded = besogo.loadStatusFromString(str);
  CHECK_EQUALS(status.blackFirst.type, STATUS_KO);
});

besogo.addTest("Status", "DeadBetterThanko", function()
{
  let status1 = besogo.makeStatusSimple(STATUS_DEAD);
  let status2 = besogo.makeStatusSimple(STATUS_KO);

  // when goal is to kill, dead is better
  CHECK(status1.better(status2, GOAL_KILL));
  CHECK(!status2.better(status1, GOAL_KILL));

  // when goal is to live, dead is worse
  CHECK(!status1.better(status2, GOAL_LIVE));
  CHECK(status2.better(status1, GOAL_LIVE));
});

besogo.addTest("Status", "ApproachKoGood", function()
{
  let status = besogo.makeStatusSimple(STATUS_KO);
  status.setApproachKo(1);
  CHECK_EQUALS(status.str(), "A+1KO+");
});

besogo.addTest("Status", "ApproachKoBad", function()
{
  let status = besogo.makeStatusSimple(STATUS_KO);
  status.setApproachKo(-1);
  CHECK_EQUALS(status.str(), "A-1KO+");
});

besogo.addTest("Status", "ApproachKoGoodBetterThanApproachKoBad", function()
{
  let statusBad = besogo.makeStatusSimple(STATUS_KO);
  statusBad.setApproachKo(-1);
  let statusGood = besogo.makeStatusSimple(STATUS_KO);
  statusGood.setApproachKo(1);
  CHECK(statusGood.better(statusBad));
  CHECK(!statusBad.better(statusGood));
});

besogo.addTest("Status", "SaveLoadApproachKo", function()
{
  let status = besogo.makeStatusSimple(STATUS_KO);
  status.setApproachKo(-1);
  CHECK_EQUALS(status.str(), "A-1KO+");

  let statusLoaded = besogo.loadStatusFromString(status.str());
  CHECK_EQUALS(statusLoaded.str(), "A-1KO+");
  CHECK_EQUALS(statusLoaded.blackFirst.approaches, -1);
  CHECK_EQUALS(statusLoaded.blackFirst.extraThreats, 0);
});

besogo.addTest("Status", "SaveLoadPositiveApproachKo", function()
{
  let status = besogo.makeStatusSimple(STATUS_KO);
  status.setApproachKo(1);
  CHECK_EQUALS(status.str(), "A+1KO+");

  let statusLoaded = besogo.loadStatusFromString(status.str());
  CHECK_EQUALS(statusLoaded.str(), "A+1KO+");
  CHECK_EQUALS(statusLoaded.blackFirst.approaches, 1);
  CHECK_EQUALS(statusLoaded.blackFirst.extraThreats, 0);
});

besogo.addTest("Status", "SaveLoadApproachKoWithNegativeExtraThreats", function()
{
  let status = besogo.makeStatusSimple(STATUS_KO);
  status.setApproachKo(-1, -1);
  CHECK_EQUALS(status.str(), "A-1KO-");

  let statusLoaded = besogo.loadStatusFromString(status.str());
  CHECK_EQUALS(statusLoaded.str(), "A-1KO-");
  CHECK_EQUALS(statusLoaded.blackFirst.approaches, -1);
  CHECK_EQUALS(statusLoaded.blackFirst.extraThreats, -1);
});

besogo.addTest("Status", "SaveLoadTenThousandYearsKo", function()
{
  let status = besogo.makeStatusSimple(STATUS_TEN_THOUSAND_YEARS_KO);
  CHECK_EQUALS(status.str(), "10kKO");
  let statusLoaded = besogo.loadStatusFromString(status.str());
  CHECK_EQUALS(statusLoaded.str(), "10kKO");
  CHECK_EQUALS(statusLoaded.blackFirst.type, STATUS_TEN_THOUSAND_YEARS_KO);
});

besogo.addTest("Status", "TenThousandYearsKoIsWorseThanDirectKo", function()
{
  let statusBad = besogo.makeStatusSimple(STATUS_TEN_THOUSAND_YEARS_KO);
  let statusGood = besogo.makeStatusSimple(STATUS_KO);

  CHECK(statusGood.better(statusBad));
  CHECK(!statusBad.better(statusGood));
});

besogo.addTest("Status", "TenThousandYearsKoIsSameAsOneMoveApproachKo", function()
{
  let status1 = besogo.makeStatusSimple(STATUS_TEN_THOUSAND_YEARS_KO);
  let status2 = besogo.makeStatusSimple(STATUS_KO);
  status2.setApproachKo(-1);

  CHECK(!status1.better(status2));
  CHECK(!status2.better(status1));
});

besogo.addTest("Status", "TenThousandYearsKoIsBetterThanTwoMovesApproachKo", function()
{
  let statusGood = besogo.makeStatusSimple(STATUS_TEN_THOUSAND_YEARS_KO);
  let statusBad = besogo.makeStatusSimple(STATUS_KO);
  statusBad.setApproachKo(-2);

  CHECK(statusGood.better(statusBad));
  CHECK(!statusBad.better(statusGood));
});

besogo.addTest("Status", "StatusSekiSimple", function()
{
  let status1 = besogo.makeStatus();
  status1.setSeki(false);
  CHECK_EQUALS(status1.str(), "SEKI");

  let status2 = besogo.makeStatus();
  status2.setSeki(true);
  CHECK_EQUALS(status2.str(), "SEKI+");

  CHECK(!status1.better(status2));
  CHECK(status2.better(status1));
});

besogo.addTest("Status", "SaveLoadSeki", function()
{
  let status = besogo.makeStatusSimple(STATUS_SEKI);
  let str = status.str();
  let statusLoaded = besogo.loadStatusFromString(str);
  CHECK_EQUALS(status.blackFirst.type, STATUS_SEKI);
  CHECK(!status.blackFirst.sente);
});

besogo.addTest("Status", "SaveLoadSeki+", function()
{
  let status = besogo.makeStatusSimple(STATUS_SEKI);
  status.setSeki(true);
  let str = status.str();
  CHECK(str == 'SEKI+');

  let statusLoaded = besogo.loadStatusFromString(str);
  CHECK_EQUALS(status.blackFirst.type, STATUS_SEKI);
  CHECK(status.blackFirst.sente);
});

besogo.addTest("Status", "InitStatusOnLoadWithoutNone", function()
{
  let editor = besogo.makeEditor();
  let childDead = editor.getRoot().registerMove(1, 1);
  childDead.setStatusSource(besogo.makeStatusSimple(STATUS_DEAD));

  let childKo = editor.getRoot().registerMove(2, 1);
  childKo.setStatusSource(besogo.makeStatusSimple(STATUS_KO));

  CHECK_EQUALS(editor.getRoot().children.length, 2);

  let editor2 = besogo.makeEditor();
  besogo.loadSgf(besogo.parseSgf(besogo.composeSgf(editor)), editor2);

  CHECK_EQUALS(editor2.getRoot().children.length, 2);
});

besogo.addTest("Status", "InitStatusOnLoad", function()
{
  let editor = besogo.makeEditor();
  let childDead = editor.getRoot().registerMove(1, 1);
  childDead.setStatusSource(besogo.makeStatusSimple(STATUS_DEAD));

  let childKo = editor.getRoot().registerMove(2, 1);
  childKo.setStatusSource(besogo.makeStatusSimple(STATUS_KO));

  let childNone = editor.getRoot().registerMove(3, 1);
  CHECK_EQUALS(editor.getRoot().children.length, 3);

  let editor2 = besogo.makeEditor();
  besogo.loadSgf(besogo.parseSgf(besogo.composeSgf(editor)), editor2);

  CHECK_EQUALS(editor2.getRoot().children.length, 3);
});

besogo.addTest("Status", "SetStatusSourceOnNonLeaf", function()
{
  let root = besogo.makeGameRoot();
  let child = root.registerMove(5, 5);
  let childOfChild = child.registerMove(6, 6);
  CHECK(child.hasChildIncludingVirtual());
  CHECK(child.statusSource == null);
  child.setStatusSource(besogo.makeStatusSimple(STATUS_DEAD));
  CHECK(child.statusSource == null);
});

besogo.addTest("Status", "AddChildToNodeWithStatusSource", function()
{
  let root = besogo.makeGameRoot();
  let child = root.registerMove(5, 5);
  CHECK(!child.statusSource);
  child.setStatusSource(besogo.makeStatusSimple(STATUS_DEAD));
  CHECK(child.statusSource);
  child.registerMove(6, 6);
  CHECK(!child.statusSource);
});

besogo.addTest("Status", "LoadingSgfWithStatusSourceNotOnLeafGetsFixed", function()
{
  let editor = besogo.makeEditor();
  let root = editor.getRoot();
  let child = root.registerMove(5, 5);
  child.registerMove(6, 6);
  // setting it up "illegaly" to get to a wrong state.
  child.statusSource = besogo.makeStatusSimple(STATUS_DEAD);

  let editor2 = besogo.makeEditor();
  besogo.loadSgf(besogo.parseSgf(besogo.composeSgf(editor)), editor2);

  CHECK_EQUALS(editor2.getRoot().children.length, 1);
  CHECK(editor2.getRoot().children[0].statusSource == null);
});


besogo.addTest("Status", "EmptyGameRootHasStatus", function()
{
  let root = besogo.makeGameRoot();
  CHECK(root.status);
});
