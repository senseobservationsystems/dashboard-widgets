/**
 * @file graph.js
 * 
 * @brief 
 * The Graph is an interactive visualization chart to draw (measurement) data 
 * in time. You can freely move and zoom in the graph by dragging and scrolling 
 * in the window. The time scale on the axis is adjusted automatically, and 
 * supports scales ranging from milliseconds to years.
 *
 * Graph is part of the CHAP Links library.
 * 
 * Graph is tested on Firefox 3.6, Safari 5.0, Chrome 6.0, Opera 10.6, and
 * Internet Explorer 6+.
 *
 * @license
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy 
 * of the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 *
 * Copyright © 2010-2012 Almende B.V.
 *
 * @author 	Jos de Jong, <jos@almende.org>
 * @date    2012-03-19
 */


/*


TODO
  when subsampling, always take the same start - else it looks like the data is jumping
  implement option backgroundColor like all other Google Visualizations (and see Links.Network)
  css: make two style groups: haxis and vaxis
  the Graph must not crash when feeding an empty table or with wrong column types

  add an option to select/deselect all functions in the legend
  add possibilty to have a horizontal axis with numbers instead of dates
  add possibility to see the actual value where you are hovering about with the mouse
  recalculate min and max scale after each zoom action too?
  add possiblitiy to zoom vertically

  add a line style circle? square? triange? custom color for the circle or dot?
  enable highlighting one of the graphs (select one graph)

BUGS
  when zooming in on on line segment, it is not drawn correctly (disappears)
  IE: problems when zooming into the millisecond range (round off errors somehow?)
  IE:
    unstable for >10000 datapoints. Maybe due to conversion to VML by excanvas? Or a bug in my code?

  Safari: 
    sometimes, the canvas is not cleared completely (which is fixed after another redraw)
      --> test if fixed now (by clearing before resizing)

  the text on the vertical axis has round off errors when dealing with very small scale (1e-6) 

Documentation
  http://codingforums.com/showthread.php?t=99027
  http://bytes.com/topic/javascript/answers/160118-creating-xhtml-iframe
	http://www.kevlindev.com/tutorials/basics/shapes/js_dom/index.htm
  http://adomas.org/javascript-mouse-wheel/
  http://www.brainjar.com/dhtml/drag/
  http://dev-tips.com/demo/css3_circles.html

*/



/**
 * Declare a unique namespace for CHAP's Common Hybrid Visualisation Library,
 * "links"
 */ 
if (typeof links === 'undefined') {
  links = {};
  // important: do not use var, as "var links = {};" will overwrite 
  //            the existing links variable value with undefined in IE8, IE7.  
}


/**
 * Ensure the variable google exists
 */ 
if (typeof google === 'undefined') {
  google = undefined; 
  // important: do not use var, as "var google = undefined;" will overwrite 
  //            the existing google variable value with undefined in IE8, IE7.
}


/**
 * @class Graph
 * The Graph is a visualization Graphs on a time line 
 * 
 * Graph is developed in javascript as a Google Visualization Chart.
 * 
 * @param {dom_element} container   The DOM element in which the Graph will
 *                                  be created. Normally a div element.
 */
links.Graph = function(container) {
  // create variables and set default values
  this.containerElement = container;
  this.width = "100%";
  this.height = "300px";
  this.start = null;
  this.end = null;
  this.autoDataStep = true;
  this._moveable = true;
  this._zoomable = true;

  this.redrawWhileMoving = true;

  this.legend = undefined;
  this.line = new Object();  // object default style for all lines
  this.lines = new Array();  // array containing specific line styles, colors, etc.
  /*
  this.defaultColors = ["red", "green", "blue", "magenta", 
                        "purple", "orange", "lime", "darkgreen", "darkblue", 
                        "turquoise", "gray", "darkgray", "darkred",  "chocolate", 
                        "plum", "#808000"];
  */
  this.defaultColors = ["red", "#008000", "#0000FF", "#FF00FF", 
                        "#800080", "#FFA500", "#00FF00", "#006400", "#00008B", 
                        "#40E0D0", "#808080", "#A9A9A9", "#8B0000",  "#D2691E", 
                        "#DDA0DD", "#808000"];
                                            
  // The axis is drawn from -axisMargin to frame.width+axisMargin. When making
  // axisMargin smaller, drawing the axis is faster as the axis is shorter.
  // this makes scrolling faster. But when moving the Graph, the Graph 
  // needs to be redrawn more often, which makes movement less smooth.
  //this.axisMargin = document.body.clientWidth; // in pixels
  this.axisMargin = 800;  // in pixels
  
  this.mainPadding = 8; // pixels. Todo: make option?

  // create a default, empty array
  this.data = [];

  // create a frame and canvas
  this._create();
}


/** 
 * Main drawing logic. This is the function that needs to be called 
 * in the html page, to draw the Graph.
 * 
 * A data table with the events must be provided, and an options table. 
 * Available options:
 *  - width        Width for the Graph in pixels or percentage.
 *  - height       Height for the Graph in pixels or percentage.
 *  - start        A Date object with the start date of the visible range
 *  - end          A Date object with the end date of the visible range
 * TODO: describe all options
 *   
 *  All options are optional.
 * 
 * @param {DataTable or Array} data  The data containing the events for the Graph.
 *                                   Object DataTable is defined in 
 *                                   google.visualization.DataTable.
 * @param {name/value map} options A name/value map containing settings for the
 *                                 Graph.
 */
links.Graph.prototype.draw = function(data, options) {
  this._readData(data);

  if (options != undefined) {
    // retrieve parameter values
    if (options.width != undefined)         this.width = options.width; 
    if (options.height != undefined)        this.height = options.height; 

    if (options.start != undefined)         this.start = options.start;
    if (options.end != undefined)           this.end = options.end;
    if (options.scale != undefined)         this.scale = options.scale;
    if (options.step != undefined)          this.step = options.step;
    if (options.autoDataStep != undefined)  this.autoDataStep = options.autoDataStep;
    
    if (options._moveable != undefined)     this._moveable = options._moveable;
    if (options._zoomable != undefined)     this._zoomable = options._zoomable;

    if (options.line != undefined)          this.line = options.line;
    if (options.lines != undefined)         this.lines = options.lines;
    
    if (options.vStart != undefined)        this.vStart = options.vStart;
    if (options.vEnd != undefined)          this.vEnd = options.vEnd;
    if (options.vStep != undefined)         this.vStepSize = options.vStep;
    if (options.vPrettyStep != undefined)   this.vPrettyStep = options.vPrettyStep;

    if (options.legend !== undefined)       this.legend = options.legend;  // can contain legend.width

    // TODO: add options to set the horizontal and vertical range
  }

  // apply size and time range
  var redrawNow = false;
  this.setSize(this.width, this.height);
  this.setVisibleChartRange(this.start, this.end, redrawNow);
  
  if (this.scale && this.step) {
    this.hStep.setScale(this.scale, this.step);
  }
  
  // draw the Graph
  this.redraw();

  this.trigger('ready');
}

/**
 * fire an event
 * @param {String} event   The name of an event, for example "rangechange" or "edit"
 * @param {Object} params  Optional parameters
 */
links.Graph.prototype.trigger = function (event, params) {
  // fire event via the links event bus
  links.events.trigger(this, event, params);    

  // fire the ready event
  if (google && google.visualization && google.visualization.events) {
    google.visualization.events.trigger(this, event, params);    
  }
}


/**
 * Read data into the graph
 */ 
links.Graph.prototype._readData = function(data) {
  if (google && google.visualization && google.visualization.DataTable &&
      data instanceof google.visualization.DataTable) {
    // read a Google DataTable
    this.data = [];
    
    for (var col = 1, cols = data.getNumberOfColumns(); col < cols; col++) {
      var dataset = [];
      for (var row = 0, rows = data.getNumberOfRows(); row < rows; row++) {
        dataset.push({"date" : data.getValue(row, 0), "value" : data.getValue(row, col)} );
      }

      var graph = {
        "label": data.getColumnLabel(col),
        "dataRange": undefined,
        "rowRange": undefined,
        "visibleRowRange": undefined,
        "data": dataset
      }
      this.data.push(graph);
      
      // TODO: sort by date, and remove redundant null values
    }
  }
  else {
    // parse Javascipt array
    this.data = data || [];
  }
  
  // calculate date and value ranges
  for (var i = 0, len = this.data.length; i < len; i++) {
    var graph = this.data[i];
    
    graph.dataRange = this._getDataRange(graph.data);
    graph.rowRange = this._getRowRange(graph.data);
  }
}


/** 
 * @class StepDate
 * The class StepDate is an iterator for dates. You provide a start date and an 
 * end date. The class itself determines the best scale (step size) based on the  
 * provided start Date, end Date, and minimumStep.
 * 
 * If minimumStep is provided, the step size is chosen as close as possible
 * to the minimumStep but larger than minimumStep. If minimumStep is not
 * provided, the scale is set to 1 DAY.
 * The minimumStep should correspond with the onscreen size of about 6 characters
 * 
 * Alternatively, you can set a scale by hand.
 * After creation, you can initialize the class by executing start(). Then you
 * can iterate from the start date to the end date via next(). You can check if
 * the end date is reached with the function end(). After each step, you can 
 * retrieve the current date via get().
 * The class step has scales ranging from milliseconds, seconds, minutes, hours, 
 * days, to years.
 * 
 * Version: 0.9
 * 
 * @param {Date} start        The start date, for example new Date(2010, 9, 21)
 *                            or new Date(2010, 9,21,23,45,00)
 * @param {Date} end          The end date
 * @param {int}  minimumStep  Optional. Minimum step size in milliseconds
 */
links.Graph.StepDate = function(start, end, minimumStep) {

  // variables
  this.current = new Date();
  this._start = new Date();
  this._end = new Date();
  
  this.autoScale  = true;
  this.scale = links.Graph.StepDate.SCALE.DAY;
  this.step = 1;

  // initialize the range
  this._setRange(start, end, minimumStep);
}

/// enum scale
links.Graph.StepDate.SCALE = { MILLISECOND : 1, 
                         SECOND : 2, 
                         MINUTE : 3, 
                         HOUR : 4, 
                         DAY : 5, 
                         MONTH : 6, 
                         YEAR : 7};


/**
 * Set a new range
 * If minimumStep is provided, the step size is chosen as close as possible
 * to the minimumStep but larger than minimumStep. If minimumStep is not
 * provided, the scale is set to 1 DAY.
 * The minimumStep should correspond with the onscreen size of about 6 characters
 * @param {Date} start        The start date and time.
 * @param {Date} end          The end date and time.
 * @param {int}  minimumStep  Optional. Minimum step size in milliseconds
 */ 
links.Graph.StepDate.prototype._setRange = function(start, end, minimumStep) {
  if (isNaN(start) || isNaN(end)) {
    // TODO: throw error?
    return;
  }

  this._start      = (start != undefined)  ? new Date(start) : new Date();
  this._end        = (end != undefined)    ? new Date(end) : new Date();

  if (this.autoScale) {
    this._setMinimumStep(minimumStep);
  }
}

/**
 * Set the step iterator to the start date.
 */ 
links.Graph.StepDate.prototype.start = function() {
  this.current = new Date(this._start);
  this._roundToMinor();
}

/**
 * Round the current date to the first minor date value
 * This must be executed once when the current date is set to start Date
 */ 
links.Graph.StepDate.prototype._roundToMinor = function() {
  // round to floor
  // IMPORTANT: we have no breaks in this switch! (this is no bug)
  switch (this.scale) {
    case links.Graph.StepDate.SCALE.YEAR:
      this.current.setFullYear(this.step * Math.floor(this.current.getFullYear() / this.step));
      this.current.setMonth(0);
    case links.Graph.StepDate.SCALE.MONTH:        this.current.setDate(1);
    case links.Graph.StepDate.SCALE.DAY:          this.current.setHours(0);
    case links.Graph.StepDate.SCALE.HOUR:         this.current.setMinutes(0);
    case links.Graph.StepDate.SCALE.MINUTE:       this.current.setSeconds(0);
    case links.Graph.StepDate.SCALE.SECOND:       this.current.setMilliseconds(0);
    //case links.Graph.StepDate.SCALE.MILLISECOND: // nothing to do for milliseconds
  }

  if (this.step != 1) {
    // round down to the first minor value that is a multiple of the current step size
    switch (this.scale) {
      case links.Graph.StepDate.SCALE.MILLISECOND:  this.current.setMilliseconds(this.current.getMilliseconds() - this.current.getMilliseconds() % this.step);  break;
      case links.Graph.StepDate.SCALE.SECOND:       this.current.setSeconds(this.current.getSeconds() - this.current.getSeconds() % this.step);  break;
      case links.Graph.StepDate.SCALE.MINUTE:       this.current.setMinutes(this.current.getMinutes() - this.current.getMinutes() % this.step);  break;
      case links.Graph.StepDate.SCALE.HOUR:         this.current.setHours(this.current.getHours() - this.current.getHours() % this.step);  break;
      case links.Graph.StepDate.SCALE.DAY:          this.current.setDate((this.current.getDate()-1) - (this.current.getDate()-1) % this.step + 1);  break;
      case links.Graph.StepDate.SCALE.MONTH:        this.current.setMonth(this.current.getMonth() - this.current.getMonth() % this.step);  break;
      case links.Graph.StepDate.SCALE.YEAR:         this.current.setFullYear(this.current.getFullYear() - this.current.getFullYear() % this.step); break;
      default:                      break;
    }
  }
}

/**
 * Check if the end date is reached
 * @return {boolean}  true if the current date has passed the end date
 */ 
links.Graph.StepDate.prototype.end = function () {
  return (this.current > this._end);
}

/** 
 * Do the next step
 */ 
links.Graph.StepDate.prototype.next = function() {
  var prev = this.getCurrent().getTime();
  
  // Two cases, needed to prevent issues with switching daylight savings 
  // (end of March and end of October)
  if (this.current.getMonth() < 6)   {
    switch (this.scale)
    {
      case links.Graph.StepDate.SCALE.MILLISECOND:  

      this.current = new Date(this.current.getTime() + this.step); break;
      case links.Graph.StepDate.SCALE.SECOND:       this.current = new Date(this.current.getTime() + this.step * 1000); break;
      case links.Graph.StepDate.SCALE.MINUTE:       this.current = new Date(this.current.getTime() + this.step * 1000 * 60); break;
      case links.Graph.StepDate.SCALE.HOUR:         
        this.current = new Date(this.current.getTime() + this.step * 1000 * 60 * 60); 
        // in case of skipping an hour for daylight savings, adjust the hour again (else you get: 0h 5h 9h ... instead of 0h 4h 8h ...)
        var h = this.current.getHours();
        this.current.setHours(h - (h % this.step));
        break;
      case links.Graph.StepDate.SCALE.DAY:          this.current.setDate(this.current.getDate() + this.step); break;
      case links.Graph.StepDate.SCALE.MONTH:        this.current.setMonth(this.current.getMonth() + this.step); break;
      case links.Graph.StepDate.SCALE.YEAR:         this.current.setFullYear(this.current.getFullYear() + this.step); break;
      default:                      break;
    }
  }
  else {
    switch (this.scale)
    {
      case links.Graph.StepDate.SCALE.MILLISECOND:  

      this.current = new Date(this.current.getTime() + this.step); break;
      case links.Graph.StepDate.SCALE.SECOND:       this.current.setSeconds(this.current.getSeconds() + this.step); break;
      case links.Graph.StepDate.SCALE.MINUTE:       this.current.setMinutes(this.current.getMinutes() + this.step); break;
      case links.Graph.StepDate.SCALE.HOUR:         this.current.setHours(this.current.getHours() + this.step); break;
      case links.Graph.StepDate.SCALE.DAY:          this.current.setDate(this.current.getDate() + this.step); break;
      case links.Graph.StepDate.SCALE.MONTH:        this.current.setMonth(this.current.getMonth() + this.step); break;
      case links.Graph.StepDate.SCALE.YEAR:         this.current.setFullYear(this.current.getFullYear() + this.step); break;
      default:                      break;
    }
  }

  if (this.step != 1) {
    // round down to the correct major value
    switch (this.scale) {
      case links.Graph.StepDate.SCALE.MILLISECOND:  if(this.current.getMilliseconds() < this.step) this.current.setMilliseconds(0);  break;
      case links.Graph.StepDate.SCALE.SECOND:       if(this.current.getSeconds() < this.step) this.current.setSeconds(0);  break;
      case links.Graph.StepDate.SCALE.MINUTE:       if(this.current.getMinutes() < this.step) this.current.setMinutes(0);  break;
      case links.Graph.StepDate.SCALE.HOUR:         if(this.current.getHours() < this.step) this.current.setHours(0);  break;
      case links.Graph.StepDate.SCALE.DAY:          if(this.current.getDate() < this.step+1) this.current.setDate(1); break;
      case links.Graph.StepDate.SCALE.MONTH:        if(this.current.getMonth() < this.step) this.current.setMonth(0);  break;
      case links.Graph.StepDate.SCALE.YEAR:         break; // nothing to do for year
      default:                break;
    }
  }

  // safety mechanism: if current time is still unchanged, move to the end
  if (this.current.getTime() == prev) {
    this.current = new Date(this._end);
  }
}

/**
 * Get the current datetime 
 * @return {Date}  current The current date
 */ 
links.Graph.StepDate.prototype.getCurrent = function() {
  return this.current;
}

/**
 * Set a custom scale. Autoscaling will be disabled.
 * For example setScale(SCALE.MINUTES, 5) will result
 * in minor steps of 5 minutes, and major steps of an hour. 
 * 
 * @param {Step.SCALE} newScale  A scale. Choose from SCALE.MILLISECOND,
 *                               SCALE.SECOND, SCALE.MINUTE, SCALE.HOUR,
 *                               SCALE.DAY, SCALE.MONTH, SCALE.YEAR.
 * @param {int}        newStep   A step size, by default 1. Choose for
 *                               example 1, 2, 5, or 10.
 */   
links.Graph.StepDate.prototype.setScale = function(newScale, newStep) {
  this.scale = newScale;
  
  if (newStep > 0)
    this.step = newStep;
  
  this.autoScale = false;
}

/**
 * Enable or disable autoscaling
 * @param {boolean} enable  If true, autoascaling is set true
 */ 
links.Graph.StepDate.prototype.setAutoScale = function (enable) {
  this.autoScale = enable;
}


/**
 * Automatically determine the scale that bests fits the provided minimum step
 * @param {int} minimumStep  The minimum step size in milliseconds
 */ 
links.Graph.StepDate.prototype._setMinimumStep = function(minimumStep) {
  if (minimumStep == undefined)
    return;

  var stepYear       = (1000 * 60 * 60 * 24 * 30 * 12);
  var stepMonth      = (1000 * 60 * 60 * 24 * 30);
  var stepDay        = (1000 * 60 * 60 * 24);
  var stepHour       = (1000 * 60 * 60);
  var stepMinute     = (1000 * 60);
  var stepSecond     = (1000);
  var stepMillisecond= (1);

  // find the smallest step that is larger than the provided minimumStep
  if (stepYear*1000 > minimumStep)        {this.scale = links.Graph.StepDate.SCALE.YEAR;        this.step = 1000;}
  if (stepYear*500 > minimumStep)         {this.scale = links.Graph.StepDate.SCALE.YEAR;        this.step = 500;}
  if (stepYear*100 > minimumStep)         {this.scale = links.Graph.StepDate.SCALE.YEAR;        this.step = 100;}
  if (stepYear*50 > minimumStep)          {this.scale = links.Graph.StepDate.SCALE.YEAR;        this.step = 50;}
  if (stepYear*10 > minimumStep)          {this.scale = links.Graph.StepDate.SCALE.YEAR;        this.step = 10;}
  if (stepYear*5 > minimumStep)           {this.scale = links.Graph.StepDate.SCALE.YEAR;        this.step = 5;}
  if (stepYear > minimumStep)             {this.scale = links.Graph.StepDate.SCALE.YEAR;        this.step = 1;}
  if (stepMonth*3 > minimumStep)          {this.scale = links.Graph.StepDate.SCALE.MONTH;       this.step = 3;}
  if (stepMonth > minimumStep)            {this.scale = links.Graph.StepDate.SCALE.MONTH;       this.step = 1;}
  if (stepDay*5 > minimumStep)            {this.scale = links.Graph.StepDate.SCALE.DAY;         this.step = 5;}
  if (stepDay*2 > minimumStep)            {this.scale = links.Graph.StepDate.SCALE.DAY;         this.step = 2;}
  if (stepDay > minimumStep)              {this.scale = links.Graph.StepDate.SCALE.DAY;         this.step = 1;}
  if (stepHour*4 > minimumStep)           {this.scale = links.Graph.StepDate.SCALE.HOUR;        this.step = 4;}
  if (stepHour > minimumStep)             {this.scale = links.Graph.StepDate.SCALE.HOUR;        this.step = 1;}
  if (stepMinute*15 > minimumStep)        {this.scale = links.Graph.StepDate.SCALE.MINUTE;      this.step = 15;}
  if (stepMinute*10 > minimumStep)        {this.scale = links.Graph.StepDate.SCALE.MINUTE;      this.step = 10;}
  if (stepMinute*5 > minimumStep)         {this.scale = links.Graph.StepDate.SCALE.MINUTE;      this.step = 5;}
  if (stepMinute > minimumStep)           {this.scale = links.Graph.StepDate.SCALE.MINUTE;      this.step = 1;}
  if (stepSecond*15 > minimumStep)        {this.scale = links.Graph.StepDate.SCALE.SECOND;      this.step = 15;}
  if (stepSecond*10 > minimumStep)        {this.scale = links.Graph.StepDate.SCALE.SECOND;      this.step = 10;}
  if (stepSecond*5 > minimumStep)         {this.scale = links.Graph.StepDate.SCALE.SECOND;      this.step = 5;}
  if (stepSecond > minimumStep)           {this.scale = links.Graph.StepDate.SCALE.SECOND;      this.step = 1;}
  if (stepMillisecond*200 > minimumStep)  {this.scale = links.Graph.StepDate.SCALE.MILLISECOND; this.step = 200;}
  if (stepMillisecond*100 > minimumStep)  {this.scale = links.Graph.StepDate.SCALE.MILLISECOND; this.step = 100;}
  if (stepMillisecond*50 > minimumStep)   {this.scale = links.Graph.StepDate.SCALE.MILLISECOND; this.step = 50;}
  if (stepMillisecond*10 > minimumStep)   {this.scale = links.Graph.StepDate.SCALE.MILLISECOND; this.step = 10;}
  if (stepMillisecond*5 > minimumStep)    {this.scale = links.Graph.StepDate.SCALE.MILLISECOND; this.step = 5;}
  if (stepMillisecond > minimumStep)      {this.scale = links.Graph.StepDate.SCALE.MILLISECOND; this.step = 1;}
}

/**
 * Snap a date to a rounded value. The snap intervals are dependent on the 
 * current scale and step.
 * @param {Date} date   the date to be snapped
 */ 
links.Graph.StepDate.prototype._snap = function(date) {
  if (this.scale == links.Graph.StepDate.SCALE.YEAR) {
    var year = date.getFullYear() + Math.round(date.getMonth() / 12);
    date.setFullYear(Math.round(year / this.step) * this.step);
    date.setMonth(0);
    date.setDate(0);
    date.setHours(0);
    date.setMinutes(0);
    date.setSeconds(0);
    date.setMilliseconds(0);
  } 
  else if (this.scale == links.Graph.StepDate.SCALE.MONTH) {
    if (date.getDate() > 15) {
      date.setDate(1); 
      date.setMonth(date.getMonth() + 1);
      // important: first set Date to 1, after that change the month.      
    }
    else {
      date.setDate(1);
    }
    
    date.setHours(0);
    date.setMinutes(0);
    date.setSeconds(0);
    date.setMilliseconds(0);
  } 
  else if (this.scale == links.Graph.StepDate.SCALE.DAY) {
    switch (this.step) {
      case 5:
      case 2: 
        date.setHours(Math.round(date.getHours() / 24) * 24); break;
      default: 
        date.setHours(Math.round(date.getHours() / 12) * 12); break;
    }
    date.setMinutes(0);
    date.setSeconds(0);
    date.setMilliseconds(0);
  } 
  else if (this.scale == links.Graph.StepDate.SCALE.HOUR) {
    switch (this.step) {
      case 4:
        date.setMinutes(Math.round(date.getMinutes() / 60) * 60); break;
      default: 
        date.setMinutes(Math.round(date.getMinutes() / 30) * 30); break;
    }    
    date.setSeconds(0);
    date.setMilliseconds(0);
  } else if (this.scale == links.Graph.StepDate.SCALE.MINUTE) {
    switch (this.step) {
      case 15:
      case 10:
        date.setMinutes(Math.round(date.getMinutes() / 5) * 5); 
        date.setSeconds(0);
        break;
      case 5:
        date.setSeconds(Math.round(date.getSeconds() / 60) * 60); break;
      default: 
        date.setSeconds(Math.round(date.getSeconds() / 30) * 30); break;
    }    
    date.setMilliseconds(0);
  } 
  else if (this.scale == links.Graph.StepDate.SCALE.SECOND) {
    switch (this.step) {
      case 15:
      case 10:
        date.setSeconds(Math.round(date.getSeconds() / 5) * 5); 
        date.setMilliseconds(0);
        break;
      case 5:
        date.setMilliseconds(Math.round(date.getMilliseconds() / 1000) * 1000); break;
      default: 
        date.setMilliseconds(Math.round(date.getMilliseconds() / 500) * 500); break;
    }
  }
  else if (this.scale == links.Graph.StepDate.SCALE.MILLISECOND) {
    var step = this.step > 5 ? this.step / 2 : 1;
    date.setMilliseconds(Math.round(date.getMilliseconds() / step) * step);    
  }
}

/**
 * Check if the current step is a major step (for example when the step
 * is DAY, a major step is each first day of the MONTH)
 * @return true if current date is major, else false.
 */ 
links.Graph.StepDate.prototype.isMajor = function() {
  switch (this.scale)
  {
    case links.Graph.StepDate.SCALE.MILLISECOND:
      return (this.current.getMilliseconds() == 0);
    case links.Graph.StepDate.SCALE.SECOND:
      return (this.current.getSeconds() == 0);
    case links.Graph.StepDate.SCALE.MINUTE:
      return (this.current.getHours() == 0) && (this.current.getMinutes() == 0);  
      // Note: this is no bug. Major label is equal for both minute and hour scale
    case links.Graph.StepDate.SCALE.HOUR:
      return (this.current.getHours() == 0);
    case links.Graph.StepDate.SCALE.DAY:  
      return (this.current.getDate() == 1);
    case links.Graph.StepDate.SCALE.MONTH:        
      return (this.current.getMonth() == 0);
    case links.Graph.StepDate.SCALE.YEAR:         
      return false
    default:                      
      return false;    
  }
}


/**
 * Returns formatted text for the minor axislabel, depending on the current
 * date and the scale. For example when scale is MINUTE, the current time is 
 * formatted as "hh::mm".
 * @param {Date}       optional custom date. if not provided, current date is taken
 * @return {string}    minor axislabel
 */ 
links.Graph.StepDate.prototype.getLabelMinor = function(date) {
  var MONTHS_SHORT = new Array("Jan", "Feb", "Mar", 
                                "Apr", "May", "Jun", 
                                "Jul", "Aug", "Sep", 
                                "Oct", "Nov", "Dec");

  if (date == undefined) {
    date = this.current;
  }

  switch (this.scale)
  {
    case links.Graph.StepDate.SCALE.MILLISECOND:  return date.getMilliseconds();
    case links.Graph.StepDate.SCALE.SECOND:       return date.getSeconds();
    case links.Graph.StepDate.SCALE.MINUTE:       return this._addZeros(date.getHours(), 2) + ":" +
                                                   this._addZeros(date.getMinutes(), 2);
    case links.Graph.StepDate.SCALE.HOUR:         return this._addZeros(date.getHours(), 2) + ":" +
                                                   this._addZeros(date.getMinutes(), 2);
    case links.Graph.StepDate.SCALE.DAY:          return date.getDate();
    case links.Graph.StepDate.SCALE.MONTH:        return MONTHS_SHORT[date.getMonth()];   // month is zero based
    case links.Graph.StepDate.SCALE.YEAR:         return date.getFullYear();
    default:                                return "";    
  }
}


/**
 * Returns formatted text for the major axislabel, depending on the current
 * date and the scale. For example when scale is MINUTE, the major scale is
 * hours, and the hour will be formatted as "hh". 
 * @param {Date}       optional custom date. if not provided, current date is taken
 * @return {string}    major axislabel
 */ 
links.Graph.StepDate.prototype.getLabelMajor = function(date) {
  var MONTHS = new Array("January", "February", "March", 
                         "April", "May", "June", 
                         "July", "August", "September", 
                         "October", "November", "December");
  var DAYS = new Array("Sunday", "Monday", "Tuesday", 
                       "Wednesday", "Thursday", "Friday", "Saturday");  

  if (date == undefined) {
    date = this.current;
  } 

  switch (this.scale) {
    case links.Graph.StepDate.SCALE.MILLISECOND:
      return  this._addZeros(date.getHours(), 2) + ":" +
              this._addZeros(date.getMinutes(), 2) + ":" +
              this._addZeros(date.getSeconds(), 2);   
    case links.Graph.StepDate.SCALE.SECOND:
      return  date.getDate() + " " + 
              MONTHS[date.getMonth()] + " " +
              this._addZeros(date.getHours(), 2) + ":" +
              this._addZeros(date.getMinutes(), 2);
    case links.Graph.StepDate.SCALE.MINUTE:
      return  DAYS[date.getDay()] + " " +
              date.getDate() + " " + 
              MONTHS[date.getMonth()] + " " +
              date.getFullYear();
    case links.Graph.StepDate.SCALE.HOUR:
      return  DAYS[date.getDay()] + " " +
              date.getDate() + " " + 
              MONTHS[date.getMonth()] + " " +
              date.getFullYear();
    case links.Graph.StepDate.SCALE.DAY:
      return  MONTHS[date.getMonth()] + " " +
              date.getFullYear();
    case links.Graph.StepDate.SCALE.MONTH:
      return date.getFullYear();
    default:
      return "";
  }        
}

/**
 * Add leading zeros to the given value to match the desired length.
 * For example addZeros(123, 5) returns "00123"
 * @param {int} value   A value
 * @param {int} len     Desired final length
 * @return {string}     value with leading zeros
 */ 
links.Graph.StepDate.prototype._addZeros = function(value, len) {
  var str = "" + value;
  while (str.length < len) {
    str = "0" + str;
  }
  return str;
}

/** 
 * @class StepNumber
 * The class StepNumber is an iterator for numbers. You provide a start and end 
 * value, and a best step size. StepNumber itself rounds to fixed values and 
 * a finds the step that best fits the provided step.
 * 
 * If prettyStep is true, the step size is chosen as close as possible to the 
 * provided step, but being a round value like 1, 2, 5, 10, 20, 50, ....
 * 
 * Example usage: 
 *   var step = new links.Graph.StepNumber(0, 10, 2.5, true);
 *   step.start();
 *   while (!step.end()) {
 *     alert(step.getCurrent());
 *     step.next();
 *   }
 * 
 * Version: 1.0
 * 
 * @param {number} start       The start value
 * @param {number} end         The end value
 * @param {number} step        Optional. Step size. Must be a positive value.
 * @param {boolean} prettyStep Optional. If true, the step size is rounded
 *                             To a pretty step size (like 1, 2, 5, 10, 20, 50, ...)
 */
links.Graph.StepNumber = function (start, end, step, prettyStep) {
  this._start = 0;
  this._end = 0;
  this.step_ = 1;
  this.prettyStep = true;
  this.precision = 5;

  this._current = 0;
  this._setRange(start, end, step, prettyStep);
}

/** 
 * Set a new range: start, end and step.
 * 
 * @param {number} start       The start value
 * @param {number} end         The end value
 * @param {number} step        Optional. Step size. Must be a positive value.
 * @param {boolean} prettyStep Optional. If true, the step size is rounded
 *                             To a pretty step size (like 1, 2, 5, 10, 20, 50, ...)
 */
links.Graph.StepNumber.prototype._setRange = function(start, end, step, prettyStep) {
  this._start = start ? start : 0;
  this._end = end ? end : 0;

  this.setStep(step, prettyStep);
}

/**
 * Set a new step size
 * @param {number} step        New step size. Must be a positive value
 * @param {boolean} prettyStep Optional. If true, the provided step is rounded
 *                             to a pretty step size (like 1, 2, 5, 10, 20, 50, ...)
 */ 
links.Graph.StepNumber.prototype.setStep = function(step, prettyStep) {
  if (step == undefined || step <= 0)
    return;

  this.prettyStep = prettyStep;
  if (this.prettyStep == true)
    this.step_ = links.Graph.StepNumber._calculatePrettyStep(step);
  else 
    this.step_ = step;
    
    
  if (this._end / this.step_ > Math.pow(10, this.precision)) {
      this.precision = undefined;
  }
}

/**
 * Calculate a nice step size, closest to the desired step size.
 * Returns a value in one of the ranges 1*10^n, 2*10^n, or 5*10^n, where n is an 
 * integer number. For example 1, 2, 5, 10, 20, 50, etc...
 * @param {number}  step  Desired step size
 * @return {number}       Nice step size
 */
links.Graph.StepNumber._calculatePrettyStep = function (step) {
  log10 = function (x) {return Math.log(x) / Math.LN10;}

  // try three steps (multiple of 1, 2, or 5
  var step1 = 1 * Math.pow(10, Math.round(log10(step / 1)));
  var step2 = 2 * Math.pow(10, Math.round(log10(step / 2)));
  var step5 = 5 * Math.pow(10, Math.round(log10(step / 5)));
  
  // choose the best step (closest to minimum step)
  var prettyStep = step1;
  if (Math.abs(step2 - step) <= Math.abs(prettyStep - step)) prettyStep = step2;
  if (Math.abs(step5 - step) <= Math.abs(prettyStep - step)) prettyStep = step5;

  // for safety
  if (prettyStep <= 0) {
    prettyStep = 1;
  }

  return prettyStep;
}

/**
 * returns the current value of the step
 * @return {number} current value
 */
links.Graph.StepNumber.prototype.getCurrent = function () {
    if (this.precision) {
        return Number((this._current).toPrecision(this.precision));
    }
    else {
        return this._current;
    }
  
  /* TODO: cleanup
  if (this._current < 100000) {
    currentRounded *= 1; // remove zeros at the tail, behind the comma
  }
  else {
    currentRounded = Number(currentRounded);
  }
  return Number(currentRounded); 
  */
}

/**
 * returns the current step size
 * @return {number} current step size
 */ 
links.Graph.StepNumber.prototype.getStep = function () {
  return this.step_;  
}

/**
 * Set the current value to the largest value smaller than start, which
 * is a multiple of the step size
 */ 
links.Graph.StepNumber.prototype.start = function() {
  if (this.prettyStep)
    this._current = this._start - this._start % this.step_;
  else 
    this._current = this._start;
}

/**
 * Do a step, add the step size to the current value
 */  
links.Graph.StepNumber.prototype.next = function () {
  this._current += this.step_;
}

/**
 * Returns true whether the end is reached
 * @return {boolean}  True if the current value has passed the end value.
 */ 
links.Graph.StepNumber.prototype.end = function () {
  return (this._current > this._end);
}


/**
 * Set a custom scale. Autoscaling will be disabled.
 * For example setScale(SCALE.MINUTES, 5) will result
 * in minor steps of 5 minutes, and major steps of an hour. 
 * 
 * @param {Step.SCALE} newScale  A scale. Choose from SCALE.MILLISECOND,
 *                               SCALE.SECOND, SCALE.MINUTE, SCALE.HOUR,
 *                               SCALE.DAY, SCALE.MONTH, SCALE.YEAR.
 * @param {int}        newStep   A step size, by default 1. Choose for
 *                               example 1, 2, 5, or 10.
 */   
links.Graph.prototype.setScale = function(scale, step) {
  this.hStep.setScale(scale, step);
  this.redraw();
}

/**
 * Enable or disable autoscaling
 * @param {boolean} enable  If true or not defined, autoscaling is enabled. 
 *                          If false, autoscaling is disabled. 
 */ 
links.Graph.prototype.setAutoScale = function(enable) {
  this.hStep.setAutoScale(enable);
  this.redraw();
}


/**
 * Append suffix "px" to provided value x
 * @param {int}     x  An integer value
 * @return {string} the string value of x, followed by the suffix "px"
 */ 
links.Graph.px = function(x) {
  return x + "px";
}


/**
 * Calculate the factor and offset to convert a position on screen to the 
 * corresponding date and vice versa. 
 * After the method calcConversionFactor is executed once, the methods screenToTime and 
 * timeToScreen can be used.
 */ 
links.Graph.prototype._calcConversionFactor = function() {
  this.ttsOffset = parseFloat(this.start.valueOf());
  this.ttsFactor = parseFloat(this.frame.clientWidth) / 
                   parseFloat(this.end.valueOf() - this.start.valueOf());
}


/** 
 * Convert a position on screen (pixels) to a datetime 
 * Before this method can be used, the method calcConversionFactor must be 
 * executed once.
 * @param {int}     x    Position on the screen in pixels
 * @return {Date}   time The datetime the corresponds with given position x
 */ 
links.Graph.prototype._screenToTime = function(x) {
  var time = new Date(parseFloat(x) / this.ttsFactor + this.ttsOffset);
  return time;
}

/** 
 * Convert a datetime (Date object) into a position on the screen
 * Before this method can be used, the method calcConversionFactor must be 
 * executed once.
 * @param {Date}   time A date
 * @return {int}   x    The position on the screen in pixels which corresponds
 *                      with the given date.
 */ 
links.Graph.prototype.timeToScreen = function(time) {
  var x = (time.valueOf() - this.ttsOffset) * this.ttsFactor;
  return x;
}

/**
 * Create the main frame for the Graph.
 * This function is executed once when a Graph object is created. The frame
 * contains a canvas, and this canvas contains all objects like the axis and 
 * events.
 */
links.Graph.prototype._create = function () {
  // remove all elements from the container element.
  while (this.containerElement.hasChildNodes()) {
    this.containerElement.removeChild(this.containerElement.firstChild);
  }
  
  this.main = document.createElement("DIV");
  this.main.className = "graph-frame";
  this.main.style.position = "relative";
  this.main.style.overflow = "hidden";
  this.containerElement.appendChild(this.main);   
  
  // create the main box where the Graph will be created
  this.frame = document.createElement("DIV");
  this.frame.style.overflow = "hidden";
  this.frame.style.position = "relative";
  this.frame.style.height = "200px";  // height MUST be initialized. 
  // Width and height will be set via setSize();
  //this.containerElement.appendChild(this.frame);
  this.main.appendChild(this.frame);

  // create a canvas background, which can be used to give the canvas a colored background
  this.frame.canvasBackground = document.createElement("DIV");
  this.frame.canvasBackground.className = "graph-canvas";
  this.frame.canvasBackground.style.position = "relative";
  this.frame.canvasBackground.style.left = links.Graph.px(0);
  this.frame.canvasBackground.style.top = links.Graph.px(0);
  this.frame.canvasBackground.style.width = "100%";
  this.frame.appendChild(this.frame.canvasBackground);

  // create a div to contain the grid lines of the vertical axis
  this.frame.vgrid = document.createElement("DIV");
  this.frame.vgrid.className = "graph-axis-grid";
  this.frame.vgrid.style.position = "absolute";
  this.frame.vgrid.style.left = links.Graph.px(0);
  this.frame.vgrid.style.top = links.Graph.px(0);
  this.frame.vgrid.style.width = "100%"; 
  this.frame.appendChild(this.frame.vgrid);
  
  // create the canvas inside the frame. all elements will be added to this 
  // canvas
  this.frame.canvas = document.createElement("DIV");
  //this.frame.canvas.className = "graph-canvas";
  this.frame.canvas.style.position = "absolute";
  this.frame.canvas.style.left = links.Graph.px(0);
  this.frame.canvas.style.top = links.Graph.px(0);
  this.frame.appendChild(this.frame.canvas);
  // Width and height will be set via setSize();
  
  // inside the canvas, create a DOM element "axis" to store all axis related elements
  this.frame.canvas.axis = document.createElement("DIV");
  this.frame.canvas.axis.style.position = "relative";
  this.frame.canvas.axis.style.left = links.Graph.px(0);
  this.frame.canvas.axis.style.top = links.Graph.px(0);
  this.frame.canvas.appendChild(this.frame.canvas.axis);
  this.majorLabels = new Array();

  // create the graph canvas (HTML canvas element)
  this.frame.canvas.graph = document.createElement( "canvas" );
  this.frame.canvas.graph.style.position = "absolute";
  this.frame.canvas.graph.style.left = links.Graph.px(0);
  this.frame.canvas.graph.style.top = links.Graph.px(0);
  //this.frame.canvas.graph.width = "800";   // width is adjusted lateron
  //this.frame.canvas.graph.height = "200";  // height is adjusted lateron
  this.frame.canvas.appendChild(this.frame.canvas.graph);

  isIE = (/MSIE/.test(navigator.userAgent) && !window.opera);
  if (isIE && (typeof(G_vmlCanvasManager) != 'undefined')) {
    this.frame.canvas.graph = G_vmlCanvasManager.initElement(this.frame.canvas.graph);
  }
    
  // add event listeners to handle moving and zooming the contents
  var me = this;
  var onmousedown = function (event) {me._onMouseDown(event);};
  var onmousewheel = function (event) {me._onWheel(event);};
  var ontouchstart = function (event) {me._onTouchStart(event);};
  var onzoom = function (event) {me._onZoom(event);};

  // TODO: these events are never cleaned up... can give a "memory leakage"?
  links.Graph.addEventListener(this.frame, "mousedown", onmousedown);
  links.Graph.addEventListener(this.frame, "mousewheel", onmousewheel);
  links.Graph.addEventListener(this.frame, "touchstart", ontouchstart);
  links.Graph.addEventListener(this.frame, "mousedown", function() {me._checkSize();});

  // create a step for drawing the horizontal and vertical axis
  this.hStep = new links.Graph.StepDate();     // TODO: rename step to hStep
  this.vStep = new links.Graph.StepNumber();

  // the array events contains pointers to all data events. It is used 
  // to sort and stack the events.
  this.eventsSorted = []; 
}


/**
 * Set a new size for the graph
 * @param {string} width   Width in pixels or percentage (for example "800px"
 *                         or "50%")
 * @param {string} height  Height in pixels or percentage  (for example "400px"
 *                         or "30%")
 */ 
links.Graph.prototype.setSize = function(width, height) {
  // TODO: test if this solves the width as percentage problem in EXT-GWT
  this.containerElement.style.width = width;
  this.containerElement.style.height = height;

  this.main.style.width = width;
  this.main.style.height = height;

  this.frame.style.width = links.Graph.px(this.main.clientWidth);
  this.frame.style.height = links.Graph.px(this.main.clientHeight);

  this.frame.canvas.style.width = links.Graph.px(this.frame.clientWidth);
  this.frame.canvas.style.height = links.Graph.px(this.frame.clientHeight);
}

/**
 * Zoom the graph the given zoomfactor in or out. Start and end date will
 * be adjusted, and the graph will be redrawn. You can optionally give a 
 * date around which to zoom.
 * For example, try zoomfactor = 0.1 or -0.1
 * @param {float}  zoomFactor      Zooming amount. Positive value will zoom in,
 *                                 negative value will zoom out
 * @param {Date}   zoomAroundDate  Date around which will be zoomed. Optional
 */ 
links.Graph.prototype._zoom = function(zoomFactor, zoomAroundDate) {
  // if zoomAroundDate is not provided, take it half between start Date and end Date
  if (zoomAroundDate == undefined)
    zoomAroundDate = new Date((this.start.valueOf() + this.end.valueOf()) / 2);
  
  // prevent zoom factor larger than 1 or smaller than -1 (larger than 1 will
  // result in a start>=end )
  if (zoomFactor >= 1) zoomFactor = 0.9;
  if (zoomFactor <= -1) zoomFactor = -0.9;

  // adjust a negative factor such that zooming in with 0.1 equals zooming
  // out with a factor -0.1
  if (zoomFactor < 0) {
    zoomFactor = zoomFactor / (1 + zoomFactor);
  }

  // zoom start Date and end Date relative to the zoomAroundDate
  var startDiff = parseFloat(this.start.valueOf() - zoomAroundDate.valueOf());
  var endDiff = parseFloat(this.end.valueOf() - zoomAroundDate.valueOf());
    
  // calculate new dates
  var newStart = new Date(this.start.valueOf() - startDiff * zoomFactor);
  var newEnd   = new Date(this.end.valueOf() - endDiff * zoomFactor);
  
  // prevent scale of less than 10 milliseconds
  // TODO: IE has problems with milliseconds
  if (zoomFactor > 0 && (newEnd.valueOf() - newStart.valueOf()) < 10)
    return;
    
  // prevent scale of mroe than than 10 thousand years
  if (zoomFactor < 0 && (newEnd.getFullYear() - newStart.getFullYear()) > 10000)
    return;
  
  // apply new dates
  this.start = newStart;
  this.end = newEnd;
  
  this._redrawHorizontalAxis();
  this._redrawData();    
}

/**
 * Move the graph the given movefactor to the left or right. Start and end 
 * date will be adjusted, and the graph will be redrawn. 
 * For example, try moveFactor = 0.1 or -0.1
 * @param {float}  moveFactor      Moving amount. Positive value will move right,
 *                                 negative value will move left
 */ 
links.Graph.prototype._move = function(moveFactor) {
  // TODO: test this function again
  // zoom start Date and end Date relative to the zoomAroundDate
  var diff = parseFloat(this.end.valueOf() - this.start.valueOf());
  
  // apply new dates
  this.start = new Date(this.start.valueOf() + diff * moveFactor);
  this.end   = new Date(this.end.valueOf() + diff * moveFactor);
  
  // redraw
  this._redrawHorizontalAxis(); 
  this._redrawData(); 
}

/**
 * Zoom the graph vertically. The vertical range will be adjusted, and the graph 
 * will be redrawn. You can optionally give a value around which to zoom.
 * For example, try zoomfactor = 0.1 or -0.1
 * @param {float}  zoomFactor      Zooming amount. Positive value will zoom in,
 *                                 negative value will zoom out
 * @param {Date}   zoomAroundValue Value around which will be zoomed. Optional
 */ 
links.Graph.prototype._zoomVertical = function(zoomFactor, zoomAroundValue) {

  if (zoomAroundValue == undefined)
    zoomAroundValue = (this.vStart + this.vEnd) / 2;
  
  // prevent zoom factor larger than 1 or smaller than -1 (larger than 1 will
  // result in a start>=end )
  if (zoomFactor >= 1) zoomFactor = 0.9;
  if (zoomFactor <= -1) zoomFactor = -0.9;

  // adjust a negative factor such that zooming in with 0.1 equals zooming
  // out with a factor -0.1
  if (zoomFactor < 0) {
    zoomFactor = zoomFactor / (1 + zoomFactor);
  }

  // zoom start Date and end Date relative to the zoomAroundDate
  var startDiff = (this.vStart - zoomAroundValue);
  var endDiff = (this.vEnd - zoomAroundValue);
    
  // calculate new dates
  var newStart = (this.vStart - startDiff * zoomFactor);
  var newEnd   = (this.vEnd - endDiff * zoomFactor);
  
  // prevent empty range
  if (newStart >= newEnd) {
    return;
  }

  // prevent range larger than the available range
  if (newStart < this.vMin) {
    newStart = this.vMin;
  }
  if (newEnd > this.vMax) {
    newEnd = this.vMax;
  }
  /* TODO: allow start and end larger than the value range?
  if (newStart < this.vMin && newStart < this.vStart) {
    newStart = (this.vStart > this.vMin) ? this.vMin : this.vStart;
  }
  if (newEnd > this.vMax && newEnd > this.vEnd) {
    newEnd = (this.vEnd < this.vMax) ? this.vMax : this.vEnd;
  }
  */
  
  // apply new range
  this.vStart = newStart;
  this.vEnd = newEnd;

  // redraw
  this._redrawVerticalAxis(); 
  this._redrawHorizontalAxis(); // -> width of the vertical axis can be changed
  this._redrawData(); 
}


/** 
 * Redraw the Graph. This needs to be executed after the start and/or
 * end time are changed, or when data is added or removed dynamically. 
 */ 
links.Graph.prototype.redraw = function() {
  this._initSize();

  // Note: the order of drawing is important!    
  this._redrawLegend();
  this._redrawVerticalAxis();
  this._redrawHorizontalAxis();
  this._redrawData();

  // store the current width and height. This is needed to detect when the frame
  // was resized (externally).
  this.lastMainWidth = this.main.clientWidth;
  this.lastMainHeight = this.main.clientHeight;
}

/**
 * Initialize size, range, location of axis  
 * Execute this method when the data or options are changed, before redrawing
 * the graph.
 */ 
links.Graph.prototype._initSize = function() {
  // calculate the width and height of a single character
  // this is used to calculate the step size, and also the positioning of the
  // axis
  var charText = document.createTextNode("0");
  var charDiv = document.createElement("DIV");
  charDiv.className = "graph-axis-text";
  charDiv.appendChild(charText);
  charDiv.style.position = "absolute";
  charDiv.style.visibility = "hidden";
  charDiv.style.padding = "0px";
  this.frame.canvas.axis.appendChild(charDiv);
  this.axisCharWidth  = parseInt(charDiv.clientWidth);
  this.axisCharHeight = parseInt(charDiv.clientHeight);
  charDiv.style.padding = "";
  charDiv.className = "graph-axis-text graph-axis-text-minor";
  this.axisTextMinorHeight = parseInt(charDiv.offsetHeight);
  charDiv.className = "graph-axis-text graph-axis-text-major";
  this.axisTextMajorHeight = parseInt(charDiv.offsetHeight);
  this.frame.canvas.axis.removeChild(charDiv);  // TODO: When using .redraw() via the browser event onresize, this gives an error in Chrome

  // calculate the position of the axis
  this.axisOffset = this.main.clientHeight - 
                    this.axisTextMinorHeight - 
                    this.axisTextMajorHeight - 
                    2 * this.mainPadding;

  // TODO: do not retrieve datarange here? -> initSize is executed during each redraw()
  // retrieve the data range (this can take some time for large amounts of data)
  //this.dataRange = this._getDataRange(this.data[0]); // TODO
  if (this.data.length > 0) {
    this.dataRange = this.data[0].dataRange;
    for (var i = 0, len = this.data.length; i < len; i++) {
      this.dataRange.min = Math.min(this.dataRange.min, this.data[i].dataRange.min);
      this.dataRange.max = Math.max(this.dataRange.max, this.data[i].dataRange.max);
    }
  }
  else {
    this.dataRange = {"min" : -10, "max" : 10};
  }
}

/**
 * Draw the horizontal axis in the graph, containing grid, axis, minor and 
 * major labels 
 */
links.Graph.prototype._redrawHorizontalAxis = function () {
  var startTime = new Date(); // TODO: cleanup
  
  // clear any existing data
  while (this.frame.canvas.axis.hasChildNodes()) {
    this.frame.canvas.axis.removeChild(this.frame.canvas.axis.lastChild);
  }
  this.majorLabels = new Array();

  // resize the horizontal axis
  this.frame.style.left = links.Graph.px(this.main.axisLeft.clientWidth + this.mainPadding);
  this.frame.style.top = links.Graph.px(this.mainPadding);
  this.frame.style.height = links.Graph.px(this.main.clientHeight - 2 * this.mainPadding );
  this.frame.style.width = links.Graph.px(this.main.clientWidth - 
                                          this.main.axisLeft.clientWidth - 
                                          this.legendWidth -
                                          2 * this.mainPadding - 2);

  this.frame.canvas.style.width = links.Graph.px(this.frame.clientWidth);
  this.frame.canvas.style.height = links.Graph.px(this.axisOffset);
  this.frame.canvasBackground.style.height = links.Graph.px(this.axisOffset);

  this._calcConversionFactor();

  // the drawn axis is more wide than the actual visual part, such that
  // the axis can be dragged without having to redraw it each time again.
  var start = this._screenToTime(-this.axisMargin);
  var end = this._screenToTime(this.frame.clientWidth + this.axisMargin);
  var width = this.frame.clientWidth + 2*this.axisMargin;

  var yvalueMinor = this.axisOffset;
  var yvalueMajor = this.axisOffset + this.axisTextMinorHeight;

  // calculate minimum step (in milliseconds) based on character size
  this.minimumStep = this._screenToTime(this.axisCharWidth * 6).valueOf() - 
                     this._screenToTime(0).valueOf();

  this.hStep._setRange(start, end, this.minimumStep);

  // create a left major label
  if (this.leftMajorLabel) {
    this.frame.canvas.removeChild(this.leftMajorLabel)
    this.leftMajorLabel = undefined;
  }
  var leftDate = this.hStep.getLabelMajor(this._screenToTime(0));
  var content = document.createTextNode(leftDate);
  this.leftMajorLabel = document.createElement("DIV");
  this.leftMajorLabel.className = "graph-axis-text graph-axis-text-major";
  this.leftMajorLabel.appendChild(content);
  this.leftMajorLabel.style.position = "absolute";
  this.leftMajorLabel.style.left = links.Graph.px(0);
  this.leftMajorLabel.style.top = links.Graph.px(yvalueMajor);
  this.leftMajorLabel.title = leftDate;
  this.frame.canvas.appendChild(this.leftMajorLabel);

  this.hStep.start();
  while (!this.hStep.end()) {
    var x = this.timeToScreen(this.hStep.getCurrent());
    var hvline = this.hStep.isMajor() ? this.frame.clientHeight : 
                                      (this.axisOffset + this.axisTextMinorHeight);

    //create vertical line
    var vline = document.createElement("DIV");
    vline.className = this.hStep.isMajor() ? "graph-axis-grid graph-axis-grid-major" :  
                                            "graph-axis-grid graph-axis-grid-minor";
    vline.style.position = "absolute";
    vline.style.borderLeftStyle = "solid";
    vline.style.top = links.Graph.px(0);
    vline.style.width = links.Graph.px(0);
    vline.style.height = links.Graph.px(hvline);
    vline.style.left = links.Graph.px(x - vline.offsetWidth/2);
    this.frame.canvas.axis.appendChild(vline);

    if (this.hStep.isMajor())
    {
      var content = document.createTextNode(this.hStep.getLabelMajor());
      var majorValue = document.createElement("DIV");
      this.frame.canvas.axis.appendChild(majorValue);
      majorValue.className = "graph-axis-text graph-axis-text-major";
      majorValue.appendChild(content);
      majorValue.style.position = "absolute";
      majorValue.style.width = links.Graph.px(majorValue.clientWidth);
      majorValue.style.left = links.Graph.px(x);
      majorValue.style.top = links.Graph.px(yvalueMajor);
      majorValue.title = this.hStep.getCurrent();
      majorValue.x = x;
      this.majorLabels.push(majorValue); 
    }
    
    // minor label
    var content = document.createTextNode(this.hStep.getLabelMinor());
    var minorValue = document.createElement("DIV");
    minorValue.appendChild(content);
    minorValue.className = "graph-axis-text graph-axis-text-minor";
    minorValue.style.position = "absolute";
    minorValue.style.left = links.Graph.px(x);
    minorValue.style.top  = links.Graph.px(yvalueMinor);
    minorValue.title = this.hStep.getCurrent();
    this.frame.canvas.axis.appendChild(minorValue);
    
    this.hStep.next();
  }

  // make horizontal axis line on top
  var line = document.createElement("DIV");
  line.className = "graph-axis";
  line.style.position = "absolute";
  line.style.borderTopStyle = "solid";
  line.style.top = links.Graph.px(0);
  line.style.left = links.Graph.px(this.timeToScreen(start));
  line.style.width = links.Graph.px(this.timeToScreen(end) - this.timeToScreen(start));
  line.style.height = links.Graph.px(0);
  this.frame.canvas.axis.appendChild(line);

  // make horizontal axis line on bottom side
  var line = document.createElement("DIV");
  line.className = "graph-axis";
  line.style.position = "absolute";
  line.style.borderTopStyle = "solid";
  line.style.top = links.Graph.px(this.axisOffset);
  line.style.left = links.Graph.px(this.timeToScreen(start)); 
  line.style.width = links.Graph.px(this.timeToScreen(end) - this.timeToScreen(start));
  line.style.height = links.Graph.px(0);
  this.frame.canvas.axis.appendChild(line);
  
  // reposition the left major label
  this._redrawAxisLeftMajorLabel();
  
  var endTime = new Date(); // TODO: cleanup
  //document.title = (endTime - startTime) + " ms"; // TODO: cleanup
}


/**
 * Reposition the major labels of the horizontal axis
 */ 
links.Graph.prototype._redrawAxisLeftMajorLabel = function() {
  var offset = parseFloat(this.frame.canvas.axis.style.left);
  
  var lastBelowZero = null;
  var firstAboveZero = null;
  var xPrev = null;
  for (i in this.majorLabels) {
    var label = this.majorLabels[i];
    
    if (label.x + offset < 0)
      lastBelowZero = label;
    
    if (label.x + offset  > 0 && (xPrev == null || xPrev + offset  < 0)) {
      firstAboveZero = label;
    }

    xPrev = label.x;
  }

  if (lastBelowZero)
    lastBelowZero.style.visibility = "hidden";  

  if (firstAboveZero)
    firstAboveZero.style.visibility = "visible";  

  if (firstAboveZero && this.leftMajorLabel.clientWidth > firstAboveZero.x + offset ) {
    this.leftMajorLabel.style.visibility = "hidden";
  }
  else {
    var leftTime = this.hStep.getLabelMajor(this._screenToTime(-offset));
    this.leftMajorLabel.title = leftTime;
    this.leftMajorLabel.innerHTML = leftTime;
    if (this.leftMajorLabel.style.visibility != "visible") {
      this.leftMajorLabel.style.visibility = "visible";
    }
  }
}

/**
 * Draw the vertical axis in the graph
 */
links.Graph.prototype._redrawVerticalAxis = function () {
  var testStart = new Date(); // TODO: cleanup
  
  if (!this.main.axisLeft) {
    // create the left vertical axis
    this.main.axisLeft = document.createElement("DIV");
    this.main.axisLeft.style.position = "absolute";
    this.main.axisLeft.className = "graph-axis graph-axis-vertical";
    this.main.axisLeft.style.borderRightStyle = "solid";

    this.main.appendChild(this.main.axisLeft);
  } else {
    // clear any existing data
    while (this.main.axisLeft.hasChildNodes()) {
      this.main.axisLeft.removeChild(this.main.axisLeft.lastChild);
    }
  }

  if (!this.main.axisRight) {
    // create the left vertical axis
    this.main.axisRight = document.createElement("DIV");
    this.main.axisRight.style.position = "absolute";
    this.main.axisRight.className = "graph-axis graph-axis-vertical";
    this.main.axisRight.style.borderRightStyle = "solid";
    this.main.appendChild(this.main.axisRight);
  } else {
    // do nothing
  }

  if (!this.main.zoomButtons) {
    // create zoom buttons for the vertical axis    
    this.main.zoomButtons = document.createElement("DIV");
    this.main.zoomButtons.className = "graph-axis-button-menu";
    this.main.zoomButtons.style.position = "absolute";

    var graph = this;
    var zoomIn = document.createElement("DIV");
    zoomIn.innerHTML = "+";
    zoomIn.title = "Zoom in vertically (shift + scroll wheel)";
    zoomIn.className = "graph-axis-button";
    this.main.zoomButtons.appendChild(zoomIn);
    links.Graph.addEventListener(zoomIn, "mousedown", function (event) {
      graph._zoomVertical(0.2);
      links.Graph.preventDefault(event);
    });

    var zoomOut = document.createElement("DIV");
    zoomOut.innerHTML = "&minus;";
    zoomOut.className = "graph-axis-button";
    zoomOut.title = "Zoom out vertically (shift + scroll wheel)";
    this.main.zoomButtons.appendChild(zoomOut);
    links.Graph.addEventListener(zoomOut, "mousedown", function (event) {
      graph._zoomVertical(-0.2);
      links.Graph.preventDefault(event);
    });
    
    this.main.appendChild(this.main.zoomButtons);
  }  

  // clear any existing data from the grid
  while (this.frame.vgrid.hasChildNodes()) {
    this.frame.vgrid.removeChild(this.frame.vgrid.lastChild);
  }  

  // get the minimum and maximum data values, and add 5 percent
  // so there is always some whitespace above and below the drawn data
  var range = this.dataRange.max - this.dataRange.min;
  if (range <= 0) {
    range = 1;
  }
  var avg = (this.dataRange.max + this.dataRange.min) / 2;
  this.vMin = avg - range / 2 * 1.05;
  this.vMax = avg + range / 2 * 1.05;

  // determine the range start, end, and step
  this.vStart = (this.vStart != undefined && this.vStart < this.vMax) ? this.vStart : this.vMin;
  this.vEnd = (this.vEnd != undefined && this.vEnd > this.vMin) ? this.vEnd : this.vMax;
  // TODO: allow start and end larger than visible area?
  this.vStart = Math.max(this.vStart, this.vMin);
  this.vEnd = Math.min(this.vEnd, this.vMax);
  
  var start = this.vStart;
  var end = this.vEnd;
  var stepnum = parseInt(this.axisOffset / 40);
  var step = this.vStepSize || ((this.vEnd - this.vStart) / stepnum);
  var prettyStep = true; 
  this.vStep._setRange(start, end, step, prettyStep);

  if (this.vEnd > this.vStart) {
    // calculate the conversion from y value to position on screen
    var graphBottom = this.axisOffset;
    var graphTop = 0;
    var yScale = (graphTop - graphBottom) / (this.vEnd - this.vStart);
    var yShift = graphBottom - this.vStart * yScale;
    this.yToScreen = function (y) {
      return y * yScale + yShift;
    }
    this.screenToY = function (ys) {
      return (ys - yShift) / yScale;
    }
    // TODO: make a more neat solution for this.yToScreen()
  }
  else {
    this.yToScreen = function (y) {
      return 0;
    }
    this.screenToY = function (ys) {
      return 0;
    }
  }

  var maxWidth = 0;
  this.vStep.start();
  
  if ( this.yToScreen(this.vStep.getCurrent()) > this.axisOffset) {
    this.vStep.next();
  }
  
  while(!this.vStep.end()) {
    var y = this.vStep.getCurrent();
    var yScreen = this.yToScreen(y);

    // use scientific notation when necessary
    if (Math.abs(y) > 1e6) {
      y = y.toExponential();
    }
    else if (Math.abs(y) < 1e-4) {
      if (Math.abs(y) > this.vStep.getStep()/2)
        y = y.toExponential();
      else 
        y = 0;
    }

    // create the text of the label
    var content = document.createTextNode(y);
    var labelText = document.createElement("DIV");
    labelText.appendChild(content);
    labelText.className = "graph-axis-text graph-axis-text-vertical";
    labelText.style.position = "absolute";
    labelText.style.whiteSpace = "nowrap";
    labelText.style.textAlign = "right";
    this.main.axisLeft.appendChild(labelText);
    
    // create the label line
    var labelLine = document.createElement("DIV");
    labelLine.className = "graph-axis-grid graph-axis-grid-vertical";
    labelLine.className = "graph-axis";
    labelLine.style.position = "absolute";
    labelLine.style.borderTopStyle = "solid";
    labelLine.style.width = "5px";
    this.main.axisLeft.appendChild(labelLine);    

    // create the grid line
    var labelGridLine = document.createElement("DIV");
    labelGridLine.className = (y != 0) ? "graph-axis-grid graph-axis-grid-minor" :
                                         "graph-axis-grid graph-axis-grid-major";
    labelGridLine.style.position = "absolute";
    labelGridLine.style.left = "0px";
    labelGridLine.style.width = "100%";
    labelGridLine.style.borderTopStyle = "solid";
    this.frame.vgrid.appendChild(labelGridLine);
    
    // position the label text and line vertically
    var h = labelText.offsetHeight;
    labelText.style.top  = links.Graph.px(yScreen - h/2);
    labelLine.style.top = links.Graph.px(yScreen);
    labelGridLine.style.top = links.Graph.px(yScreen);

    // calculate the widest label so far.
    maxWidth = Math.max(maxWidth, labelText.offsetWidth); 
  
    this.vStep.next();
  }
  
  // right align all elements
  maxWidth += this.main.zoomButtons.clientWidth; // append width of the zoom buttons
  var maxWidthPx = links.Graph.px(maxWidth);
  for (i = 0; i < this.main.axisLeft.childNodes.length; i++) {
    this.main.axisLeft.childNodes[i].style.left = 
      links.Graph.px(maxWidth - this.main.axisLeft.childNodes[i].offsetWidth);
  }
  
  // resize the axis
  this.main.axisLeft.style.left = links.Graph.px(this.mainPadding);
  this.main.axisLeft.style.top = links.Graph.px(this.mainPadding);
  this.main.axisLeft.style.height = links.Graph.px(this.axisOffset + 1);
  this.main.axisLeft.style.width = links.Graph.px(maxWidth);

  this.main.axisRight.style.left = 
    links.Graph.px(this.main.clientWidth - this.legendWidth - this.mainPadding - 2); 
  this.main.axisRight.style.top = links.Graph.px(this.mainPadding);
  this.main.axisRight.style.height = links.Graph.px(this.axisOffset + 1);
  
  
  var testEnd = new Date(); // TODO: cleanup
  //document.title += " v:" +(testEnd - testStart) + "ms"; // TODO: cleanup
}


/**
 * Draw all events that are provided in the data on the graph
 */ 
links.Graph.prototype._redrawData = function() {
  var testStart = new Date(); // TODO: cleanup
  
  this._calcConversionFactor();

  // determine the size of the graph
  var start = this._screenToTime(-this.axisMargin);
  var end = this._screenToTime(this.frame.clientWidth + this.axisMargin);
  var width = this.frame.clientWidth + 2*this.axisMargin;
/*
  // TODO: use axisMargin?
  var start = this._screenToTime(0);
  var end = this._screenToTime(this.frame.clientWidth);
  var width = this.frame.clientWidth;
  */
  
  var graph = this.frame.canvas.graph;
  var ctx = graph.getContext("2d");

  // clear the graph. 
  // It is important to clear the old size of the graph (before resizing), else 
  // Safari does not clear the whole graph.
  ctx.clearRect(0, 0, graph.height, graph.width);  

  // resize the graph element
  var left = this.timeToScreen(start);
  var right = this.timeToScreen(end);
  var width = right - left;
  var height = this.axisOffset;

  graph.style.left = links.Graph.px(left);
  graph.width = width;
  graph.height = height;

  var offset = parseFloat(graph.style.left);

  // draw the graph(s)
  for (var col = 0, colCount = this.data.length; col < colCount; col++) {
    var style = this._getLineStyle(col);
    var color = this._getLineColor(col);
    var width = this._getLineWidth(col);
    var radius = this._getLineRadius(col);
    var visible = this._getLineVisible(col);
    var data = this.data[col].data;
    
    // determine the first and last row inside the visible area
    rowRange = 
      this._getVisbleRowRange(data, start, end, this.data[col].visibleRowRange);
    this.data[col].visibleRowRange = rowRange;

    // choose a step size, depending on the width of the screen in pixels
    // and the number of data points.
    if ( this.autoDataStep ) {
      // skip data points in case of much data
      var rowCount = (rowRange.end - rowRange.start);
      var canvasWidth = (this.frame.clientWidth + 2 * this.axisMargin);
      var rowStep = Math.max(Math.floor(rowCount / canvasWidth), 1);
    }
    else {
      // draw all data points
      var rowStep = 1;
    }
    
    if (visible) {      
      if (style == "line" || style == "dot-line") {
        // draw line
        ctx.strokeStyle = color;
        ctx.lineWidth = width;

        ctx.beginPath();
        row = rowRange.start;
        while (row <= rowRange.end) {
          // find the first data row with a non-null value
          while (row <= rowRange.end && data[row].value == null) {
            row += rowStep;
          }
          if (row <= rowRange.end) {
            // move to the first non-null data point
            value = data[row].value;
            x = this.timeToScreen(data[row].date) - offset;
            y = this.yToScreen(value);
            ctx.moveTo(x, y);
            
            /* TODO: implement fill style
            ctx.moveTo(x, this.yToScreen(0));
            ctx.lineTo(x, y);
            */
            row += rowStep;
          }
          
          // draw lines as long as data values are not null
          while (row <= rowRange.end && (value = data[row].value) != null) {
            x = this.timeToScreen(data[row].date) - offset;
            y = this.yToScreen(value);
            ctx.lineTo(x, y); 
            row += rowStep;
          }        

          /* TODO: implement fill style
          ctx.lineTo(x, this.yToScreen(0));
          */
        }

        /* TODO: implement fill style
        ctx.fillStyle = "rgba(255,255,0, 0.5)";
        ctx.fill();
        */

        ctx.stroke();      
      }    

      if (style == "dot" || style == "dot-line") {
        // draw dots
        var diameter = 2 * radius;
        ctx.fillStyle = color;

        ctx.beginPath();
        for (row = rowRange.start; row <= rowRange.end; row += rowStep) {
          var value = data[row].value;
          if (value != null) {
            x = this.timeToScreen(data[row].date) - offset;
            y = this.yToScreen(value);
            ctx.fillRect(x - radius, y - radius, diameter, diameter);  
          }
        }
        ctx.stroke();
      }
    }
  }
  
  var testEnd = new Date(); // TODO: cleanup
  // console.log("redraw took " + (testEnd - testStart) + " milliseconds"); // TODO: cleanup
}

/**
 * Average a range of values in the given data table
 * @param {Array}  data    table containing objects with parameters date and value
 * @param {Number} start   index to start averaging
 * @param {Number} length  the number of values to average
 * @return {Object}        An object with average values for the date and value
 * 
 */ 
 // TODO: this method is not used. Delete it?
links.Graph.prototype._average = function(data, start, length) {
  var sumDate = 0;
  var countDate = 0;
  var sumValue = 0;
  var countValue = 0;
  
  for (row = start, end = Math.min(start+length, data.length); row < end; row++) {
    var d = data[row];
    if (d.date !== undefined) {
      sumDate += d.date.getTime();
      countDate += 1;
    }
    if (d.value !== undefined) {
      sumValue += d.value;
      countValue += 1;
    }
  }
  
  var avgDate = new Date(Math.round(sumDate / countDate));
  var avgValue = sumValue / countValue;
  
  return {"date": avgDate, "value": avgValue};
}


/**
 * Draw all events that are provided in the data on the graph
 */ 
links.Graph.prototype._redrawLegend = function() {
  // Calculate the number of functions that need a legend entry
  var legendCount = 0;
  for (var col = 0, len = this.data.length; col < len; col++) {
    if (this._getLineLegend(col) == true)
      legendCount ++;
  }
  
  if (legendCount == 0 || (this.legend && this.legend.visible === false) ) {
    // no legend entries
    if (this.main.legend) {
      // remove if existing
      this.main.removeChild(this.main.legend);
      this.main.legend = undefined;
    }

    this.legendWidth = 0;
    return;
  }

  var scrollTop = 0;
  if (!this.main.legend) {
    // create the legend
    this.main.legend = document.createElement("DIV");
    this.main.legend.className = "graph-legend";
    this.main.legend.style.position = "absolute";
    this.main.legend.style.overflowY = "auto";

    this.main.appendChild(this.main.legend);
  } else {
    // clear any existing contents of the legend
    scrollTop = this.main.legend.scrollTop;
    while (this.main.legend.hasChildNodes()) {
      this.main.legend.removeChild(this.main.legend.lastChild);
    } 
  }

  var maxWidth = 0;
  for (var col = 0, len = this.data.length; col < len; col++) {
    var showLegend = this._getLineLegend(col);
    
    if (showLegend) {
      var color = this._getLineColor(col);
      var label = this.data[col].label;
      
      var divLegendItem = document.createElement("DIV");
      divLegendItem.className = "graph-legend-item";
      this.main.legend.appendChild(divLegendItem);

      if (this.legend && this.legend.toggleVisibility) {
        // show a checkbox to show/hide graph
        var chkShow = document.createElement("INPUT");
        chkShow.type = "checkbox";
        chkShow.checked = this._getLineVisible(col);
        chkShow.defaultChecked = this._getLineVisible(col);    // for IE
        chkShow.style.marginRight = links.Graph.px(this.mainPadding);
        chkShow.col = col; // store its column number
        
        var me = this;
        chkShow.onmousedown = function (event) {
          me._setLineVisible(this.col, !this.checked );
          me._checkSize();
          me.redraw();          
        }       
        
        divLegendItem.appendChild(chkShow);
      }

      var spanColor = document.createElement("SPAN");
      spanColor.style.backgroundColor = color;
      spanColor.innerHTML = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
      divLegendItem.appendChild(spanColor);

      var text = document.createTextNode(" " + label);
      divLegendItem.appendChild(text);
      // TODO: test on IE
      
      maxWidth = Math.max(maxWidth, divLegendItem.clientWidth);
    }
  }

  // position the legend in the upper right corner
  // TODO: make location customizable
  this.main.legend.style.top = links.Graph.px(this.mainPadding);
  this.main.legend.style.height = "auto";
  var scroll = false;
  if (this.main.legend.clientHeight > (this.axisOffset - 1)) {
    this.main.legend.style.height = links.Graph.px(this.axisOffset - 1) ;
    scroll = true;
  }

  if (this.legend && this.legend.width) {
    this.main.legend.style.width = this.legend.width;
  }
  else if (scroll) {
    this.main.legend.style.width = "auto";
    this.main.legend.style.width = links.Graph.px(this.main.legend.clientWidth + 40); // adjust for scroll bar width
  }
  else {
    this.main.legend.style.width = "auto";
    this.main.legend.style.width = links.Graph.px(this.main.legend.clientWidth + 5);  // +5 to prevent wrapping text
  }

  this.legendWidth = 
    (this.main.legend.offsetWidth ? this.main.legend.offsetWidth : this.main.legend.clientWidth) +
     this.mainPadding; // TODO: test on IE6

  this.main.legend.style.left = links.Graph.px(this.main.clientWidth - this.legendWidth); 

  // restore the previous scroll position
  if (scrollTop) {
    this.main.legend.scrollTop = scrollTop;
  }
}

/**
 * Determines 
 * @param data {Array}      An array containing objects with parameters 
 *                          d (datetime) and v (value)
 * @param start {Date}      The start date of the visible range
 * @param end {Date}        The end date of the visible range
 * @return {object}         Range object containing start row and end row
 *                            range.start {int}  row number of first visible row
 *                            range.end   {int}  row number of last visible row +1
 *                                               (this can be the rowcount +1)
 */ 
links.Graph.prototype._getVisbleRowRange = function(data, start, end, oldRowRange) {
  if (!data) {
    data = [];
  }
  var rowCount = data.length;

  // initialize
  var rowRange = {"start": 0, "end": (rowCount-1)};
  if (oldRowRange !== undefined) {
    rowRange.start = oldRowRange.start;
    rowRange.end = oldRowRange.end;
  }
  
  // check if the current range does not exceed the actual number of rows
  if (rowRange.start > rowCount - 1 && rowCount > 0) {
    rowRange.start = rowCount - 1;
  }
    
  if (rowRange.end > rowCount - 1) {
    rowRange.end = rowCount - 1;
  }
  
  // find the first visible row. Start searching at the previous first visible row
  while (rowRange.start > 0 && 
         data[rowRange.start].date.valueOf() > start.valueOf()) {
    rowRange.start--;
  }
  while (rowRange.start < rowCount-1 && 
         data[rowRange.start].date.valueOf() < start.valueOf()) {
    rowRange.start++;
  }

  // find the last visible row. Start searching at the previous last visible row
  while (rowRange.end > rowRange.start && 
         data[rowRange.end].date.valueOf() > end.valueOf()) {
    rowRange.end--;
  }
  while (rowRange.end < rowCount-1 && 
         data[rowRange.end].date.valueOf() < end.valueOf()) {
    rowRange.end++;
  }

  return rowRange;
}


/**
 * Determines the row range of a datatable
 * @param data {Array}      An array containing objects with parameters 
 *                          d (datetime) and v (value)
 * @param start {Date}      The start date of the visible range
 * @param end {Date}        The end date of the visible range
 * @return {object}         Range object containing start row and end row
 *                            range.start {Date} first date in the data
 *                            range.end   {Date} last date in the data
 */ 
links.Graph.prototype._getRowRange = function(data) {
  if (!data) {
    data = [];
  }
  
  var rowRange = {
    "min": new Date(),
    "max": new Date()
  };

  if (data.length > 0) {
      rowRange.min = data[0].date;
      rowRange.max = data[0].date;    
  }

  for (var row = 0, rows = data.length; row < rows; row++) {
    var d = data[row].date;
    if (d !== undefined) {
      rowRange.min = Math.min(d, rowRange.min);
      rowRange.max = Math.max(d, rowRange.max);      
    }
  }

  if (rowRange.min !== undefined) {
    rowRange.min = new Date(rowRange.min);
  }
  if (rowRange.max !== undefined) {
    rowRange.max = new Date(rowRange.max);
  }

  return rowRange;
}

/**
 * Calculate the maximum and minimum value of all graphs in the provided data
 * table.
 * @param data {Array}      An array containing objects with parameters 
 *                          d (datetime) and v (value)
 * @return {Object}         An object with parameters min and max (both numbers)
 */ 
links.Graph.prototype._getDataRange = function(data) {
  if (!data) {
    data = [];
  }
  
  var dataRange = {
    "min": -10, 
    "max": 10
  };
  
  // initialize min and max
  if (data.length > 0) {
    dataRange.min = data[0].value;
    dataRange.max = data[0].value;
  }  
  
  for (var row = 1, rows = data.length; row < rows; row++) {
    dataRange.min = Math.min(data[row].value, dataRange.min);
    dataRange.max = Math.max(data[row].value, dataRange.max);      
  }

  return dataRange;
}


/**
 * Returns a string with the style for the given column in data.
 * Available styles are "dot", "line", or "dot-line"
 * @param {int} column    The column number 
 * @return {string} style The style for this line
 */
links.Graph.prototype._getLineStyle = function(column) {
  if (this.lines && column < this.lines.length) {
    var line = this.lines[column];
    if (line && line.style != undefined)
      return line.style.toLowerCase();
  }
  
  if (this.line && this.line.style != undefined)
      return this.line.style.toLowerCase();
  
  return "line";
}

/**
 * Returns a string with the color for the given column in data.
 * @param {int} column    The column number 
 * @return {string} color The color for this line
 */
links.Graph.prototype._getLineColor = function(column) {
  if (this.lines && column < this.lines.length) {
    var line = this.lines[column];
    if (line && line.color != undefined)
      return line.color;
  }
  
  if (this.line && this.line.color != undefined)
      return this.line.color;
  
  if (column < this.defaultColors.length) {
    return this.defaultColors[column];
  }
  
  return "black";
}

/**
 * Returns a float with the line width for the given column in data.
 * @param {int} column        The column number 
 * @return {float} linewidthh The width for this line
 */
links.Graph.prototype._getLineWidth = function(column) {
  if (this.lines && column < this.lines.length) {
    var line = this.lines[column];
    if (line && line.width != undefined)
      return parseFloat(line.width);
  }

  if (this.line && this.line.width != undefined)
      return parseFloat(this.line.width);
  
  return 1.0;
}

/**
 * Returns a float with the line radius (radius for the dots) for the given 
 * column in data.
 * @param {int} column        The column number 
 * @return {float} lineRadius The radius for the dots on this line
 */
links.Graph.prototype._getLineRadius = function(column) {
  if (this.lines && column < this.lines.length) {
    var line = this.lines[column];
    if (line && line.radius != undefined)
      return parseFloat(line.radius);
  }

  if (this.line && this.line.radius != undefined)
      return parseFloat(this.line.radius);
  
  return 1.0;
}

/**
 * Returns whether a certain line must be displayed in the legend
 * @param {int} column            The column number 
 * @return {boolean} showLegend   Whether this line must be displayed in the legend
 */
links.Graph.prototype._getLineLegend = function(column) {
  if (this.lines && column < this.lines.length) {
    var line = this.lines[column];
    if (line && line.legend != undefined)
      return line.legend;
  }

  if (this.line && this.line.legend != undefined)
      return this.line.legend;
  
  return true;
}

/**
 * Returns whether a certain line is visible (and must be drawn)
 * @param {int} column            The column number 
 * @return {boolean} visible      True if this line is visible
 */
links.Graph.prototype._getLineVisible = function(column) {
  if (this.lines && column < this.lines.length) {
    var line = this.lines[column];
    if (line && line.visible != undefined)
      return line.visible;
  }

  if (this.line && this.line.visible != undefined)
      return this.line.visible;

  return true;
}


/**
 * Change the visibility of a line
 * @param {int} column         The column number (one based)
 * @param {boolean} visible    True if this line must be visible
 */
links.Graph.prototype._setLineVisible = function(column, visible) {
  column = parseInt(column);
  if (column < 0)
    return;
  
  if (!this.lines)
    this.lines = new Array();
    
  if (!this.lines[column])
    this.lines[column] = {};
    
  this.lines[column].visible = visible;
}

/**
 * Check if the current frame size corresponds with the end Date. If the size
 * does not correspond, the end Date is changed to match the frame size.
 * 
 * This function is used before a mousedown and scroll event, to check if 
 * the frame size is not changed (caused by resizing events on the page).
 */ 
links.Graph.prototype._checkSize = function() {
  if (this.lastMainWidth != this.main.clientWidth ||
      this.lastMainHeight != this.main.clientHeight) {

    var diff = this.main.clientWidth - this.lastMainWidth;

    // recalculate the current end Date based on the real size of the frame
    this.end = new Date((this.frame.clientWidth + diff) / (this.frame.clientWidth) * 
                       (this.end.valueOf() - this.start.valueOf()) + 
                        this.start.valueOf() );
    // startEnd is the stored end position on start of a mouse movement
    if (this.startEnd) {
      this.startEnd = new Date((this.frame.clientWidth + diff) / (this.frame.clientWidth) * 
                         (this.startEnd.valueOf() - this.start.valueOf()) + 
                          this.start.valueOf() );
    }

    // redraw the graph
    this.redraw();
  }
}

/**
 * Start a moving operation inside the provided parent element
 * @param {event}       event         The event that occurred (required for 
 *                                    retrieving the  mouse position)
 * @param {htmlelement} parentElement The parent element. All child elements 
 *                                    in this element will be moved.
 */
links.Graph.prototype._onMouseDown = function(event) {
  event = event || window.event;
  
  if (!this._moveable)
    return;
  
  // check if mouse is still down (may be up when focus is lost for example
  // in an iframe)
  if (this.leftButtonDown) {
    this.onMouseUp(event);
  }

  // only react on left mouse button down
  this.leftButtonDown = event.which ? (event.which == 1) : (event.button == 1);
  if (!this.leftButtonDown && !this.touchDown) {
    return;
  }
  
  // check if frame is not resized (causing a mismatch with the end Date) 
  this._checkSize();
  
  // get mouse position
  this.startMouseX = event.clientX || event.targetTouches[0].clientX;
  this.startMouseY =  event.clientY || event.targetTouches[0].clientY;
    
  this.startStart = new Date(this.start);
  this.startEnd = new Date(this.end);
  this.startVStart = this.vStart;
  this.startVEnd = this.vEnd;
  this.startGraphLeft = parseFloat(this.frame.canvas.graph.style.left); 
  this.startAxisLeft = parseFloat(this.frame.canvas.axis.style.left);
  
  this.frame.style.cursor = 'move';

  // add event listeners to handle moving the contents
  // we store the function onmousemove and onmouseup in the graph, so we can
  // remove the eventlisteners lateron in the function mouseUp()
  var me = this;
  this.onmousemove = function (event) {me._onMouseMove(event);};
  this.onmouseup   = function (event) {me._onMouseUp(event);};
  links.Graph.addEventListener(document, "mousemove", this.onmousemove);
  links.Graph.addEventListener(document, "mouseup", this.onmouseup);
  links.Graph.preventDefault(event);
}


/**
 * Perform moving operating. 
 * This function activated from within the funcion links.Graph._onMouseDown(). 
 * @param {event}   event  Well, eehh, the event
 */ 
links.Graph.prototype._onMouseMove = function (event) {
  event = event || window.event;

  var mouseX = event.clientX || event.targetTouches[0].clientX;
  var mouseY = event.clientY || event.targetTouches[0].clientY;
  
  // calculate change in mouse position
  var diffX = parseFloat(mouseX) - this.startMouseX;
  //var diffY = parseFloat(mouseY) - this.startMouseY;
  var diffY = this.screenToY(this.startMouseY) - this.screenToY(mouseY);
  var diffYs = mouseY - this.startMouseY;

  // FIXME: on millisecond scale this.start needs to be rounded to integer milliseconds.
  var diffMillisecs = parseFloat(-diffX) / this.frame.clientWidth * 
                      (this.startEnd.valueOf() - this.startStart.valueOf());

  this.start = new Date(this.startStart.valueOf() + Math.round(diffMillisecs));
  this.end = new Date(this.startEnd.valueOf() + Math.round(diffMillisecs));

  this.start = new Date(this.startStart.valueOf() + Math.round(diffMillisecs));
  this.end = new Date(this.startEnd.valueOf() + Math.round(diffMillisecs));

  // adjust vertical axis setting when needed
  vStartNew = this.startVStart + diffY;
  vEndNew = this.startVEnd + diffY;
  if (vStartNew < this.vMin) {
    var d = (this.vMin - vStartNew);
    vStartNew += d;
    vEndNew += d;
  }
  if (vEndNew > this.vMax) {
    var d = (vEndNew - this.vMax);
    vStartNew -= d;
    vEndNew -= d;
  }
  var epsilon = (this.vEnd - this.vStart) / 1000000;
  var movedVertically = (Math.abs(vStartNew - this.vStart) > epsilon || 
    Math.abs(vEndNew - this.vEnd) > epsilon);
  if (movedVertically) {
    this.vStart = vStartNew;
    this.vEnd = vEndNew;
  }

  if ((!this.redrawWhileMoving ||
       Math.abs(this.startAxisLeft + diffX) < this.axisMargin) && 
      !movedVertically) {
    // move the horizontal axis and data(this is fast)
    this.frame.canvas.axis.style.left = links.Graph.px(this.startAxisLeft + diffX);
    this.frame.canvas.graph.style.left = links.Graph.px(this.startGraphLeft + diffX);
  }
  else {
    // redraw the horizontal and vertical axis and the data (this is slow)
    this._redrawVerticalAxis();

    this.frame.canvas.axis.style.left = links.Graph.px(0);
    this.startAxisLeft = -diffX;
    this._redrawHorizontalAxis();
    
    this.frame.canvas.graph.style.left = links.Graph.px(0);
    this.startGraphLeft = -diffX - this.axisMargin;
    this._redrawData();    
  }
  this._redrawAxisLeftMajorLabel(); // reposition the left major label
  
  // fire a rangechange event
  var properties = {'start': new Date(this.start), 
                    'end':   new Date(this.end)};
  this.trigger('rangechange', properties);  

  links.Graph.preventDefault(event);
}


/**
 * Stop moving operating.
 * This function activated from within the funcion links.Graph._onMouseDown(). 
 * @param {event}  event   The event
 */ 
links.Graph.prototype._onMouseUp = function (event) {
  this.frame.style.cursor = 'auto';
  this.leftButtonDown = false;
  
  this.frame.canvas.axis.style.left = links.Graph.px(0);
  this._redrawHorizontalAxis();

  this.frame.canvas.graph.style.left = links.Graph.px(0);
  this._redrawData();

  // fire a rangechanged event
  var properties = {'start': new Date(this.start), 
                    'end':   new Date(this.end)};
  this.trigger('rangechanged', properties);  
  
  // remove event listeners
  links.Graph.removeEventListener(document, "mousemove", this.onmousemove);
  links.Graph.removeEventListener(document, "mouseup",   this.onmouseup); 
  links.Graph.preventDefault(event);
}



/**
 * Event handler for touchstart event on mobile devices 
 */ 
links.Graph.prototype._onTouchStart = function(event) {
  this.touchDown = true;
  
  var me = this;
  this.ontouchmove = function (event) {me._onTouchMove(event);};
  this.ontouchend   = function (event) {me._onTouchEnd(event);};
  links.Graph.addEventListener(document, "touchmove", me.ontouchmove);
  links.Graph.addEventListener(document, "touchend", me.ontouchend);
  
  this._onMouseDown(event);
};

/**
 * Event handler for touchmove event on mobile devices 
 */ 
links.Graph.prototype._onTouchMove = function(event) {
  this._onMouseMove(event);
};

/**
 * Event handler for touchend event on mobile devices 
 */ 
links.Graph.prototype._onTouchEnd = function(event) {
  this.touchDown = false;

  links.Graph.removeEventListener(document, "touchmove", this.ontouchmove);
  links.Graph.removeEventListener(document, "touchend",   this.ontouchend); 

  this._onMouseUp(event);
};


/** 
 * Event handler for mouse wheel event, used to zoom the graph
 * Code from http://adomas.org/javascript-mouse-wheel/
 * @param {event}  event   The event
 */
links.Graph.prototype._onWheel = function(event) {
  event = event || window.event;

  if (!this._zoomable)
    return;
             
  // retrieve delta
  var delta = 0;
  if (event.wheelDelta) { /* IE/Opera. */
    delta = event.wheelDelta/120;
  } else if (event.detail) { /* Mozilla case. */
    // In Mozilla, sign of delta is different than in IE.
    // Also, delta is multiple of 3.
    delta = -event.detail/3;
  }

  // If delta is nonzero, handle it.
  // Basically, delta is now positive if wheel was scrolled up,
  // and negative, if wheel was scrolled down.
  if (delta) {
    // check if frame is not resized (causing a mismatch with the end date) 
    this._checkSize();
    
    // perform the zoom action. Delta is normally 1 or -1
    var zoomFactor = delta / 5.0; 

    if (!event.shiftKey) {
      // zoom horizontally
      var frameLeft = links.Graph._getAbsoluteLeft(this.frame);
      if (event.clientX != undefined && frameLeft != undefined ) {
        var x = event.clientX - frameLeft;      
        var zoomAroundDate = this._screenToTime(x);
      }
      else { 
        var zoomAroundDate = undefined;
      }
      this._zoom(zoomFactor, zoomAroundDate);

      // fire a rangechange event
      var properties = {'start': new Date(this.start), 
                        'end': new Date(this.end)};
      this.trigger('rangechange', properties);  
      
      // fire a rangechanged event
      var properties = {'start': new Date(this.start), 
                        'end': new Date(this.end)};
      this.trigger('rangechanged', properties); 
    }
    else {
      // zoom vertically
      var frameTop = links.Graph._getAbsoluteTop(this.frame);
      if (event.clientY != undefined && frameTop != undefined ) {
        var y = event.clientY - frameTop;      
        var zoomAroundValue = this.screenToY(y);
      }
      else { 
        var zoomAroundValue = undefined;
      }
      this._zoomVertical (zoomFactor, zoomAroundValue);
      
    }
  }

  // Prevent default actions caused by mouse wheel.
  // That might be ugly, but we handle scrolls somehow
  // anyway, so don't bother here..
  if (event.preventDefault)
    event.preventDefault();
	event.returnValue = false;
}

/**
 * Retrieve the absolute left value of a DOM element
 * @param {DOM element} elem    A dom element, for example a div
 * @return {number} left        The absolute left position of this element
 *                              in the browser page.
 */ 
links.Graph._getAbsoluteLeft = function(elem)
{
  var left = 0;
  while( elem != null ) {
    left += elem.offsetLeft;
    left -= elem.scrollLeft;
    elem = elem.offsetParent;
  }
  if (!document.body.scrollLeft && window.pageXOffset) {
      // FF
      left -= window.pageXOffset;
  }
  return left;
}

/**
 * Retrieve the absolute top value of a DOM element
 * @param {DOM element} elem    A dom element, for example a div
 * @return {number} top         The absolute top position of this element
 *                              in the browser page.
 */ 
links.Graph._getAbsoluteTop = function(elem)
{
  var top = 0;
  while( elem != null ) {
    top += elem.offsetTop;
    top -= elem.scrollTop;
    elem = elem.offsetParent;
  }
  if (!document.body.scrollTop && window.pageYOffset) {
      // FF
      top -= window.pageYOffset;
  }
  return top;
}


/**
 * Set a new value for the visible range int the Graph.
 * Set start to null to include everything from the earliest date to end.
 * Set end to null to include everything from start to the last date.
 * Example usage: 
 *    myGraph.setVisibleChartRange(new Date("2010-08-22"),
 *                                 new Date("2010-09-13"));
 * @param {Date}   start       The start date for the graph
 * @param {Date}   end         The end date for the graph
 * @param {Boolean} redrawNow  Optional. If true (default), the graph is
 *                             automatically redrawn after the range is changed
 */
links.Graph.prototype.setVisibleChartRange = function(start, end, redrawNow) {
  // TODO: rewrite this method for the new data format
  if (start != null) {
    this.start = start;
  } else {
    // use earliest date from the data
    var startValue = null;  
    for (var col = 0, cols = this.data.length; col < cols; col++) {
      var d = this.data[col].rowRange.min;
      
      if (startValue)
        startValue = Math.min(startValue, d);
      else
        startValue = d;
    }

    if (startValue)
      this.start = new Date(startValue);
    else 
      this.start = new Date();
  }
  
  if (end != null) {  
    this.end = end;
  } else {
    // use lastest date from the data
    var endValue = null;  
    for (var col = 0, cols = this.data.length; col < cols; col++) {
      var d = this.data[col].rowRange.max;
      
      if (endValue)
        endValue = Math.max(endValue, d);
      else
        endValue = d;
    }
    
    if (endValue) {
      this.end = new Date(endValue);
    } else {
      this.end = new Date();
      this.end.setDate(this.end.getDate() + 20);
    }
  }

  // prevent start Date <= end Date
  if (this.end.valueOf() <= this.start.valueOf()) {
    this.end = new Date(this.start);
    this.end.setDate(this.end.getDate() + 20);
  }
  
  this._calcConversionFactor();  
  
  if (redrawNow == undefined) {
    redrawNow = true;
  }
  if (redrawNow) {
    this.redraw();
  }  
}

/**
 * Adjust the visible chart range to fit the contents.
 */ 
links.Graph.prototype.setVisibleChartRangeAuto = function() {
  this.setVisibleChartRange(undefined, undefined);
}

/**
 * Adjust the visible range such that the current time is located in the center 
 * of the timeline
 */ 
links.Graph.prototype.setVisibleChartRangeNow = function() {
  var now = new Date();
  
  var diff = (this.end.getTime() - this.start.getTime());
    
  var startNew = new Date(now.getTime() - diff/2);
  var endNew = new Date(startNew.getTime() + diff);
  this.setVisibleChartRange(startNew, endNew);
}

/**
 * Retrieve the current visible range in the Graph.
 * @return {Object} An object with start and end properties
 */
links.Graph.prototype.getVisibleChartRange = function() {
  var range = {
    'start': new Date(this.start),
    'end': new Date(this.end)
  };
  return range;
}

/**
 * Retrieve the current value range (range on the vertical axis)
 */ 
links.Graph.prototype.getValueRange = function() {
  return {
    'start': this.vStart,
    'end': this.vEnd
  };
}

/**
 * Set vertical value range
 * @param {Number} start        Start of the range. If undefined, start will
 *                              be set to match the minimum data value
 * @param {Number} end          End of the range. If undefined, end will
 *                              be set to match the maximum data value
 * @param {Boolean} redrawNow   Optional. If true (default) the graph is 
 *                              redrawn after the range has been changed
 */ 
links.Graph.prototype.setValueRange = function(start, end, redrawNow) {
  this.vStart = start ? Number(start) : undefined;
  this.vEnd = end ? Number(end) : undefined;
  
  if (this.vEnd <= this.vStart) {
    this.vEnd = undefined;
  }
  
  if (redrawNow == undefined) {
    redrawNow = true;
  }
  if (redrawNow) {
    this.redraw();
  }  
}

/**
 * Adjust the vertical range to auto fit the contents
 */ 
links.Graph.prototype.setValueRangeAuto = function() {
  this.setValueRange(undefined, undefined);
}


/** ------------------------------------------------------------------------ **/


/** 
 * Event listener (singleton)
 */ 
links.events = links.events || {
  'listeners': [],

  /**
   * Find a single listener by its object
   * @param {Object} object
   * @return {Number} index  -1 when not found 
   */  
  'indexOf': function (object) {
    var listeners = this.listeners;
    for (var i = 0, iMax = this.listeners.length; i < iMax; i++) {
      var listener = listeners[i];
      if (listener && listener.object == object) {
        return i;
      }
    }
    return -1;
  },

  /**
   * Add an event listener
   * @param {Object} object
   * @param {String} event       The name of an event, for example 'select'
   * @param {function} callback  The callback method, called when the 
   *                             event takes place
   */ 
  'addListener': function (object, event, callback) {
    var index = this.indexOf(object);
    var listener = this.listeners[index]; 
    if (!listener) {
      listener = {
        'object': object,
        'events': {}
      };
      this.listeners.push(listener);
    }
    
    var callbacks = listener.events[event];
    if (!callbacks) {
      callbacks = [];
      listener.events[event] = callbacks;
    }
    
    // add the callback if it does not yet exist
    if (callbacks.indexOf(callback) == -1) {
      callbacks.push(callback);
    }
  },
  
  /**
   * Remove an event listener
   * @param {Object} object      
   * @param {String} event       The name of an event, for example 'select'
   * @param {function} callback  The registered callback method
   */ 
  'removeListener': function (object, event, callback) {
    var index = this.indexOf(object);
    var listener = this.listeners[index]; 
    if (listener) {
      var callbacks = listener.events[event];      
      if (callbacks) {
        var index = callbacks.indexOf(callback);
        if (index != -1) {
          callbacks.splice(index, 1);
        }
        
        // remove the array when empty
        if (callbacks.length == 0) {
          delete listener.events[event];
        }
      }

      // count the number of registered events. remove listener when empty
      var count = 0;
      var events = listener.events;
      for (var event in events) {
        if (events.hasOwnProperty(event)) {
          count++;
        }
      }
      if (count == 0) {
        delete this.listeners[index];
      }
    }
  },

  /**
   * Remove all registered event listeners
   */ 
  'removeAllListeners': function () {
    this.listeners = [];
  },
  
  /**
   * Trigger an event. All registered event handlers will be called
   * @param {Object} object
   * @param {String} event
   * @param {Object} properties (optional)
   */ 
  'trigger': function (object, event, properties) {
    var index = this.indexOf(object);
    var listener = this.listeners[index]; 
    if (listener) {
      var callbacks = listener.events[event];
      if (callbacks) {
        for (var i = 0, iMax = callbacks.length; i < iMax; i++) {
          callbacks[i](properties);
        }
      }
    }
  }
};


/** ------------------------------------------------------------------------ **/


/**
 * Add and event listener. Works for all browsers
 * @param {DOM Element} element    An html element
 * @param {string}      action     The action, for example "click", 
 *                                 without the prefix "on"
 * @param {function}    listener   The callback function to be executed
 * @param {boolean}     useCapture
 */ 
links.Graph.addEventListener = function (element, action, listener, useCapture) {
  if (element.addEventListener) {
    if (useCapture === undefined)
      useCapture = false;
      
    if (action === "mousewheel" && navigator.userAgent.indexOf("Firefox") >= 0) {
      action = "DOMMouseScroll";  // For Firefox
    }
      
    element.addEventListener(action, listener, useCapture);
  } else {    
    element.attachEvent("on" + action, listener);  // IE browsers
  }
};

/**
 * Remove an event listener from an element
 * @param {DOM element}  element   An html dom element
 * @param {string}       action    The name of the event, for example "mousedown"
 * @param {function}     listener  The listener function
 * @param {boolean}      useCapture
 */ 
links.Graph.removeEventListener = function(element, action, listener, useCapture) {
  if (element.removeEventListener) {
    // non-IE browsers
    if (useCapture === undefined)
      useCapture = false;    
          
    if (action === "mousewheel" && navigator.userAgent.indexOf("Firefox") >= 0) {
      action = "DOMMouseScroll";  // For Firefox
    }
      
    element.removeEventListener(action, listener, useCapture); 
  } else {
    // IE browsers
    element.detachEvent("on" + action, listener);
  }
};


/**
 * Stop event propagation
 */ 
links.Graph.stopPropagation = function (event) {
  if (!event) 
    var event = window.event;
  
  if (event.stopPropagation) {
    event.stopPropagation();  // non-IE browsers
  }
  else {
    event.cancelBubble = true;  // IE browsers
  }
}


/**
 * Cancels the event if it is cancelable, without stopping further propagation of the event.
 */ 
links.Graph.preventDefault = function (event) {
  if (!event) 
    var event = window.event;
  
  if (event.preventDefault) {
    event.preventDefault();  // non-IE browsers
  }
  else {    
    event.returnValue = false;  // IE browsers
  }
}
