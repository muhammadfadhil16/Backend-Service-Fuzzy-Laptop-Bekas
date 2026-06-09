# Dokumentasi EvaluatorService (Fuzzy Engine)

**EvaluatorService** adalah microservice mesin inferensi **Fuzzy Logic Mamdani** yang berdiri sendiri. Menerima input kondisi laptop dan aturan fuzzy secara dinamis melalui API, lalu mengembalikan skor kelayakan (0–100), status, serta detail fuzzifikasi dan inferensi. Service ini **stateless** — tidak memerlukan database.

## 1. Ikhtisar Arsitektur

```
BackendService
    ↓ HTTP POST /api/evaluator
EvaluatorService (FuzzyService)
    ↓
Engine Fuzzy Mamdani:
  Fuzzifikasi (5 variabel) → Inferensi Dinamis (MIN/MAX via matrix_aturan) → Defuzzifikasi (COA Diskrit) → Status
    ↓
Response JSON
```

**Karakteristik:**
- **Stateless** — tidak ada koneksi database.
- **Dinamis** — semua parameter kurva dan matriks aturan dikirim dalam request.
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
        "KondisiKeyboard": 90,
        "RAM": 8,
        "KesehatanBaterai": 75,
        "Processor": 12000
    },
    "rules": {
        "fuzzifikasi": {
            "LCD": {
                "buruk": [40, 60],
                "sedang": [40, 50, 70, 80],
                "baik": [60, 80]
            },
            "KondisiKeyboard": {
                "buruk": [40, 60],
                "sedang": [40, 50, 70, 80],
                "baik": [60, 80]
            },
            "RAM": {
                "rendah": [2, 4],
                "sedang": [2, 4, 8],
                "tinggi": [4, 8]
            },
            "KesehatanBaterai": {
                "rendah": [30, 50],
                "sedang": [30, 60, 85],
                "tinggi": [70, 90]
            },
            "Processor": {
                "rendah": [500, 10000],
                "sedang": [500, 10000, 15000],
                "tinggi": [10000, 15000]
            }
        },
        "defuzzifikasi": {
            "tidak_layak": [30, 50],
            "cukup_layak": [40, 60, 70, 90],
            "layak": [80, 100]
        },
        "matrix_aturan": [
            { "lcd": "buruk", "keyboard": "buruk", "ram": "rendah", "baterai": "rendah", "processor": "rendah", "output": "tidak_layak" },
            { "lcd": "sedang", "keyboard": "sedang", "ram": "sedang", "baterai": "sedang", "processor": "sedang", "output": "cukup_layak" },
            { "lcd": "baik", "keyboard": "baik", "ram": "tinggi", "baterai": "tinggi", "processor": "tinggi", "output": "layak" }
        ]
    }
}
```

### Aturan Validasi

| Field | Aturan |
|-------|--------|
| `input.LCD` | required, numeric, between 0–100 |
| `input.KondisiKeyboard` | required, numeric, between 0–100 |
| `input.RAM` | required, numeric, min:0 |
| `input.KesehatanBaterai` | required, numeric, between 0–100 |
| `input.Processor` | required, numeric, min:0 |
| `rules.fuzzifikasi` | required, array |
| `rules.defuzzifikasi` | required, array |
| `rules.matrix_aturan` | required, array |
| `rules.matrix_aturan.*.lcd` | required, string, in: buruk/sedang/baik |
| `rules.matrix_aturan.*.keyboard` | required, string, in: buruk/sedang/baik |
| `rules.matrix_aturan.*.ram` | required, string, in: rendah/sedang/tinggi |
| `rules.matrix_aturan.*.baterai` | required, string, in: rendah/sedang/tinggi |
| `rules.matrix_aturan.*.processor` | required, string, in: rendah/sedang/tinggi |
| `rules.matrix_aturan.*.output` | required, string, in: tidak_layak/cukup_layak/layak |

### Response (200)

```json
{
    "status": "success",
    "data": {
        "input": {
            "LCD": 85,
            "KondisiKeyboard": 90,
            "RAM": 8,
            "KesehatanBaterai": 75,
            "Processor": 12000
        },
        "fuzzifikasi": {
            "LCD": { "buruk": 0, "sedang": 0, "baik": 1 },
            "Keyboard": { "buruk": 0, "sedang": 0, "baik": 1 },
            "RAM": { "rendah": 0, "sedang": 0, "tinggi": 1 },
            "KesehatanBaterai": { "rendah": 0, "sedang": 0.33, "tinggi": 0.67 },
            "Processor": { "rendah": 0, "sedang": 0.6, "tinggi": 0.4 }
        },
        "inferensi": {
            "tidak_layak": 0,
            "cukup_layak": 0,
            "layak": 1
        },
        "nilaiKelayakan": 90.68,
        "statusKelayakan": "Layak"
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
Input nilai crisp (5 variabel)
    ↓
Fuzzifikasi: kurva → derajat keanggotaan [0, 1] per variabel per kategori
    ↓
Inferensi: iterasi matrix_aturan → MIN (AND) per aturan → MAX (OR) per kategori
    ↓
Clipping: cukup_layak = min(cukup_layak, 1 - tidak_layak)
          layak = min(layak, 1 - max(tidak_layak, cukup_layak))
    ↓
Defuzzifikasi: Centroid of Area (COA) diskrit → skor akhir 0–100
    ↓
Batas status: tentukan label "Tidak Layak" / "Cukup Layak" / "Layak"
```

### 4.1 Fuzzifikasi

Mendukung 4 jenis kurva keanggotaan. Parameter diambil dari `$rules['fuzzifikasi']`.

#### a. Kurva Turun (Linear Down)

```
μ = 1                    jika x ≤ a
    (b - x) / (b - a)    jika a < x < b
    0                    jika x ≥ b
```

Parameter: `[a, b]`

Digunakan untuk kategori **buruk/rendah** ( nilai kecil = derajat tinggi).

#### b. Kurva Naik (Linear Up)

```
μ = 0                    jika x ≤ a
    (x - a) / (b - a)    jika a < x < b
    1                    jika x ≥ b
```

Parameter: `[a, b]`

Digunakan untuk kategori **baik/tinggi** (nilai besar = derajat tinggi).

#### c. Kurva Segitiga (Triangular)

```
μ = 0                    jika x ≤ a atau x ≥ c
    (x - a) / (b - a)    jika a < x < b
    (c - x) / (c - b)    jika b ≤ x < c
```

Parameter: `[a, b, c]`

Digunakan untuk kategori **sedang** pada RAM, KesehatanBaterai, dan Processor.

#### d. Kurva Trapesium (Trapezoidal)

```
μ = 0                    jika x ≤ a atau x ≥ d
    (x - a) / (b - a)    jika a < x < b
    1                    jika b ≤ x ≤ c
    (d - x) / (d - c)    jika c < x < d
```

Parameter: `[a, b, c, d]`

Digunakan untuk kategori **sedang** pada LCD dan KondisiKeyboard.

### 4.2 Inferensi (Mamdani Dinamis)

Tidak seperti pendekatan konvensional dengan aturan tetap, engine ini menggunakan **matriks aturan dinamis** yang dikirim melalui `rules.matrix_aturan`.

**Proses inferensi:**

1. **Iterasi setiap aturan** dalam `matrix_aturan`.
2. Untuk setiap aturan, hitung **alpha-predikat** menggunakan operator **MIN** (AND logika) dari seluruh antecedent:
   ```
   α = min(μ_lcd, μ_keyboard, μ_ram, μ_baterai, μ_processor)
   ```
3. **Agregasi per kategori output** menggunakan operator **MAX** (OR logika):
   ```
   μ_kategori = max(α₁, α₂, ..., αₙ)  untuk semua aturan dengan output = kategori
   ```
4. **Clipping** untuk memastikan total derajat keanggotaan tidak melebihi 1:
   ```
   cukup_layak = min(cukup_layak, 1 - tidak_layak)
   layak = min(layak, 1 - max(tidak_layak, cukup_layak))
   ```

Pendekatan ini memungkinkan fleksibilitas penuh dalam mendefinisikan hubungan antar variabel, karena pengirim request dapat menentukan aturan IF-THEN dalam bentuk `[lcd, keyboard, ram, baterai, processor] → output` sebanyak yang diperlukan (hingga 243 kemungkinan kombinasi dari 3⁵).

### 4.3 Defuzzifikasi (Centroid of Area / COA Diskrit)

Berbeda dengan metode centroid weighted average yang menggunakan titik pusat tunggal, engine ini menghitung **Center of Area (COA)** secara diskrit dengan sampling pada rentang 0–100:

```
          Σ(z · μ(z))
nilai = ─────────────   untuk z = 0, 1, 2, ..., 100
            Σμ(z)
```

Di mana untuk setiap nilai `z`:
1. Hitung `μ_tidak_layak(z)` dengan kurva turun pada interval `[tidak_layak[0], tidak_layak[1]]`, lalu clip dengan hasil inferensi: `min(μ_tidak_layak(z), nilai_inferensi_tidak_layak)`.
2. Hitung `μ_cukup_layak(z)` dengan kurva trapesium pada interval `[cukup_layak[0], cukup_layak[1], cukup_layak[2], cukup_layak[3]]`, lalu clip dengan hasil inferensi.
3. Hitung `μ_layak(z)` dengan kurva naik pada interval `[layak[0], layak[1]]`, lalu clip dengan hasil inferensi.
4. Ambil nilai maksimum dari ketiganya sebagai `μ(z)`.

Parameter defuzzifikasi dikirim dalam `rules.defuzzifikasi` sebagai **parameter kurva**, bukan titik centroid:

```json
{
    "tidak_layak": [30, 50],
    "cukup_layak": [40, 60, 70, 90],
    "layak": [80, 100]
}
```

Jika total `Σμ(z) = 0` (penyebut = 0), maka nilai kelayakan dikembalikan sebagai **50** (nilai tengah).

### 4.4 Penentuan Status

Menggunakan batas *hardcoded* (tidak dari request):

| Rentang Skor | Status |
|---|---|
| 0 – 65 | **Tidak Layak** |
| >65 – 85 | **Cukup Layak** |
| >85 – 100 | **Layak** |

### Contoh Perhitungan Lengkap

**Input:** LCD=85, Keyboard=90, RAM=8, Baterai=75, Processor=12000

**Fuzzifikasi:**
- LCD: buruk=0, sedang=0, baik=1
- Keyboard: buruk=0, sedang=0, baik=1
- RAM: rendah=0, sedang=0, tinggi=1
- Baterai: rendah=0, sedang=0.33, tinggi=0.67
- Processor: rendah=0, sedang=0.6, tinggi=0.4

**Inferensi (dengan matrix_aturan 3 aturan dasar):**
- Aturan 1 (semua buruk/rendah → tidak_layak): α = min(0,0,0,0,0) = 0
- Aturan 2 (semua sedang → cukup_layak): α = min(0,0,0,0.33,0.6) = 0
- Aturan 3 (semua baik/tinggi → layak): α = min(1,1,1,0.67,0.4) = 0.4

Agregasi: tidak_layak=0, cukup_layak=0, layak=0.4

**Clipping:** tidak_layak=0, cukup_layak=0, layak=min(0.4, 1-0) = 0.4

**Defuzzifikasi COA:** Sampling 0–100 menghasilkan nilai kelayakan ~90.68

**Status:** 90.68 > 85 → **"Layak"**

## 5. Variabel Input

| Variabel | Rentang | Kategori | Kurva |
|----------|---------|----------|-------|
| LCD | 0–100 | buruk | turun |
| LCD | 0–100 | sedang | trapesium |
| LCD | 0–100 | baik | naik |
| KondisiKeyboard | 0–100 | buruk | turun |
| KondisiKeyboard | 0–100 | sedang | trapesium |
| KondisiKeyboard | 0–100 | baik | naik |
| RAM | numeric (unbounded) | rendah | turun |
| RAM | numeric (unbounded) | sedang | segitiga |
| RAM | numeric (unbounded) | tinggi | naik |
| KesehatanBaterai | 0–100 | rendah | turun |
| KesehatanBaterai | 0–100 | sedang | segitiga |
| KesehatanBaterai | 0–100 | tinggi | naik |
| Processor | numeric (unbounded) | rendah | turun |
| Processor | numeric (unbounded) | sedang | segitiga |
| Processor | numeric (unbounded) | tinggi | naik |

> **Catatan:** LCD, KondisiKeyboard, dan KesehatanBaterai memiliki batas 0–100 karena merupakan nilai persentase. RAM dan Processor menggunakan skor benchmark numerik bebas, sehingga parameter kurva harus disesuaikan oleh pengirim request.

## 6. Testing

### Unit Test — `tests/Unit/FuzzyServiceTest.php`

| Test | Skenario |
|---|---|
| `test_komponen_kritis_rendah_tidak_terangkat_oleh_processor_normal` | LCD=0, Keyboard=0, RAM=2, Baterai=0, Processor=3000 → inferensi `tidak_layak`=1.0, nilaiKelayakan ≤65, status **"Tidak Layak"**. Memastikan komponen kritis tidak terkompensasi oleh processor yang normal. |

### Feature Test — `tests/Feature/PenilaianTest.php`

| Test | Skenario |
|---|---|
| `test_penilaian_dengan_input_valid` | Input laptop bagus → status 200, struktur response lengkap |
| `test_laptop_spesifikasi_rendah` | Input laptop rusak → nilaiKelayakan ≤65, status **"Tidak Layak"** |
| `test_laptop_spesifikasi_tinggi` | Input laptop tinggi (RAM=16, Processor=5000) → nilaiKelayakan >85, status **"Layak"** |
| `test_validasi_field_wajib_diisi` | Body kosong → 422 |
| `test_validasi_lcd_harus_antara_0_100` | LCD=150 → 422 |
| `test_processor_harus_numerik` | Processor="delapan" → 422 pada `input.Processor` |

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

*Dokumentasi ini diperbarui pada 9 Juni 2026.*
