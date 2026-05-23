<?php

namespace App\Services\Fuzzy;

class FuzzyService
{
    public function calculate(array $input, array $rules): array
    {
        $lcd = $input['LCD'];
        $kesehatanBaterai = $input['KesehatanBaterai'];
        $processor = $input['Processor'];
        $kondisiKeyboard = $input['KondisiKeyboard'];

        // Ekstrak Rules untuk mempermudah pemanggilan
        $rf = $rules['fuzzifikasi'];
        $rd = $rules['defuzzifikasi'];

        // Fuzzifikasi
        $lcdRendah = $this->kurvaTurun($lcd, $rf['LCD']['rendah'][0], $rf['LCD']['rendah'][1]);
        $lcdTinggi = $this->kurvaNaik($lcd, $rf['LCD']['tinggi'][0], $rf['LCD']['tinggi'][1]);

        $kesehatanBateraiRendah = $this->kurvaTurun($kesehatanBaterai, $rf['KesehatanBaterai']['rendah'][0], $rf['KesehatanBaterai']['rendah'][1]);
        $kesehatanBateraiNormal = $this->kurvaSegitiga($kesehatanBaterai, $rf['KesehatanBaterai']['normal'][0], $rf['KesehatanBaterai']['normal'][1], $rf['KesehatanBaterai']['normal'][2]);
        $kesehatanBateraiTinggi = $this->kurvaNaik($kesehatanBaterai, $rf['KesehatanBaterai']['tinggi'][0], $rf['KesehatanBaterai']['tinggi'][1]);

        $processorRendah = $this->kurvaTurun($processor, $rf['Processor']['rendah'][0], $rf['Processor']['rendah'][1]);
        $processorNormal = $this->kurvaSegitiga($processor, $rf['Processor']['normal'][0], $rf['Processor']['normal'][1], $rf['Processor']['normal'][2]);
        $processorTinggi = $this->kurvaNaik($processor, $rf['Processor']['tinggi'][0], $rf['Processor']['tinggi'][1]);

        $keyboardRendah = $this->kurvaTurun($kondisiKeyboard, $rf['KondisiKeyboard']['rendah'][0], $rf['KondisiKeyboard']['rendah'][1]);
        $keyboardTinggi = $this->kurvaNaik($kondisiKeyboard, $rf['KondisiKeyboard']['tinggi'][0], $rf['KondisiKeyboard']['tinggi'][1]);

        // Inferensi
        $tidakLayak = max($lcdRendah, $kesehatanBateraiRendah, $processorRendah, $keyboardRendah);
        $kurangLayak = max($kesehatanBateraiNormal, $processorNormal);
        $layak = min($lcdTinggi, $kesehatanBateraiTinggi, $processorTinggi, $keyboardTinggi);

        // Normalisasi dan prioritas inferensi.
        // Jika komponen kritis sudah sangat rendah, kategori yang lebih tinggi tidak boleh
        // mengangkat skor secara berlebihan hanya karena satu parameter lain normal/tinggi.
        $tidakLayak = min(1.0, $tidakLayak);
        $kurangLayak = min(1.0, $kurangLayak);
        $layak = min(1.0, $layak);
        $kurangLayak = min($kurangLayak, 1.0 - $tidakLayak);
        $layak = min($layak, 1.0 - max($tidakLayak, $kurangLayak));

        // Defuzzifikasi
        $tidakLayakCentroid = $rd['centroid']['tidak_layak'];
        $kurangLayakCentroid = $rd['centroid']['kurang_layak'];
        $layakCentroid = $rd['centroid']['layak'];

        $pembilang = ($tidakLayak * $tidakLayakCentroid) + ($kurangLayak * $kurangLayakCentroid) + ($layak * $layakCentroid);
        $penyebut = $tidakLayak + $kurangLayak + $layak;

        $nilaiKelayakan = ($penyebut > 0) ? $pembilang / $penyebut : 0;

        // Penentuan Status
        if ($nilaiKelayakan < $rd['batas_status']['tidak_bagus']) {
            $status = 'Tidak Bagus';
        } elseif ($nilaiKelayakan < $rd['batas_status']['normal']) {
            $status = 'Normal';
        } else {
            $status = 'Bagus';
        }

        // Return Data
        return [
            'input' => $input,
            'fuzzifikasi' => [
                'LCD' => ['rendah' => $lcdRendah, 'tinggi' => $lcdTinggi],
                'KesehatanBaterai' => [
                    'rendah' => $kesehatanBateraiRendah,
                    'normal' => $kesehatanBateraiNormal,
                    'tinggi' => $kesehatanBateraiTinggi,
                ],
                'Processor' => ['rendah' => $processorRendah, 'normal' => $processorNormal, 'tinggi' => $processorTinggi],
                'Keyboard' => ['rendah' => $keyboardRendah, 'tinggi' => $keyboardTinggi],
            ],
            'inferensi' => [
                'tidak_layak' => $tidakLayak,
                'kurang_layak' => $kurangLayak,
                'layak' => $layak,
            ],
            'nilaiKelayakan' => round($nilaiKelayakan, 2),
            'statusKelayakan' => $status,
        ];
    }

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
}
