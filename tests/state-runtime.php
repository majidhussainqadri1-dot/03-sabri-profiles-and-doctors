<?php
require __DIR__.'/test-bootstrap.php';
require dirname(__DIR__).'/includes/class-spd-helpers.php';
test_assert(SPD_Helpers::state_transition_allowed('incomplete','active','profile'),'Valid profile transition rejected.');
test_assert(!SPD_Helpers::state_transition_allowed('tombstoned','active','profile'),'Tombstone resurrection allowed.');
test_assert(SPD_Helpers::state_transition_allowed('draft','pending_review','professional_field'),'Professional submit transition rejected.');
test_assert(SPD_Helpers::state_transition_allowed('pending_scan','active','media'),'Media activation rejected.');
test_assert(!SPD_Helpers::state_transition_allowed('rejected','active','media'),'Rejected media reactivation allowed.');
test_assert(SPD_Helpers::normalize_locale('ur_PK')==='ur-PK','Locale normalization failed.');
test_assert(SPD_Helpers::same_origin_url('https://example.test/profile/x'),'Same-origin URL rejected.');
test_assert(!SPD_Helpers::same_origin_url('https://evil.test/x'),'Cross-origin URL accepted.');
test_assert(SPD_Helpers::current_contract_claim(array('contract_version'=>'1.2.0','generated_at'=>gmdate('c'),'valid_until'=>gmdate('c',time()+600)),'1.0.0'),'Fresh contract claim rejected.');
test_assert(!SPD_Helpers::current_contract_claim(array('contract_version'=>'1.2.0','generated_at'=>gmdate('c',time()-7200),'valid_until'=>gmdate('c',time()+600)),'1.0.0'),'Stale contract claim accepted.');
echo "State and helper runtime checks passed.\n";
