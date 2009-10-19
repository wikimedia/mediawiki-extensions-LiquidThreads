
liquidThreads.doMarkRead =
	function(e) {
		e.preventDefault();
		
		var button = $j(this);
		
		// Find the operand.
		var form = button.closest('form.lqt_newmessages_read_button');
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
					function(reply) {
						var row = button.closest('tr');
						row.fadeOut( 'slow',
							function() { row.remove(); } );
						spinner.remove();
					}, 'json' );
			}, 'json' );
	}

// Setup
$j( function() {
	var buttons = $j('.lqt-read-button');
	
	buttons.click( liquidThreads.doMarkRead );
} );
