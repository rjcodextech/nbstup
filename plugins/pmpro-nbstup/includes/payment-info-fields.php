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
 * Add member details fields after Payment Details section
 */
add_action( 'pmpro_checkout_after_payment_information_fields', 'pmpro_add_member_details_fields', 3 );
function pmpro_add_member_details_fields() {
	$user_id = get_current_user_id();

	$values = array(
		'name' => $user_id ? get_user_meta( $user_id, 'name', true ) : '',
		'phone_no' => $user_id ? get_user_meta( $user_id, 'phone_no', true ) : '',
		'aadhar_number' => $user_id ? get_user_meta( $user_id, 'aadhar_number', true ) : '',
		'father_husband_name' => $user_id ? get_user_meta( $user_id, 'father_husband_name', true ) : '',
		'dob' => $user_id ? get_user_meta( $user_id, 'dob', true ) : '',
		'gender' => $user_id ? get_user_meta( $user_id, 'gender', true ) : '',
		'join_blood_donation' => $user_id ? get_user_meta( $user_id, 'join_blood_donation', true ) : '',
		'Occupation' => $user_id ? get_user_meta( $user_id, 'Occupation', true ) : '',
		'member_password' => '',
	);

	foreach ( $values as $key => $value ) {
		if ( isset( $_REQUEST[ $key ] ) ) {
			if ( $key === 'join_blood_donation' ) {
				$values[ $key ] = sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
			} else {
				$values[ $key ] = sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
			}
		}
	}

	$gender_options = array(
		'male' => __( 'Male', 'pmpro-nbstup' ),
		'female' => __( 'Female', 'pmpro-nbstup' ),
		'other' => __( 'Other', 'pmpro-nbstup' ),
	);

	$occupation_options = array(
		'Government Job' => __( 'Government Job', 'pmpro-nbstup' ),
		'Private Job' => __( 'Private Job', 'pmpro-nbstup' ),
		'Business' => __( 'Business', 'pmpro-nbstup' ),
		'Agriculture' => __( 'Agriculture', 'pmpro-nbstup' ),
		'Housewife' => __( 'Housewife', 'pmpro-nbstup' ),
		'Student' => __( 'Student', 'pmpro-nbstup' ),
		'Contract Workers' => __( 'Contract Workers', 'pmpro-nbstup' ),
		'Public Representative' => __( 'Public Representative', 'pmpro-nbstup' ),
	);
	?>
	<fieldset id="pmpro_form_fieldset-member-details" class="pmpro_form_fieldset">
		<div class="pmpro_card">
			<div class="pmpro_card_content">

				<legend class="pmpro_form_legend">
					<h2 class="pmpro_form_heading pmpro_font-large">
						<?php esc_html_e( 'Member Details', 'pmpro-nbstup' ); ?>
					</h2>
				</legend>

				<div class="pmpro_form_fields">

					<!-- Name -->
					<div id="name_wrap" class="pmpro_form_field pmpro_form_field-text pmpro_form_field-name pmpro_form_field-required">
						<label class="pmpro_form_label" for="name">
							<?php esc_html_e( 'नाम', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>
						<input
							type="text"
							id="name"
							name="name"
							size="30"
							class="pmpro_form_input pmpro_form_input-text pmpro_form_input-name pmpro_form_input-required"
							aria-required="true"
							required
							value="<?php echo esc_attr( $values['name'] ); ?>"
						/>
					</div>

					<!-- Phone Number -->
					<div id="phone_no_wrap" class="pmpro_form_field pmpro_form_field-text pmpro_form_field-phone_no pmpro_form_field-required">
						<label class="pmpro_form_label" for="phone_no">
							<?php esc_html_e( 'फ़ोन नंबर', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>
						<input
							type="text"
							id="phone_no"
							name="phone_no"
							size="20"
							class="pmpro_form_input pmpro_form_input-text pmpro_form_input-phone_no pmpro_form_input-required"
							aria-required="true"
							required
							value="<?php echo esc_attr( $values['phone_no'] ); ?>"
						/>
					</div>

					<!-- Aadhar Number -->
					<div id="aadhar_number_wrap" class="pmpro_form_field pmpro_form_field-number pmpro_form_field-aadhar_number pmpro_form_field-required">
						<label class="pmpro_form_label" for="aadhar_number">
							<?php esc_html_e( 'आधार कार्ड नंबर', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>
						<input
							type="number"
							id="aadhar_number"
							name="aadhar_number"
							size="20"
							class="pmpro_form_input pmpro_form_input-number pmpro_form_input-aadhar_number pmpro_form_input-required"
							aria-required="true"
							required
							value="<?php echo esc_attr( $values['aadhar_number'] ); ?>"
						/>
					</div>

					<!-- Father / Husband Name -->
					<div id="father_husband_name_wrap" class="pmpro_form_field pmpro_form_field-text pmpro_form_field-father_husband_name pmpro_form_field-required">
						<label class="pmpro_form_label" for="father_husband_name">
							<?php esc_html_e( 'पिता / पति का नाम', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>
						<input
							type="text"
							id="father_husband_name"
							name="father_husband_name"
							size="30"
							class="pmpro_form_input pmpro_form_input-text pmpro_form_input-father_husband_name pmpro_form_input-required"
							aria-required="true"
							required
							value="<?php echo esc_attr( $values['father_husband_name'] ); ?>"
						/>
					</div>

					<!-- Date of Birth -->
					<div id="dob_wrap" class="pmpro_form_field pmpro_form_field-date pmpro_form_field-dob pmpro_form_field-required">
						<label class="pmpro_form_label" for="dob">
							<?php esc_html_e( 'जन्म तिथि (आधार कार्ड के अनुसार)', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>
						<input
							type="date"
							id="dob"
							name="dob"
							class="pmpro_form_input pmpro_form_input-date pmpro_form_input-dob pmpro_form_input-required"
							aria-required="true"
							required
							value="<?php echo esc_attr( $values['dob'] ); ?>"
						/>
					</div>

					<!-- Gender -->
					<div id="gender_wrap" class="pmpro_form_field pmpro_form_field-select pmpro_form_field-gender pmpro_form_field-required">
						<label class="pmpro_form_label" for="gender">
							<?php esc_html_e( 'जेंडर', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>
						<select
							id="gender"
							name="gender"
							class="pmpro_form_input pmpro_form_input-select pmpro_form_input-gender pmpro_form_input-required"
							aria-required="true"
							required>
							<option value=""><?php esc_html_e( 'Select', 'pmpro-nbstup' ); ?></option>
							<?php foreach ( $gender_options as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $values['gender'], $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<!-- Join Blood Donation Team -->
					<div id="join_blood_donation_wrap" class="pmpro_form_field pmpro_form_field-checkbox pmpro_form_field-join_blood_donation">
						<label class="pmpro_form_label" for="join_blood_donation">
							<input
								type="checkbox"
								id="join_blood_donation"
								name="join_blood_donation"
								value="1"
								<?php checked( (int) $values['join_blood_donation'], 1 ); ?>
							/>
							<?php esc_html_e( 'क्या आप स्वयं से रक्तदान टीम के सदस्य बनना चाहते हैं?', 'pmpro-nbstup' ); ?>
						</label>
						<p class="pmpro_form_hint">यदि आपका जवाब हाँ है तो चेक बॉक्स में <img draggable="false" role="img" class="emoji" alt="✔" src="https://s.w.org/images/core/emoji/17.0.2/svg/2714.svg"> करें, अन्यथा खाली छोड़ दें</p>
					</div>

					<!-- Occupation -->
					<div id="Occupation_wrap" class="pmpro_form_field pmpro_form_field-radio pmpro_form_field-Occupation pmpro_form_field-required">
						<span class="pmpro_form_label">
							<?php esc_html_e( 'कार्य / व्यवसाय (Occupation)', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</span>
						<div class="pmpro_form_field-radio-wrapper">
							<?php foreach ( $occupation_options as $value => $label ) : ?>
								<div class="pmpro_form_field pmpro_form_field-radio-item">
								<label>
									<input
										type="radio"
										name="Occupation"
										value="<?php echo esc_attr( $value ); ?>"
										required
										<?php checked( $values['Occupation'], $value ); ?>
									/>
									<?php echo esc_html( $label ); ?>
								</label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Password -->
					<div id="member_password_wrap" class="pmpro_form_field pmpro_form_field-password pmpro_form_field-member_password pmpro_form_field-required">
						<label class="pmpro_form_label" for="member_password">
							<?php esc_html_e( 'Password', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>
						<input
							type="password"
							id="member_password"
							name="member_password"
							size="30"
							class="pmpro_form_input pmpro_form_input-password pmpro_form_input-member_password pmpro_form_input-required"
							aria-required="true"
							required
							value=""
						/>
					</div>

				</div><!-- .pmpro_form_fields -->

			</div><!-- .pmpro_card_content -->
		</div><!-- .pmpro_card -->
	</fieldset>
	<?php
}

/**
 * Add nominee details fields after Payment Details section
 */
add_action( 'pmpro_checkout_after_payment_information_fields', 'pmpro_add_nominee_details_fields', 4 );
function pmpro_add_nominee_details_fields() {
	$user_id = get_current_user_id();

	$values = array(
		'nominee_name_1' => $user_id ? get_user_meta( $user_id, 'nominee_name_1', true ) : '',
		'relation_with_nominee_1' => $user_id ? get_user_meta( $user_id, 'relation_with_nominee_1', true ) : '',
		'nominee_1_mobile' => $user_id ? get_user_meta( $user_id, 'nominee_1_mobile', true ) : '',
		'nominee_name_2' => $user_id ? get_user_meta( $user_id, 'nominee_name_2', true ) : '',
		'relation_with_nominee_2' => $user_id ? get_user_meta( $user_id, 'relation_with_nominee_2', true ) : '',
		'nominee_2_mobile' => $user_id ? get_user_meta( $user_id, 'nominee_2_mobile', true ) : '',
	);

	foreach ( $values as $key => $value ) {
		if ( isset( $_REQUEST[ $key ] ) ) {
			$values[ $key ] = sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
		}
	}
	?>
	<fieldset id="pmpro_form_fieldset-nominee-details" class="pmpro_form_fieldset">
		<div class="pmpro_card">
			<div class="pmpro_card_content">

				<legend class="pmpro_form_legend">
					<h2 class="pmpro_form_heading pmpro_font-large">
						<?php esc_html_e( 'Nominee Details', 'pmpro-nbstup' ); ?>
					</h2>
				</legend>

				<div class="pmpro_form_fields">

					<!-- Nominee Name 1 -->
					<div id="nominee_name_1_wrap" class="pmpro_form_field pmpro_form_field-text pmpro_form_field-nominee_name_1">
						<label class="pmpro_form_label" for="nominee_name_1">
							<?php esc_html_e( 'Nominee Name 1', 'pmpro-nbstup' ); ?>
						</label>
						<input
							type="text"
							id="nominee_name_1"
							name="nominee_name_1"
							size="30"
							class="pmpro_form_input pmpro_form_input-text pmpro_form_input-nominee_name_1"
							value="<?php echo esc_attr( $values['nominee_name_1'] ); ?>"
						/>
					</div>

					<!-- Relation With Nominee 1 -->
					<div id="relation_with_nominee_1_wrap" class="pmpro_form_field pmpro_form_field-text pmpro_form_field-relation_with_nominee_1">
						<label class="pmpro_form_label" for="relation_with_nominee_1">
							<?php esc_html_e( 'Relation With Nominee 1', 'pmpro-nbstup' ); ?>
						</label>
						<input
							type="text"
							id="relation_with_nominee_1"
							name="relation_with_nominee_1"
							size="30"
							class="pmpro_form_input pmpro_form_input-text pmpro_form_input-relation_with_nominee_1"
							value="<?php echo esc_attr( $values['relation_with_nominee_1'] ); ?>"
						/>
					</div>

					<!-- Nominee 1 Mobile -->
					<div id="nominee_1_mobile_wrap" class="pmpro_form_field pmpro_form_field-text pmpro_form_field-nominee_1_mobile">
						<label class="pmpro_form_label" for="nominee_1_mobile">
							<?php esc_html_e( 'Nominee 1 Mobile', 'pmpro-nbstup' ); ?>
						</label>
						<input
							type="text"
							id="nominee_1_mobile"
							name="nominee_1_mobile"
							size="20"
							class="pmpro_form_input pmpro_form_input-text pmpro_form_input-nominee_1_mobile"
							value="<?php echo esc_attr( $values['nominee_1_mobile'] ); ?>"
						/>
					</div>

					<!-- Nominee Name 2 -->
					<div id="nominee_name_2_wrap" class="pmpro_form_field pmpro_form_field-text pmpro_form_field-nominee_name_2">
						<label class="pmpro_form_label" for="nominee_name_2">
							<?php esc_html_e( 'Nominee Name 2', 'pmpro-nbstup' ); ?>
						</label>
						<input
							type="text"
							id="nominee_name_2"
							name="nominee_name_2"
							size="30"
							class="pmpro_form_input pmpro_form_input-text pmpro_form_input-nominee_name_2"
							value="<?php echo esc_attr( $values['nominee_name_2'] ); ?>"
						/>
					</div>

					<!-- Relation With Nominee 2 -->
					<div id="relation_with_nominee_2_wrap" class="pmpro_form_field pmpro_form_field-text pmpro_form_field-relation_with_nominee_2">
						<label class="pmpro_form_label" for="relation_with_nominee_2">
							<?php esc_html_e( 'Relation With Nominee 2', 'pmpro-nbstup' ); ?>
						</label>
						<input
							type="text"
							id="relation_with_nominee_2"
							name="relation_with_nominee_2"
							size="30"
							class="pmpro_form_input pmpro_form_input-text pmpro_form_input-relation_with_nominee_2"
							value="<?php echo esc_attr( $values['relation_with_nominee_2'] ); ?>"
						/>
					</div>

					<!-- Nominee 2 Mobile -->
					<div id="nominee_2_mobile_wrap" class="pmpro_form_field pmpro_form_field-text pmpro_form_field-nominee_2_mobile">
						<label class="pmpro_form_label" for="nominee_2_mobile">
							<?php esc_html_e( 'Nominee 2 Mobile', 'pmpro-nbstup' ); ?>
						</label>
						<input
							type="text"
							id="nominee_2_mobile"
							name="nominee_2_mobile"
							size="20"
							class="pmpro_form_input pmpro_form_input-text pmpro_form_input-nominee_2_mobile"
							value="<?php echo esc_attr( $values['nominee_2_mobile'] ); ?>"
						/>
					</div>

				</div><!-- .pmpro_form_fields -->

			</div><!-- .pmpro_card_content -->
		</div><!-- .pmpro_card -->
	</fieldset>
	<?php
}

/**
 * Add address fields before Bank Transfer section
 */
add_action( 'pmpro_checkout_after_payment_information_fields', 'pmpro_add_address_fields', 5 );
function pmpro_add_address_fields() {
	?>
	<fieldset id="pmpro_form_fieldset-address" class="pmpro_form_fieldset">
		<div class="pmpro_card">
			<div class="pmpro_card_content">

				<legend class="pmpro_form_legend">
					<h2 class="pmpro_form_heading pmpro_font-large">
						<?php esc_html_e( 'Address Information', 'pmpro-nbstup' ); ?>
					</h2>
				</legend>

				<div class="pmpro_form_fields">

					<!-- State -->
					<div id="user_state_wrap"
						class="pmpro_form_field pmpro_form_field-select pmpro_form_field-user_state pmpro_form_field-required">

						<label class="pmpro_form_label" for="user_state">
							<?php esc_html_e( 'State', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>

						<select
							id="user_state"
							name="user_state"
							class="pmpro_form_input pmpro_form_input-select pmpro_form_input-user_state pmpro_form_input-required"
							aria-required="true">
							<option value=""><?php esc_html_e( 'Select State', 'pmpro-nbstup' ); ?></option>
							<?php
							$states = pmpro_nbstup_get_all_states();
							foreach ( $states as $state ) {
								echo '<option value="' . esc_attr( $state->id ) . '">' . esc_html( $state->name ) . '</option>';
							}
							?>
						</select>
					</div>

					<!-- District -->
					<div id="user_district_wrap"
						class="pmpro_form_field pmpro_form_field-select pmpro_form_field-user_district pmpro_form_field-required">

						<label class="pmpro_form_label" for="user_district">
							<?php esc_html_e( 'District', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>

						<select
							id="user_district"
							name="user_district"
							class="pmpro_form_input pmpro_form_input-select pmpro_form_input-user_district pmpro_form_input-required"
							aria-required="true"
							disabled>
							<option value=""><?php esc_html_e( 'Select State First', 'pmpro-nbstup' ); ?></option>
						</select>
					</div>

					<!-- Block -->
					<div id="user_block_wrap"
						class="pmpro_form_field pmpro_form_field-select pmpro_form_field-user_block pmpro_form_field-required">

						<label class="pmpro_form_label" for="user_block">
							<?php esc_html_e( 'Block', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>

						<select
							id="user_block"
							name="user_block"
							class="pmpro_form_input pmpro_form_input-select pmpro_form_input-user_block pmpro_form_input-required"
							aria-required="true"
							disabled>
							<option value=""><?php esc_html_e( 'Select District First', 'pmpro-nbstup' ); ?></option>
						</select>
					</div>

					<!-- Address -->
					<div id="user_address_wrap"
						class="pmpro_form_field pmpro_form_field-text pmpro_form_field-user_address pmpro_form_field-required">

						<label class="pmpro_form_label" for="user_address">
							<?php esc_html_e( 'Address', 'pmpro-nbstup' ); ?>
							<span class="pmpro_asterisk">
								<abbr title="<?php esc_attr_e( 'Required Field', 'pmpro-nbstup' ); ?>">*</abbr>
							</span>
						</label>

						<input
							type="text"
							id="user_address"
							name="user_address"
							size="30"
							class="pmpro_form_input pmpro_form_input-text pmpro_form_input-user_address pmpro_form_input-required"
							aria-required="true"
						/>
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
 * Save Address data after checkout
 * (User meta + Order meta)
 */
add_action( 'pmpro_after_checkout', 'pmpro_save_address_data', 10, 2 );
function pmpro_save_address_data( $user_id, $order ) {

	if ( empty( $_POST['gateway'] ) || $_POST['gateway'] !== 'check' ) {
		return;
	}

	// State
	if ( ! empty( $_POST['user_state'] ) ) {
		$state_id = intval( $_POST['user_state'] );
		update_user_meta( $user_id, 'user_state', $state_id );
		update_post_meta( $order->id, 'user_state', $state_id );
	}

	// District
	if ( ! empty( $_POST['user_district'] ) ) {
		$district_id = intval( $_POST['user_district'] );
		update_user_meta( $user_id, 'user_district', $district_id );
		update_post_meta( $order->id, 'user_district', $district_id );
	}

	// Block
	if ( ! empty( $_POST['user_block'] ) ) {
		$block_id = intval( $_POST['user_block'] );
		update_user_meta( $user_id, 'user_block', $block_id );
		update_post_meta( $order->id, 'user_block', $block_id );
	}

	// Address
	if ( ! empty( $_POST['user_address'] ) ) {
		$address = sanitize_textarea_field( $_POST['user_address'] );
		update_user_meta( $user_id, 'user_address', $address );
		update_post_meta( $order->id, 'user_address', $address );
	}
}

/**
 * Save member details fields after checkout
 * (User meta only)
 */
add_action( 'pmpro_after_checkout', 'pmpro_save_member_details_fields', 10, 2 );
function pmpro_save_member_details_fields( $user_id, $order ) {
	if ( empty( $user_id ) ) {
		return;
	}

	$fields = array(
		'name' => 'text',
		'phone_no' => 'text',
		'aadhar_number' => 'text',
		'father_husband_name' => 'text',
		'dob' => 'text',
		'gender' => 'text',
		'Occupation' => 'text',
	);

	foreach ( $fields as $key => $type ) {
		if ( isset( $_POST[ $key ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			if ( $value !== '' ) {
				update_user_meta( $user_id, $key, $value );
			} else {
				delete_user_meta( $user_id, $key );
			}
		}
	}

	$join_blood = ! empty( $_POST['join_blood_donation'] ) ? 1 : 0;
	update_user_meta( $user_id, 'join_blood_donation', $join_blood );

	if ( ! empty( $_POST['member_password'] ) ) {
		$password = wp_unslash( $_POST['member_password'] );
		wp_update_user(
			array(
				'ID' => $user_id,
				'user_pass' => $password,
			)
		);
	}
}

/**
 * Save nominee details fields after checkout
 * (User meta only)
 */
add_action( 'pmpro_after_checkout', 'pmpro_save_nominee_details_fields', 10, 2 );
function pmpro_save_nominee_details_fields( $user_id, $order ) {
	if ( empty( $user_id ) ) {
		return;
	}

	$fields = array(
		'nominee_name_1' => 'text',
		'relation_with_nominee_1' => 'text',
		'nominee_1_mobile' => 'text',
		'nominee_name_2' => 'text',
		'relation_with_nominee_2' => 'text',
		'nominee_2_mobile' => 'text',
	);

	foreach ( $fields as $key => $type ) {
		if ( isset( $_POST[ $key ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			if ( $value !== '' ) {
				update_user_meta( $user_id, $key, $value );
			} else {
				delete_user_meta( $user_id, $key );
			}
		}
	}
}

/**
 * Validate checkout fields
 */
add_filter( 'pmpro_registration_checks', 'pmpro_nbstup_validate_checkout_fields' );
function pmpro_nbstup_validate_checkout_fields( $continue ) {
	global $pmpro_msg, $pmpro_msgt;

	$errors = array();

	$required_fields = array(
		'name' => __( 'Name', 'pmpro-nbstup' ),
		'phone_no' => __( 'Phone Number', 'pmpro-nbstup' ),
		'aadhar_number' => __( 'Aadhar Number', 'pmpro-nbstup' ),
		'father_husband_name' => __( 'Father / Husband Name', 'pmpro-nbstup' ),
		'dob' => __( 'Date of Birth', 'pmpro-nbstup' ),
		'gender' => __( 'Gender', 'pmpro-nbstup' ),
		'Occupation' => __( 'Occupation', 'pmpro-nbstup' ),
		'member_password' => __( 'Password', 'pmpro-nbstup' ),
		'user_state' => __( 'State', 'pmpro-nbstup' ),
		'user_district' => __( 'District', 'pmpro-nbstup' ),
		'user_block' => __( 'Block', 'pmpro-nbstup' ),
		'user_address' => __( 'Address', 'pmpro-nbstup' ),
		'nominee_name_1' => __( 'Nominee Name 1', 'pmpro-nbstup' ),
		'relation_with_nominee_1' => __( 'Relation With Nominee 1', 'pmpro-nbstup' ),
		'nominee_1_mobile' => __( 'Nominee 1 Mobile', 'pmpro-nbstup' ),
		'nominee_name_2' => __( 'Nominee Name 2', 'pmpro-nbstup' ),
		'relation_with_nominee_2' => __( 'Relation With Nominee 2', 'pmpro-nbstup' ),
		'nominee_2_mobile' => __( 'Nominee 2 Mobile', 'pmpro-nbstup' ),
	);

	foreach ( $required_fields as $key => $label ) {
		$value = isset( $_REQUEST[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) ) : '';
		if ( $value === '' ) {
			$errors[] = sprintf( __( '%s is required.', 'pmpro-nbstup' ), $label );
		}
	}

	$phone = isset( $_REQUEST['phone_no'] ) ? preg_replace( '/\s+/', '', wp_unslash( $_REQUEST['phone_no'] ) ) : '';
	if ( $phone !== '' && ! preg_match( '/^\d{10}$/', $phone ) ) {
		$errors[] = __( 'Phone Number must be 10 digits.', 'pmpro-nbstup' );
	}

	$aadhar = isset( $_REQUEST['aadhar_number'] ) ? preg_replace( '/\s+/', '', wp_unslash( $_REQUEST['aadhar_number'] ) ) : '';
	if ( $aadhar !== '' && ! preg_match( '/^\d{12}$/', $aadhar ) ) {
		$errors[] = __( 'Aadhar Number must be 12 digits.', 'pmpro-nbstup' );
	}

	$nominee_1_mobile = isset( $_REQUEST['nominee_1_mobile'] ) ? preg_replace( '/\s+/', '', wp_unslash( $_REQUEST['nominee_1_mobile'] ) ) : '';
	if ( $nominee_1_mobile !== '' && ! preg_match( '/^\d{10}$/', $nominee_1_mobile ) ) {
		$errors[] = __( 'Nominee 1 Mobile must be 10 digits.', 'pmpro-nbstup' );
	}

	$nominee_2_mobile = isset( $_REQUEST['nominee_2_mobile'] ) ? preg_replace( '/\s+/', '', wp_unslash( $_REQUEST['nominee_2_mobile'] ) ) : '';
	if ( $nominee_2_mobile !== '' && ! preg_match( '/^\d{10}$/', $nominee_2_mobile ) ) {
		$errors[] = __( 'Nominee 2 Mobile must be 10 digits.', 'pmpro-nbstup' );
	}

	$dob = isset( $_REQUEST['dob'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['dob'] ) ) : '';
	if ( $dob !== '' ) {
		$date = DateTime::createFromFormat( 'Y-m-d', $dob );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $dob ) {
			$errors[] = __( 'Date of Birth must be a valid date.', 'pmpro-nbstup' );
		}
	}

	$gender = isset( $_REQUEST['gender'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['gender'] ) ) : '';
	$gender_options = array( 'male', 'female', 'other' );
	if ( $gender !== '' && ! in_array( $gender, $gender_options, true ) ) {
		$errors[] = __( 'Please select a valid Gender.', 'pmpro-nbstup' );
	}

	$occupation = isset( $_REQUEST['Occupation'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['Occupation'] ) ) : '';
	$occupation_options = array(
		'Government Job',
		'Private Job',
		'Business',
		'Agriculture',
		'Housewife',
		'Student',
		'Contract Workers',
		'Public Representative',
	);
	if ( $occupation !== '' && ! in_array( $occupation, $occupation_options, true ) ) {
		$errors[] = __( 'Please select a valid Occupation.', 'pmpro-nbstup' );
	}

	$member_password = isset( $_REQUEST['member_password'] ) ? wp_unslash( $_REQUEST['member_password'] ) : '';
	if ( $member_password !== '' && strlen( $member_password ) < 6 ) {
		$errors[] = __( 'Password must be at least 6 characters.', 'pmpro-nbstup' );
	}

	$state_id = isset( $_REQUEST['user_state'] ) ? intval( $_REQUEST['user_state'] ) : 0;
	if ( $state_id <= 0 ) {
		$errors[] = __( 'State is required.', 'pmpro-nbstup' );
	}

	$district_id = isset( $_REQUEST['user_district'] ) ? intval( $_REQUEST['user_district'] ) : 0;
	if ( $district_id <= 0 ) {
		$errors[] = __( 'District is required.', 'pmpro-nbstup' );
	}

	$block_id = isset( $_REQUEST['user_block'] ) ? intval( $_REQUEST['user_block'] ) : 0;
	if ( $block_id <= 0 ) {
		$errors[] = __( 'Block is required.', 'pmpro-nbstup' );
	}

	if ( ! empty( $_REQUEST['gateway'] ) && $_REQUEST['gateway'] === 'check' ) {
		$txn_id = isset( $_REQUEST['bank_transaction_id'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['bank_transaction_id'] ) ) ) : '';
		if ( $txn_id === '' ) {
			$errors[] = __( 'Transaction ID is required.', 'pmpro-nbstup' );
		}

		if ( empty( $_FILES['bank_payment_receipt']['name'] ) ) {
			$errors[] = __( 'Payment Receipt is required.', 'pmpro-nbstup' );
		} elseif ( ! empty( $_FILES['bank_payment_receipt']['error'] ) ) {
			$errors[] = __( 'Payment Receipt upload failed.', 'pmpro-nbstup' );
		}
	}

	if ( ! empty( $errors ) ) {
		$pmpro_msg  = implode( '<br />', array_unique( $errors ) );
		$pmpro_msgt = 'pmpro_error';
		return false;
	}

	return $continue;
}

/**
 * Create dummy email and login using Aadhar number
 */
add_filter( 'pmpro_checkout_new_user_array', 'pmpro_nbstup_set_dummy_email_on_checkout', 10, 2 );
function pmpro_nbstup_set_dummy_email_on_checkout( $user, $order ) {
	$aadhar = isset( $_REQUEST['aadhar_number'] ) ? preg_replace( '/\s+/', '', wp_unslash( $_REQUEST['aadhar_number'] ) ) : '';
	$aadhar = preg_replace( '/\D+/', '', $aadhar );

	if ( empty( $aadhar ) ) {
		return $user;
	}

	$dummy_email = pmpro_nbstup_build_dummy_email( $aadhar );
	if ( $dummy_email ) {
		$user['user_email'] = $dummy_email;
	}

	if ( empty( $user['user_login'] ) ) {
		$user['user_login'] = pmpro_nbstup_unique_login( $aadhar );
	}

	return $user;
}

add_action( 'pmpro_after_checkout', 'pmpro_nbstup_update_dummy_email', 15, 2 );
function pmpro_nbstup_update_dummy_email( $user_id, $order ) {
	if ( empty( $user_id ) ) {
		return;
	}

	$aadhar = get_user_meta( $user_id, 'aadhar_number', true );
	$aadhar = preg_replace( '/\D+/', '', (string) $aadhar );
	if ( empty( $aadhar ) ) {
		return;
	}

	$dummy_email = pmpro_nbstup_build_dummy_email( $aadhar, $user_id );
	if ( empty( $dummy_email ) ) {
		return;
	}

	$current_user = get_userdata( $user_id );
	if ( $current_user && strtolower( $current_user->user_email ) !== strtolower( $dummy_email ) ) {
		wp_update_user(
			array(
				'ID' => $user_id,
				'user_email' => $dummy_email,
			)
		);
	}
}

function pmpro_nbstup_build_dummy_email( $aadhar, $user_id = 0 ) {
	$aadhar = preg_replace( '/\D+/', '', (string) $aadhar );
	if ( empty( $aadhar ) ) {
		return '';
	}

	$email = $aadhar . '@nbstup.com';

	$existing_user_id = email_exists( $email );
	if ( $existing_user_id && (int) $existing_user_id !== (int) $user_id ) {
		$email = $aadhar . '-' . (int) $user_id . '@nbstup.com';
	}

	return is_email( $email ) ? $email : '';
}

function pmpro_nbstup_unique_login( $base_login ) {
	$base_login = sanitize_user( $base_login, true );
	if ( empty( $base_login ) ) {
		$base_login = 'user';
	}

	$login = $base_login;
	$suffix = 1;
	while ( username_exists( $login ) ) {
		$login = $base_login . $suffix;
		$suffix++;
	}

	return $login;
}

add_filter( 'authenticate', 'pmpro_nbstup_authenticate_with_aadhar', 20, 3 );
function pmpro_nbstup_authenticate_with_aadhar( $user, $username, $password ) {
	if ( $user instanceof WP_User ) {
		return $user;
	}

	$username = trim( (string) $username );
	if ( $username === '' || $password === '' ) {
		return $user;
	}

	$aadhar = preg_replace( '/\D+/', '', $username );
	if ( $aadhar === '' ) {
		return $user;
	}

	$users = get_users(
		array(
			'meta_key' => 'aadhar_number',
			'meta_value' => $aadhar,
			'number' => 1,
			'fields' => array( 'ID', 'user_login', 'user_pass' ),
		)
	);

	if ( empty( $users ) ) {
		return $user;
	}

	$found_user = $users[0];
	if ( user_can( $found_user->ID, 'manage_options' ) ) {
		return new WP_Error( 'admin_login_required', __( '<strong>Error</strong>: Please use the admin login.', 'pmpro-nbstup' ) );
	}
	if ( wp_check_password( $password, $found_user->user_pass, $found_user->ID ) ) {
		return new WP_User( $found_user->ID );
	}

	return new WP_Error( 'incorrect_password', __( '<strong>Error</strong>: The password you entered is incorrect.', 'pmpro-nbstup' ) );
}

add_filter( 'authenticate', 'pmpro_nbstup_block_member_username_login', 30, 3 );
function pmpro_nbstup_block_member_username_login( $user, $username, $password ) {
	$username = trim( (string) $username );
	if ( $username === '' || $password === '' ) {
		return $user;
	}

	$is_aadhar_login = preg_match( '/^\d+$/', $username );
	if ( $is_aadhar_login ) {
		return $user;
	}

	if ( $user instanceof WP_User ) {
		if ( in_array( 'subscriber', (array) $user->roles, true ) ) {
			return new WP_Error( 'member_login_required', __( '<strong>Error</strong>: Members must log in using Aadhar number and password.', 'pmpro-nbstup' ) );
		}
		return $user;
	}

	$maybe_user = null;
	if ( is_email( $username ) ) {
		$user_id = email_exists( $username );
		$maybe_user = $user_id ? get_user_by( 'id', $user_id ) : null;
	} else {
		$maybe_user = get_user_by( 'login', $username );
	}

	if ( $maybe_user && in_array( 'subscriber', (array) $maybe_user->roles, true ) ) {
		return new WP_Error( 'member_login_required', __( '<strong>Error</strong>: Members must log in using Aadhar number and password.', 'pmpro-nbstup' ) );
	}

	return $user;
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
 * Show Address details in Member Dashboard (Order History)
 */
add_action( 'pmpro_member_order_details_after', 'pmpro_show_address_to_member', 10, 1 );
function pmpro_show_address_to_member( $order ) {

	$state_id    = get_post_meta( $order->id, 'user_state', true );
	$district_id = get_post_meta( $order->id, 'user_district', true );
	$block_id    = get_post_meta( $order->id, 'user_block', true );
	$address     = get_post_meta( $order->id, 'user_address', true );

	if ( empty( $state_id ) && empty( $address ) ) {
		return;
	}

	$state_name    = $state_id ? pmpro_nbstup_get_state_name( $state_id ) : '';
	$district_name = $district_id ? pmpro_nbstup_get_district_name( $district_id ) : '';
	$block_name    = $block_id ? pmpro_nbstup_get_block_name( $block_id ) : '';
	?>
	<div class="pmpro_box pmpro_box-address">
		<h3><?php esc_html_e( 'Address Information', 'pmpro-nbstup' ); ?></h3>

		<?php if ( $state_name ) : ?>
			<p>
				<strong><?php esc_html_e( 'State:', 'pmpro-nbstup' ); ?></strong>
				<?php echo esc_html( $state_name ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $district_name ) : ?>
			<p>
				<strong><?php esc_html_e( 'District:', 'pmpro-nbstup' ); ?></strong>
				<?php echo esc_html( $district_name ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $block_name ) : ?>
			<p>
				<strong><?php esc_html_e( 'Block:', 'pmpro-nbstup' ); ?></strong>
				<?php echo esc_html( $block_name ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $address ) : ?>
			<p>
				<strong><?php esc_html_e( 'Address:', 'pmpro-nbstup' ); ?></strong>
				<?php echo esc_html( $address ); ?>
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

/**
 * Show Address details in WP-Admin Order screen
 */
add_action( 'pmpro_order_details_after', 'pmpro_show_address_in_admin', 10, 1 );
function pmpro_show_address_in_admin( $order ) {

	$state_id    = get_post_meta( $order->id, 'user_state', true );
	$district_id = get_post_meta( $order->id, 'user_district', true );
	$block_id    = get_post_meta( $order->id, 'user_block', true );
	$address     = get_post_meta( $order->id, 'user_address', true );

	if ( empty( $state_id ) && empty( $address ) ) {
		return;
	}

	$state_name    = $state_id ? pmpro_nbstup_get_state_name( $state_id ) : '';
	$district_name = $district_id ? pmpro_nbstup_get_district_name( $district_id ) : '';
	$block_name    = $block_id ? pmpro_nbstup_get_block_name( $block_id ) : '';
	?>
	<tr>
		<td colspan="2">
			<h3><?php esc_html_e( 'Address Information', 'pmpro-nbstup' ); ?></h3>

			<?php if ( $state_name ) : ?>
				<p>
					<strong><?php esc_html_e( 'State:', 'pmpro-nbstup' ); ?></strong>
					<?php echo esc_html( $state_name ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $district_name ) : ?>
				<p>
					<strong><?php esc_html_e( 'District:', 'pmpro-nbstup' ); ?></strong>
					<?php echo esc_html( $district_name ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $block_name ) : ?>
				<p>
					<strong><?php esc_html_e( 'Block:', 'pmpro-nbstup' ); ?></strong>
					<?php echo esc_html( $block_name ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $address ) : ?>
				<p>
					<strong><?php esc_html_e( 'Address:', 'pmpro-nbstup' ); ?></strong>
					<?php echo esc_html( $address ); ?>
				</p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/**
 * Add custom columns to Members List
 */
add_filter( 'pmpro_members_list_csv_extra_columns', 'pmpro_nbstup_add_members_list_columns' );
add_filter( 'pmpro_memberslist_extra_cols_header', 'pmpro_nbstup_add_members_list_columns' );
function pmpro_nbstup_add_members_list_columns( $columns ) {
	// Ensure $columns is an array
	if ( ! is_array( $columns ) ) {
		$columns = array();
	}
	
	$columns['transaction_id'] = 'Transaction ID';
	$columns['state']          = 'State';
	$columns['district']       = 'District';
	$columns['block']          = 'Block';
	$columns['address']        = 'Address';
	return $columns;
}

/**
 * Display custom column data in Members List
 */
add_filter( 'pmpro_members_list_csv_extra_columns_values', 'pmpro_nbstup_members_list_column_values', 10, 2 );
add_filter( 'pmpro_memberslist_extra_cols_body', 'pmpro_nbstup_members_list_column_values', 10, 2 );
function pmpro_nbstup_members_list_column_values( $values, $user ) {
	// Ensure $values is an array
	if ( ! is_array( $values ) ) {
		$values = array();
	}
	
	// Get user ID
	$user_id = is_object( $user ) ? $user->ID : $user;
	
	// Transaction ID
	$values['transaction_id'] = get_user_meta( $user_id, 'bank_transaction_id', true );
	
	// State
	$state_id = get_user_meta( $user_id, 'user_state', true );
	$values['state'] = $state_id ? pmpro_nbstup_get_state_name( $state_id ) : '';
	
	// District
	$district_id = get_user_meta( $user_id, 'user_district', true );
	$values['district'] = $district_id ? pmpro_nbstup_get_district_name( $district_id ) : '';
	
	// Block
	$block_id = get_user_meta( $user_id, 'user_block', true );
	$values['block'] = $block_id ? pmpro_nbstup_get_block_name( $block_id ) : '';
	
	// Address
	$values['address'] = get_user_meta( $user_id, 'user_address', true );
	
	return $values;
}
