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

        $this->assertSame(1.0, $result['inferensi']['tidak_layak']);
        $this->assertLessThanOrEqual(65, $result['nilaiKelayakan']);
        $this->assertSame('Tidak Layak', $result['statusKelayakan']);
    }

    private function rules(): array
    {
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
            ]
        ];
    }
}
