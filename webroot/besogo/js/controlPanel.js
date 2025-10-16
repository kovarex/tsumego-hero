besogo.makeControlPanel = function(container, editor)
{
  'use strict';
  var leftElements = [], // SVG elements for previous node buttons
      rightElements = [], // SVG elements for next node buttons
      siblingElements = [], // SVG elements for sibling buttons
      variantStyleButton, // Button for changing variant style
      childVariantElement; // SVG element for child style variants

  drawNavButtons();
  //drawStyleButtons();

  editor.addListener(update);
  update({ navChange: true, variantStyle: editor.getVariantStyle() }); // Initialize
  
  // Callback for variant style and nav state changes
  function update(msg)
  {
    var current;

    if (msg.navChange || msg.treeChange) // Update the navigation buttons
    {
      current = editor.getCurrent();
      if (current.parent)
      {
        arraySetColor(leftElements, 'black');
        if (current.parent.children.length > 1) // Has siblings
          arraySetColor(siblingElements, 'black');
        else
          arraySetColor(siblingElements, besogo.GREY);
      }
      else
      {
        arraySetColor(leftElements, besogo.GREY);
        arraySetColor(siblingElements, besogo.GREY);
      }
      if (current.children.length)
        arraySetColor(rightElements, 'black');
      else
        arraySetColor(rightElements, besogo.GREY);
    }

    function arraySetColor(list, color) // Changes fill color of list of svg elements
    {
      for (let i = 0; i < list.length; i++)
        list[i].setAttribute('fill', color);
    }
  }

  function drawNavButtons()
  {
    leftElements.push(makeNavButton('First node',
                                    '5,10 5,90 25,90 25,50 95,90 95,10 25,50 25,10',
                                    function() { editor.prevNode(-1); }));
    leftElements.push(makeNavButton('Previous node',
                                    '85,10 85,90 15,50',
                                    function() { editor.prevNode(1); }));
    rightElements.push(makeNavButton('Next node',
                                     '15,10 15,90 85,50',
                                     function() { if(!besogo.controlButtonLock) editor.nextNode(1); }));
    rightElements.push(makeNavButton('Last node',
                                     '95,10 95,90 75,90 75,50 5,90 5,10 75,50 75,10',
                                     function() { if(!besogo.controlButtonLock) editor.nextNode(-1); }));
    function makeNavButton(tooltip, pointString, action) // Creates a navigation button
    {
      var button = document.createElement('button'),
          svg = makeButtonContainer(),
          element = besogo.svgEl("polygon",
                                 {
                                     points: pointString,
                                     stroke: 'none',
                                     fill: 'black'
                                 });

      button.title = tooltip;
      button.onclick = action;
      button.appendChild(svg);
      svg.appendChild(element);
      container.appendChild(button);

      return element;
    }
  }

  function drawStyleButtons()
  {
    var svg, element, coordStyleButton;

    svg = makeButtonContainer();
    element = besogo.svgEl("path", {
        d: 'm75,25h-50l50,50',
        stroke: 'black',
        "stroke-width": 5,
        fill: 'none'
    });
    svg.appendChild(element);
    childVariantElement = besogo.svgEl('circle', {
        cx: 25,
        cy: 25,
        r: 20,
        stroke: 'none'
    });
    coordStyleButton = document.createElement('button');
    coordStyleButton.onclick = function() { editor.toggleCoordStyle(); };
    coordStyleButton.title = 'Toggle coordinates';
    container.appendChild(coordStyleButton);
    svg = makeButtonContainer();
    coordStyleButton.appendChild(svg);
    svg.appendChild(besogo.svgLabel(50, 50, 'black', '四4'));
  }

  // Makes an SVG container for the button graphics
  function makeButtonContainer()
  {
    return besogo.svgEl('svg',
                        {
                          width: '100%',
                          height: '100%',
                          viewBox: "0 0 100 100"
                        });
  }
};
