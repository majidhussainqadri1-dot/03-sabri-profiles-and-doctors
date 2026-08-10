<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Frontend_Future {
	public function future_profile_sections( array $dto, $is_owner = false ) {
		$f = (array) ( $dto['future'] ?? array() );
		if ( ! $f ) { return ''; }
		$lifecycle = (array) ( $f['lifecycle'] ?? array() );
		ob_start();
		?>
		<div class="spd-future-profile" aria-label="<?php esc_attr_e( 'Professional identity and knowledge profile', 'sabri-profiles-doctors' ); ?>">
		<?php if ( ! empty( $lifecycle['status'] ) && 'active' !== $lifecycle['status'] ) : ?>
			<section class="spd-card spd-future-lifecycle"><h2><?php esc_html_e( 'Professional Status', 'sabri-profiles-doctors' ); ?></h2><p><strong><?php echo esc_html( ucfirst( $lifecycle['status'] ) ); ?></strong></p><?php if ( ! empty( $lifecycle['reason'] ) ) : ?><p><?php echo esc_html( $lifecycle['reason'] ); ?></p><?php endif; ?><p class="spd-meta"><?php esc_html_e( 'Appointments and direct contact are disabled for non-active professional states.', 'sabri-profiles-doctors' ); ?></p></section>
		<?php endif; ?>

		<?php $wallet = (array) ( $f['credential_wallet']['items'] ?? array() ); if ( $wallet ) : ?>
			<section class="spd-card"><h2><?php esc_html_e( 'Portable Verified Credentials', 'sabri-profiles-doctors' ); ?></h2><ul><?php foreach ( $wallet as $item ) : ?><li><strong><?php echo esc_html( $item['name'] ?: $item['type'] ); ?></strong><?php echo ! empty( $item['issuer'] ) ? ' — ' . esc_html( $item['issuer'] ) : ''; ?><?php if ( ! empty( $item['verification_url'] ) ) : ?> · <a href="<?php echo esc_url( $item['verification_url'] ); ?>" rel="noopener noreferrer"><?php esc_html_e( 'Verify', 'sabri-profiles-doctors' ); ?></a><?php endif; ?></li><?php endforeach; ?></ul><p class="spd-meta"><?php esc_html_e( 'Only current provider-verified credentials are projected; private evidence is never exposed.', 'sabri-profiles-doctors' ); ?></p></section>
		<?php endif; ?>

		<?php $passport = (array) ( $f['learning_passport']['items'] ?? array() ); if ( $passport ) : ?>
			<section class="spd-card"><h2><?php esc_html_e( 'Verified Learning & Achievement Passport', 'sabri-profiles-doctors' ); ?></h2><ul><?php foreach ( $passport as $item ) : ?><li><strong><?php echo esc_html( $item['title'] ); ?></strong><?php echo ! empty( $item['issuer'] ) ? ' — ' . esc_html( $item['issuer'] ) : ''; ?><?php if ( ! empty( $item['credential_url'] ) ) : ?> · <a href="<?php echo esc_url( $item['credential_url'] ); ?>" rel="noopener noreferrer"><?php esc_html_e( 'Credential', 'sabri-profiles-doctors' ); ?></a><?php endif; ?></li><?php endforeach; ?></ul></section>
		<?php endif; ?>

		<?php $trust = (array) ( $f['trust_timeline'] ?? array() ); if ( $trust ) : ?>
			<section class="spd-card"><h2><?php esc_html_e( 'Professional Trust Timeline', 'sabri-profiles-doctors' ); ?></h2><ol class="spd-trust-timeline"><?php foreach ( $trust as $item ) : ?><li><strong><?php echo esc_html( $item['label'] ?: ucwords( str_replace( '_', ' ', $item['type'] ) ) ); ?></strong><?php echo ! empty( $item['occurred_at'] ) ? ' — ' . esc_html( $item['occurred_at'] ) : ''; ?></li><?php endforeach; ?></ol></section>
		<?php endif; ?>

		<?php $expertise = (array) ( $f['expertise_evidence'] ?? array() ); if ( $expertise ) : ?>
			<section class="spd-card"><h2><?php esc_html_e( 'Evidence-Backed Expertise', 'sabri-profiles-doctors' ); ?></h2><?php foreach ( $expertise as $topic ) : ?><article class="spd-evidence-topic"><h3><?php echo esc_html( $topic['topic'] ); ?></h3><ul><?php foreach ( (array) $topic['evidence'] as $e ) : ?><li><?php if ( ! empty( $e['url'] ) ) : ?><a href="<?php echo esc_url( $e['url'] ); ?>"><?php echo esc_html( $e['label'] ); ?></a><?php else : ?><?php echo esc_html( $e['label'] ); ?><?php endif; ?> <span class="spd-meta"><?php echo esc_html( $e['type'] ); ?></span></li><?php endforeach; ?></ul></article><?php endforeach; ?><p class="spd-meta"><?php esc_html_e( 'Evidence categories are informational and are not a treatment-outcome ranking.', 'sabri-profiles-doctors' ); ?></p></section>
		<?php endif; ?>

		<?php $coverage = (array) ( $f['knowledge_coverage']['categories'] ?? array() ); if ( $coverage ) : ?>
			<section class="spd-card"><h2><?php esc_html_e( 'Knowledge Coverage Map', 'sabri-profiles-doctors' ); ?></h2><dl class="spd-stats"><?php foreach ( $coverage as $item ) : ?><div><dt><?php echo esc_html( $item['category'] ); ?></dt><dd><?php echo esc_html( number_format_i18n( $item['evidence_count'] ) ); ?></dd></div><?php endforeach; ?></dl><p class="spd-meta"><?php esc_html_e( 'These are transparent evidence counts, not an opaque prestige score or paid ranking.', 'sabri-profiles-doctors' ); ?></p></section>
		<?php endif; ?>

		<?php $graph = (array) ( $f['knowledge_graph'] ?? array() ); if ( ! empty( $graph['nodes'] ) ) : ?>
			<section class="spd-card"><h2><?php esc_html_e( 'Professional Knowledge Graph', 'sabri-profiles-doctors' ); ?></h2><p><?php echo esc_html( sprintf( __( '%1$d verified public nodes and %2$d relationships connect this profile with publications, learning, research and institutions.', 'sabri-profiles-doctors' ), count( $graph['nodes'] ), count( (array) ( $graph['edges'] ?? array() ) ) ) ); ?></p><ul><?php foreach ( array_slice( $graph['nodes'], 0, 12 ) as $node ) : ?><li><?php if ( ! empty( $node['url'] ) ) : ?><a href="<?php echo esc_url( $node['url'] ); ?>"><?php echo esc_html( $node['label'] ); ?></a><?php else : ?><?php echo esc_html( $node['label'] ); ?><?php endif; ?> <span class="spd-meta"><?php echo esc_html( $node['type'] ); ?></span></li><?php endforeach; ?></ul></section>
		<?php endif; ?>

		<?php $editions = (array) ( $f['multilingual_editions'] ?? array() ); if ( $editions ) : ?>
			<section class="spd-card"><h2><?php esc_html_e( 'Approved Language Editions', 'sabri-profiles-doctors' ); ?></h2><?php foreach ( $editions as $edition ) : ?><article lang="<?php echo esc_attr( $edition['locale'] ); ?>"><h3><?php echo esc_html( $edition['locale'] ); ?><?php echo 'machine' === $edition['source'] ? ' — ' . esc_html__( 'Machine translated, owner approved', 'sabri-profiles-doctors' ) : ''; ?></h3><?php if ( $edition['headline'] ) : ?><p><strong><?php echo esc_html( $edition['headline'] ); ?></strong></p><?php endif; ?><?php if ( $edition['bio'] ) : ?><p><?php echo nl2br( esc_html( $edition['bio'] ) ); ?></p><?php endif; ?></article><?php endforeach; ?></section>
		<?php endif; ?>

		<?php if ( ! empty( $f['contact_relay']['url'] ) ) : ?>
			<section class="spd-card"><h2><?php esc_html_e( 'Privacy-Safe Contact', 'sabri-profiles-doctors' ); ?></h2><a class="spd-btn" href="<?php echo esc_url( $f['contact_relay']['url'] ); ?>"><?php echo esc_html( $f['contact_relay']['label'] ); ?></a><p class="spd-meta"><?php esc_html_e( 'The recipient address remains hidden; File 17 owns the communication channel and authorization.', 'sabri-profiles-doctors' ); ?></p></section>
		<?php endif; ?>

		<?php $links = (array) ( $f['verified_links'] ?? array() ); if ( $links ) : ?>
			<section class="spd-card"><h2><?php esc_html_e( 'Verified External & Institutional Links', 'sabri-profiles-doctors' ); ?></h2><ul><?php foreach ( $links as $link ) : ?><li><a href="<?php echo esc_url( $link['url'] ); ?>" rel="noopener noreferrer"><?php echo esc_html( $link['label'] ?: $link['domain'] ); ?></a> <span class="spd-badge">✓ <?php esc_html_e( 'Verified link', 'sabri-profiles-doctors' ); ?></span></li><?php endforeach; ?></ul></section>
		<?php endif; ?>

		<?php if ( 'doctor' === $dto['profile_type'] && ! empty( $dto['badge']['verified'] ) && 'active' === ( $lifecycle['status'] ?? 'active' ) ) : ?>
			<section class="spd-card spd-ai-work"><h2><?php esc_html_e( 'Ask About This Doctor’s Public Work', 'sabri-profiles-doctors' ); ?></h2><form data-spd-ai-work data-public-id="<?php echo esc_attr( $dto['public_id'] ); ?>"><label><?php esc_html_e( 'Question', 'sabri-profiles-doctors' ); ?><textarea name="question" maxlength="500" required></textarea></label><button class="spd-btn" type="submit"><?php esc_html_e( 'Ask from grounded public sources', 'sabri-profiles-doctors' ); ?></button></form><div data-spd-ai-answer role="status" aria-live="polite"></div><p class="spd-meta"><?php esc_html_e( 'This feature is restricted to public professional work and cannot diagnose, prescribe, dose, guarantee outcomes or replace emergency care.', 'sabri-profiles-doctors' ); ?></p></section>
		<?php endif; ?>

		<section class="spd-card"><h2><?php esc_html_e( 'Portable Professional Data', 'sabri-profiles-doctors' ); ?></h2><div class="spd-actions"><a class="spd-btn spd-btn--secondary" href="<?php echo esc_url( rest_url( 'sabri-profiles/v1/profiles/' . rawurlencode( $dto['public_id'] ) . '/dossier' ) ); ?>"><?php esc_html_e( 'Structured Professional Dossier', 'sabri-profiles-doctors' ); ?></a><a class="spd-btn spd-btn--secondary" href="<?php echo esc_url( rest_url( 'sabri-profiles/v1/profiles/' . rawurlencode( $dto['public_id'] ) . '/fhir' ) ); ?>"><?php esc_html_e( 'FHIR Professional Projection', 'sabri-profiles-doctors' ); ?></a><a class="spd-btn spd-btn--secondary" href="<?php echo esc_url( rest_url( 'sabri-profiles/v1/profiles/' . rawurlencode( $dto['public_id'] ) . '/federation' ) ); ?>"><?php esc_html_e( 'Federation Projection', 'sabri-profiles-doctors' ); ?></a></div></section>

		<?php if ( $is_owner ) : $state = (array) ( $f['federation'] ?? array() ); ?>
			<section class="spd-card spd-future-owner"><h2><?php esc_html_e( 'Future Professional Identity Tools', 'sabri-profiles-doctors' ); ?></h2>
				<h3><?php esc_html_e( 'Selective Disclosure Link', 'sabri-profiles-doctors' ); ?></h3><form data-spd-disclosure><fieldset><legend><?php esc_html_e( 'Include public-safe scopes', 'sabri-profiles-doctors' ); ?></legend><?php foreach ( array( 'identity','verification','credentials','expertise','clinic','achievements','affiliations' ) as $scope ) : ?><label class="spd-check"><input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $scope ); ?>"<?php checked( in_array( $scope, array( 'identity','verification','credentials' ), true ) ); ?>> <?php echo esc_html( ucwords( $scope ) ); ?></label><?php endforeach; ?></fieldset><label><?php esc_html_e( 'Expiry in hours', 'sabri-profiles-doctors' ); ?><input type="number" name="hours" min="1" max="24" value="1"></label><button class="spd-btn" type="submit"><?php esc_html_e( 'Create temporary disclosure link', 'sabri-profiles-doctors' ); ?></button></form><div data-spd-disclosure-result role="status" aria-live="polite"></div>

				<h3><?php esc_html_e( 'Add / Approve Language Edition', 'sabri-profiles-doctors' ); ?></h3><form data-spd-translation><label><?php esc_html_e( 'Locale', 'sabri-profiles-doctors' ); ?><input name="locale" placeholder="ur-PK" maxlength="20" required></label><label><?php esc_html_e( 'Headline', 'sabri-profiles-doctors' ); ?><input name="headline" maxlength="250"></label><label><?php esc_html_e( 'Biography', 'sabri-profiles-doctors' ); ?><textarea name="bio" maxlength="4000"></textarea></label><label><?php esc_html_e( 'Source', 'sabri-profiles-doctors' ); ?><select name="source"><option value="human"><?php esc_html_e( 'Human-authored', 'sabri-profiles-doctors' ); ?></option><option value="machine"><?php esc_html_e( 'Machine translation — owner approved', 'sabri-profiles-doctors' ); ?></option></select></label><button class="spd-btn" type="submit"><?php esc_html_e( 'Save approved edition', 'sabri-profiles-doctors' ); ?></button></form><div data-spd-translation-result role="status" aria-live="polite"></div>

				<h3><?php esc_html_e( 'Reconfirm Profile Freshness', 'sabri-profiles-doctors' ); ?></h3><form data-spd-reconfirm><label><?php esc_html_e( 'Field', 'sabri-profiles-doctors' ); ?><select name="field_key"><?php foreach ( array_keys( (array) ( $f['freshness'] ?? array() ) ) as $key ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></option><?php endforeach; ?></select></label><label><?php esc_html_e( 'Reconfirm for days', 'sabri-profiles-doctors' ); ?><input type="number" name="days" min="30" max="730" value="365"></label><button class="spd-btn" type="submit"><?php esc_html_e( 'Reconfirm field', 'sabri-profiles-doctors' ); ?></button></form><div data-spd-reconfirm-result role="status" aria-live="polite"></div>

				<h3><?php esc_html_e( 'Professional Lifecycle & Federation', 'sabri-profiles-doctors' ); ?></h3><form data-spd-future-state data-public-id="<?php echo esc_attr( $dto['public_id'] ); ?>"><label><?php esc_html_e( 'Professional status', 'sabri-profiles-doctors' ); ?><select name="professional_lifecycle"><option value="active"<?php selected( 'active', $lifecycle['status'] ?? 'active' ); ?>><?php esc_html_e( 'Active', 'sabri-profiles-doctors' ); ?></option><option value="retired"<?php selected( 'retired', $lifecycle['status'] ?? '' ); ?>><?php esc_html_e( 'Retired', 'sabri-profiles-doctors' ); ?></option><?php if ( SPD_Membership_Adapter::is_founder( get_current_user_id() ) || current_user_can( 'manage_options' ) ) : ?><option value="legacy"<?php selected( 'legacy', $lifecycle['status'] ?? '' ); ?>><?php esc_html_e( 'Legacy / Memorial', 'sabri-profiles-doctors' ); ?></option><?php endif; ?></select></label><label><?php esc_html_e( 'Status reason', 'sabri-profiles-doctors' ); ?><textarea name="lifecycle_reason" maxlength="500"><?php echo esc_textarea( $lifecycle['reason'] ?? '' ); ?></textarea></label><label class="spd-check"><input type="checkbox" name="federation_opt_in" value="1"<?php checked( ! empty( $state['opt_in'] ) ); ?>> <?php esc_html_e( 'Opt in to federation-ready public actor projection', 'sabri-profiles-doctors' ); ?></label><button class="spd-btn" type="submit"><?php esc_html_e( 'Save future-profile state', 'sabri-profiles-doctors' ); ?></button></form><div data-spd-future-state-result role="status" aria-live="polite"></div>

				<h3><?php esc_html_e( 'Embeddable Verified Card', 'sabri-profiles-doctors' ); ?></h3><textarea readonly rows="3" dir="ltr"><?php echo esc_textarea( $f['embed_card']['html'] ?? '' ); ?></textarea><p class="spd-meta"><?php esc_html_e( 'The card is scriptless, tracking-free and links back to the canonical profile.', 'sabri-profiles-doctors' ); ?></p>

				<?php if ( ! empty( $f['change_history'] ) ) : ?><h3><?php esc_html_e( 'Recent Profile Change History', 'sabri-profiles-doctors' ); ?></h3><ul><?php foreach ( array_slice( $f['change_history'], 0, 20 ) as $item ) : ?><li><strong><?php echo esc_html( $item['event'] ); ?></strong><?php echo ! empty( $item['occurred_at'] ) ? ' — ' . esc_html( $item['occurred_at'] ) : ''; ?><?php if ( ! empty( $item['changed_fields'] ) ) : ?><br><span class="spd-meta"><?php echo esc_html( implode( ', ', $item['changed_fields'] ) ); ?></span><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
			</section>
		<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
