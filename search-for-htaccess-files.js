jQuery(document).ready( function( $ ){
	viewHtaccessDialog = $( "#view-htaccess" ).dialog({
      autoOpen: false,
      height: 400,
      width: '80%',
      modal: true,
      buttons: [
	  {
        text: "Close",
		click: function() {
		  $( this ).dialog( "close" );
        }
      }
	  ],
    });

	$('.view-htaccess').on('click', function(e) {
		var path = $(this).data( 'path' );

		e.preventDefault();

		$.ajax({
		url : view_htaccess.ajax_url,
		type : 'post',
		data : {
			action : 'view_htaccess',
			nonce : view_htaccess.view_nonce,
			path : path
		},
		success: function( response ) {
			if ( 'OK' == response.status ) {
				fileContent = response.fileContent.replace( /\</g, '&lt;' );  // Change < to &lt; entity so it can be displayed.
				$('#view-htaccess').html( '<pre>' + fileContent + '</pre>' );  // Change the dialog's contents to the result.
				viewHtaccessDialog.dialog( "open" );
			}
			else if ( 'Error' == response.status ) {
				alert( response.statusText );
			}
		},
		error: function( response ) {
			alert( 'Error retrieving the file contents.' );
		}
		});
	});
});