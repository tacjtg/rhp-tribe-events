<?php
/**
 * The Admin View for Logs
 *
 * @author dliszka
 * @since 1.1
 */
$opts = RockhouseEvents::getOptions();
?>
			<div class="tribe-settings-form-wrap">
<?php

		$logger = RockhouseLogger::instance();
		$loglist = $logger->fetch('all');

		if( count($loglist) > 1 ) {
?>
				<h3>Etix API Logs</h3>

				<div id="modern-tribe-info">
					<pre id="syslog" style="font-size: 10px; line-height: 1.5em;font-weight:bold">
<?php
			$logs = '';
			foreach($loglist as $stamp => $line) {
				$logs .= "[{$stamp}] {$line}\r\n";
			}
			echo $logs; // flush buffer once
?>
					</pre>
				</div>
<?php
		} else {

?>
			<br/>
			<br/>
			<p class="description">
				No logs means no problems, enjoy the silence!
			</p>
<?php
		}

		// Now for critical events
		$log = RockhouseLogger::instance();
		$crit = $log->fetch('critical');
		if( count($crit) > 1 ) {
			$logs = '';
			foreach($crit as $stamp => $line) {
				$logs .= "[{$stamp}] {$line}\r\n";
			}
?>
				<h3>Critical Errors</h3>
				<div id="modern-tribe-info">
					<pre id="syslog" style="font-size: 10px; line-height: 1.5em;font-weight:bold">
						<?php echo $logs; ?>
					</pre>
				</div>
<?php
		}
?>

			</div>

