function darkAndLight() {
  if (light) {
    //make dark
    document.cookie = "lightDark=dark;path=/";
    document.cookie = "lightDark=dark;path=/sets/view";
    document.cookie = "lightDark=dark;path=/tsumegos/play";
    document.cookie = "lightDark=dark;path=/users";
    document.cookie = "lightDark=dark;path=/users/view";
    $("#darkButtonImage").attr("src", "/img/dark-icon1.png");
    $("#darkButtonImage2").attr("src", "/img/dark-icon1.png");
    $("#darkButtonImage3").attr("src", "/img/dark-icon1.png");
    document.cookie = "lightDark=dark";

    $(".new1, .title4, .new1, #playTitleA").css("color", "#f0f0f0");
    $(".modify-description").css("color", "#f0f0f0");
    $("body").css("background", "linear-gradient(#2a2a2a, #111)");
    $("body").css("background-attachment", "fixed");
    $("body, font, .filterLabel1, .timeTableColor11").css("color", "#f0f0f0");
    $(".whitebox2, h2, .imp").css("background-color", "#2a2a2a");
    $(".new1").css("background", "linear-gradient(#3c3c3c, #2a2a2a)");
    $(".new1 a").css("color", "#f0f0f0");
    $(".title4").css("background", "linear-gradient(#737373, #4a4a4a)");
    $(".besogo-container").css("background-color", "#2a2a2a");
    $("li.setBlank").css("background-color", "#2a2a2a");
    $("li.setBlank").css("background-image", "none");
    $("li.setBlank").css("border-top", "none");
    $("li.setBlank").css("border-right", "none");
    $("li.setBlank").css("border-bottom", "none");
    $("li.setBlank").css("border-left", "none");
    $(".sandboxComment").css("background", "linear-gradient(#666, #424242)");
    $(".sandboxComment").css("box-shadow", "2px 2px #0f0f0f");
    $(".commentBox2, .commentAnswer, .adminText").css("color", "#70ddff");
    $("li.setV2").css("border", "1px solid #f0f0f0");
    $(".sandboxTable2time").css("color", "#b9b9b9");
    $(".besogo-container").css("background", "#2a2a2a");

    $(
      ".highscoreTable .color12, #sandbox, .userstatsstbale a, .admin-panel"
    ).css("color", "#f0f0f0");
    $(
      "#show, #show2, #commentPosition, #show3, .selectable-text, .admin-panel #show2, .admin-panel #show3, .admin-panel #show4, .admin-panel #show5, .admin-panel font"
    ).css("color", "#f0f0f0");
    $(".btn").css("color", "#8c8c8c");
    $(".highscoreTable .color12").css("background-color", "#404040");
    $(".btn.active, .btn:active").css("background-color", "#b5b5b5");
    $(".btn.active, .btn:active").css("color", "#323232");
    $(".btn:active:hover").css("background-color", "#f0f0f0");
    $(".btn:active:hover").css("color", "#323232");
    $("li.setS1,li.setC1").css("background-color", "#0a4");
    $("li.setF1,li.setX1").css("background-color", "#dd3a4b");
    $("li.setW1").css("background-color", "#00aeab");
    $("li.setV1").css("background-color", "#0088e3");
    $("li.setN1").css("background-color", "#444");
    $("li.setG1").css("background-color", "#b0bd00");
    $(
      "li.setV2,li.set2,li.setS2,li.setW2,li.setC2,li.setF2,li.setX2,li.setG2"
    ).css("border", "2px solid #f0f0f0");
    $(".setViewAccuracy").css("color", "#c234ff");
    $(".setViewTime").css("color", "#d55e29");
    $(".admin-panel, .selectable-text, .admin-panel font").css(
      "color",
      "#717171"
    );
    $("#sandbox").css("background-color", "#5b5d60");
    $(".timeTableColor11").css("background", "linear-gradient(#666, #424242)");
    $(".highscoreTable").css("color", "black");
    $(
      ".highscoreTable .color1,.highscoreTable .color2,.highscoreTable .color3,.highscoreTable .color4,.highscoreTable .color9d,.highscoreTable .color8d,.highscoreTable .color7d"
    ).css("background-color", "#2a2a2a");
    $(
      ".highscoreTable .color6d,.highscoreTable .color5d,.highscoreTable .color4d,.highscoreTable .color3d,.highscoreTable .color2d,.highscoreTable .color1d,.highscoreTable .color1k"
    ).css("background-color", "#2a2a2a");
    $(
      ".highscoreTable .color2k,.highscoreTable .color3k,.highscoreTable .color4k,.highscoreTable .color5k,.highscoreTable .color6k,.highscoreTable .color7k,.highscoreTable .color8k"
    ).css("background-color", "#2a2a2a");
    $(
      ".highscoreTable .color9k,.highscoreTable .color10k,.highscoreTable .color11k,.highscoreTable .color12k"
    ).css("background-color", "#2a2a2a");
    $(".admin-panel").css("background", "linear-gradient(#666, #424242)");
    $(".versionColor a").css("color", "#0066cc");
    $(".sandboxTable2 a, .statsTable a").css("color", "#f0f0f0");

    $(".achievemetProfileLink a, .achievemetIndexLink a").css(
      "color",
      "#f0f0f0"
    );
    $(
      "#show, #commentPosition, #show3, .selectable-text, .admin-panel #show2, .admin-panel #show3, .admin-panel #show4, .admin-panel #show5, .admin-panel font"
    ).css("color", "#f0f0f0");
    $(
      "#status2 font font, .titleDescription1 font, .modify-description, .statsTable font"
    ).css("color", "grey");
    $(".achievement1, .achievement2, .achievementSmall").css(
      "background",
      "linear-gradient(#dfdfdf, #979797)"
    );
    $(".homeCenter2, .title6").css("background-color", "#2a2a2a");
    $(".signin").css("background-color", "#515151");
    $(".homeCenter2, .title6").css("color", "#f0f0f0");
    $("#achievementWrapper font").css("color", "#717171");
    $(
      ".achievementColorBlack2,.achievementColorPurple2,.achievementColorGreen2,.achievementColorOrange2,.achievementColorBlue2,.achievementColorDarkblue2,.achievementColorRed2,.achievementColorRating2,.achievementColorTime2"
    ).css("color", "#f0f0f0");
    $(".admin-panel h1").css("color", "#f0f0f0");
    $("#uotdStartPage").css("background-color", "none");
    $("#uotdStartPage").css("background-color", "transparent");
    $(".new1 a, .new1 b, .scheduleTsumego").css("color", "#f0f0f0");
    $("#levelBarDisplay1").css("accent-color", "#56bb2c");
    $("#levelBarDisplay1text").css("color", "#56bb2c");
    $("#levelBarDisplay2").css("accent-color", "#c240f7");
    $("#levelBarDisplay2text").css("color", "#c240f7");
    $(".h1profile h1, .profileTable2 a").css("color", "#fff");
    $(".set-search").css("color", "#fff");
    $(".set-search, #set-size-input").css("background", "#222222");
  } else {
    //make light
    document.cookie = "lightDark=light;path=/";
    document.cookie = "lightDark=light;path=/sets/view";
    document.cookie = "lightDark=light;path=/tsumegos/play";
    document.cookie = "lightDark=light;path=/users";
    document.cookie = "lightDark=light;path=/users/view";
    $("#darkButtonImage").attr("src", "/img/light-icon1x.png");
    $("#darkButtonImage2").attr("src", "/img/light-icon1x.png");
    $("#darkButtonImage3").attr("src", "/img/light-icon1x.png");

    $(".new1, .title4, .new1, #playTitleA").css("color", "black");
    $(".modify-description").css("color", "black");
    $("body").css("background", "linear-gradient(#282828, #424141)");
    $("body").css("background-attachment", "fixed");
    $("body, font, .filterLabel1, .timeTableColor11").css("color", "black");
    $(".whitebox2, h2, .imp").css("background-color", "#fff");
    $(".new1").css("background", "linear-gradient(#fff, #f2f2f2)");
    $(".title4").css("background", "linear-gradient(#e1e1e1, #bdbdbd)");
    $(".besogo-container").css("background-color", "#fff");
    $("li.setBlank").css("background-color", "#fff");
    $("li.setBlank").css("background-image", "none");
    $("li.setBlank").css("border-top", "none");
    $("li.setBlank").css("border-right", "none");
    $("li.setBlank").css("border-bottom", "none");
    $("li.setBlank").css("border-left", "none");
    $(".sandboxComment").css("background", "#ddd");
    $(".sandboxComment").css("box-shadow", "2px 2px #999");
    $(".commentBox2, .commentAnswer").css("color", "#113cd4");
    $(".adminText").css("color", "#000");
    $("li.setV2").css("border", "1px solid black");
    $(".sandboxTable2time").css("color", "#575757");
    $(".besogo-container").css("background", "#fff");

    $(
      ".highscoreTable .color12, #sandbox, .userstatsstbale a, .admin-panel"
    ).css("color", "black");
    $(
      "#show, #show2, #commentPosition, #show3, .selectable-text, .admin-panel #show2, .admin-panel #show3, .admin-panel #show4, .admin-panel #show5, .admin-panel font"
    ).css("color", "#717171");
    $(".btn").css("color", "black");
    $(".highscoreTable .color12").css("background-color", "#ddd");
    $(".btn.active, .btn:active").css("background-color", "#323232");
    $(".btn.active, .btn:active").css("color", "#fff");
    $("li.setS1,li.setC1").css("background-color", "#3ecf78");
    $("li.setF1,li.setX1").css("background-color", "#c63f4d");
    $("li.setW1").css("background-color", "34cfcc");
    $("li.setV1").css("background-color", "2d98e0");
    $("li.setN1").css("background-color", "7f8287");
    $("li.setG1").css("background-color", "#d7e062");
    $(
      "li.setV2,li.set2,li.setS2,li.setW2,li.setC2,li.setF2,li.setX2,li.setG2"
    ).css("border", "1px solid black");
    $(".setViewAccuracy").css("color", "#722394");
    $(".setViewTime").css("color", "#b34717");
    $(".admin-panel, .selectable-text, .admin-panel font").css(
      "color",
      "#717171"
    );
    $("#sandbox").css("background-color", "#fff");
    $(".timeTableColor11").css("background", "linear-gradient(#ddd, #999)");
    $(".highscoreTable").css("color", "black");
    $(
      ".highscoreTable .color1,.highscoreTable .color2,.highscoreTable .color3,.highscoreTable .color4,.highscoreTable .color9d,.highscoreTable .color8d,.highscoreTable .color7d"
    ).css("background-color", "#fff");
    $(
      ".highscoreTable .color6d,.highscoreTable .color5d,.highscoreTable .color4d,.highscoreTable .color3d,.highscoreTable .color2d,.highscoreTable .color1d,.highscoreTable .color1k"
    ).css("background-color", "#fff");
    $(
      ".highscoreTable .color2k,.highscoreTable .color3k,.highscoreTable .color4k,.highscoreTable .color5k,.highscoreTable .color6k,.highscoreTable .color7k,.highscoreTable .color8k"
    ).css("background-color", "#fff");
    $(
      ".highscoreTable .color9k,.highscoreTable .color10k,.highscoreTable .color11k,.highscoreTable .color12k"
    ).css("background-color", "#fff");
    $(".admin-panel").css("background", "#eaeaea");
    $(".versionColor a").css("color", "#0066cc");
    $(".achievemetProfileLink a, .achievemetIndexLink a").css(
      "color",
      "#0066cc"
    );
    $(
      "#show, #commentPosition, #show3, .selectable-text, .admin-panel #show2, .admin-panel #show3, .admin-panel #show4, .admin-panel #show5, .admin-panel font"
    ).css("color", "#717171");
    $("#status2 font font, .titleDescription1 font, .statsTable font").css(
      "color",
      "#717171"
    );
    $(".achievement1, .achievement2, .achievementSmall").css(
      "background",
      "linear-gradient(0deg, rgb(0% 0% 0% / 0.3) 0%, rgb(44.897% 44.897% 44.897% / 0.4232177734375) 6.25%, rgb(59.989% 59.989% 59.989% / 0.5310546875) 12.5%, rgb(69.948% 69.948% 69.948% / 0.6245361328125) 18.75%, rgb(77.216% 77.216% 77.216% / 0.7046874999999999) 25%, rgb(82.734% 82.734% 82.734% / 0.7725341796874999) 31.25%, rgb(86.99% 86.991% 86.99% / 0.8291015625) 37.5%, rgb(90.281% 90.281% 90.281% / 0.8754150390625) 43.75%, rgb(92.807% 92.807% 92.807% / 0.9124999999999999) 50%, rgb(94.712% 94.712% 94.712% / 0.9413818359374999) 56.25%, rgb(96.112% 96.112% 96.112% / 0.9630859375) 62.5%, rgb(97.098% 97.098% 97.098% / 0.9786376953125) 68.75%, rgb(97.752% 97.752% 97.752% / 0.9890625) 75%, rgb(98.145% 98.145% 98.145% / 0.9953857421874999) 81.25%, rgb(98.347% 98.347% 98.347% / 0.9986328124999999) 87.5%, rgb(98.421% 98.421% 98.421% / 0.9998291015625) 93.75%, rgb(98.431% 98.431% 98.431%) 100% )"
    );
    $(".homeCenter2, .title6").css("background-color", "#fff");
    $(".homeCenter2, .title6").css("color", "#000");
    $("#xpDisplay").css("color", "#000");
    $(".sandboxTable2 a, .statsTable a").css("color", "#0066cc");
    $(".new1 a").css("color", "#0066cc");
    $(".new-button").css("color", "#fff");
    $(".signin").css("background-color", "#fff");
    $("#achievementWrapper font").css("color", "gray");
    $(
      ".achievementColorBlack2,.achievementColorPurple2,.achievementColorGreen2,.achievementColorOrange2,.achievementColorBlue2,.achievementColorDarkblue2,.achievementColorRed2,.achievementColorRating2,.achievementColorTime2"
    ).css("color", "black");
    $(".admin-panel h1").css("color", "black");
    $("#timeButton, #ratioButton, #numbersButton").css("color", "#74d13f");
    $("#uotdStartPage").css("background-color", "none");
    $("#uotdStartPage").css("background-color", "transparent");
    $(".new1 a, .new1 b, .scheduleTsumego").css("color", "black");
    $(
      ".setN1 a, .setV1 a, .setS1 a, .setC1 a, .setF1 a, .setX1 a, .setW1 a, .setG1 a, .setA1 a"
    ).css("color", "white");
    $("#levelBarDisplay1").css("accent-color", "#4d8536");
    $("#levelBarDisplay1text").css("color", "#4d8536");
    $("#levelBarDisplay2").css("accent-color", "#8130a2");
    $("#levelBarDisplay2text").css("color", "#8130a2");
    $(".h1profile h1, .profileTable2 a").css("color", "black");
    $(".set-search").css("color", "#fff");
    $(".set-search, #set-size-input").css("background", "#555555");
  }
  light = !light;
}

function levelBarChange(num) {
  if (num == 1) {
    $(".account-bar-user-class").removeAttr("id");
    $(".account-bar-user-class").attr("id", "account-bar-user");
    $("#xp-bar-fill").css("width", barPercent1 + "%");
    $("#xp-bar-fill").removeAttr("class");
    $("#xp-bar-fill").attr("class", "xp-bar-fill-c1");
    $("#account-bar-xp").text(barLevelNum);
    $("#modeSelector").removeAttr("class");
    $("#modeSelector").attr("class", "modeSelector2");
    modeSelector = 2;
    levelBar = 1;
    levelToRatingHover = num;
    document.cookie = "levelBar=1;path=/;SameSite=none;Secure=false";
    document.cookie = "levelBar=1;path=/sets;SameSite=none;Secure=false";
    document.cookie = "levelBar=1;path=/sets/view;SameSite=none;Secure=false";
    document.cookie =
      "levelBar=1;path=/tsumegos/play;SameSite=none;Secure=false";
    document.cookie = "levelBar=1;path=/users;SameSite=none;Secure=false";
    document.cookie = "levelBar=1;path=/users/view;SameSite=none;Secure=false";
  } else {
    $(".account-bar-user-class").removeAttr("id");
    $(".account-bar-user-class").attr("id", "account-bar-user2");
    $("#xp-bar-fill").css("width", barPercent2 + "%");
    $("#xp-bar-fill").removeAttr("class");
    $("#xp-bar-fill").attr("class", "xp-bar-fill-c2");
    $("#account-bar-xp").text(barRatingNum);
    $("#modeSelector").removeAttr("class");
    $("#modeSelector").attr("class", "modeSelector1");
    modeSelector = 1;
    levelBar = 2;
    levelToRatingHover = num;
    document.cookie = "levelBar=2;path=/;SameSite=none;Secure=false";
    document.cookie = "levelBar=2;path=/sets;SameSite=none;Secure=false";
    document.cookie = "levelBar=2;path=/sets/view;SameSite=none;Secure=false";
    document.cookie =
      "levelBar=2;path=/tsumegos/play;SameSite=none;Secure=false";
    document.cookie = "levelBar=2;path=/users;SameSite=none;Secure=false";
    document.cookie = "levelBar=2;path=/users/view;SameSite=none;Secure=false";
  }
}
