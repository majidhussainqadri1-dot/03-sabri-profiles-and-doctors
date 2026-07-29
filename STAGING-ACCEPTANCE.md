# File 03 Staging Acceptance Matrix

All tests must pass before merge or deployment.

## Dependencies and activation

- File 03 fails closed when File 00 is absent.
- File 03 activates when File 00 is available.
- Missing File 09 produces no verified public doctor listings.
- File 20 navigation resolves Founder and Doctors without duplicate pages.

## Identity and authorization

- File 03 creates no role and grants no role capability.
- File 03 cannot approve, reject, suspend, or verify a doctor.
- Only the unique canonical File 00 Founder account can edit Founder presentation.
- A second administrator cannot edit Founder presentation by default.

## Doctor projection

- File 00-approved plus File 09-verified doctor with reviewer evidence can receive a snapshot.
- A doctor without both authorities is excluded.
- A material professional-field change causes snapshot mismatch and removes directory eligibility.
- File 09 re-review refreshes the snapshot and restores eligibility.
- Public directory displays only approved snapshot data, never unreviewed current data.

## Privacy

- General profiles default to private/member visibility rather than public.
- Private and owner profile requests are noindex/noarchive/no-store/private.
- Public contact remains hidden without explicit consent.
- User-nicename enumeration does not expose a private profile.
- Privacy export reports all File 03 presentation data.
- Privacy erasure deletes File 03-owned media bytes and metadata.
- Non-owned media is retained with an explicit message rather than falsely reported deleted.

## Media and page safety

- Invalid MIME, oversized, decompression-bomb-sized, and upload-error files are rejected.
- Replacing a File 03-owned image deletes the old image.
- An unrelated page using a different Sabri shortcode is never overwritten.
- Exact File 03-managed pages update safely and preserve ownership metadata.

## Retention and uninstall

- Legacy audit identifiers anonymize after 180 days and rows delete after 365 days.
- Normal uninstall preserves data.
- Destructive uninstall runs only with both required opt-ins and removes only proven File 03-owned data.
