# Product Requirements Document (PRD): EvaluatorService
**Sistem Penilaian Kelayakan Laptop Bekas (Fuzzy Engine - Versi Terbuka Dinamis)**

## 1. Ringkasan Eksekutif
**EvaluatorService** adalah microservice berbasis **Fuzzy Logic Mamdani** yang berfungsi sebagai mesin inferensi (*fuzzy engine*) untuk menentukan kelayakan laptop bekas. Service ini menerima input kondisi fisik dan spesifikasi laptop secara dinamis melalui API dan memberikan skor numerik serta label status kelayakan.

### Tujuan Utama:
* Memberikan standarisasi penilaian laptop bekas secara objektif dan samar (fuzzy).
* Menghasilkan perhitungan yang fleksibel melalui parameter fungsi keanggotaan dan basis data aturan (*rule-base*) yang dikirim secara dinamis tanpa menyentuh kode program hardcoded.
* Beroperasi sebagai service mandiri (**stateless**) yang mudah diintegrasikan dengan sistem lain di dalam arsitektur microservice.

---

## 2. Arsitektur & Teknologi
* **Framework:** Laravel 12.x
* **Bahasa:** PHP 8.2+
* **Arsitektur:** Microservice (Stateless) — Tidak menggunakan database internal. Semua data parameter kurva dan kombinasi aturan (matrix rules) dikirim via request body.
* **Metode Logic:** Fuzzy Mamdani (Fuzzifikasi → Inferensi Kombinasi Dinamis MIN-MAX → Defuzzifikasi Centroid of Area Diskrit).
* **Deployment:** Docker Ready (Port 8001).

---

## 3. Spesifikasi Fitur (Fuzzy Engine)

### 3.1 Variabel Input (Crisp Input)
Sistem mengevaluasi **5 variabel utama** sesuai dengan standarisasi data uji skripsi:
1.  **LCD (0-100):** Kondisi visual dan fungsionalitas layar.
2.  **Kondisi Keyboard (0-100):** Fungsi tombol fisik dan responsivitas keyboard.
3.  **RAM (Numeric):** Kapasitas RAM terpasang (dalam GB, batas minimal 0).
4.  **Kesehatan Baterai (0-100):** Persentase kapasitas maksimal baterai saat ini (*Battery Health*).
5.  **Processor (Numeric):** Skor performa prosesor berdasarkan benchmark PassMark CPU Mark.

### 3.2 Logika Fuzzifikasi
Mendukung 4 jenis titik potong kurva keanggotaan untuk mengubah nilai numerik (*crisp*) menjadi derajat keanggotaan [μ] ∈ [0, 1]:
* **Linear Down (Turun):** Digunakan untuk kategori batas bawah ("Buruk" / "Rendah").
* **Linear Up (Naik):** Digunakan untuk kategori batas atas ("Baik" / "Tinggi").
* **Triangular (Segitiga):** Digunakan untuk koordinat 3 parameter [a, b, c].
* **Trapezoidal (Trapesium):** Digunakan untuk koordinat 4 parameter [a, b, c, d] guna menangani nilai plateau/maksimal datar (misal: kategori "Sedang").

### 3.3 Aturan Inferensi (Dynamic Knowledge Base)
Aturan dieksekusi secara berulang (*looping parsing*) menggunakan matriks kombinasi aturan (**hingga 243 kombinasi aturan**) yang dikirim oleh backend service:
* **T-Norm (MIN):** Mencari nilai α-predikat dari kombinasi kondisi komponen menggunakan kata hubung `AND` pada setiap baris aturan.
* **S-Norm (MAX):** Agregasi (penggabungan) seluruh nilai kesimpulan dari baris-baris aturan yang memiliki label output sejenis (`tidak_layak`, `cukup_layak`, `layak`).
* **Clipping:** Setelah agregasi, nilai `cukup_layak` dan `layak` diklip agar tidak melebihi 1 secara kumulatif.

### 3.4 Defuzzifikasi (Crisp Output)
Menggunakan metode **Centroid of Area (COA) diskrit / Sampling Loop z** untuk menghasilkan nilai akhir kelayakan laptop (skor 0-100):

$$Nilai Kelayakan = \frac{\sum_{z=0}^{100} (\mu(z) \times z)}{\sum_{z=0}^{100} \mu(z)}$$

Di mana μ(z) adalah nilai maksimum dari ketiga kurva output (tidak_layak, cukup_layak, layak) yang sudah diklip dengan hasil inferensi pada setiap titik z.

---

## 4. Spesifikasi API

### 4.1 Endpoint: `POST /api/evaluator`
Digunakan untuk melakukan kalkulasi mesin fuzzy.

**Struktur Request (JSON):**
```json
{
  "input": {
    "LCD": 85,
    "KondisiKeyboard": 80,
    "RAM": 8,
    "KesehatanBaterai": 75,
    "Processor": 9850
  },
  "rules": {
    "fuzzifikasi": {
      "LCD": { "buruk": [0, 50], "sedang": [40, 55, 75, 85], "baik": [75, 100] },
      "KondisiKeyboard": { "buruk": [0, 50], "sedang": [40, 55, 75, 85], "baik": [75, 100] },
      "RAM": { "rendah": [0, 4], "sedang": [4, 8, 16], "tinggi": [8, 16] },
      "KesehatanBaterai": { "rendah": [0, 50], "sedang": [40, 60, 80], "tinggi": [75, 100] },
      "Processor": { "rendah": [0, 5000], "sedang": [4000, 10000, 15000], "tinggi": [12000, 25000] }
    },
    "matrix_aturan": [
      { "lcd": "baik", "keyboard": "baik", "ram": "sedang", "baterai": "sedang", "processor": "sedang", "output": "layak" },
      { "lcd": "buruk", "keyboard": "buruk", "ram": "rendah", "baterai": "rendah", "processor": "rendah", "output": "tidak_layak" }
    ],
    "defuzzifikasi": {
      "tidak_layak": [0, 65],
      "cukup_layak": [55, 65, 85, 90],
      "layak": [85, 100]
    }
  }
}
```

**Struktur Response (JSON):**
```json
{
  "status": "success",
  "data": {
    "nilaiKelayakan": 78.5,
    "statusKelayakan": "Cukup Layak",
    "fuzzifikasi": { ... detail derajat keanggotaan ... },
    "inferensi": { ... detail hasil rules ... }
  }
}
```

**Status kelayakan:**
| Rentang Skor | Status |
|---|---|
| 0 – 65 | **Tidak Layak** |
| >65 – 85 | **Cukup Layak** |
| >85 – 100 | **Layak** |

---

## 5. Validasi & Aturan Teknis
1.  **Validasi Input:** LCD, KondisiKeyboard, dan KesehatanBaterai harus 0–100. RAM dan Processor numerik dengan minimal 0.
2.  **Validasi matrix_aturan:** Setiap baris wajib memiliki `lcd`, `keyboard`, `ram`, `baterai`, `processor` dengan nilai enum sesuai kategorinya, serta `output` yang valid (`tidak_layak`, `cukup_layak`, `layak`).
3.  **Error Handling:** Mengembalikan status code `422 Unprocessable Entity` jika struktur JSON tidak lengkap atau nilai di luar batas.
4.  **Statelessness:** Sistem tidak menyimpan riwayat penilaian. Setiap request adalah transaksi baru yang independen.

---

## 6. Jaminan Kualitas (QA)
*   **Unit Testing:** Memastikan logika matematika kurva fuzzy dan perhitungan centroid akurat (`tests/Unit/FuzzyServiceTest.php`).
*   **Feature Testing:** Memastikan endpoint API merespons dengan benar terhadap berbagai skenario input (`tests/Feature/PenilaianTest.php`).
*   **Skenario Kritis:** Pengujian khusus untuk memastikan jika satu komponen sangat buruk (misal: LCD pecah/0), skor akhir akan jatuh ke "Tidak Layak" meskipun processor sangat cepat.

---

## 7. Batasan dan Ruang Lingkup (Project Boundaries)

### 7.1 Batasan Fungsional (Out of Scope)
*   **Pencarian Harga Otomatis:** Sistem tidak melakukan *web scraping* atau integrasi ke e-commerce untuk mencari harga pasar secara otomatis. Harga pasar (`market_price`) murni berdasarkan input manual pengguna.
*   **Otentikasi Pengguna:** Versi saat ini tidak memiliki sistem login/registrasi. API bersifat publik (atau diasumsikan berada dalam jaringan internal yang aman).
*   **Manajemen Gambar:** Sistem tidak mendukung pengunggahan atau penyimpanan foto fisik laptop.
*   **Variabel Penilaian Terbatas:** Penilaian saat ini hanya terbatas pada 5 komponen utama (LCD, Keyboard, RAM, Baterai, Processor). Komponen lain seperti kondisi engsel, port USB, atau webcam tidak masuk dalam hitungan skor fuzzy secara matematis.

### 7.2 Batasan Teknis & Dependensi
*   **Dependensi Microservice:** Keakuratan skor kelayakan sepenuhnya bergantung pada ketersediaan dan logika di `EvaluatorService`. Jika service tersebut mati, `BackendService` tidak dapat menghitung skor baru.
*   **Koneksi Internet:** Diperlukan koneksi internet aktif untuk memanggil API Gemini AI. Jika koneksi terputus, sistem akan menggunakan *local fallback recommendation* (Simulasi AI).
*   **Skalabilitas Database:** Database MySQL dirancang untuk penyimpanan riwayat penilaian, bukan untuk database spesifikasi laptop (katalog) yang sangat besar.

### 7.3 Target Lingkungan
*   Proyek ini dioptimalkan untuk berjalan di lingkungan **Docker**.
*   Backend menggunakan standar REST API untuk berkomunikasi dengan Frontend.

---

## 8. Pengembangan Selanjutnya (Roadmap)
*   [ ] Dashboard visualisasi kurva fuzzy untuk memudahkan admin menentukan titik potong (a, b, c, d).
*   [ ] Export hasil penilaian ke PDF sebagai sertifikat kelayakan.
*   [ ] Penambahan variabel input baru (kondisi engsel, port USB, webcam).

---

*Dokumen ini diperbarui pada 9 Juni 2026.*
