# Product Requirements Document (PRD): EvaluatorService
**Sistem Penilaian Kelayakan Laptop Bekas (Fuzzy Engine)**

## 1. Ringkasan Eksekutif
**EvaluatorService** adalah microservice berbasis **Fuzzy Logic Mamdani** yang berfungsi sebagai mesin inferensi untuk menentukan kelayakan laptop bekas. Service ini menerima input kondisi fisik dan spesifikasi laptop secara dinamis melalui API dan memberikan skor numerik serta label status kelayakan.

### Tujuan Utama:
*   Memberikan standarisasi penilaian laptop bekas secara objektif.
*   Menghasilkan perhitungan yang fleksibel melalui parameter fuzzy yang dapat dikonfigurasi tanpa menyentuh kode program.
*   Beroperasi sebagai service mandiri (**stateless**) yang mudah diintegrasikan dengan sistem lain.

---

## 2. Arsitektur & Teknologi
*   **Framework:** Laravel 11.x
*   **Bahasa:** PHP 8.2+
*   **Arsitektur:** Microservice (Stateless) — Tidak menggunakan database. Semua data rules dikirim via request body.
*   **Metode Logic:** Fuzzy Mamdani (Fuzzifikasi -> Inferensi MAX/MIN -> Defuzzifikasi Centroid).
*   **Deployment:** Docker Ready (Port 8001).

---

## 3. Spesifikasi Fitur (Fuzzy Engine)

### 3.1 Variabel Input (Crisp Input)
Sistem menerima 4 variabel utama:
1.  **LCD (0-100):** Kondisi visual layar.
2.  **Kesehatan Baterai (0-100):** Persentase kapasitas baterai (Battery Health).
3.  **Processor (Numeric):** Skor benchmark/performa processor (tidak terbatas 0-100).
4.  **Kondisi Keyboard (0-100):** Fungsi tombol dan fisik keyboard.

### 3.2 Logika Fuzzifikasi
Mendukung 3 jenis kurva keanggotaan untuk mengubah nilai numerik menjadi derajat keanggotaan [0, 1]:
*   **Linear Down (Turun):** Digunakan untuk kategori "Rendah".
*   **Linear Up (Naik):** Digunakan untuk kategori "Tinggi".
*   **Triangular (Segitiga):** Digunakan untuk kategori "Normal".

### 3.3 Aturan Inferensi (Knowledge Base)
Sistem menggunakan aturan tetap dalam kode (`FuzzyService.php`) dengan operator logika:
*   **Tidak Layak:** `MAX` (LCD Rendah, Baterai Rendah, Processor Rendah, Keyboard Rendah).
*   **Kurang Layak:** `MAX` (Baterai Normal, Processor Normal).
*   **Layak:** `MIN` (LCD Tinggi, Baterai Tinggi, Processor Tinggi, Keyboard Tinggi).

*Catatan: Sistem menerapkan normalisasi bobot agar total derajat keanggotaan tidak melebihi 1.0 (Prioritas komponen kritis).*

### 3.4 Defuzzifikasi (Crisp Output)
Menggunakan metode **Centroid Weighted Average** untuk menghasilkan nilai akhir (0-100):
$$Nilai = \frac{\sum (\mu \times Centroid)}{\sum \mu}$$

---

## 4. Spesifikasi API

### 4.1 Endpoint: `POST /api/evaluator`
Digunakan untuk melakukan kalkulasi kelayakan.

**Struktur Request (JSON):**
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
      "LCD": { "rendah": [40, 60], "tinggi": [60, 80] },
      "KesehatanBaterai": { "rendah": [30, 50], "normal": [30, 60, 85], "tinggi": [70, 90] },
      "Processor": { "rendah": [5000, 10000], "normal": [8000, 12000, 15000], "tinggi": [13000, 18000] },
      "KondisiKeyboard": { "rendah": [40, 70], "tinggi": [70, 90] }
    },
    "defuzzifikasi": {
      "centroid": { "tidak_layak": 30, "kurang_layak": 60, "layak": 90 },
      "batas_status": { "tidak_bagus": 40, "normal": 65 }
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
