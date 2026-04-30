<?php
/**
 * Child connection error panel template.
 *
 * Available variables:
 * - string $burst_mainwp_support_url
 * - string $burst_mainwp_report_id
 * - string $burst_mainwp_status_id
 * - string $burst_mainwp_button_id
 * - string $burst_mainwp_report
 *
 * @package Burst_Statistics_MainWP
 */

defined( 'ABSPATH' ) || exit;

$burst_mainwp_support_url = isset( $burst_mainwp_support_url ) ? (string) $burst_mainwp_support_url : '';
$burst_mainwp_report_id   = isset( $burst_mainwp_report_id ) ? (string) $burst_mainwp_report_id : '';
$burst_mainwp_status_id   = isset( $burst_mainwp_status_id ) ? (string) $burst_mainwp_status_id : '';
$burst_mainwp_button_id   = isset( $burst_mainwp_button_id ) ? (string) $burst_mainwp_button_id : '';
$burst_mainwp_report      = isset( $burst_mainwp_report ) ? (string) $burst_mainwp_report : '';
?>
<div class="ui negative message" role="alert" aria-live="assertive" style="padding:20px; margin: 20px;">
	<div class="header" style="margin-bottom:10px;font-size:18px;line-height:1.3;">
		<?php esc_html_e( 'Connection to child site failed', 'burst-mainwp' ); ?>
	</div>
	<p style="margin:0 0 12px;font-size:14px;line-height:1.45;">
		<?php esc_html_e( 'Could not connect to child site. Please ensure Burst Statistics is installed and active on the child site.', 'burst-mainwp' ); ?>
	</p>
	<ul
		style="margin:0 0 14px;padding-left:1.4em;font-size:14px;line-height:1.45;color:inherit;list-style:disc outside;">
		<li style="margin:0 0 4px;color:inherit;display:list-item;list-style:disc outside;">
			<?php esc_html_e( 'Check that Burst Statistics is active on the child site.', 'burst-mainwp' ); ?>
		</li>
		<li style="margin:0 0 4px;color:inherit;display:list-item;list-style:disc outside;">
			<?php esc_html_e( 'Open the child site once in MainWP and run a Sync.', 'burst-mainwp' ); ?>
		</li>
		<li style="color:inherit;display:list-item;list-style:disc outside;">
			<?php esc_html_e( 'If it still fails, copy the report below and send it to support.', 'burst-mainwp' ); ?>
		</li>
	</ul>
	<div class="field" style="margin:0 0 14px;">
		<label for="<?php echo esc_attr( $burst_mainwp_report_id ); ?>" style="display:block;margin-bottom:6px;font-size:14px;">
			<strong><?php esc_html_e( 'Support report', 'burst-mainwp' ); ?></strong>
		</label>
		<textarea id="<?php echo esc_attr( $burst_mainwp_report_id ); ?>" readonly rows="7"
			style="width:100%;font-family:monospace;line-height:1.45;font-size:13px;"
			aria-describedby="<?php echo esc_attr( $burst_mainwp_status_id ); ?>"><?php echo esc_textarea( $burst_mainwp_report ); ?></textarea>
	</div>
	<div class="ui buttons" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
		<button type="button" id="<?php echo esc_attr( $burst_mainwp_button_id ); ?>"
			class="ui button green"><?php esc_html_e( 'Copy report', 'burst-mainwp' ); ?></button>
		<a class="ui button" target="_blank" rel="noopener noreferrer"
			href="<?php echo esc_url( $burst_mainwp_support_url ); ?>"><?php esc_html_e( 'Contact support', 'burst-mainwp' ); ?></a>
	</div>
	<p id="<?php echo esc_attr( $burst_mainwp_status_id ); ?>" role="status" aria-live="polite"
		style="margin:10px 0 0;font-size:13px;line-height:1.4;"></p>
	<script>
		(function () {
			var btn = document.getElementById(<?php echo wp_json_encode( $burst_mainwp_button_id ); ?>);
			var report = document.getElementById(<?php echo wp_json_encode( $burst_mainwp_report_id ); ?>);
			var status = document.getElementById(<?php echo wp_json_encode( $burst_mainwp_status_id ); ?>);
			if (!btn || !report || !status) {
				return;
			}

			var copiedMsg = <?php echo wp_json_encode( __( 'Report copied. Paste it in your support message.', 'burst-mainwp' ) ); ?>;
			var fallbackMsg = <?php echo wp_json_encode( __( 'Copy failed. Please select the report text manually and copy it.', 'burst-mainwp' ) ); ?>;

			btn.addEventListener('click', function () {
				report.focus();
				report.select();

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(report.value)
						.then(function () {
							status.textContent = copiedMsg;
						})
						.catch(function () {
							status.textContent = fallbackMsg;
						});
				} else {
					try {
						document.execCommand('copy');
						status.textContent = copiedMsg;
					} catch (e) {
						status.textContent = fallbackMsg;
					}
				}
			});
		})();
	</script>
</div>
