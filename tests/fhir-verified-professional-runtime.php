<?php
/**
 * F03-FUT-17, F03-FR-011, F03-CEN-02:
 * A public member (or unverified professional proposal) must not be
 * represented as a FHIR Practitioner/PractitionerRole. Only a current,
 * verified doctor can be projected as that interoperable professional role.
 *
 * No WordPress DB/network; method-level fail-first proof.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/../includes/class-spd-future-profile.php';
function check(bool $ok, string $msg): void {
    if (!$ok) { fwrite(STDERR,"FAIL: {$msg}\n"); exit(1); }
}
$dto=[
    'public_id'=>'a4444444-4444-4444-8444-444444444444',
    'display_name'=>'Test Public Member',
    'profile_type'=>'member',
    'badge'=>['key'=>'member','verified'=>false],
    'fields'=>['languages'=>'Urdu'],
    'credential_card'=>[],
    'professional'=>[],
    'future'=>['lifecycle'=>['status'=>'active']],
];
check(SPD_Future_Profile::fhir_projection($dto) === [], 'public member incorrectly represented as FHIR Practitioner');
$founder=$dto;
$founder['profile_type']='founder';
$founder['badge']=['key'=>'official_founder','verified'=>true];
check(SPD_Future_Profile::fhir_projection($founder) === [], 'Founder status must not imply clinical Practitioner verification');
$dto['profile_type']='doctor';
check(SPD_Future_Profile::fhir_projection($dto) === [], 'unverified doctor represented as certified Practitioner');
$dto['badge']=['key'=>'verified_doctor','verified'=>true];
$result=SPD_Future_Profile::fhir_projection($dto);
check(($result['practitioner']['resourceType']??'')==='Practitioner', 'verified doctor Practitioner missing');
check(($result['practitioner_role']['resourceType']??'')==='PractitionerRole', 'verified doctor role missing');
check(($result['clinical_record']??null)===false, 'patient data boundary missing');
$dto['future']['lifecycle']['status']='retired';
$retired=SPD_Future_Profile::fhir_projection($dto);
check(($retired['practitioner']['active']??true)===false && ($retired['practitioner_role']['active']??true)===false, 'retired doctor misrepresented as active');
echo "PASS FHIR only verified professional with governed lifecycle status\n";
