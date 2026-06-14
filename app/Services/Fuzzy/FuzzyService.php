<?php

namespace App\Services\Fuzzy;

class FuzzyService
{
    public function calculate(array $input, array $rules): array
    {
        // 1. Ambil 5 Variabel Input sesuai Skripsi
        $lcd = $input['LCD'];
        $keyboard = $input['KondisiKeyboard'];
        $ram = $input['RAM']; // Variabel baru
        $baterai = $input['KesehatanBaterai'];
        $processor = $input['Processor'];

        $rf = $rules['fuzzifikasi'];

        // 2. TAHAP FUZZIFIKASI
        // A. Kondisi LCD & Keyboard (Buruk, Sedang, Baik)
        $lcdBuruk = $this->kurvaTurun($lcd, $rf['LCD']['buruk'][0], $rf['LCD']['buruk'][1]);
        $lcdSedang = $this->evaluateSedang($lcd, $rf['LCD']['sedang']);
        $lcdBaik = $this->kurvaNaik($lcd, $rf['LCD']['baik'][0], $rf['LCD']['baik'][1]);

        $keyBuruk = $this->kurvaTurun($keyboard, $rf['KondisiKeyboard']['buruk'][0], $rf['KondisiKeyboard']['buruk'][1]);
        $keySedang = $this->evaluateSedang($keyboard, $rf['KondisiKeyboard']['sedang']);
        $keyBaik = $this->kurvaNaik($keyboard, $rf['KondisiKeyboard']['baik'][0], $rf['KondisiKeyboard']['baik'][1]);

        // B. RAM, Baterai, Processor (Rendah, Sedang, Tinggi)
        $ramRendah = $this->kurvaTurun($ram, $rf['RAM']['rendah'][0], $rf['RAM']['rendah'][1]);
        $ramSedang = $this->evaluateSedang($ram, $rf['RAM']['sedang']);
        $ramTinggi = $this->kurvaNaik($ram, $rf['RAM']['tinggi'][0], $rf['RAM']['tinggi'][1]);

        $batRendah = $this->kurvaTurun($baterai, $rf['KesehatanBaterai']['rendah'][0], $rf['KesehatanBaterai']['rendah'][1]);
        $batSedang = $this->evaluateSedang($baterai, $rf['KesehatanBaterai']['sedang']);
        $batTinggi = $this->kurvaNaik($baterai, $rf['KesehatanBaterai']['tinggi'][0], $rf['KesehatanBaterai']['tinggi'][1]);

        $procRendah = $this->kurvaTurun($processor, $rf['Processor']['rendah'][0], $rf['Processor']['rendah'][1]);
        $procSedang = $this->evaluateSedang($processor, $rf['Processor']['sedang']);
        $procTinggi = $this->kurvaNaik($processor, $rf['Processor']['tinggi'][0], $rf['Processor']['tinggi'][1]);

        // 3. TAHAP INFERENSI (Mamdani - MIN/MAX Dinamis)
        $rulesMatrix = $rules['matrix_aturan'] ?? [];
        $outputs = [
            'tidak_layak' => [],
            'cukup_layak' => [],
            'layak' => []
        ];

        foreach ($rulesMatrix as $rule) {
            // Pemetaan derajat keanggotaan berdasarkan label di matrix_aturan
            $lcdVal = match($rule['lcd']) {
                'buruk' => $lcdBuruk,
                'sedang' => $lcdSedang,
                'baik' => $lcdBaik,
                default => 0.0
            };
            $keyVal = match($rule['keyboard']) {
                'buruk' => $keyBuruk,
                'sedang' => $keySedang,
                'baik' => $keyBaik,
                default => 0.0
            };
            $ramVal = match($rule['ram']) {
                'rendah' => $ramRendah,
                'sedang' => $ramSedang,
                'tinggi' => $ramTinggi,
                default => 0.0
            };
            $batVal = match($rule['baterai']) {
                'rendah' => $batRendah,
                'sedang' => $batSedang,
                'tinggi' => $batTinggi,
                default => 0.0
            };
            $procVal = match($rule['processor']) {
                'rendah' => $procRendah,
                'sedang' => $procSedang,
                'tinggi' => $procTinggi,
                default => 0.0
            };

            // Rule Predicate (Alpha Predicate) menggunakan operator MIN (AND)
            $alpha = min($lcdVal, $keyVal, $ramVal, $batVal, $procVal);
            
            if (isset($outputs[$rule['output']])) {
                $outputs[$rule['output']][] = $alpha;
            }
        }

        // Agregasi menggunakan operator MAX (OR)
        $tidakLayak = empty($outputs['tidak_layak']) ? 0.0 : max($outputs['tidak_layak']);
        $cukupLayak = empty($outputs['cukup_layak']) ? 0.0 : max($outputs['cukup_layak']);
        $layak = empty($outputs['layak']) ? 0.0 : max($outputs['layak']);

        $tidakLayak = min(1.0, $tidakLayak);
        $cukupLayak = min(1.0, $cukupLayak);
        $layak = min(1.0, $layak);

        // TAHAP DEFUZZIFIKASI (BISECTOR MURNI - FLOAT SAMPLING)
        $rd = $rules['defuzzifikasi']; 
        
        $muArray = [];
        $totalArea = 0.0;
        $step = 0.1; // Precise float sampling

        // 1. Sampling titik (z) dari 0 hingga 100 menggunakan step float
        for ($z = 0.0; $z <= 100.0; $z = round($z + $step, 1)) {
            $muTidakLayak = min($tidakLayak, $this->kurvaTurun($z, $rd['tidak_layak'][0], $rd['tidak_layak'][1]));
            $muCukupLayak = min($cukupLayak, $this->kurvaTrapesium($z, $rd['cukup_layak'][0], $rd['cukup_layak'][1], $rd['cukup_layak'][2], $rd['cukup_layak'][3]));
            $muLayak = min($layak, $this->kurvaNaik($z, $rd['layak'][0], $rd['layak'][1]));

            // Agregasi (MAX) area kurva
            $muZ = max($muTidakLayak, $muCukupLayak, $muLayak);
            
            // Simpan nilai untuk perhitungan array Bisector
            $muArray[] = [
                'z' => $z,
                'mu' => $muZ
            ];
            $totalArea += $muZ;
        }

        // 2. Logika pencarian titik Bisector (Membagi area jadi 2 seimbang)
        $nilaiKelayakan = 50.0;
        if ($totalArea > 0) {
            $targetArea = $totalArea / 2.0;
            $accumulatedArea = 0.0;
            foreach ($muArray as $item) {
                $accumulatedArea += $item['mu']; 
                
                if ($accumulatedArea >= $targetArea) {
                    $nilaiKelayakan = $item['z']; 
                    break;
                }
            }
        }

        // Penentuan Status
        if ($nilaiKelayakan <= 65) {
            $status = 'Tidak Layak';
        } elseif ($nilaiKelayakan > 65 && $nilaiKelayakan <= 85) {
            $status = 'Cukup Layak';
        } else {
            $status = 'Layak';
        }

        // Return Data Terstruktur
        return [
            'input' => $input,
            'fuzzifikasi' => [
                'LCD' => ['buruk' => $lcdBuruk, 'sedang' => $lcdSedang, 'baik' => $lcdBaik],
                'Keyboard' => ['buruk' => $keyBuruk, 'sedang' => $keySedang, 'baik' => $keyBaik],
                'RAM' => ['rendah' => $ramRendah, 'sedang' => $ramSedang, 'tinggi' => $ramTinggi],
                'KesehatanBaterai' => ['rendah' => $batRendah, 'sedang' => $batSedang, 'tinggi' => $batTinggi],
                'Processor' => ['rendah' => $procRendah, 'sedang' => $procSedang, 'tinggi' => $procTinggi],
            ],
            'inferensi' => [
                'tidak_layak' => $tidakLayak,
                'cukup_layak' => $cukupLayak,
                'layak' => $layak,
            ],
            'nilaiKelayakan' => round($nilaiKelayakan, 2),
            'statusKelayakan' => $status,
        ];
    }

    // --- RUMUS MATEMATIKA FUNGSI KEANGGOTAAN ---

    private function kurvaTurun(float $x, float $a, float $b): float {
        if ($x <= $a) return 1.0;
        if ($x >= $b) return 0.0;
        return ($b - $x) / ($b - $a);
    }

    private function kurvaNaik(float $x, float $a, float $b): float {
        if ($x <= $a) return 0.0;
        if ($x >= $b) return 1.0;
        return ($x - $a) / ($b - $a);
    }

    private function kurvaSegitiga(float $x, float $a, float $b, float $c): float {
        if ($x <= $a || $x >= $c) return 0.0;
        if ($x == $b) return 1.0;
        if ($x > $a && $x < $b) return ($x - $a) / ($b - $a);
        return ($c - $x) / ($c - $b);
    }

    // Fungsi Baru untuk Trapesium (Sesuai Skripsi Bab 3)
    private function kurvaTrapesium(float $x, float $a, float $b, float $c, float $d): float {
        if ($x <= $a || $x >= $d) return 0.0;
        if ($x >= $b && $x <= $c) return 1.0;
        if ($x > $a && $x < $b) return ($x - $a) / ($b - $a);
        return ($d - $x) / ($d - $c);
    }

    private function evaluateSedang(float $x, array $params): float {
        if (count($params) === 4) {
            return $this->kurvaTrapesium($x, $params[0], $params[1], $params[2], $params[3]);
        }
        return $this->kurvaSegitiga($x, $params[0], $params[1], $params[2]);
    }
}