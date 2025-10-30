const GOAL_NONE = 0;
const GOAL_KILL = 1;
const GOAL_LIVE = 2;

const STATUS_NONE = 0;
const STATUS_DEAD_NONE = 1;
const STATUS_DEAD = 2;
const STATUS_BENT_FOUR_IN_THE_CORNER = 3;
const STATUS_DEAD_IN_SUPER_KO = 4;
const STATUS_DEAD_IN_DOUBLE_KO = 5;
const STATUS_TEN_THOUSAND_YEARS_KO = 6;
const STATUS_KO = 7;
const STATUS_SEKI = 8;
const STATUS_ALIVE_IN_DOUBLE_KO = 9;
const STATUS_ALIVE_IN_SUPER_KO = 10;
const STATUS_ALIVE = 11;
const STATUS_ALIVE_NONE = 12;

besogo.makeStatusInternal = function(type)
{
  var status = [];
  status.type = type;
  if (type == STATUS_SEKI)
    status.sente = false;
  else if (type == STATUS_KO)
  {
    status.approaches = 0;
    status.extraThreats = 0;
  }

  status.str = function()
  {
    if (this.type == STATUS_DEAD)
      return "DEAD";
    if (this.type == STATUS_BENT_FOUR_IN_THE_CORNER)
      return "BENT4";
    if (this.type == STATUS_DEAD_IN_SUPER_KO)
      return "ASKO";
    if (this.type == STATUS_DEAD_IN_DOUBLE_KO)
      return "DDKO";
    if (this.type == STATUS_ALIVE_IN_DOUBLE_KO)
      return "ADKO";
    if (this.type == STATUS_ALIVE_IN_SUPER_KO)
      return "ASKO";
    if (this.type == STATUS_TEN_THOUSAND_YEARS_KO)
      return "10kKO";
    if (this.type == STATUS_KO)
      return this.getKoApproachesStr() + 'KO' + this.getKoStr();
    if (this.type == STATUS_SEKI)
      return "SEKI" + (this.sente ? '+' : '');
    if (this.type == STATUS_ALIVE)
      return "ALIVE";
  }

  status.getApproachCount = function()
  {
    if (!this.approaches)
      return 0;
    return this.approaches;
  }

  status.strLong = function()
  {
    if (this.type == STATUS_TEN_THOUSAND_YEARS_KO)
      return "Ten thousand years ko";
    if (this.type == STATUS_BENT_FOUR_IN_THE_CORNER)
      return "Bent four in the corner";
    if (this.type == STATUS_DEAD_IN_SUPER_KO)
      return "Dead in super ko";
    if (this.type == STATUS_DEAD_IN_DOUBLE_KO)
      return "Dead in double ko";
    if (this.type == STATUS_ALIVE_IN_SUPER_KO)
      return "Alive in super ko";
    if (this.type == STATUS_ALIVE_IN_DOUBLE_KO)
      return "Alive in double ko";
    if (this.type == STATUS_KO)
      return this.str() + ' (' + this.getKoApproachStrLong() + this.getKoStrLong() + ')';
    if (this.type == STATUS_SEKI)
      return this.str() + ' (' + (this.sente ? "in sente" : "in gote") + ')';
    return this.str();
  }

  status.getKoApproachesStr = function()
  {
    console.assert(this.type == STATUS_KO);
    if (!this.approaches || this.approaches == 0)
      return '';

    let result = '';
    if (this.approaches > 0)
      result += "A+";
    else
      result += "A-";

    if (this.approaches > 0)
      result += this.approaches;
    else if (this.approaches < 0)
      result += -this.approaches;
    return result;
  }

  status.getKoStr = function()
  {
    console.assert(this.type == STATUS_KO);
    let result = '';
    if (!this.extraThreats || this.extraThreats >= 0)
      result += "+";
    else
      result += "-";

    if (this.extraThreats > 0)
      result += (this.extraThreats + 1)
    else if (this.extraThreats < -1)
      result += -this.extraThreats;
    return result;
  }

  status.getKoApproachStrLong = function()
  {
    console.assert(this.type == STATUS_KO);
    if (!this.approaches || this.approaches == 0)
      return '';
    if (this.approaches > 0)
      return 'White needs to do ' + this.approaches + ' approach move' + (this.approaches > 1 ? 's' : '') + ' to start a direct ko, ';
    if (this.approaches < 0)
      return 'Black needs to do ' + -this.approaches + ' approach move' + (this.approaches < -1 ? 's' : '') + ' to start a direct ko, ';
  }

  status.getKoStrLong = function()
  {
    console.assert(this.type == STATUS_KO);
    if (!this.extraThreats || this.extraThreats == 0)
      return 'Black takes first';
    if (this.extraThreats == -1)
      return 'White takes first';
    if (this.extraThreats > 0)
      return 'White needs ' + this.extraThreats + ' threat' + (this.extraThreats > 1 ? 's' : '') + ' to start the ko';
    if (this.extraThreats < 0)
      return 'Black needs ' + (-this.extraThreats - 1) + ' threat' + (this.extraThreats < -2 ? 's' : '') + ' to start the ko';
  }



  status.setKo = function(extraThreats)
  {
    this.type = STATUS_KO;
    this.extraThreats = extraThreats;
  }

  status.setApproachKo = function(approaches, extraThreats = 0)
  {
    this.type = STATUS_KO;
    this.approaches = approaches;
    this.extraThreats = extraThreats;
  }

  status.setSeki = function(sente)
  {
    this.type = STATUS_SEKI;
    this.sente = sente;
  }

  status.better = function(other, goal)
  {
    if (this.type == STATUS_TEN_THOUSAND_YEARS_KO)
      if (other.type == STATUS_KO)
        return other.approaches < -1;
    if (other.type == STATUS_TEN_THOUSAND_YEARS_KO)
      if (this.type == STATUS_KO)
        return this.approaches > -1;
    if (this.type != other.type)
      return goal == GOAL_KILL ? (this.type < other.type) : (this.type > other.type);
    if (this.type == STATUS_KO)
      if (this.approaches != other.approaches)
        return this.approaches > other.approaches;
      else
        return this.extraThreats > other.extraThreats;
    if (this.type == STATUS_SEKI)
      return this.sente && !other.sente;
    return false;
  }
  return status;
}

besogo.makeStatusSimple = function(blackFirstType)
{
  return besogo.makeStatus(besogo.makeStatusInternal(blackFirstType));
}

besogo.loadStatusFromString = function(str)
{
  var status = [];
  var parts = str.split('/');
  if (parts.length == 1)
    return besogo.makeStatus(besogo.loadStatusInternalFromString(str));
  return besogo.makeStatus(besogo.loadStatusInternalFromString(parts[0]),
                           besogo.loadStatusInternalFromString(parts[1]));
}

besogo.loadGoalFromString = function(str)
{
  if (str == "KILL")
    return GOAL_KILL;
  if (str == "LIVE")
    return GOAL_LIVE;
  return GOAL_NONE;
}

besogo.goalStr = function(goal)
{
  if (goal == GOAL_KILL)
    return "KILL";
  if (goal == GOAL_LIVE)
    return "LIVE";
  return '';
}

besogo.loadStatusInternalFromString = function(str)
{
  if (str == "DEAD")
    return besogo.makeStatusInternal(STATUS_DEAD);
  if (str == "SEKI")
    return besogo.makeStatusInternal(STATUS_SEKI);
  if (str == "SEKI+")
  {
    let status = besogo.makeStatusInternal(STATUS_SEKI);
    status.setSeki(true);
    return status;
  }
  if (str == "ALIVE")
    return besogo.makeStatusInternal(STATUS_ALIVE);

  if (str == "10kKO")
    return besogo.makeStatusInternal(STATUS_TEN_THOUSAND_YEARS_KO);

  if (str == "BENT4")
    return besogo.makeStatusInternal(STATUS_BENT_FOUR_IN_THE_CORNER);

  if (str == "DDKO")
    return besogo.makeStatusInternal(STATUS_DEAD_IN_DOUBLE_KO);

  if (str == "DSKO")
    return besogo.makeStatusInternal(STATUS_DEAD_IN_SUPER_KO);

  if (str == "ADKO")
    return besogo.makeStatusInternal(STATUS_ALIVE_IN_DOUBLE_KO);

  if (str == "ASKO")
    return besogo.makeStatusInternal(STATUS_ALIVE_IN_SUPER_KO);

  var approaches = 0;
  if (str[0] == "A" && (str[1] == "+" || str[1] == "-"))
  {
    let i = 2;
    while (!isNaN(parseInt(str[i])))
    {
      approaches *= 10;
      approaches += parseInt(str[i]);
      ++i;
    }
    if (str[1] == "-")
      approaches = -approaches;
    str = str.substr(i, str.length - i);
  }

  if (str.length >= 2 && str[0] == "K" && str[1] == "O")
  {
    let result = besogo.makeStatusInternal(STATUS_KO);
    result.approaches = approaches;
    if (str.length == 2)
      return result;
    if (str[2] == "+")
    {
      if (str.length == 3)
      {
        result.extraThreats = 0;
        return result;
      }
      let number = Number(str.substr(3, str.length - 3));
      result.extraThreats = number - 1;
      return result;
    }
    if (str[2] == "-")
    {
      if (str.length == 3)
      {
        result.extraThreats = -1;
        return result;
      }
      let number = Number(str.substr(3, str.length - 3));
      result.extraThreats = -number;
      return result;
    }
  }
}

besogo.makeStatus = function(blackFirst = null, whiteFirst = null)
{
  var status = [];
  status.blackFirst = blackFirst ? blackFirst : besogo.makeStatusInternal(STATUS_NONE);
  status.whiteFirst = whiteFirst ? whiteFirst : besogo.makeStatusInternal(STATUS_ALIVE);

  status.str = function()
  {
    var result = "";
    if (this.whiteFirst.type != STATUS_ALIVE)
    {
      result += this.whiteFirst.str();
      result += "/";
    }
    result += this.blackFirst.str();
    return result;
  }

  status.strLong = function()
  {
    var result = "";
    if (this.whiteFirst.type != STATUS_ALIVE)
    {
      result += this.whiteFirst.strLong();
      result += "/";
    }
    result += this.blackFirst.strLong();
    return result;
  }

  status.better = function(other, goal = GOAL_KILL)
  {
    if (this.blackFirst.type == STATUS_NONE)
      return false;
    return this.blackFirst.better(other.blackFirst, goal);
  }

  status.setKo = function(extraThreats)
  {
    this.blackFirst.setKo(extraThreats);
  }

  status.setApproachKo = function(approaches, extraThreats = 0)
  {
    this.blackFirst.setApproachKo(approaches, extraThreats);
  }

  status.setSeki = function(sente)
  {
    this.blackFirst.setSeki(sente);
  }

  status.isNone = function()
  {
    return this.blackFirst.type == STATUS_NONE;
  }

  return status;
}
