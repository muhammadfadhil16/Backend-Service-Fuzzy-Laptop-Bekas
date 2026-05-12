<?php

namespace App\Services\Fuzzy;

class FuzzyKelayakanService
{
    public function calculate(array $input, array $rules): array
    {
        $lcd = $input['LCD'];
        $kesehatanBaterai = $input['KesehatanBaterai'];
        $ram = $input['RAM'];
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

        $ramRendah = $this->kurvaTurun($ram, $rf['RAM']['rendah'][0], $rf['RAM']['rendah'][1]);
        $ramNormal = $this->kurvaSegitiga($ram, $rf['RAM']['normal'][0], $rf['RAM']['normal'][1], $rf['RAM']['normal'][2]);
        $ramTinggi = $this->kurvaNaik($ram, $rf['RAM']['tinggi'][0], $rf['RAM']['tinggi'][1]);

        $keyboardRendah = $this->kurvaTurun($kondisiKeyboard, $rf['KondisiKeyboard']['rendah'][0], $rf['KondisiKeyboard']['rendah'][1]);
        $keyboardTinggi = $this->kurvaNaik($kondisiKeyboard, $rf['KondisiKeyboard']['tinggi'][0], $rf['KondisiKeyboard']['tinggi'][1]);

        // Inferensi
        $tidakLayak = max($lcdRendah, $kesehatanBateraiRendah, $ramRendah, $keyboardRendah);
        $layak = ($lcdTinggi + $kesehatanBateraiTinggi + $ramTinggi + $keyboardTinggi) / 4;
        $kurangLayak = max($kesehatanBateraiNormal, $ramNormal);

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
                'RAM' => ['rendah' => $ramRendah, 'normal' => $ramNormal, 'tinggi' => $ramTinggi],
                'Keyboard' => ['rendah' => $keyboardRendah, 'tinggi' => $keyboardTinggi],
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
