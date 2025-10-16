function displayMultipleChoiceResult(num) {
  let correct = 0;
  let mText = "";
  let mText2 = "";
  let mText3 = 0;
  let mTextPlural = "s";
  let libPlural1 = "liberties";
  let libPlural2 = "liberties";
  let multipleChoiceLibertiesB2 =
    multipleChoiceLibertyCount - multipleChoiceLibertiesB;
  let multipleChoiceLibertiesW2 =
    multipleChoiceLibertyCount - multipleChoiceLibertiesW;
  let libCalcB, libCalcW, libCalcB2, libCalcW2, libCalc3;
  let blackLiberties = multipleChoiceLibertyCount - multipleChoiceLibertiesW;
  let whiteLiberties = multipleChoiceLibertyCount - multipleChoiceLibertiesB;

  if (!hasChosen) {
    $("#besogo-multipleChoice1").css("background-color", "#e0747f");
    $("#besogo-multipleChoice2").css("background-color", "#e0747f");
    $("#besogo-multipleChoice3").css("background-color", "#e0747f");
    $("#besogo-multipleChoice4").css("background-color", "#e0747f");
    if (multipleChoiceSemeaiType == 1) {
      if (blackLiberties < whiteLiberties) {
        correct = 1;
        $("#besogo-multipleChoice1").css("background-color", "#3ecf78");
        mText3 = multipleChoiceLibertiesW - multipleChoiceLibertiesB;
        if (mText3 == 1) mTextPlural = "";
        mText2 =
          "Black is dead. (" + mText3 + " move" + mTextPlural + " behind)";
      } else if (blackLiberties > whiteLiberties) {
        correct = 2;
        $("#besogo-multipleChoice2").css("background-color", "#3ecf78");
        mText3 = multipleChoiceLibertiesB - multipleChoiceLibertiesW;
        if (mText3 == 1) mTextPlural = "";
        mText2 =
          "White is dead. (" + mText3 + " move" + mTextPlural + " behind)";
      } else {
        correct = 3;
        $("#besogo-multipleChoice3").css("background-color", "#3ecf78");
        mText2 = "The position is unsettled. (Whoever plays first wins.)";
      }
      if (blackLiberties == 1) libPlural1 = "liberty";
      if (whiteLiberties == 1) libPlural2 = "liberty";
      mText =
        "Semeai Type 1: No eyes, less than 2 inside liberties.<br>No Seki possible.<br> Black has " +
        blackLiberties +
        " " +
        libPlural1 +
        ".<br> White has " +
        whiteLiberties +
        " " +
        libPlural2 +
        ".<br>" +
        mText2;
    } else if (multipleChoiceSemeaiType == 2) {
      mText =
        "Semeai Type 2: No eyes, 2 or more inside liberties.<br>The favorite's task is to kill.<br>The favorite counts his outside liberties plus 1 inside liberty.<br>The underdog's task is Seki.<br>The underdog counts his outside liberties plus all inside liberties.<br>";
      let underdog = "Black";
      if (tsumegoFileLink == 29813) {
        mText += "Seki.<br>";
        $("#besogo-multipleChoice4").css("background-color", "#3ecf78");
        correct = 4;
      } else if (multipleChoiceLibertiesB < multipleChoiceLibertiesW) {
        mText += "White is the favorite with more outside liberties.<br>";
        underdog = "Black";
        libCalcB =
          parseInt(multipleChoiceLibertyCount) -
          parseInt(multipleChoiceLibertiesW) +
          parseInt(multipleChoiceInsideLiberties);
        libCalcW =
          parseInt(multipleChoiceLibertyCount) -
          parseInt(multipleChoiceLibertiesB) +
          1;
        libCalcB2 =
          parseInt(multipleChoiceLibertyCount) -
          parseInt(multipleChoiceLibertiesW);
        libCalcW2 =
          parseInt(multipleChoiceLibertyCount) -
          parseInt(multipleChoiceLibertiesB);
        if (libCalcB == 1) libPlural1 = "liberty";
        if (libCalcW == 1) libPlural2 = "liberty";
        mText +=
          "Black has " +
          libCalcB +
          " " +
          libPlural1 +
          ".(underdog: " +
          libCalcB2 +
          " outside + " +
          multipleChoiceInsideLiberties +
          " inside)<br>";
        mText +=
          "White has " +
          libCalcW +
          " " +
          libPlural2 +
          ".(favorite: " +
          libCalcW2 +
          " outside + 1 inside)<br>";
        libCalc3 = libCalcW - libCalcB;
        if (libCalc3 === 0) {
          mText += "Unsettled. (whoever plays first accomplishes the task)<br>";
          $("#besogo-multipleChoice3").css("background-color", "#3ecf78");
          correct = 3;
        } else if (libCalc3 < 0) {
          mText += "Seki.<br>";
          $("#besogo-multipleChoice4").css("background-color", "#3ecf78");
          correct = 4;
        } else {
          mText += "Black is dead. (" + libCalc3 + " move(s) behind)<br>";
          $("#besogo-multipleChoice1").css("background-color", "#3ecf78");
          correct = 1;
        }
      } else if (multipleChoiceLibertiesB > multipleChoiceLibertiesW) {
        mText += "Black is the favorite with more outside liberties.<br>";
        underdog = "White";
        libCalcB =
          parseInt(multipleChoiceLibertyCount) -
          parseInt(multipleChoiceLibertiesW) +
          1;
        libCalcW =
          parseInt(multipleChoiceLibertyCount) -
          parseInt(multipleChoiceLibertiesB) +
          parseInt(multipleChoiceInsideLiberties);
        libCalcB2 =
          parseInt(multipleChoiceLibertyCount) -
          parseInt(multipleChoiceLibertiesW);
        libCalcW2 =
          parseInt(multipleChoiceLibertyCount) -
          parseInt(multipleChoiceLibertiesB);
        if (libCalcB == 1) libPlural1 = "liberty";
        if (libCalcW == 1) libPlural2 = "liberty";
        mText +=
          "White has " +
          libCalcW +
          " " +
          libPlural2 +
          ".(underdog: " +
          libCalcW2 +
          " outside + " +
          multipleChoiceInsideLiberties +
          " inside)<br>";
        mText +=
          "Black has " +
          libCalcB +
          " " +
          libPlural1 +
          ".(favorite: " +
          libCalcB2 +
          " outside + 1 inside)<br>";
        libCalc3 = libCalcB - libCalcW;
        if (libCalc3 === 0) {
          mText += "Unsettled. (whoever plays first accomplishes the task)<br>";
          $("#besogo-multipleChoice3").css("background-color", "#3ecf78");
          correct = 3;
        } else if (libCalc3 < 0) {
          mText += "Seki.<br>";
          $("#besogo-multipleChoice4").css("background-color", "#3ecf78");
          correct = 4;
        } else {
          mText += "White is dead. (" + libCalc3 + " move(s) behind)<br>";
          $("#besogo-multipleChoice2").css("background-color", "#3ecf78");
          correct = 2;
        }
      } else {
        mText += "Seki - same amount of liberties.<br>";
        $("#besogo-multipleChoice4").css("background-color", "#3ecf78");
        correct = 4;
      }
    } else if (multipleChoiceSemeaiType == 3) {
      console.log("b" + multipleChoiceLibertiesB);
      console.log(multipleChoiceLibertiesW);
      console.log(tsumegoFileLink);
      let moreText = "";
      if (tsumegoFileLink == 29824)
        moreText =
          "White can play 2 under the stones tesujis to gain 2 more liberties.<br>";
      if (
        (tsumegoFileLink == 29824 &&
          multipleChoiceLibertiesB == 1 &&
          multipleChoiceLibertiesW == 2) ||
        (tsumegoFileLink == 29825 &&
          multipleChoiceLibertiesB == 1 &&
          multipleChoiceLibertiesW == 2) ||
        (tsumegoFileLink == 29826 &&
          multipleChoiceLibertiesB == 1 &&
          multipleChoiceLibertiesW == 2)
      ) {
        multipleChoiceLibertiesB = 1;
        multipleChoiceLibertiesW = 1;
        multipleChoiceLibertiesB2 = 5;
        multipleChoiceLibertiesW2 = 5;
        if (tsumegoFileLink == 29824) {
          multipleChoiceLibertiesB2 = 7;
          multipleChoiceLibertiesW2 = 7;
        }
      } else if (
        tsumegoFileLink == 29827 &&
        multipleChoiceLibertiesB == 1 &&
        multipleChoiceLibertiesW == 2
      ) {
        multipleChoiceLibertiesB = 1;
        multipleChoiceLibertiesW = 1;
        multipleChoiceLibertiesB2 = 15;
        multipleChoiceLibertiesW2 = 15;
      }
      if (multipleChoiceLibertiesB < multipleChoiceLibertiesW) {
        correct = 1;
        $("#besogo-multipleChoice1").css("background-color", "#3ecf78");
        mText3 = multipleChoiceLibertiesW - multipleChoiceLibertiesB;
        if (mText3 == 1) mTextPlural = "";
        mText2 =
          "Black is dead. (" + mText3 + " move" + mTextPlural + " behind)";
      } else if (multipleChoiceLibertiesB > multipleChoiceLibertiesW) {
        correct = 2;
        $("#besogo-multipleChoice2").css("background-color", "#3ecf78");
        mText3 = multipleChoiceLibertiesB - multipleChoiceLibertiesW;
        if (mText3 == 1) mTextPlural = "";
        mText2 =
          "White is dead. (" + mText3 + " move" + mTextPlural + " behind)";
      } else {
        correct = 3;
        $("#besogo-multipleChoice3").css("background-color", "#3ecf78");
        mText2 = "The position is unsettled. (Whoever plays first wins.)";
      }
      if (multipleChoiceLibertiesW2 == 1) libPlural1 = "liberty";
      if (multipleChoiceLibertiesB2 == 1) libPlural2 = "liberty";
      mText =
        "Semeai Type 3: Eye vs no eye.<br>No Seki possible.<br> All inside liberties count for the group with the eye.<br>Black has " +
        multipleChoiceLibertiesW2 +
        " " +
        libPlural1 +
        ".<br> White has " +
        multipleChoiceLibertiesB2 +
        " " +
        libPlural2 +
        ".<br>" +
        moreText +
        mText2;
    } else if (multipleChoiceSemeaiType == 4) {
      let x3 = "";
      let bx1 = "";
      let wx1 = "";
      let blackLibertiesBefore = "";
      let whiteLibertiesBefore = "";
      let fav = "";
      let bwSum = "";
      let plural = "";
      let x1 = "";
      let x5 = "";
      let x2 = "";
      if (blackLiberties > whiteLiberties) {
        fav = "Black is the favorite with more exclusive liberties.<br>";
        blackLibertiesBefore = blackLiberties;
        whiteLibertiesBefore = whiteLiberties;
        whiteLiberties += parseInt(multipleChoiceInsideLiberties);
        bx1 = "favorite: " + blackLibertiesBefore + " exclusive liberties";
        wx1 =
          "underdog: " +
          whiteLibertiesBefore +
          " exclusive + " +
          multipleChoiceInsideLiberties +
          " inside";
      } else if (whiteLiberties > blackLiberties) {
        fav = "White is the favorite with more exclusive liberties.<br>";
        whiteLibertiesBefore = whiteLiberties;
        blackLibertiesBefore = blackLiberties;
        blackLiberties += parseInt(multipleChoiceInsideLiberties);
        bx1 =
          "underdog: " +
          blackLibertiesBefore +
          " exclusive + " +
          multipleChoiceInsideLiberties +
          " inside";
        wx1 = "favorite: " + whiteLibertiesBefore + " exclusive liberties";
      } else {
        fav = "Seki - same amount of liberties.<br>";
        correct = 4;
        $("#besogo-multipleChoice4").css("background-color", "#3ecf78");
      }
      if (whiteLiberties > blackLiberties) {
        bwSum = whiteLiberties - blackLiberties;
        plural = "";
        if (bwSum > 1) plural = "s";

        if (fav == "Black is the favorite with more exclusive liberties.<br>") {
          x1 =
            "Seki. (Black is " +
            bwSum +
            " move" +
            plural +
            " behind for killing)";
          correct = 4;
          $("#besogo-multipleChoice4").css("background-color", "#3ecf78");
        } else {
          x1 = "Black is dead. (" + bwSum + " move" + plural + " behind)";
          correct = 1;
          $("#besogo-multipleChoice1").css("background-color", "#3ecf78");
        }
      }
      if (whiteLiberties < blackLiberties) {
        bwSum = blackLiberties - whiteLiberties;
        plural = "";
        if (bwSum > 1) plural = "s";
        if (fav == "White is the favorite with more exclusive liberties.<br>") {
          x1 =
            "Seki. (White is " +
            bwSum +
            " move" +
            plural +
            " behind for killing)";
          correct = 4;
          $("#besogo-multipleChoice4").css("background-color", "#3ecf78");
        } else {
          x1 = "White is dead. (" + bwSum + " move" + plural + " behind)";
          correct = 2;
          $("#besogo-multipleChoice2").css("background-color", "#3ecf78");
        }
      }
      if (
        whiteLiberties == blackLiberties &&
        fav !== "Seki - same amount of liberties.<br>"
      ) {
        x1 = "Unsettled. (whoever plays first accomplishes the task)";
        correct = 3;
        $("#besogo-multipleChoice3").css("background-color", "#3ecf78");
      }

      if (fav !== "Seki - same amount of liberties.<br>") {
        x3 =
          "Black has " +
          blackLiberties +
          " liberties. (" +
          bx1 +
          ")<br> White has " +
          whiteLiberties +
          " liberties. (" +
          wx1 +
          ")<br>" +
          x1;
        if (multipleChoiceInsideLiberties == 0) {
          x3 =
            "Black has " +
            blackLiberties +
            " liberties. <br> White has " +
            whiteLiberties +
            " liberties. <br>" +
            x1;
        }
      } else {
        if (multipleChoiceInsideLiberties == 0) {
          x1 = "Unsettled. (whoever plays first wins)";
          x3 =
            "Black has " +
            blackLiberties +
            " liberties. <br> White has " +
            whiteLiberties +
            " liberties. <br>" +
            x1;
          correct = 3;
          $("#besogo-multipleChoice3").css("background-color", "#3ecf78");
          $("#besogo-multipleChoice4").css("background-color", "#e0747f");
        }
      }
      if (multipleChoiceInsideLiberties == 0) {
        x5 = "No Seki possible because there are no inside liberties.<br>";
        fav = "";
      } else {
        x5 =
          "Seki possible if there is one or more inside liberties.<br>The favorite's task is to kill.<br>The favorite counts his exclusive liberties.<br>The underdog's task is Seki.<br>The underdog counts his exclusive plus all inside liberties.<br>";
      }
      mText =
        "Semeai Type 4: Same sized big eyes.<br>" + x5 + " " + fav + " " + x3;
    } else if (multipleChoiceSemeaiType == 5) {
      let x3 = "";
      let bx1 = "";
      let wx1 = "";
      let blackLibertiesBefore = "";
      let whiteLibertiesBefore = "";
      let fav = "";
      let bwSum = "";
      let plural = "";
      let x1 = "";
      let x5 = "";
      let x2 = "";
      let sum1 = "";
      let sum2 = "";
      if (whiteLiberties > blackLiberties) {
        sum1 = whiteLiberties - blackLiberties;
        sum2 = "";
        if (sum1 > 1) sum2 = "s";
        x1 = "Black is dead. (" + sum1 + " move" + sum2 + " behind)";
        correct = 1;
        $("#besogo-multipleChoice1").css("background-color", "#3ecf78");
      }
      if (whiteLiberties < blackLiberties) {
        sum1 = blackLiberties - whiteLiberties;
        sum2 = "";
        if (sum1 > 1) sum2 = "s";
        x1 = "White is dead. (" + sum1 + " move" + sum2 + " behind)";
        correct = 2;
        $("#besogo-multipleChoice2").css("background-color", "#3ecf78");
      }
      if (whiteLiberties == blackLiberties) {
        x1 = "The position is unsettled. (whoever plays first wins)";
        correct = 3;
        $("#besogo-multipleChoice3").css("background-color", "#3ecf78");
      }
      mText =
        "Semeai Type 5: Big eye vs smaller eye.<br>No Seki possible.<br> All inside liberties count for the group with the bigger eye.<br> Black has " +
        blackLiberties +
        " liberties.<br> White has " +
        whiteLiberties +
        " liberties.<br>" +
        x1;
    } else if (multipleChoiceSemeaiType == 6) {
      let x3 = "";
      let bx1 = "";
      let wx1 = "";
      let blackLibertiesBefore = "";
      let whiteLibertiesBefore = "";
      let fav = "";
      let bwSum = "";
      let plural = "";
      let x1 = "";
      let x5 = "";
      let x2 = "";
      let sum1 = "";
      let sum2 = "";
      if (blackLiberties > whiteLiberties) {
        fav = "Black is the favorite with more exclusive liberties.<br>";
        blackLibertiesBefore = blackLiberties;
        whiteLibertiesBefore = whiteLiberties;
        whiteLiberties += parseInt(multipleChoiceInsideLiberties);
        bx1 = "favorite: " + blackLibertiesBefore + " exclusive liberties";
        wx1 =
          "underdog: " +
          whiteLibertiesBefore +
          " exclusive + " +
          multipleChoiceInsideLiberties +
          " inside";
      } else if (whiteLiberties > blackLiberties) {
        fav = "White is the favorite with more exclusive liberties.<br>";
        whiteLibertiesBefore = whiteLiberties;
        blackLibertiesBefore = blackLiberties;
        blackLiberties += parseInt(multipleChoiceInsideLiberties);
        bx1 =
          "underdog: " +
          blackLibertiesBefore +
          " exclusive + " +
          multipleChoiceInsideLiberties +
          " inside";
        wx1 = "favorite: " + whiteLibertiesBefore + " exclusive liberties";
      } else {
        fav = "Seki - same amount of liberties.<br>";
        correct = 4;
        $("#besogo-multipleChoice4").css("background-color", "#3ecf78");
      }
      if (whiteLiberties > blackLiberties) {
        bwSum = whiteLiberties - blackLiberties;
        plural = "";
        if (bwSum > 1) plural = "s";
        if (fav == "Black is the favorite with more exclusive liberties.<br>") {
          x1 =
            "Seki. (Black is " +
            bwSum +
            " move" +
            plural +
            " behind for killing)";
          correct = 4;
          $("#besogo-multipleChoice4").css("background-color", "#3ecf78");
        } else {
          x1 = "Black is dead. (" + bwSum + " move" + plural + " behind)";
          correct = 1;
          $("#besogo-multipleChoice1").css("background-color", "#3ecf78");
        }
      }
      if (whiteLiberties < blackLiberties) {
        bwSum = blackLiberties - whiteLiberties;
        plural = "";
        if (bwSum > 1) plural = "s";
        if (fav == "White is the favorite with more exclusive liberties.<br>") {
          x1 =
            "Seki. (White is " +
            bwSum +
            " move" +
            plural +
            " behind for killing)";
          correct = 4;
          $("#besogo-multipleChoice4").css("background-color", "#3ecf78");
        } else {
          x1 = "White is dead. (" + bwSum + " move" + plural + " behind)";
          correct = 2;
          $("#besogo-multipleChoice2").css("background-color", "#3ecf78");
        }
      }
      if (
        whiteLiberties == blackLiberties &&
        fav != "Seki - same amount of liberties.<br>"
      ) {
        x1 = "Unsettled. (whoever plays first accomplishes the task)";
        correct = 3;
        $("#besogo-multipleChoice3").css("background-color", "#3ecf78");
      }

      if (fav != "Seki - same amount of liberties.<br>") {
        x3 =
          "Black has " +
          blackLiberties +
          " liberties. (" +
          bx1 +
          ")<br> White has " +
          whiteLiberties +
          " liberties. (" +
          wx1 +
          ")<br>" +
          x1;
      }
      x5 =
        "Seki possible if there is one or more inside liberties.<br>The favorite's task is to kill.<br>The favorite counts his exclusive liberties.<br>The underdog's task is Seki.<br>The underdog counts his exclusive plus all inside liberties.<br>";
      mText =
        "Semeai Type 6: Small eye vs small eye.<br>" + x5 + " " + fav + x3;
    }
    if (num == correct) {
      displayResult("S");
      multipleChoiceEnabled = false;
      //$(".alertBanner").css("background-color", "rgb(18, 121, 59)");
      $(".alertBanner").addClass("alertBannerCorrect");
      $(".alertBanner").html('Correct!<span class="alertClose">x</span>');
    } else {
      displayResult("F");
      multipleChoiceEnabled = false;
      //$(".alertBanner").css("background-color", "linear-gradient(rgba(214,70,74,1), rgba(201,95,105,1))");
      $(".alertBanner").addClass("alertBannerIncorrect");
      $(".alertBanner").html('Incorrect<span class="alertClose">x</span>');
    }
    $("#multipleChoiceText").html(mText);
    $("#multipleChoiceAlerts").fadeIn(500);
    hasChosen = true;
  }
}
