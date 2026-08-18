# Knowledge Base — BPS AI Assistant (DEMO)

> **Status: DEMO_NOT_VERIFIED** — Semua entri di folder ini adalah konten demo untuk
> presentasi. Bukan data statistik resmi BPS yang sudah diverifikasi. Jangan
> memperlakukan angka/URL di sini sebagai sumber resmi. URL dikosongkan jika belum
> ada sumber resmi yang terverifikasi (tidak boleh dibuat-buat).

## Format

Setiap file `.md` punya YAML frontmatter:

```yaml
---
id: SRC-DEMO-001
title: Judul Sumber Demo
category: definition | numeric_statistic | publication | metadata_methodology | bps_service | navigation
source_url:                       # kosong jika belum terverifikasi
source_status: DEMO_NOT_VERIFIED
---
```

Diikuti konten markdown (penjelasan, definisi, dll).

## Aturan

- Tidak membuat URL BPS palsu.
- Tidak membuat angka BPS aktual yang belum dicek.
- Placeholder angka contoh harus berlabel jelas (CONTOH / DEMO).
- Citation di jawaban LLM hanya boleh memakai `id` (SOURCE_ID) yang ada di sini.
