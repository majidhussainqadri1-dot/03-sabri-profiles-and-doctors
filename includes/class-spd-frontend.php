<?php
defined( 'ABSPATH' ) || exit;

class SPD_Frontend {
	public function hooks() {
		add_shortcode( 'sabri_founder_profile', array( $this, 'founder' ) );
		add_shortcode( 'sabri_doctor_directory', array( $this, 'directory' ) );
		add_shortcode( 'sabri_member_profile', array( $this, 'profile' ) );
		add_shortcode( 'sabri_edit_profile', array( $this, 'edit' ) );
		add_action( 'admin_post_spd_save_profile', array( $this, 'save' ) );
		add_action( 'wp_head', array( $this, 'structured_data' ) );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
	}

	public function founder() {
		$f     = SPD_Helpers::founder();
		$pages = (array) get_option( 'spf_page_map', array() );
		$photo = absint( $f['photo_id'] ) ? wp_get_attachment_image_url( absint( $f['photo_id'] ), 'medium' ) : '';
		$cover = absint( $f['cover_id'] ) ? wp_get_attachment_image_url( absint( $f['cover_id'] ), 'large' ) : '';
		ob_start();
		?>
		<main class="spd spd-founder" aria-labelledby="spd-founder-name">
			<section class="spd-hero"<?php echo $cover ? ' style="background-image:url(' . esc_url( $cover ) . ')"' : ''; ?>>
				<div class="spd-avatar spd-avatar--large"><?php echo $photo ? '<img src="' . esc_url( $photo ) . '" alt="' . esc_attr( $f['name'] ) . '">' : esc_html( $this->initials( $f['name'] ) ); ?></div>
				<div class="spd-hero__text"><span class="spd-badge">✓ Verified Founder</span><h1 id="spd-founder-name"><?php echo esc_html( $f['name'] ); ?></h1><p><?php echo esc_html( $f['title'] ); ?></p><p><?php echo esc_html( $f['location'] ); ?></p></div>
			</section>
			<?php echo $this->contact_buttons( $f['phone'], $f['whatsapp'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<nav class="spd-actions" aria-label="Founder actions">
				<?php if ( ! empty( $pages['clinic'] ) ) : ?><a class="spd-btn" href="<?php echo esc_url( get_permalink( $pages['clinic'] ) ); ?>">Book Appointment</a><?php endif; ?>
				<?php if ( ! empty( $pages['learn'] ) ) : ?><a class="spd-btn spd-btn--light" href="<?php echo esc_url( get_permalink( $pages['learn'] ) ); ?>">Learn Sabri Classical Homeopathy</a><?php endif; ?>
			</nav>
			<div class="spd-grid">
				<?php $this->panel( 'Introduction', $f['introduction'] ); ?>
				<?php $this->panel( 'Mission', $f['mission'] ); ?>
				<?php $this->panel( 'Vision', $f['vision'] ); ?>
				<?php $this->panel( 'Objectives', $f['objectives'] ); ?>
				<?php $this->panel( 'Professional Methodology', $f['methodology'] ); ?>
				<?php $this->panel( 'Clinical Experience', $f['experience'] ); ?>
				<?php $this->panel( 'Research & Knowledge Areas', $f['research'] ); ?>
				<?php $this->panel( 'Books & Publications', $f['publications'], true ); ?>
			</div>
			<?php echo $this->latest_posts(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p class="spd-disclaimer">Health information on this profile is educational and is not a substitute for diagnosis, emergency care or advice from a qualified healthcare professional.</p>
		</main>
		<?php
		return ob_get_clean();
	}

	private function panel( $title, $content, $lines = false ) {
		?><section class="spd-card"><h2><?php echo esc_html( $title ); ?></h2><?php if ( $lines ) : ?><ul><?php foreach ( preg_split( '/\r\n|\r|\n/', (string) $content ) as $line ) : if ( trim( $line ) ) : ?><li><?php echo esc_html( trim( $line ) ); ?></li><?php endif; endforeach; ?></ul><?php else : ?><p><?php echo nl2br( esc_html( $content ) ); ?></p><?php endif; ?></section><?php
	}

	private function latest_posts() {
		$founder_id = absint( get_option( 'spf_founder_user_id', 0 ) );
		if ( ! $founder_id ) {
			return '';
		}
		$q = new WP_Query( array( 'author' => $founder_id, 'posts_per_page' => 4, 'post_status' => 'publish', 'no_found_rows' => true ) );
		if ( ! $q->have_posts() ) {
			return '';
		}
		$out = '<section class="spd-latest"><h2>Latest Posts</h2><div class="spd-directory-grid">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$out .= '<article class="spd-card"><h3><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3><p>' . esc_html( wp_trim_words( get_the_excerpt(), 22 ) ) . '</p></article>';
		}
		wp_reset_postdata();
		return $out . '</div></section>';
	}

	public function directory() {
		$country   = isset( $_GET['spd_country'] ) ? sanitize_text_field( wp_unslash( $_GET['spd_country'] ) ) : '';
		$specialty = isset( $_GET['spd_specialty'] ) ? sanitize_text_field( wp_unslash( $_GET['spd_specialty'] ) ) : '';
		$search    = isset( $_GET['spd_search'] ) ? sanitize_text_field( wp_unslash( $_GET['spd_search'] ) ) : '';
		$meta      = array( 'relation' => 'AND', array( 'key' => '_spd_verification_status', 'value' => 'verified' ) );
		if ( $country ) { $meta[] = array( 'key' => '_spd_country', 'value' => $country, 'compare' => 'LIKE' ); }
		if ( $specialty ) { $meta[] = array( 'key' => '_spd_specialty', 'value' => $specialty, 'compare' => 'LIKE' ); }
		$args = array( 'number' => 24, 'role' => 'sabri_doctor_verified', 'orderby' => 'display_name', 'order' => 'ASC', 'meta_query' => $meta );
		if ( $search ) { $args['search'] = '*' . $search . '*'; $args['search_columns'] = array( 'display_name', 'user_nicename' ); }
		$users = ( new WP_User_Query( $args ) )->get_results();
		ob_start();
		?>
		<main class="spd" aria-labelledby="spd-doctors-title"><header class="spd-page-header"><h1 id="spd-doctors-title">Doctors</h1><p>Browse verified professional profiles. Always confirm that a practitioner is appropriately licensed in your location.</p></header>
		<form class="spd-filter" method="get"><label>Search<input type="search" name="spd_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Doctor name"></label><label>Country<input name="spd_country" value="<?php echo esc_attr( $country ); ?>"></label><label>Specialty<input name="spd_specialty" value="<?php echo esc_attr( $specialty ); ?>"></label><button class="spd-btn" type="submit">Filter doctors</button></form>
		<div class="spd-directory-grid">
		<?php if ( $users ) : foreach ( $users as $user ) : ?>
			<?php $photo = absint( SPD_Helpers::get( $user->ID, 'profile_photo_id', 0 ) ); ?>
			<article class="spd-card spd-doctor-card"><div class="spd-avatar"><?php echo $photo ? wp_get_attachment_image( $photo, 'thumbnail', false, array( 'alt' => $user->display_name ) ) : esc_html( $this->initials( $user->display_name ) ); ?></div><div><span class="spd-badge">✓ Verified</span><h2><a href="<?php echo esc_url( SPD_Helpers::profile_url( $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a></h2><p><?php echo esc_html( SPD_Helpers::get( $user->ID, 'specialty', 'Homeopathic practitioner' ) ); ?></p><p><?php echo esc_html( trim( SPD_Helpers::get( $user->ID, 'city' ) . ', ' . SPD_Helpers::get( $user->ID, 'country' ), ', ' ) ); ?></p></div></article>
		<?php endforeach; else : ?><p class="spd-empty">No verified doctor matched these filters yet.</p><?php endif; ?>
		</div><p class="spd-disclaimer">Verification confirms the information reviewed by this platform; it does not guarantee treatment results.</p></main>
		<?php
		return ob_get_clean();
	}

	public function profile() {
		$user = $this->requested_user();
		if ( ! $user ) {
			return '<div class="spd-notice">Profile not found.</div>';
		}
		$is_doctor = SPD_Helpers::is_doctor( $user->ID );
		$status    = SPD_Helpers::verification_status( $user->ID );
		if ( $is_doctor && 'verified' !== $status && get_current_user_id() !== $user->ID && ! current_user_can( 'manage_sabri_doctors' ) ) {
			return '<div class="spd-notice">This professional profile is awaiting review.</div>';
		}
		$photo = absint( SPD_Helpers::get( $user->ID, 'profile_photo_id', 0 ) );
		$cover = absint( SPD_Helpers::get( $user->ID, 'cover_photo_id', 0 ) );
		ob_start(); ?>
		<main class="spd spd-member"><section class="spd-hero"<?php echo $cover ? ' style="background-image:url(' . esc_url( wp_get_attachment_image_url( $cover, 'large' ) ) . ')"' : ''; ?>><div class="spd-avatar spd-avatar--large"><?php echo $photo ? wp_get_attachment_image( $photo, 'medium', false, array( 'alt' => $user->display_name ) ) : esc_html( $this->initials( $user->display_name ) ); ?></div><div class="spd-hero__text"><?php if ( $is_doctor ) : ?><span class="spd-badge spd-badge--<?php echo esc_attr( $status ); ?>"><?php echo 'verified' === $status ? '✓ ' : ''; echo esc_html( SPD_Helpers::status_label( $status ) ); ?></span><?php endif; ?><h1><?php echo esc_html( $user->display_name ); ?></h1><p><?php echo esc_html( ucfirst( SPD_Helpers::get( $user->ID, 'account_type', 'member' ) ) ); ?></p></div></section>
		<?php echo $this->contact_buttons( SPD_Helpers::get( $user->ID, 'phone' ), SPD_Helpers::get( $user->ID, 'whatsapp' ), SPD_Helpers::can_show_contact( $user->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( get_current_user_id() === $user->ID ) : $map = (array) get_option( 'spd_page_map', array() ); ?><p><a class="spd-btn spd-btn--light" href="<?php echo esc_url( ! empty( $map['edit'] ) ? get_permalink( $map['edit'] ) : '#' ); ?>">Edit my profile</a></p><?php endif; ?>
		<div class="spd-grid"><?php foreach ( SPD_Helpers::fields() as $key => $label ) : if ( in_array( $key, array( 'phone', 'whatsapp', 'account_type' ), true ) ) { continue; } $value = SPD_Helpers::get( $user->ID, $key ); if ( $value ) : ?><section class="spd-card"><h2><?php echo esc_html( $label ); ?></h2><p><?php echo nl2br( esc_html( $value ) ); ?></p></section><?php endif; endforeach; ?></div></main>
		<?php return ob_get_clean();
	}

	public function edit() {
		if ( ! is_user_logged_in() ) {
			return '<div class="spd-notice">Please log in to edit your profile.</div>';
		}
		$user = wp_get_current_user();
		ob_start(); ?>
		<main class="spd"><header class="spd-page-header"><h1>Edit Profile</h1><p>Add the first essential details now. More profile features will be added in later files.</p></header>
		<?php if ( isset( $_GET['spd_updated'] ) ) : ?><div class="spd-notice spd-notice--success">Profile saved.</div><?php endif; ?>
		<form class="spd-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
		<input type="hidden" name="action" value="spd_save_profile"><?php wp_nonce_field( 'spd_save_profile', 'spd_nonce' ); ?>
		<label>Display name<input name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>" required></label>
		<?php foreach ( SPD_Helpers::fields() as $key => $label ) : if ( 'account_type' === $key ) { continue; } $value = SPD_Helpers::get( $user->ID, $key ); ?>
		<label><?php echo esc_html( $label ); ?><?php if ( 'bio' === $key || 'studied_books' === $key ) : ?><textarea name="<?php echo esc_attr( $key ); ?>" rows="4"><?php echo esc_textarea( $value ); ?></textarea><?php else : ?><input name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo in_array( $key, array( 'phone', 'whatsapp' ), true ) ? ' inputmode="tel" placeholder="+923001234567"' : ''; ?>><?php endif; ?></label>
		<?php endforeach; ?>
		<label class="spd-check"><input type="checkbox" name="public_contact" value="1" <?php checked( SPD_Helpers::get( $user->ID, 'public_contact', '0' ), '1' ); ?>> Show my phone and WhatsApp publicly. Doctors’ professional contact is public.</label>
		<label>Profile photo (JPG, PNG or WebP; maximum 5 MB)<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label><label>Cover photo (JPG, PNG or WebP; maximum 5 MB)<input type="file" name="cover_photo" accept="image/jpeg,image/png,image/webp"></label>
		<button class="spd-btn" type="submit">Save profile</button></form></main>
		<?php return ob_get_clean();
	}

	public function save() {
		if ( ! is_user_logged_in() || ! isset( $_POST['spd_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spd_nonce'] ) ), 'spd_save_profile' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
		$user_id = get_current_user_id();
		if ( isset( $_POST['display_name'] ) ) { wp_update_user( array( 'ID' => $user_id, 'display_name' => sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) ) ); }
		foreach ( SPD_Helpers::fields() as $key => $label ) {
			if ( 'account_type' === $key || ! isset( $_POST[ $key ] ) ) { continue; }
			$value = in_array( $key, array( 'bio', 'studied_books' ), true ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			if ( in_array( $key, array( 'phone', 'whatsapp' ), true ) ) { $value = SPD_Helpers::clean_phone( $value ); }
			update_user_meta( $user_id, '_spd_' . $key, $value );
		}
		update_user_meta( $user_id, '_spd_public_contact', isset( $_POST['public_contact'] ) ? '1' : '0' );
		$this->upload( $user_id, 'profile_photo', 'profile_photo_id' );
		$this->upload( $user_id, 'cover_photo', 'cover_photo_id' );
		$map = (array) get_option( 'spd_page_map', array() );
		$url = ! empty( $map['edit'] ) ? get_permalink( $map['edit'] ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'spd_updated', '1', $url ) ); exit;
	}

	private function upload( $user_id, $field, $meta_key ) {
		if ( empty( $_FILES[ $field ]['name'] ) ) { return; }
		$file = $_FILES[ $field ];
		if ( ! empty( $file['error'] ) || (int) $file['size'] > 5 * MB_IN_BYTES ) { return; }
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) );
		if ( empty( $checked['type'] ) ) { return; }
		require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_upload( $field, 0, array( 'post_author' => $user_id ), array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) );
		if ( ! is_wp_error( $id ) ) { update_user_meta( $user_id, '_spd_' . $meta_key, absint( $id ) ); }
	}

	private function contact_buttons( $phone, $whatsapp, $public ) {
		if ( ! $public ) { return '<div class="spd-actions"><span class="spd-btn spd-btn--disabled" aria-disabled="true">Call — Private</span><span class="spd-btn spd-btn--disabled" aria-disabled="true">WhatsApp — Private</span></div>'; }
		$out = '<div class="spd-actions">';
		$out .= $phone ? '<a class="spd-btn" href="tel:' . esc_attr( SPD_Helpers::clean_phone( $phone ) ) . '">Call</a>' : '<span class="spd-btn spd-btn--disabled" aria-disabled="true">Call — Not added</span>';
		$wa = SPD_Helpers::whatsapp_url( $whatsapp );
		$out .= $wa ? '<a class="spd-btn spd-btn--whatsapp" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener noreferrer">WhatsApp</a>' : '<span class="spd-btn spd-btn--disabled" aria-disabled="true">WhatsApp — Not added</span>';
		return $out . '</div>';
	}

	private function requested_user() {
		$key = isset( $_GET['user'] ) ? sanitize_title( wp_unslash( $_GET['user'] ) ) : '';
		if ( $key ) { return get_user_by( 'slug', $key ); }
		return is_user_logged_in() ? wp_get_current_user() : false;
	}

	private function initials( $name ) {
		$parts = preg_split( '/\s+/', trim( $name ) ); $out = '';
		foreach ( array_slice( $parts, 0, 2 ) as $part ) { $out .= function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 ); }
		return strtoupper( $out );
	}

	public function structured_data() {
		if ( ! is_singular( 'page' ) ) { return; }
		$map = (array) get_option( 'spd_page_map', array() );
		if ( ! empty( $map['founder'] ) && (int) get_queried_object_id() === (int) $map['founder'] ) {
			$f = SPD_Helpers::founder(); $data = array( '@context' => 'https://schema.org', '@type' => 'Person', 'name' => $f['name'], 'jobTitle' => $f['title'], 'url' => get_permalink( $map['founder'] ) );
			echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
		}
	}

	public function robots( $robots ) {
		$map = (array) get_option( 'spd_page_map', array() );
		if ( ! empty( $map['profile'] ) && is_page( $map['profile'] ) ) { $user = $this->requested_user(); if ( $user && SPD_Helpers::is_doctor( $user->ID ) && 'verified' !== SPD_Helpers::verification_status( $user->ID ) ) { $robots['noindex'] = true; } }
		return $robots;
	}
}
