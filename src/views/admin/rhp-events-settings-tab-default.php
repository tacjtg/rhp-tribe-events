<?php
/**
 * The Admin View for default settings
 *
 * @author dliszka
 * @since 1.1
 */
$opts = RockhouseEvents::getOptions();
?>
			<div class="tribe-settings-form-wrap">

				<h3>Multiple Venue Support</h3>

				<p class="description">
					When this is selected the <strong>Events > Venues</strong> is visible to all Box Office users to manage and all other users can select from a list of Venues when editing Events.
				</p>

				<fieldset id="tribe-field-multipleVenues" class="tribe-field tribe-field-checkbox_bool tribe-size-medium multiple-venues">
					<legend class="tribe-field-label">
						Enable Multiple Venues
					</legend>
					<div class="tribe-field-wrap">
					<input type="checkbox" id="multipleVenues" name="multipleVenues" <?php echo $opts['multipleVenues'] ? 'value="1" checked="checked"' : ''; ?>/>
					</div>
				</fieldset>
				<div class="clear"></div>

				<h3>Etix.com Link Enforcement</h3>

				<p class="description">
					You can set a default Cobrand and Partner ID to be appended to Etix.com links set as the CTA URL here.
				</p>

				<fieldset id="tribe-field-urlCobrandFilter" class="tribe-field tribe-field-checkbox_bool tribe-size-medium cobrand-filter">
					<legend class="tribe-field-label">
						Default Cobrand
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="urlCobrandFilter" id="urlCobrandFilter" value="<?php echo $opts['urlCobrandFilter']; ?>">
					</div>
				</fieldset>
				<div class="clear"></div>
				<fieldset id="tribe-field-urlPartnerFilter" class="tribe-field tribe-field-checkbox_bool tribe-size-medium partner-filter">
					<legend class="tribe-field-label">
						Default Partner ID
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="urlPartnerFilter" id="urlPartnerFilter" value="<?php echo $opts['urlPartnerFilter']; ?>">
					</div>
				</fieldset>
				<div class="clear"></div>

				<h3>Event Archiving</h3>
				<p class="description">
					When an Event End Date has passed the Event Page will still be available on the site for the number of days specified here.  Search Engines will be informed that this event has been intentionally removed and should also be de-indexed.
				</p>
				<fieldset id="tribe-field-eventArchiveWindow" class="tribe-field tribe-field-text tribe-size-medium">
					<legend class="tribe-field-label">
						Archive Events After (days)
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="eventArchiveWindow" id="eventArchiveWindow" value="<?php echo $opts['eventArchiveWindow']; ?>">
					</div>
				</fieldset>
				<p class="description">
					Once this number of days has elapsed visitors to the archived Events's URL will be redirected to the primary Events listing.
				</p>
				<div class="clear"></div>

				<h3>Call to Action Labels</h3>

				<p class="description">
					These are the global settings for the labels used on the Call to Action buttons for Events.  They will be pre-populated when you add a new Event.  You can override each of these for individual Events as needed when editing the Event under Event Details > Advanced.
				</p>

				<h4>Event Listings & Pages</h4>

				<fieldset id="tribe-field-ctaTextFreeShow" class="tribe-field tribe-field-text tribe-size-medium rhpcta-default-text">
					<legend class="tribe-field-label">
						Label: Free Show
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="ctaTextFreeShow" value="<?php echo $opts['ctaTextFreeShow']; ?>">
					</div>
				</fieldset>
				<p class="tribe-field-indent tribe-field-description rhpcta-default-text description">
					Text for CTA when the Free Show checkbox is activated.
				</p>
				<div class="clear"></div>

				<fieldset id="tribe-field-ctaTextSoldOut" class="tribe-field tribe-field-text tribe-size-medium rhpcta-default-text">
					<legend class="tribe-field-label">
						Label: Sold Out
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="ctaTextSoldOut" value="<?php echo $opts['ctaTextSoldOut']; ?>">
					</div>
				</fieldset>
				<p class="tribe-field-indent tribe-field-description rhpcta-default-text description">
					Text for the CTA when the Sold Out checkbox is activated.
				</p>
				<div class="clear"></div>

				<fieldset id="tribe-field-ctaTextComingSoon" class="tribe-field tribe-field-text tribe-size-medium rhpcta-default-text">
					<legend class="tribe-field-label">
						Label: Coming Soon
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="ctaTextComingSoon" value="<?php echo $opts['ctaTextComingSoon']; ?>">
					</div>
				</fieldset>
				<p class="tribe-field-indent tribe-field-description rhpcta-default-text description">
					CTA Text when the Event Start Date and On Sale Date are in the future.
				</p>
				<div class="clear"></div>

				<fieldset id="tribe-field-ctaTextOnSale" class="tribe-field tribe-field-text tribe-size-medium rhpcta-default-text">
					<legend class="tribe-field-label">
						Label: Buy Tickets
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="ctaTextOnSale" value="<?php echo $opts['ctaTextOnSale']; ?>">
					</div>
				</fieldset>
				<p class="tribe-field-indent tribe-field-description rhpcta-default-text description">
					CTA Text when the Event Start Date is in the future and the On Sale Date is empty or in the past.
				</p>
				<div class="clear"></div>

				<fieldset id="tribe-field-ctaTextOffSale" class="tribe-field tribe-field-text tribe-size-medium rhpcta-default-text">
					<legend class="tribe-field-label">
						Label: Not Available Online
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="ctaTextOffSale" value="<?php echo $opts['ctaTextOffSale']; ?>">
					</div>
				</fieldset>
				<p class="tribe-field-indent tribe-field-description rhpcta-default-text description">
					CTA Text when the Event has not yet ended and the Off Sale Date is in the past.<br/>Commonly used when onlines sales end prior to an Event or tickets are only available at the door.
				</p>
				<div class="clear"></div>


				<h4>Sidebar Widgets</h4>

				<p class="description">
					Widgets are space constrained and require a slightly shorter set of defaults. These settings are only available to be set here (Event level CTA overrides do not apply).
				</p>

				<fieldset id="tribe-field-ctaWidgetTextFreeShow" class="tribe-field tribe-field-text tribe-size-medium rhpcta-default-text">
					<legend class="tribe-field-label">
						Label: Free Show
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="ctaWidgetTextFreeShow" value="<?php echo $opts['ctaWidgetTextFreeShow']; ?>">
					</div>
				</fieldset>
				<div class="clear"></div>

				<fieldset id="tribe-field-ctaWidgetTextSoldOut" class="tribe-field tribe-field-text tribe-size-medium rhpcta-default-text">
					<legend class="tribe-field-label">
						Label: Sold Out
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="ctaWidgetTextSoldOut" value="<?php echo $opts['ctaWidgetTextSoldOut']; ?>">
					</div>
				</fieldset>
				<div class="clear"></div>

				<fieldset id="tribe-field-ctaWidgetTextComingSoon" class="tribe-field tribe-field-text tribe-size-medium rhpcta-default-text">
					<legend class="tribe-field-label">
						Label: Coming Soon
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="ctaWidgetTextComingSoon" value="<?php echo $opts['ctaWidgetTextComingSoon']; ?>">
					</div>
				</fieldset>
				<div class="clear"></div>

				<fieldset id="tribe-field-ctaWidgetTextOnSale" class="tribe-field tribe-field-text tribe-size-medium rhpcta-default-text">
					<legend class="tribe-field-label">
						Label: Buy Tickets
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="ctaWidgetTextOnSale" value="<?php echo $opts['ctaWidgetTextOnSale']; ?>">
					</div>
				</fieldset>
				<div class="clear"></div>

				<fieldset id="tribe-field-ctaWidgetTextOffSale" class="tribe-field tribe-field-text tribe-size-medium rhpcta-default-text">
					<legend class="tribe-field-label">
						Label: Not Available Online
					</legend>
					<div class="tribe-field-wrap">
						<input type="text" name="ctaWidgetTextOffSale" value="<?php echo $opts['ctaWidgetTextOffSale']; ?>">
					</div>
				</fieldset>
				<div class="clear"></div>

				<input type="hidden" name="rhp-events-nonce" id="rhp-events-nonce" value="<?php echo wp_create_nonce('rhp-events-settings'); ?>" />
				<input id="rhpSaveSettings" class="button-primary" type="submit" name="rhpSaveSettings" value=" Save Changes" />
			</div>

