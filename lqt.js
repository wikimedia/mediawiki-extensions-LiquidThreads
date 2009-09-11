var liquidThreads = {
	currentReplyThread : null,
	currentToolbar : null,
	
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
		
		if (thread_id == liquidThreads.currentReplyThread) {
			liquidThreads.cancelEdit({});
			return;
		}
		
		var query = '&lqt_method=reply&lqt_operand='+thread_id;
		
		var replyDiv = $j(container).find('.lqt-reply-form')[0];
		
		liquidThreads.injectEditForm( query, replyDiv, e.preload );
		liquidThreads.currentReplyThread = thread_id;
		
		return false;
	},
	
	'handleNewLink' : function(e) {
		e.preventDefault();
		
		var query = '&lqt_method=talkpage_new_thread';
		
		var container = $j('.lqt-new-thread' );
		
		liquidThreads.injectEditForm( query, container );
		liquidThreads.currentReplyThread = 0;
			
		return false;
	},
	
	'injectEditForm' : function(query, container, preload) {
		var url = wgServer+wgScript+'?lqt_inline=1&title='+encodeURIComponent(wgPageName)+
					query
					
		liquidThreads.cancelEdit( container );
		
		var loadSpinner = $j('<div class="mw-ajax-loader"/>');
		$j(container).before( loadSpinner );
		
		var finishShow = function() {
			// Scroll to the textbox
			var targetOffset = $j(container).find('#wpTextbox1').offset().top;
			
			// Buffer at the top, roughly enough to see the heading and one line
			targetOffset -= 100;
			$j('html,body').animate({scrollTop: targetOffset}, 'slow');
			
			$j(container).find('#wpTextbox1').focus();
		}
		
		var finishSetup = function() {
			// Kill the loader.
			loadSpinner.remove();
			
			if (preload) {
				$j("textarea", container)[0].value = preload;
			}
			
			$j(container).slideDown( 'slow', finishShow );
			
			var cancelButton = $j(container).find('#mw-editform-cancel');
			cancelButton.click( liquidThreads.cancelEdit );
			
			$j(container).find('#wpTextbox1')[0].rows = 10;
			
			// Add toolbar
			mwSetupToolbar();
			
			// Check for live preview
			if ( $j('#wpLivePreview').length ) {
				$j.getScript( stylepath+'/common/preview.js',
								function() { setupLivePreview(); } );
			}
		};
		
		mwEditButtons = [];
		
		$j.getScript( stylepath+'/common/edit.js',
			function() {
				$j(container).load(wgServer+wgScript, 'title='+encodeURIComponent(wgPageName)+
							query+'&lqt_inline=1', finishSetup );
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
		var post = $j(thread.find('.lqt_post')[0]);
		
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
	
	'addQuoteButton' : function( toolbar ) {
		var quoteButton = $j('<li/>' );
		quoteButton.addClass('lqt-command');
		quoteButton.addClass('lqt-command-quote');
		
		var link = $j('<a href="#"/>');
		link.append( wgLqtMessages['lqt-quote'] );
		quoteButton.append( link );
		
		quoteButton.click( liquidThreads.doQuote );
		
		$j(toolbar).prepend( quoteButton );
	},
	
	'cancelEdit' : function( e ) {
		if (e.preventDefault) {
			e.preventDefault();
		}
		
		$j('.lqt-edit-form').not(e).fadeOut('slow', function() { $j(this).empty(); } );
		
		liquidThreads.currentReplyThread = null;
	},
	
	'setupMenus' : function() {
		var post = $j(this);
		
		var toolbar = post.find('.lqt-thread-toolbar');
		toolbar.hide();
		
		post.hover(
					function() {
						toolbar.fadeIn(100);
						liquidThreads.currentToolbar = toolbar;
					} /* over */,
					function() {
						if ( liquidThreads.currentToolbar &&
								liquidThreads.currentToolbar.is(toolbar) ) {
							liquidThreads.currentToolbar = null;
						}
						
						toolbar.fadeOut(20);
					}/*out */ );
					
		var menu = post.find('.lqt-thread-toolbar-command-list');
		var menuContainer = post.find( '.lqt-thread-toolbar-menu' );
		menu.remove().appendTo( menuContainer );
		menuContainer.find('.lqt-thread-toolbar-command-list').hide();
		
		var menuTrigger = menuContainer.find( '.lqt-thread-actions-trigger' );
		
		menuTrigger.hover( function() { menu.fadeIn(); } );
		toolbar.hover( function() {}, function() { menu.fadeOut(); } );

		menuTrigger.show();
	},
	
	'checkForUpdates' : function() {
		var threadModifiedTS = {};
		var threads = [];
		
		$j('.lqt-thread-topmost').each( function() {
			var tsField = $j(this).find('.lqt-thread-modified');
			var oldTS = tsField.val();
			// Prefix is lqt-thread-modified-
			var threadID = tsField.attr('id').substr( "lqt-thread-modified-".length );
			
			threadModifiedTS[threadID] = oldTS;
			threads.push(threadID);
		} );
		
		var getData = { 'action' : 'query', 'list' : 'threads', 'thid' : threads.join('|'),
						'format' : 'json', 'thprop' : 'id|subject|parent|modified' };
		
		$j.get( wgScriptPath+'/api.php', getData,
			function(data) {
				var threads = data.query.threads;
				
				$j.each( threads, function( i, thread ) {
					var threadID = thread.id;
					var threadModified = thread.modified;
					
					if ( threadModified != threadModifiedTS[threadID] ) {
						liquidThreads.showUpdated(threadID);
					}
				} );
			}, 'json' );
	},
	
	'showUpdated' : function(id) {
		// Check if there's already an updated marker here
		var threadObject = $j("#lqt_thread_id_"+id);
		
		if ( threadObject.find('.lqt-updated-notification').length ) {
			return;
		}
		
		var notifier = $j('<div/>');
		notifier.text( wgLqtMessages['lqt-ajax-updated'] );
		notifier.addClass( 'lqt-updated-notification' );
		
		threadObject.prepend(notifier);
	},
	
	'setupThread' : function(threadContainer) {
		var prefixLength = "lqt_thread_id_".length;
		
		// Update reply links
		var replyLI = $j(threadContainer).find( '.lqt-command-reply' );
		var threadWrapper = $j(threadContainer).closest('.lqt_thread')[0]
		var threadId = threadWrapper.id.substring( prefixLength );
		
		$j(threadContainer).data( 'thread-id', threadId );
		
		if (!(replyLI.length)) {
			return;
		}
		
		replyLI[0].id = "lqt-reply-id-"+threadId;
		var replyLink = replyLI.find('a');
		
		replyLink.click( liquidThreads.handleReplyLink );
		
		// Hide edit forms
		$j(threadContainer).find('div.lqt-edit-form').each(
			function() {
				if ( $j(this).find('#wpTextbox1').length ) {
					return;
				}
				
				this.style.display = 'none';
			} );
	
		// Update menus
		$j(threadContainer).each( liquidThreads.setupMenus );
	
		// Add quote button to menus
		var toolbar = $j(threadContainer).find('.lqt-thread-toolbar-commands');
		liquidThreads.addQuoteButton(toolbar);
		
		// Check for a "show replies" button
		$j('a.lqt-show-replies').click( liquidThreads.showReplies );
		
		// "Show more posts" link
		$j('a.lqt-show-more-posts').click( liquidThreads.showMore );
	},
	
	'showReplies' : function(e) {
		e.preventDefault();
		
		// Grab the closest thread
		var thread = $j(this).closest('.lqt_thread').find('div.lqt-post-wrapper')[0];
		thread = $j(thread);
		var threadId = thread.data('thread-id');
		var replies = thread.parent().find('.lqt-thread-replies');
		var loader = $j('<div class="mw-ajax-loader"/>');
		
		replies.empty();
		replies.hide();
		replies.before( loader );
		
		var apiParams = { 'action' : 'query', 'list' : 'threads', 'thid' : threadId,
							'format' : 'json', 'thrender' : '1', 'thprop' : 'id' };
		
		$j.get( wgScriptPath+'/api.php', apiParams,
			function(data) {
				// Interpret
				var content = data.query.threads[0].content;
				content = $j(content).find('.lqt-thread-replies')[0];
				
				// Inject
				replies.empty().append( $j(content).contents() );
				
				// Remove post separator, if it follows the replies element
				if ( replies.next().is('.lqt-post-sep') ) {
					replies.next().remove();
				}
				
				// Set up
				replies.find('div.lqt-post-wrapper').each( function() {
					liquidThreads.setupThread( $j(this) );
				} );
				
				// Show
				loader.remove();
				replies.fadeIn('slow');
			}, 'json' );
	},
	
	'showMore' : function(e) {
		e.preventDefault();
		
		// Add spinner
		var loader = $j('<div class="mw-ajax-loader"/>');
		$j(this).after(loader);
		
		// Grab the appropriate thread
		var thread = $j(this).closest('.lqt_thread').find('div.lqt-post-wrapper')[0];
		thread = $j(thread);
		var threadId = thread.data('thread-id');
		
		// Find the hidden field that gives the point to start at.
		var startAtField = $j(this).siblings().filter('.lqt-thread-start-at');
		var startAt = startAtField.val();
		startAtField.remove();
		
		// API request
		var apiParams = { 'action' : 'query', 'list' : 'threads', 'thid' : threadId,
							'format' : 'json', 'thrender' : '1', 'thprop' : 'id',
							'threnderstartrepliesat' : startAt };
		
		$j.get( wgScriptPath+'/api.php', apiParams,
			function(data) {
				var content = data.query.threads[0].content;
				content = $j(content).find('.lqt-thread-replies')[0];
				content = $j(content).contents();
				content = content.not('.lqt-replies-finish');
				
				if ( $j(content[0]).is('.lqt-post-sep') ) {
					content = content.not($j(content[0]));
				}
				
				// Inject loaded content.
				content.hide();
				loader.after( content );
				
				content.find('div.lqt-post-wrapper').each( function() {
					liquidThreads.setupThread( $j(this) );
				} );
				
				content.fadeIn();
				loader.remove();
			}, 'json' );
			
		$j(this).remove();
	}
}

js2AddOnloadHook( function() {
	// One-time setup for the full page
	
	// Update the new thread link
	var newThreadLink = $j('.lqt_start_discussion a');
	
	// Add scrolling handler
	$j(document).scroll( function() {
		var toolbar = liquidThreads.currentToolbar;
		if ( !toolbar ) { return; }
		
		var post = toolbar.closest('.lqt_thread');
		var scrollTop = $j(document).scrollTop();
		var toolbarTop = toolbar.offset().top;
		var postTop = post.offset().top;
		
		if ( scrollTop > toolbarTop ) {
			toolbar.css( 'top', scrollTop );
		} else if ( toolbar.css('top') && toolbar.css('top') != 'auto'
					&& scrollTop < toolbarTop ) {
			// Move back either to the start of the post, or to the scroll point
			if ( scrollTop > postTop ) {
				toolbar.css( 'top', scrollTop );
			} else {
				toolbar.css( 'top', 'auto' );
			}
		}
	} );
	
	if (newThreadLink) {
		newThreadLink.click( liquidThreads.handleNewLink );
	}

	// Find all threads, and do the appropriate setup for each of them
	
	var threadContainers = $j('div.lqt-post-wrapper');
	
	threadContainers.each( function(i) {
		liquidThreads.setupThread( this );
	} );
	
	// Set up periodic update checking
	setInterval( liquidThreads.checkForUpdates, 30000 );
} );

