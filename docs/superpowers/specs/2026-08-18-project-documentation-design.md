# BPS AI Assistant Documentation Design

**Tanggal:** 18 Agustus 2026  
**Status:** Disetujui  
**Branch:** `worktree-resume-task7`  
**Target:** Draft PR #1

## 1. Tujuan

Menyediakan dokumentasi lengkap dan konsisten untuk BPS AI Assistant setelah integrasi BPS WebAPI resmi selesai. Dokumentasi harus melayani tiga kelompok pembaca:

1. pengunjung GitHub yang ingin memahami nilai produk dalam beberapa menit;
2. reviewer, pembimbing, atau stakeholder yang ingin mengetahui seluruh riwayat pekerjaan dan keputusan implementasi;
3. developer yang perlu memahami arsitektur, alur runtime, konfigurasi, pengujian, keamanan, dan operasional aplikasi.

## 2. Deliverables

Tiga file Markdown baru harus dibuat:

1. `README.md`
2. `docs/PROJECT_HISTORY.md`
3. `docs/TECHNICAL_WORKFLOW.md`

Semua file menggunakan Bahasa Indonesia sebagai bahasa utama. Nama class, method, interface, endpoint, environment variable, command, dan istilah teknis tetap menggunakan bentuk aslinya.

## 3. Prinsip Penulisan

- Lengkap dan mandiri: pembaca tidak perlu membuka chat history untuk memahami proyek.
- Akurat terhadap implementasi pada branch final dan tidak menjelaskan fitur hipotetis sebagai fitur yang sudah tersedia.
- Tidak memuat credential, API key, token, password, atau nilai secret nyata.
- Setiap klaim pengujian harus memakai hasil yang benar-benar telah dijalankan.
- Mermaid digunakan untuk diagram arsitektur, sequence, routing, cache, citation, dan error flow.
- Referensi file dan class harus menggunakan path aktual di repository.
- Perubahan keputusan selama implementasi harus dijelaskan, termasuk alasan mengganti raw manual tool loop dengan native Laravel AI SDK loop.
- Keterbatasan harus ditulis secara eksplisit, terutama endpoint glosarium live yang tidak tersedia dan numeric query historis yang dapat menghasilkan `no_evidence`.

## 4. Desain `README.md`

### 4.1 Arah visual

Gaya **Modern AI Product**:

- hero terpusat menggunakan HTML yang kompatibel dengan GitHub Markdown;
- judul produk dan slogan singkat;
- badge teknologi, build, tests, dan status integrasi;
- navigasi internal yang mudah dipindai;
- section heading yang konsisten;
- tabel dan Mermaid tanpa bergantung pada aset eksternal wajib.

### 4.2 Struktur isi

1. Hero dan badges.
2. Ringkasan produk.
3. Problem yang diselesaikan.
4. Feature highlights.
5. Diagram singkat cara kerja.
6. Teknologi utama.
7. Quick start lengkap.
8. Environment variables dengan placeholder aman.
9. Menjalankan aplikasi.
10. Contoh API request dan response.
11. Ringkasan keluarga tool BPS.
12. Security dan privacy.
13. Testing dan hasil verifikasi.
14. Artisan operations.
15. Struktur proyek ringkas.
16. Keterbatasan dan catatan produksi.
17. Link ke `PROJECT_HISTORY.md` dan `TECHNICAL_WORKFLOW.md`.
18. Status proyek dan kontribusi.

### 4.3 Badge

Badge tidak boleh mengklaim CI eksternal bila workflow CI belum tersedia. Badge statis yang diizinkan:

- PHP 8.3+
- Laravel 13
- Laravel AI SDK
- BPS WebAPI
- Tests 111 / 105 default pass + 6 live-gated
- License, hanya bila license repository dapat diverifikasi

## 5. Desain `docs/PROJECT_HISTORY.md`

### 5.1 Tujuan

Merekam kronologi pekerjaan dari kondisi awal sampai implementasi final dan live validation. Dokumen ini berfungsi sebagai development journal, audit trail, dan materi handover.

### 5.2 Struktur isi

1. Executive summary.
2. Kondisi awal proyek.
3. Arsitektur demo awal.
4. Masalah awal yang ditemukan.
5. Perbaikan end-to-end demo.
6. Eksplorasi BPS WebAPI.
7. Verifikasi auth path-segment dan response shape.
8. Keputusan arsitektur hybrid live + cache + fallback.
9. Penyusunan spec dan implementation plan.
10. Kronologi Task 1–15.
11. Tabel commit implementasi dan tujuan setiap commit.
12. TDD RED/GREEN per fase.
13. Code review findings dan hardening.
14. Runtime failures yang ditemukan saat live validation.
15. Root cause dan solusi untuk SQLite cache, CA bundle, final synthesis, execution budget, dan endpoint glosarium.
16. Hasil smoke validation.
17. Hasil strict live integration S1–S6.
18. Hasil HTTP end-to-end.
19. Branch, push, dan draft PR.
20. Keputusan yang berubah selama pengerjaan.
21. Pelajaran teknis.
22. Outstanding operational notes.

### 5.3 Data verifikasi yang harus dicatat

- default suite: 111 discovered, 105 passed, 6 live-gated skipped, 353 assertions;
- strict live suite: 6 passed, 32 assertions;
- targeted final regression: 17 passed, 48 assertions;
- Vite production build berhasil;
- full Pint berhasil;
- secret scan pada client assets dan views tidak menemukan credential;
- live preload: 549 domain, 16 indikator nasional, 1.744 variabel nasional, 10 indikator Jawa Barat, 612 variabel Jawa Barat;
- HTTP publication flow: answered dengan citation BPS `verified:true`;
- HTTP definition flow: fallback `.md` dengan citation `verified:false` karena glosarium live unavailable;
- HTTP numeric historical flow dapat berakhir `no_evidence` secara aman.

## 6. Desain `docs/TECHNICAL_WORKFLOW.md`

### 6.1 Tujuan

Menjadi referensi teknis utama untuk memahami dan memelihara chatbot tanpa harus membaca seluruh source code terlebih dahulu.

### 6.2 Struktur isi

1. System context.
2. Architectural goals dan constraints.
3. High-level component diagram.
4. Daftar komponen dan tanggung jawab.
5. Request lifecycle dari browser ke response.
6. Scope classification dan intent routing.
7. Numeric clarification flow.
8. Feature flag dan fallback decision.
9. BPS tool registry mapping.
10. Katalog 25 tools, endpoint, parameter, dan output.
11. Native Laravel AI tool execution loop.
12. `BudgetedTool`, `ToolCappedAgent`, dan final synthesis.
13. Execution time budget.
14. BPS API client URL construction dan auth.
15. Cache v2 dan 24-hour cache lifecycle.
16. Recursive credential redaction.
17. Response normalization dan bounded outputs.
18. Citation collection dan filtering.
19. API response serialization `verified`.
20. Error handling per layer.
21. Windows/XAMPP TLS and CA resolution.
22. Service provider registration dan request-scoped state.
23. Internal API contracts.
24. Environment variables.
25. Artisan commands.
26. Testing strategy.
27. Live integration scenarios.
28. Security boundaries.
29. Operational runbook.
30. Troubleshooting matrix.
31. Known limitations.
32. Extension guide.

### 6.3 Diagram Mermaid wajib

- component architecture;
- `/api/chat` sequence;
- intent routing flowchart;
- tool-loop sequence;
- cache read/write flow;
- citation trust flow;
- error/fallback flow.

## 7. Sumber Fakta

Dokumentasi harus disusun dari sumber berikut:

- source code branch `worktree-resume-task7`;
- commit history branch tersebut;
- test suite dan output verifikasi final;
- live BPS WebAPI probes dan live integration test output;
- draft PR #1;
- memory proyek yang sudah diverifikasi;
- session transcript hanya untuk kronologi yang tidak direkam oleh Git.

Source code dan hasil test lebih tinggi prioritasnya daripada implementation plan lama bila terdapat perbedaan.

## 8. Keamanan Dokumentasi

Dokumentasi dilarang memuat:

- BPS WebAPI key aktual;
- LimitRouter API key aktual;
- credential helper output;
- isi `.env`;
- stack trace yang mengandung URL dengan key;
- local path yang tidak relevan bagi pengguna repository.

Contoh environment variable harus menggunakan placeholder seperti:

```dotenv
BPS_WEBAPI_KEY=your_bps_webapi_key_here
LIMITROUTER_API_KEY=your_limitrouter_key_here
```

## 9. Validasi

Setelah tiga file dibuat:

1. pastikan ketiga file ada dan tidak kosong;
2. scan placeholder `TBD`, `TODO`, dan teks yang tidak lengkap;
3. scan credential literal dan nama environment variable pada area yang tidak semestinya;
4. validasi link relatif antarfile;
5. validasi setiap Mermaid block memiliki opening dan closing fence;
6. cocokkan jumlah tool dan nama class dengan source code;
7. cocokkan command dan environment variable dengan config/source;
8. jalankan default full test suite;
9. jalankan full Pint;
10. jalankan Vite build;
11. commit dan push ke branch `worktree-resume-task7`;
12. pastikan draft PR #1 ikut terbarui.

## 10. Acceptance Criteria

Dokumentasi dianggap selesai bila:

- ketiga file tersedia pada lokasi yang disepakati;
- README tampil terstruktur dan menarik pada GitHub tanpa aset wajib yang rusak;
- kronologi mencakup pekerjaan dari kondisi awal sampai draft PR final;
- technical workflow cukup lengkap untuk developer baru mengikuti alur request dan memodifikasi tool;
- tidak ada secret nyata;
- tidak ada placeholder atau bagian terpotong;
- seluruh klaim test dan live validation sesuai evidence;
- branch bersih setelah commit;
- commit sudah dipush dan muncul pada draft PR #1.
