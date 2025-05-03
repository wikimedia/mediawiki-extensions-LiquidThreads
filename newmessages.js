/**
 * JavaScript for LiquidThread's Special:NewMessages.
 */

/* global liquidThreads */

( function () {

	Object.assign( liquidThreads, {
		markReadDone: {
			one: function ( reply, button, operand ) {
				let msg, undoMsg, placeholderIndex, $elem,
					beforeMsg, afterMsg, beforeText, afterText,
					titleSel, subject, title, url, $link,
					undoURL, query, $undoLink,
					$row = $( button ).closest( 'tr' ),
					$rightCol = $row.find( 'td.lqt-newmessages-right' );

				$( button ).closest( 'td' ).empty();

				msg = mw.msg( 'lqt-marked-as-read-placeholder' );
				undoMsg = mw.msg( 'lqt-email-undo' );
				// We have to split the message to the part before the
				// $1 and the part after the $1
				placeholderIndex = msg.indexOf( '$1' );
				$elem = $( '<span>' ).addClass( 'lqt-read-placeholder' );

				if ( placeholderIndex >= 0 ) {
					beforeMsg = msg.slice( 0, Math.max( 0, placeholderIndex ) );
					afterMsg = msg.slice( placeholderIndex + 2 );

					beforeText = document.createTextNode( beforeMsg );
					$elem.append( beforeText );

					// Produce the link
					titleSel = '.lqt-thread-topmost > .lqt-thread-title-metadata';
					subject = $rightCol.find( '.lqt_header' ).text();
					title = $rightCol.find( titleSel ).val();
					title = title.replace( ' ', '_' );
					title = encodeURIComponent( title );
					title = title.replace( '%2F', '/' );
					url = mw.config.get( 'wgArticlePath' ).replace( '$1', title );
					$link = $( '<a>' ).attr( 'href', url ).text( subject );
					$elem.append( $link );

					afterText = document.createTextNode( afterMsg + ' ' );
					$elem.append( afterText );
				} else {
					$elem.text( msg );
				}

				// Add the "undo" link.
				undoURL = mw.config.get( 'wgArticlePath' ).replace( '$1', mw.config.get( 'wgPageName' ) );
				query = 'lqt_method=mark_as_unread&lqt_operand=' + operand;
				if ( !undoURL.includes( '?' ) ) {
					query = '?' + query;
				} else {
					query = '&' + query;
				}
				undoURL += query;

				$undoLink = $( '<a>' ).attr( 'href', undoURL ).text( undoMsg );
				$elem.append( $undoLink );

				$rightCol.empty().append( $elem );
			},

			all: function () {
				const $tables = $( 'table.lqt-new-messages' );
				// FIXME: Use CSS transition
				// eslint-disable-next-line no-jquery/no-fade
				$tables.fadeOut( 'slow', () => {
					$tables.remove();
				} );
			}
		},

		doMarkRead: function ( e ) {
			let $form,
				$button = $( this );

			e.preventDefault();

			// Find the operand.
			$form = $button.closest( 'form.lqt_newmessages_read_button' );

			if ( !$form.length ) {
				$form = $button.closest( 'form.lqt_newmessages_read_all_button' );
				liquidThreads.doMarkAllRead( $form );
			} else {
				liquidThreads.doMarkOneRead( $form );
			}
		},

		doMarkOneRead: function ( $form ) {
			const operand = $form.find( 'input[name=lqt_operand]' ).val(),
				threads = operand.replace( /,/g, '|' ),
				$spinner = $( '<div>' ).addClass( 'mw-ajax-loader' );

			$form.prepend( $spinner );

			( new mw.Api() ).post( {
				action: 'threadaction',
				threadaction: 'markread',
				thread: threads,
				token: mw.user.tokens.get( 'csrfToken' )
			} ).done( ( e ) => {
				liquidThreads.markReadDone.one( e, $form.find( 'input[type=submit]' ), operand );
				$( 'li#pt-newmessages' ).html( $( '<a>', $( e.threadactions ).last().prop( 'unreadlink' ) ) ); // Unreadlink will be on the last threadaction
				$spinner.remove();
			} );
		},

		doMarkAllRead: function ( $form ) {
			const $spinner = $( '<div>' ).addClass( 'mw-ajax-loader' );

			$form.prepend( $spinner );

			( new mw.Api() ).post( {
				action: 'threadaction',
				threadaction: 'markread',
				thread: 'all',
				token: mw.user.tokens.get( 'csrfToken' )
			} ).done( ( res ) => {
				liquidThreads.markReadDone.all( res );
				$( 'li#pt-newmessages' ).html( $( '<a>', res.threadactions[ 0 ].unreadlink ) );
				$spinner.remove();
			} );
		}

	} );

	// Setup
	$( () => {
		$( '.lqt-read-button' ).on( 'click', liquidThreads.doMarkRead );
	} );

}() );
