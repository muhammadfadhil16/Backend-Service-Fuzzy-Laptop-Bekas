
# Fuzzy Kelayakan Service

Dokumentasi ini menjelaskan struktur dan fitur layanan fuzzy untuk penilaian kelayakan laptop bekas. Layanan ini sekarang bersifat dinamis, di mana parameter fuzzifikasi dan defuzzifikasi dikirimkan melalui API.

## Struktur Folder Terkait

- `app/Services/Fuzzy/FuzzyKelayakanService.php`
	- Implementasi perhitungan fuzzy (fuzzifikasi, inferensi, defuzzifikasi).
- `app/Http/Controllers/Api/PenilaianController.php`
	- Endpoint API yang menerima input data laptop dan aturan (rules).
- `routes/api.php`
	- Definisi route API untuk penilaian.

## Ringkasan Fitur

- **Fuzzifikasi Dinamis**: Mendukung 4 variabel input (LCD, KesehatanBaterai, RAM, KondisiKeyboard) dengan parameter kurva yang dapat dikonfigurasi.
- **Inferensi**: Menggabungkan hasil fuzzifikasi menggunakan operator logika (MAX dan Average) untuk menentukan kategori kelayakan.
- **Defuzzifikasi Centroid**: Menggunakan nilai centroid yang dikirimkan untuk menghitung skor kelayakan akhir (0-100).
- **Keluaran Lengkap**: Mengembalikan detail input, nilai fuzzifikasi tiap variabel, skor akhir, dan label status.

## API Endpoint

**Endpoint:**
```
POST /api/penilaian
```

**Struktur Request:**

Payload harus memiliki dua objek utama: `input` (data laptop) dan `rules` (parameter fuzzy).

```json
{
    "input": {
        "LCD": 85,
        "KesehatanBaterai": 75,
        "RAM": 8,
        "KondisiKeyboard": 90
    },
    "rules": {
        "fuzzifikasi": {
            "LCD": {
                "rendah": [20, 50],
                "tinggi": [80, 100]
            },
            "KesehatanBaterai": {
                "rendah": [40, 60],
                "normal": [40, 60, 80],
                "tinggi": [80, 100]
            },
            "RAM": {
                "rendah": [4, 8],
                "normal": [4, 8, 16],
                "tinggi": [8, 16]
            },
            "KondisiKeyboard": {
                "rendah": [20, 50],
                "tinggi": [70, 100]
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

**Contoh Response:**

```json
{
    "status": "success",
    "data": {
        "input": {
            "LCD": 85,
            "KesehatanBaterai": 75,
            "RAM": 8,
            "KondisiKeyboard": 90
        },
        "fuzzifikasi": {
            "LCD": {"rendah": 0, "tinggi": 0.25},
            "KesehatanBaterai": {"rendah": 0, "normal": 0.25, "tinggi": 0},
            "RAM": {"rendah": 0, "normal": 1, "tinggi": 0},
            "Keyboard": {"rendah": 0, "tinggi": 0.67}
        },
        "nilaiKelayakan": 69.17,
        "statusKelayakan": "Bagus"
    }
}
```

## Detail Perhitungan (Service)

Service utama: `FuzzyKelayakanService::calculate(array $input, array $rules): array`

### 1) Fuzzifikasi

Parameter fuzzifikasi diambil dari `$rules['fuzzifikasi']`. Fungsi keanggotaan yang tersedia:

- **Kurva Turun**: `kurvaTurun(x, a, b)`
- **Kurva Naik**: `kurvaNaik(x, a, b)`
- **Kurva Segitiga**: `kurvaSegitiga(x, a, b, c)`

### 2) Inferensi

Logika penggabungan nilai (Hardcoded dalam service):

- **Tidak Layak** = `max(LCD rendah, KesehatanBaterai rendah, RAM rendah, Keyboard rendah)`
- **Layak** = `average(LCD tinggi, KesehatanBaterai tinggi, RAM tinggi, Keyboard tinggi)`
- **Kurang Layak** = `max(KesehatanBaterai normal, RAM normal)`

### 3) Defuzzifikasi

Menggunakan metode centroid dengan nilai yang dikirim dari `$rules['defuzzifikasi']['centroid']`:

- `tidak_layak` (Default: 30)
- `kurang_layak` (Default: 60)
- `layak` (Default: 90)

Rumus:
$$ \text{nilaiKelayakan} = \frac{\sum (\mu_i \cdot c_i)}{\sum \mu_i} $$

### 4) Status Kelayakan

Batas nilai status diambil dari `$rules['defuzzifikasi']['batas_status']`:

- `nilaiKelayakan < tidak_bagus`  -> **"Tidak Bagus"**
- `nilaiKelayakan < normal`       -> **"Normal"**
- Selain itu                      -> **"Bagus"**

## Panduan Pengembangan

- **Menambah Variabel**: Jika ingin menambah variabel baru (misal: HDD), tambahkan di controller validation, service fuzzifikasi, dan update logic inferensi.
- **Mengubah Rules**: Anda tidak perlu mengubah kode PHP untuk mengganti batasan nilai fuzzy, cukup ubah payload `rules` yang dikirimkan oleh pemanggil API (Core Service).

