<?php
/**
 * The Admin View for Etix API settings
 *
 * @author dliszka
 * @since 1.1
 */

$opts = RockhouseEvents::getOptions();
?>
			<div id="modern-tribe-info">
				<img style="width:auto;height:50px;display:block;margin: 0 auto" src="http://www.etix.com/ticket/online3/images/etix-logo.svg" />
				<br/>
				Your Rockhouse Partners site can be synchonized automatically with your Etix performances via the Etix Public Activities API.  All performances that are displayed to the public on Etix.com will be kept in sync with your site. Private or incomplete performances will not be included.  Performances with a "Display on Etix.com" date in the future will not be sync until that date has passed.  Further instructions can be found <a href="http://docs.rockhousepartners.net/etix-api/" target="_blank">on our documentation site</a>.
			</div>

			<div class="tribe-settings-form-wrap">


<?php
	if( $opts['etixApiKey'] and $opts['etixApiKeyStatus'] == 'Valid' ) {
?>
				<h3>Manual Update</h3>

				<div id="message">

					<pre id="etix-tools-output">
					</pre>

					<p>
						Your WordPress site will be pull updates from the Etix API approximately every 10 minutes.  If you need to initial a manual sync you can do so here.
					</p>

					<p class="submit">
						<input type="hidden" name="etix-ajax-action" id="etix-ajax-action" value="tools" />
						<input type="submit" name="Sync" class="button-primary ajax" value=" Update Events from Etix " />
						<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-feedback" title="" alt="" />
					</p>

				</div>
<?php
	}
?>
				<div class="clear"></div>
				<h3>Credentials</h3>

				<fieldset id="tribe-field-etixApiKey" class="tribe-field tribe-field-checkbox_bool tribe-size-large etix-api-key">
					<legend class="tribe-field-label">
						Etix API Key
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="etixApiKey" class="large-text" id="etixApiKey" value="<?php echo $opts['etixApiKey']; ?>">
<?php
	if( $opts['etixApiKey'] ) {
		echo '<pre>Key Status: <strong>',$opts['etixApiKeyStatus'],"</strong>\n";
		echo 'The current key was set by <strong>',$opts['etixApiKeyUser'],'</strong> on ', $opts['etixApiKeySaved'] ,'.</pre> ';
	}
?>
					</div>
				</fieldset>
				<div class="clear"></div>
<?php
	if( $opts['etixApiKey'] ) {

		if( $opts['etixApiKeyStatus'] == 'Valid' ) {
			// Only show these fields if we have an API key
?>
				<div id="message">
					<p>Etix API Sync is authorized and active.  Events will be syncd with the Public API hourly, remove your key to disable the process.</p>
				</div>
				<div class="clear"></div>

				<h3>Venues & Organizations</h3>

				<div id="message">
					<p>A single venue is supported at this time.  Multiple venue and Organization support will be available in 2016.  Contact <a href="mailto:feedback@etix.com">feedback@etix.com</a> if you would like more information.</p>
				</div>

				<fieldset id="tribe-field-etixApiOrgVenueIds" class="tribe-field tribe-field-checkbox_bool tribe-size-large etix-api-orgvenue">
					<legend class="tribe-field-label">
						Venue ID
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="etixApiOrgVenueIds" id="etixApiOrgVenueIds" value="<?php echo $opts['etixApiOrgVenueIds']; ?>">
<?php
			if( !empty($opts['etixApiOrgVenueIds']) and !empty( $opts['etixApiOrgVenueInfo'] ) ) {
				echo '<pre>Latest sync results:</pre> <pre>',$opts['etixApiOrgVenueInfo'],'</pre>';
			}
?>

					</div>
				</fieldset>

<?php
			if( empty($opts['etixApiOrgVenueIds']) ) {
				echo '<div id="message" class="error"><p>You must specify a Venue ID to use with the API.</p></div>';
			}
		} else {
			$log_link = admin_url( 'edit.php?post_type=' . Tribe__Events__Main::POSTTYPE . '&page=' . EtixApiSettings::MENU_SLUG . '&tab=logs' );
			echo '<div id="message" class="error"><p>Invalid Etix API Key, Connections have been suspended.  You can <a href="',$log_link,'">check the logs</a> for more info.</p></div>';
		}
	}
?>
				<div class="clear"></div>


				<fieldset id="tribe-field-etixReset" class="tribe-field tribe-field-checkbox_bool tribe-size-large etix-api-reset">
					<legend class="tribe-field-label">
						Reset Etix API Events
					</legend>
					<div class="tribe-field-wrap">
				<?php if( isset($_POST['rhp-show-undo']) ): global $rm_posts; ?>
					<div id="modern-tribe-info">
						<h4>Clear All Etix API Events Warning</h4>

						Do you <em>really</em> want to wipe all events that have been synced with the Etix API?  All Series categories that have been added will also be deleted.
						<br/>
						<br/>
						<strong>This will forcibly delete <?php echo $rm_posts->post_count; ?> posts, never to be recovered.</strong>
						<br/>
						<br/>
						If this is a production site with live events, you should probably not do this.
					</div>

					<input id="rhp-undo-sync" name="rhp-undo-sync" class="button-primary" type="submit" value=" Delete <?php echo $rm_posts->post_count; ?> Events " />
					<input class="button-secondary" type="submit" value=" Cancel " />
				<?php else: ?>
					<input id="rhp-show-undo" name="rhp-show-undo" class="button" type="submit" value=" Clear All Events from the API " />
				<?php endif; ?>
					</div>
				</fieldset>

				<div class="clear"></div>

				<br/>
				<br/>
				<input type="hidden" name="etix-api-nonce" id="etix-api-nonce" value="<?php echo wp_create_nonce('etix-api-settings'); ?>" />
				<input id="etixSaveSettings" class="button-primary" type="submit" name="etixSaveSettings" value=" Save Changes" />

			</div>

