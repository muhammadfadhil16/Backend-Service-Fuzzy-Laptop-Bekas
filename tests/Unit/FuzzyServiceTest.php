<?php

namespace Tests\Unit;

use App\Services\Fuzzy\FuzzyService;
use PHPUnit\Framework\TestCase;

class FuzzyServiceTest extends TestCase
{
    public function test_komponen_kritis_rendah_tidak_terangkat_oleh_processor_normal(): void
    {
        $service = new FuzzyService();

        $result = $service->calculate(
            [
                'LCD' => 0,
                'KondisiKeyboard' => 0,
                'RAM' => 2,
                'KesehatanBaterai' => 0,
                'Processor' => 3000,
            ],
            $this->rules()
        );

        $this->assertEquals(0.67, round($result['inferensi']['tidak_layak'], 2));
        $this->assertLessThanOrEqual(65, $result['nilaiKelayakan']);
        $this->assertSame('Tidak Layak', $result['statusKelayakan']);
    }

    private function determineOutput(string $lcd, string $keyboard, string $ram, string $baterai, string $benchmark): string
    {
        if ($lcd === 'buruk' && $keyboard === 'buruk' && $ram === 'rendah') {
            return 'tidak_layak';
        }
        if ($lcd === 'buruk' && $keyboard === 'buruk' && $ram === 'sedang' && $baterai === 'rendah') {
            return 'tidak_layak';
        }
        if ($lcd === 'buruk' && $keyboard === 'buruk' && $ram === 'tinggi' && $baterai === 'rendah') {
            return 'tidak_layak';
        }
        if ($lcd === 'buruk' && $keyboard === 'sedang' && $ram === 'rendah' && $baterai === 'rendah') {
            return 'tidak_layak';
        }
        if ($lcd === 'buruk' && $keyboard === 'baik' && $ram === 'rendah' && $baterai === 'rendah') {
            return 'tidak_layak';
        }
        if ($lcd === 'sedang' && $keyboard === 'buruk' && $ram === 'rendah' && $baterai === 'rendah') {
            return 'tidak_layak';
        }
        if ($lcd === 'baik' && $keyboard === 'buruk' && $ram === 'rendah' && $baterai === 'rendah') {
            return 'tidak_layak';
        }

        if ($lcd === 'sedang' && $keyboard === 'baik' && $ram === 'sedang' && $baterai === 'tinggi') {
            return 'layak';
        }
        if ($lcd === 'sedang' && $keyboard === 'baik' && $ram === 'tinggi' && $baterai === 'tinggi') {
            return 'layak';
        }
        if ($lcd === 'baik' && $keyboard === 'sedang' && $ram === 'sedang' && $baterai === 'tinggi') {
            return 'layak';
        }
        if ($lcd === 'baik' && $keyboard === 'sedang' && $ram === 'tinggi' && $baterai === 'tinggi') {
            return 'layak';
        }
        if ($lcd === 'baik' && $keyboard === 'baik' && $ram === 'sedang' && $baterai === 'sedang') {
            return 'layak';
        }
        if ($lcd === 'baik' && $keyboard === 'baik' && $ram === 'sedang' && $baterai === 'tinggi') {
            return 'layak';
        }
        if ($lcd === 'baik' && $keyboard === 'baik' && $ram === 'tinggi' && $baterai === 'sedang') {
            return 'layak';
        }
        if ($lcd === 'baik' && $keyboard === 'baik' && $ram === 'tinggi' && $baterai === 'tinggi') {
            return 'layak';
        }

        return 'cukup_layak';
    }

    private function rules(): array
    {
        $lcdOptions       = ['buruk', 'sedang', 'baik'];
        $keyboardOptions  = ['buruk', 'sedang', 'baik'];
        $ramOptions       = ['rendah', 'sedang', 'tinggi'];
        $bateraiOptions   = ['rendah', 'sedang', 'tinggi'];
        $benchmarkOptions = ['rendah', 'sedang', 'tinggi'];

        $matrix = [];
        foreach ($lcdOptions as $lcd) {
            foreach ($keyboardOptions as $keyboard) {
                foreach ($ramOptions as $ram) {
                    foreach ($bateraiOptions as $baterai) {
                        foreach ($benchmarkOptions as $benchmark) {
                            $matrix[] = [
                                'lcd'        => $lcd,
                                'keyboard'   => $keyboard,
                                'ram'        => $ram,
                                'baterai'    => $baterai,
                                'processor'  => $benchmark,
                                'output'     => $this->determineOutput($lcd, $keyboard, $ram, $baterai, $benchmark),
                            ];
                        }
                    }
                }
            }
        }

        return [
            'fuzzifikasi' => [
                'LCD' => [
                    'buruk' => [40, 60],
                    'sedang' => [40, 50, 70, 80],
                    'baik' => [60, 80]
                ],
                'KondisiKeyboard' => [
                    'buruk' => [40, 60],
                    'sedang' => [40, 50, 70, 80],
                    'baik' => [60, 80]
                ],
                'RAM' => [
                    'rendah' => [2, 4],
                    'sedang' => [2, 4, 8],
                    'tinggi' => [4, 8]
                ],
                'KesehatanBaterai' => [
                    'rendah' => [40, 60],
                    'sedang' => [40, 60, 80],
                    'tinggi' => [60, 80]
                ],
                'Processor' => [
                    'rendah' => [1000, 2000],
                    'sedang' => [1000, 2500, 4000],
                    'tinggi' => [3000, 5000]
                ]
            ],
            'defuzzifikasi' => [
                'tidak_layak' => [30, 50],
                'cukup_layak' => [40, 60, 70, 90],
                'layak' => [80, 100]
            ],
            'matrix_aturan' => $matrix
        ];
    }
}
