<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TextitBiz_Admin {
	private $plugin;
	private $detector;

	public function __construct( TextitBiz_Notifications $plugin, TextitBiz_Detector $detector ) {
		$this->plugin   = $plugin;
		$this->detector = $detector;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_textitbiz_clear_logs', array( $this, 'handle_clear_logs' ) );
	}

	public function register_menu() {
		add_options_page(
			'TextitBiz Notifications',
			'TextitBiz Notifications',
			'manage_options',
			'textitbiz-notifications',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'textitbiz_notifications',
			TextitBiz_Notifications::OPTION_KEY,
			array( $this, 'sanitize_settings' )
		);
	}

	public function sanitize_settings( $input ) {
		$existing   = $this->plugin->get_settings();
		$new_secret = sanitize_text_field( $input['api_key'] ?? '' );
		$checkboxes = array(
			'enabled',
			'enable_metform',
			'enable_elementor_pro',
			'enable_contact_form_7',
			'enable_woocommerce',
		);

		$output = array(
			'user_id'          => sanitize_text_field( $input['user_id'] ?? '' ),
			'api_key'          => '',
			'api_key_enc'      => '',
			'admin_phone'      => sanitize_text_field( $input['admin_phone'] ?? '' ),
			'message_template' => sanitize_textarea_field( $input['message_template'] ?? '' ),
			'monitored_forms'  => array_values(
				array_filter(
					array_map(
						'sanitize_text_field',
						isset( $input['monitored_forms'] ) && is_array( $input['monitored_forms'] ) ? $input['monitored_forms'] : array()
					)
				)
			),
		);

		foreach ( $checkboxes as $checkbox ) {
			$output[ $checkbox ] = isset( $input[ $checkbox ] ) ? '1' : '0';
		}

		if ( '' !== $new_secret ) {
			$encrypted = $this->plugin->encrypt_secret( $new_secret );

			if ( '' !== $encrypted ) {
				$output['api_key_enc'] = $encrypted;
			} else {
				$output['api_key'] = $new_secret;
			}
		} elseif ( ! empty( $existing['api_key_enc'] ) ) {
			$output['api_key_enc'] = $existing['api_key_enc'];
		} elseif ( ! empty( $existing['api_key'] ) ) {
			$encrypted = $this->plugin->encrypt_secret( $existing['api_key'] );

			if ( '' !== $encrypted ) {
				$output['api_key_enc'] = $encrypted;
			} else {
				$output['api_key'] = $existing['api_key'];
			}
		}

		return $output;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'textitbiz-notifications' ) );
		}

		$settings    = $this->plugin->get_settings();
		$active      = $this->detector->get_active_integrations();
		$forms       = $this->detector->get_detected_forms();
		$logs        = $this->plugin->get_logs();
		$option_name = TextitBiz_Notifications::OPTION_KEY;
		$clear_logs_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=textitbiz_clear_logs' ),
			'textitbiz_clear_logs_action',
			'textitbiz_clear_logs_nonce'
		);
		?>
		<div class="wrap">
			<h1>TextitBiz Notifications</h1>
			<form method="post" action="options.php" style="max-width:980px;">
				<?php settings_fields( 'textitbiz_notifications' ); ?>

				<div style="background:#fff;border:1px solid #dcdcde;padding:24px;margin:20px 0;">
					<h2 style="margin-top:0;">Choose Forms</h2>
					<p>Select only the forms you want to trigger SMS. Nothing will be sent for unselected forms.</p>

					<?php if ( empty( $active ) ) : ?>
						<p>No supported active form plugins found.</p>
					<?php else : ?>
						<?php foreach ( $active as $integration_key => $integration ) : ?>
							<?php if ( empty( $forms[ $integration_key ] ) ) {
								continue;
							} ?>
							<div style="margin:0 0 18px; padding:16px; background:#f6f7f7; border:1px solid #dcdcde;">
								<h3 style="margin-top:0;"><?php echo esc_html( $integration['label'] ); ?></h3>
								<?php foreach ( $forms[ $integration_key ] as $form ) : ?>
									<label style="display:block;margin:8px 0;">
										<input type="checkbox" class="textitbiz-form-checkbox" data-template="<?php echo esc_attr( $this->build_form_template( $form ) ); ?>" data-shortcodes="<?php echo esc_attr( wp_json_encode( $this->get_form_shortcodes( $form ) ) ); ?>" name="<?php echo esc_attr( $option_name ); ?>[monitored_forms][]" value="<?php echo esc_attr( $form['key'] ); ?>" <?php checked( $this->plugin->is_form_monitored( $form['key'] ) ); ?>>
										<strong><?php echo esc_html( $form['label'] ); ?></strong>
									</label>
									<?php if ( ! empty( $form['fields'] ) ) : ?>
										<p style="margin:4px 0 0 24px;color:#50575e;">Fields: <code><?php echo esc_html( implode( ', ', $form['fields'] ) ); ?></code></p>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>

					<p><label><input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], '1' ); ?>> Enable SMS notifications</label></p>
				</div>

				<div style="background:#fff;border:1px solid #dcdcde;padding:24px;margin:20px 0;">
					<h2 style="margin-top:0;">Textit.biz Account Settings</h2>
					<p>Please enter your Textit.biz account credentials</p>
					<p style="margin-top:0;">No account ? Simplay <a href="https://textit.biz/signup_1.php" target="_blank" rel="noopener noreferrer">click here</a> to register for a free account with text credits<br><a href="https://textit.biz" target="_blank" rel="noopener noreferrer">Textit.biz SMS Gateway</a>. Hotline 94772823050</p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Admin Mobile Number</th>
							<td><input type="text" class="regular-text" name="<?php echo esc_attr( $option_name ); ?>[admin_phone]" value="<?php echo esc_attr( $settings['admin_phone'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row">Message</th>
							<td><textarea id="textitbiz-message-template" class="large-text code" rows="8" name="<?php echo esc_attr( $option_name ); ?>[message_template]"><?php echo esc_textarea( $this->normalize_message_template( $settings['message_template'] ) ); ?></textarea><p class="description">Keep it simple. SMS should only include the main details. Check email or form entries for the full details.</p><div style="margin-top:12px;padding:12px;background:#f6f7f7;border:1px solid #dcdcde;"><strong>Suggested message for selected form</strong><pre id="textitbiz-suggested-template" style="margin:10px 0 0;white-space:pre-wrap;font-family:Consolas,monospace;"></pre><p style="margin:8px 0 0;color:#50575e;">Copy this if you want, then edit it as needed.</p></div></td>
						</tr>
						<tr>
							<th scope="row">User ID</th>
							<td><input type="text" class="regular-text" name="<?php echo esc_attr( $option_name ); ?>[user_id]" value="<?php echo esc_attr( $settings['user_id'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row">Password</th>
							<td><input type="password" class="regular-text" name="<?php echo esc_attr( $option_name ); ?>[api_key]" value="" autocomplete="new-password"><p class="description">Leave blank to keep existing password.</p></td>
						</tr>
					</table>
				</div>

				<div style="background:#fff;border:1px solid #dcdcde;padding:24px;margin:20px 0;">
					<h2 style="margin-top:0;">Available Shortcodes</h2>
					<p>These shortcodes change based on the selected form.</p>

					<table class="widefat striped">
						<tbody id="textitbiz-shortcodes-list">
							<tr>
								<td colspan="2">Select a form above to see matching shortcodes.</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div style="background:#fff;border:1px solid #dcdcde;padding:24px;margin:20px 0;">
					<h2 style="margin-top:0;">SMS Logs</h2>
					<p>Shows the last 20 send attempts with status (success, error, warning, info).</p>

					<p><a class="button button-secondary" href="<?php echo esc_url( $clear_logs_url ); ?>">Clear Logs</a></p>

					<?php if ( empty( $logs ) ) : ?>
						<p>No logs yet.</p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th style="width:180px;">Time</th>
									<th style="width:90px;">Status</th>
									<th style="width:130px;">Source</th>
									<th>Message</th>
									<th>Details</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $logs as $log ) : ?>
									<tr>
										<td><?php echo esc_html( $log['time'] ?? '' ); ?></td>
										<td><strong><?php echo esc_html( strtoupper( (string) ( $log['level'] ?? '' ) ) ); ?></strong></td>
										<td><?php echo esc_html( $log['source'] ?? '' ); ?></td>
										<td><?php echo esc_html( $log['message'] ?? '' ); ?></td>
										<td><code><?php echo esc_html( wp_json_encode( $log['context'] ?? array() ) ); ?></code></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<script>
		(function() {
			var checkboxes = document.querySelectorAll('.textitbiz-form-checkbox');
			var preview = document.getElementById('textitbiz-suggested-template');
			var shortcodeList = document.getElementById('textitbiz-shortcodes-list');
			var messageField = document.getElementById('textitbiz-message-template');
			if (!preview || !shortcodeList || !messageField || !checkboxes.length) {
				return;
			}

			function escapeHtml(text) {
				return String(text)
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;')
					.replace(/'/g, '&#039;');
			}

			function isLegacyMessage(value) {
				if (!value) {
					return true;
				}

				var markers = ['{source}', 'Form: {form_name}', 'Email: {email}', 'Page: {page_url}'];
				var matched = 0;
				markers.forEach(function(marker) {
					if (value.indexOf(marker) !== -1) {
						matched++;
					}
				});

				return matched >= 1;
			}

			function renderShortcodes(selected) {
				if (!selected.length) {
					shortcodeList.innerHTML = '<tr><td colspan="2">Select a form above to see matching shortcodes.</td></tr>';
					return;
				}

				var raw = selected[0].getAttribute('data-shortcodes') || '[]';
				var items = [];
				try {
					items = JSON.parse(raw);
				} catch (e) {
					items = [];
				}

				if (!items.length) {
					shortcodeList.innerHTML = '<tr><td colspan="2">No shortcodes found for this form.</td></tr>';
					return;
				}

				shortcodeList.innerHTML = items.map(function(item) {
					return '<tr><td style="width:260px;"><code>' + escapeHtml(item.code) + '</code></td><td>' + escapeHtml(item.label) + '</td></tr>';
				}).join('');
			}

			function updatePreview() {
				var selected = Array.prototype.filter.call(checkboxes, function(item) {
					return item.checked;
				});

				if (!selected.length) {
					preview.textContent = 'Select a form above to see a suggested SMS message.';
					renderShortcodes(selected);
					return;
				}

				var suggested = selected[0].getAttribute('data-template') || '';
				preview.textContent = suggested;
				if (isLegacyMessage(messageField.value)) {
					messageField.value = suggested;
				}
				renderShortcodes(selected);
			}

			Array.prototype.forEach.call(checkboxes, function(item) {
				item.addEventListener('change', updatePreview);
			});

			updatePreview();
		})();
		</script>
		<?php
	}

	public function handle_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'textitbiz-notifications' ) );
		}

		check_admin_referer( 'textitbiz_clear_logs_action', 'textitbiz_clear_logs_nonce' );

		$this->plugin->clear_logs();

		wp_safe_redirect( admin_url( 'options-general.php?page=textitbiz-notifications' ) );
		exit;
	}

	private function build_form_template( array $form ) {
		$lines  = array();
		$fields = ! empty( $form['fields'] ) ? $form['fields'] : array();

		$lines[] = 'New ' . $form['label'];

		foreach ( $this->get_priority_fields( $fields ) as $field ) {
			$lines[] = $this->get_field_label( $field ) . ': {field:' . $field . '}';
		}

		if ( 1 === count( $lines ) ) {
			foreach ( array_slice( $fields, 0, 4 ) as $field ) {
				$lines[] = $this->get_field_label( $field ) . ': {field:' . $field . '}';
			}
		}

		return implode( "\n", $lines );
	}

	private function get_priority_fields( array $fields ) {
		$selected = array();
		$groups   = array(
			array( 'name', 'first', 'full' ),
			array( 'phone', 'tel', 'mobile', 'whatsapp' ),
			array( 'email' ),
			array( 'subject', 'package', 'tour', 'booking', 'adult', 'guest', 'checkin', 'date' ),
		);

		foreach ( $groups as $needles ) {
			foreach ( $fields as $field ) {
				$field_lc = strtolower( (string) $field );
				foreach ( $needles as $needle ) {
					if ( false !== strpos( $field_lc, $needle ) ) {
						$selected[] = $field;
						continue 3;
					}
				}
			}
		}

		return array_values( array_unique( $selected ) );
	}

	private function get_form_shortcodes( array $form ) {
		$items  = array();
		$fields = ! empty( $form['fields'] ) ? $form['fields'] : array();

		$items[] = array(
			'code'  => '{form_name}',
			'label' => 'Selected form name',
		);

		foreach ( $fields as $field ) {
			$items[] = array(
				'code'  => '{field:' . $field . '}',
				'label' => $this->get_field_label( $field ),
			);
		}

		return $items;
	}

	private function get_field_label( $field ) {
		$raw = strtolower( (string) $field );

		if ( false !== strpos( $raw, 'first-name' ) || false !== strpos( $raw, 'first_name' ) || 'name' === $raw || false !== strpos( $raw, 'full-name' ) ) {
			return 'Name';
		}

		if ( false !== strpos( $raw, 'tel' ) || false !== strpos( $raw, 'phone' ) || false !== strpos( $raw, 'mobile' ) ) {
			return 'Phone';
		}

		if ( false !== strpos( $raw, 'email' ) ) {
			return 'Email';
		}

		if ( false !== strpos( $raw, 'adult' ) ) {
			return 'Adults';
		}

		if ( false !== strpos( $raw, 'checkin' ) ) {
			return 'Check-in Date';
		}

		$field = str_replace( array( 'mf-', '_', '-' ), ' ', (string) $field );
		$field = preg_replace( '/\s+/', ' ', $field );

		return ucwords( trim( $field ) );
	}

	private function normalize_message_template( $template ) {
		$current = "New {form_name}\nName: {name}\nPhone: {phone}";
		$template = trim( (string) $template );

		if ( '' === $template ) {
			return $current;
		}

		$legacy_markers = array( '{source}', 'Form: {form_name}', 'Email: {email}', 'Page: {page_url}' );
		$matched        = 0;

		foreach ( $legacy_markers as $marker ) {
			if ( false !== strpos( $template, $marker ) ) {
				$matched++;
			}
		}

		if ( $matched >= 1 ) {
			return $current;
		}

		return $template;
	}
}
