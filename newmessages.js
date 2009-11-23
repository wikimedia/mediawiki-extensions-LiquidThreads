liquidThreads.markReadDone =
{
	'one' : function(reply,button,operand) {
		var row = $j(button).closest('tr');
		var right_col = row.find('td.lqt-newmessages-right');
		$j(button).closest('td').empty();
		
		var msg = wgLqtMessages['lqt-marked-as-read-placeholder'];
		var undoMsg = wgLqtMessages['lqt-email-undo'];
		// We have to split the message to the part before the
		// $1 and the part after the $1
		var placeholderIndex = msg.indexOf( '$1' );
		var elem = $j('<span class="lqt-read-placeholder"/>');
		
		if (placeholderIndex >= 0) {
			var beforeMsg = msg.substr(0,placeholderIndex);
			var afterMsg = msg.substr(placeholderIndex+2);
			
			var beforeText = $j(document.createTextNode(beforeMsg));
			elem.append(beforeText);
			
			// Produce the link
			var titleSel = '.lqt-thread-topmost > .lqt-thread-title-metadata';
			var subject = right_col.find('h3').text();
			var title = right_col.find(titleSel).val();
			var url = wgArticlePath.replace( '$1', title );
			var link = $j('<a/>').attr('href', url).text(subject);
			elem.append(link);
			
			var afterText = $j(document.createTextNode(afterMsg+' '));
			elem.append(afterText);
		} else {
			elem.text(msg);
		}
		
		// Add the "undo" link.
		var undoURL = wgArticlePath.replace( '$1', wgPageName );
		var query = 'lqt_method=mark_as_unread&lqt_operand='+operand;
		if ( undoURL.indexOf('?') == -1 ) {
			query = '?'+query;
		} else {
			query = '&'+query;
		}
		undoURL += query;
		
		var undoLink = $j('<a/>').attr('href', undoURL).text(undoMsg);
		elem.append( undoLink );
		
		right_col.empty().append(elem);
	},
	
	'all' : function(reply) {
		var tables = $j('table.lqt-new-messages');
		tables.fadeOut( 'slow',
			function() { tables.remove(); } );
	}
};

liquidThreads.doMarkRead =
	function(e) {
		e.preventDefault();
		
		var button = $j(this);
		var type = 'one';
		
		// Find the operand.
		var form = button.closest('form.lqt_newmessages_read_button');
		
		if (!form.length) {
			form = button.closest( 'form.lqt_newmessages_read_all_button' );
			type = 'all';
		}
		
		var operand = form.find('input[name=lqt_operand]').val();
		var threads = operand.replace( /\,/g, '|' );
		
		var getTokenParams =
		{
			'action' : 'query',
			'prop' : 'info',
			'intoken' : 'edit',
			'titles' : 'Some Title',
			'format' : 'json'
		};
		
		var spinner = $j('<div class="mw-ajax-loader"/>');
		$j(button).before( spinner );
		
		$j.get( wgScriptPath+'/api'+wgScriptExtension, getTokenParams,
			function( data ) {
				var token = data.query.pages[-1].edittoken;

				var markReadParameters =
				{
					'action' : 'threadaction',
					'threadaction' : 'markread',
					'format' : 'json',
					'thread' : threads,
					'token' : token
				}
				
				$j.post( wgScriptPath+'/api'+wgScriptExtension,
					markReadParameters,
					function(e) {
						liquidThreads.markReadDone[type](e,button,operand);
						spinner.remove();
					}, 'json' );
			}, 'json' );
	};

// Setup
$j( function() {
	var buttons = $j('.lqt-read-button');
	
	buttons.click( liquidThreads.doMarkRead );
} );
