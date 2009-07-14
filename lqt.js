var liquidThreads = {
	'handleReplyLink' : function(e) {
		var link;
		if (!e) e = window.event;
		if (e.target) link = e.target;
		else if (e.srcElement) link = e.srcElement;
		if (link.nodeType == 3) // defeat Safari bug
			link = link.parentNode;
		link = link.parentNode; // Get the enclosing li.
		
		var prefixLength = "lqt-reply-id-".length;
		var thread_id = link.id.substring( prefixLength );
		var container = document.getElementById( 'lqt_thread_id_'+thread_id );
		var footer_cmds = getElementsByClassName( container, '*', 'lqt_post' )[0];
		var query = '&lqt_method=reply&lqt_operand='+thread_id;
		
		liquidThreads.injectEditForm( query, container, footer_cmds.nextSibling, e.preload );
	
		if (e.preventDefault)
			e.preventDefault();
		
		return false;
	},
	
	'handleNewLink' : function(e) {
		var link;
		if (!e) e = window.event;
		if (e.target) link = e.target;
		else if (e.srcElement) link = e.srcElement;
		if (link.nodeType == 3) // defeat Safari bug
			link = link.parentNode;
			
		var query = '&lqt_method=talkpage_new_thread';
		var container = document.getElementById( 'bodyContent' );
		
		liquidThreads.injectEditForm( query, container, link.parentNode.nextSibling );
		
		if (e.preventDefault)
			e.preventDefault();
			
		return false;
	},
	
	'injectEditForm' : function(query, container, before, preload) {
		var x = sajax_init_object();
		var url = wgServer+wgScript+'?title='+encodeURIComponent(wgPageName)+
					query+'&lqt_inline=1'
		x.open( 'get', url, true );
		
		x.onreadystatechange =
			function() {
				if (x.readyState != 4)
					return;
					
				if ( liquidThreads.currentEditForm ) {
					var f = liquidThreads.currentEditForm;
					f.parentNode.removeChild( f );
				}
				
				var result = x.responseText;
				var replyDiv = document.createElement( 'div' );
				replyDiv.className = 'lqt_ajax_reply_form'
				replyDiv.innerHTML = result;
				
				if (before) {
					container.insertBefore( replyDiv, before );
				} else {
					container.appendChild( replyDiv );
				}
				
				liquidThreads.currentEditForm = replyDiv;
				
				if (preload) {
					var textbox = document.getElementById( 'wpTextbox1' );
					textbox.value = preload;
				}
			};
		
		x.send( null );
	},
	
	//From http://clipmarks.com/clipmark/CEFC94CB-94D6-4495-A7AA-791B7355E284/
	'insertAtCursor' : function(myField, myValue) {
		//IE support
		if (document.selection) {
			myField.focus();
			sel = document.selection.createRange();
			sel.text = myValue;
		}
		//MOZILLA/NETSCAPE support
		else if (myField.selectionStart || myField.selectionStart == '0') {
			var startPos = myField.selectionStart;
			var endPos = myField.selectionEnd;
			myField.value = myField.value.substring(0, startPos)
			+ myValue
			+ myField.value.substring(endPos, myField.value.length);
		} else {
			myField.value += myValue;
		}
	},
	
	'transformQuote' : function(quote) {
		// trim() doesn't work on all browsers
		quote = quote.replace(/^\s+|\s+$/g, '');
		var lines = quote.split("\n");
		var newQuote = '';
		
		for( var i = 0; i<lines.length; ++i ) {
			if (lines[i].length) {
				newQuote += liquidThreads.quoteLine(lines[i])+"\n";
			}
		}
		
		return newQuote;
	},
	
	'quoteLine' : function(line) {
		var versionParts = wgVersion.split('.');
		
		if (versionParts[0] <= 1 && versionParts[1] < 16) {
			return '<blockquote>'+line+'</blockquote>';
		}
		
		return '> '+line;
	},
	
	'getSelection' : function() {
		if (window.getSelection) {
			return window.getSelection().toString();
		} else if (document.selection) {
			return document.selection.createRange().text;
		} else if (document.getSelection) {
			return document.getSelection();
		} else {
			return '';
		}
	},
	
	'doQuote' : function(e) {
		if (!e) e = window.event;
		e.preventDefault();
		
		var button;
		if (e.target) button = e.target;
		else if (e.srcElement) button = e.srcElement;
		if (button.nodeType == 3) // defeat Safari bug
			button = button.parentNode;
		
		var thread = button;
		
		// Get the post node
		// Keep walking up until we hit the thread node.
		while (thread.id.substr(0,13) != 'lqt_thread_id') {
			thread = thread.parentNode;
		}
		var post = getElementsByClassName( thread, 'div', 'lqt_post' )[0];
		
		var text = liquidThreads.getSelection();
		
		if (text.length == 0) {
			// Quote the whole post
			if (post.innerText) {
				text = post.innerText;
			} else if (post.textContent) {
				text = post.textContent;
			}
		}
		
		text = liquidThreads.transformQuote( text );
		// TODO auto-generate context info and link.
		
		var textbox = document.getElementById( 'wpTextbox1' );
		if (textbox) {
			liquidThreads.insertAtCursor( textbox, text );
			textbox.focus();
		} else {
			// Open the reply window
			var replyLI = getElementsByClassName( thread, 'li', 'lqt-command-reply' )[0];
			var replyLink = replyLI.getElementsByTagName( 'a' )[0];
			
			liquidThreads.handleReplyLink( { 'target':replyLink, 'preload':text } );
		}
		
		return false;
	},
	
	'showQuoteButtons' : function() {
		var elems = getElementsByClassName( document, 'div', 'lqt-thread-header-rhs' );
		var url = wgScriptPath+'/extensions/LiquidThreads/icons/lqt-icon-quote.png';
		
		var length = elems.length;
		for( var i = 0; i<length; ++i ) {
			var quoteButton = document.createElement( 'span' );
			quoteButton.className = 'lqt-header-quote';
			
			var img = document.createElement( 'img' );
			img.src = url;
			img.className = 'lqt-command-icon';
			
			var text = wgLqtMessages['lqt-quote'];
			var textNode = document.createTextNode( text );
			
			var link = document.createElement( 'a' );
			link.href='#';
			link.appendChild( img );
			link.appendChild( textNode );
			quoteButton.appendChild( link );
			
			addHandler( quoteButton, 'click', liquidThreads.doQuote );
			
			elems[i].insertBefore( quoteButton, elems[i].firstChild );
			elems[i].insertBefore( document.createTextNode( '|' ), quoteButton.nextSibling );
		}
	}
}

addOnloadHook( function() {
	// Find all the reply links
	var threadContainers = getElementsByClassName( document, 'div', 'lqt_thread' );
	var prefixLength = "lqt_thread_id_".length;
	
	for( var i = 0; i < threadContainers.length; ++i ) {
		var container = threadContainers[i];
		var replyLI = getElementsByClassName( container, '*', 'lqt-command-reply' )[0];
		var threadId = container.id.substring( prefixLength );
		
		if (!replyLI) {
			continue;
		}
		
		replyLI.id = "lqt-reply-id-"+threadId;
		var replyLink = replyLI.firstChild;
		
		addHandler( replyLink, 'click', liquidThreads.handleReplyLink );
	}
	
	// Update the new thread link
	var newThreadLink = getElementsByClassName( document, 'a', 'lqt_start_discussion' )[0];
	
	if (newThreadLink) {
		addHandler( newThreadLink, 'click', liquidThreads.handleNewLink );
	}
	
	// Show quote buttons
	liquidThreads.showQuoteButtons();
} );

