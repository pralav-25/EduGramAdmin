(function (root) {
  'use strict';
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g,
    char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
  async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    let data;
    try { data = await response.json(); }
    catch { throw new Error('The server returned an unreadable response. Please retry.'); }
    if (!response.ok) throw new Error(data.error || `Request failed (${response.status}).`);
    return data;
  }
  function initDirectory(route, columns) {
    const search = document.getElementById('search');
    const tbody = document.querySelector('tbody');
    const status = document.getElementById('directory-status');
    const pagination = document.getElementById('pagination');
    let page = 1, loadedPage = 1, controller, timer, sequence = 0;
    const perPage = 20;
    const prev = document.createElement('button');
    const next = document.createElement('button');
    const retry = document.createElement('button');
    for (const [button, label] of [[prev, 'Previous'], [next, 'Next'], [retry, 'Retry']]) {
      button.type = 'button'; button.className = 'btn btn-outline-secondary'; button.textContent = label;
      pagination.appendChild(button);
    }
    retry.hidden = true;
    async function load() {
      const ownSequence = ++sequence;
      controller?.abort(); controller = new AbortController();
      const requestedPage = page;
      status.textContent = 'Loading…'; prev.disabled = next.disabled = true; retry.hidden = true;
      try {
        const query = new URLSearchParams({ route, q: search.value.trim(), page: requestedPage, per_page: perPage });
        const data = await requestJson('../api/index.php?' + query, { signal: controller.signal });
        if (ownSequence !== sequence) return;
        tbody.replaceChildren();
        data.data.forEach((row, index) => {
          const tr = document.createElement('tr');
          for (const value of [(requestedPage - 1) * perPage + index + 1, ...columns.map(key => row[key] ?? '')]) {
            const td = document.createElement('td'); td.textContent = String(value); tr.appendChild(td);
          }
          tbody.appendChild(tr);
        });
        loadedPage = requestedPage;
        status.textContent = data.total ? `Page ${page} · ${data.total} matching ${route}` : `No matching ${route}.`;
        prev.disabled = page <= 1; next.disabled = page * perPage >= data.total;
      } catch (error) {
        if (ownSequence !== sequence || error.name === 'AbortError') return;
        page = loadedPage;
        status.textContent = error.message; retry.hidden = false;
        prev.disabled = page <= 1;
      }
    }
    prev.onclick = () => { page = Math.max(1, page - 1); void load(); };
    next.onclick = () => { page += 1; void load(); };
    retry.onclick = () => { void load(); };
    search.addEventListener('input', () => {
      ++sequence; controller?.abort(); clearTimeout(timer); page = 1;
      prev.disabled = next.disabled = true;
      timer = setTimeout(load, 200);
    });
    void load();
  }
  const api = { escapeHtml, requestJson, initDirectory };
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.EduUI = api;
})(globalThis);
