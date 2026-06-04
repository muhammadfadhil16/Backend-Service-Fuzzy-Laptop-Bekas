<?php

namespace Tests\Feature;

use Tests\TestCase;

class PenilaianTest extends TestCase
{
    private function getValidRules(): array
    {
        return [
            'fuzzifikasi' => [
                'LCD' => [
                    'rendah' => [40, 60],
                    'tinggi' => [60, 80]
                ],
                'KesehatanBaterai' => [
                    'rendah' => [30, 50],
                    'normal' => [30, 60, 85],
                    'tinggi' => [70, 90]
                ],
                'Processor' => [
                    'rendah' => [500, 10000],
                    'normal' => [500, 10000, 15000],
                    'tinggi' => [10000, 15000]
                ],
                'KondisiKeyboard' => [
                    'rendah' => [40, 70],
                    'tinggi' => [70, 90]
                ]
            ],
            'defuzzifikasi' => [
                'centroid' => [
                    'tidak_layak' => 30,
                    'kurang_layak' => 60,
                    'layak' => 90
                ],
                'batas_status' => [
                    'tidak_bagus' => 40,
                    'normal' => 65
                ]
            ]
        ];
    }

    /**
     * Test endpoint penilaian dengan input valid
     */
    public function test_penilaian_dengan_input_valid(): void
    {
        $response = $this->postJson('/api/evaluator', [
            'input' => [
                'LCD' => 80,
                'KesehatanBaterai' => 70,
                'Processor' => 8000,
                'KondisiKeyboard' => 85
            ],
            'rules' => $this->getValidRules()
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'input',
                        'fuzzifikasi',
                        'inferensi',
                        'nilaiKelayakan',
                        'statusKelayakan'
                    ]
                ]);
    }

    /**
     * Test validasi input LCD harus antara 0-100
     */
    public function test_validasi_lcd_harus_antara_0_100(): void
    {
        $response = $this->postJson('/api/evaluator', [
            'input' => [
                'LCD' => 150,
                'KesehatanBaterai' => 70,
                'Processor' => 8000,
                'KondisiKeyboard' => 85
            ],
            'rules' => $this->getValidRules()
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['input.LCD']);
    }

    /**
     * Test validasi field wajib diisi
     */
    public function test_validasi_field_wajib_diisi(): void
    {
        $response = $this->postJson('/api/evaluator', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'input',
                    'rules'
                ]);
    }

    /**
     * Test laptop dengan spesifikasi rendah
     */
    public function test_laptop_spesifikasi_rendah(): void
    {
        $response = $this->postJson('/api/evaluator', [
            'input' => [
                'LCD' => 40,
                'KesehatanBaterai' => 30,
                'Processor' => 4000,
                'KondisiKeyboard' => 40
            ],
            'rules' => $this->getValidRules()
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success'
                ]);

        $data = $response->json('data');
        $this->assertLessThan(50, $data['nilaiKelayakan']);
    }

    /**
     * Test laptop dengan spesifikasi tinggi
     */
    public function test_laptop_spesifikasi_tinggi(): void
    {
        $response = $this->postJson('/api/evaluator', [
            'input' => [
                'LCD' => 95,
                'KesehatanBaterai' => 85,
                'Processor' => 12000,
                'KondisiKeyboard' => 95
            ],
            'rules' => $this->getValidRules()
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertGreaterThan(50, $data['nilaiKelayakan']);
    }

    /**
     * Test nilai Processor harus numerik
     */
    public function test_processor_harus_numerik(): void
    {
        $response = $this->postJson('/api/evaluator', [
            'input' => [
                'LCD' => 80,
                'KesehatanBaterai' => 70,
                'Processor' => 'delapan',
                'KondisiKeyboard' => 85
            ],
            'rules' => $this->getValidRules()
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['input.Processor']);
    }
}
