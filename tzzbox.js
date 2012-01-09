/* Tzzbox 1.0 - 26 December 2011
 * Copyright (c) 2011 Martijn Grendelman (http://www.grendelman.net/)
 *
 */

(function( $ ) {

	var Tzzbox = {

		//Configuration
		defaultOptions: {
			cyclic: true,                  // Makes galleries cyclic, no end/begin
			effectDurations: {
				fadeOut: 400,
				fadeIn: 400
			},
			images: 'images/',             // The directory of the images, relative to this file
			keyboard: true,
			overlay: {
				background: '#000',
				opacity: 0.75
			},
			preloadHover: false,           // Preload images on mouseover
			removeTitles: true,            // Set to false if you want to keep title attributes intact
			slideshowDelay: 3,             // Delay in seconds before showing the next slide
			titleSplit: '::',              // The characters you want to split title with
			viewport: true,                // Stay within the viewport, true is recommended
      defaults: {
				flash: {
					width: 640,
					height: 480
				},
				iframe: {
					width: 600,
					height: 400
				}
			}
		},

		// Some variables
		options: {},
		images: '',
		next: 0,
		mynum: 0,
		prev: 0,
		num: 0,    // the actual number of images in the set
		maxnum: 0, // the highest index: num - 1
		links: {},
		obj_options: {},
		api: {},
		slideTimer: false,
		sliding: false,
		nav_width: false,

		init : function ( opts ) {
			Tzzbox.options = $.extend( Tzzbox.defaultOptions, opts);

			Tzzbox.links = this;

			if (/^(https?:\/\/|\/)/.test(Tzzbox.options.images)) Tzzbox.images = Tzzbox.options.images;
			else {
				var a = /tzzbox(?:-[\w\d.]+)?\.js(.*)/;
				Tzzbox.images = ($("script[src]").filter(function (i) {
					return this.src.match(a)
				}).attr('src')).replace (a, '') + Tzzbox.options.images;
			}

			return this.click(function () {
				mynum = Tzzbox.links.index($(this)); // The current index

				Tzzbox.setup_dom();
				Tzzbox.show_overlay();
				Tzzbox.init_resize();
				Tzzbox.init_buttons();
				Tzzbox.init_keys();
				Tzzbox.init_resize();
				Tzzbox.load_image(mynum, false);
				return false;

			});
		},

		setup_dom : function () {

			overlay = (
				'<div id="tzzbox_overlay">' +
					'<div id="tzzbox_object"></div>' +
					'<div id="tzzbox_details"></div>' +
					'<div id="tzzbox_prev">' +
					'</div>' +
					'<div id="tzzbox_next">' +
					'</div>' +
					'<div id="tzzbox_nav">' +
						'<span id="tzzbox_current"></span> of <span id="tzzbox_count"></span> &nbsp; ' +
						'<img src="' + Tzzbox.images + 'controller_prev.png" id="tzzbox_prevbutton" />' +
						'<img src="' + Tzzbox.images + 'controller_next.png" id="tzzbox_nextbutton" />' +
						'<img src="' + Tzzbox.images + 'controller_slideshow_play.png" id="tzzbox_slshbutton" />' +
						'<img src="' + Tzzbox.images + 'controller_close.png" class="close" />' +
					'</div>' +
				'</div>'
			);
			$('body').append(overlay);
		},

		show_overlay : function () {
			$("#tzzbox_overlay").overlay({
				mask: {
					color: '#000',
					loadSpeed: 200,
					opacity: 0.9
				},
				onClose: function () {
					Tzzbox.stop_slideshow();
					$(document).unbind("keydown");
					$('#tzzbox_overlay').remove();
				},
				top: '50%',
				load: true,
				//speed: Tzzbox.options.effectDurations.fadeIn
				speed: 'slow'
			});
			Tzzbox.api = $("#tzzbox_overlay").data("overlay");
		},

		load_prev : function () {
			if (Tzzbox.prev < Tzzbox.mynum || Tzzbox.options.cyclic) {
				Tzzbox.load_image(Tzzbox.prev, true);
			}
		},

		load_next : function () {
			if (Tzzbox.next > Tzzbox.mynum || Tzzbox.options.cyclic) {
				Tzzbox.load_image(Tzzbox.next, true);
			}
		},

		init_buttons : function () {
			$("#tzzbox_nextbutton").click(function () {
				Tzzbox.load_next();
			});

			$("#tzzbox_prevbutton").click(function () {
				Tzzbox.load_prev();
			});

			$("#tzzbox_next").click(function () {
				Tzzbox.load_next();
			});

			$("#tzzbox_prev").click(function () {
				Tzzbox.load_prev();
			});

			$("#tzzbox_slshbutton").click(function () {
				Tzzbox.toggle_slideshow();
			});
		},

		init_keys : function () {
			if (Tzzbox.options.keyboard) {
				$(document).keydown(function (e) {
					if (e.which == 39) {
						Tzzbox.load_next();
					}
					else if (e.which == 37) {
						Tzzbox.load_prev();
					}
					else if (e.which == 32) {
						Tzzbox.toggle_slideshow();
					}
					return false;
				});
			}
		},

		init_resize : function () {
			$(window).resize (function () {
				Tzzbox.place_nav();
			});
		},

		load_image : function (idx, fadeout) {

			// Get all the parameters from the link
			link = Tzzbox.links.slice(idx,idx+1);
			url = link.attr("href");
			title = link.attr("title").split(Tzzbox.options.titleSplit);
			rel=link.attr("rel");

			caption = $.trim(title[0]);
			description = $.trim(title[1]);
			opts = $.trim(title[2]);
			Tzzbox.parse_obj_options (opts);

			setname=rel.replace("set[", "").replace("]","");
			setrel='set[' + setname + ']';
			Tzzbox.num=$('[rel*="' + setrel + '"]').length;
			Tzzbox.maxnum = Tzzbox.num - 1;  // The highest index in the set, 0-based
			Tzzbox.mynum = idx;
			Tzzbox.next = Tzzbox.mynum + 1;
			if (Tzzbox.next > Tzzbox.maxnum) Tzzbox.next = 0;  // wrap forward to first
			Tzzbox.prev = Tzzbox.mynum - 1;
			if (Tzzbox.prev < 0) Tzzbox.prev = Tzzbox.maxnum;  // wrap back to last

			if (url.match(/\.swf/)) {
				Tzzbox.obj_options.obj_type = 'flash';
				Tzzbox.reposition_wrapper (url, fadeout);
			}
			/*
			else if (caption.match(/^Iframe/)) {
				Tzzbox.obj_options.obj_type = 'iframe';
				var iframe = $('<iframe/>').attr({'id': 'tzzbox_iframe', 'src': url, 'name': "tzzbox_" + (Math.random() * 99999).round()}).css({'border': 0, 'margin': 0, 'padding': 0, width: '100%', height: '100%'});
				Tzzbox.reposition_wrapper (iframe, fadeout);
			}
			*/
			else {
				Tzzbox.obj_options.obj_type = 'img';
				var img = new Image();
				$(img).load(function () {
					Tzzbox.reposition_wrapper (img, fadeout);
				})
				.error (function () {
					alert ("Image could not be loaded.");
				})
				.attr ("src", url);
			}
		},

		reposition_wrapper : function (obj, fadeout) {
			if (fadeout) {
				$("#tzzbox_overlay").fadeOut(Tzzbox.options.effectDurations.fadeOut, function () {
					Tzzbox.reposition (obj, true);
				});
			}
			else {
				Tzzbox.reposition (obj, false);
			}
		},

		reposition : function (obj, fadein) {

			// Fill in the details ;-)
			if (caption.length || description.length) {
				$("#tzzbox_details").html("<h3>" + caption + "</h3>\n" + description);
			}

			// We have the new image right here.
			if (Tzzbox.obj_options.obj_type == 'img') {
				w = obj.width;
				h = obj.height;
			}
			else if (Tzzbox.obj_options.obj_type == 'flash') {
				w = Tzzbox.options.defaults.flash.width;
				h = Tzzbox.options.defaults.flash.height;
			}
			/*
			else if (Tzzbox.obj_options.obj_type == 'iframe') {
				w = Tzzbox.options.defaults.iframe.width;
				h = Tzzbox.options.defaults.iframe.height;
			}
			*/


			calcw = false;
			calch = false;

			if (typeof (Tzzbox.obj_options.width) != 'undefined') {
				calch = Math.ceil (Tzzbox.obj_options.width / w * h);
				calcw = Tzzbox.obj_options.width;
			}
			if (typeof (Tzzbox.obj_options.height) != 'undefined') {
				calch = Tzzbox.obj_options.height;
				calcw = (calcw ? calcw :  Math.ceil (Tzzbox.obj_options.height / h * w));
			}

			h = (calch ? calch : h);
			w = (calcw ? calcw : w);

			dw = $(window).width();
			dh = $(window).height() - 70;
			t = Math.ceil((dh - h) / 2);
			l = Math.ceil((dw - w) / 2);

			if (Tzzbox.obj_options.obj_type == 'img') {
				$("#tzzbox_object").html(obj);
				$('#tzzbox_object').width(w).height(h);
				$('#tzzbox_object img').width(w).height(h);
			}
			/*
			else if (Tzzbox.obj_options.obj_type == 'iframe') {
				$("#tzzbox_object").html(obj).width(w).height(h);
			}
			*/
			else if (Tzzbox.obj_options.obj_type == 'flash') {
				$('#tzzbox_object').flashembed(obj).width(w).height(h);
			}

			// Position the overlay
			$("#tzzbox_overlay").css({
				'top': t,
				'left': l
			});

			// Fade in the navigation bar
			Tzzbox.place_nav();

			if (fadein) {
				$("#tzzbox_overlay").fadeIn(Tzzbox.options.effectDurations.fadeIn, function () {
					Tzzbox.place_nav2();
				});
			}
			else {
				Tzzbox.place_nav2();
			}
		},

		place_nav : function () {

			w = $(window).width();
			h = $(window).height();

			$("#tzzbox_current").html(Tzzbox.mynum + 1);
			$("#tzzbox_count").html(Tzzbox.num);

			$("#tzzbox_nav").fadeIn(300, function () {
			});

			if (!Tzzbox.nav_width) {
				Tzzbox.nav_width = $("#tzzbox_nav").width();
			}
			nt = h - 50;       // nav top
			nl = (w - Tzzbox.nav_width) / 2; // nav left

			$("#tzzbox_nav").css({
				"top": nt + "px",
				"left": nl + "px"
			});

		},

		place_nav2 : function () {

			oh = $("#tzzbox_object").height();
			ow = $("#tzzbox_object").width();
			nh = $("#tzzbox_prev").height();
			nw = $("#tzzbox_prev").width();

			//alert ("oh: " + oh + ", ow: " + ow + ", nh: " + nh + ", nw: " + nw);

			t = (oh - nh) / 2;
			l1 = -(nw /2);
			l2 = ow-(nw /2);

			$("#tzzbox_prev").css("top", t + "px").css("left", l1 + "px");
			$("#tzzbox_next").css("top", t + "px").css("left", l2 + "px");
		},

		parse_obj_options : function (options) {

			// reinitialize for every object
			Tzzbox.obj_options = {};
			if (opts.length) {
				allopts = opts.split(',');
				$.each(allopts, function (k, v)  {
					o = v.split(':');
					Tzzbox.obj_options[$.trim(o[0])] = $.trim (o[1]);
				});
			}
		},

		toggle_slideshow : function () {
			if (Tzzbox.sliding) {
				Tzzbox.stop_slideshow();
			}
			else {
				Tzzbox.start_slideshow();
			}
		},

		start_slideshow : function () {
			$('#tzzbox_slshbutton').attr('src', Tzzbox.images + 'controller_slideshow_stop.png');
			Tzzbox.sliding = true;
			Tzzbox.slide_next();
		},

		stop_slideshow : function () {
			$('#tzzbox_slshbutton').attr('src', Tzzbox.images + 'controller_slideshow_play.png');
			Tzzbox.sliding = false;
			window.clearTimeout(Tzzbox.slideTimer);
		},

		slide_next : function () {
			Tzzbox.load_next();
			if (Tzzbox.obj_options.obj_type == 'flash') {
				Tzzbox.stop_slideshow ();
			}
			else {
				Tzzbox.slideTimer = window.setTimeout(Tzzbox.slide_next, Tzzbox.options.slideshowDelay * 1000);
			}
		},
	}

	$.fn.tzzbox = function( method ) {

		if ( Tzzbox[method] && $.isFunction( Tzzbox[ method ]) ) {
			return Tzzbox[ method ].apply( this, Array.prototype.slice.call( arguments, 1 ));
		}
		else if ( typeof method === 'object' || ! method ) {
			return Tzzbox.init.apply( this, arguments );
		}
		else {
			$.error( 'Method ' +  method + ' does not exist on jQuery.tzzbox' );
		}


	};

})( jQuery );
/*!
 * jQuery Tools v1.2.6 - The missing UI library for the Web
 * 
 * overlay/overlay.js
 * toolbox/toolbox.expose.js
 * toolbox/toolbox.flashembed.js
 * 
 * NO COPYRIGHTS OR LICENSES. DO WHAT YOU LIKE.
 * 
 * http://flowplayer.org/tools/
 * 
 */
(function(a){a.tools=a.tools||{version:"v1.2.6"},a.tools.overlay={addEffect:function(a,b,d){c[a]=[b,d]},conf:{close:null,closeOnClick:!0,closeOnEsc:!0,closeSpeed:"fast",effect:"default",fixed:!a.browser.msie||a.browser.version>6,left:"center",load:!1,mask:null,oneInstance:!0,speed:"normal",target:null,top:"10%"}};var b=[],c={};a.tools.overlay.addEffect("default",function(b,c){var d=this.getConf(),e=a(window);d.fixed||(b.top+=e.scrollTop(),b.left+=e.scrollLeft()),b.position=d.fixed?"fixed":"absolute",this.getOverlay().css(b).fadeIn(d.speed,c)},function(a){this.getOverlay().fadeOut(this.getConf().closeSpeed,a)});function d(d,e){var f=this,g=d.add(f),h=a(window),i,j,k,l=a.tools.expose&&(e.mask||e.expose),m=Math.random().toString().slice(10);l&&(typeof l=="string"&&(l={color:l}),l.closeOnClick=l.closeOnEsc=!1);var n=e.target||d.attr("rel");j=n?a(n):null||d;if(!j.length)throw"Could not find Overlay: "+n;d&&d.index(j)==-1&&d.click(function(a){f.load(a);return a.preventDefault()}),a.extend(f,{load:function(d){if(f.isOpened())return f;var i=c[e.effect];if(!i)throw"Overlay: cannot find effect : \""+e.effect+"\"";e.oneInstance&&a.each(b,function(){this.close(d)}),d=d||a.Event(),d.type="onBeforeLoad",g.trigger(d);if(d.isDefaultPrevented())return f;k=!0,l&&a(j).expose(l);var n=e.top,o=e.left,p=j.outerWidth({margin:!0}),q=j.outerHeight({margin:!0});typeof n=="string"&&(n=n=="center"?Math.max((h.height()-q)/2,0):parseInt(n,10)/100*h.height()),o=="center"&&(o=Math.max((h.width()-p)/2,0)),i[0].call(f,{top:n,left:o},function(){k&&(d.type="onLoad",g.trigger(d))}),l&&e.closeOnClick&&a.mask.getMask().one("click",f.close),e.closeOnClick&&a(document).bind("click."+m,function(b){a(b.target).parents(j).length||f.close(b)}),e.closeOnEsc&&a(document).bind("keydown."+m,function(a){a.keyCode==27&&f.close(a)});return f},close:function(b){if(!f.isOpened())return f;b=b||a.Event(),b.type="onBeforeClose",g.trigger(b);if(!b.isDefaultPrevented()){k=!1,c[e.effect][1].call(f,function(){b.type="onClose",g.trigger(b)}),a(document).unbind("click."+m).unbind("keydown."+m),l&&a.mask.close();return f}},getOverlay:function(){return j},getTrigger:function(){return d},getClosers:function(){return i},isOpened:function(){return k},getConf:function(){return e}}),a.each("onBeforeLoad,onStart,onLoad,onBeforeClose,onClose".split(","),function(b,c){a.isFunction(e[c])&&a(f).bind(c,e[c]),f[c]=function(b){b&&a(f).bind(c,b);return f}}),i=j.find(e.close||".close"),!i.length&&!e.close&&(i=a("<a class=\"close\"></a>"),j.prepend(i)),i.click(function(a){f.close(a)}),e.load&&f.load()}a.fn.overlay=function(c){var e=this.data("overlay");if(e)return e;a.isFunction(c)&&(c={onBeforeLoad:c}),c=a.extend(!0,{},a.tools.overlay.conf,c),this.each(function(){e=new d(a(this),c),b.push(e),a(this).data("overlay",e)});return c.api?e:this}})(jQuery);
(function(a){a.tools=a.tools||{version:"v1.2.6"};var b;b=a.tools.expose={conf:{maskId:"exposeMask",loadSpeed:"slow",closeSpeed:"fast",closeOnClick:!0,closeOnEsc:!0,zIndex:9998,opacity:.8,startOpacity:0,color:"#fff",onLoad:null,onClose:null}};function c(){if(a.browser.msie){var b=a(document).height(),c=a(window).height();return[window.innerWidth||document.documentElement.clientWidth||document.body.clientWidth,b-c<20?c:b]}return[a(document).width(),a(document).height()]}function d(b){if(b)return b.call(a.mask)}var e,f,g,h,i;a.mask={load:function(j,k){if(g)return this;typeof j=="string"&&(j={color:j}),j=j||h,h=j=a.extend(a.extend({},b.conf),j),e=a("#"+j.maskId),e.length||(e=a("<div/>").attr("id",j.maskId),a("body").append(e));var l=c();e.css({position:"absolute",top:0,left:0,width:l[0],height:l[1],display:"none",opacity:j.startOpacity,zIndex:j.zIndex}),j.color&&e.css("backgroundColor",j.color);if(d(j.onBeforeLoad)===!1)return this;j.closeOnEsc&&a(document).bind("keydown.mask",function(b){b.keyCode==27&&a.mask.close(b)}),j.closeOnClick&&e.bind("click.mask",function(b){a.mask.close(b)}),a(window).bind("resize.mask",function(){a.mask.fit()}),k&&k.length&&(i=k.eq(0).css("zIndex"),a.each(k,function(){var b=a(this);/relative|absolute|fixed/i.test(b.css("position"))||b.css("position","relative")}),f=k.css({zIndex:Math.max(j.zIndex+1,i=="auto"?0:i)})),e.css({display:"block"}).fadeTo(j.loadSpeed,j.opacity,function(){a.mask.fit(),d(j.onLoad),g="full"}),g=!0;return this},close:function(){if(g){if(d(h.onBeforeClose)===!1)return this;e.fadeOut(h.closeSpeed,function(){d(h.onClose),f&&f.css({zIndex:i}),g=!1}),a(document).unbind("keydown.mask"),e.unbind("click.mask"),a(window).unbind("resize.mask")}return this},fit:function(){if(g){var a=c();e.css({width:a[0],height:a[1]})}},getMask:function(){return e},isLoaded:function(a){return a?g=="full":g},getConf:function(){return h},getExposed:function(){return f}},a.fn.mask=function(b){a.mask.load(b);return this},a.fn.expose=function(b){a.mask.load(b,this);return this}})(jQuery);
(function(){var a=document.all,b="http://www.adobe.com/go/getflashplayer",c=typeof jQuery=="function",d=/(\d+)[^\d]+(\d+)[^\d]*(\d*)/,e={width:"100%",height:"100%",id:"_"+(""+Math.random()).slice(9),allowfullscreen:!0,allowscriptaccess:"always",quality:"high",version:[3,0],onFail:null,expressInstall:null,w3c:!1,cachebusting:!1};window.attachEvent&&window.attachEvent("onbeforeunload",function(){__flash_unloadHandler=function(){},__flash_savedUnloadHandler=function(){}});function f(a,b){if(b)for(var c in b)b.hasOwnProperty(c)&&(a[c]=b[c]);return a}function g(a,b){var c=[];for(var d in a)a.hasOwnProperty(d)&&(c[d]=b(a[d]));return c}window.flashembed=function(a,b,c){typeof a=="string"&&(a=document.getElementById(a.replace("#","")));if(a){typeof b=="string"&&(b={src:b});return new j(a,f(f({},e),b),c)}};var h=f(window.flashembed,{conf:e,getVersion:function(){var a,b;try{b=navigator.plugins["Shockwave Flash"].description.slice(16)}catch(c){try{a=new ActiveXObject("ShockwaveFlash.ShockwaveFlash.7"),b=a&&a.GetVariable("$version")}catch(e){try{a=new ActiveXObject("ShockwaveFlash.ShockwaveFlash.6"),b=a&&a.GetVariable("$version")}catch(f){}}}b=d.exec(b);return b?[b[1],b[3]]:[0,0]},asString:function(a){if(a===null||a===undefined)return null;var b=typeof a;b=="object"&&a.push&&(b="array");switch(b){case"string":a=a.replace(new RegExp("([\"\\\\])","g"),"\\$1"),a=a.replace(/^\s?(\d+\.?\d*)%/,"$1pct");return"\""+a+"\"";case"array":return"["+g(a,function(a){return h.asString(a)}).join(",")+"]";case"function":return"\"function()\"";case"object":var c=[];for(var d in a)a.hasOwnProperty(d)&&c.push("\""+d+"\":"+h.asString(a[d]));return"{"+c.join(",")+"}"}return String(a).replace(/\s/g," ").replace(/\'/g,"\"")},getHTML:function(b,c){b=f({},b);var d="<object width=\""+b.width+"\" height=\""+b.height+"\" id=\""+b.id+"\" name=\""+b.id+"\"";b.cachebusting&&(b.src+=(b.src.indexOf("?")!=-1?"&":"?")+Math.random()),b.w3c||!a?d+=" data=\""+b.src+"\" type=\"application/x-shockwave-flash\"":d+=" classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\"",d+=">";if(b.w3c||a)d+="<param name=\"movie\" value=\""+b.src+"\" />";b.width=b.height=b.id=b.w3c=b.src=null,b.onFail=b.version=b.expressInstall=null;for(var e in b)b[e]&&(d+="<param name=\""+e+"\" value=\""+b[e]+"\" />");var g="";if(c){for(var i in c)if(c[i]){var j=c[i];g+=i+"="+encodeURIComponent(/function|object/.test(typeof j)?h.asString(j):j)+"&"}g=g.slice(0,-1),d+="<param name=\"flashvars\" value='"+g+"' />"}d+="</object>";return d},isSupported:function(a){return i[0]>a[0]||i[0]==a[0]&&i[1]>=a[1]}}),i=h.getVersion();function j(c,d,e){if(h.isSupported(d.version))c.innerHTML=h.getHTML(d,e);else if(d.expressInstall&&h.isSupported([6,65]))c.innerHTML=h.getHTML(f(d,{src:d.expressInstall}),{MMredirectURL:location.href,MMplayerType:"PlugIn",MMdoctitle:document.title});else{c.innerHTML.replace(/\s/g,"")||(c.innerHTML="<h2>Flash version "+d.version+" or greater is required</h2><h3>"+(i[0]>0?"Your version is "+i:"You have no flash plugin installed")+"</h3>"+(c.tagName=="A"?"<p>Click here to download latest version</p>":"<p>Download latest version from <a href='"+b+"'>here</a></p>"),c.tagName=="A"&&(c.onclick=function(){location.href=b}));if(d.onFail){var g=d.onFail.call(this);typeof g=="string"&&(c.innerHTML=g)}}a&&(window[d.id]=document.getElementById(d.id)),f(this,{getRoot:function(){return c},getOptions:function(){return d},getConf:function(){return e},getApi:function(){return c.firstChild}})}c&&(jQuery.tools=jQuery.tools||{version:"v1.2.6"},jQuery.tools.flashembed={conf:e},jQuery.fn.flashembed=function(a,b){return this.each(function(){jQuery(this).data("flashembed",flashembed(this,a,b))})})})();
