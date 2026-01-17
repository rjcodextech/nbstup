<?php
/**
 * PMPro Bank Transfer Fields
 * Transaction ID + Payment Receipt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enable file upload on PMPro checkout form
 */
add_filter( 'pmpro_checkout_form_enctype', function () {
	return 'multipart/form-data';
});

/**
 * Add fields after Payment Details section
 */
add_action( 'pmpro_checkout_after_payment_information_fields', 'pmpro_add_bank_transfer_fields' );
function pmpro_add_bank_transfer_fields() {
	?>
	<fieldset id="pmpro_form_fieldset-bank-transfer" class="pmpro_form_fieldset">
		<div class="pmpro_card">
			<div class="pmpro_card_content">

				<legend class="pmpro_form_legend">
					<h2 class="pmpro_form_heading pmpro_font-large">
						<?php esc_html_e( 'Bank Transfer Details', 'pmpro-nbstup' ); ?>
					</h2>
				</legend>

				<div class="pmpro_form_fields">

					<!-- Transaction ID -->
					<div id="bank_transaction_id_wrap"
						class="pmpro_form_field pmpro_form_field-text pmpro_form_field-bank_transaction_id pmpro_form_field-required">

						<label class="pmpro_form_label" for="bank_transaction_id">
							<?php esc_html_e( 'Transaction ID', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>

						<input
							type="text"
							id="bank_transaction_id"
							name="bank_transaction_id"
							size="30"
							class="pmpro_form_input pmpro_form_input-text pmpro_form_input-bank_transaction_id pmpro_form_input-required"
							aria-required="true"
						/>
					</div>

					<!-- Payment Receipt -->
					<div id="bank_payment_receipt_wrap"
						class="pmpro_form_field pmpro_form_field-file pmpro_form_field-bank_payment_receipt pmpro_form_field-required">

						<label class="pmpro_form_label" for="bank_payment_receipt">
							<?php esc_html_e( 'Payment Receipt', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>

						<div id="pmpro_file_bank_payment_receipt_upload"
							class="pmpro_form_field-file-upload">

							<input
								type="file"
								id="bank_payment_receipt"
								name="bank_payment_receipt"
								accept=".png,.jpg,.jpeg,.pdf"
								class="pmpro_form_input pmpro_form_input-file pmpro_form_input-bank_payment_receipt pmpro_form_input-required"
								aria-required="true"
							/>
						</div>
					</div>

				</div><!-- .pmpro_form_fields -->

			</div><!-- .pmpro_card_content -->
		</div><!-- .pmpro_card -->
	</fieldset>
	<?php
}

/**
 * Save Bank Transfer data after checkout
 * (User meta + Order meta)
 */
add_action( 'pmpro_after_checkout', 'pmpro_save_bank_transfer_data', 10, 2 );
function pmpro_save_bank_transfer_data( $user_id, $order ) {

	if ( empty( $_POST['gateway'] ) || $_POST['gateway'] !== 'check' ) {
		return;
	}

	// Transaction ID
	if ( ! empty( $_POST['bank_transaction_id'] ) ) {
		$txn_id = sanitize_text_field( $_POST['bank_transaction_id'] );

		update_user_meta( $user_id, 'bank_transaction_id', $txn_id );
		update_post_meta( $order->id, 'bank_transaction_id', $txn_id );
	}

	// Payment Receipt
	if ( ! empty( $_FILES['bank_payment_receipt']['name'] ) ) {

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload = wp_handle_upload(
			$_FILES['bank_payment_receipt'],
			array( 'test_form' => false )
		);

		if ( empty( $upload['error'] ) ) {
			$receipt_url = esc_url_raw( $upload['url'] );

			update_user_meta( $user_id, 'bank_payment_receipt', $receipt_url );
			update_post_meta( $order->id, 'bank_payment_receipt', $receipt_url );
		}
	}
}

/**
 * Show Bank Transfer details in Member Dashboard (Order History)
 */
add_action( 'pmpro_member_order_details_after', 'pmpro_show_bank_details_to_member', 10, 1 );
function pmpro_show_bank_details_to_member( $order ) {

	$txn_id  = get_post_meta( $order->id, 'bank_transaction_id', true );
	$receipt = get_post_meta( $order->id, 'bank_payment_receipt', true );

	if ( empty( $txn_id ) && empty( $receipt ) ) {
		return;
	}
	?>
	<div class="pmpro_box pmpro_box-bank-transfer">
		<h3><?php esc_html_e( 'Bank Transfer Details', 'pmpro-nbstup' ); ?></h3>

		<?php if ( $txn_id ) : ?>
			<p>
				<strong><?php esc_html_e( 'Transaction ID:', 'pmpro-nbstup' ); ?></strong>
				<?php echo esc_html( $txn_id ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $receipt ) : ?>
			<p>
				<strong><?php esc_html_e( 'Payment Receipt:', 'pmpro-nbstup' ); ?></strong>
				<a href="<?php echo esc_url( $receipt ); ?>" target="_blank">
					<?php esc_html_e( 'View Receipt', 'pmpro-nbstup' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Show Bank Transfer details in WP-Admin Order screen
 */
add_action( 'pmpro_order_details_after', 'pmpro_show_bank_details_in_admin', 10, 1 );
function pmpro_show_bank_details_in_admin( $order ) {

	$txn_id  = get_post_meta( $order->id, 'bank_transaction_id', true );
	$receipt = get_post_meta( $order->id, 'bank_payment_receipt', true );

	if ( empty( $txn_id ) && empty( $receipt ) ) {
		return;
	}
	?>
	<tr>
		<td colspan="2">
			<h3><?php esc_html_e( 'Bank Transfer Details', 'pmpro-nbstup' ); ?></h3>

			<?php if ( $txn_id ) : ?>
				<p>
					<strong><?php esc_html_e( 'Transaction ID:', 'pmpro-nbstup' ); ?></strong>
					<?php echo esc_html( $txn_id ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $receipt ) : ?>
				<p>
					<strong><?php esc_html_e( 'Payment Receipt:', 'pmpro-nbstup' ); ?></strong>
					<a href="<?php echo esc_url( $receipt ); ?>" target="_blank">
						<?php esc_html_e( 'View Receipt', 'pmpro-nbstup' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}
