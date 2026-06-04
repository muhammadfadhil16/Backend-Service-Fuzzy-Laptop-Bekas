# Dokumentasi EvaluatorService (Fuzzy Engine)

**EvaluatorService** adalah microservice mesin inferensi **Fuzzy Logic Mamdani** yang berdiri sendiri. Menerima input kondisi laptop dan aturan fuzzy secara dinamis melalui API, lalu mengembalikan skor kelayakan (0–100), status, serta detail fuzzifikasi dan inferensi. Service ini **stateless** — tidak memerlukan database.

## 1. Ikhtisar Arsitektur

```
BackendService
    ↓ HTTP POST /api/evaluator
EvaluatorService (FuzzyService)
    ↓
Engine Fuzzy Mamdani:
  Fuzzifikasi → Inferensi (MAX) → Defuzzifikasi (Centroid) → Status
    ↓
Response JSON
```

**Karakteristik:**
- **Stateless** — tidak ada koneksi database.
- **Dinamis** — semua parameter kurva dan centroid dikirim dalam request.
- **Port:** 8001 (Docker).

## 2. Struktur Folder

```
app/
├── Http/Controllers/Api/
│   └── EvaluationController.php    # Endpoint POST /api/evaluator
└── Services/Fuzzy/
    └── FuzzyService.php            # Engine fuzzy logic (fuzzifikasi, inferensi, defuzzifikasi)
routes/
└── api.php                         # Definisi route /api/evaluator
tests/
├── Feature/
│   └── PenilaianTest.php           # Feature test endpoint
└── Unit/
    └── FuzzyServiceTest.php        # Unit test engine fuzzy
```

## 3. API Endpoint

**Endpoint:** `POST /api/evaluator`

### Request Body

```json
{
    "input": {
        "LCD": 85,
        "KesehatanBaterai": 75,
        "Processor": 12000,
        "KondisiKeyboard": 90
    },
    "rules": {
        "fuzzifikasi": {
            "LCD": {
                "rendah": [40, 60],
                "normal": [40, 60, 80],
                "tinggi": [60, 80]
            },
            "KesehatanBaterai": {
                "rendah": [30, 50],
                "normal": [30, 60, 85],
                "tinggi": [70, 90]
            },
            "Processor": {
                "rendah": [500, 10000],
                "normal": [500, 10000, 15000],
                "tinggi": [10000, 15000]
            },
            "KondisiKeyboard": {
                "rendah": [40, 70],
                "normal": [40, 70, 90],
                "tinggi": [70, 90]
            }
        },
        "defuzzifikasi": {
            "centroid": {
                "tidak_layak": 30,
                "kurang_layak": 60,
                "layak": 90
            },
            "batas_status": {
                "tidak_bagus": 40,
                "normal": 65
            }
        }
    }
}
```

### Aturan Validasi

| Field | Aturan |
|-------|--------|
| `input.LCD` | required, numeric, between 0–100 |
| `input.KesehatanBaterai` | required, numeric, between 0–100 |
| `input.Processor` | required, numeric |
| `input.KondisiKeyboard` | required, numeric, between 0–100 |
| `rules.fuzzifikasi` | required, array |
| `rules.defuzzifikasi` | required, array |

### Response (200)

```json
{
    "status": "success",
    "data": {
        "input": {
            "LCD": 85,
            "KesehatanBaterai": 75,
            "Processor": 12000,
            "KondisiKeyboard": 90
        },
        "fuzzifikasi": {
            "LCD": { "rendah": 0, "normal": 0.75, "tinggi": 1 },
            "KesehatanBaterai": { "rendah": 0, "normal": 0.42, "tinggi": 0.58 },
            "Processor": { "rendah": 0, "normal": 0.6, "tinggi": 0.4 },
            "KondisiKeyboard": { "rendah": 0, "normal": 0, "tinggi": 1 }
        },
        "inferensi": {
            "tidak_layak": 0,
            "kurang_layak": 0.6,
            "layak": 1
        },
        "nilaiKelayakan": 77.4,
        "statusKelayakan": "Bagus"
    }
}
```

### Error (422 — Validasi)

```json
{
    "message": "The input.LCD field is required.",
    "errors": {
        "input.LCD": ["The input.LCD field is required."]
    }
}
```

## 4. Fuzzy Logic Engine

Service utama: `FuzzyService::calculate(array $input, array $rules): array`

Alur perhitungan:

```
Input nilai crisp
    ↓
Fuzzifikasi: kurva → derajat keanggotaan [0, 1] per variabel per kategori
    ↓
Inferensi: aturan IF-THEN dengan operator MAX (OR) → 3 kategori kelayakan
    ↓
Normalisasi: jika total bobot > 1, bagi setiap nilai dengan total
    ↓
Defuzzifikasi: Centroid Weighted Average → skor akhir 0–100
    ↓
Batas status: tentukan label "Tidak Bagus" / "Normal" / "Bagus"
```

### 4.1 Fuzzifikasi

Mendukung 3 jenis kurva keanggotaan. Parameter diambil dari `$rules['fuzzifikasi']`.

#### a. Kurva Turun (Linear Down)

```
        1 ┤
          |\
          | \
    0 ┤---└──┴───
          a   b

μ = 1                    jika x ≤ a
    (b - x) / (b - a)    jika a < x < b
    0                    jika x ≥ b
```

#### b. Kurva Naik (Linear Up)

```
        1 ┤      /
          |     /
          |    /
    0 ┤---┴───└──
          a   b

μ = 0                    jika x ≤ a
    (x - a) / (b - a)    jika a < x < b
    1                    jika x ≥ b
```

#### c. Kurva Segitiga (Triangular)

```
        1 ┤    /\
          |   /  \
          |  /    \
    0 ┤---└──┴──┴──└---
          a  b  c

μ = 0                    jika x ≤ a atau x ≥ c
    (x - a) / (b - a)    jika a < x < b
    (c - x) / (c - b)    jika b ≤ x < c
```

### 4.2 Inferensi (Mamdani)

Menggabungkan hasil fuzzifikasi ke dalam 3 kategori kelayakan menggunakan operator **MAX** (setara dengan OR logika).

**Rules:**

| Kategori | Aturan |
|----------|--------|
| `tidak_layak` | MAX(LCD_rendah, KesehatanBaterai_rendah, Processor_rendah, KondisiKeyboard_rendah) |
| `kurang_layak` | MAX(KesehatanBaterai_normal, Processor_normal, KondisiKeyboard_normal) |
| `layak` | MAX(LCD_tinggi, KesehatanBaterai_tinggi, Processor_tinggi, KondisiKeyboard_tinggi) |

**Normalisasi:** Jika `total_bobot = μ_tidak_layak + μ_kurang_layak + μ_layak > 1`, maka setiap nilai dibagi `total_bobot` agar berada di rentang [0, 1] dan totalnya = 1.

### 4.3 Defuzzifikasi (Centroid Weighted Average)

```
nilaiKelayakan = (μ_tidak_layak × C_tidak_layak + μ_kurang_layak × C_kurang_layak + μ_layak × C_layak)
                 ─────────────────────────────────────────────────────────────────────────────
                 (μ_tidak_layak + μ_kurang_layak + μ_layak)
```

**Centroid default:** `tidak_layak = 30`, `kurang_layak = 60`, `layak = 90`

Jika total bobot = 0 (penyebut = 0), maka `nilaiKelayakan = 0`.

### 4.4 Penentuan Status

Menggunakan `$rules['defuzzifikasi']['batas_status']`:

| Rentang Skor | Status |
|---|---|
| 0 – ≤`tidak_bagus` (40) | **Tidak Bagus** |
| >`tidak_bagus` – ≤`normal` (65) | **Normal** |
| >`normal` – 100 | **Bagus** |

### Contoh Perhitungan Lengkap

**Input:** LCD=85, Baterai=75, Processor=12000, Keyboard=90

**Fuzzifikasi:**
- LCD: rendah=0, normal=0.75, tinggi=1
- Baterai: rendah=0, normal=0.42, tinggi=0.58
- Processor: rendah=0, normal=0.6, tinggi=0.4
- Keyboard: rendah=0, normal=0, tinggi=1

**Inferensi:**
- tidak_layak = MAX(0, 0, 0, 0) = 0
- kurang_layak = MAX(0.42, 0.6, 0) = 0.6
- layak = MAX(1, 0.58, 0.4, 1) = 1

**Normalisasi:** Total = 0 + 0.6 + 1 = 1.6 > 1
- tidak_layak = 0 / 1.6 = 0
- kurang_layak = 0.6 / 1.6 = 0.375
- layak = 1 / 1.6 = 0.625

**Defuzzifikasi:**
```
nilaiKelayakan = (0 × 30 + 0.375 × 60 + 0.625 × 90) / (0 + 0.375 + 0.625)
               = (0 + 22.5 + 56.25) / 1
               = 78.75
```

**Status:** 78.75 > 65 → **"Bagus"**

## 5. Variabel Input

| Variabel | Rentang | Kategori | Kurva |
|----------|---------|----------|-------|
| LCD | 0–100 | rendah | turun |
| LCD | 0–100 | normal | segitiga |
| LCD | 0–100 | tinggi | naik |
| KesehatanBaterai | 0–100 | rendah | turun |
| KesehatanBaterai | 0–100 | normal | segitiga |
| KesehatanBaterai | 0–100 | tinggi | naik |
| Processor | numeric (unbounded) | rendah | turun |
| Processor | numeric (unbounded) | normal | segitiga |
| Processor | numeric (unbounded) | tinggi | naik |
| KondisiKeyboard | 0–100 | rendah | turun |
| KondisiKeyboard | 0–100 | normal | segitiga |
| KondisiKeyboard | 0–100 | tinggi | naik |

> **Catatan:** Berbeda dengan variabel lain, `Processor` tidak memiliki batas 0–100 karena menggunakan skor benchmark (numeric bebas). Parameter kurva harus disesuaikan oleh pengirim request.

## 6. Testing

### Unit Test — `tests/Unit/FuzzyServiceTest.php`

| Test | Skenario |
|---|---|
| `test_komponen_kritis_rendah_tidak_terangkat_oleh_processor_normal` | LCD=0, Baterai=0, Processor=10000, Keyboard=0 → status **"Tidak Bagus"** (nilai=30). Memastikan komponen kritis tidak terkompensasi oleh processor yang normal. |

### Feature Test — `tests/Feature/PenilaianTest.php`

| Test | Skenario |
|---|---|
| `test_penilaian_dengan_input_valid` | Input laptop bagus → status **"Bagus"** |
| `test_penilaian_dengan_input_rendah` | Input laptop rusak → status **"Tidak Bagus"** |
| `test_validasi_input_kosong` | Field kosong → 422 |
| `test_validasi_nilai_diluar_batas` | LCD=150 → 422 |
| `test_penilaian_input_mendekati_batas_status` | Skor mendekati 40 → boundary test |

**Menjalankan test:**

```bash
php artisan test
```

## 7. Environment Variables

| Variable | Default | Keterangan |
|---|---|---|
| `APP_URL` | `http://localhost` | URL aplikasi |
| `APP_KEY` | — | Wajib untuk Laravel (generate via `php artisan key:generate`) |

> **Catatan:** Service ini **tidak** memerlukan koneksi database. Semua data dikirim dinamis melalui request.

---

*Dokumentasi ini diperbarui pada 23 Mei 2026.*
