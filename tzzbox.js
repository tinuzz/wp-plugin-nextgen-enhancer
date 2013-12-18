/*
Shutter Reloaded for NextGEN Gallery
http://www.laptoptips.ca/javascripts/shutter-reloaded/
Version: 1.3.3
Copyright (C) 2007-2008  Andrew Ozz (Modification by Alex Rabe)
Released under the GPL, http://www.gnu.org/copyleft/gpl.html

Acknowledgement: some ideas are from: Shutter by Andrew Sutherland - http://code.jalenack.com, WordPress - http://wordpress.org, Lightbox by Lokesh Dhakar - http://www.huddletogether.com, the icons are from Crystal Project Icons, Everaldo Coelho, http://www.everaldo.com

*/

shutterOnload = function(){shutterReloaded.init('sh');}

if (typeof shutterOnload == 'function') {
	if ('undefined' != typeof jQuery) jQuery(document).ready(function(){shutterOnload();});
	else if( typeof window.onload != 'function' ) window.onload = shutterOnload;
	else {oldonld = window.onload;window.onload = function(){if(oldonld){oldonld();};shutterOnload();}};
}

shutterReloaded = {



	I : function (a) {
		return document.getElementById(a);
	},

	settings : function() {
		var t = this, s = shutterSettings;

		t.imageCount = s.imageCount || 0;
		t.msgLoading = s.msgLoading || 'LOADING';
		t.msgClose = s.msgClose || 'Click to Close';
	},

	init : function (a) {
		var t = this, L, T, ext, i, m, setid, inset, shfile, shMenuPre, k, img;
		shutterLinks = {}, shutterSets = {};
		if ( 'object' != typeof shutterSettings ) shutterSettings = {};
        
        // If the screen orientation is defined we are in a modern mobile OS
        t.mobileOS = typeof orientation != 'undefined' ? true : false;
                
		for ( i = 0; i < document.links.length; i++ ) {
			L = document.links[i];
			ext = ( L.href.indexOf('?') == -1 ) ? L.href.slice(-4).toLowerCase() : L.href.substring( 0, L.href.indexOf('?') ).slice(-4).toLowerCase();
			if ( ext != '.jpg' && ext != '.png' && ext != '.gif' && ext != 'jpeg' && ext != '.mp4' ) continue;
			if ( a == 'sh' && L.className.toLowerCase().indexOf('shutter') == -1 ) continue;
			if ( a == 'lb' && L.rel.toLowerCase().indexOf('lightbox') == -1 ) continue;

			if ( L.className.toLowerCase().indexOf('shutterset') != -1 )
			setid = L.className.replace(/\s/g, '_');			
			else if ( L.rel.toLowerCase().indexOf('lightbox[') != -1 )
			setid = L.rel.replace(/\s/g, '_');
			else setid = 0, inset = -1;
			
			if( setid ) {
				if ( ! shutterSets[setid] ) shutterSets[setid] = [];
					inset = shutterSets[setid].push(i);
			}
			
			shfile = L.href.slice(L.href.lastIndexOf('/')+1);
			T = ( L.title && L.title != shfile ) ? L.title : '';

			if (ext == '.mp4') {
				linktype = 'video';
			}
			else {
				linktype = 'image';
			}
			
			shutterLinks[i] = {link:L.href,num:inset,set:setid,title:T,linktype:linktype}
			L.onclick = new Function('shutterReloaded.make("' + i + '");return false;');
		}

		t.settings();

	},

	make : function(ln,fs) {
		var t = this, prev, next, prevlink = '', nextlink = '', previmg, nextimg, D, S, W, fsarg = -1, imgNum, NavBar;

		if ( ! t.Top ) {
			if ( typeof window.pageYOffset != 'undefined' ) t.Top = window.pageYOffset;
			else t.Top = (document.documentElement.scrollTop > 0) ? document.documentElement.scrollTop : document.body.scrollTop;
		}

		if ( typeof t.pgHeight == 'undefined' )
			t.pgHeight = Math.max(document.documentElement.scrollHeight,document.body.scrollHeight);

		if ( fs ) t.FS = ( fs > 0 ) ? 1 : 0;
		else t.FS = shutterSettings.FS || 0;

		if ( t.resizing ) t.resizing = null;
        
        // resize event if window or orientation changed (i.e. iOS)
        if(t.mobileOS == true)
            window.onorientationchange = new Function('shutterReloaded.resize("'+ln+'");');
        else
            window.onresize = new Function('shutterReloaded.resize("'+ln+'");');

		document.documentElement.style.overflowX = 'hidden';
		if ( ! t.VP ) {
			t._viewPort();
			t.VP = true;
		}

		if ( ! (S = t.I('shShutter')) ) {
			S = document.createElement('div');
			S.setAttribute('id','shShutter');
			document.getElementsByTagName('body')[0].appendChild(S);
			t.hideTags();
		}

		if ( ! (D = t.I('shDisplay')) ) {
			D = document.createElement('div');
			D.setAttribute('id','shDisplay');
			D.style.top = t.Top + 'px';
			document.getElementsByTagName('body')[0].appendChild(D);
		}

		S.style.height = t.pgHeight + 'px';

		var dv = t.textBtns ? ' | ' : '';
		if ( shutterLinks[ln].num > 1 ) {
			prev = shutterSets[shutterLinks[ln].set][shutterLinks[ln].num - 2];
			prevlink = '<a href="#" id="prevpic" onclick="shutterReloaded.make('+prev+');return false">&lt;&lt;</a>'+dv;
			previmg = new Image();
			previmg.src = shutterLinks[prev].link;
		} else {
			prevlink = '';
		}

		if ( shutterLinks[ln].num != -1 && shutterLinks[ln].num < (shutterSets[shutterLinks[ln].set].length) ) {
			next = shutterSets[shutterLinks[ln].set][shutterLinks[ln].num];
			nextlink = '<a href="#" id="nextpic" onclick="shutterReloaded.make('+next+');return false">&gt;&gt;</a>'+dv;
			nextimg = new Image();
			nextimg.src = shutterLinks[next].link;
		} else {
			nextlink = '';
		}

		objOptions = {};
		title = shutterLinks[ln].title.split('::');
		if (typeof title[0] != 'undefined') caption = title[0].trim();  // IE 9+, FF 3.5+
		if (typeof title[1] != 'undefined') description = title[1].trim();
		if (typeof title[2] != 'undefined') {
			opts = title[2].trim();
			if (opts.length) {
				allopts = opts.split(',');
				allopts.forEach(function (v) {
					o = v.split(':');
					objOptions[o[0].trim()] = o[1].trim();
				});
			}
		}
		if (typeof objOptions['width'] == 'undefined') {
			objOptions['width'] = 640;
			objOptions['height'] = 480;
		}

		imgNum = ( (shutterLinks[ln].num > 0) && t.imageCount ) ? '<div id="shCount">&nbsp;(&nbsp;' + shutterLinks[ln].num + '&nbsp;/&nbsp;' + shutterSets[shutterLinks[ln].set].length + '&nbsp;)&nbsp;</div>' : '';

		NavBar = '<div id="shTitle"><div id="shPrev">' + prevlink + '</div><div id="shNext">' + nextlink + '</div><div id="shName">' + 
			'<b>' + caption + '</b><br />' + description + '</div>' + imgNum + '</div>';

		if (shutterLinks[ln].linktype == 'video') {
			D.innerHTML = '<div id="shWrap"><div id="shTopVid" style="width: ' + objOptions['width'] + 'px"><div id="videoplayer"></div></div>' + NavBar + '</div>';
			t.I('shTopVid').onclick = function (e) { e.stopPropagation(); }
			jwplayer("videoplayer").setup({
				autostart: true,
				file: shutterLinks[ln].link,
				width: objOptions['width'],
				height: objOptions['height']
			});
			t.showVid();
		}
		else {
			D.innerHTML = '<div id="shWrap"><img src="'+shutterLinks[ln].link+'" id="shTopImg" title="' + t.msgClose + ' ' + shutterLinks[ln].linktype + '" onload="shutterReloaded.showImg();" />' + NavBar +'</div>';
			t.I('shTopImg').onclick = function (e) { e.stopPropagation(); }
			//Google Chrome 4.0.249.78 bug for onload attribute
			document.getElementById('shTopImg').src = shutterLinks[ln].link;
		}

		document.onkeydown = function(event){shutterReloaded.handleArrowKeys(event);};
		t.I('shTitle').onclick = function(e) {
			e.stopPropagation();
		};

		window.setTimeout(function(){shutterReloaded.loading();},1000);
	},

	loading : function() {
		var t = this, S, WB, W;
		if ( (W = t.I('shWrap')) && W.style.visibility == 'visible' ) return;
		if ( ! (S = t.I('shShutter')) ) return;
		if ( t.I('shWaitBar') ) return;
		WB = document.createElement('div');
		WB.setAttribute('id','shWaitBar');
		WB.style.top = t.Top + 'px';
        WB.style.marginTop =(t.pgHeight/2) + 'px'
		WB.innerHTML = t.msgLoading;
		S.appendChild(WB);
	},

	hideShutter : function() {
		var t = this, D, S;
		if ( D = t.I('shDisplay') ) D.parentNode.removeChild(D);
		if ( S = t.I('shShutter') ) S.parentNode.removeChild(S);
		t.hideTags(true);
		window.scrollTo(0,t.Top);
		window.onresize = t.FS = t.Top = t.VP = null;
		document.documentElement.style.overflowX = '';
		document.onkeydown = null;
		document.onclick = function(e){ return true; }
	},

	resize : function(ln) {
		var t = this;

		if ( t.resizing ) return;
		if ( ! t.I('shShutter') ) return;
		var W = t.I('shWrap');
		if ( W ) W.style.visibility = 'hidden';

		window.setTimeout(function(){shutterReloaded.resizing = null},500);
		window.setTimeout(new Function('shutterReloaded.VP = null;shutterReloaded.make("'+ln+'");'),100);
		t.resizing = true;
	},

	_viewPort : function() {
		var t = this;
		var wiH = window.innerHeight ? window.innerHeight : 0;
		var dbH = document.body.clientHeight ? document.body.clientHeight : 0;
		var deH = document.documentElement ? document.documentElement.clientHeight : 0;

		if( wiH > 0 ) {
			t.wHeight = ( (wiH - dbH) > 1 && (wiH - dbH) < 30 ) ? dbH : wiH;
			t.wHeight = ( (t.wHeight - deH) > 1 && (t.wHeight - deH) < 30 ) ? deH : t.wHeight;
		} else t.wHeight = ( deH > 0 ) ? deH : dbH;

		var deW = document.documentElement ? document.documentElement.clientWidth : 0;
		var dbW = window.innerWidth ? window.innerWidth : document.body.clientWidth;
		t.wWidth = ( deW > 1 ) ? deW : dbW;
	},

	showImg : function() {
		var t = this, S = t.I('shShutter'), D = t.I('shDisplay'), TI = t.I('shTopImg'), T = t.I('shTitle'), NB = t.I('shNavBar'), W, WB, wHeight, wWidth, shHeight, maxHeight, itop, mtop, resized = 0;

		if ( ! S ) return;
		if ( (W = t.I('shWrap')) && W.style.visibility == 'visible' ) return;
		if ( WB = t.I('shWaitBar') ) WB.parentNode.removeChild(WB);

		S.style.width = D.style.width = '';
		T.style.width = (TI.width - 4) + 'px';

		shHeight = t.wHeight - 50;

		if ( t.FS ) {
			if ( TI.width > (t.wWidth - 10) )
			S.style.width = D.style.width = TI.width + 10 + 'px';
			document.documentElement.style.overflowX = '';
		} else {
			//window.scrollTo(0,t.Top);
			if ( TI.height > shHeight ) {
				TI.width = TI.width * (shHeight / TI.height);
				TI.height = shHeight;
				resized = 1;
			}
			if ( TI.width > (t.wWidth - 16) ) {
				TI.height = TI.height * ((t.wWidth - 16) / TI.width);
				TI.width = t.wWidth - 16;
				resized = 1;
			}
			T.style.width = (TI.width - 4) + 'px';
		}

		maxHeight = t.Top + TI.height + 10;
		if ( maxHeight > t.pgHeight ) S.style.height = maxHeight + 'px';
		//window.scrollTo(0,t.Top);

		itop = (shHeight - TI.height) * 0.45;
		mtop = (itop > 3) ? Math.floor(itop) : 3;
		D.style.top = t.Top + mtop + 'px';
		W.style.visibility = 'visible';
		shutterReloaded.clickhandler();
	},

	showVid : function() {
		var t = this, S = t.I('shShutter'), D = t.I('shDisplay'), TI = t.I('shTopVid'), P = t.I('videoplayer'),
			T = t.I('shTitle'), NB = t.I('shNavBar'), W, WB, wHeight, wWidth, shHeight, maxHeight, itop, mtop, resized = 0;

		if ( (W = t.I('shWrap')) && W.style.visibility == 'visible' ) return;

		T.style.width = (TI.offsetWidth - 24) + 'px';

		maxHeight = t.Top + TI.offsetHeight + 10;
		if ( maxHeight > t.pgHeight ) S.style.height = maxHeight + 'px';

		shHeight = t.wHeight - 50;
		itop = (shHeight - TI.offsetHeight) * 0.45;
		mtop = (itop > 3) ? Math.floor(itop) : 3;
		D.style.top = t.Top + mtop + 'px';
		W.style.visibility = 'visible';
		shutterReloaded.clickhandler();
	},

	hideTags : function(arg) {
		var sel = document.getElementsByTagName('select');
		var obj = document.getElementsByTagName('object');
		var emb = document.getElementsByTagName('embed');
		var ifr = document.getElementsByTagName('iframe');

		var vis = ( arg ) ? 'visible' : 'hidden';

		for (i = 0; i < sel.length; i++) sel[i].style.visibility = vis;
		for (i = 0; i < obj.length; i++) obj[i].style.visibility = vis;
		for (i = 0; i < emb.length; i++) emb[i].style.visibility = vis;
		for (i = 0; i < ifr.length; i++) ifr[i].style.visibility = vis;
	},


	clickhandler : function () {
		var t = this;
		if ( S = t.I('shShutter') ) {
			S.onclick = function(event){
				console.log(event);
				shutterReloaded.hideShutter();
			}
		};
		if ( D = t.I('shDisplay') ) {
			D.onclick = S.onclick;
		};
	},

	handleArrowKeys : function(e) {
	    var code = 0;
	    if (!e) var e = window.event
	    	if (e.keyCode) code = e.keyCode;
	    	else if (e.which) code = e.which;
	    	
		var nextlink = document.getElementById('prevpic');
		var prevlink = document.getElementById('nextpic');
		var closelink = document.getElementById('shTopImg');
		var closevid = document.getElementById('shTopVid');
	
		switch (code) {
		    case 39:
			if (prevlink) prevlink.onclick();
			break;    
		    case 37:
			if (nextlink) nextlink.onclick();
			break;    
		    case 27:
			if (closelink) closelink.onclick();
			else if (closevid) {
				shutterReloaded.hideShutter();
			}
			break;    
		 }
	}
}
