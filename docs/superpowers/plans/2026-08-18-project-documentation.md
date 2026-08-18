# BPS AI Assistant Project Documentation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membuat README GitHub yang menarik serta dua dokumen lengkap yang merekam sejarah implementasi dan menjelaskan arsitektur/workflow teknis BPS AI Assistant.

**Architecture:** Dokumentasi dibagi berdasarkan kebutuhan pembaca: `README.md` sebagai landing page produk, `docs/PROJECT_HISTORY.md` sebagai audit trail kronologis, dan `docs/TECHNICAL_WORKFLOW.md` sebagai referensi engineering. Semua fakta diturunkan dari source code, commit history, test output, live probes, dan spec yang telah disetujui; implementasi lama yang berbeda dari source final tidak digunakan sebagai sumber utama.

**Tech Stack:** GitHub Flavored Markdown, Mermaid, HTML yang didukung GitHub, Laravel 13, Laravel AI SDK, BPS WebAPI, PHPUnit, Laravel Pint, Vite.

**Spec:** `docs/superpowers/specs/2026-08-18-project-documentation-design.md`

---

## File Structure

### Create

- `README.md` — landing page GitHub bergaya Modern AI Product.
- `docs/PROJECT_HISTORY.md` — kronologi lengkap pekerjaan dari demo awal sampai live integration dan draft PR.
- `docs/TECHNICAL_WORKFLOW.md` — arsitektur, komponen, request lifecycle, tool catalog, cache, citation, error, testing, security, dan runbook.

### Validate

- seluruh class/tool/config yang direferensikan harus ada pada branch;
- seluruh Mermaid fence harus berpasangan;
- seluruh link relatif antarfile harus mengarah ke file yang ada;
- tidak ada secret nyata, placeholder belum selesai, atau klaim test yang tidak didukung evidence.

---

### Task 1: Build the Documentation Fact Inventory

**Files:**
- Read: `app/Ai/*.php`
- Read: `app/Bps/*.php`
- Read: `app/Bps/Tools/*.php`
- Read: `app/Console/Commands/*.php`
- Read: `app/Providers/*.php`
- Read: `config/ai.php`, `config/bps.php`, `config/cache.php`
- Read: `routes/api.php`
- Read: `tests/Unit/**/*.php`, `tests/Feature/*.php`
- Read: Git history `main..HEAD`

- [ ] **Step 1: Export the final commit inventory**

Run:

```powershell
git log --reverse --format="%h|%s" main..HEAD
```

Expected: ordered commits beginning with the core BPS tool work and ending with the documentation spec commit.

- [ ] **Step 2: Export the concrete BPS tool inventory**

Run:

```powershell
Get-ChildItem app/Bps/Tools/*Tool.php |
    Where-Object Name -NotIn @('AbstractBpsTool.php') |
    Select-Object -ExpandProperty Name
```

Expected: 25 concrete tools (4 core tools + 21 remaining tools).

- [ ] **Step 3: Record verified test/build evidence**

Use these already-observed results as documentation facts:

```text
Default PHPUnit: 111 discovered; 105 passed; 6 live-gated skipped; 353 assertions.
Strict live integration: 6 passed; 32 assertions.
Final targeted regressions: 17 passed; 48 assertions.
Laravel Pint: passed.
Vite build: passed.
Client secret scan: no matches.
```

- [ ] **Step 4: Record live operational evidence**

Use these observed preload counts:

```text
Domains: 549
National indicators: 16
National variables: 1,744
Jawa Barat indicators: 10
Jawa Barat variables: 612
```

Use these observed HTTP outcomes:

```text
Definition: answered through .md fallback; citation verified=false.
Numeric historical query: safe no_evidence when official evidence could not be completed.
Clarification: clarification_required.
Out of scope: out_of_scope.
Prompt injection: out_of_scope, no credential/citation leak.
Publication: answered with 10 BPS citations, all verified=true.
```

- [ ] **Step 5: Cross-check the inventory against the approved spec**

Run:

```powershell
Select-String -Path docs/superpowers/specs/2026-08-18-project-documentation-design.md `
    -Pattern '^## ','^### '
```

Expected: all three deliverables and all validation requirements are represented in the implementation tasks below.

---

### Task 2: Write `docs/PROJECT_HISTORY.md`

**Files:**
- Create: `docs/PROJECT_HISTORY.md`

- [ ] **Step 1: Write document metadata and navigation**

The file must start with:

```markdown
# Riwayat Pengembangan BPS AI Assistant

> Dokumentasi kronologis pengembangan aplikasi dari demo knowledge-base lokal sampai integrasi BPS WebAPI resmi, live validation, dan draft Pull Request.

- [Ringkasan Eksekutif](#ringkasan-eksekutif)
- [Kondisi Awal](#kondisi-awal)
- [Tahapan Pengembangan](#tahapan-pengembangan)
- [Masalah dan Root Cause](#masalah-dan-root-cause)
- [Hasil Verifikasi](#hasil-verifikasi)
- [Commit dan Pull Request](#commit-dan-pull-request)
- [Pelajaran Teknis](#pelajaran-teknis)
```

- [ ] **Step 2: Document the original demo state**

Include:

- Laravel 13 application;
- `DemoLexicalRetriever` and `data/knowledge/*.md` marked `DEMO_NOT_VERIFIED`;
- `ChatService` original flow: scope, retrieve, prompt, parse, citation;
- LimitRouter through Laravel AI SDK;
- initial issues with message object shape and Windows cURL error 60.

- [ ] **Step 3: Document BPS WebAPI exploration and architecture decisions**

Include:

- endpoint documentation and live probes;
- path-segment key auth except `dataexim` query auth;
- HTTP 200 can still contain BPS `status:Error`;
- hybrid live + 24-hour cache + `.md` fallback;
- intent-based tool subsets;
- server-only credential boundary;
- citation trust decision.

- [ ] **Step 4: Document Tasks 1–15 in chronological order**

For each task, include:

- objective;
- files/classes created or modified;
- RED test or initial failure;
- implementation outcome;
- verification result;
- commit when applicable.

Tasks must cover config, DTO, exception, API client, citation, registry, 4 core tools, 21 remaining tools, tool cap, `BpsAgent`, prompt, routing, commands, smoke validation, and live integration.

- [ ] **Step 5: Document runtime-discovered defects and root causes**

Create subsections for:

1. linked worktree missing `vendor/autoload.php`;
2. partial Composer install / missing PHPUnit executable;
3. missing SQLite runtime database and cache table;
4. missing CA bundle in worktree;
5. absolute CA path being joined to base path twice;
6. final synthesis missing after tool cap;
7. web SAPI execution limit of 30/65 seconds;
8. glosarium endpoint live unavailability;
9. numeric discovery exhausting the tool cap;
10. `verified` citation flag missing from JSON serialization;
11. singleton mutable citation state under workers;
12. nested SIMDASI business error inside outer `status:OK`.

- [ ] **Step 6: Add final evidence tables**

Include tables for:

- test/build/security results;
- live preload counts;
- HTTP scenario outcomes;
- branch commit list;
- operational notes before production.

- [ ] **Step 7: Add decisions that changed during implementation**

Explain:

- raw manual OpenAI-compatible loop was replaced by native Laravel AI SDK loop;
- `definition` was routed back to `.md` after glosarium live failures;
- numeric flow gained `ListPeriodsTool`;
- total execution budget replaced a single-call timeout assumption;
- README/documentation was added to the same draft PR.

- [ ] **Step 8: Validate history completeness**

Run:

```powershell
Select-String -Path docs/PROJECT_HISTORY.md `
    -Pattern 'Task 1','Task 15','4af9629','Pull Request','Root Cause','105','32 assertions'
```

Expected: every pattern is present.

- [ ] **Step 9: Commit the project history document**

```powershell
git add docs/PROJECT_HISTORY.md
git commit -m "docs: add complete project development history`n`nCo-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 3: Write `docs/TECHNICAL_WORKFLOW.md`

**Files:**
- Create: `docs/TECHNICAL_WORKFLOW.md`

- [ ] **Step 1: Write technical metadata and navigation**

The file must start with:

```markdown
# Arsitektur dan Workflow Teknis BPS AI Assistant

> Referensi engineering untuk memahami request lifecycle, routing intent, tool-use agent, BPS WebAPI, cache, citation, error handling, keamanan, testing, dan operasional.
```

Add a complete table of contents covering every section in the approved spec.

- [ ] **Step 2: Add the high-level component diagram**

Use a Mermaid `flowchart LR` showing:

```text
Browser -> Laravel API -> ChatController -> ChatService -> ScopeGuard
ChatService -> BpsAgent -> BpsToolRegistry -> BPS Tools -> BpsApiClient -> BPS WebAPI
ChatService -> RetrieverInterface -> DemoLexicalRetriever -> data/knowledge
BpsAgent -> AiProviderInterface -> LimitRouterProvider -> Laravel AI SDK -> LimitRouter
ChatService -> ChatResponse -> Browser
```

- [ ] **Step 3: Add the `/api/chat` sequence diagram**

Show:

- input validation;
- rate limit;
- scope classification;
- clarification short-circuit;
- feature flag decision;
- BPS agent path;
- `.md` fallback path;
- citation mapping;
- normalized JSON response.

- [ ] **Step 4: Document every major component**

Create a table with path, class, responsibility, inputs, outputs, and dependencies for:

- `ChatController`;
- `ChatService`;
- `ScopeGuard` / `ScopeDecision`;
- `PromptBuilder`;
- `AiProviderInterface` / `LimitRouterProvider`;
- `ToolCappedAgent` / `BudgetedTool`;
- `BpsAgent`;
- `BpsToolRegistry`;
- `CitationCollectingTool`;
- `BpsApiClient` / `BpsResponse` / `BpsApiException`;
- `BpsCitation` / `Citation`;
- retriever classes;
- service providers;
- Artisan commands.

- [ ] **Step 5: Document intent routing**

Include a Mermaid decision flow and a table for:

```text
definition
numeric_statistic
publication
metadata_methodology
navigation
bps_service
out_of_scope
```

State that `definition` and `bps_service` currently fall back to `.md`; numeric missing geography/period returns clarification.

- [ ] **Step 6: Document the 25-tool catalog**

List every concrete tool with:

- class name;
- endpoint;
- required parameters;
- optional parameters;
- result key;
- intent family.

Group tools into core, catalog, publication, trade, census, and SIMDASI.

- [ ] **Step 7: Document the native Laravel AI loop**

Explain:

- native `ToolCappedAgent` and `maxSteps()`;
- `BudgetedTool` shared counter across sequential and parallel calls;
- `maxToolCalls + 1` tool-loop steps;
- one no-tool synthesis call when final text is empty;
- total execution time formula `(maxToolCalls + 2) * timeoutSec + 5`;
- fallback `no_evidence` if synthesis still returns empty.

Add a Mermaid sequence diagram for this loop.

- [ ] **Step 8: Document BPS API, cache, and redaction**

Include:

- path and query URL construction;
- key auth positions;
- response `status` and `data-availability` checks;
- cache key `bps:v2:{md5(url)}`;
- only successful response caching;
- 24-hour TTL;
- recursive key replacement with `[REDACTED]` before parsing/caching;
- output limit 100 with `total`, `returned`, `truncated`.

Add Mermaid cache flow.

- [ ] **Step 9: Document citation trust flow**

Explain:

- citations extracted only from official tool result fields;
- `_citations` returned to the model;
- backend collection by source ID;
- LLM chooses IDs only;
- `Citation::fromBpsSources()` rejects unknown IDs;
- `ChatResponse` sends `verified`;
- `.md` fallback citations remain `verified:false`.

Add Mermaid citation flow.

- [ ] **Step 10: Document error and fallback behavior**

Create an error matrix for:

- validation failure;
- rate limit;
- out-of-scope;
- missing numeric parameters;
- BPS connection timeout;
- BPS outer error body;
- nested interoperability error;
- tool call cap;
- provider exception;
- empty final synthesis;
- missing BPS key/disabled flag;
- live endpoint unavailable.

Add Mermaid error/fallback flow.

- [ ] **Step 11: Document configuration, API contract, operations, and testing**

Include safe placeholder tables for all relevant env vars; request/response JSON examples; `bps:preload`; `bps:clear-cache`; default tests; strict live tests; build and Pint commands; security scan; runbook and troubleshooting.

- [ ] **Step 12: Add known limitations and extension guidance**

Document:

- glosarium live unavailable;
- numeric historical query may safely return `no_evidence`;
- cache clear requires dedicated store;
- live tests spend provider quota;
- key regeneration requirement;
- steps to add a new tool, intent mapping, citation mapper, and test.

- [ ] **Step 13: Validate technical references**

Run:

```powershell
$doc = Get-Content docs/TECHNICAL_WORKFLOW.md -Raw
$toolNames = Get-ChildItem app/Bps/Tools/*Tool.php |
    Where-Object Name -NotIn @('AbstractBpsTool.php') |
    ForEach-Object BaseName
$missing = $toolNames | Where-Object { $doc -notmatch [regex]::Escape($_) }
if ($missing) { throw "Missing tools: $($missing -join ', ')" }
```

Expected: no missing tools.

- [ ] **Step 14: Commit the technical workflow document**

```powershell
git add docs/TECHNICAL_WORKFLOW.md
git commit -m "docs: add complete chatbot technical workflow`n`nCo-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 4: Write the Modern AI Product `README.md`

**Files:**
- Create: `README.md`

- [ ] **Step 1: Build the centered hero**

Use GitHub-compatible HTML:

```html
<div align="center">
  <h1>BPS AI Assistant</h1>
  <p><strong>Ask Indonesia's Official Statistics</strong></p>
  <p>Asisten statistik berbasis Laravel, Laravel AI SDK, dan BPS WebAPI resmi.</p>
</div>
```

Add badge links without claiming an external CI workflow.

- [ ] **Step 2: Add navigation and feature highlights**

Include internal links to overview, features, workflow, quick start, API, tool catalog, testing, security, operations, documentation, and limitations.

- [ ] **Step 3: Add a concise product workflow diagram**

Use one Mermaid flowchart showing browser, intent routing, official tools, verified citations, and `.md` fallback.

- [ ] **Step 4: Add complete developer setup**

Include:

```bash
git clone https://github.com/Gimm17/bps-chatbot-ai.git
cd bps-chatbot-ai
composer install
npm install
copy .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
npm run build
php artisan serve
```

Also provide POSIX alternatives where Windows `copy`/`touch` differ.

- [ ] **Step 5: Add safe environment configuration**

Use placeholder values only and explain feature flag, cache, timeout, provider, live test, and CA bundle settings.

- [ ] **Step 6: Add API examples**

Show complete request and representative responses for:

- answered with verified BPS citations;
- clarification required;
- no evidence;
- out of scope.

- [ ] **Step 7: Add tool catalog summary, test commands, operations, and project structure**

Link full catalog to `docs/TECHNICAL_WORKFLOW.md`; show verified command output counts; document preload/clear cache; include a focused tree.

- [ ] **Step 8: Add security, limitations, and documentation links**

Include key boundaries, redaction, trusted citations, validation, rate limiting, and production key regeneration. Link both detailed docs and the design spec.

- [ ] **Step 9: Validate README presentation**

Check:

```powershell
Select-String -Path README.md -Pattern '<div align="center">','```mermaid','docs/PROJECT_HISTORY.md','docs/TECHNICAL_WORKFLOW.md','BPS_WEBAPI_KEY=your_'
```

Expected: every pattern is present.

- [ ] **Step 10: Commit README**

```powershell
git add README.md
git commit -m "docs: add modern GitHub project README`n`nCo-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 5: Validate the Complete Documentation Set

**Files:**
- Validate: `README.md`
- Validate: `docs/PROJECT_HISTORY.md`
- Validate: `docs/TECHNICAL_WORKFLOW.md`

- [ ] **Step 1: Verify deliverable count and non-empty files**

```powershell
$files = @('README.md','docs/PROJECT_HISTORY.md','docs/TECHNICAL_WORKFLOW.md')
foreach ($file in $files) {
    if (-not (Test-Path $file)) { throw "Missing $file" }
    if ((Get-Item $file).Length -lt 1000) { throw "$file is unexpectedly short" }
}
```

- [ ] **Step 2: Scan unfinished content**

```powershell
Select-String -Path README.md,docs/PROJECT_HISTORY.md,docs/TECHNICAL_WORKFLOW.md `
    -Pattern 'TBD','TODO','FIXME','\[PAUSED','implement later'
```

Expected: no matches.

- [ ] **Step 3: Scan secrets**

```powershell
Select-String -Path README.md,docs/PROJECT_HISTORY.md,docs/TECHNICAL_WORKFLOW.md `
    -Pattern '32a4af778c0b74a62c19857b278cab33','sk-lr-[A-Za-z0-9]+'
```

Expected: no matches.

- [ ] **Step 4: Validate relative links**

Verify these files exist:

```powershell
@(
    'docs/PROJECT_HISTORY.md',
    'docs/TECHNICAL_WORKFLOW.md',
    'docs/superpowers/specs/2026-08-18-project-documentation-design.md'
) | ForEach-Object {
    if (-not (Test-Path $_)) { throw "Broken documentation link target: $_" }
}
```

- [ ] **Step 5: Validate Mermaid fence pairing**

```powershell
foreach ($file in @('README.md','docs/TECHNICAL_WORKFLOW.md')) {
    $text = Get-Content $file -Raw
    $open = ([regex]::Matches($text, '```mermaid')).Count
    $all = ([regex]::Matches($text, '```')).Count
    if ($open -lt 1 -or ($all % 2) -ne 0) { throw "Invalid Mermaid/code fences in $file" }
}
```

- [ ] **Step 6: Run application regression gates**

```powershell
vendor/bin/phpunit
vendor/bin/pint --test
npm run build
```

Expected:

```text
PHPUnit: 105 passed and 6 live-gated skipped in the default run.
Pint: passed.
Vite: production build passed.
```

- [ ] **Step 7: Check Git diff and status**

```powershell
git diff --check
git status --short
```

Expected: only intended documentation files and plan commit differences before final commit.

---

### Task 6: Push and Update Draft PR #1

**Files:**
- Commit: `README.md`
- Commit: `docs/PROJECT_HISTORY.md`
- Commit: `docs/TECHNICAL_WORKFLOW.md`
- Commit: plan file if not already committed

- [ ] **Step 1: Commit the implementation plan**

```powershell
git add docs/superpowers/plans/2026-08-18-project-documentation.md
git commit -m "docs: plan complete project documentation`n`nCo-Authored-By: Claude <noreply@anthropic.com>"
```

- [ ] **Step 2: Push all documentation commits**

```powershell
git push
```

Expected: `origin/worktree-resume-task7` advances to the final documentation commit.

- [ ] **Step 3: Verify draft PR includes the new commits**

Use GitHub API or the PR URL:

```text
https://github.com/Gimm17/bps-chatbot-ai/pull/1
```

Expected: README and both detailed documentation files appear in the Files Changed tab.

- [ ] **Step 4: Verify final branch cleanliness**

```powershell
git status -sb
git rev-parse HEAD
git rev-parse origin/worktree-resume-task7
```

Expected: clean status and identical local/remote SHA.
