<?php
/**
 * Events > Etix API Submenu
 */

?>
<div id="etix-settings" class="tribe_settings wrap">
	<div class="tribe-settings-form form">
	<form method="post">

		<div class="header">
			<h2><?php _e( 'Etix API Settings', 'tribe-events-calendar' ); ?></h2>
		</div>


		<div class="content-wrapper">

		<?php do_action('etix-api-settings-above'); ?>

		<?php do_action('etix-api-settings-content'); ?>

		<?php do_action('etix-api-settings-below'); ?>

		</div>

	</form>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#etix-settings .tribe-settings-form form').delegate('input.button-primary.ajax','click',function(){

		var button = $(this),
			input = $(this).closest('form').serialize(),
			myparent = $(this).parent(),
            loading = $('.ajax-feedback',myparent);

        if( loading.css('visibility') !== 'visible' ) {
			button.prop('disabled',true);
            $('div.updated',myparent).remove();
            loading.css('visibility','visible');
            $.ajax({
              type: 'POST',
              url: ajaxurl,
			  data: input + '&action=etix-api-ajax&security=<?php echo wp_create_nonce('etix-api-ajax-nonce'); ?>',
              dataType: 'json',
              timeout : 120000,
              success: function(data, textStatus, jqXHR) {
                    if( data ) {

                        if( data.reload )
                            window.location.reload();

                        if( data.message )
                            $('<div class="updated"><p><strong>' + data.message + '</strong></p></div>').appendTo( myparent ).delay(1000).fadeOut();

                        if( data.error )
                            $('<div class="updated"><p><strong>Error: ' + data.error + '</strong></p></div>').appendTo( myparent ).delay(1000).fadeOut();

						if( data.payload )
							$( data.target ).html( data.payload );

                    }
                },
              complete: function(jqXHR, textStatus) {
                  loading.css('visibility','hidden');
				  button.prop('disabled',false);
                  if( textStatus !== 'success' )
                      $('<div class="updated"><p><strong>Unable to completed that action, please try again.</strong></p></div>').appendTo( myparent );
              }
            });
        }
        return false;
    });
});
</script>
