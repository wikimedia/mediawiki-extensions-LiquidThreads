var liquidThreads = {
	'handleReplyLink' : function(e) {
		if (e.preventDefault)
			e.preventDefault();

		var target = this;
		
		if ( !this.className && e.target) {
			target = $j(e.target);
		}
		
		var prefixLength = "lqt_thread_id_".length;
		var container = $j(target).closest('.lqt_thread')[0];
		var thread_id = container.id.substring( prefixLength );
		var query = '&lqt_method=reply&lqt_operand='+thread_id;
		
		var replyDiv = $j(container).find('.lqt-reply-form')[0];
		
		liquidThreads.injectEditForm( query, replyDiv, e.preload );
		
		return false;
	},
	
	'handleNewLink' : function(e) {
		e.preventDefault();
		
		var query = '&lqt_method=talkpage_new_thread';
		
		var container = $j('.lqt-new-thread' );
		
		liquidThreads.injectEditForm( query, container );
			
		return false;
	},
	
	'injectEditForm' : function(query, container, preload) {
		var url = wgServer+wgScript+'?lqt_inline=1&title='+encodeURIComponent(wgPageName)+
					query
					
		liquidThreads.cancelEdit( container );
		
		$j(container).load(wgServer+wgScript, 'title='+encodeURIComponent(wgPageName)+
					query+'&lqt_inline=1',
					function() {
						if (preload) {
							$j("textarea", container)[0].value = preload;
						}
						
						$j(container).slideDown('slow');
						
						var cancelButton = $j(container).find('#mw-editform-cancel');
						cancelButton.click( liquidThreads.cancelEdit );
					} );
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
		if (e.preventDefault)
			e.preventDefault();
		
		// Get the post node
		// Keep walking up until we hit the thread node.
		var thread = $j(this).closest('.lqt_thread');
		var post = thread.find('.lqt_post');
		
		var text = liquidThreads.getSelection();
		
		if (text.length == 0) {
			// Quote the whole post
			text = post.text();
		}
		
		text = liquidThreads.transformQuote( text );
		// TODO auto-generate context info and link.
		
		var textbox = document.getElementById( 'wpTextbox1' );
		if (textbox) {
			liquidThreads.insertAtCursor( textbox, text );
			textbox.focus();
		} else {
			// Open the reply window
			var replyLI = thread.find('.lqt-command-reply')[0];
			var replyLink = $j(replyLI).find('a')[0];
			
			liquidThreads.handleReplyLink( { 'target':replyLink, 'preload':text } );
		}
		
		return false;
	},
	
	'showQuoteButtons' : function() {
		var elems = $j('.lqt-thread-toolbar-rhs');
		
		elems.each( function(i) {
			var quoteButton = $j('<span></span>' );
			quoteButton.className = 'lqt-header-quote';
			
			var link = $j('<a href="#"></a>');
			link.append( wgLqtMessages['lqt-quote'] );
			quoteButton.append( link );
			
			quoteButton.click( liquidThreads.doQuote );
			
			$j(this).prepend( ' | ' );
			$j(this).prepend( quoteButton );
		} );
	},
	
	'cancelEdit' : function( e ) {
		if (e.preventDefault) {
			e.preventDefault();
		}
		
		$j('.lqt-reply-form,.lqt-new-thread').not(e).slideUp('slow');
		$j('.lqt-reply-form,.lqt-new-thread').not(e).empty();
	}
}

js2AddOnloadHook( function() {
	// Find all the reply links
	var threadContainers = $j('div.lqt_thread');
	var prefixLength = "lqt_thread_id_".length;
	
	threadContainers.each( function(i) {
		var replyLI = $j(this).find( '.lqt-command-reply' );
		var threadId = this.id.substring( prefixLength );
		
		if (!(replyLI.length)) {
			return;
		}
		
		replyLI[0].id = "lqt-reply-id-"+threadId;
		var replyLink = replyLI.find('a');
		
		replyLink.click( liquidThreads.handleReplyLink );
	} );
	
	// Update the new thread link
	var newThreadLink = $j('a.lqt_start_discussion');
	
	if (newThreadLink) {
		newThreadLink.click( liquidThreads.handleNewLink );
	}
	
	// Show quote buttons
	liquidThreads.showQuoteButtons();
} );

