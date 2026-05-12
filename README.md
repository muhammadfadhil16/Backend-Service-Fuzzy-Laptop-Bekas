
# Backend Service: Fuzzy Laptop Bekas

Backend service ini dibangun menggunakan Laravel untuk menyediakan perhitungan logika fuzzy (Fuzzy Logic) guna menilai kelayakan laptop bekas berdasarkan berbagai parameter kondisi fisik dan spesifikasi.

## Fitur Utama

- **Fuzzy Inference System (FIS)**: Menggunakan metode Sugeno/Mamdani sederhana (centroid) untuk menentukan skor kelayakan.
- **Dynamic Rules**: Parameter fuzzifikasi dan defuzzifikasi dikirimkan melalui API, memungkinkan Core Service untuk menyesuaikan aturan tanpa mengubah kode backend.
- **API Response Lengkap**: Memberikan transparansi perhitungan dengan menyertakan derajat keanggotaan tiap variabel.

## Persyaratan Sistem

- PHP >= 8.2
- Composer
- Laravel 11.x

## Instalasi

1. Clone repositori:
   ```bash
   git clone <repository-url>
   ```
2. Install dependensi:
   ```bash
   composer install
   ```
3. Salin file `.env`:
   ```bash
   cp .env.example .env
   ```
4. Generate app key:
   ```bash
   php artisan key:generate
   ```
5. Jalankan server:
   ```bash
   php artisan serve
   ```

## Dokumentasi API

Dokumentasi detail mengenai struktur request, parameter fuzzy, dan logika perhitungan dapat ditemukan di:
- [Fuzzy Service Documentation](docs/fuzzyservice.md)

### Ringkasan Endpoint
- **POST `/api/penilaian`**: Menghitung nilai kelayakan laptop berdasarkan input data dan rules yang diberikan.

## Lisensi
Proyek ini dikembangkan untuk kebutuhan Sistem Penilaian Kelayakan Laptop Bekas.
