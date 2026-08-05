<?php
require __DIR__ . '/test-bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-spd-helpers.php';
require dirname( __DIR__ ) . '/includes/class-spd-membership-adapter.php';
require dirname( __DIR__ ) . '/includes/class-spd-verification-adapter.php';

test_assert( SPD_Helpers::clean_phone( '+44 (20) 7946-0958' ) === '+442079460958', 'Phone normalization failed.' );
test_assert( SPD_Helpers::normalize_locale( 'ur_PK' ) === 'ur-PK', 'Locale normalization failed.' );
test_assert( SPD_Helpers::normalize_focal( 120 ) === 100.0, 'Focal upper bound failed.' );
test_assert( SPD_Helpers::normalize_focal( -3 ) === 0.0, 'Focal lower bound failed.' );
test_assert( SPD_Helpers::state_transition_allowed( 'active', 'suspended', 'profile' ), 'Valid profile transition rejected.' );
test_assert( ! SPD_Helpers::state_transition_allowed( 'tombstoned', 'active', 'profile' ), 'Forbidden profile transition accepted.' );
$data = array( 'display_name' => 'Doctor Example', 'country' => 'PK', 'profile_photo_id' => 12 );
$one  = SPD_Verification_Adapter::fingerprint( $data );
$two  = SPD_Verification_Adapter::fingerprint( array_reverse( $data, true ) );
test_assert( hash_equals( $one, $two ), 'Fingerprint must be canonical.' );
test_assert( SPD_Verification_Adapter::status_label( 'verified' ) === 'Verified', 'Status label failed.' );
test_assert( SPD_Helpers::current_contract_claim( array( 'contract_version' => '1.2.0', 'generated_at' => gmdate( 'c' ), 'valid_until' => gmdate( 'c', time() + 600 ) ), '1.0.0' ), 'Fresh contract claim rejected.' );
test_assert( ! SPD_Helpers::current_contract_claim( array( 'contract_version' => '1.2.0', 'generated_at' => gmdate( 'c', time() - 7200 ), 'valid_until' => gmdate( 'c', time() + 600 ) ), '1.0.0' ), 'Stale contract claim accepted.' );
echo "Security and state-machine unit checks passed.\n";
