var width = 0;
var height = 0;

var spotWidth = 202;
var spotHeight = 202;

var squaresWidth = 0;
var squaresHeight = 0;

var total = 0;
var firstTime = true;

var stardedLoading = false;
var delete_sensorid = 0;
var delete_dataid = 0;

var start_time = 0;
var end_time = 0;
var current_time = 0;

var linkArr = new Array();
var interval = null;

$(document).ready(function(){
	init();
});

google.load("visualization", "1");
var data = undefined;
var timeline = undefined;
google.setOnLoadCallback(drawVisualization); 

function init() {
    

	width = $("#cardSlots").width();
	height = $("#cardSlots").height();
	
	squaresWidth = Math.floor(width/spotWidth);
	squaresHeight = Math.floor(height/spotHeight);
	
	total = squaresWidth*squaresHeight;
  
	var i = 0;
	$('.slot').each(function(index) {
		i++;
    	$(this).data( 'number', i ).attr( 'id', 'slot'+i ).appendTo( '#cardSlots' ).droppable( {
      		accept:  '.widget',
      		hoverClass: 'hovered',
      		drop: handleDrop
    	} );
	});
	
	//$('.used').text('X');
	$('.used').droppable( 'disable' );
	
	$('.widget').draggable({
		      containment: '#content',
		      cursor: 'move',
		      iframeFix: true,
		      handle: '.move',
		      /*cursorAt: { cursor: "crosshair", top:-10, left:-10},*/
		      scroll: false,
		      revert: false,
		      helper: 'clone',
		      opacity: 0.7,
		      drag: handleDragging
	});
	
	reindex();
}

function handleDragging(event, ui){
	$("#cardSlots div.slot").css({'background':'none'});
	var rightSpace = squaresWidth-($('.hovered').data( 'number' )-Math.floor($('.hovered').data('number')/squaresWidth)*squaresWidth);
	var bottomSpace = Math.ceil((total-$('.hovered').data( 'number' ))/squaresWidth);
	var number = $('.hovered').data( 'number' );
	var width = $(this).attr( 'w' );
	var height = $(this).attr( 'h' );
	rightSpace++;
	bottomSpace++;
	if(rightSpace == 5){rightSpace = 1}
	var canDrop = true;
	if((rightSpace >= width) && (bottomSpace >= height) && rightSpace <= squaresWidth ){
			for(var i = 0; i < width; i++){
				var selectedx = number+i;
				for(var b = 0; b < height; b++){
					var selectedy = selectedx+(b*squaresWidth);
					if($('#slot'+selectedy).hasClass('ui-droppable-disabled')){
						canDrop = false;
					}
				}
			}
	}else{
	  	canDrop = false;
	}
	
	$('.ui-droppable').css({'background-color':"#FFF"});
	//$('.used').css({'background-color':"#CCC"});
	if((rightSpace >= $(this).attr('w')) && (bottomSpace >= $(this).attr('h')) && rightSpace <= squaresWidth && canDrop ){
		for(var i = 0; i < width; i++){
			var selectedx = number+i;
			for(var b = 0; b < height; b++){
				var selectedy = selectedx+(b*squaresWidth);
				//$('#slot'+selectedy).css({'background-color':"#00FF00"});
				if(width == 1 && height == 1){
					$('#slot'+selectedy).css({'background':"url(images/hover/hoverImage1.jpg) no-repeat top left"});
				}else if(width == 2 && height == 1){
					$('#slot'+selectedy).css({'background':"url(images/hover/hoverImage2.jpg) no-repeat top left"});
					if(i == 1){
						$('#slot'+selectedy).css({'background':"url(images/hover/hoverImage2.jpg) no-repeat top right"});
					}
				}else if(width == 1 && height == 2){
					$('#slot'+selectedy).css({'background':"url(images/hover/hoverImage3.jpg) no-repeat top left"});
					if(b == 1){
						$('#slot'+selectedy).css({'background':"url(images/hover/hoverImage3.jpg) no-repeat bottom left"});
					}
				}else if(width == 2 && height == 2){
					$('#slot'+selectedy).css({'background':"url(images/hover/hoverImage3.jpg) no-repeat top left"});
					if(b == 1){
						$('#slot'+selectedy).css({'background':"url(images/hover/hoverImage3.jpg) no-repeat bottom left"});
					}
					if(b == 1){
						$('#slot'+selectedy).css({'background':"url(images/hover/hoverImage3.jpg) no-repeat bottom left"});
					}
					if(b == 1 && i == 1){
						$('#slot'+selectedy).css({'background':"url(images/hover/hoverImage3.jpg) no-repeat bottom right"});
					}
				}
				
			}
		}
	}else{
		//$('.hovered').css({'background-color':"#FF0000"});
	}
	
	$(this).css({'width':(spotWidth-2)*$(this).attr('w'), 'height':(spotHeight-2)*$(this).attr('h')});
	$(this).css('background:none!important;');
	
}

function stopDragging(event, ui){
	$("#cardSlots div.slot").css({'background-color':'#FFF'});
	$("#cardSlots div.slot").css({'background':'none'});
	//$(this).css({'width':'80px', 'height':'50px'});
	$(this).html("");
	stardedLoading = false;
	reindex();
}

function handleDrop( event, ui ) {
  $("#cardSlots div.slot").css({'background':'none'});
  var slotNumber = $(this).data( 'number' );
  var cardNumber = ui.draggable.data( 'number' );
  if(!cardNumber){
  		cardNumber = ui.draggable.attr( 'wid' );
  }
  var canDrop = true;
  var rightSpace = squaresWidth-(slotNumber-Math.floor(slotNumber/squaresWidth)*squaresWidth);
  var bottomSpace = Math.ceil((total-slotNumber)/squaresWidth);
  var width = ui.draggable.data( 'width' );
  var height = ui.draggable.data( 'height' );
  if(!height){
  		height = ui.draggable.attr( 'h' );
  }
  if(!width){
  		width = ui.draggable.attr( 'w' );
  }
  rightSpace++;
  bottomSpace++;
  if(rightSpace == 5){rightSpace = 1}
  
  if((rightSpace >= width) && (bottomSpace >= height) && rightSpace <= squaresWidth ){
		for(var i = 0; i < width; i++){
			var selectedx = slotNumber+i;
			for(var b = 0; b < height; b++){
				var selectedy = selectedx+(b*squaresWidth);
				if($('#slot'+selectedy).hasClass('ui-droppable-disabled')){
					canDrop = false;
				}
			}
		}
  }else{
  	canDrop = false;
  }

  if ( slotNumber != null && canDrop ) {
	var slots = "";
	var widgetid = cardNumber;
	var gridid = $('#cardSlots').attr('gridid');
	var appid = ui.draggable.attr('appid');
	if(ui.draggable.attr('slots')){
		var deleteslots = ui.draggable.attr('slots').split('-');
	}
	$.ajax({
		type: "GET",
		url: "setWidgetOnPlace.php",
		data: "slot="+(slotNumber-1)+"&widget="+widgetid+"&grid="+gridid+"&appid="+appid
	}).done(function( msg ) {
		if(msg == 1){
			for(var i = 0; i < width; i++){
				var selectedx = slotNumber+i;
				for(var b = 0; b < height; b++){
					var selectedy = selectedx+(b*4);
					//$('#slot'+selectedy).text('X');
					$('#slot'+selectedy).droppable( 'disable' );
					$('#slot'+selectedy).addClass('used');
					slots += "-"+selectedy;
				}
			}
			$.getJSON("getWidgetApp.php?id="+widgetid+"&tile="+(slotNumber-1), function(data) {
    			var top = Math.floor((slotNumber-1)/squaresWidth);
    			var left = (slotNumber-1) - (top*squaresWidth);
    			var browserName=navigator.appName;
    			var extraClasses = "";
    			if(width == 1 && height == 1){
    				extraClasses += "small";
    			}else if(width == 2 && height == 1){
    				extraClasses += "wide";
    			}else if(width == 1 && height == 2){
    				extraClasses += "height";
    			}else if(width == 2 && height == 2){
    				extraClasses += "big";
    			}
    			if(ui.draggable.find('.title').text() == ""){
    				var title = "new";
    			}else{
    				var title = ui.draggable.find('.title').text();
    			}
    			if(ui.draggable.find('.share').size() == 1){
    				var share = '<div class="share"></div>';
    			}else{
    				var share = "";
    			}
    			if(ui.draggable.find('.settings').size() == 1){
    				var settings = '<div class="settings"></div>';
    			}else{
    				var settings = "";
    			}
    			if(ui.draggable.find('.details').size() == 1){
    				var details = '<a href="" rel="detailView[iframe]" class="details" title="details"></a>';
    			}else{
    				var details = "";
    			}
    			
    			var updateclass = "";
    			if(data.update == "true"){
    				updateclass = "newdata";
    			}

    			if (browserName=="Microsoft Internet Explorer"){
    				$('<div class="widget '+extraClasses+'" slots="'+slots+'" appid="'+ui.draggable.attr( 'appid' )+'" wid="'+ui.draggable.attr( 'wid' )+'" t="'+(slotNumber-1)+'" w="'+width+'" h="'+height+'" style="top:'+ ((top*(spotHeight-2))) +'px; left:'+ (((left*(spotWidth-2)))+5) +'px; width:'+ (width*(spotWidth-2)-10) +'px; height:'+ height*(spotHeight-2) +'px; border:none;" id="widget'+ (slotNumber-1) +'"><div class="helper '+updateclass+'"><div class="inner"><div class="delete"></div><div class="move"></div>'+details+share+settings+'</div><span class="title">'+title+'</span></div><iframe style="width:'+ ((width*(spotWidth-2))-13) +'px; height:'+ height*(spotHeight-2)-81 +'px;" frameborder="0" scrolling="no" link="'+data.viewUrl+'" settings="false" share="false" size="'+width+'x'+height+'" src="'+data.viewUrl+"&start_time="+start_time+"&end_time="+end_time+"&current_time="+current_time+'"> </iframe></div>').appendTo( '#cardSlots' ).draggable( {
				      containment: '#content',
				      cursor: 'move',
				      iframeFix: true,
				      cursorAt: { cursor: "crosshair", top:-10, left:-10},
				      scroll: false,
				      revert: false,
				      helper: 'clone',
				      opacity: 0.7,
				      drag: handleDragging
				    } );
    			}else if(browserName=="Netscape"){
    				$('<div class="widget '+extraClasses+'" slots="'+slots+'" appid="'+ui.draggable.attr( 'appid' )+'" wid="'+ui.draggable.attr( 'wid' )+'" t="'+(slotNumber-1)+'" w="'+width+'" h="'+height+'" style="top:'+ ((top*spotHeight)-top/3) +'px; left:'+ (((left*spotWidth)-left/3)+5) +'px; width:'+ (((width*spotWidth)-(width/3))-10) +'px; height:'+ ((height*spotHeight)-(height/3)) +'px; border:none;" id="widget'+ (slotNumber-1) +'"><div class="helper '+updateclass+'"><div class="inner"><div class="delete"></div><div class="move"></div>'+details+share+settings+'</div><span class="title">'+title+'</span></div><iframe style="width:'+ ((width*spotWidth)-(width/3)-13) +'px; height:'+ ((height*spotHeight)-(height/3)-81) +'px;" frameborder="0" scrolling="no" link="'+data.viewUrl+'" settings="false" share="false" size="'+width+'x'+height+'" src="'+data.viewUrl+"&start_time="+start_time+"&end_time="+end_time+"&current_time="+current_time+'"> </iframe></div>').appendTo( '#cardSlots' ).draggable( {
				      containment: '#content',
				      cursor: 'move',
				      iframeFix: true,
				      cursorAt: { cursor: "crosshair", top:-10, left:-10},
				      scroll: false,
				      revert: false,
				      helper: 'clone',
				      opacity: 0.7,
				      drag: handleDragging
				    } );
    			}else{
    				$('<div class="widget '+extraClasses+'" slots="'+slots+'" appid="'+ui.draggable.attr( 'appid' )+'" wid="'+ui.draggable.attr( 'wid' )+'" t="'+(slotNumber-1)+'" w="'+width+'" h="'+height+'" style="top:'+ (top*spotHeight) +'px; left:'+ ((left*spotWidth)+5) +'px; width:'+ ((width*spotWidth)-10) +'px; height:'+ height*spotHeight +'px; border:none;" id="widget'+ (slotNumber-1) +'"><div class="helper '+updateclass+'"><div class="inner"><div class="delete"></div><div class="move"></div>'+details+share+settings+'</div><span class="title">'+title+'</span></div><iframe style="width:'+ ((width*spotWidth)-13) +'px; height:'+ height*spotHeight-81 +'px;" frameborder="0" scrolling="no" link="'+data.viewUrl+'" settings="false" share="false" size="'+width+'x'+height+'" src="'+data.viewUrl+"&start_time="+start_time+"&end_time="+end_time+"&current_time="+current_time+'"> </iframe></div>').appendTo( '#cardSlots' ).draggable( {
				      containment: '#content',
				      cursor: 'move',
				      iframeFix: true,
				      cursorAt: { cursor: "crosshair", top:-10, left:-10},
				      scroll: false,
				      revert: false,
				      helper: 'clone',
				      opacity: 0.7,
				      drag: handleDragging
				    } );
    			}
    			
    			reloadFrames();
    			reindex();
    			
    			if(ui.draggable.attr( 'wid' ) && ui.draggable.attr( 't' )){
    				$.ajax({
						type: "GET",
						url: "deleteWidgetOfPlace.php",
						data: "wid="+ui.draggable.attr( 'wid' )+"&gid="+$("#cardSlots").attr("gridid")+"&t="+ui.draggable.attr( 't' )
					}).done(function( msg ) {
						
						for(var i = 1; i < deleteslots.length; i++){
							var s = (parseInt(deleteslots[i]));
							$('#slot'+s).droppable( 'enable' );
							$('#slot'+s).removeClass('used');
							$('#slot'+s).css({'background-color':"#FFF"});
						}
						//remove from database
						ui.draggable.remove();
					});
    			}
			});
			
		}else{
			alert("Error, uw widget kon niet worden geplaatst... "+msg);
		}
		
	});
	reindex();
    //ui.draggable.css({'width':'auto', 'height':'auto'});
    
  }
}

function reindex(){
	$("#cardSlots .widget .inner").animate({
	    height: "0px",
	    top: "25px",
	    opacity: 0
	  }, 200 );
	  
	$("#cardSlots .widget").mouseenter(function(){
      $(this).find(".inner").animate({
	    height: "25px",
	    top: "2px",
	    opacity: 1
	  }, 200 );
    }).mouseleave(function(){
      $(this).find(".inner").animate({
	    height: "1px",
	    top: "25px",
	    opacity: 0
	  }, 200 );
    });
    
    $('#cardSlots .widget .helper .inner .share').click(function(){
    	$(this).parent().parent().parent().find('iframe').attr('share', 'true');
    	$(this).parent().parent().parent().find('iframe').attr('settings', 'false');
    	reloadFrames();
    });
    $('#cardSlots .widget .helper .inner .settings').click(function(){
    	$(this).parent().parent().parent().find('iframe').attr('settings', 'true');
    	$(this).parent().parent().parent().find('iframe').attr('share', 'false');
    	reloadFrames();
    });
    
    $("#cardSlots .widget .helper .inner .delete").click(function(){
    	if($(this).parent().parent().parent().attr('slots')){
			var deleteslots = $(this).parent().parent().parent().attr('slots').split('-');
			var widget = $(this);
		    $.ajax({
				type: "GET",
				url: "deleteWidgetOfPlace.php",
				data: "wid="+$(this).parent().parent().parent().attr( 'wid' )+"&gid="+$("#cardSlots").attr("gridid")+"&t="+$(this).parent().parent().parent().attr( 't' )
			}).done(function( msg ) {
				for(var i = 1; i < deleteslots.length; i++){
					var s = (parseInt(deleteslots[i]));
					$('#slot'+s).droppable( 'enable' );
					$('#slot'+s).removeClass('used');
					$('#slot'+s).css({'background-color':"#FFF"});
				}
				widget.parent().parent().parent().remove();
			});
		}
	});
	$("#cardSlots div.slot").css({'background-color':'#FFF'});
	
	clearInterval(interval);
	interval = setInterval('moverItem()',10000);
	$("#cardSlots .widget").mouseover(
	function() {
		$(this).find(".newdata").css({backgroundPosition:"0px -50px"});
	}).mouseout(
		function(){
			$(this).find(".newdata").css({backgroundPosition:"0px 0px"});
	});
	
	$("a[rel^='detailView']").prettyPhoto();
	
	var t=setTimeout("reloadFrames()",3000);
	reloadFrames();
}

function moverItem(){
	$("#cardSlots .widget .newdata").stop().css({backgroundPosition:"0px 0px"}).animate(
		{backgroundPosition:"(-700px 0px )"}, 
		1000,
		function(){
			$(this).css({backgroundPosition:"0px 0px"});
		}
	);
}

function onTimeChange(event) {
    document.getElementById("customTime").innerHTML = "Custom Time: " + event.time;
    // adjust the end date of the event in the data table
    var start = data.getValue(0, 0);
    if (event.time > start) {
      data.setValue(0, 1, new Date(event.time));
      var now = new Date();
      timeline.redraw();
    }
  
}

function onRangeChanged(event) {
    timeline.setCustomTime(event.start.getTime()+((event.end.getTime()-event.start.getTime())/2));
    
    start_time = event.start.getTime();
    end_time = event.end.getTime();
    current_time = event.start.getTime()+((event.end.getTime()-event.start.getTime())/2);
    reloadFrames();
	
	timeline.redraw();
}

/**
   * Jump to today
*/
function today() {
	timeline.setCustomTime(new Date((new Date()).getTime()));
	var start = new Date((new Date()).getTime() - 12 * 60 * 60 * 1000 );
    var end   = new Date((new Date()).getTime() + 12 * 60 * 60 * 1000);
	timeline.setVisibleChartRange(start.getTime(), end.getTime());
	
	start_time = start.getTime();
    end_time = end.getTime();
    current_time = new Date((new Date()).getTime()).getTime();
    reloadFrames();
}

/**
   * Add a new event
*/
function add() {
    var range = timeline.getVisibleChartRange();
    var start = new Date((range.start.valueOf() + range.end.valueOf()) / 2);
    var content = document.getElementById("txtContent").value;
	if(content != ""){
		timeline.addItem({
		  'start': start, 
		  'content': content,
		  'group': 'Notes'
		});
		$('#txtContent').val("");
		//Send data to common sense
		$.getJSON("setTimelineSensorData.php?val="+content+"&date="+start.getTime()/1000, function(data) {
			//alert(data);
		});
		
		var count = data.getNumberOfRows();
		timeline.setSelection([{
		  'row': count-1
		    }]);
		//drawVisualization();
	}
}

function onItemAdd(){
	var sel = timeline.getSelection();
    if (sel.length) {
      if (sel[0].row != undefined) {
        var row = sel[0].row;
      }
    }
    
    if (row != undefined) {
    	$.getJSON("setTimelineSensorData.php?val="+timeline.getItem(row).content+"&date="+timeline.getItem(row).start.getTime()/1000, function(data) {
			//alert(data);
		});
		//drawVisualization();
    }
}

/**
  * Change the content of the currently selected event
*/
function change() {
    // retrieve the selected row
    var sel = timeline.getSelection();
    if (sel.length) {
      if (sel[0].row != undefined) {
        var row = sel[0].row;
      }
    }

    if (row != undefined) {
      var content = $("#txtContent").val();
        timeline.changeItem(row, {
          'content': content
          // Note: start, end, and group can be added here too.
        });
        
		$.getJSON("setTimelineSensorData.php?val="+$('.timeline-event-selected').remove('div').text()+"&date="+timeline.getItem(row).start.getTime()/1000, function(data) {
			//alert(data);
		});
		doDelete();
        
        $('#txtContent').val("");
        drawVisualization();
    } else {
      alert("First select an event, then press remove again");
    }
}

/**
  * Delete the currently selected event
*/
function doDelete() {
        // retrieve the selected row
        var sel = timeline.getSelection();
        if (sel.length) {
          if (sel[0].row != undefined) {
            var row = sel[0].row;
          }
        }

        if (row != undefined) {
        	$.ajax({
				type: "GET",
				url: "deleteSensorData.php",
				data: "sensorid="+this.delete_sensorid+"&dataid="+this.delete_dataid
			}).done(function( msg ) {
				alert(msg);
			});
          timeline.deleteItem(row);
          $('#txtContent').val("");
        } else {
          alert("First select an event, then press remove again");
        }
}

function onItemDelete(){
	$.ajax({
		type: "GET",
		url: "deleteSensorData.php",
		data: "sensorid="+$('.timeline-event-selected .index').attr('sensorid')+"&dataid="+$('.timeline-event-selected .index').attr('id')
	}).done(function( msg ) {
		alert(msg);
	});
}

function onItemSelect() {
        // retrieve the selected row
        var sel = timeline.getSelection();
        if (sel.length) {
          if (sel[0].row != undefined) {
            var row = sel[0].row;
          }
        }

        if (row != undefined) {
        	$('#txtContent').val($('.timeline-event-selected').text());
        	this.delete_dataid = $('.timeline-event-selected .index').attr('id');
        	this.delete_sensorid = $('.timeline-event-selected .index').attr('sensorid');
        } else {
          alert("First select an event");
        }
        
}

function onItemChange(){
	var sel = timeline.getSelection();
        if (sel.length) {
          if (sel[0].row != undefined) {
            var row = sel[0].row;
          }
        }

        if (row != undefined) {
			$.getJSON("setTimelineSensorData.php?val="+$('.timeline-event-selected').remove('div').text()+"&date="+timeline.getItem(row).start.getTime()/1000, function(data) {
				//alert(data);
			});
			doDelete();
			drawVisualization();
		}
}

// Called when the Visualization API is loaded.
function drawVisualization() {
    // Create and populate a data table.
    data = new google.visualization.DataTable();
    data.addColumn('datetime', 'start');
    data.addColumn('datetime', 'end');
    data.addColumn('string', 'content');
    data.addColumn('string', 'group');

	$.getJSON("getTimelineSensor.php?start=1&end=", function(d) {
		$.each(d, function(key, val) {
			data.addRow([new Date(val['start']*1000), ,val['content'],'Notes']);
		});
		dataloaded();
	});
}

function dataloaded(){
	var start = new Date((new Date()).getTime() - 12 * 60 * 60 * 1000 );
    var end   = new Date((new Date()).getTime() + 12 * 60 * 60 * 1000);
    
    date = new Date(start.getTime()+((end.getTime()-start.getTime())/2));
    
    start_time = start.getTime();
    end_time = end.getTime();
    current_time = date.getTime();
    
	
	// specify options
    options = {
      'width':  "100%", 
      'height': "auto", 
      'style': "box",
      'showCustomTime': true, 
      'editable': true,
      'showNavigation': true
    };
	
    // Instantiate our timeline object.
    timeline = new links.Timeline(document.getElementById('mytimeline'));
    
    // Add event listeners
    google.visualization.events.addListener(timeline, 'timechange', onTimeChange);
    google.visualization.events.addListener(timeline, 'rangechanged', onRangeChanged);
    google.visualization.events.addListener(timeline, 'select', onItemSelect);
    google.visualization.events.addListener(timeline, 'delete', onItemDelete);
    google.visualization.events.addListener(timeline, 'add', onItemAdd);
    google.visualization.events.addListener(timeline, 'change', onItemChange);
    
    // Draw our timeline with the created data and options 
    timeline.draw(data, options);

    timeline.setVisibleChartRange(start, end);
    
    timeline.setCustomTime(timeline.getVisibleChartRange().start.getTime()+((timeline.getVisibleChartRange().end.getTime()-timeline.getVisibleChartRange().start.getTime())/2));
    timeline.redraw();
    
    $('.timeline-navigation-new').remove();
    $('.timeline-navigation').append('<input type="button" class="timeline-navigation-today" value="Today" title="Jump to today" onclick="today();">');
    reindex();
}

function reloadFrames(){
	$('.widget iframe').each(function(index) {
		if($(this).attr('link') == null){
			$(this).attr('link', $(this).attr('src'));
		}
		if($(this).attr('share') == null){
			$(this).attr('share', "false");
		}
		if($(this).attr('settings') == null){
			$(this).attr('settings', "false");
		}

		$(this).attr('src', $(this).attr('link')+"&start_time="+start_time+"&end_time="+end_time+"&current_time="+current_time+"&size="+$(this).attr('size')+"&settings="+$(this).attr('settings')+"&share="+$(this).attr('share'));
		//alert($(this).attr('link')+"&start_time="+start_time+"&end_time="+end_time+"&current_time="+current_time+"&size="+$(this).attr('size')+"&settings="+$(this).attr('settings')+"&share="+$(this).attr('share'));
		$(this).parent().find('.helper .inner .details').attr('href', $(this).attr('link')+"&start_time="+start_time+"&end_time="+end_time+"&current_time="+current_time+"&size="+$(this).attr('size')+"&settings=false&share=false&details=true&iframe=true&width=800&height=600&output=embed");
	});
}
