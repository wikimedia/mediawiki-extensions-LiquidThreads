/**
 * LiquidThreads core javascript library.
 *
 * Exposes global object `liquidThreads`.
 * Exposes static method `jQuery.getCSS`.
 *
 * FIXME: This module uses deprecated jQuery.browser.
 */
/*global liquidThreads, alert, wgWikiEditorPreferences, mwSetupToolbar */
( function ( mw, $ ) {

window.wgWikiEditorIconVersion = 0;

$.getCSS = function ( url, media ) {
	$( '<link>' ).attr( {
		href: url,
		media: media || 'screen',
		type: 'text/css',
		rel: 'stylesheet'
	} ).appendTo( 'head' );
};

window.liquidThreads = {
	currentReplyThread : null,
	currentToolbar : null,

	'handleReplyLink' : function ( e ) {
		if ( e.preventDefault ) {
			e.preventDefault();
		}

		var target = this;

		if ( !this.className && e.target) {
			target = $( e.target );
		}

		var container = $( target ).closest( '.lqt_thread' )[0];
		var thread_id = $( this ).data( 'thread-id' );

		// hide the form for this thread if it's currently being shown
		if ( thread_id === liquidThreads.currentReplyThread && $( '#wpTextbox1' ).is( ':visible' ) ) {
			liquidThreads.cancelEdit( {} );
			return;
		}

		var params = { 'method' : 'reply', 'thread' : thread_id };

		var repliesElement = $( container ).contents().filter( '.lqt-thread-replies' );
		var replyDiv = repliesElement.contents().filter( '.lqt-reply-form' );
		replyDiv = replyDiv.add( $( container ).contents().filter( '.lqt-reply-form' ) );
		if ( !replyDiv.length ) {
			// Create a div for it
			replyDiv = $( '<div class="lqt-reply-form lqt-edit-form"/>' );

			// Try to find a place for it
			if ( !repliesElement.length ) {
				repliesElement = liquidThreads.getRepliesElement( $( container ) );
			}

			repliesElement.find( '.lqt-replies-finish' ).before( replyDiv );
		}
		replyDiv.show();

		replyDiv = replyDiv[0];

		liquidThreads.injectEditForm( params, replyDiv, e.preload );
		liquidThreads.currentReplyThread = thread_id;
	},

	'getRepliesElement' : function ( thread /* a .lqt_thread */ ) {
		var repliesElement = thread.contents().filter( '.lqt-thread-replies' );

		if ( !repliesElement.length ) {
			repliesElement = $( '<div class="lqt-thread-replies"/>' );

			var finishDiv = $( '<div class="lqt-replies-finish"/>' );
			finishDiv.append( $( '<div class="lqt-replies-finish-corner"/>' ) );
			finishDiv.contents().html( '&nbsp;' );
			repliesElement.append( finishDiv );

			var repliesFinishElement = thread.contents().filter( '.lqt-replies-finish' );
			if ( repliesFinishElement.length ) {
				repliesFinishElement.before( repliesElement );
			} else {
				thread.append( repliesElement );
			}
		}

		return repliesElement;
	},

	'checkEmptyReplies' : function ( element, action ) {
		var contents = element.contents();

		contents = contents.not( '.lqt-replies-finish,.lqt-post-sep,.lqt-edit-form' );

		if ( !contents.length ) {
			if ( action === undefined || action === 'remove' ) {
				element.remove();
			} else {
				element.hide();
			}
		}
	},

	'handleNewLink' : function ( e ) {
		e.preventDefault();

		var talkpage = $( this ).attr( 'lqt_talkpage' );
		var params = { 'talkpage' : talkpage, 'method' : 'talkpage_new_thread' };

		var container = $( '.lqt-new-thread' );
		container.data( 'lqt-talkpage', talkpage );

		liquidThreads.injectEditForm( params, container );
		liquidThreads.currentReplyThread = 0;
	},

	'handleEditLink' : function ( e ) {
		e.preventDefault();

		// Grab the container.
		var parent = $( this ).closest( '.lqt-post-wrapper' );

		var container = $( '<div/>' ).addClass( 'lqt-edit-form' );
		parent.contents().fadeOut();
		parent.append( container );
		var params = { 'method' : 'edit', 'thread' : parent.data( 'thread-id' ) };

		liquidThreads.injectEditForm( params, container );
	},

	'injectEditForm' : function ( params, container, preload ) {
		var page = $( container ).closest( '.lqt-thread-topmost' )
				.find( '.lqt-thread-talkpage-metadata' ).val();
		if ( !page ) {
			page = $( container ).data( 'lqt-talkpage' );
		}

		liquidThreads.cancelEdit( container );

		var isIE7 = $.browser.msie && $.browser.version.substr( 0, 1 ) === '7';

		var loadSpinner = $( '<div class="mw-ajax-loader lqt-loader"/>' );
		$( container ).before( loadSpinner );

		var finishShow = function () {
			// Scroll to the textbox
			var targetOffset = $( container ).offset().top;
			var windowHeight = $( window ).height();
			var editBoxHeight = $( container ).height();

			var scrollOffset;
			if ( windowHeight < editBoxHeight ) {
				scrollOffset = targetOffset;
			} else {
				scrollOffset = targetOffset - windowHeight + editBoxHeight;
			}

			$( 'html, body' ).animate( { scrollTop: scrollOffset }, 'slow' );
			// Auto-focus and set to auto-grow as well
			$( container ).find( '#wpTextbox1' ).focus();//.autogrow();
			// Focus the subject field if there is one. Overrides previous line.
			$( container ).find( '#lqt_subject_field' ).focus();

			// Update signature editor
			$( container ).find( 'input[name=wpLqtSignature]' ).hide();
			$( container ).find( '.lqt-signature-preview' ).show();
			var editLink = $( '<a class="lqt-signature-edit-button"/>' );
			editLink.text( mw.msg( 'lqt-edit-signature' ) );
			editLink.click( liquidThreads.handleEditSignature );
			editLink.attr( 'href', '#' );
			$( container ).find( '.lqt-signature-preview' ).after( editLink );
			editLink.before( ' ' );
		};

		var finishSetup = function () {
			// Kill the loader.
			$( '.lqt-loader' ).remove();

			if ( preload ) {
				$( 'textarea', container )[0].value = preload;
			}

			if ( isIE7 ) {
				setTimeout( finishShow, 500 );
			} else {
				$( container ).slideDown( 'slow', finishShow );
			}

			var cancelButton = $( container ).find( '#mw-editform-cancel' );
			cancelButton.click( liquidThreads.cancelEdit );

			$( container ).find( '#wpTextbox1' ).attr( 'rows', 12 );
			$( container ).find( '#wpDiff' ).hide();

			if ( $.fn.wikiEditor && $.wikiEditor.isSupported( $.wikiEditor.modules.toolbar ) ) {
				var useDialogs;

				if ( typeof wgWikiEditorPreferences !== 'undefined' ) {
					useDialogs = wgWikiEditorPreferences.toolbar.dialogs;
				} else {
					useDialogs = mw.user.options.get( 'usebetatoolbar-cgd' );
				}

				if ( useDialogs && $.wikiEditor.isSupported( $.wikiEditor.modules.dialogs ) ) {
					$( '#wpTextbox1' ).addClass( 'toolbar-dialogs' );
				}

				// Add wikiEditor toolbar
				$( '#wpTextbox1' ).wikiEditor( 'addModule', { 'toolbar': liquidThreads.toolbar.config, 'dialogs': liquidThreads.toolbar.dialogs } );


				// cleanup unnecessary things from the old toolbar
				$( '#editpage-specialchars' ).remove();
				$( '#wpTextbox1' ).focus();
			} else {
				// Add old toolbar
				mwSetupToolbar(); // Fix it. Can't locate this.
			}
			var currentFocused = $( container ).find( '#wpTextbox1' );
			mw.hook( 'ext.lqt.textareaCreated' ).fire( currentFocused );
			$( container ).find( '#wpTextbox1,#wpSummary' ).focus( function () {
				currentFocused = this;
			} );
		};

		mw.loader.using( ['mediawiki.action.edit'],
			function () {
				if ( isIE7 ) {
					$( container ).empty().show();
				}
				liquidThreads.loadInlineEditForm( params, container, function () {
					mw.loader.using(
						[ 'ext.wikiEditor', 'user.options',
							'jquery.wikiEditor.toolbar', 'jquery.wikiEditor.dialogs',
							'jquery.async', 'jquery.cookie' ],
						finishSetup
					);
				} );
			} );

	},

	'loadInlineEditForm' : function ( params, container, callback ) {
		params.action = 'threadaction';
		params.threadaction = 'inlineeditform';
		params.token = mw.user.tokens.get( 'editToken' );

		( new mw.Api() ).post( params ).done( function ( result ) {
			$( container ).empty().append( $( result.threadaction.inlineeditform.html ).contents() );

			callback();
		} );
	},

	//From http://clipmarks.com/clipmark/CEFC94CB-94D6-4495-A7AA-791B7355E284/
	'insertAtCursor' : function ( myField, myValue ) {
		//IE support
		if ( document.selection ) {
			myField.focus();
			var sel = document.selection.createRange();
			sel.text = myValue;
		}
		//MOZILLA/NETSCAPE support
		else if ( myField.selectionStart || myField.selectionStart === '0' ) {
			var startPos = myField.selectionStart;
			var endPos = myField.selectionEnd;
			myField.value = myField.value.substring( 0, startPos ) + myValue + myField.value.substring( endPos, myField.value.length );
		} else {
			myField.value += myValue;
		}
	},

	'getSelection' : function () {
		if ( window.getSelection ) {
			return window.getSelection().toString();
		} else if ( document.selection ) {
			return document.selection.createRange().text;
		} else if ( document.getSelection ) {
			return document.getSelection();
		} else {
			return '';
		}
	},

	'cancelEdit' : function ( e ) {
		if ( e !== undefined && e.preventDefault ) {
			e.preventDefault();
		}

		// XXX: Should this be e.target instead of e?
		$( '.lqt-edit-form' ).not( e ).each(
			function () {
				var repliesElement = $( this ).closest( '.lqt-thread-replies' );
				$( this ).fadeOut( 'slow',
					function () {
						$( this ).empty();

						if ( $( this ).parent().is( '.lqt-post-wrapper' ) ) {
							$( this ).parent().contents().fadeIn();
							$( this ).remove();
						}

						liquidThreads.checkEmptyReplies( repliesElement );
					} );
			} );

		liquidThreads.currentReplyThread = null;
	},

	'setupMenus' : function () {
		var post = $( this );

		var toolbar = post.contents().filter( '.lqt-thread-toolbar' );
		var threadID = post.data( 'thread-id' );
		var menu = post.find( '.lqt-thread-toolbar-command-list' );
		var menuContainer = post.find( '.lqt-thread-toolbar-menu' );
		menu.remove().appendTo( menuContainer );
		menuContainer.find( '.lqt-thread-toolbar-command-list' ).hide();

		// Add handler for reply link
		var replyLink = toolbar.find( '.lqt-command-reply > a' );
		replyLink.data( 'thread-id', threadID );
		replyLink.click( liquidThreads.handleReplyLink );

		// Add "Drag to new location" to menu
		var dragLI = $( '<li class="lqt-command-drag lqt-command" />' );
		var dragLink = $( '<a/>' ).text( mw.msg( 'lqt-drag-activate' ) );
		dragLink.attr( 'href', '#' );
		dragLI.append( dragLink );
		dragLink.click( liquidThreads.activateDragDrop );

		menu.append( dragLI );

		// Remove split and merge
		menu.contents().filter( '.lqt-command-split,.lqt-command-merge' ).remove();

		var trigger = menuContainer.find( '.lqt-thread-actions-trigger' );

		trigger.show();
		menu.hide();

		// FIXME: After a drag-and-drop, this stops working on the thread and its replies
		trigger.click(
			function ( e ) {
				e.stopImmediatePropagation();
				e.preventDefault();

				// Hide the other menus
				$( '.lqt-thread-toolbar-command-list' ).not( menu ).hide( 'fast' );

				menu.toggle( 'fast' );

				var windowHeight = $( window ).height();
				var toolbarOffset = toolbar.offset().top;
				var scrollPos = $( window ).scrollTop();

				var menuBottom = ( toolbarOffset + 150 - scrollPos );

				if ( menuBottom > windowHeight ) {
					// Switch to an upwards menu.
					menu.css( 'bottom', toolbar.height() );
				} else {
					menu.css( 'bottom', 'auto' );
				}
			} );
	},

	'setupThreadMenu' : function ( menu, id ) {
		if ( menu.find( '.lqt-command-edit-subject' ).length ||
			menu.closest( '.lqt_thread' ).is( '.lqt-thread-uneditable' )
		) {
			return;
		}

		var editSubjectField = $( '<li/>' );
		var editSubjectLink = $( '<a href="#"/>' );
		editSubjectLink.text( mw.msg( 'lqt-change-subject' ) );
		editSubjectField.append( editSubjectLink );
		editSubjectField.click( liquidThreads.handleChangeSubject );
		editSubjectField.data( 'thread-id', id );

		editSubjectField.addClass( 'lqt-command-edit-subject' );

		// appending a space first to prevent cursive script character joining across elements
		menu.append( ' ', editSubjectField );
	},

	'handleChangeSubject' : function ( e ) {
		e.preventDefault();

		$( this ).closest( '.lqt-command-edit-subject' ).hide();

		// Grab the h2
		var threadId = $( this ).data( 'thread-id' );
		var header = $( '#lqt-header-' + threadId );
		var headerText = header.find( "input[name='raw-header']" ).val();

		var textbox = $( '<input type="textbox" />' ).val( headerText );
		textbox.attr( 'id', 'lqt-subject-input-' + threadId );
		textbox.attr( 'size', '75' );
		textbox.val( headerText );

		var saveText = mw.msg( 'lqt-save-subject' );
		var saveButton = $( '<input type="button" />' );
		saveButton.val( saveText );
		saveButton.click( liquidThreads.handleSubjectSave );

		var cancelButton = $( '<input type="button" />' );
		cancelButton.val( mw.msg( 'lqt-cancel-subject-edit' ) );
		cancelButton.click( function () {
			var form = $( this ).closest( '.mw-subject-editor' );
			var header = form.closest( '.lqt_header' );
			header.contents().filter( '.mw-headline' ).show();
			header.next().find( '.lqt-command-edit-subject' ).show();
			form.remove();

		} );

		header.contents().filter( 'span.mw-headline' ).hide();

		var subjectForm = $( '<span class="mw-subject-editor"/>' );
		subjectForm.append( textbox );
		subjectForm.append( '&nbsp;' );
		subjectForm.append( saveButton );
		subjectForm.append( '&nbsp;' );
		subjectForm.append( cancelButton );
		subjectForm.data( 'thread-id', threadId );

		header.append( subjectForm );
	},

	handleSubjectSave: function () {
		var button = $( this );
		var subjectForm = button.closest( '.mw-subject-editor' );
		var header = subjectForm.closest( '.lqt_header' );
		var threadId = subjectForm.data( 'thread-id' );
		var textbox = $( '#lqt-subject-input-'+threadId );
		var newSubject = $.trim( textbox.val() );

		if ( !newSubject ) {
			alert( mw.msg( 'lqt-ajax-no-subject' ) );
			return;
		}

		// Add a spinner
		var spinner = $( '<div class="mw-ajax-loader"/>' );
		header.append( spinner );
		subjectForm.hide();

		var request = {
			action: 'threadaction',
			threadaction: 'setsubject',
			subject: $.trim( newSubject ),
			thread: threadId,
			token: mw.user.tokens.get( 'editToken' )
		};

		// Set new subject through API.
		( new mw.Api() ).post( request ).done( function ( reply ) {
			var result;

			try {
				result = reply.threadaction.thread.result;
			} catch ( err ) {
				result = 'error';
			}

			if ( result === 'success' ) {
				spinner.remove();
				header.next().find( '.lqt-command-edit-subject' ).show();

				liquidThreads.doReloadThread( $( '#lqt_thread_id_' + threadId ) );
			} else {
				var code, description;
				try {
					code = reply.error.code;
					description = reply.error.info;

					if ( code === 'invalid-subject' ) {
						alert( mediaWiki.msg( 'lqt-ajax-invalid-subject' ) );
					}

					subjectForm.show();
					spinner.remove();
				} catch ( err ) {
					alert( mediaWiki.msg( 'lqt-save-subject-error-unknown' ) );
					subjectForm.remove();
					spinner.remove();
					header.contents().filter( '.mw-headline' ).show();
					header.next().find( '.lqt-command-edit-subject' ).show();
				}
			}
		} );
	},

	handleDocumentClick: function () {
		// Collapse all menus
		$( '.lqt-thread-toolbar-command-list' ).hide( 'fast' );
	},

	'checkForUpdates' : function () {
		var threadModifiedTS = {};
		var threads = [];

		$( '.lqt-thread-topmost' ).each( function () {
			var tsField = $( this ).find( '.lqt-thread-modified' );
			if ( tsField.length ) {
				var oldTS = tsField.val();
				// Prefix is lqt-thread-modified-
				var threadID = tsField.attr( 'id' ).substr( 'lqt-thread-modified-'.length );
				threadModifiedTS[threadID] = oldTS;
				threads.push( threadID );
			}
		} );

		// Optimisation: if no threads are to be checked, do not check.
		if ( !threads.length ) {
			return;
		}

		( new mw.Api() ).get( {
			'action': 'query',
			'list'  : 'threads',
			'thid'  : threads.join( '|' ),
			'thprop': 'id|subject|parent|modified'
		} ).done ( function ( data ) {
			var threads = data.query.threads;

			$.each( threads, function ( i, thread ) {
				var threadID = thread.id;
				var threadModified = thread.modified;

				if ( threadModified !== threadModifiedTS[threadID] ) {
					liquidThreads.showUpdated( threadID );
				}
			} );
		} );
	},

	'showUpdated' : function ( id ) {
		// Check if there's already an updated marker here
		var threadObject = $( '#lqt_thread_id_' + id );

		if ( threadObject.find( '.lqt-updated-notification' ).length ) {
			return;
		}

		var notifier = $( '<div/>' );
		notifier.text( mw.msg( 'lqt-ajax-updated' ) + ' ' );
		notifier.addClass( 'lqt-updated-notification' );

		var updateButton = $( '<a href="#"/>' );
		updateButton.text( mw.msg( 'lqt-ajax-update-link' ) );
		updateButton.addClass( 'lqt-update-link' );
		updateButton.click( liquidThreads.updateThread );

		notifier.append( updateButton );

		threadObject.prepend( notifier );
	},

	'updateThread' : function ( e ) {
		e.preventDefault();

		var thread = $( this ).closest( '.lqt_thread' );

		liquidThreads.doReloadThread( thread );
	},

	'doReloadThread' : function ( thread /* The .lqt_thread */ ) {
		var post = thread.find( 'div.lqt-post-wrapper' )[0];
		post = $( post );
		var threadId = thread.data( 'thread-id' );
		var loader = $( '<div class="mw-ajax-loader"/>' );
		var header = $( '#lqt-header-' + threadId );

		thread.prepend( loader );

		// Build an AJAX request
		( new mw.Api() ).get( {
			action  : 'query',
			list    : 'threads',
			thid    : threadId,
			thrender: 1
		} ).done( function ( data ) {
			// Load data from JSON
			var html = data.query.threads[threadId].content;
			var newContent = $( html );

			// Clear old post and header.
			thread.empty();
			thread.hide();
			header.empty();
			header.hide();

			// Replace post content
			var newThread = newContent.filter( 'div.lqt_thread' );
			var newThreadContent = newThread.contents();
			thread.append( newThreadContent );
			thread.attr( 'class', newThread.attr( 'class' ) );

			// Set up thread.
			thread.find( '.lqt-post-wrapper' ).each( function () {
				liquidThreads.setupThread( $( this ) );
			} );

			header.fadeIn();
			thread.fadeIn();

			// Scroll to the updated thread.
			var targetOffset = $( thread ).offset().top;
			$( 'html, body' ).animate( { scrollTop: targetOffset }, 'slow' );
		} );
	},

	'setupThread' : function ( threadContainer ) {
		var prefixLength = 'lqt_thread_id_'.length;
		// Add the interruption class if it needs it
		// FIXME: misses a lot of cases
		var $parentWrapper = $( threadContainer )
			.closest( '.lqt-thread-wrapper' ).parent().closest( '.lqt-thread-wrapper' );
		if ( $parentWrapper.next( '.lqt-thread-wrapper' ).length > 0 ) {
			$parentWrapper
				.find( '.lqt-thread-replies' )
				.addClass( 'lqt-thread-replies-interruption' );
		}
		// Update reply links
		var threadWrapper = $( threadContainer ).closest( '.lqt_thread' )[0];
		var threadId = threadWrapper.id.substring( prefixLength );

		$( threadContainer ).data( 'thread-id', threadId );
		$( threadWrapper ).data( 'thread-id', threadId );

		// Set up reply link
		var replyLinks = $( threadWrapper ).find( '.lqt-add-reply' );
		replyLinks.click( liquidThreads.handleReplyLink );
		replyLinks.data( 'thread-id', threadId );

		// Hide edit forms
		$( threadContainer ).find( 'div.lqt-edit-form' ).each(
			function () {
				if ( $( this ).find( '#wpTextbox1' ).length ) {
					return;
				}

				this.style.display = 'none';
			} );

		// Update menus
		$( threadContainer ).each( liquidThreads.setupMenus );

		// Update thread-level menu, if appropriate
		if ( $( threadWrapper ).hasClass( 'lqt-thread-topmost' ) ) {
			// To perform better, check the 3 elements before the top-level thread container before
			//  scanning the whole document
			var menu,
				threadLevelCommandSelector = '#lqt-threadlevel-commands-'+threadId,
				traverseElement = $( threadWrapper );

			for ( var i = 0; i < 3 && menu === undefined; ++i ) {
				traverseElement = traverseElement.prev();
				if ( traverseElement.is( threadLevelCommandSelector ) ) {
					menu = traverseElement;
				}
			}

			if ( typeof menu === 'undefined' ) {
				menu = $( threadLevelCommandSelector );
			}

			liquidThreads.setupThreadMenu( menu, threadId );
		}
	},

	'showReplies' : function ( e ) {
		e.preventDefault();

		// Grab the closest thread
		var thread = $( this ).closest( '.lqt_thread' ).find( 'div.lqt-post-wrapper' )[0];
		thread = $( thread );
		var threadId = thread.data( 'thread-id' );
		var replies = thread.parent().find( '.lqt-thread-replies' );
		var loader = $( '<div class="mw-ajax-loader"/>' );
		var sep = $( '<div class="lqt-post-sep">&nbsp;</div>' );

		replies.empty();
		replies.hide();
		replies.before( loader );

		( new mw.Api() ).get( {
			action  : 'query',
			list    : 'threads',
			thid    : threadId,
			thrender: '1',
			thprop  : 'id'
		} ).done( function ( data ) {
			// Interpret
			if ( typeof data.query.threads[threadId] !== 'undefined' ) {
				var content = data.query.threads[threadId].content;
				content = $( content ).find( '.lqt-thread-replies' )[0];

				// Inject
				replies.empty().append( $( content ).contents() );

				// Remove post separator, if it follows the replies element
				if ( replies.next().is( '.lqt-post-sep' ) ) {
					replies.next().remove();
				}

				// Set up
				replies.find( 'div.lqt-post-wrapper' ).each( function () {
					liquidThreads.setupThread( $( this ) );
				} );

				replies.before( sep );

				// Show
				loader.remove();
				replies.fadeIn( 'slow' );
			}
		} );
	},

	'showMore' : function ( e ) {
		e.preventDefault();

		// Add spinner
		var loader = $( '<div class="mw-ajax-loader"/>' );
		$( this ).after( loader );

		// Grab the appropriate thread
		var thread = $( this ).closest( '.lqt_thread' ).find( 'div.lqt-post-wrapper' )[0];
		thread = $( thread );
		var threadId = thread.data( 'thread-id' );

		// Find the hidden field that gives the point to start at.
		var startAtField = $( this ).siblings().filter( '.lqt-thread-start-at' );
		var startAt = startAtField.val();
		startAtField.remove();

		( new mw.Api() ).get( {
			action   : 'query',
			list     : 'threads',
			thid     : threadId,
			thrender : '1',
			thprop   : 'id',
			threnderstartrepliesat: startAt
		} ).done( function ( data ) {
			var content = data.query.threads[threadId].content;
			content = $( content ).find( '.lqt-thread-replies' )[0];
			content = $( content ).contents();
			content = content.not( '.lqt-replies-finish' );

			if ( $( content[0] ).is( '.lqt-post-sep' ) ) {
				content = content.not( $( content[0] ) );
			}

			// Inject loaded content.
			content.hide();
			loader.after( content );

			content.find( 'div.lqt-post-wrapper' ).each( function () {
				liquidThreads.setupThread( $( this ) );
			} );

			content.fadeIn();
			loader.remove();
		} );

		$( this ).remove();
	},

	'asyncWatch' : function ( e ) {
		var button = $( this ),
			tlcOffset = 'lqt-threadlevel-commands-'.length,
			oldButton = button.clone();
			// Find the title of the thread
			var threadLevelCommands = button.closest( '.lqt_threadlevel_commands' );
			var title = $( '#lqt-thread-title-' + threadLevelCommands.attr( 'id' ).substring( tlcOffset ) ).val();

		// Check if we're watching or unwatching.
		var action = '';
		if ( button.hasClass( 'lqt-command-watch' ) ) {
			button.removeClass( 'lqt-command-watch' ).addClass( 'lqt-command-unwatch' );
			button.find( 'a' ).attr( 'href', button.find( 'a' ).attr( 'href' ).replace( 'watch', 'unwatch' ) ).text( mw.msg( 'unwatch' ) );
			action = 'watch';
		} else if ( button.hasClass( 'lqt-command-unwatch' ) ) {
			button.removeClass( 'lqt-command-unwatch' ).addClass( 'lqt-command-watch' );
			action = 'unwatch';
			button.find( 'a' ).attr( 'href', button.find( 'a' ).attr( 'href' ).replace( 'unwatch', 'watch' ) ).text( mw.msg( 'watch' ) );
		}

		// Replace the watch link with a spinner
		var spinner = $( '<li/>' ).html( '&nbsp;' ).addClass( 'mw-small-spinner' );
		button.replaceWith( spinner );

		// Check if we're watching or unwatching.
		var api = new mw.Api(),
			success = function () {
				spinner.replaceWith( button );
			},
			error = function () {
				// FIXME: Use a better i18n way to show this
				alert( 'failed to connect.. Please try again!' );
				spinner.replaceWith( oldButton );
			};

		if ( action === 'unwatch' ) {
			api.unwatch( title ).done( success ).fail( error );
		} else if ( action === 'watch' ) {
			api.watch( title ).done( success ).fail( error );
		}

		e.preventDefault();
	},

	'showThreadLinkWindow' : function ( e ) {
		e.preventDefault();
		var thread = $( this ).closest( '.lqt_thread' );
		var linkTitle = thread.find( '.lqt-thread-title-metadata' ).val();
		var linkURL = mw.util.getUrl( linkTitle );
		linkURL = mw.config.get( 'wgServer' ) + linkURL;
		if ( linkURL.substr( 0, 2 ) === '//' ) {
			linkURL = window.location.protocol + linkURL;
		}
		liquidThreads.showLinkWindow( linkTitle, linkURL );
	},

	'showSummaryLinkWindow' : function ( e ) {
		e.preventDefault();
		var linkURL = mw.config.get( 'wgServer' ) + $( this ).attr( 'href' );
		if ( linkURL.substr( 0, 2 ) === '//' ) {
			linkURL = window.location.protocol + linkURL;
		}
		var linkTitle = $( this ).parent().find( 'input[name=summary-title]' ).val();
		liquidThreads.showLinkWindow( linkTitle, linkURL );
	},

	'showLinkWindow' : function ( linkTitle, linkURL ) {
		linkTitle = '[['+linkTitle+']]';

		// Build dialog
		var urlLabel = $( '<th/>' ).text( mw.msg( 'lqt-thread-link-url' ) );
		var urlField = $( '<td/>' ).addClass( 'lqt-thread-link-url' );
		urlField.text( linkURL );
		var urlRow = $( '<tr/>' ).append( urlLabel ).append( urlField );

		var titleLabel = $( '<th/>' ).text( mw.msg( 'lqt-thread-link-title' ) );
		var titleField = $( '<td/>' ).addClass( 'lqt-thread-link-title' );
		titleField.text( linkTitle );
		var titleRow = $( '<tr/>' ).append( titleLabel ).append( titleField );

		var table = $( '<table><tbody></tbody></table>' );
		table.find( 'tbody' ).append( urlRow ).append( titleRow );

		var dialog = $( '<div/>' ).append( table );

		$( 'body' ).prepend( dialog );

		dialog.dialog( { 'width' : 600 } );
	},

	'handleAJAXSave' : function ( e ) {
		var editform = $( this ).closest( '.lqt-edit-form' );
		var type = editform.find( 'input[name=lqt_method]' ).val();
		var wikiEditorContext = editform.find( '#wpTextbox1' ).data( 'wikiEditor-context' );
		var text;

		if ( !wikiEditorContext || typeof( wikiEditorContext ) === 'undefined' ||
				!wikiEditorContext.$iframe ) {
			text = editform.find( '#wpTextbox1' ).val();
		} else {
			text = wikiEditorContext.$textarea.textSelection( 'getContents' );
		}

		if ( $.trim( text ).length === 0 ) {
			alert( mw.msg( 'lqt-empty-text' ) );
			return;
		}

		var summary = editform.find( '#wpSummary' ).val();

		var signature;
		if ( editform.find( 'input[name=wpLqtSignature]' ).length ) {
			signature = editform.find( 'input[name=wpLqtSignature]' ).val();
		} else {
			signature = undefined;
		}

		// Check if summary is undefined
		if ( summary === undefined ) {
			summary = '';
		}

		var subject = editform.find( '#lqt_subject_field' ).val();
		var replyThread = editform.find( 'input[name=lqt_operand]' ).val();
		var bumpBox = editform.find( '#wpBumpThread' );
		var bump = bumpBox.length === 0 || bumpBox.is( ':checked' );

		var spinner = $( '<div class="mw-ajax-loader"/>' );
		editform.prepend( spinner );

		var replyCallback = function ( data ) {
			var $parent = $( '#lqt_thread_id_' + data.threadaction.thread['parent-id'] );
			var $html = $( data.threadaction.thread.html );
			var $newThread = $html.find( '#lqt_thread_id_' + data.threadaction.thread['thread-id'] );
			$parent.find( '.lqt-thread-replies:first' ).append( $newThread );
			$parent.closest( '.lqt-thread-topmost' )
				.find( 'input.lqt-thread-modified' )
				.val( data.threadaction.thread.modified );
			liquidThreads.setupThread( $newThread.find( '.lqt-post-wrapper' ) );
			$( 'html,body' ).animate( { scrollTop: $newThread.offset().top }, 'slow');
		};

		var newCallback = function ( data ) {
			var $newThread = $( data.threadaction.thread.html );
			$( '.lqt-threads' ).prepend( $newThread );
			// remove the no threads message if it's on the page
			$( '.lqt-no-threads' ).remove();
			liquidThreads.setupThread( $newThread.find( '.lqt-post-wrapper' ) );
			$( 'html,body' ).animate( { scrollTop: $newThread.offset().top }, 'slow' );
		};

		var editCallback = function () {
			liquidThreads.doReloadThread( editform.closest( '.lqt-thread-topmost' ) );
		};

		var errorCallback = function () {
			// Create a hidden field to mimic the save button, and
			// submit it normally, so they'll get a real error message.

			var saveHidden = $( '<input/>' );
			saveHidden.attr( 'type', 'hidden' );
			saveHidden.attr( 'name', 'wpSave' );
			saveHidden.attr( 'value', 'Save' );

			var form = editform.find( '#editform' );
			form.append( saveHidden );
			form.parent().data( 'non-ajax-submit', true ); // To avoid edit form open warning
			form.submit();
		};

		var doneCallback = function ( data ) {
			var result;
			try {
				result = data.threadaction.thread.result;
			} catch ( err ) {
				result = 'error';
			}

			if ( result !== 'Success' ) {
				errorCallback();
				return;
			}

			var callback;

			if ( type === 'reply' ) {
				callback = replyCallback;
			}

			if ( type === 'talkpage_new_thread' ) {
				callback = newCallback;
			}

			if ( type === 'edit' ) {
				callback = editCallback;
			}

			editform.empty().hide();

			callback( data );

			// Load the new TOC
			liquidThreads.reloadTOC();
		};

		if ( type === 'reply' ) {
			liquidThreads.doReply( replyThread, text, summary,
					doneCallback, bump, signature, errorCallback );

			e.preventDefault();
		} else if ( type === 'talkpage_new_thread' ) {
			var page = editform.closest( '.lqt-new-thread' ).data( 'lqt-talkpage' );
			if ( !page ) {
				page = $( $( '[lqt_talkpage]' )[0] ).attr( 'lqt_talkpage' ); // A couple of elements have this attribute, it doesn't matter which
			}
			liquidThreads.doNewThread( page, subject, text, summary,
					doneCallback, bump, signature, errorCallback );

			e.preventDefault();
		} else if ( type === 'edit' ) {
			liquidThreads.doEditThread( replyThread, subject, text, summary,
					doneCallback, bump, signature, errorCallback );
			e.preventDefault();
		}
	},

	'reloadTOC' : function () {
		var toc = $( '.lqt_toc' );

		if ( !toc.length ) {
			toc = $( '<table/>' ).addClass( 'lqt_toc' );
			$( '.lqt-new-thread' ).after( toc );

			var contentsHeading = $( '<h2/>' );
			contentsHeading.text( mw.msg( 'lqt_contents_title' ) );
			toc.before( contentsHeading );
		}

		var loadTOCSpinner = $( '<div class="mw-ajax-loader"/>' );
		loadTOCSpinner.css( 'height', toc.height() );
		toc.empty().append( loadTOCSpinner );
		toc.load( window.location.href + ' .lqt_toc > *', function () {
			loadTOCSpinner.remove();
		} );
	},

	'doNewThread' : function ( talkpage, subject, text, summary, doneCallback, bump, signature, errorCallback ) {
		var newTopicParams = {
			action : 'threadaction',
			threadaction : 'newthread',
			talkpage : talkpage,
			subject : subject,
			text : text,
			token : mw.user.tokens.get( 'editToken' ),
			render : '1',
			reason : summary,
			bump : bump
		};

		if ( $( '#wpCaptchaWord' ) ) {
			newTopicParams.captchaword = $( '#wpCaptchaWord' ).val();
		}

		if ( $( '#wpCaptchaId' ) ) {
			newTopicParams.captchaid = $( '#wpCaptchaId' ).val();
		}

		if ( typeof signature !== 'undefined' ) {
			newTopicParams.signature = signature;
		}
		( new mw.Api() ).post( newTopicParams ).done( doneCallback ).fail( errorCallback );
	},

	'doReply' : function ( thread, text, summary, callback, bump, signature ) {
		var replyParams = {
			action : 'threadaction',
			threadaction : 'reply',
			thread : thread,
			text : text,
			token : mw.user.tokens.get( 'editToken' ),
			render : '1',
			reason : summary,
			bump : bump
		};

		if ( $( '#wpCaptchaWord' ) ) {
			replyParams.captchaword = $( '#wpCaptchaWord' ).val();
		}

		if ( $( '#wpCaptchaId' ) ) {
			replyParams.captchaid = $( '#wpCaptchaId' ).val();
		}

		if ( typeof signature !== 'undefined' ) {
			replyParams.signature = signature;
		}

		( new mw.Api() ).post( replyParams ).done( callback );
	},

	'doEditThread' : function ( thread, subject, text, summary,
					callback, bump, signature ) {
		var request = {
			action       : 'threadaction',
			threadaction : 'edit',
			thread       : thread,
			text         : text,
			render       : 1,
			reason       : summary,
			bump         : bump,
			subject      : subject,
			token        : mw.user.tokens.get( 'editToken' )
		};

		if ( $( '#wpCaptchaWord' ) ) {
			request.captchaword = $( '#wpCaptchaWord' ).val();
		}

		if ( $( '#wpCaptchaId' ) ) {
			request.captchaid = $( '#wpCaptchaId' ).val();
		}

		if ( typeof signature !== 'undefined' ) {
			request.signature = signature;
		}

		( new mw.Api() ).post( request ).done( callback );
	},

	onTextboxKeyUp: function () {
		// Check if a user has signed their post, and if so, tell them they don't have to.
		var text = $.trim( $( this ).val() );
		var prevWarning = $( '#lqt-sign-warning' );
		if ( text.match(/~~~~$/) ) {
			if ( prevWarning.length ) {
				return;
			}

			// Show the warning
			var elem = $( '<div>' ).attr( { 'id': 'lqt-sign-warning', 'class': 'error' } ).text( mw.msg( 'lqt-sign-not-necessary' ) ),
				$weTop = $( this ).closest( '.lqt-edit-form' ).find( '.wikiEditor-ui-top' );

			if ( $weTop.length ) {
				$weTop.before( elem );
			} else {
				$( this ).before( elem );
			}
		} else {
			prevWarning.remove();
		}
	},

	'activateDragDrop' : function ( e ) {
		// FIXME: Need a cancel drop action
		e.preventDefault();

		// Set up draggability.
		var $thread = $( this ).closest( '.lqt_thread' );
		var threadID = $thread.find( '.lqt-post-wrapper' ).data( 'thread-id' );
		var scrollOffset;
		// FIXME: what does all of this do? From here
		$( 'html,body' ).each( function () {
			if ( $( this ).attr( 'scrollTop' ) ) {
				scrollOffset = $( this ).attr( 'scrollTop' );
			}
		} );

		scrollOffset = scrollOffset - $thread.offset().top;

		var helperFunc;
		if ( $thread.hasClass( 'lqt-thread-topmost' ) ) {
			var $header = $( '#lqt-header-' + threadID );
			var $headline = $header.contents().filter( '.mw-headline' ).clone();
			var $helper = $( '<h2 />' ).append( $headline );
			helperFunc = function () { return $helper; };
		} else {
			helperFunc =
				function () {
					var $helper = $thread.clone();
					$helper.find( '.lqt-thread-replies' ).remove();
					return $helper;
				};
		}
		// to here.

		var draggableOptions = {
			'axis' : 'y',
			'opacity' : '0.70',
			'revert' : 'invalid',
			'helper' : helperFunc
		};
		$thread.draggable( draggableOptions );

		// Kill all existing drop zones
		$( '.lqt-drop-zone' ).remove();

		// Set up some dropping targets. Add one before the first thread, after every
		//  other thread, and as a subthread of every post.
		var createDropZone = function ( sortKey, parent ) {
			return $( '<div class="lqt-drop-zone" />' )
				.text( mw.msg( 'lqt-drag-drop-zone' ) )
				.data( 'sortkey', sortKey )
				.data( 'parent', parent );
		};

		// Add a drop zone at the very top unless the drag thread is the very first thread
		$( '.lqt-thread-topmost:first' )
			.not( $thread )
			.before( createDropZone( 'now', 'top' ) );

		// Now one after every thread except the drag thread
		// FIXME: Do not add one right before the current thread (bug 26237 comment 2)
		$( '.lqt-thread-topmost' ).not( $thread ).each( function () {
			var sortkey = $( this ).contents().filter( 'input[name=lqt-thread-sortkey]' ).val(),
				d = new Date(
					sortkey.substr(0,4),
					sortkey.substr(4,2) - 1, // month is from 0 to 11
					sortkey.substr(6,2),
					sortkey.substr(8,2),
					sortkey.substr(10,2),
					sortkey.substr(12,2)
				);

			// Use proper date manipulation to avoid invalid timestamps such as
			// 20120101000000 - 1 = 20120100999999 (instead of 20111231235959)
			// (in that case the API would return an "invalid-sortkey" error)
			d.setTime( d.getTime() - 1 );
			sortkey = [
				d.getFullYear(),
				( d.getMonth() < 9 ? '0' : '' ) + (d.getMonth() + 1),
				( d.getDate() < 10 ? '0' : '' ) + d.getDate(),
				( d.getHours() < 10 ? '0' : '' ) + d.getHours(),
				( d.getMinutes() < 10 ? '0' : '' ) + d.getMinutes(),
				( d.getSeconds() < 10? '0' : '' ) + d.getSeconds()
			].join( '' );
			$( this ).after( createDropZone( sortkey, 'top' ) );
		} );

		// Now one underneath every thread except the drag thread
		$( '.lqt_thread' ).not( $thread ).each( function () {
			var $curThread = $( this );
			// don't put any drop zones under child threads
			if ( $.contains( $thread[0], $curThread[0] ) ) {
				return;
			}
			// don't put it right next to the thread
			if ( $curThread.find( '.lqt-thread-replies:first > .lqt_thread:last' )[0] === $thread[0] ) {
				return;
			}
			var repliesElement = liquidThreads.getRepliesElement( $curThread );
			repliesElement.contents().filter( '.lqt-replies-finish' ).before( createDropZone( 'now', $curThread.data( 'thread-id' ) ) );
		} );

		var droppableOptions = {
			'activeClass' : 'lqt-drop-zone-active',
			'hoverClass' : 'lqt-drop-zone-hover',
			'drop' : liquidThreads.completeDragDrop,
			'tolerance' : 'intersect'
		};

		$( '.lqt-drop-zone' ).droppable( droppableOptions );

		scrollOffset = scrollOffset + $thread.offset().top;

		// Reset scroll position
		$( 'html,body' ).attr( 'scrollTop', scrollOffset );
	},

	'completeDragDrop' : function ( e, ui ) {
		var thread = $( ui.draggable );

		// Determine parameters
		var params = {
			'sortkey' : $( this ).data( 'sortkey' ),
			'parent' : $( this ).data( 'parent' )
		};

		// Figure out an insertion point
		if ( $( this ).prev().length ) {
			params.insertAfter = $( this ).prev();
		} else if ( $( this ).next().length ) {
			params.insertBefore = $( this ).next();
		} else {
			params.insertUnder = $( this ).parent();
		}

		// Kill the helper.
		ui.helper.remove();

		setTimeout( function () { thread.draggable( 'destroy' ); }, 1 );

		// Remove drop points and schedule removal of empty replies elements.
		var emptyChecks = [];
		$( '.lqt-drop-zone' ).each( function () {
			var repliesHolder = $( this ).closest( '.lqt-thread-replies' );

			$( this ).remove();

			if ( repliesHolder.length ) {
				liquidThreads.checkEmptyReplies( repliesHolder, 'hide' );
				emptyChecks = $.merge( emptyChecks, repliesHolder );
			}
		} );

		params.emptyChecks = emptyChecks;

		// Now, let's do our updates
		liquidThreads.confirmDragDrop( thread, params );
	},

	'confirmDragDrop' : function ( thread, params ) {
		var confirmDialog = $( '<div class="lqt-drag-confirm" />' );

		// Add an intro
		var intro = $( '<p/>' ).text( mw.msg( 'lqt-drag-confirm' ) );
		confirmDialog.append( intro );

		// Summarize changes to be made
		var actionSummary = $( '<ul/>' );

		var addAction = function ( msg ) {
			var li = $( '<li/>' );
			li.text( mw.msg( msg ) );
			actionSummary.append( li );
		};

		var topLevel = ( params.parent === 'top' );
		var wasTopLevel = thread.hasClass( 'lqt-thread-topmost' );

		if ( params.sortkey === 'now' && wasTopLevel && topLevel ) {
			addAction( 'lqt-drag-bump' );
		} else if ( topLevel && params.sortkey !== 'now' ) {
			addAction( 'lqt-drag-setsortkey' );
		}

		if ( !wasTopLevel && topLevel ) {
			addAction( 'lqt-drag-split' );
		} else if ( !topLevel ) {
			addAction( 'lqt-drag-reparent' );
		}

		confirmDialog.append( actionSummary );

		// Summary prompt
		var summaryWrapper = $( '<p/>' );
		var summaryPrompt = $( '<label for="reason" />' ).text( mw.msg( 'lqt-drag-reason' ) );
		var summaryField = $( '<input type="text" size="45"/>' );
		summaryField.addClass( 'lqt-drag-confirm-reason' )
			.attr( 'name', 'reason' )
			.attr( 'id', 'reason' )
			.keyup( function ( event ) {
				if ( event.keyCode === 13 ) {
					$( '#lqt-drag-save-button' ).click();
				}
			} );
		summaryWrapper.append( summaryPrompt );
		summaryWrapper.append( summaryField );
		confirmDialog.append( summaryWrapper );

		if ( typeof params.reason !== 'undefined' ) {
			summaryField.val( params.reason );
		}

		// New subject prompt, if appropriate
		if ( !wasTopLevel && topLevel ) {
			var subjectPrompt = $( '<p/>' ).text( mw.msg( 'lqt-drag-subject' ) );
			var subjectField = $( '<input type="text" size="45"/>' );
			subjectField.addClass( 'lqt-drag-confirm-subject' )
					.attr( 'name', 'subject' );
			subjectPrompt.append( subjectField );
			confirmDialog.append( subjectPrompt );
		}

		// Now dialogify it.
		$( 'body' ).append( confirmDialog );

		var spinner;
		var successCallback = function () {
			confirmDialog.dialog( 'close' );
			confirmDialog.remove();
			spinner.remove();
			liquidThreads.reloadTOC();
		};

		var buttons = [ {
			id: 'lqt-drag-save-button',
			text: mw.msg( 'lqt-drag-save' ),
			click: function () {
				// Load data
				params.reason = $( this ).find( 'input[name=reason]' ).val();

				if ( !wasTopLevel && topLevel ) {
					params.subject = $.trim( $( this ).find( 'input[name=subject]' ).val() );
				}

				// Add spinners
				spinner = $( '<div id="lqt-drag-spinner" class="mw-ajax-loader" />' );
				thread.before( spinner );

				if ( params.insertAfter !== undefined ) {
					params.insertAfter.after( spinner );
				}

				$( this ).dialog( 'close' );

				liquidThreads.submitDragDrop( thread, params,
					successCallback );
			}
		} ];
		confirmDialog.dialog( { 'title': mw.msg( 'lqt-drag-title' ),
			'buttons' : buttons, 'modal' : true, 'width': 550 } );
	},

	'submitDragDrop' : function ( thread, params, callback ) {
		var newSortkey = params.sortkey;
		var newParent = params.parent;
		var threadId = thread.find( '.lqt-post-wrapper' ).data( 'thread-id' );

		var topLevel = ( newParent === 'top' );
		var wasTopLevel = thread.hasClass( 'lqt-thread-topmost' );
		var apiRequest = {
			action: 'threadaction',
			thread: threadId,
			reason: params.reason,
			token : mw.user.tokens.get( 'editToken' )
		};

		var doEmptyChecks = function () {
			$.each( params.emptyChecks, function ( k, element ) {
				liquidThreads.checkEmptyReplies( $( element ) );
			} );
		};

		var doneCallback = function ( data ) {
			// TODO error handling
			var result = 'success';

			if ( typeof data === 'undefined' || !data || typeof data.threadaction === 'undefined' ) {
				result = 'failure';
			}

			if ( typeof data.error !== 'undefined' ) {
				result = data.error.code + ': ' + data.error.info;
			}

			if ( result !== 'success' ) {
				alert( 'Error: ' + result );
				doEmptyChecks();
				$( '#lqt-drag-spinner' ).remove();
				return;
			}

			var payload;
			if ( typeof data.threadaction.thread !== 'undefined' ) {
				payload = data.threadaction.thread;
			} else if ( typeof data.threadaction[0] !== 'undefined' ) {
				payload = data.threadaction[0];
			}

			var oldParent;
			if ( !wasTopLevel ) {
				oldParent = thread.closest( '.lqt-thread-topmost' );
			}

			// Do the actual physical movement
			var threadId = thread.find( '.lqt-post-wrapper' )
					.data( 'thread-id' );

			// Assorted ways of returning a thread to its proper place.
			if ( typeof params.insertAfter !== 'undefined' ) {
				thread.remove();
				params.insertAfter.after( thread );
			} else if ( typeof params.insertBefore !== 'undefined' ) {
				thread.remove();
				params.insertBefore.before( thread );
			} else if ( typeof params.insertUnder !== 'undefined' ) {
				thread.remove();
				params.insertUnder.prepend( thread );
			}

			thread.data( 'thread-id', threadId );
			thread.find( '.lqt-post-wrapper' ).data( 'thread-id', threadId );

			if ( typeof payload['new-sortkey'] !== 'undefined' ) {
				var newSortKey = payload['new-sortkey'];
				thread.find( '.lqt-thread-modified' ).val( newSortKey );
				thread.find( 'input[name=lqt-thread-sortkey]' ).val( newSortKey );
			} else {
				// Force an update on the top-level thread
				var reloadThread = thread;

				if ( !topLevel && typeof payload['new-ancestor-id'] !== 'undefined' ) {
					var ancestorId = payload['new-ancestor-id'];
					reloadThread = $( '#lqt_thread_id_' + ancestorId );
				}

				liquidThreads.doReloadThread( reloadThread );
			}

			// Kill the heading, if there isn't one.
			if ( !topLevel && wasTopLevel ) {
				thread.find( 'h2.lqt_header' ).remove();
			}

			if ( !wasTopLevel && typeof oldParent !== 'undefined' ) {
				liquidThreads.doReloadThread( oldParent );
			}

			// Call callback
			if ( typeof callback === 'function' ) {
				callback();
			}

			doEmptyChecks();
		};

		if ( !topLevel || !wasTopLevel ) {
			// Is it a split or a merge

			if ( topLevel ) {
				// It is a split, and needs a new subject
				if ( typeof params.subject !== 'string' || params.subject.length === 0 ) {

					$( '#lqt-drag-spinner' ).remove();
					alert( mw.msg( 'lqt-ajax-no-subject' ) );
					// here we should prompt the user again to enter a new subject
					return;
				}
				apiRequest.threadaction = 'split';
				apiRequest.subject = params.subject;
			} else {
				apiRequest.threadaction = 'merge';
				apiRequest.newparent = newParent;
			}

			if ( newSortkey !== 'none' ) {
				apiRequest.sortkey = newSortkey;
			}
			( new mw.Api() ).post( apiRequest ).done( doneCallback );
		} else if ( newSortkey !== 'none' ) {
			apiRequest.threadaction = 'setsortkey';
			apiRequest.sortkey = newSortkey;
			( new mw.Api() ).post( apiRequest ).done( doneCallback );
		}
	},

	'handleEditSignature' : function ( e ) {
		e.preventDefault();

		var container = $( this ).parent();

		container.find( '.lqt-signature-preview' ).hide();
		container.find( 'input[name=wpLqtSignature]' ).show();
		$( this ).hide();

		// Add a save button
		var saveButton = $( '<a href="#"/>' );
		saveButton.text( mw.msg( 'lqt-preview-signature' ) );
		saveButton.click( liquidThreads.handlePreviewSignature );

		container.find( 'input[name=wpLqtSignature]' ).after( saveButton );
	},

	'handlePreviewSignature' : function ( e ) {
		e.preventDefault();

		var container = $( this ).parent();

		var spinner = $( '<span class="mw-small-spinner"/>' );
		$( this ).replaceWith( spinner );

		var textbox = container.find( 'input[name=wpLqtSignature]' ),
			preview = container.find( '.lqt-signature-preview' );

		textbox.hide();

		( new mw.Api() ).post( {
			action : 'parse',
			text   : textbox.val(),
			pst    : '1',
			prop   : 'text'
		} ).done( function ( data ) {
			var html = $( $.trim( data.parse.text['*'] ) );

			if ( html.length === 2 ) { // Not 1, because of the NewPP report
				html = html.contents();
			}

			preview.empty().append( html );
			preview.show();
			spinner.remove();
			container.find( '.lqt-signature-edit-button' ).show();
		} );
	}
};

$( document ).ready( function () {
	// One-time setup for the full page

	// Update the new thread link
	var newThreadLink = $( '.lqt_start_discussion a' );

	$( 'li#ca-addsection a' ).attr( 'lqt_talkpage', $( '.lqt_start_discussion a' ).attr( 'lqt_talkpage' ) );

	newThreadLink = newThreadLink.add( $( 'li#ca-addsection a' ) );

	if ( newThreadLink ) {
		newThreadLink.click( liquidThreads.handleNewLink );
	}

	// Find all threads, and do the appropriate setup for each of them

	var threadContainers = $( 'div.lqt-post-wrapper' );

	threadContainers.each( function () {
		liquidThreads.setupThread( this );
	} );

	// Live bind for unwatch/watch stuff.
	$( '.lqt-command-watch' ).live( 'click', liquidThreads.asyncWatch );
	$( '.lqt-command-unwatch' ).live( 'click', liquidThreads.asyncWatch );

	// Live bind for link window
	$( '.lqt-command-link' ).live( 'click', liquidThreads.showThreadLinkWindow );

	// Live bind for summary links
	$( '.lqt-summary-link' ).live( 'click', liquidThreads.showSummaryLinkWindow );

	// For "show replies"
	$( 'a.lqt-show-replies' ).live( 'click', liquidThreads.showReplies );

	// "Show more posts" link
	$( 'a.lqt-show-more-posts' ).live( 'click', liquidThreads.showMore );

	// Edit link handler
	$( '.lqt-command-edit > a' ).live( 'click', liquidThreads.handleEditLink );

	// Save handlers
	$( '#wpSave' ).live( 'click', liquidThreads.handleAJAXSave );
	$( '#wpTextbox1' ).live( 'keyup', liquidThreads.onTextboxKeyUp );

	// Hide menus when a click happens outside them
	$( document ).click( liquidThreads.handleDocumentClick );

	// Set up periodic update checking
	setInterval( liquidThreads.checkForUpdates, 60000 );

	$( window ).bind( 'beforeunload', function() {
		var confirmExitPage = false;
		$( '.lqt-edit-form:not(.lqt-summarize-form)' ).each( function( index, element ) {
			var textArea = $( element ).children( 'form' ).find( 'textarea' );
			if ( element.style.display !== 'none' && !$( element ).data( 'non-ajax-submit' ) && textArea.val() ) {
				confirmExitPage = true;
			}
		} );
		if ( confirmExitPage ) {
			return mw.msg( 'lqt-pagechange-editformopen' );
		}
	} );
} );

}( mediaWiki, jQuery ) );
