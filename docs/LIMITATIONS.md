# Kekurangan, Kendala, dan Solusi — BPS AI Assistant

> Dokumen lengkap kekurangan/kendala yang ditemukan sejauh ini (eksplorasi BPS WebAPI, 3 ronde bugfix produksi, deploy cPanel) beserta solusinya dan status masing-masing.
>
> **Update:** 19 Agustus 2026. Live di https://bps-chatbot.pinnhost.my.id.

---

## Daftar Isi

1. [A. Limitasi BPS WebAPI](#a-limitasi-bps-webapi-di-luar-kendali-kode--terbesar)
2. [B. Model / AI behavior](#b-model--ai-behavior)
3. [C. Tool / cap design — tension fundamental](#c-tool--cap-design--tension-fundamental)
4. [D. Operasional / infrastruktur](#d-operasional--infrastruktur)
5. [E. Keamanan — wajib sebelum produksi serius](#e-keamanan--wajib-sebelum-produksi-serius)
6. [F. UX / fungsional](#f-ux--fungsional)
7. [Prioritas rekomendasi](#prioritas-rekomendasi)

---

## A. Limitasi BPS WebAPI (di luar kendali kode) — TERBESAR

| Kendala | Bukti | Solusi | Status |
|---|---|---|---|
| API hanya return metadata publikasi (judul/abstract/link PDF), **bukan angka isi PDF** | Probe: "2025" no_evidence padahal angka ada di PDF publikasi BPS | Integrasi **PDF parser/scraper**: download PDF via link dari GetPublication, ekstrak tabel angka (smalot/pdfparser atau sejenis). Cache hasil ekstrak | ❌ Belum — proyek fitur baru |
| Dynamic data var 240 (proyeksi penduduk) hanya punya period 2026, bukan 2023/2025 per-tahun kalender | GetDynamicData th=2025 return OK tapi values untuk periode sensus/proyeksi, bukan kalender | Map permintaan "tahun X" → periode proyeksi terdekat; atau ekstrak dari publikasi (lihat atas) | ⚠️ Sebagian — model jujur beri 2026 terdekat |
| Endpoint glosarium live unavailable | Semua variasi GetGlosariumTool return error | Rute definition ke tool katalog lain (ListVars/Indicators/Publications) | ✅ Sudah (round 1) |
| WAF BPS (PerimeterX) blokir curl CLI langsung | Probe curl dari server → "PerimeterX WAF Block"; Laravel Http lolos | Selalu probe via app (Http facade), bukan curl; atau set UA header eksplisit di BpsApiClient | ⚠️ Workaround aktif |

---

## B. Model / AI behavior

| Kendala | Bukti | Solusi | Status |
|---|---|---|---|
| Nondeterministik: query sama beda hasil per run | "2025" try1 no_evidence, try2 answered | Naikkan konsistensi via prompt eksplisit + retry synthesis saat no_evidence padahal data terkumpul | ❌ Belum (Fix C kompleks) |
| Model kadang tidak panggil tool sama sekali → no_evidence | Trace "2025" run#2: hanya ListDomains, lalu no_evidence | Prompt rule 8 (sekarang) arahkan fallback; + synthesis retry dengan history tool results | ⚠️ Sebagian |
| Response answer mengandung ```json code fence mentah (nested JSON dalam answer) | Screenshot + live output | Strip code fence di ChatResult::parse lebih agresif (rekursif), atau prompt "jangan sertakan JSON dalam answer" | ❌ Belum — kosmetik |
| Tidak ada conversation memory (single-turn) | `buildMessages`: 1 user message | Integrasi tabel `agent_conversations` (sudah ada migration) + history | ❌ Belum |

---

## C. Tool / cap design — tension fundamental

| Kendala | Bukti | Solusi | Status |
|---|---|---|---|
| `maxToolCalls=4` tidak cukup untuk chain panjang (Domains→Vars→Periods→DynamicData→Publications = 6) | "2023/2025" gagal saat model perlu fallback publikasi | Naikkan cap | ⚠️ Dicoba & di-revert |
| `maxToolCalls=6` REGRESS (context bloat mengaburkan model) | Jabar 2025: answered→no_evidence saat cap=6 | — | ✅ Reverted ke 4 |
| **Tension**: lebih banyak tool = context bloat = model kurang fokus | Trade-off cap 4 vs 6 | Tool selection cerdas (hanya inject tool relevan per sub-query, bukan semua numeric), atau RAG tool discovery | ❌ Belum — riset |
| Tidak ada retry/fallback saat no_evidence + collectedSources tidak kosong | Data citation terkumpul tapi model menyerah | Synthesis call kedua dengan instruksi "rangkan data dari tool results di history" | ❌ Belum (Fix C, kompleks) |
| 15 tool di numeric subset potensial membingungkan model | Trace: model kadang pilih tool salah | Group tool per fase (discovery vs data vs publication), inject bertahap | ❌ Belum |

---

## D. Operasional / infrastruktur

| Kendala | Bukti | Solusi | Status |
|---|---|---|---|
| cPanel docroot tidak bisa diubah ke /public (UAPI Domains module tidak terinstall) | UserData: `documentroot=project root` | Root `.htaccess` rewrite ke `/public/index.php` + blokir file sensitif | ✅ Sudah (round deploy) |
| Disk server 97% penuh (46GB avail) | `df -h` | Hapus backup lama, monitor; `composer --no-dev` | ⚠️ Perlu rotasi backup |
| Lokal tidak bisa reach LimitRouter (ConnectionException) | Repro lokal → no_evidence | Test path AI hanya di server, bukan lokal | ⚠️ Constraint jaringan |
| SSH paramiko timeout pada request >120s | Crash verify "jabar 2025" | Naikkan channel timeout, atau polling async | ⚠️ Operasional |
| Cache clear butuh dedicated store | `bps:clear-cache` flush | Sudah ada command | ✅ Sudah |
| Tidak ada monitoring error produksi | — | Sentry/Laravel Telescope + alert | ❌ Belum |

---

## E. Keamanan — WAJIB SEBELUM PRODUKSI SERIUS

| Kendala | Solusi | Status |
|---|---|---|
| BPS WebAPI key `32a4af778c0b74a62c19857b278cab33` tersebar di chat/transcript | Regenerasi di portal BPS + update `.env` server | ❌ Belum |
| Password cPanel `EzA%=72,@3D%` ada di `deploy/pwsupersecretcpanel.txt` + chat | Regenerasi password cPanel | ❌ Belum |
| Auth SSH pakai password (bukan SSH key) | Setup SSH key ed25519 | ❌ Belum |
| `deploy/pwsupersecretcpanel.txt` di repo lokal (tidak di-gitignore?) | Hapus file, gitignore, pindah ke secret manager | ❌ Belum |

---

## F. UX / fungsional

| Kendala | Solusi | Status |
|---|---|---|
| Sapaan sebelumnya out_of_scope | Intent greeting + Layer 0 detector | ✅ Sudah (round 1) |
| Definisi `verified=false` (demo) | Tool subset definition (ListVars/Indicators/Statictables/Publications) | ✅ Sudah (round 1) |
| Numeric minta ulang wilayah/tahun padahal jelas | `PROVINCE_PATTERNS` (38 provinsi) + `LATEST_PERIOD_KEYWORDS` (terbaru/terkini/sekarang) | ✅ Sudah (round 1) |
| Root `/` 403 | `.htaccess` rewrite root ke `public/index.php` | ✅ Sudah (round deploy) |

---

## Prioritas rekomendasi

1. **Keamanan dulu** (E) — regenerasi BPS key + password cPanel, setup SSH key ed25519. Murah, cepat, kritis. Tidak boleh ada di repo.
2. **PDF parser publikasi BPS** (A) — solusi sebenarnya untuk angka tahun spesifik (2025). Proyek fitur, bukan bug fix. Paling impactful untuk kebutuhan pengguna nyata.
3. **Retry synthesis Fix C** (B/C) — kurangi nondeterminism. Kompleks tapi addressable: synthesis call kedua saat no_evidence + collectedSources tidak kosong.
4. **Tool selection cerdas** (C) — selesaikan tension cap vs context. Riset: inject hanya tool relevan per sub-query.
5. **Conversation memory** (B) — tabel `agent_conversations` sudah ada migration, tinggal integrasi history.
6. **Monitoring** (D) — Sentry atau Laravel Telescope + alert error produksi.

---

## Catatan investigasi

- **Round 1 bugfix** (PR #2): greeting, definition verified, numeric geography/period detection. Branch `worktree-fix-greeting-definition-numeric`, commit `3f843a7`.
- **Round 2 bugfix** (PR #3): numeric publication/statictable fallback + prompt guidance. Branch `worktree-fix-numeric-publication-tools`, commit `3a9d688`.
- **Metode investigasi**: systematic-debugging skill. Reproduksi live via `/api/chat` → baca source → instrumentasi BpsAgent+CitationCollectingTool logging (sementara, di-revert) → trace tool call → hipotesis → fix TDD → deploy → verifikasi live.
- **Limitasi metode**: probe BPS API langsung via curl diblokir WAF (PerimeterX); probe harus via app Laravel Http facade. Lokal tidak bisa reach LimitRouter (ConnectionException) sehingga repro path AI hanya bisa di server.
