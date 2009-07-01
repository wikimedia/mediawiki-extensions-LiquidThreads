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
		
		liquidThreads.injectEditForm( query, container, footer_cmds.nextSibling );
	
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
		
		e.preventDefault();
		return false;
	},
	
	'injectEditForm' : function(query, container, before) {
		var x = sajax_init_object();
		var url = wgServer+wgScript+'?title='+encodeURIComponent(wgPageName)+
					query+'&lqt_inline=1'
		x.open( 'get', url, true );
		
		x.onreadystatechange =
			function() {
				if (x.readyState != 4)
					return;
				
				var result = x.responseText;
				var replyDiv = document.createElement( 'div' );
				replyDiv.className = 'lqt_ajax_reply_form'
				replyDiv.innerHTML = result;
				
				if (before) {
					container.insertBefore( replyDiv, before );
				} else {
					container.appendChild( replyDiv );
				}
			};
		
		x.send( null );
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
	
	addHandler( newThreadLink, 'click', liquidThreads.handleNewLink );
} );

