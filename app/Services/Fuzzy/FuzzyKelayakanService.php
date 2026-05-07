<?php

namespace App\Services\Fuzzy;

class FuzzyKelayakanService
{
    public function calculate(array $input): array
    {
        $lcd = $input['LCD'];
        $kesehatanBaterai = $input['KesehatanBaterai'];
        $ram = $input['RAM'];
        $kondisiKeyboard = $input['KondisiKeyboard'];

        // Fuzzifikasi
        $lcdRendah = $this->kurvaTurun($lcd, 20, 50);
        $lcdTinggi = $this->kurvaNaik($lcd, 80, 100);

        $kesehatanBateraiRendah = $this->kurvaTurun($kesehatanBaterai, 40, 60);
        $kesehatanBateraiNormal = $this->kurvaSegitiga($kesehatanBaterai, 40, 60, 80);
        $kesehatanBateraiTinggi = $this->kurvaNaik($kesehatanBaterai, 80, 100);

        $ramRendah = $this->kurvaTurun($ram, 4, 8);
        $ramSedang = $this->kurvaSegitiga($ram, 4, 8, 16);
        $ramTinggi = $this->kurvaNaik($ram, 8, 16);

        $keyboardRendah = $this->kurvaTurun($kondisiKeyboard, 20, 50);
        $keyboardTinggi = $this->kurvaNaik($kondisiKeyboard, 70, 100);

        // Inferensi
        $tidakLayak = max(
            $lcdRendah,
            $kesehatanBateraiRendah,
            $ramRendah,
            $keyboardRendah
        );

        $layak = ($lcdTinggi + $kesehatanBateraiTinggi + $ramTinggi + $keyboardTinggi) / 4;

        $kurangLayak = max(
            $kesehatanBateraiNormal,
            $ramSedang
        );

        // Defuzzifikasi
        $tidakLayakCentroid = 30;
        $kurangLayakCentroid = 60;
        $layakCentroid = 90;

        $pembilang = ($tidakLayak * $tidakLayakCentroid)
            + ($kurangLayak * $kurangLayakCentroid)
            + ($layak * $layakCentroid);

        $penyebut = $tidakLayak + $kurangLayak + $layak;

        $nilaiKelayakan = ($penyebut > 0) ? $pembilang / $penyebut : 0;

        if ($nilaiKelayakan < 40) {
            $status = 'Tidak Bagus';
        } elseif ($nilaiKelayakan < 65) {
            $status = 'Normal';
        } else {
            $status = 'Bagus';
        }

        return [
            'input' => [
                'LCD' => $lcd,
                'KesehatanBaterai' => $kesehatanBaterai,
                'RAM' => $ram,
                'KondisiKeyboard' => $kondisiKeyboard,
            ],
            'fuzzifikasi' => [
                'LCD' => ['rendah' => $lcdRendah, 'tinggi' => $lcdTinggi],
                'KesehatanBaterai' => [
                    'rendah' => $kesehatanBateraiRendah,
                    'normal' => $kesehatanBateraiNormal,
                    'tinggi' => $kesehatanBateraiTinggi,
                ],
                'RAM' => ['rendah' => $ramRendah, 'sedang' => $ramSedang, 'tinggi' => $ramTinggi],
                'Keyboard' => ['rendah' => $keyboardRendah, 'tinggi' => $keyboardTinggi],
            ],
            'nilaiKelayakan' => round($nilaiKelayakan, 2),
            'statusKelayakan' => $status,
        ];
    }

    private function kurvaTurun(float $x, float $a, float $b): float
    {
        if ($x <= $a) {
            return 1.0;
        }
        if ($x >= $b) {
            return 0.0;
        }

        return ($b - $x) / ($b - $a);
    }

    private function kurvaNaik(float $x, float $a, float $b): float
    {
        if ($x <= $a) {
            return 0.0;
        }
        if ($x >= $b) {
            return 1.0;
        }

        return ($x - $a) / ($b - $a);
    }

    private function kurvaSegitiga(float $x, float $a, float $b, float $c): float
    {
        if ($x <= $a || $x >= $c) {
            return 0.0;
        }
        if ($x == $b) {
            return 1.0;
        }
        if ($x > $a && $x < $b) {
            return ($x - $a) / ($b - $a);
        }

        return ($c - $x) / ($c - $b);
    }
}
