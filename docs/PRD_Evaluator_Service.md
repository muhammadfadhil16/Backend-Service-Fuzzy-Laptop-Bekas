
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
* **Metode Logic:** Fuzzy Mamdani (Fuzzifikasi -> Inferensi Kombinasi Dinamis MIN-MAX -> Defuzzifikasi Centroid).
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
Mendukung 4 jenis titik potong kurva keanggotaan untuk mengubah nilai numerik (*crisp*) menjadi derajat keanggotaan $[\mu] \in [0, 1]$:
* **Linear Down (Turun):** Digunakan untuk kategori batas bawah (misal: "Buruk" / "Rendah").
* **Linear Up (Naik):** Digunakan untuk kategori batas atas (misal: "Baik" / "Tinggi").
* **Triangular (Segitiga):** Digunakan untuk koordinat 3 parameter $[a, b, c]$.
* **Trapezoidal (Trapesium):** Digunakan untuk koordinat 4 parameter $[a, b, c, d]$ guna menangani nilai plateau/maksimal datar (misal: kategori "Sedang" atau "Normal").

### 3.3 Aturan Inferensi (Dynamic Knowledge Base)
Sistem meninggalkan pendekatan aturan kaku (*hardcoded*). Kini aturan dieksekusi secara berulang (*looping parsing*) menggunakan matriks kombinasi aturan (**hingga 243 kombinasi aturan**) yang dikirim oleh backend service:
* **T-Norm (MIN):** Digunakan untuk mencari nilai $\alpha$-predikat dari kombinasi kondisi komponen menggunakan kata hubung `AND` pada setiap baris aturan.
* **S-Norm (MAX):** Digunakan untuk proses agregasi (penggabungan) seluruh nilai kesimpulan dari baris-baris aturan yang memiliki label output sejenis (`tidak_layak`, `cukup_layak`, `layak`).

### 3.4 Defuzzifikasi (Crisp Output)
Menggunakan metode **Centroid (Sugeno/Mamdani Weighted Average discretization / Sampling Loop $z$)** untuk menghasilkan nilai akhir kelayakan laptop (skor 0-100):
$$Nilai Kelayakan = \frac{\sum (\mu_z \times z)}{\sum \mu_z}$$

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
      {
        "lcd": "baik",
        "keyboard": "baik",
        "ram": "sedang",
        "baterai": "sedang",
        "processor": "sedang",
        "output": "layak"
      },
      {
        "lcd": "buruk",
        "keyboard": "buruk",
        "ram": "rendah",
        "baterai": "rendah",
        "processor": "rendah",
        "output": "tidak_layak"
      }
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
    "statusKelayakan": "Bagus",
    "fuzzifikasi": { ... detail derajat keanggotaan ... },
    "inferensi": { ... detail hasil rules ... }
  }
}
```

---

## 5. Validasi & Aturan Teknis
1.  **Validasi Input:** Semua field input (kecuali Processor) harus berada di rentang 0-100.
2.  **Error Handling:** Mengembalikan status code `422 Unprocessable Entity` jika struktur JSON tidak lengkap atau nilai di luar batas.
3.  **Statelessness:** Sistem tidak menyimpan riwayat penilaian. Setiap request adalah transaksi baru yang independen.

---

## 6. Jaminan Kualitas (QA)
*   **Unit Testing:** Memastikan logika matematika kurva fuzzy dan perhitungan centroid akurat (`tests/Unit/FuzzyServiceTest.php`).
*   **Feature Testing:** Memastikan endpoint API merespons dengan benar terhadap berbagai skenario input (`tests/Feature/PenilaianTest.php`).
*   **Skenario Kritis:** Pengujian khusus untuk memastikan jika satu komponen sangat buruk (misal: LCD pecah/0), skor akhir akan jatuh ke "Tidak Bagus" meskipun processor sangat cepat.

---

## 7. Batasan dan Ruang Lingkup (Project Boundaries)

### 7.1 Batasan Fungsional (Out of Scope)
*   **Pencarian Harga Otomatis:** Sistem tidak melakukan *web scraping* atau integrasi ke e-commerce untuk mencari harga pasar secara otomatis. Harga pasar (`market_price`) murni berdasarkan input manual pengguna.
*   **Otentikasi Pengguna:** Versi saat ini tidak memiliki sistem login/registrasi. API bersifat publik (atau diasumsikan berada dalam jaringan internal yang aman).
*   **Manajemen Gambar:** Sistem tidak mendukung pengunggahan atau penyimpanan foto fisik laptop.
*   **Variabel Penilaian Terbatas:** Penilaian saat ini hanya terbatas pada 4 komponen utama (LCD, Baterai, Processor, Keyboard). Komponen lain seperti kondisi engsel, port USB, atau webcam tidak masuk dalam hitungan skor fuzzy secara matematis.

### 7.2 Batasan Teknis & Dependensi
*   **Dependensi Microservice:** Keakuratan skor kelayakan sepenuhnya bergantung pada ketersediaan dan logika di `EvaluatorService`. Jika service tersebut mati, `BackendService` tidak dapat menghitung skor baru.
*   **Koneksi Internet:** Diperlukan koneksi internet aktif untuk memanggil API Gemini AI. Jika koneksi terputus, sistem akan menggunakan *local fallback recommendation* (Simulasi AI).
*   **Skalabilitas Database:** Database MySQL dirancang untuk penyimpanan riwayat penilaian, bukan untuk database spesifikasi laptop (katalog) yang sangat besar.

### 7.3 Target Lingkungan
*   Proyek ini dioptimalkan untuk berjalan di lingkungan **Docker**.
*   Backend menggunakan standar REST API untuk berkomunikasi dengan Frontend.

---

## 8. Pengembangan Selanjutnya (Roadmap)
*   [ ] Implementasi **Mamdani Rules** yang dinamis (saat ini rules `MAX/MIN` masih hardcoded di Service).
*   [ ] Dukungan untuk kurva **Trapezoid**.
*   [ ] Dashboard visualisasi kurva fuzzy untuk memudahkan admin menentukan titik potong (`a, b, c`).
*   [ ] Export hasil penilaian ke PDF sebagai sertifikat kelayakan.

---

*Dokumen ini diperbarui secara otomatis berdasarkan analisis kode.*
