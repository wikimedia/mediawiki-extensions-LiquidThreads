// Prototype in string.trim on browsers that haven't yet implemented
// See http://blog.stevenlevithan.com/archives/faster-trim-javascript for the reason why /^\s+|\s+$/g is not used as the regex
if ( typeof String.prototype.trim !== "function" )
	String.prototype.trim = function(str) { return str.replace(/^\s\s*/, '').replace(/\s\s*$/, ''); };

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
		
		var container = $j(target).closest('.lqt_thread')[0];
		var thread_id = $j(this).data('thread-id');
		
		if (thread_id == liquidThreads.currentReplyThread) {
			liquidThreads.cancelEdit({});
			return;
		}
		
		var query = '&lqt_method=reply&lqt_operand='+thread_id;
		
		var repliesElement = $j(container).contents().filter('.lqt-thread-replies');
		var replyDiv = repliesElement.contents().filter('.lqt-reply-form');
		if (!replyDiv.length) {
			// Create a div for it
			replyDiv = $j('<div class="lqt-reply-form lqt-edit-form"/>');
			
			// Try to find a place for it
			if ( !repliesElement.length ) {
				repliesElement = $j('<div class="lqt-thread-replies"/>' );
				
				var finishDiv = $j('<div class="lqt-replies-finish"/>');
				finishDiv.append($j('<div class="lqt-replies-finish-corner"/>'));
				finishDiv.contents().html('&nbsp;');
				repliesElement.append(finishDiv);
				
				$j(container).append(repliesElement);
			}
			
			repliesElement.find('.lqt-replies-finish').before( replyDiv );
		}
		
		replyDiv = replyDiv[0];
		
		liquidThreads.injectEditForm( query, replyDiv, e.preload );
		liquidThreads.currentReplyThread = thread_id;
	},
	
	'handleNewLink' : function(e) {
		e.preventDefault();
		
		var query = '&lqt_method=talkpage_new_thread';
		
		var container = $j('.lqt-new-thread' );
		
		liquidThreads.injectEditForm( query, container );
		liquidThreads.currentReplyThread = 0;
	},
	
	'handleEditLink' : function(e) {
		e.preventDefault();
		
		// Grab the container.
		var container = $j(this).closest('.lqt-post-wrapper');
		var query='&lqt_method=edit&lqt_operand='+container.data('thread-id');
		
		liquidThreads.injectEditForm( query, container );
	},
	
	'injectEditForm' : function(query, container, preload) {
		var url = wgServer+wgScript+'?lqt_inline=1&title='+encodeURIComponent(wgPageName)+
					query;
					
		liquidThreads.cancelEdit( container );
		
		var isIE7 = $j.browser.msie && $j.browser.version.substr(0,1) == '7';
		
		var loadSpinner = $j('<div class="mw-ajax-loader"/>');
		$j(container).before( loadSpinner );
		
		var finishShow = function() {
			// Scroll to the textbox
			var targetOffset = $j(container).find('#wpTextbox1').offset().top;
			var windowHeight = $j(window).height();
			var editBoxHeight = $j(container).height();
			
			if (!targetOffset) {
				targetOffset = $j(container).offset().top;
			}
			
			var scrollOffset = targetOffset - windowHeight + editBoxHeight;
			
			$j('html,body').animate({scrollTop: scrollOffset}, 'slow');
			// Auto-focus and set to auto-grow as well
			$j(container).find('#wpTextbox1').focus();//.autogrow();
			// Focus the subject field if there is one. Overrides previous line.
			$j(container).find('#lqt_subject_field').focus();
		}
		
		var finishSetup = function() {
			// Kill the loader.
			loadSpinner.remove();
			
			if (preload) {
				$j("textarea", container)[0].value = preload;
			}
			
			if ( isIE7 ) {
				setTimeout( finishShow, 500 );
			} else {
				$j(container).slideDown( 'slow', finishShow );
			}
			
			var cancelButton = $j(container).find('#mw-editform-cancel');
			cancelButton.click( liquidThreads.cancelEdit );
			
			$j(container).find('#wpTextbox1').attr( 'rows', 12 );
			
			// Add toolbar
			mwSetupToolbar();
			
			currentFocused = $j(container).find('#wpTextbox1');
			$j(container).find('#wpTextbox1,#wpSummary').focus(
				function() {
					currentFocused = this;
				} );
			
			// Check for live preview
			if ( $j('#wpLivePreview').length ) {
				$j.getScript( stylepath+'/common/preview.js',
								function() { setupLivePreview(); } );
			}
		};
		
		mwEditButtons = [];
		
		$j.getScript( stylepath+'/common/edit.js',
			function() {
				if ( isIE7 ) {
					$j(container).empty().show();
					$j(container).load(wgServer+wgScript,
							'title='+encodeURIComponent(wgPageName)+
							query+'&lqt_inline=1', finishSetup );
				} else {
					$j(container).load(wgServer+wgScript,
							'title='+encodeURIComponent(wgPageName)+
							query+'&lqt_inline=1', finishSetup );
				}
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
		quote = quote.trim();
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
		
		$j('.lqt-edit-form').not(e).each(
			function() {
				var repliesElement = $j(this).closest('.lqt-thread-replies');
				var emptyAll = repliesElement.length &&
					!( repliesElement.contents().not('.lqt-replies-finish')
						.not('.lqt-edit-form').length );
				$j(this).fadeOut('slow',
					function() {
						$j(this).empty();
						
						if (emptyAll) {
							repliesElement.remove();
						}
					} )
			} );
		
		liquidThreads.currentReplyThread = null;
	},
	
	'setupMenus' : function() {
		var post = $j(this);
		
		var toolbar = post.contents().filter('.lqt-thread-toolbar');
		var threadID = post.data('thread-id');
		var menu = post.find('.lqt-thread-toolbar-command-list');
		var menuContainer = post.find( '.lqt-thread-toolbar-menu' );
		menu.remove().appendTo( menuContainer );
		menuContainer.find('.lqt-thread-toolbar-command-list').hide();
		
		// Add handler for edit link -- Disabled for further tweaking
//		var editLink = menu.find('.lqt-command-edit > a');
//		editLink.click( liquidThreads.handleEditLink );

		// Add handler for reply link
		var replyLink = menu.find('.lqt-command-reply > a');
		replyLink.data( 'thread-id', threadID );
		replyLink.click( liquidThreads.handleReplyLink );

		var trigger = menuContainer.find( '.lqt-thread-actions-trigger' )	

		trigger.show();
		menu.hide();
		
		trigger.click(
			function(e) {
				e.stopImmediatePropagation();
				
				// Hide the other menus
				$j('.lqt-thread-toolbar-command-list').not(menu).hide('fast');
				
				menu.toggle( 'fast' );
				
				var windowHeight = $j(window).height();
				var toolbarOffset = toolbar.offset().top;
				var scrollPos = $j(window).scrollTop();
				
				var menuBottom = ( toolbarOffset + 150 - scrollPos );
				
				if ( menuBottom > windowHeight ) {
					// Switch to an upwards menu.
					menu.css( 'bottom', toolbar.height() );
				} else {
					menu.css( 'bottom', 'auto' );
				}
			} );
	},
	
	'setupThreadMenu' : function( menu, id ) {
		if ( menu.find('.lqt-command-edit-subject').length ) {
			return;
		}
		
		var editSubjectField = $j('<li/>');
		var editSubjectLink = $j('<a href="#"/>');
		editSubjectLink.text( wgLqtMessages['lqt-change-subject'] );
		editSubjectField.append( editSubjectLink );
		editSubjectField.click( liquidThreads.handleChangeSubject );
		editSubjectField.data( 'thread-id', id )
		
		editSubjectField.addClass( 'lqt-command-edit-subject' );
		
		menu.append( editSubjectField );
	},
	
	'handleChangeSubject' : function(e) {
		e.preventDefault();

		// Grab the h2
		var threadId = $j(this).data('thread-id');
		var header = $j('#lqt-header-'+threadId);
		var headerText = header.find("input[name='raw-header']").val();
		
		var textbox = $j('<input type="textbox" />').val(headerText);
		textbox.attr('id', 'lqt-subject-input-'+threadId);
		textbox.attr('size', '75');
		textbox.val(headerText);
		
		var saveText = wgLqtMessages['lqt-save-subject'];
		var saveButton = $j('<input type="button" />');
		saveButton.val( saveText );
		saveButton.click( liquidThreads.handleSubjectSave );
		
		var cancelButton = $j('<input type="button" />');
		cancelButton.val( wgLqtMessages['lqt-cancel-subject-edit'] );
		cancelButton.click( function(e) {
			var form = $j(this).closest('.mw-subject-editor');
			var header = form.closest('.lqt_header');
			header.contents().filter('.mw-headline').show();
			form.remove();
			
		} );
		
		header.contents().filter('span.mw-headline').hide();
		
		var subjectForm = $j('<span class="mw-subject-editor"/>');
		subjectForm.append(textbox);
		subjectForm.append( '&nbsp;' );
		subjectForm.append(saveButton);
		subjectForm.append( '&nbsp;' );
		subjectForm.append( cancelButton );
		subjectForm.data( 'thread-id', threadId );
		
		header.append(subjectForm);
		
	},
	
	'handleSubjectSave' : function(e) {
		var button = $j(this);
		var subjectForm = button.closest('.mw-subject-editor');
		var header = subjectForm.closest('.lqt_header');
		var threadId = subjectForm.data('thread-id');
		var textbox = $j('#lqt-subject-input-'+threadId);
		var newSubject = textbox.val().trim();
		
		if (!newSubject) {
			alert( wgLqtMessages['lqt-ajax-no-subject'] );
			return;
		}
		
		// Add a spinner
		var spinner = $j('<div class="mw-ajax-loader"/>');
		header.append(spinner);
		subjectForm.hide();
		
		var request = {
			'action' : 'threadaction',
			'threadaction' : 'setsubject',
			'subject' : newSubject.trim(),
			'thread' : threadId
		};
		
		var errorHandler = function(reply) {
			try {
				code = reply.error.code;
				description = reply.error.info;
				
				if (code == 'invalid-subject') {
					alert( wgLqtMessages['lqt-ajax-invalid-subject'] );
				} else {
					var msg = wgLqtMessages['lqt-save-subject-failed'];
					msg.replace( '$1', description );
				}
				
				subjectForm.show();
				spinner.remove();
			} catch (err) {
				alert( wgLqtMessages['lqt-save-subject-error-unknown'] );
				subjectForm.remove();
				spinner.remove();
				header.contents().filter('.mw-headline').show();
			}
		}
		
		// Set new subject through API.
		liquidThreads.apiRequest( request, function(reply) {
			var result;
			
			try {
				result = reply.threadaction.thread.result;
			} catch (err) {
				result = 'error';
			}
			
			if ( result == 'success' ) {
				spinner.remove();
				var thread = $j('#lqt_thread_id_'+threadId);
				liquidThreads.doReloadThread( thread );
			} else {
				errorHandler(reply);
			}
		} );
	},
		
	'handleDocumentClick' : function(e) {
		// Collapse all menus
		$j('.lqt-thread-toolbar-command-list').hide('fast');
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
		
		// Optimisation: if no threads are to be checked, do not check.
		if ( ! threads.length ) {
			return;
		}
		
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
		notifier.text( wgLqtMessages['lqt-ajax-updated'] + ' ' );
		notifier.addClass( 'lqt-updated-notification' );
		
		var updateButton = $j('<a href="#"/>');
		updateButton.text( wgLqtMessages['lqt-ajax-update-link'] );
		updateButton.addClass( 'lqt-update-link' );
		updateButton.click( liquidThreads.updateThread );
		
		notifier.append( updateButton );
		
		threadObject.prepend(notifier);
	},
	
	'updateThread' : function(e) {
		e.preventDefault();
		
		var thread = $j(this).closest('.lqt_thread');

		liquidThreads.doReloadThread( thread );
	},
	
	'doReloadThread' : function( thread /* The .lqt_thread */ ) {
		var post = thread.find('div.lqt-post-wrapper')[0];
		post = $j(post);
		var threadId = post.data('thread-id');
		var loader = $j('<div class="mw-ajax-loader"/>');
		var header = $j('#lqt-header-'+threadId);
		
		thread.prepend(loader);
		
		// Build an AJAX request
		var apiReq = { 'action' : 'query', 'list' : 'threads', 'thid' : threadId,
						'format' : 'json', 'thrender' : 1 };
						
		$j.get( wgScriptPath+'/api.php', apiReq,
			function(data) {
				// Load data from JSON
				var html = data.query.threads[0].content;
				var newContent = $j(html);
				
				// Clear old post and header.
				thread.empty();
				thread.hide();
				header.empty();
				header.hide();
				
				// Replace post content
				var newThread = newContent.filter('div.lqt_thread')[0];
				var newThreadContent = $j(newThread).contents();
				thread.append( newThreadContent );
				
				// Replace header content
				var newHeader = newContent.filter('#lqt-header-'+threadId);
				var newHeaderContent = $j(newHeader).contents();
				header.append( newHeaderContent );
				
				// Set up thread.
				thread.find('.lqt-post-wrapper').each( function() {
					liquidThreads.setupThread( $j(this) );
				} );
				
				header.fadeIn();
				thread.fadeIn();
				
				// Scroll to the updated thread.
				var targetOffset = $j(thread).offset().top;
				$j('html,body').animate({scrollTop: targetOffset}, 'slow');
				
			}, 'json' );
	},
	
	'setupThread' : function(threadContainer) {
		var prefixLength = "lqt_thread_id_".length;
		
		// Update reply links
		var threadWrapper = $j(threadContainer).closest('.lqt_thread')[0]
		var threadId = threadWrapper.id.substring( prefixLength );
		
		$j(threadContainer).data( 'thread-id', threadId );
		
		// Set up reply link
		var replyLinks = $j(threadWrapper).find('.lqt-add-reply');
		replyLinks.click( liquidThreads.handleReplyLink );
		replyLinks.data( 'thread-id', threadId );
		
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
		
		// Update thread-level menu, if appropriate
		if ( $j(threadWrapper).hasClass( 'lqt-thread-topmost' ) ) {
			var threadLevelCommandSelector = '#lqt-threadlevel-commands-'+threadId;
			
			var menu = $j(threadLevelCommandSelector);
			liquidThreads.setupThreadMenu( menu, threadId );
		}
		
		// Check for a "show replies" button
		$j('a.lqt-show-replies').click( liquidThreads.showReplies );
		
		// "Show more posts" link
		$j('a.lqt-show-more-posts').click( liquidThreads.showMore );
		
		// Handler for "Link to this" button
		$j('.lqt-command-link').click( liquidThreads.showLinkWindow );
	},
	
	'showReplies' : function(e) {
		e.preventDefault();
		
		// Grab the closest thread
		var thread = $j(this).closest('.lqt_thread').find('div.lqt-post-wrapper')[0];
		thread = $j(thread);
		var threadId = thread.data('thread-id');
		var replies = thread.parent().find('.lqt-thread-replies');
		var loader = $j('<div class="mw-ajax-loader"/>');
		var sep = $j('<div class="lqt-post-sep">&nbsp;</div>');
		
		replies.empty();
		replies.hide();
		replies.before( loader );
		
		var apiParams = { 'action' : 'query', 'list' : 'threads', 'thid' : threadId,
					'format' : 'json', 'thrender' : '1', 'thprop' : 'id' };
		
		$j.get( wgScriptPath+'/api'+wgScriptExtension, apiParams,
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
				
				replies.before(sep);
				
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
	},
	
	'asyncWatch' : function(e) {
		var button = $j(this);
		var tlcOffset = "lqt-threadlevel-commands-".length;
		
		// Find the title of the thread
		var threadLevelCommands = button.closest('.lqt_threadlevel_commands');
		var threadID = threadLevelCommands.attr('id').substring( tlcOffset );
		var title = $j('#lqt-thread-title-'+threadID).val();
		
		// Check if we're watching or unwatching.
		var action = '';
		if ( button.hasClass( 'lqt-command-watch' ) ) {
			button.removeClass( 'lqt-command-watch' );
			action = 'watch';
		} else if ( button.hasClass( 'lqt-command-unwatch' ) ) {
			button.removeClass( 'lqt-command-unwatch' );
			action = 'unwatch';
		}
		
		// Replace the watch link with a spinner
		button.empty().addClass( 'mw-small-spinner' );
		
		// Do the AJAX call.
		var apiParams = { 'action' : 'watch', 'title' : title, 'format' : 'json' };
		
		if (action == 'unwatch') {
			apiParams.unwatch = 'yes';
		}
		
		$j.get( wgScriptPath+'/api'+wgScriptExtension, apiParams,
			function( data ) {
				threadLevelCommands.load( window.location.href+' '+
						'#'+threadLevelCommands.attr('id')+' > *' );
			}, 'json' );
		
		e.preventDefault();
	},
	
	'showLinkWindow' : function(e) {
		var linkURL = $j(this).find('a').attr('href');
		var thread = $j(this).closest('.lqt_thread');
		var linkTitle = thread.find('.lqt-thread-title-metadata').val();
		linkTitle = '[[' + linkTitle + ']]';
		
		// Build dialog
		var urlLabel = $j('<th/>').text(wgLqtMessages['lqt-thread-link-url']);
		var urlField = $j('<tr/>').text(linkURL).addClass( 'lqt-thread-link-url' );
		var urlRow = $j('<tr/>').append(urlLabel).append(urlField );
		
		var titleLabel = $j('<th/>').text(wgLqtMessages['lqt-thread-link-title']);
		var titleField = $j('<tr/>').text(linkTitle).addClass( 'lqt-thread-link-title' );
		var titleRow = $j('<tr/>').append(titleLabel).append(titleField );
		
		var table = $j('<table><tbody></tbody></table>');
		table.find('tbody').append(urlRow).append(titleRow);
		
		var dialog = $j('<div/>').append(table);
		
		$j(this).prepend(dialog);
		
		var dialogOptions = {
			'AutoOpen' : true,
			'width' : 600
		};
		
		dialog.dialog( dialogOptions );
		
		e.preventDefault();
	},
	
	'getToken' : function( callback ) {
		var getTokenParams =
		{
			'action' : 'query',
			'prop' : 'info',
			'intoken' : 'edit',
			'titles' : 'Some Title',
			'format' : 'json'
		};
		
		$j.get( wgScriptPath+'/api'+wgScriptExtension, getTokenParams,
			function( data ) {
				var token = data.query.pages[-1].edittoken;
				
				callback(token);
			}, 'json' );
	},
	
	'handleAJAXSave' : function( e ) {
		var editform = $j(this).closest('.lqt-edit-form');
		var type = editform.find('input[name=lqt_method]').val();
		
		var text = editform.find('#wpTextbox1').val();
		var summary = editform.find('#wpSummary').val();
		
		// Check if summary is undefined
		if (summary === undefined) {
			summary = '';
		}
		
		var subject = editform.find( '#lqt_subject_field' ).val();
		var replyThread = editform.find('input[name=lqt_operand]').val();
		var bump = editform.find('#wpBumpThread').is(':checked') ? 1 : 0;
		
		var spinner = $j('<div class="mw-ajax-loader"/>');
		editform.prepend(spinner);
		
		var replyCallback = function( data ) { 
			// Grab topmost thread, reload it.
			var topmostThread = editform.closest('.lqt-thread-topmost');
			var post = topmostThread.find('.lqt-post-wrapper');
//			var threadID = post.data('thread-id'); Unused, but useful
			var newPostID = data.threadaction.thread['thread-id'];
			
			// Load data from JSON
			var html = data.threadaction.thread['html']
			var newContent = $j(html);
				
			// Clear old post.
			topmostThread.empty();
				
			// Replace post content
			var newThread = newContent.filter('div.lqt_thread')[0];
			var newThreadContent = $j(newThread).contents();
			topmostThread.append( newThreadContent );
			topmostThread.removeClass( 'lqt-thread-no-subthreads' );
			topmostThread.addClass( 'lqt-thread-with-subthreads' );
				
			// Set up thread.
			topmostThread.find('.lqt-post-wrapper').each( function() {
				liquidThreads.setupThread( $j(this) );
			} );
				
			// Scroll to the new post.
			var newPost = $j('#lqt_thread_id_'+newPostID);
			var targetOffset = $j(newPost).offset().top;
			$j('html,body').animate({scrollTop: targetOffset}, 'slow');
		}
		
		var newCallback = function( data ) {
			// Grab the thread ID
			var newThreadID = data.threadaction.thread['thread-id'];
			var html = data.threadaction.thread['html'];
			
			var newThread = $j(html);
			
			if ( $j('.lqt_toc').length ) {
				$j('.lqt_toc').after(newThread);
			} else {
				$j('.lqt-no-threads').replaceWith( newThread );
			}
			
			$j(newThread).find( '.lqt-post-wrapper').each(
				function() {
					// Set up thread.
					liquidThreads.setupThread( $j(this) );
					
					targetOffset = $j(this).offset().top;
					$j('html,body').animate(
						{scrollTop: targetOffset},
						'slow');
				}
			);
		}
		
		var doneCallback = function(data) {
			try {
				var result = data.threadaction.thread.result;
			} catch ( err ) {
				result = 'error';
			}
			
			if ( result != 'Success' ) {
				// Create a hidden field to mimic the save button, and
				// submit it normally, so they'll get a real error message.
				
				var saveHidden = $j('<input/>');
				saveHidden.attr( 'type', 'hidden' );
				saveHidden.attr( 'name', 'wpSave' );
				saveHidden.attr( 'value', 'Save' );
				
				var form = editform.find('#editform');
				form.append(saveHidden);
				form.submit();
				return;
			}

			var callback;
			
			if ( type == 'reply' ) {
				callback = replyCallback;
			}
			
			if ( type == 'talkpage_new_thread' ) {
				callback = newCallback;
			}
			
			editform.empty().hide();
			
			callback(data);
			
			// Load the new TOC
			liquidThreads.reloadTOC();
		};
		
		if ( type == 'reply' ) {			
			liquidThreads.doReply( replyThread, text, summary,
						doneCallback, bump );
			
			e.preventDefault();
		} else if ( type == 'talkpage_new_thread' ) {
			liquidThreads.doNewThread( wgPageName, subject, text, summary,
					doneCallback, bump );
			
			e.preventDefault();
		}
	},
	
	'reloadTOC' : function() {
		var toc = $j('.lqt_toc');
		var loadTOCSpinner = $j('<div class="mw-ajax-loader"/>');
		loadTOCSpinner.css( 'height', toc.height() );
		toc.empty().append( loadTOCSpinner );
		toc.load( window.location.href + ' .lqt_toc > *',
			function() {
				loadTOCSpinner.remove();
			} );
	},
	
	'doNewThread' : function( talkpage, subject, text, summary, callback, bump ) {
		liquidThreads.getToken(
			function(token) {
				var newTopicParams =
				{
					'action' : 'threadaction',
					'threadaction' : 'newthread',
					'talkpage' : talkpage,
					'subject' : subject,
					'text' : text,
					'token' : token,
					'format' : 'json',
					'render' : '1',
					'reason' : summary,
					'bump' : bump
				};
				
				$j.post( wgScriptPath+'/api'+wgScriptExtension, newTopicParams,
					function(data) {
						if (callback) {
							callback(data);
						}
					}, 'json' );
			} );
	},
	
	'doReply' : function( thread, text, summary, callback, bump ) {
		liquidThreads.getToken(
			function(token) {
				var replyParams =
				{
					'action' : 'threadaction',
					'threadaction' : 'reply',
					'thread' : thread,
					'text' : text,
					'token' : token,
					'format' : 'json',
					'render' : '1',
					'reason' : summary,
					'bump' : bump
				};
				
				$j.post( wgScriptPath+'/api'+wgScriptExtension, replyParams,
					function(data) {
						if (callback) {
							callback(data);
						}
					}, 'json' );
			} );
	},
	
	'onTextboxKeyUp' : function(e) {
		// Check if a user has signed their post, and if so, tell them they don't have to.
		var text = $j(this).val().trim();
		var prevWarning = $j('#lqt-sign-warning');
		if ( text.match(/~~~~$/) ) {
			if ( prevWarning.length ) {
				return;
			}
			
			// Show the warning
			var msg = wgLqtMessages['lqt-sign-not-necessary'];
			var elem = $j('<div id="lqt-sign-warning" class="error"/>');
			elem.text(msg);
			
			$j(this).before( elem );
		} else {
			prevWarning.remove();
		}
	},
	
	'apiRequest' : function( request, callback ) {
		// Set new subject through API.
		liquidThreads.getToken( function(token) {
			
			if ( typeof request == 'function' ) {
				request = request(token);
			} else {
				request.token = token;
			}
			
			request.format = 'json';
			
			var path = wgScriptPath+'/api'+wgScriptExtension;
			$j.post( path, request,
					function(data) {
						if (callback) {
							callback(data);
						}
					}, 'json' );
		} );
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
	
	// Live bind for unwatch/watch stuff.
	$j('.lqt-command-watch').live( 'click', liquidThreads.asyncWatch );
	$j('.lqt-command-unwatch').live( 'click', liquidThreads.asyncWatch );
	
	// Set up periodic update checking
	setInterval( liquidThreads.checkForUpdates, 60000 );
	
	// Autogrowing textarea - this only affects the new-topic page
//	$j('#wpTextbox1')//.autogrow();

	$j('#wpSave').live( 'click', liquidThreads.handleAJAXSave );
	$j('#wpTextbox1').live( 'keyup', liquidThreads.onTextboxKeyUp );
	$j(document).click( liquidThreads.handleDocumentClick );
} );

