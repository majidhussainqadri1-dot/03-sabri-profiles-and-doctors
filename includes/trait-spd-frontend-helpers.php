<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Frontend_Helpers {
	private function cleanup_prepared_media( array $prepared_media, $user_id ) {
		foreach ( $prepared_media as $purpose => $prepared ) {
			if ( ! empty( $prepared['attachment_id'] ) ) {
				SPD_Media::delete_owned( $prepared['attachment_id'], $user_id, $purpose );
			}
		}
	}

	private function error_status( WP_Error $error ) {
		$data = $error->get_error_data();
		return is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 400;
	}

	private function contact_actions( array $dto ) {
		$contacts = $dto['contacts'];
		if ( ! $contacts ) {
			return '<div class="spd-actions"><span class="spd-btn spd-btn--disabled" aria-disabled="true">' . esc_html__( 'Contact details are private', 'sabri-profiles-doctors' ) . '</span></div>';
		}
		$out = '<div class="spd-actions" aria-label="' . esc_attr__( 'Contact actions', 'sabri-profiles-doctors' ) . '">';
		if ( ! empty( $contacts['phone'] ) ) {
			$out .= '<a class="spd-btn" href="tel:' . esc_attr( SPD_Helpers::clean_phone( $contacts['phone'] ) ) . '">' . esc_html__( 'Call', 'sabri-profiles-doctors' ) . '</a>';
		}
		if ( ! empty( $contacts['email'] ) ) {
			$out .= '<a class="spd-btn" href="mailto:' . esc_attr( antispambot( $contacts['email'] ) ) . '">' . esc_html__( 'Email', 'sabri-profiles-doctors' ) . '</a>';
		}
		if ( ! empty( $contacts['whatsapp'] ) ) {
			$out .= '<a class="spd-btn" href="' . esc_url( SPD_Helpers::whatsapp_url( $contacts['whatsapp'] ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'WhatsApp', 'sabri-profiles-doctors' ) . '</a>';
		}
		if ( ! empty( $contacts['internal_message_url'] ) ) {
			$out .= '<a class="spd-btn" href="' . esc_url( $contacts['internal_message_url'] ) . '">' . esc_html__( 'Message', 'sabri-profiles-doctors' ) . '</a>';
		}
		return $out . '</div>';
	}

	private function profile_subtitle( array $dto ) {
		if ( 'founder' === $dto['profile_type'] ) {
			return $dto['founder']['professional_title'] ?? __( 'Founder — Sabri Social Homeopathy Platform', 'sabri-profiles-doctors' );
		}
		if ( 'doctor' === $dto['profile_type'] ) {
			return $dto['professional']['professional_title'] ?? __( 'Homeopathic Doctor', 'sabri-profiles-doctors' );
		}
		return __( 'Member', 'sabri-profiles-doctors' );
	}

	private function field_labels() {
		return array( 'bio' => __( 'Introduction', 'sabri-profiles-doctors' ), 'country' => __( 'Country', 'sabri-profiles-doctors' ), 'city' => __( 'City', 'sabri-profiles-doctors' ), 'languages' => __( 'Languages', 'sabri-profiles-doctors' ), 'studied_books' => __( 'Classical Books Studied', 'sabri-profiles-doctors' ) );
	}

	private function professional_labels() {
		return array( 'qualification' => __( 'Qualification', 'sabri-profiles-doctors' ), 'licence_number' => __( 'Licence / Registration', 'sabri-profiles-doctors' ), 'licensing_authority' => __( 'Licensing Authority', 'sabri-profiles-doctors' ), 'experience_years' => __( 'Experience', 'sabri-profiles-doctors' ), 'specialty' => __( 'Specialty', 'sabri-profiles-doctors' ), 'consultation_modes' => __( 'Consultation Modes', 'sabri-profiles-doctors' ) );
	}

	private function founder_labels() {
		return array( 'professional_title' => __( 'Professional Title', 'sabri-profiles-doctors' ), 'mission' => __( 'Mission', 'sabri-profiles-doctors' ), 'vision' => __( 'Vision', 'sabri-profiles-doctors' ), 'objectives' => __( 'Objectives', 'sabri-profiles-doctors' ), 'methodology' => __( 'Professional Methodology', 'sabri-profiles-doctors' ), 'experience' => __( 'Clinical Experience', 'sabri-profiles-doctors' ), 'research' => __( 'Research and Knowledge Areas', 'sabri-profiles-doctors' ), 'publications' => __( 'Books and Publications', 'sabri-profiles-doctors' ), 'institutional_links' => __( 'Institutional Links', 'sabri-profiles-doctors' ) );
	}

	private function notice( $message, $type = 'info' ) {
		return '<div class="spd spd-notice spd-notice--' . esc_attr( sanitize_key( $type ) ) . '" role="status">' . esc_html( $message ) . '</div>';
	}

	private function text_with_audience( $key, $label, $value, $audience ) {
		return '<div class="spd-field-row"><label>' . esc_html( $label ) . '<input name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" maxlength="255"></label><label>' . esc_html__( 'Audience', 'sabri-profiles-doctors' ) . $this->audience_select( $key, $audience ) . '</label></div>';
	}

	private function textarea_with_audience( $key, $label, $value, $audience, $maxlength ) {
		return '<div class="spd-field-row"><label>' . esc_html( $label ) . '<textarea name="' . esc_attr( $key ) . '" rows="5" maxlength="' . absint( $maxlength ) . '">' . esc_textarea( $value ) . '</textarea></label><label>' . esc_html__( 'Audience', 'sabri-profiles-doctors' ) . $this->audience_select( $key, $audience ) . '</label></div>';
	}

	private function audience_select( $key, $selected ) {
		$options = array( 'public' => __( 'Public', 'sabri-profiles-doctors' ), 'members' => __( 'Members', 'sabri-profiles-doctors' ), 'contacts' => __( 'Contacts', 'sabri-profiles-doctors' ), 'private' => __( 'Private', 'sabri-profiles-doctors' ) );
		$html = '<select name="audience[' . esc_attr( $key ) . ']">';
		foreach ( $options as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html . '</select>';
	}
}
