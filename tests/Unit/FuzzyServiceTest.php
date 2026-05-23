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
                'KesehatanBaterai' => 0,
                'Processor' => 10000,
                'KondisiKeyboard' => 0,
            ],
            $this->rules()
        );

        $this->assertSame(1.0, $result['inferensi']['tidak_layak']);
        $this->assertSame(0.0, $result['inferensi']['kurang_layak']);
        $this->assertSame(30.0, $result['nilaiKelayakan']);
        $this->assertSame('Tidak Bagus', $result['statusKelayakan']);
    }

    private function rules(): array
    {
        return [
            'fuzzifikasi' => [
                'LCD' => [
                    'rendah' => [40, 60],
                    'normal' => [40, 60, 80],
                    'tinggi' => [60, 80],
                ],
                'KesehatanBaterai' => [
                    'rendah' => [30, 50],
                    'normal' => [30, 60, 85],
                    'tinggi' => [70, 90],
                ],
                'Processor' => [
                    'rendah' => [500, 10000],
                    'normal' => [500, 10000, 15000],
                    'tinggi' => [10000, 15000],
                ],
                'KondisiKeyboard' => [
                    'rendah' => [40, 70],
                    'normal' => [40, 70, 90],
                    'tinggi' => [70, 90],
                ],
            ],
            'defuzzifikasi' => [
                'centroid' => [
                    'tidak_layak' => 30,
                    'kurang_layak' => 60,
                    'layak' => 90,
                ],
                'batas_status' => [
                    'tidak_bagus' => 40,
                    'normal' => 65,
                ],
            ],
        ];
    }
}
