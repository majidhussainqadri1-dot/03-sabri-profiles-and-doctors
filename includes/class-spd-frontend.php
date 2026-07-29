<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Frontend {
	public function hooks() {
		add_shortcode( 'sabri_founder_profile', array( $this, 'founder' ) );
		add_shortcode( 'sabri_doctor_directory', array( $this, 'directory' ) );
		add_shortcode( 'sabri_member_profile', array( $this, 'profile' ) );
		add_shortcode( 'sabri_edit_profile', array( $this, 'edit' ) );
		add_action( 'admin_post_spd_save_profile_projection', array( $this, 'save' ) );
		add_action( 'admin_post_nopriv_spd_save_profile_projection', array( $this, 'reject_anonymous_save' ) );
		add_action( 'template_redirect', array( $this, 'private_headers' ), 0 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'wp_head', array( $this, 'structured_data' ) );
	}

	public function founder() {
		$id = SPD_Membership_Adapter::founder_id();
		if ( ! $id ) {
			return '<div class="spd-notice">The canonical Founder identity is not configured.</div>';
		}
		$f     = SPD_Helpers::founder();
		$photo = $f['photo_id'] ? wp_get_attachment_image_url( $f['photo_id'], 'medium' ) : '';
		$cover = $f['cover_id'] ? wp_get_attachment_image_url( $f['cover_id'], 'large' ) : '';
		ob_start(); ?>
		<main class="spd spd-founder" aria-labelledby="spd-founder-name"><section class="spd-hero"<?php echo $cover ? ' style="background-image:url(' . esc_url( $cover ) . ')"' : ''; ?>><div class="spd-avatar spd-avatar--large"><?php echo $photo ? '<img src="' . esc_url( $photo ) . '" alt="' . esc_attr( $f['name'] ) . '">' : esc_html( $this->initials( $f['name'] ) ); ?></div><div class="spd-hero__text"><span class="spd-badge">✓ Official Founder</span><h1 id="spd-founder-name"><?php echo esc_html( $f['name'] ); ?></h1><p><?php echo esc_html( $f['title'] ); ?></p><p><?php echo esc_html( $f['location'] ); ?></p></div></section>
		<?php echo $this->contact_buttons( $id, $f['phone'], $f['whatsapp'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="spd-grid"><?php foreach ( array( 'introduction'=>'Introduction','mission'=>'Mission','vision'=>'Vision','objectives'=>'Objectives','methodology'=>'Professional Methodology','experience'=>'Clinical Experience','research'=>'Research & Knowledge Areas','publications'=>'Books & Publications' ) as $key=>$label ) : if ( trim( (string) $f[ $key ] ) ) : ?><section class="spd-card"><h2><?php echo esc_html( $label ); ?></h2><p><?php echo nl2br( esc_html( $f[ $key ] ) ); ?></p></section><?php endif; endforeach; ?></div>
		<p class="spd-disclaimer">Emergency cases are not accepted online. Contact the nearest appropriate emergency service, hospital, or local clinician.</p></main>
		<?php return ob_get_clean();
	}

	public function directory() {
		$country   = isset( $_GET['spd_country'] ) ? sanitize_text_field( wp_unslash( $_GET['spd_country'] ) ) : '';
		$specialty = isset( $_GET['spd_specialty'] ) ? sanitize_text_field( wp_unslash( $_GET['spd_specialty'] ) ) : '';
		$search    = isset( $_GET['spd_search'] ) ? sanitize_text_field( wp_unslash( $_GET['spd_search'] ) ) : '';
		$ids       = get_users( array( 'fields'=>'ids', 'number'=>500, 'meta_key'=>'_smc_doctor_verified', 'meta_value'=>'1' ) );
		$doctors   = array();
		foreach ( $ids as $id ) {
			if ( ! SPD_Verification_Adapter::directory_eligible( $id ) ) { continue; }
			$data = SPD_Verification_Adapter::approved_fields( $id );
			$haystack = strtolower( implode( ' ', array( $data['display_name'] ?? '', $data['country'] ?? '', $data['city'] ?? '', $data['specialty'] ?? '' ) ) );
			if ( $search && false === strpos( $haystack, strtolower( $search ) ) ) { continue; }
			if ( $country && false === stripos( (string) ( $data['country'] ?? '' ), $country ) ) { continue; }
			if ( $specialty && false === stripos( (string) ( $data['specialty'] ?? '' ), $specialty ) ) { continue; }
			$doctors[] = array( 'id'=>$id, 'data'=>$data );
		}
		usort( $doctors, static function( $a, $b ) { return strcasecmp( $a['data']['display_name'] ?? '', $b['data']['display_name'] ?? '' ); } );
		ob_start(); ?>
		<main class="spd" aria-labelledby="spd-doctors-title"><header class="spd-page-header"><h1 id="spd-doctors-title">Doctors</h1><p>Only profiles approved by File 00 and File 09, with an unchanged approved snapshot and public visibility, appear here.</p></header>
		<form class="spd-filter" method="get"><label>Search<input type="search" name="spd_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Doctor name"></label><label>Country<input name="spd_country" value="<?php echo esc_attr( $country ); ?>"></label><label>Specialty<input name="spd_specialty" value="<?php echo esc_attr( $specialty ); ?>"></label><button class="spd-btn" type="submit">Filter doctors</button></form><div class="spd-directory-grid">
		<?php if ( $doctors ) : foreach ( array_slice( $doctors, 0, 100 ) as $doctor ) : $d=$doctor['data']; $id=$doctor['id']; $photo=absint($d['profile_photo_id']??0); ?><article class="spd-card spd-doctor-card"><div class="spd-avatar"><?php echo $photo ? wp_get_attachment_image( $photo, 'thumbnail', false, array( 'alt'=>$d['display_name']??'' ) ) : esc_html( $this->initials( $d['display_name']??'' ) ); ?></div><div><span class="spd-badge">✓ Verified</span><h2><a href="<?php echo esc_url( SPD_Helpers::profile_url( $id ) ); ?>"><?php echo esc_html( $d['display_name']??'' ); ?></a></h2><p><?php echo esc_html( $d['specialty']??'Homeopathic practitioner' ); ?></p><p><?php echo esc_html( trim( ($d['city']??'') . ', ' . ($d['country']??''), ', ' ) ); ?></p></div></article><?php endforeach; else : ?><p class="spd-empty">No verified public doctor matched these filters.</p><?php endif; ?></div><p class="spd-disclaimer">Verification records reviewed evidence; it does not guarantee treatment results.</p></main>
		<?php return ob_get_clean();
	}

	public function profile() {
		$user = $this->requested_user();
		if ( ! $user || ! $this->can_view( $user->ID ) ) {
			return '<div class="spd-notice">This profile is private or unavailable.</div>';
		}
		$is_doctor = SPD_Membership_Adapter::is_doctor( $user->ID );
		$is_owner  = get_current_user_id() === $user->ID;
		$data      = $is_doctor && SPD_Verification_Adapter::approved_fields( $user->ID ) ? SPD_Verification_Adapter::approved_fields( $user->ID ) : array(
			'display_name'=>$user->display_name, 'country'=>SPD_Helpers::get($user->ID,'country'), 'city'=>SPD_Helpers::get($user->ID,'city'),
			'bio'=>SPD_Helpers::get($user->ID,'bio'), 'profile_photo_id'=>absint(get_user_meta($user->ID,'_spd_profile_photo_id',true)), 'cover_photo_id'=>absint(get_user_meta($user->ID,'_spd_cover_photo_id',true)),
		);
		$status = $is_doctor ? SPD_Verification_Adapter::status( $user->ID ) : '';
		$photo  = absint( $data['profile_photo_id'] ?? 0 );
		$cover  = absint( $data['cover_photo_id'] ?? 0 );
		ob_start(); ?>
		<main class="spd spd-member"><section class="spd-hero"<?php echo $cover ? ' style="background-image:url(' . esc_url( wp_get_attachment_image_url( $cover, 'large' ) ) . ')"' : ''; ?>><div class="spd-avatar spd-avatar--large"><?php echo $photo ? wp_get_attachment_image( $photo, 'medium', false, array( 'alt'=>$data['display_name']??'' ) ) : esc_html( $this->initials( $data['display_name']??'' ) ); ?></div><div class="spd-hero__text"><?php if ( $is_doctor ) : ?><span class="spd-badge spd-badge--<?php echo esc_attr( $status ); ?>"><?php echo 'verified' === $status ? '✓ ' : ''; echo esc_html( SPD_Helpers::status_label( $status ) ); ?></span><?php endif; ?><h1><?php echo esc_html( $data['display_name']??'' ); ?></h1><p><?php echo esc_html( $is_doctor ? 'Homeopathic Doctor' : ucfirst( SPD_Helpers::get( $user->ID, 'account_type', 'member' ) ) ); ?></p></div></section>
		<?php echo $this->contact_buttons( $user->ID, $data['phone']??SPD_Helpers::get($user->ID,'phone'), $data['whatsapp']??SPD_Helpers::get($user->ID,'whatsapp') ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( $is_owner ) : $map=(array)get_option('spd_page_map',array()); ?><p><a class="spd-btn spd-btn--light" href="<?php echo esc_url( !empty($map['edit'])?get_permalink($map['edit']):'#' ); ?>">Edit profile presentation</a></p><?php if ( 'changes_pending' === $status ) : ?><div class="spd-notice">Professional or media changes are awaiting File 09 re-review. Public listing is suspended until approval.</div><?php endif; endif; ?>
		<div class="spd-grid"><?php foreach ( array('qualification'=>'Qualification','licence_number'=>'Licence / Registration','licensing_authority'=>'Licensing Authority','experience_years'=>'Experience','specialty'=>'Specialty','languages'=>'Languages','studied_books'=>'Classical Books Studied','consultation_modes'=>'Consultation Modes','clinic'=>'Clinic','city'=>'City','country'=>'Country','bio'=>'Introduction') as $key=>$label ) : if ( !empty($data[$key]) ) : ?><section class="spd-card"><h2><?php echo esc_html($label); ?></h2><p><?php echo nl2br(esc_html($data[$key])); ?></p></section><?php endif; endforeach; ?></div></main>
		<?php return ob_get_clean();
	}

	public function edit() {
		if ( ! is_user_logged_in() ) { return '<div class="spd-notice">Please log in to edit profile presentation.</div>'; }
		$id = get_current_user_id();
		$visibility = SPD_Membership_Adapter::public_visibility( $id );
		ob_start(); ?>
		<main class="spd"><header class="spd-page-header"><h1>Edit Profile Presentation</h1><p>Identity, role, credentials, professional details, and verification are managed by Files 00 and 09. This page controls only visibility, public contact consent, and presentation images.</p></header>
		<?php if ( isset($_GET['spd_updated']) ) : ?><div class="spd-notice spd-notice--success">Profile presentation saved.</div><?php endif; ?>
		<form class="spd-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="spd_save_profile_projection"><?php wp_nonce_field('spd_save_profile_projection','spd_nonce'); ?>
		<label>Profile visibility<select name="profile_visibility"><option value="private" <?php selected($visibility,'private'); ?>>Private</option><option value="members" <?php selected($visibility,'members'); ?>>Members only</option><option value="public" <?php selected($visibility,'public'); ?>>Public</option></select></label>
		<label class="spd-check"><input type="checkbox" name="public_contact" value="1" <?php checked(get_user_meta($id,'_spd_public_contact',true),'1'); ?>> Show my canonical phone and WhatsApp on viewers who may see this profile.</label>
		<label>Profile image (JPG, PNG or WebP; maximum 5 MB)<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label><label>Cover image (JPG, PNG or WebP; maximum 5 MB)<input type="file" name="cover_photo" accept="image/jpeg,image/png,image/webp"></label>
		<button class="spd-btn" type="submit">Save presentation</button></form></main>
		<?php return ob_get_clean();
	}

	public function save() {
		if ( ! is_user_logged_in() || ! isset($_POST['spd_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spd_nonce'])),'spd_save_profile_projection') ) {
			wp_die( esc_html__('Security check failed.','sabri-profiles-doctors'),'',array('response'=>403) );
		}
		$id = get_current_user_id();
		$visibility = isset($_POST['profile_visibility']) ? sanitize_key(wp_unslash($_POST['profile_visibility'])) : 'private';
		if ( ! in_array($visibility,array('private','members','public'),true) ) { $visibility='private'; }
		update_user_meta($id,'_spd_profile_visibility',$visibility);
		update_user_meta($id,'_spd_public_contact',isset($_POST['public_contact'])?'1':'0');
		$this->save_image($id,'profile_photo','profile_photo_id','profile');
		$this->save_image($id,'cover_photo','cover_photo_id','cover');
		SPD_Helpers::audit($id,'profile_projection','updated','Profile visibility, contact consent, or presentation media updated.');
		$map=(array)get_option('spd_page_map',array()); $url=!empty($map['edit'])?get_permalink($map['edit']):home_url('/');
		wp_safe_redirect(add_query_arg('spd_updated','1',$url)); exit;
	}

	public function reject_anonymous_save() { wp_die( esc_html__('Log in to update a profile.','sabri-profiles-doctors'),'',array('response'=>403) ); }

	private function save_image($id,$field,$meta,$purpose) {
		$result=SPD_Media::upload($id,$field,$purpose);
		if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()),'',array('response'=>400,'back_link'=>true));}
		if($result){SPD_Media::replace($id,$meta,$result,$purpose);}
	}

	private function requested_user() {
		$key=isset($_GET['user'])?sanitize_title(wp_unslash($_GET['user'])):'';
		return $key?get_user_by('slug',$key):(is_user_logged_in()?wp_get_current_user():false);
	}

	private function can_view($user_id) {
		if(get_current_user_id()===absint($user_id)||current_user_can('smc_manage_membership')){return true;}
		if(SPD_Membership_Adapter::is_founder($user_id)){return true;}
		if(SPD_Membership_Adapter::is_doctor($user_id)){return SPD_Verification_Adapter::directory_eligible($user_id);}
		$visibility=SPD_Membership_Adapter::public_visibility($user_id);
		return 'public'===$visibility||('members'===$visibility&&is_user_logged_in());
	}

	private function contact_buttons($user_id,$phone,$whatsapp) {
		if('1'!==(string)get_user_meta(absint($user_id),'_spd_public_contact',true)){return '<div class="spd-actions"><span class="spd-btn spd-btn--disabled" aria-disabled="true">Phone private</span><span class="spd-btn spd-btn--disabled" aria-disabled="true">WhatsApp private</span></div>';}
		$out='<div class="spd-actions">';
		$out.=$phone?'<a class="spd-btn" href="tel:'.esc_attr(SPD_Helpers::clean_phone($phone)).'">Call</a>':'<span class="spd-btn spd-btn--disabled" aria-disabled="true">Phone unavailable</span>';
		$url=SPD_Helpers::whatsapp_url($whatsapp); $out.=$url?'<a class="spd-btn spd-btn--whatsapp" href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">WhatsApp</a>':'<span class="spd-btn spd-btn--disabled" aria-disabled="true">WhatsApp unavailable</span>';
		return $out.'</div>';
	}

	private function initials($name){$parts=preg_split('/\s+/',trim((string)$name));$out='';foreach(array_slice($parts,0,2)as$part){$out.=function_exists('mb_substr')?mb_substr($part,0,1):substr($part,0,1);}return strtoupper($out);}

	private function is_private_request() {
		$map=(array)get_option('spd_page_map',array());
		if(!empty($map['edit'])&&is_page(absint($map['edit']))){return true;}
		if(!empty($map['profile'])&&is_page(absint($map['profile']))){$u=$this->requested_user();return !$u||!$this->can_view($u->ID)||get_current_user_id()===$u->ID;}
		return false;
	}

	public function robots($robots) {
		if($this->is_private_request()){$robots['noindex']=true;$robots['nofollow']=true;$robots['noarchive']=true;}
		return $robots;
	}

	public function private_headers() {
		if(!$this->is_private_request()){return;}
		nocache_headers();
		header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0',true);
		header('Pragma: no-cache',true);
		header('X-Robots-Tag: noindex, nofollow, noarchive',true);
		header('Referrer-Policy: no-referrer',true);
		header('X-Frame-Options: SAMEORIGIN',true);
		header('X-Content-Type-Options: nosniff',true);
		header('Permissions-Policy: camera=(), microphone=(), geolocation=()',true);
	}

	public function structured_data() {
		$map=(array)get_option('spd_page_map',array());
		if(!empty($map['founder'])&&is_page(absint($map['founder']))){$f=SPD_Helpers::founder();if($f['name']){echo '<script type="application/ld+json">'.wp_json_encode(array('@context'=>'https://schema.org','@type'=>'Person','name'=>$f['name'],'jobTitle'=>$f['title'],'url'=>get_permalink(absint($map['founder']))),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';}}
	}
}
