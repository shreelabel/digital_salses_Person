/* ============================================================
   api.js — thin fetch wrapper for the REST API.
   Adds the CSRF header on unsafe methods. Returns parsed JSON.
   ============================================================ */
(function () {
  'use strict';
  const SLC = window.SLC || (window.SLC = {});

  function buildUrl(path) {
    const base = (SLC.apiBase || (SLC.base || '') + '/api').replace(/\/$/, '');
    return base + '/' + String(path).replace(/^\//, '');
  }

  async function request(method, path, body) {
    const headers = { 'Accept': 'application/json' };
    const opts = { method: method, headers: headers, credentials: 'same-origin' };
    const unsafe = ['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1;
    if (unsafe) {
      headers['X-CSRF-Token'] = SLC.csrfToken || '';
    }
    if (body !== undefined && body !== null) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    let resp;
    try {
      resp = await fetch(buildUrl(path), opts);
    } catch (err) {
      throw new Error('Network error reaching the API.');
    }
    if (SLC.handleFetchError && SLC.handleFetchError(resp)) {
      throw new Error('Unauthorized');
    }
    let data = null;
    const text = await resp.text();
    if (text) {
      try { data = JSON.parse(text); } catch (e) { data = { raw: text }; }
    }
    if (!resp.ok) {
      const msg = (data && data.error) ? data.error : ('Request failed (' + resp.status + ')');
      const err = new Error(msg);
      err.status = resp.status;
      err.data = data;
      throw err;
    }
    return data;
  }

  async function upload(path, formData) {
    const headers = { 'Accept': 'application/json' };
    if (SLC.csrfToken) {
      headers['X-CSRF-Token'] = SLC.csrfToken;
    }
    const resp = await fetch(buildUrl(path), {
      method: 'POST',
      headers: headers,
      body: formData,
      credentials: 'same-origin',
    });
    if (SLC.handleFetchError && SLC.handleFetchError(resp)) {
      throw new Error('Unauthorized');
    }
    let data = null;
    const text = await resp.text();
    if (text) {
      try { data = JSON.parse(text); } catch (e) { data = { raw: text }; }
    }
    if (!resp.ok) {
      const msg = (data && data.error) ? data.error : ('Upload failed (' + resp.status + ')');
      const err = new Error(msg);
      err.status = resp.status;
      err.data = data;
      throw err;
    }
    return data;
  }

  const api = {
    get: (p) => request('GET', p),
    post: (p, b) => request('POST', p, b || {}),
    put: (p, b) => request('PUT', p, b || {}),
    del: (p) => request('DELETE', p),
    upload: upload,
    raw: request,
    buildUrl: buildUrl,
  };

  // Resource factory: /resource + standard CRUD
  api.resource = function (name) {
    return {
      list: (qs) => api.get(name + (qs ? ('?' + new URLSearchParams(qs).toString()) : '')),
      get: (id) => api.get(name + '/' + id),
      create: (data) => api.post(name, data),
      update: (id, data) => api.put(name + '/' + id, data),
      remove: (id) => api.del(name + '/' + id),
      bulkDelete: (ids) => api.post(name + '/bulk-delete', { ids: ids }),
    };
  };

  SLC.api = api;
  window.SLC = SLC;
})();
