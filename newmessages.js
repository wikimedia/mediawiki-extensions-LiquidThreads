
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
		
		var doneCallback =
			function(reply) {
				if ( type == 'one' ) {
					var row = button.closest('tr');
					row.fadeOut( 'slow',
						function() { row.remove(); } );
				} else {
					var tables = $j('table.lqt-new-messages');
					tables.fadeOut( 'slow',
						function() { tables.remove(); } );
				}
				
				spinner.remove();
			}
		
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
					doneCallback, 'json' );
			}, 'json' );
	}

// Setup
$j( function() {
	var buttons = $j('.lqt-read-button');
	
	buttons.click( liquidThreads.doMarkRead );
} );
