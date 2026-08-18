// BPS AI Assistant — chat client. Vanilla JS, no framework.
// Browser hanya memanggil /api/* aplikasi sendiri. Tidak pernah limitrouter.com.

(function () {
    const $ = (sel) => document.querySelector(sel);
    const messagesEl = $('#messages');
    const welcomeEl = $('#welcome');
    const composer = $('#composer');
    const sendBtn = $('#send');
    const suggestBtns = document.querySelectorAll('.bps-suggest');

    const API = {
        chat: '/api/chat',
        feedback: '/api/feedback',
    };

    let conversationId = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'sess-' + Date.now();
    let busy = false;

    // --- helpers ---

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Markdown minimal + sanitasi: paragraphs, bold, lists, headings, tables, inline [1].
    // Tidak ada raw HTML (tag di-escape dulu).
    function renderMarkdown(text) {
        if (!text) return '';
        let t = escapeHtml(text);

        // headings
        t = t.replace(/^###\s+(.+)$/gm, '<h3>$1</h3>')
             .replace(/^##\s+(.+)$/gm, '<h2>$1</h2>')
             .replace(/^#\s+(.+)$/gm, '<h1>$1</h1>');

        // tables (pipe)
        if (/\|/.test(t) && /\n\|/.test('\n' + t)) {
            t = renderTable(t);
        }

        // bullet lists
        t = t.replace(/^(?:•|\*|-)\s+(.+)$/gm, '<li>$1</li>');
        t = t.replace(/(<li>[\s\S]*?<\/li>)(?!\s*<li>)/g, '<ul>$1</ul>');

        // numbered lists
        t = t.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');

        // bold
        t = t.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        // inline citation [1]
        t = t.replace(/\[(\d+)\]/g, '<span class="inline-flex items-center bg-surface-blue text-primary-container rounded px-1" style="font-size:11px;font-weight:600;">[$1]</span>');

        // paragraphs (split on blank lines, wrap non-block lines)
        const blocks = t.split(/\n{2,}/).map(b => {
            const trimmed = b.trim();
            if (trimmed === '') return '';
            if (/^<(h\d|ul|ol|li|table)/.test(trimmed)) return trimmed;
            return '<p>' + trimmed.replace(/\n/g, '<br>') + '</p>';
        });

        return blocks.join('');
    }

    function renderTable(t) {
        const lines = t.split('\n');
        const rows = [];
        for (const line of lines) {
            if (/^\s*\|/.test(line)) {
                const cells = line.trim().replace(/^\||\|$/g, '').split('|').map(c => c.trim());
                rows.push(cells);
            }
        }
        if (rows.length < 2) return t;
        // skip separator row (---)
        const header = rows[0];
        const body = rows.filter((r, i) => i > 0 && !r.every(c => /^-+$/.test(c)));
        let html = '<table><thead><tr>' + header.map(c => `<th>${c}</th>`).join('') + '</tr></thead><tbody>';
        for (const r of body) {
            html += '<tr>' + r.map(c => `<td>${c}</td>`).join('') + '</tr>';
        }
        html += '</tbody></table>';
        return html;
    }

    function scrollToBottom() {
        const main = document.querySelector('main');
        if (main) main.scrollTop = main.scrollHeight;
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }

    function hideWelcome() {
        if (welcomeEl && welcomeEl.style.display !== 'none') {
            welcomeEl.style.display = 'none';
        }
    }

    // --- render message types ---

    function addUserMessage(text) {
        hideWelcome();
        const wrap = document.createElement('div');
        wrap.className = 'flex justify-end';
        wrap.innerHTML = `
            <div class="bg-surface-blue text-on-surface border rounded-xl rounded-tr-sm p-md max-w-[75%]" style="border-color:var(--color-primary-fixed);">
                <p style="font-size:16px;line-height:1.6;">${escapeHtml(text)}</p>
            </div>`;
        messagesEl.appendChild(wrap);
        scrollToBottom();
    }

    function addAssistantShell() {
        hideWelcome();
        const id = 'msg-' + Math.random().toString(36).slice(2, 9);
        const wrap = document.createElement('div');
        wrap.className = 'flex gap-sm items-start';
        wrap.id = id;
        wrap.innerHTML = `
            <div class="w-8 h-8 rounded-full bg-primary-container flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-on-primary-container" style="font-size:18px;">smart_toy</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-on-surface" style="font-size:14px;font-weight:600;margin-bottom:4px;">BPS AI Assistant</p>
                <div class="bps-body text-on-surface" style="font-size:16px;line-height:1.6;"></div>
            </div>`;
        messagesEl.appendChild(wrap);
        scrollToBottom();
        return wrap;
    }

    function setStatus(shell, text) {
        const body = shell.querySelector('.bps-body');
        body.innerHTML = `<div class="flex items-center gap-sm text-on-surface-variant" style="font-size:14px;">
            <span class="flex gap-1">
                <span class="bps-dot w-1.5 h-1.5 rounded-full bg-primary-container"></span>
                <span class="bps-dot w-1.5 h-1.5 rounded-full bg-primary-container"></span>
                <span class="bps-dot w-1.5 h-1.5 rounded-full bg-primary-container"></span>
            </span>
            ${escapeHtml(text)}
        </div>`;
        scrollToBottom();
    }

    function renderAnswer(shell, answer, citations, status, clarificationQuestion) {
        const body = shell.querySelector('.bps-body');
        let html = '';

        if (status === 'clarification_required') {
            html = renderClarification(clarificationQuestion);
        } else if (status === 'no_evidence') {
            html = renderNoEvidence(answer);
        } else if (status === 'out_of_scope') {
            html = renderOutOfScope(answer);
        } else if (status === 'rate_limited') {
            html = renderRateLimit(answer);
        } else if (status === 'provider_error') {
            html = renderError(answer);
        } else {
            html = `<div class="bps-answer">${renderMarkdown(answer || '')}</div>`;
            if (citations && citations.length) {
                html += renderSources(citations);
            }
        }

        body.innerHTML = html;

        // feedback row for answered/clarification/no_evidence
        if (['answered', 'clarification_required', 'no_evidence'].includes(status)) {
            body.appendChild(renderFeedback(shell));
        }
        scrollToBottom();
    }

    function renderSources(citations) {
        let cards = '';
        citations.forEach((c, i) => {
            const num = i + 1;
            const verifiedTag = c.url
                ? `<a href="${escapeHtml(c.url)}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-primary-container hover:underline" style="font-size:13px;font-weight:600;">Buka sumber <span class="material-symbols-outlined" style="font-size:16px;">open_in_new</span></a>`
                : `<span class="text-on-surface-variant" style="font-size:13px;">Sumber demo — URL belum diverifikasi</span>`;
            const snippet = c.snippet ? `<p class="text-on-surface-variant mt-base" style="font-size:12px;">${escapeHtml(c.snippet)}</p>` : '';
            cards += `
                <div class="bg-surface-container-lowest border border-border-default rounded-lg p-md hover:border-brand-green-deep transition-colors">
                    <div class="flex items-center gap-sm mb-base">
                        <span class="bg-surface-green text-brand-green-deep rounded px-1" style="font-size:11px;font-weight:600;">[${num}]</span>
                        <span class="text-on-surface" style="font-size:14px;font-weight:600;">${escapeHtml(c.title)}</span>
                    </div>
                    <div class="flex items-center justify-between gap-sm flex-wrap">
                        <span class="text-on-surface-variant" style="font-size:12px;">Sumber BPS · DEMO</span>
                        ${verifiedTag}
                    </div>
                    ${snippet}
                </div>`;
        });
        return `<div class="mt-lg">
            <p class="text-on-surface mb-sm" style="font-size:14px;font-weight:600;">Sumber</p>
            <div class="flex flex-col gap-sm">${cards}</div>
        </div>`;
    }

    function renderClarification(question) {
        return `<div class="bg-surface-blue border rounded-xl p-md" style="border-color:var(--color-primary-fixed);">
            <p class="text-on-surface mb-sm" style="font-size:14px;font-weight:600;">Saya perlu sedikit informasi tambahan.</p>
            <p class="text-on-surface" style="font-size:16px;line-height:1.6;">${escapeHtml(question || 'Wilayah dan periode mana yang Anda maksud?')}</p>
            <div class="flex gap-sm flex-wrap mt-md">
                <span class="bg-surface-container-lowest border border-border-default rounded-full px-sm py-base text-on-surface-variant" style="font-size:13px;">Provinsi</span>
                <span class="bg-surface-container-lowest border border-border-default rounded-full px-sm py-base text-on-surface-variant" style="font-size:13px;">Kabupaten/Kota</span>
                <span class="bg-surface-container-lowest border border-border-default rounded-full px-sm py-base text-on-surface-variant" style="font-size:13px;">Tahun</span>
            </div>
        </div>`;
    }

    function renderNoEvidence(answer) {
        return `<div class="bg-surface-orange rounded-xl p-md" style="border-left:3px solid var(--color-secondary-container);">
            <p class="text-on-surface mb-sm" style="font-size:14px;font-weight:600;">Informasi belum ditemukan</p>
            <p class="text-on-surface" style="font-size:16px;line-height:1.6;">${escapeHtml(answer || 'Saya belum menemukan sumber BPS yang cukup untuk memastikan jawaban tersebut.')}</p>
            <div class="flex gap-sm flex-wrap mt-md">
                <button type="button" class="bps-action bg-surface-container-lowest border border-border-default rounded-lg px-sm py-base text-primary-container" style="font-size:13px;font-weight:600;" data-action="refine">Perjelas pertanyaan</button>
                <a href="https://www.bps.go.id" target="_blank" rel="noopener noreferrer" class="bg-surface-container-lowest border border-border-default rounded-lg px-sm py-base text-primary-container inline-flex items-center gap-1" style="font-size:13px;font-weight:600;">Cari di website BPS <span class="material-symbols-outlined" style="font-size:16px;">open_in_new</span></a>
            </div>
        </div>`;
    }

    function renderOutOfScope(answer) {
        return `<div class="bg-surface-container-low border border-outline-variant rounded-xl p-md">
            <p class="text-on-surface" style="font-size:16px;line-height:1.6;">${escapeHtml(answer || 'Saya difokuskan untuk membantu pertanyaan seputar BPS, statistik, publikasi, dan layanan BPS.')}</p>
            <p class="text-on-surface-variant mt-md mb-sm" style="font-size:13px;">Coba tanyakan:</p>
            <div class="flex flex-col gap-sm">
                <button type="button" class="bps-suggest-2 text-left bg-surface-container-lowest border border-border-default rounded-lg px-sm py-base text-on-surface hover:bg-surface-container transition-colors" style="font-size:14px;" data-q="Apa itu inflasi?">Apa itu inflasi?</button>
                <button type="button" class="bps-suggest-2 text-left bg-surface-container-lowest border border-border-default rounded-lg px-sm py-base text-on-surface hover:bg-surface-container transition-colors" style="font-size:14px;" data-q="Bagaimana mencari data penduduk?">Bagaimana mencari data penduduk?</button>
                <button type="button" class="bps-suggest-2 text-left bg-surface-container-lowest border border-border-default rounded-lg px-sm py-base text-on-surface hover:bg-surface-container transition-colors" style="font-size:14px;" data-q="Di mana saya bisa menemukan publikasi BPS?">Di mana saya bisa menemukan publikasi BPS?</button>
            </div>
        </div>`;
    }

    function renderRateLimit(answer) {
        return `<div class="bg-surface-orange rounded-xl p-md" style="border-left:3px solid var(--color-secondary-container);">
            <p class="text-brand-orange-deep mb-sm" style="font-size:15px;font-weight:600;">Terlalu banyak permintaan</p>
            <p class="text-on-surface" style="font-size:16px;line-height:1.6;">${escapeHtml(answer || 'Silakan tunggu sebentar sebelum mengirim pertanyaan berikutnya.')}</p>
            <button type="button" class="bps-action mt-md bg-surface-container-lowest border border-border-default rounded-lg px-sm py-base text-primary-container" style="font-size:13px;font-weight:600;" data-action="retry">Coba lagi</button>
        </div>`;
    }

    function renderError(answer) {
        return `<div class="bg-surface-error rounded-xl p-md" style="border-left:3px solid var(--color-error);">
            <p class="text-on-surface" style="font-size:16px;line-height:1.6;">${escapeHtml(answer || 'Layanan AI sedang tidak tersedia. Silakan coba kembali beberapa saat lagi.')}</p>
            <button type="button" class="bps-action mt-md bg-surface-container-lowest border border-border-default rounded-lg px-sm py-base text-primary-container" style="font-size:13px;font-weight:600;" data-action="retry">Coba lagi</button>
        </div>`;
    }

    function renderFeedback(shell) {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-md mt-lg';
        row.innerHTML = `
            <span class="text-on-surface-variant" style="font-size:12px;">Apakah jawaban ini membantu?</span>
            <div class="flex gap-sm">
                <button type="button" class="bps-fb inline-flex items-center gap-1 px-sm py-base rounded-lg text-on-surface-variant hover:bg-surface-green transition-colors" data-rating="helpful" aria-label="Membantu">
                    <span class="material-symbols-outlined" style="font-size:18px;">thumb_up</span>
                </button>
                <button type="button" class="bps-fb inline-flex items-center gap-1 px-sm py-base rounded-lg text-on-surface-variant hover:bg-surface-error transition-colors" data-rating="not_helpful" aria-label="Tidak membantu">
                    <span class="material-symbols-outlined" style="font-size:18px;">thumb_down</span>
                </button>
                <button type="button" class="bps-copy inline-flex items-center gap-1 px-sm py-base rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors" aria-label="Salin jawaban">
                    <span class="material-symbols-outlined" style="font-size:18px;">content_copy</span>
                    <span style="font-size:12px;">Salin</span>
                </button>
            </div>`;
        return row;
    }

    // --- send flow ---

    async function send(text) {
        if (busy) return;
        const trimmed = (text || '').trim();
        if (trimmed === '') return;

        busy = true;
        setComposerState(false);
        composer.value = '';
        autoGrow();

        addUserMessage(trimmed);
        const shell = addAssistantShell();
        setStatus(shell, 'Mencari sumber BPS...');

        // small UX pause before "generating"
        await sleep(450);
        setStatus(shell, 'Menyusun jawaban...');

        try {
            const resp = await fetch(API.chat, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ conversationId, message: trimmed }),
            });

            const data = await resp.json().catch(() => null);

            if (!resp.ok || !data) {
                const err = data && data.error ? data.error : null;
                if (resp.status === 429) {
                    renderAnswer(shell, err ? err.message : '', [], 'rate_limited', null);
                } else {
                    renderAnswer(shell, (err && err.message) ? err.message : 'Layanan AI sedang tidak tersedia. Silakan coba kembali beberapa saat lagi.', [], 'provider_error', null);
                }
                return;
            }

            renderAnswer(shell, data.answer, data.citations || [], data.status, data.clarificationQuestion);

            // wire feedback + copy + actions
            wireActions(shell, data);
        } catch (e) {
            renderAnswer(shell, 'Layanan AI sedang tidak tersedia. Silakan coba kembali beberapa saat lagi.', [], 'provider_error', null);
        } finally {
            busy = false;
            setComposerState(true);
            composer.focus();
        }
    }

    function wireActions(shell, data) {
        const body = shell.querySelector('.bps-body');
        body.querySelectorAll('.bps-fb').forEach(btn => btn.addEventListener('click', () => {
            const rating = btn.getAttribute('data-rating');
            btn.classList.add(rating === 'helpful' ? 'bg-surface-green text-brand-green-deep' : 'bg-surface-error text-error');
            fetch(API.feedback, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ messageId: data.requestId, rating }),
            }).catch(() => {});
        }));
        body.querySelectorAll('.bps-copy').forEach(btn => btn.addEventListener('click', () => {
            const text = (data.answer || '').trim();
            if (text && navigator.clipboard) navigator.clipboard.writeText(text).catch(() => {});
        }));
        body.querySelectorAll('.bps-suggest-2').forEach(btn => btn.addEventListener('click', () => send(btn.getAttribute('data-q'))));
        body.querySelectorAll('.bps-action[data-action="refine"]').forEach(btn => btn.addEventListener('click', () => composer.focus()));
        body.querySelectorAll('.bps-action[data-action="retry"]').forEach(btn => btn.addEventListener('click', () => {
            const lastUser = [...messagesEl.querySelectorAll('div.flex.justify-end')].pop();
            // simply refocus; user can resend
            composer.focus();
        }));
    }

    // --- composer ---

    function setComposerState(enabled) {
        sendBtn.disabled = !enabled || composer.value.trim() === '';
        composer.disabled = !enabled;
    }

    function autoGrow() {
        composer.style.height = 'auto';
        composer.style.height = Math.min(composer.scrollHeight, 128) + 'px';
        sendBtn.disabled = busy || composer.value.trim() === '';
    }

    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    composer.addEventListener('input', autoGrow);
    composer.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send(composer.value);
        }
    });
    sendBtn.addEventListener('click', () => send(composer.value));
    suggestBtns.forEach(btn => btn.addEventListener('click', () => send(btn.getAttribute('data-q'))));

    // initial state
    autoGrow();
    composer.focus();
})();
