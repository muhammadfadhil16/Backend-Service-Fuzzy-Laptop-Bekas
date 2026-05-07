
# Fuzzy Kelayakan Service

Dokumentasi ini menjelaskan struktur dan fitur layanan fuzzy untuk penilaian kelayakan laptop bekas.

## Struktur Folder Terkait

- `app/Services/Fuzzy/FuzzyKelayakanService.php`
	- Implementasi perhitungan fuzzy (fuzzifikasi, inferensi, defuzzifikasi).
- `app/Http/Controllers/Api/PenilaianController.php`
	- Endpoint API yang memanggil service.
- `routes/api.php`
	- Definisi route API untuk penilaian.

## Ringkasan Fitur

- Fuzzifikasi 4 variabel input: LCD, KesehatanBaterai, RAM, KondisiKeyboard.
- Inferensi sederhana untuk tiga kategori output: Tidak Layak, Kurang Layak, Layak.
- Defuzzifikasi metode centroid berbasis nilai tetap.
- Keluaran lengkap berisi input, hasil fuzzifikasi, nilai kelayakan, dan status.

## API Endpoint

Endpoint:

```
POST /api/penilaian
```

Validasi input (lihat `PenilaianController`):

- `LCD`: numeric, 0-100
- `KesehatanBaterai`: numeric, 0-100
- `RAM`: numeric
- `KondisiKeyboard`: numeric, 0-100

Contoh request:

```
{
	"LCD": 85,
	"KesehatanBaterai": 75,
	"RAM": 8,
	"KondisiKeyboard": 90
}
```

Contoh response:

```
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
			"RAM": {"rendah": 0, "sedang": 1, "tinggi": 0},
			"Keyboard": {"rendah": 0, "tinggi": 0.67}
		},
		"nilaiKelayakan": 69.17,
		"statusKelayakan": "Bagus"
	}
}
```

Catatan: nilai di contoh fuzzifikasi bergantung pada input aktual.

## Detail Perhitungan (Service)

Service utama: `FuzzyKelayakanService::calculate(array $input): array`

### 1) Fuzzifikasi

Fungsi keanggotaan yang dipakai:

- Kurva turun: `kurvaTurun(x, a, b)`
	- $\mu = 1$ untuk $x \le a$
	- $\mu = 0$ untuk $x \ge b$
	- Linear turun pada rentang $[a, b]$
- Kurva naik: `kurvaNaik(x, a, b)`
	- $\mu = 0$ untuk $x \le a$
	- $\mu = 1$ untuk $x \ge b$
	- Linear naik pada rentang $[a, b]$
- Kurva segitiga: `kurvaSegitiga(x, a, b, c)`
	- $\mu = 0$ untuk $x \le a$ atau $x \ge c$
	- $\mu = 1$ untuk $x = b$
	- Linear naik dari $a$ ke $b$, linear turun dari $b$ ke $c$

Parameter fuzzifikasi:

- LCD
	- rendah: kurvaTurun(LCD, 20, 50)
	- tinggi: kurvaNaik(LCD, 80, 100)
- KesehatanBaterai
	- rendah: kurvaTurun(KesehatanBaterai, 40, 60)
	- normal: kurvaSegitiga(KesehatanBaterai, 40, 60, 80)
	- tinggi: kurvaNaik(KesehatanBaterai, 80, 100)
- RAM
	- rendah: kurvaTurun(RAM, 4, 8)
	- sedang: kurvaSegitiga(RAM, 4, 8, 16)
	- tinggi: kurvaNaik(RAM, 8, 16)
- KondisiKeyboard
	- rendah: kurvaTurun(KondisiKeyboard, 20, 50)
	- tinggi: kurvaNaik(KondisiKeyboard, 70, 100)

### 2) Inferensi

Inferensi sederhana dengan operator maksimum dan rata-rata:

- Tidak Layak = max(LCD rendah, KesehatanBaterai rendah, RAM rendah, Keyboard rendah)
- Layak = rata-rata(LCD tinggi, KesehatanBaterai tinggi, RAM tinggi, Keyboard tinggi)
- Kurang Layak = max(KesehatanBaterai normal, RAM sedang)

### 3) Defuzzifikasi

Centroid tetap untuk setiap kategori:

- Tidak Layak = 30
- Kurang Layak = 60
- Layak = 90

Rumus defuzzifikasi:

$$
	ext{nilaiKelayakan} = \frac{\sum (\mu_i \cdot c_i)}{\sum \mu_i}
$$

Jika penyebut 0, nilai kelayakan = 0.

### 4) Status Kelayakan

- nilaiKelayakan < 40  -> "Tidak Bagus"
- nilaiKelayakan < 65  -> "Normal"
- selain itu            -> "Bagus"

## Struktur Output Service

Keluaran `calculate()`:

- `input`: nilai input asli
- `fuzzifikasi`: derajat keanggotaan tiap variabel
- `nilaiKelayakan`: nilai hasil defuzzifikasi (dibulatkan 2 desimal)
- `statusKelayakan`: label status

## Panduan Pengembangan

- Tambah variabel input baru:
	- Tambahkan parameter di validasi request.
	- Tambahkan fuzzifikasi dan ikutkan dalam inferensi.
- Ubah batasan keanggotaan:
	- Sesuaikan parameter kurva pada bagian fuzzifikasi.
- Ubah aturan inferensi:
	- Perbarui formula `tidakLayak`, `kurangLayak`, `layak` di service.
- Ubah label status:
	- Perbarui mapping batas nilai di akhir `calculate()`.

