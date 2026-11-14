'use strict';
/**
 * File03 V12.3 QR acceptance: execute the ACTUAL checked-out first-party
 * assets/js/profiles.js encoder in a synthetic DOM; compare byte-mode,
 * version-5-L mask-0 matrix against independently verified QR reference.
 * Source/CI evidence only, not a physical camera/browser staging receipt.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const crypto = require('node:crypto');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/profiles.js', 'utf8');
const fixtures = [
  ['https://www.sabrihomeopathy.com/p/abc123', '182ecf9d535c4e4e79ce63b19e2e1f7d4ca5f91f348374ba67a7253ee812fbbc'],
  ['https://www.sabrihomeopathy.com/profile/123e4567-e89b-42d3-a456-426614174000/', '7315fa241995888e09e253d1691c0fc37cc22333e5b0e1de6ba15bc3e2adc497'],
  ['https://example.com', 'b168e3821a334fbd74ed2dfbaf21fd946f2f71c1bde27a255d7d261a0ebc7c92'],
];
const urls = fixtures.map(x => x[0]);
const oversize = 'https://example.com/p/' + 'x'.repeat(125);
urls.push(oversize);
const items = urls.map(url => ({
  dataset: {url},
  innerHTML: '',
  textContent: '',
  classes: [],
  classList: {add(name){this.owner.classes.push(name);}},
}));
for (const item of items) item.classList.owner = item;
const sandbox = {
  window: {SPDProfileUI: {}},
  document: {querySelectorAll(selector){return selector === '[data-spd-qr]' ? items : [];}},
  navigator: {},
  location: {href:'https://example.com/',search:''},
  URLSearchParams, TextEncoder, Uint8Array,
  setTimeout() {},
};
vm.runInNewContext(source, sandbox, {filename:'assets/js/profiles.js', timeout:3000});
for (let i=0;i<fixtures.length;i++) {
  const markup = items[i].innerHTML;
  assert.match(markup, /<svg viewBox="0 0 45 45" /, 'QR v5 must include four-module quiet zone');
  const bytes = Buffer.alloc(37*37,0);
  const regex = /<rect x="(\d+)" y="(\d+)" width="1" height="1" fill="black"\/>/g;
  let match, n=0;
  while ((match=regex.exec(markup))!==null) {
    const x=Number(match[1])-4, y=Number(match[2])-4;
    assert.ok(x>=0 && x<37 && y>=0 && y<37, 'black module outside v5 symbol');
    bytes[y*37+x]=1; n++;
  }
  assert.ok(n>200, 'unexpectedly sparse/non-functional QR code');
  const digest=crypto.createHash('sha256').update(bytes).digest('hex');
  assert.equal(digest, fixtures[i][1], 'QR data, Reed-Solomon and format/mask must match scanner-verified reference for '+fixtures[i][0]);
}
assert.equal(items[3].textContent,oversize,'oversize URL must remain available as readable text');
assert.ok(items[3].classes.includes('spd-qr--fallback'),'oversize QR must expose visible fallback state');
assert.equal(items[3].innerHTML,'','oversize URL must not inject untrusted HTML');
console.log('PASS File03 V12.3 first-party QR exact-source v5-L byte-mode reference, accessible fallback and no injection');
