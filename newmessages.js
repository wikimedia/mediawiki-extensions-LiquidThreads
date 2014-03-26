/**
 * JavaScript for LiquidThread's Special:NewMessages.
 */
/*global liquidThreads */
( function ( mw, $ ) {

$.extend( liquidThreads, {
	markReadDone: {
		'one' : function ( reply, button, operand ) {
			var row = $( button ).closest( 'tr' );
			var right_col = row.find( 'td.lqt-newmessages-right' );
			$( button ).closest( 'td' ).empty();

			var msg = mw.msg( 'lqt-marked-as-read-placeholder' );
			var undoMsg = mw.msg( 'lqt-email-undo' );
			// We have to split the message to the part before the
			// $1 and the part after the $1
			var placeholderIndex = msg.indexOf( '$1' );
			var elem = $( '<span class="lqt-read-placeholder"/>' );

			if ( placeholderIndex >= 0 ) {
				var beforeMsg = msg.substr( 0, placeholderIndex );
				var afterMsg = msg.substr( placeholderIndex + 2 );

				var beforeText = $( document.createTextNode( beforeMsg ) );
				elem.append( beforeText );

				// Produce the link
				var titleSel = '.lqt-thread-topmost > .lqt-thread-title-metadata';
				var subject = right_col.find( '.lqt_header' ).text();
				var title = right_col.find( titleSel ).val();
				title = title.replace( ' ', '_' );
				title = encodeURIComponent( title );
				title = title.replace( '%2F', '/' );
				var url = mw.config.get( 'wgArticlePath' ).replace( '$1', title );
				var link = $( '<a/>' ).attr( 'href', url ).text( subject );
				elem.append( link );

				var afterText = $( document.createTextNode( afterMsg + ' ' ) );
				elem.append( afterText );
			} else {
				elem.text( msg );
			}

			// Add the "undo" link.
			var undoURL = mw.config.get( 'wgArticlePath' ).replace( '$1', mw.config.get( 'wgPageName' ) );
			var query = 'lqt_method=mark_as_unread&lqt_operand='+operand;
			if ( undoURL.indexOf( '?' ) === -1 ) {
				query = '?'+query;
			} else {
				query = '&'+query;
			}
			undoURL += query;

			var undoLink = $( '<a/>' ).attr( 'href', undoURL ).text( undoMsg );
			elem.append( undoLink );

			right_col.empty().append( elem );
		},

		all: function () {
			var tables = $( 'table.lqt-new-messages' );
			tables.fadeOut( 'slow', function () {
				tables.remove();
			} );
		}
	},

	'doMarkRead' : function ( e ) {
		e.preventDefault();

		var button = $( this );

		// Find the operand.
		var form = button.closest( 'form.lqt_newmessages_read_button' );

		if ( !form.length ) {
			form = button.closest( 'form.lqt_newmessages_read_all_button' );
			liquidThreads.doMarkAllRead( form );
		} else {
			liquidThreads.doMarkOneRead( form );
		}
	},

	'doMarkOneRead' : function ( form ) {
		var operand = form.find( 'input[name=lqt_operand]' ).val();
		var threads = operand.replace( /\,/g, '|' );

		var spinner = $( '<div class="mw-ajax-loader"/>' );
		$( form ).prepend( spinner );

		( new mw.Api() ).post( {
			action: 'threadaction',
			threadaction: 'markread',
			thread: threads,
			token: mw.user.tokens.get( 'editToken' )
		} ).done( function ( e ) {
			liquidThreads.markReadDone.one( e, form.find( 'input[type=submit]' ), operand );
			$( 'li#pt-newmessages' ).html( $( '<a>', $( e.threadactions ).last().prop( 'unreadlink' ) ) ); // Unreadlink will be on the last threadaction
			spinner.remove();
		} );
	},

	'doMarkAllRead' : function ( form ) {
		var spinner = $( '<div class="mw-ajax-loader"/>' );
		$( form ).prepend( spinner );

		( new mw.Api() ).post( {
			action: 'threadaction',
			threadaction: 'markread',
			thread: 'all',
			token: mw.user.tokens.get( 'editToken' )
		} ).done( function ( res ) {
			liquidThreads.markReadDone.all( res );
			$( 'li#pt-newmessages' ).html( $( '<a>', res.threadactions[0].unreadlink ) );
			spinner.remove();
		} );
	}

} );

// Setup
$( function () {
	$( '.lqt-read-button' ).click( liquidThreads.doMarkRead );
} );
}( mediaWiki, jQuery ) );
