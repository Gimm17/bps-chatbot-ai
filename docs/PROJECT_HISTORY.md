# Riwayat Pengembangan BPS AI Assistant

> Dokumentasi kronologis pengembangan aplikasi dari demo knowledge-base lokal sampai integrasi BPS WebAPI resmi, live validation, dan draft Pull Request.

## Daftar Isi

- [Ringkasan Eksekutif](#ringkasan-eksekutif)
- [Kondisi Awal](#kondisi-awal)
- [Arsitektur Demo Awal](#arsitektur-demo-awal)
- [Masalah Awal yang Ditemukan](#masalah-awal-yang-ditemukan)
- [Pemulihan Demo End-to-End](#pemulihan-demo-end-to-end)
- [Eksplorasi BPS WebAPI](#eksplorasi-bps-webapi)
- [Keputusan Arsitektur](#keputusan-arsitektur)
- [Tahapan Pengembangan](#tahapan-pengembangan)
- [TDD, Review, dan Hardening](#tdd-review-dan-hardening)
- [Masalah dan Root Cause](#masalah-dan-root-cause)
- [Hasil Verifikasi](#hasil-verifikasi)
- [Commit dan Pull Request](#commit-dan-pull-request)
- [Keputusan yang Berubah](#keputusan-yang-berubah)
- [Pelajaran Teknis](#pelajaran-teknis)
- [Catatan Operasional](#catatan-operasional)

---

## Ringkasan Eksekutif

BPS AI Assistant bermula sebagai aplikasi demo Laravel yang menjawab pertanyaan BPS dari beberapa dokumen Markdown lokal. Knowledge base tersebut sengaja diberi penanda `DEMO_NOT_VERIFIED`, sehingga jawaban cocok untuk demonstrasi alur RAG tetapi belum boleh diposisikan sebagai data resmi dan aktual.

Pekerjaan kemudian berkembang menjadi integrasi BPS WebAPI resmi dengan pendekatan agentic tool use. Aplikasi kini dapat:

- mengklasifikasikan intent pertanyaan;
- meminta klarifikasi ketika wilayah atau periode numerik belum lengkap;
- memilih subset tool BPS yang relevan;
- memanggil BPS WebAPI hanya dari server;
- membatasi jumlah tool call;
- menyimpan respons sukses dalam cache 24 jam;
- menyensor credential secara rekursif sebelum respons diproses atau disimpan;
- mengumpulkan citation hanya dari field resmi backend;
- menyaring source ID yang dibuat-buat oleh model;
- menyerialisasi penanda `verified` ke client;
- mempertahankan fallback `.md` ketika integrasi live dinonaktifkan, key tidak tersedia, intent tidak memiliki tool stabil, atau endpoint live tidak dapat diandalkan.

Implementasi akhir mencakup 25 concrete BPS tools, native Laravel AI tool loop, BPS agent orchestration, feature-flag routing, operational Artisan commands, unit/feature/live tests, smoke validation, dan HTTP end-to-end validation. Seluruh pekerjaan berada pada branch `worktree-resume-task7` dan draft Pull Request [#1](https://github.com/Gimm17/bps-chatbot-ai/pull/1).

---

## Kondisi Awal

### Platform aplikasi

Kondisi awal aplikasi terdiri dari:

- PHP 8.3+;
- Laravel 13;
- Laravel AI SDK;
- LimitRouter melalui driver `openai-compatible`;
- model default yang dikonfigurasi melalui environment variable;
- SQLite untuk kebutuhan database lokal;
- Laravel HTTP client untuk koneksi eksternal;
- UI chat berbasis Blade dan JavaScript;
- endpoint internal `/api/chat`, `/api/health`, `/api/models`, dan `/api/feedback`.

### Sumber pengetahuan

Sumber jawaban statistik berasal dari:

```text
data/knowledge/*.md
```

File tersebut dimuat oleh `KnowledgeLoader`, kemudian dicari secara lexical oleh `DemoLexicalRetriever`. Setiap sumber lokal dipetakan menjadi `RetrievedSource`, lalu citation dibentuk oleh `Citation::fromSources()`.

Semua citation jalur demo memiliki:

```text
verified = false
```

Hal ini disengaja karena knowledge base lokal adalah materi demonstrasi, bukan hasil query langsung ke BPS WebAPI.

---

## Arsitektur Demo Awal

Alur awal `ChatService` adalah:

1. menghasilkan `requestId`;
2. melakukan scope classification melalui `ScopeGuard`;
3. mengembalikan `out_of_scope` jika pertanyaan jelas di luar domain;
4. meminta klarifikasi untuk statistik numerik yang belum menyebut wilayah/periode;
5. mengambil maksimal empat sumber melalui `RetrieverInterface`;
6. menghentikan jawaban jika evidence kosong;
7. membangun system instructions dan evidence block melalui `PromptBuilder`;
8. memanggil `AiProviderInterface::chat()`;
9. mem-parse JSON model melalui `ChatResult::parse()`;
10. memetakan source ID model ke citation dari trusted registry;
11. mengembalikan `ChatResponse` ke browser.

Boundary penting sudah tersedia sejak awal:

- browser tidak pernah melihat provider key;
- aplikasi core tidak berbicara langsung dengan LimitRouter;
- model hanya memilih source ID, bukan menentukan URL citation;
- invalid output tidak langsung dipercaya;
- out-of-scope, clarification, no-evidence, dan provider error memiliki status terpisah.

---

## Masalah Awal yang Ditemukan

### 1. Bentuk message untuk Laravel AI SDK

Provider awal sempat memperlakukan pesan seperti array biasa. Versi Laravel AI SDK yang terpasang membutuhkan `Laravel\Ai\Messages\Message` objects dengan `MessageRole` yang valid.

Perbaikan dilakukan dengan memastikan `ChatProviderInput::$messages` berisi `list<Message>` dan `PromptBuilder::buildMessages()` menghasilkan object yang benar.

### 2. cURL error 60 pada Windows/XAMPP

Outbound HTTPS dari PHP gagal karena instalasi XAMPP tidak memiliki CA bundle sistem yang dapat ditemukan cURL/OpenSSL. Masalah ini terlihat sebagai:

```text
cURL error 60: SSL certificate problem: unable to get local issuer certificate
```

Solusi awal:

- menyimpan `cacert.pem` di `storage/app/ca/cacert.pem`;
- membaca `CURL_CA_BUNDLE` dari environment;
- mengarahkan Laravel HTTP client ke file tersebut;
- menambahkan bootstrap handling untuk cURL/OpenSSL pada Windows.

Implementasi ini kemudian diperkeras lagi saat live HTTP validation menemukan perbedaan antara relative dan absolute CA path.

### 3. Knowledge base belum terverifikasi

Demo sudah menjawab pertanyaan umum, tetapi tidak memiliki jaminan bahwa angka, publikasi, periode, atau metadata selalu sesuai data BPS terbaru. Inilah alasan utama migrasi ke BPS WebAPI.

---

## Pemulihan Demo End-to-End

Sebelum integrasi WebAPI dimulai, flow demo dipastikan bekerja melalui beberapa scenario:

- definition;
- numeric clarification;
- out-of-scope;
- prompt injection safety;
- health endpoint;
- invalid input rejection.

Perbaikan penting pada tahap ini:

- message object sesuai Laravel AI SDK;
- Windows CA bundle tersedia;
- system prompt tetap server-side;
- fallback output aman;
- citation demo tetap `verified:false`;
- request validation dan rate limiting tetap aktif.

Setelah baseline stabil, integrasi BPS WebAPI dapat dilakukan tanpa kehilangan alur demo yang sudah berfungsi.

---

## Eksplorasi BPS WebAPI

### Dokumentasi dan live probes

BPS WebAPI dieksplorasi melalui dokumentasi resmi dan request live. Beberapa fakta penting ditemukan:

1. key mayoritas endpoint digunakan sebagai **path segment**:

   ```text
   /key/{api-key}
   ```

2. endpoint `dataexim` menggunakan key sebagai query parameter;
3. BPS dapat mengembalikan HTTP 200 dengan body:

   ```json
   {
     "status": "Error",
     "message": "Parameter X is Missing."
   }
   ```

   sehingga aplikasi wajib memeriksa body status, bukan hanya HTTP status;
4. response list umumnya berbentuk:

   ```json
   {
     "status": "OK",
     "data-availability": "available",
     "data": [
       {"page": 1, "pages": 1, "total": 10},
       [{"id": "..."}]
     ]
   }
   ```

5. dynamic data memiliki metadata top-level seperti `subject`, `var`, `labelvervar`, `vervar`, `turvar`, `tahun`, `turtahun`, dan `datacontent`;
6. publication detail menyediakan `pdf`, sehingga URL citation dapat berasal dari field resmi tersebut;
7. interoperabilitas Sensus dan SIMDASI dapat membawa nested business error walaupun outer body berstatus `OK`;
8. endpoint glosarium live tidak stabil/tidak tersedia pada validasi akhir.

### Keluarga endpoint yang diverifikasi

- domain;
- subject dan subcategory;
- variables, units, periods, turunan variable/periode;
- indicators;
- dynamic data;
- publication, press release, static table, infographic, SDGs;
- foreign trade (`dataexim`);
- Sensus interoperability;
- SIMDASI interoperability.

---

## Keputusan Arsitektur

### Hybrid live + cache + fallback

Arsitektur yang dipilih bukan live-only. Tiga sumber digunakan dengan prioritas berbeda:

1. **BPS WebAPI live** untuk intent yang memiliki tool stabil;
2. **cache 24 jam** untuk mengurangi latency dan request berulang;
3. **knowledge base `.md`** sebagai fallback saat feature dinonaktifkan, key kosong, intent tidak memiliki tool, atau endpoint tertentu tidak stabil.

### Intent-based tool subset

Semua 25 tool tidak diberikan ke model pada setiap request. `BpsToolRegistry` memetakan intent ke subset yang relevan agar:

- context tool schema lebih kecil;
- model lebih mudah memilih tool;
- jumlah tool call terbatas tetap cukup;
- risiko pemanggilan endpoint tidak relevan berkurang.

### Native Laravel AI loop

Implementation plan awal mengusulkan raw manual `/chat/completions` loop. Setelah dependency aktual diperiksa, Laravel AI SDK ternyata menyediakan:

- `TextGenerationLoop`;
- `TextGenerationOptions::$maxSteps`;
- agent method `maxSteps()`;
- native tool serialization dan execution;
- message/tool-result history.

Karena itu raw protocol loop dibatalkan. Implementasi akhir menggunakan native SDK melalui:

- `ToolCappedAgent` untuk step budget;
- `BudgetedTool` untuk hard cap jumlah eksekusi tool, termasuk parallel calls;
- satu synthesis call tanpa tools ketika step terakhir masih berupa tool call.

### Trusted citation pipeline

Model tidak boleh membuat citation object. Tool result resmi dibungkus oleh `CitationCollectingTool`, kemudian:

1. source fields resmi diekstrak menjadi `BpsCitation`;
2. metadata `_citations` diberikan kepada model;
3. model hanya mengembalikan `citationSourceIds`;
4. backend menyimpan citation berdasarkan source ID;
5. `Citation::fromBpsSources()` menolak unknown ID;
6. `ChatResponse` menyerialisasi `verified:true` untuk citation BPS.

---

## Tahapan Pengembangan

## Fase Foundation

### Task 1 — Konfigurasi BPS WebAPI

**Tujuan:** menyediakan configuration boundary dan feature flag.

File utama:

- `config/bps.php`;
- `.env.example`.

Konfigurasi meliputi:

- `BPS_ENABLED`;
- `BPS_WEBAPI_KEY`;
- base URL;
- cache enabled dan TTL;
- agent max tool calls;
- agent timeout;
- HTTP timeout;
- live-test flag.

Boolean environment diproses dengan `filter_var(..., FILTER_VALIDATE_BOOLEAN)` untuk mencegah string `"false"` berubah menjadi truthy.

### Task 2 — `BpsResponse`

**Tujuan:** normalisasi response BPS menjadi DTO konsisten.

`BpsResponse` menyimpan:

- `isOk`;
- `rows`;
- `raw`;
- `pages`;
- `total`;
- `errorMessage`;
- `httpStatus`.

DTO mendukung JSON roundtrip untuk cache. Metadata dynamic top-level dipertahankan melalui property `raw`.

### Task 3 — `BpsApiException`

Exception khusus dibuat agar timeout/network error dapat dibedakan dari non-OK business response. Tool menangkap exception ini dan mengembalikan JSON error aman kepada model.

### Task 4 — `BpsApiClient`

`BpsApiClient` menjadi satu-satunya komponen yang membangun URL dan menyentuh BPS WebAPI.

Fitur:

- path-segment key auth;
- query key auth untuk dataexim;
- literal semicolon preservation untuk multi-HS code;
- timeout;
- cache read/write;
- hanya response sukses yang disimpan;
- exception sanitization;
- raw payload preservation.

### Task 5 — Citation DTO dan mapping

`BpsCitation` menyimpan citation resmi. `Citation::fromBpsSources()`:

- menerima source map backend;
- menerima source ID pilihan model;
- menolak ID kosong, duplicate, non-string, dan unknown;
- menghasilkan citation `verified:true`.

### Task 6 — `BpsToolRegistry`

Registry memetakan intent ke tool classes. Skeleton registry dibuat sebelum semua class tool tersedia, kemudian registry test dijalankan ulang setelah Task 8.

---

## Fase Tooling

### Task 7 — Empat core tools

Empat tool awal:

1. `ListDomainsTool`;
2. `ListVarsTool`;
3. `GetGlosariumTool`;
4. `GetDynamicDataTool`.

Pada tahap ini ditemukan bahwa accessor Laravel AI `Request` yang benar untuk mengambil seluruh argument adalah:

```php
$request->all();
```

Core tool kemudian diperkeras dengan:

- output limit 100;
- `total`, `returned`, `truncated`;
- enum schema;
- malformed-row guard;
- scalar `datacontent` guard;
- metadata `subject` dan `labelvervar`;
- safe JSON error response.

### Task 8 — 21 remaining tools

Tool yang ditambahkan dibagi dalam keluarga berikut.

#### Catalog

- `ListSubjectsTool`
- `ListSubcatsTool`
- `ListVervarsTool`
- `ListPeriodsTool`
- `ListTurvarsTool`
- `ListTurthsTool`
- `ListUnitsTool`
- `ListIndicatorsTool`

#### Publication dan content

- `ListPublicationsTool`
- `GetPublicationTool`
- `ListPressreleasesTool`
- `GetPressreleaseTool`
- `ListStatictablesTool`
- `GetStatictableTool`
- `ListInfographicsTool`
- `ListSdgsTool`

#### Trade dan interoperability

- `DataeximTool`
- `SensusListEventsTool`
- `SensusDataTool`
- `SimdasiTablesTool`
- `SimdasiDetailTool`

`AbstractBpsTool` digunakan untuk shared behavior:

- bounded list output;
- detail output;
- error envelope;
- optional parameter filtering;
- nested interoperability error detection.

---

## Fase Agentic AI

### Task 9 — Native tool loop dan hard cap

`AiProviderInterface` mendapat method:

```php
chatWithTools(ChatProviderInput $input, iterable $tools, int $maxToolCalls = 4)
```

Implementasi menggunakan:

- `ToolCappedAgent` dengan `maxSteps()`;
- `BudgetedTool` dengan shared counter;
- native Laravel AI SDK tool execution.

Cap tool call berlaku untuk sequential maupun parallel tool calls. `maxSteps` sendiri tidak cukup karena satu step dapat meminta beberapa tools, sehingga wrapper budget tetap diperlukan.

### Task 10 — `BpsAgent`

`BpsAgent` mengatur:

- pemilihan tool subset dari registry;
- pembuatan `ChatProviderInput`;
- pemanggilan provider;
- parsing `ChatResult`;
- citation collection;
- reset state per request;
- provider exception fallback ke `no_evidence`.

`BpsAgent` didaftarkan scoped, bukan singleton, karena memiliki mutable `collectedSources`.

### Task 11 — Prompt tool-use rules

`PromptBuilder` diperbarui agar:

- menggunakan tool ketika tersedia untuk fakta/angka resmi;
- tidak menjawab angka dari memori;
- tidak menebak ID subject/variable/period;
- hanya memakai `_citations` atau EVIDENCE source IDs;
- meminta klarifikasi sebelum parameter tebakan;
- mengembalikan `no_evidence` ketika data resmi tidak cukup;
- tetap mendukung jalur `.md` dengan EVIDENCE block.

### Task 12 — Feature-flagged ChatService routing

`ChatService` mendapat branch setelah clarification dan sebelum retrieval:

- jika BPS enabled, key tersedia, dan intent memiliki tools, gunakan `BpsAgent`;
- jika agent mengembalikan `null`, lanjutkan ke `.md` fallback;
- jika BPS disabled/key kosong, langsung gunakan fallback;
- citation BPS dan citation demo dipetakan melalui fungsi yang berbeda.

`ChatService` juga didaftarkan scoped agar tidak menangkap `BpsAgent` lama pada worker/Octane lifecycle.

---

## Fase Operasional dan Validasi

### Task 13 — Artisan commands

Dua command ditambahkan:

```bash
php artisan bps:preload
php artisan bps:clear-cache
```

`bps:preload` memanaskan:

- domain list;
- indikator nasional;
- variabel nasional;
- indikator Jawa Barat;
- variabel Jawa Barat.

`bps:clear-cache` menjalankan `Cache::flush()` dan hanya aman jika cache store aplikasi didedikasikan untuk BPS/demo.

### Task 14 — Smoke validation

Smoke gate mencakup:

- full Pint;
- full unit tests;
- existing feature regression;
- Vite production build;
- secret scan di `public/`, `resources/js`, dan `resources/views`;
- command discovery;
- `git diff --check`.

### Task 15 — Live integration dan HTTP end-to-end

Enam live scenarios dibuat dalam `tests/Feature/BpsChatFlowTest.php` dan digate dengan:

```dotenv
BPS_LIVE_TESTS=true
```

Scenario:

1. definition inflasi;
2. numeric inflasi Jawa Barat 2023;
3. clarification;
4. out-of-scope;
5. injection/no credential leak;
6. publication listing.

Selain PHPUnit live suite, aplikasi dijalankan melalui PHP built-in server dari directory `public`, lalu `/api/chat` dipanggil seperti client sebenarnya.

---

## TDD, Review, dan Hardening

Setiap perubahan perilaku mengikuti pola:

1. tulis regression test;
2. jalankan dan pastikan gagal karena behavior belum tersedia;
3. implementasi minimum;
4. jalankan targeted test;
5. jalankan regression suite;
6. jalankan Pint dan diff check;
7. commit.

Contoh RED yang menangkap defect nyata:

- legacy cache masih terbaca;
- key muncul pada nested raw payload;
- `datacontent` scalar diperlakukan sebagai row;
- output melebihi 100;
- glosarium row non-array menyebabkan type error;
- nested SIMDASI error dianggap sukses;
- tool loop berhenti tanpa final synthesis;
- zero-cap masih mencoba tool;
- citation state bocor antarrequest;
- absolute CA path digabung ulang dengan base path;
- web SAPI timeout lebih pendek dari agent flow;
- `verified` tidak sampai ke JSON client.

Review juga menghasilkan beberapa simplifikasi:

- slice sebelum map untuk mengurangi kerja;
- native Laravel AI loop menggantikan raw HTTP loop;
- satu helper tool base untuk 21 tool serupa;
- request-scoped services untuk state isolation;
- katalog preload menggantikan hardcoded variable ID yang belum stabil.

---

## Masalah dan Root Cause

### 1. Worktree tidak memiliki Composer runtime lengkap

**Gejala:** `vendor/autoload.php` atau PHPUnit executable tidak ditemukan.

**Root cause:** linked worktree tidak membawa seluruh artifact gitignored/partial vendor dari checkout lain.

**Solusi:** menjalankan `composer install` dan targeted `composer reinstall phpunit/phpunit` pada worktree.

### 2. Implementation plan tidak ada pada branch

**Gejala:** file plan/spec lama tidak ditemukan di worktree.

**Root cause:** plan awal pernah dibuat sebagai file untracked di lokasi induk dan tidak ikut commit.

**Solusi:** isi plan dipulihkan dari transcript session JSONL dan memory project, lalu implementasi diverifikasi terhadap source final.

### 3. SQLite database dan cache table hilang

**Gejala:** live `bps:preload` gagal dengan `Illuminate\Database\QueryException`.

**Root cause:** `.env` memakai `CACHE_STORE=database` dan `DB_CONNECTION=sqlite`, tetapi `database/database.sqlite` gitignored tidak ada pada worktree.

**Solusi:** membuat SQLite file dan menjalankan migrations. Cache write kemudian diverifikasi langsung sebelum preload diulang.

### 4. CA bundle hilang pada worktree

**Gejala:** BPS/LimitRouter HTTPS gagal pada worktree walaupun checkout utama bekerja.

**Root cause:** `storage/app/ca/cacert.pem` gitignored sehingga tidak otomatis tersedia di linked worktree.

**Solusi:** menyalin CA bundle yang sama ke runtime worktree dan memverifikasi SHA-256 identik.

### 5. Absolute CA path diproses sebagai relative

**Gejala:** CLI provider call berhasil, tetapi HTTP server tetap menghasilkan cURL error 60.

**Root cause:** bootstrap mengubah `CURL_CA_BUNDLE` menjadi absolute path. `AppServiceProvider` kemudian memanggil `basePath($ca)` lagi sehingga menghasilkan path ganda yang tidak ada.

**Solusi:** `AppServiceProvider::resolveCaPath()` mencoba `realpath($ca)` lebih dulu, kemudian relative `basePath($ca)`. Unit test mencakup absolute dan relative path.

### 6. Final synthesis hilang setelah tool cap

**Gejala:** semua step berisi tool calls; model tidak pernah menghasilkan final JSON text; provider mengembalikan `no_evidence` kosong.

**Root cause:** native loop mencapai final step dan menolak eksekusi tool terakhir, tetapi tidak otomatis membuat no-tool synthesis turn.

**Solusi:** ketika response text kosong, `LimitRouterProvider` membuat satu `ToolCappedAgent` baru dengan history lengkap dan `tools: []`, step limit 1. Tidak ada tool tambahan yang dapat melewati cap.

### 7. Execution budget salah diasumsikan satu request

**Gejala:** HTTP 500 `Maximum execution time of 30/65 seconds exceeded` saat numeric/publication live flow.

**Root cause:** provider timeout adalah per model request, sedangkan satu agent flow dapat melakukan beberapa tool-loop requests dan satu synthesis request.

**Solusi:** total bounded execution ceiling dihitung:

```text
(maxToolCalls + 2) × timeoutSec + 5
```

Dengan cap 4 dan timeout 60, ceiling menjadi 365 detik.

### 8. Glosarium endpoint live tidak tersedia

**Gejala:** semua variasi `GetGlosariumTool` live mengembalikan error atau HTTP 500.

**Root cause:** endpoint glosarium saat validasi tidak dapat diandalkan, bukan masalah tool schema saja.

**Solusi:** intent `definition` dikembalikan ke `.md` fallback. `GetGlosariumTool` tetap ada sebagai implementasi, tetapi tidak dimasukkan registry aktif sampai endpoint stabil.

### 9. Numeric discovery menghabiskan cap

**Gejala:** model menggunakan beberapa call untuk list variable/indicator, lalu dynamic-data call terjadi pada step yang sudah diblok cap.

**Root cause:** registry numeric belum menyediakan `ListPeriodsTool`, dan prompt belum cukup tegas melarang tebak ID.

**Solusi:** menambahkan `ListPeriodsTool` ke subset numeric dan prompt rule "Jangan menebak ID". Jika evidence historis tetap tidak selesai, response aman adalah `no_evidence`.

### 10. Verified flag tidak terlihat client

**Gejala:** object citation backend benar `verified:true`, tetapi HTTP JSON tidak memuat field tersebut.

**Root cause:** `ChatResponse::jsonSerialize()` hanya mengirim source ID, title, URL, dan snippet.

**Solusi:** menambahkan `verified` ke serialized citation dan regression test.

### 11. Mutable citation state pada singleton

**Gejala potensial:** citation dari request sebelumnya dapat tertinggal pada long-running worker.

**Root cause:** `BpsAgent` menyimpan `collectedSources` dan semula didaftarkan singleton.

**Solusi:** reset source pada setiap run dan mendaftarkan `BpsAgent` serta `ChatService` sebagai scoped services.

### 12. Nested interoperability error

**Gejala:** SIMDASI outer response `status:OK`, tetapi row pertama memiliki `condition:ERROR` dan `status:400`.

**Root cause:** helper hanya memeriksa outer `BpsResponse::isOk`.

**Solusi:** `AbstractBpsTool` mendeteksi nested `condition:ERROR` dan mengembalikan tool error JSON.

---

## Hasil Verifikasi

### Default regression gate

| Pemeriksaan | Hasil |
|---|---:|
| Test ditemukan | 111 |
| Test passed default | 105 |
| Live-gated skipped | 6 |
| Assertions default | 353 |
| Laravel Pint | Passed |
| Vite production build | Passed |
| Client-side secret scan | No matches |
| `git diff --check` | Passed |

### Targeted regression akhir

| Pemeriksaan | Hasil |
|---|---:|
| Targeted tests | 17 passed |
| Assertions | 48 |

### Strict live integration

| Pemeriksaan | Hasil |
|---|---:|
| Live scenarios | 6 passed |
| Assertions | 32 |
| Durasi final | sekitar 150 detik |

### Live preload

| Dataset | Row |
|---|---:|
| Domain | 549 |
| Indikator nasional | 16 |
| Variabel nasional | 1.744 |
| Indikator Jawa Barat | 10 |
| Variabel Jawa Barat | 612 |

### HTTP end-to-end

| Scenario | HTTP | Status | Citation |
|---|---:|---|---|
| Definition inflasi | 200 | `answered` | fallback `.md`, `verified:false` |
| Numeric inflasi Jabar 2023 | 200 | `no_evidence` aman | tidak mengarang citation |
| Missing geography/period | 200 | `clarification_required` | tidak ada citation |
| Puisi cinta | 200 | `out_of_scope` | tidak ada citation |
| Prompt injection/key request | 200 | `out_of_scope` | tidak ada credential/citation leak |
| Publication terbaru | 200 | `answered` | 10 citation BPS, seluruhnya `verified:true` |

---

## Commit dan Pull Request

### Commit implementasi utama

| Commit | Tujuan |
|---|---|
| `58665e0` | preserve raw responses dan 4 core BPS tools |
| `09c2c84` | gunakan `Request::all()` untuk Laravel AI tool arguments |
| `505b68d` | hardening core tool responses |
| `8a254b1` | 21 remaining BPS WebAPI tools |
| `6229deb` | native tool-use execution cap |
| `0bee054` | BPS agent orchestration dan citation collection |
| `98e2c1b` | BPS tool-use prompt rules |
| `5b80d53` | intent routing ke BPS tools dan fallback |
| `6dbd1b8` | cache preload/clear commands |
| `3014b18` | smoke validation formatting |
| `4af9629` | live WebAPI chat flow tests dan runtime fixes |

Commit setelah `4af9629` menambahkan design spec dan implementation plan dokumentasi.

### Branch dan PR

- branch: `worktree-resume-task7`;
- remote: `origin/worktree-resume-task7`;
- base: `main`;
- draft PR: [#1 — Integrate official BPS WebAPI tool-use flow](https://github.com/Gimm17/bps-chatbot-ai/pull/1).

Tidak ada merge atau force-push yang dilakukan selama pekerjaan.

---

## Keputusan yang Berubah

### Manual raw tool loop menjadi native SDK loop

Rencana awal menganggap Laravel AI SDK tidak menyediakan public step cap. Dependency aktual memperlihatkan `MaxSteps` dan `TextGenerationLoop`. Native SDK dipilih agar aplikasi tidak menduplikasi:

- schema serialization;
- tool call parsing;
- message construction;
- provider failover;
- tool-result history.

Hard cap eksekusi tetap ditambahkan melalui `BudgetedTool` karena max steps tidak membatasi jumlah parallel calls.

### Definition live menjadi fallback

Tujuan awal adalah menggunakan glosarium live untuk definition. Validasi nyata menunjukkan endpoint tidak stabil. Daripada mengarang atau melakukan retry tanpa akhir, definition diarahkan ke knowledge `.md` yang sudah tersedia dan diberi `verified:false`.

### Preload variable ID menjadi preload katalog

Rencana awal mempertimbangkan hardcoded top variable IDs. Karena ID dan periode harus diverifikasi per domain, preload diubah menjadi katalog domain/indicator/variable. Ini mengurangi asumsi dan tetap mempercepat discovery.

### Timeout per-call menjadi total bounded execution budget

Config timeout awal dianggap cukup sebagai total request budget. Runtime membuktikan satu agent run memiliki beberapa provider calls. Formula total bounded budget kemudian dibuat eksplisit dan diuji.

---

## Pelajaran Teknis

1. **Source final harus mengalahkan implementation plan lama.** Dependency dan endpoint live dapat berubah setelah rencana ditulis.
2. **HTTP 200 bukan selalu sukses.** BPS business status dan nested interoperability status tetap harus diperiksa.
3. **Step cap bukan tool-call cap.** Parallel tool calls membutuhkan shared execution counter.
4. **Tool result harus bounded.** Membatasi 100 row mencegah LLM context membengkak dan memberi metadata truncation eksplisit.
5. **Citation adalah backend security boundary.** Model boleh memilih ID, tetapi tidak boleh menentukan URL atau verified state.
6. **Long-running worker mengubah lifecycle assumptions.** Mutable request state harus scoped dan di-reset.
7. **Windows TLS memerlukan path handling yang benar.** Relative dan absolute CA path sama-sama harus didukung.
8. **Worktree tidak membawa runtime artifacts.** `.env`, SQLite, CA bundle, vendor, node_modules, dan build output perlu disiapkan per worktree.
9. **Runtime validation menemukan bug yang unit test tidak temukan.** Final synthesis, server timeout, router cwd, dan absolute CA path ditemukan pada HTTP surface.
10. **`no_evidence` adalah hasil aman, bukan selalu kegagalan.** Jika data resmi tidak cukup dalam batas tool/timeout, aplikasi harus menolak mengarang.

---

## Catatan Operasional

### Sebelum production

- regenerasi BPS WebAPI key karena development key pernah terekspos di chat history;
- pastikan LimitRouter key disimpan hanya di server secret store;
- siapkan CA bundle sistem atau path runtime yang valid;
- jalankan migrations termasuk cache table;
- gunakan cache store khusus bila memakai `bps:clear-cache`;
- jalankan `php artisan bps:preload`;
- jalankan default dan live test suite;
- pastikan `APP_DEBUG=false`;
- review timeout terhadap karakteristik hosting;
- monitor BPS/LimitRouter latency dan error rate.

### Menjalankan live validation

```bash
BPS_LIVE_TESTS=true php artisan test tests/Feature/BpsChatFlowTest.php
```

Pada PowerShell:

```powershell
$env:BPS_LIVE_TESTS = 'true'
php artisan test tests/Feature/BpsChatFlowTest.php
Remove-Item Env:BPS_LIVE_TESTS
```

Live test menggunakan provider quota dan layanan eksternal. Default test suite tetap melewati enam live scenarios.

### Dokumen terkait

- [README proyek](../README.md)
- [Arsitektur dan Workflow Teknis](TECHNICAL_WORKFLOW.md)
- [Documentation Design Spec](superpowers/specs/2026-08-18-project-documentation-design.md)
- [Documentation Implementation Plan](superpowers/plans/2026-08-18-project-documentation.md)
