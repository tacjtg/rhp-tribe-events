<?php
/**
 * The Admin View for Etix Performance settings
 *
 * @author dliszka
 * @since 1.2
 */

$opts = RockhouseEvents::getOptions();
?>
			<div class="tribe-settings-form-wrap">

<?php
	// Only show these fields if we have an API key
	if( $opts['etixApiKey'] and $opts['etixApiKeyStatus'] == 'Valid' ) {
?>
<?php
	}
?>
				<h3>Performance Management</h3>
				<p class="message">
					Checking this option will add a special category for Event Series.  Performances that are part of an Event Series will be grouped together for display and have a special landing page.  All dates and times will be displayed along with individual links to purchase tickets.
					<br/>
					<br/>
					Performances retrieved via the Etix API that are part of an Event Series will automatically have categories created and applied when this option is in effect.
				</p>
				<fieldset id="tribe-field-etixGroupSeries" class="tribe-field tribe-field-checkbox_bool ">
					<legend class="tribe-field-label">
						Categorize Event Series
					</legend>
					<div class="tribe-field-wrap">
					<input type="checkbox" id="etixGroupSeries" name="etixGroupSeries" <?php echo $opts['etixGroupSeries'] ? 'value="1" checked="checked"' : ''; ?>/>
					</div>
				</fieldset>
				<div class="clear"></div>

				<p class="message">
					New Performances retrieved via the API will be automatically published to your site by default.  Uncheck this option to store these Events as a Draft to be reviewed and published manually.
				</p>
				<fieldset id="tribe-field-etixAutoPublish" class="tribe-field tribe-field-checkbox_bool ">
					<legend class="tribe-field-label">
						Auto Publish New Events
					</legend>
					<div class="tribe-field-wrap">
					<input type="checkbox" id="etixAutoPublish" name="etixAutoPublish" <?php echo $opts['etixAutoPublish'] ? 'value="1" checked="checked"' : ''; ?>/>
					</div>
				</fieldset>
				<div class="clear"></div>

				<p class="message">
					When a Performance has either a hidden or zero dollar price code it will normally show a link to Etix to view the Performance Details.  Some venues may want these Performances to show up as a Free Event instead.  Checking this option will set the Cost to 0 and check the Free Show box to hide the CTA to Etix (be aware that the Free Show flag overrides all other CTA statuses like Coming Soon, On Sale, or Off Sale).
				</p>
				<fieldset id="tribe-field-etixSetZeroAsFree" class="tribe-field tribe-field-checkbox_bool ">
					<legend class="tribe-field-label">
						Mark zero dollar prices as Free
					</legend>
					<div class="tribe-field-wrap">
					<input type="checkbox" id="etixSetZeroAsFree" name="etixSetZeroAsFree" <?php echo $opts['etixSetZeroAsFree'] ? 'value="1" checked="checked"' : ''; ?>/>
					</div>
				</fieldset>
				<div class="clear"></div>


				<h3>Fields Updates</h3>
				<p class="message">
					When a new Performance or Series is created from the API it will initially copy all available fields.  In many cases you may want to prevent certain items from being subsequently updated if you have modified them in WordPress.  To prevent your local changes from being overwritten uncheck the fields below.
					<br/>
					<br/>
					<strong>The <em>Performance Date</em>, <em>Etix Purchase URL</em>, and <em>Sold Out</em> fields will always be kept in sync with the Etix values.</strong>  The recommended setting is to always sync On Sale /  Off Sale / End Date and allow all other fields to be changed locally.
				</p>
				<fieldset id="tribe-field-etixApiSyncFields" class="tribe-field tribe-field-checkbox_list tribe-size-medium">
					<legend class="tribe-field-label">
						Always Update from API
					</legend>
					<div class="tribe-field-wrap">
						<label title="Title">
							<input type="checkbox" name="etixApiSyncFields[]" value="title" <?php if( !empty($opts['etixApiSyncFields']['title']) ){ ?>checked="checked"<?php } ?>>Title
						</label>
						<label title="Image">
							<input type="checkbox" name="etixApiSyncFields[]" value="image_url" <?php if( !empty($opts['etixApiSyncFields']['image']) ){ ?>checked="checked"<?php } ?>>Image
						</label>
						<label title="On Sale Date">
							<input type="checkbox" name="etixApiSyncFields[]" value="on_sale_date_utc" <?php if( !empty($opts['etixApiSyncFields']['on_sale_date_utc']) ){ ?>checked="checked"<?php } ?>>On Sale Date
						</label>
						<label title="Off Sale Date">
							<input type="checkbox" name="etixApiSyncFields[]" value="off_sale_date_utc" <?php if( !empty($opts['etixApiSyncFields']['off_sale_date_utc']) ){ ?>checked="checked"<?php } ?>>Off Sale Date
						</label>
						<label title="End Date">
							<input type="checkbox" name="etixApiSyncFields[]" value="end_date_utc" <?php if( !empty($opts['etixApiSyncFields']['end_date_utc']) ){ ?>checked="checked"<?php } ?>>End Date
						</label>
						<label title="Cost">
							<input type="checkbox" name="etixApiSyncFields[]" value="cost" <?php if( !empty($opts['etixApiSyncFields']['cost']) ){ ?>checked="checked"<?php } ?>>Cost
						</label>
						<label title="Facebook Event URL">
							<input type="checkbox" name="etixApiSyncFields[]" value="fb_url" <?php if( !empty($opts['etixApiSyncFields']['fb_url']) ){ ?>checked="checked"<?php } ?>>Facebook Event URL
						</label>
					</div>
				</fieldset>
				<div class="clear"></div>

				<h3>Exclude Performances</h3>
				<p class="message">
					You can specify performances that should not be created or updated from the API here.
				</p>

				<fieldset id="tribe-field-etixApiExclusions" class="tribe-field tribe-field-textarea tribe-size-large ">
					<legend class="tribe-field-label">
						Exclude Performances <br/>(One per line)
					</legend>
					<div class="tribe-field-wrap">
						<textarea name="etixApiExclusions"><?php echo trim( implode("\n", (array) $opts['etixApiExclusions']) ); ?></textarea>
					</div>
				</fieldset>

				<p class="message">
					There are 2 ways to indicate which performances should not be included:
					<br/>
					<br/>
					<b>Matching Title</b> - If any part of this string matches the Title, do not sync that activity (case insensitive).
					<br/>
					<br/>
					<code>parking for</code>
					<br/>
					<code>SKIP THE LINE</code>
					<br/>
					<br/>
					<b>Activity ID</b> - If the Performance or Event ID exactly matches this number, do not sync that activity.
					<br/>
					<br/>
					<code>id:423647688</code>
				</p>

				<div class="clear"></div>


				<br/>
				<br/>
				<input type="hidden" name="etix-api-nonce" id="etix-api-nonce" value="<?php echo wp_create_nonce('etix-api-settings'); ?>" />
				<input id="etixSaveSettings" class="button-primary" type="submit" name="etixSaveSettings" value=" Save Changes " />

			</div>

