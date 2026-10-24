<?php

use App\Utility\ViteManifest;

// Not part of the bundle, as it makes the board fill the whole page, so it gets the time of its last change for a version
$fillCss = '/besogo/css/besogo-fill.css?v=' . filemtime(WWW_ROOT . 'besogo/css/besogo-fill.css');
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>BesoGo</title>
    <link rel="icon" type="image/png" href="/besogo/icon32.png" sizes="32x32" />
    <link rel="icon" href="/besogo/icon.svg" sizes="any" type="image/svg+xml" />
    <?php echo ViteManifest::css('besogo-css'); ?>
    <link rel="stylesheet" type="text/css" href="<?php echo h($fillCss); ?>" />
    <link rel="stylesheet" type="text/css" href="/besogo/css/board-texture13.css" />
    <?php echo ViteManifest::legacyScript('besogo'); ?>
    <style>
      .besogo-container {
        background: #fff;
      }
    </style>
  </head>
  <body>
    <div id="target"></div>

    <script type="text/javascript">
      (function () {
        var options = { resize: "fill" },
          params = location.search.substring(1).split("&"); // Drop question mark

        for (let i = 0; i < params.length; i++) {
          let value = params[i].split("="); // Splits on all "=" symbols
          options[value.shift()] = value.join("="); // First "=" separates value from name, rest are part of value
        }
        if (options.setConnectionID !== undefined)
          options.editingOnlineTsumego = true;
        options.rootPath = "/besogo/";
        options.theme = "texture13";
        options.themeParameters = ["black34.png", "white34.png"];
        options.realstones = true;
        options.coord = "western";
        options.panels = "control+diff+comment+tool+tree+file";
        options.fullEditor = true; // editing will change correct status and allows the moves to be played automatically

        besogo.create(document.getElementById("target"), options);
      })();
    </script>
  </body>
</html>
