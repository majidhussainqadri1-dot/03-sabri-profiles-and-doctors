(() => {
  'use strict';
  const cfg = window.SPDProfileUI || {};
  const base = String(cfg.restUrl || '').replace(/\/?$/, '/');
  const nonce = String(cfg.nonce || '');

  function mutationKey() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      const bytes = new Uint8Array(16);
      window.crypto.getRandomValues(bytes);
      return `spd-${Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')}`;
    }
    return `spd-${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
  }

  function stablePayload(body) {
    if (!body || typeof body !== 'object' || Array.isArray(body)) return JSON.stringify(body);
    const ordered = {};
    Object.keys(body).sort().forEach((key) => {
      const value = body[key];
      ordered[key] = Array.isArray(value) ? value.slice() : value;
    });
    return JSON.stringify(ordered);
  }

  function formMutationKey(form, body) {
    const fingerprint = stablePayload(body);
    if (!form.dataset.spdIdempotencyKey || form.dataset.spdIdempotencyPayload !== fingerprint) {
      form.dataset.spdIdempotencyKey = mutationKey();
      form.dataset.spdIdempotencyPayload = fingerprint;
    }
    return form.dataset.spdIdempotencyKey;
  }

  function clearMutationKey(form) {
    delete form.dataset.spdIdempotencyKey;
    delete form.dataset.spdIdempotencyPayload;
  }

  async function request(path, method = 'GET', body, options = {}) {
    const requestOptions = { method, credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (nonce) requestOptions.headers['X-WP-Nonce'] = nonce;
    if (body !== undefined) {
      requestOptions.headers['Content-Type'] = 'application/json';
      requestOptions.body = JSON.stringify(body);
    }
    if (options.idempotencyKey) requestOptions.headers['Idempotency-Key'] = String(options.idempotencyKey);
    const response = await fetch(base + path.replace(/^\//, ''), requestOptions);
    let payload = {};
    try { payload = await response.json(); } catch (e) { payload = {}; }
    if (!response.ok) throw new Error(payload.message || payload.code || 'Request failed');
    return payload.data;
  }

  function resultBox(form, selector, message, isError = false) {
    const scope = form.closest('.spd-card') || document;
    const box = scope.querySelector(selector);
    if (!box) return;
    box.textContent = message;
    box.classList.toggle('spd-notice--error', !!isError);
    box.classList.toggle('spd-notice--success', !isError);
  }

  document.addEventListener('submit', async (event) => {
    const form = event.target;
    try {
      if (form.matches('[data-spd-ai-work]')) {
        event.preventDefault();
        const id = form.dataset.publicId;
        const question = String(new FormData(form).get('question') || '');
        const data = await request(`profiles/${encodeURIComponent(id)}/ask-work`, 'POST', { question });
        const box = form.parentElement.querySelector('[data-spd-ai-answer]');
        if (box) {
          box.textContent = data.answer || '';
          if (Array.isArray(data.citations) && data.citations.length) {
            const list = document.createElement('ul');
            data.citations.forEach((c) => {
              const li = document.createElement('li');
              const a = document.createElement('a');
              a.href = c.url; a.textContent = c.title || c.url;
              li.appendChild(a); list.appendChild(li);
            });
            box.appendChild(list);
          }
        }
        return;
      }
      if (form.matches('[data-spd-disclosure]')) {
        event.preventDefault();
        const fd = new FormData(form);
        const scopes = fd.getAll('scopes[]').map(String);
        const hours = Math.min(24, Math.max(1, Number(fd.get('hours') || 1)));
        const body = { scopes, ttl: Math.round(hours * 3600) };
        const data = await request('me/disclosures', 'POST', body, { idempotencyKey: formMutationKey(form, body) });
        clearMutationKey(form);
        resultBox(form, '[data-spd-disclosure-result]', data.url || 'Disclosure link created.');
        return;
      }
      if (form.matches('[data-spd-translation]')) {
        event.preventDefault();
        const fd = new FormData(form);
        const body = { locale: fd.get('locale'), headline: fd.get('headline'), bio: fd.get('bio'), source: fd.get('source') };
        await request('me/translations', 'POST', body, { idempotencyKey: formMutationKey(form, body) });
        clearMutationKey(form);
        resultBox(form, '[data-spd-translation-result]', 'Approved language edition saved.');
        return;
      }
      if (form.matches('[data-spd-reconfirm]')) {
        event.preventDefault();
        const fd = new FormData(form);
        const body = { field_key: fd.get('field_key'), days: Number(fd.get('days') || 365) };
        const data = await request('me/reconfirm', 'POST', body, { idempotencyKey: formMutationKey(form, body) });
        clearMutationKey(form);
        resultBox(form, '[data-spd-reconfirm-result]', `Reconfirmed until ${data.expires_at || ''}.`);
        return;
      }
      if (form.matches('[data-spd-future-state]')) {
        event.preventDefault();
        const fd = new FormData(form);
        const id = form.dataset.publicId;
        const body = { professional_lifecycle: fd.get('professional_lifecycle'), lifecycle_reason: fd.get('lifecycle_reason'), federation_opt_in: fd.get('federation_opt_in') === '1' };
        const data = await request(`profiles/${encodeURIComponent(id)}/future-state`, 'PUT', body, { idempotencyKey: formMutationKey(form, body) });
        clearMutationKey(form);
        resultBox(form, '[data-spd-future-state-result]', `Saved: ${data.professional_lifecycle}.`);
      }
    } catch (error) {
      event.preventDefault();
      const selectors = [
        ['[data-spd-disclosure]', '[data-spd-disclosure-result]'],
        ['[data-spd-translation]', '[data-spd-translation-result]'],
        ['[data-spd-reconfirm]', '[data-spd-reconfirm-result]'],
        ['[data-spd-future-state]', '[data-spd-future-state-result]']
      ];
      for (const [match, box] of selectors) if (form.matches(match)) resultBox(form, box, error.message, true);
      if (form.matches('[data-spd-ai-work]')) {
        const answer = form.parentElement.querySelector('[data-spd-ai-answer]');
        if (answer) answer.textContent = error.message;
      }
    }
  });
})();
