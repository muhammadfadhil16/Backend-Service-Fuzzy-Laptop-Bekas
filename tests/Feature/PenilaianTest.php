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
            'matrix_aturan' => [
                [
                    'lcd' => 'baik',
                    'keyboard' => 'baik',
                    'ram' => 'tinggi',
                    'baterai' => 'tinggi',
                    'processor' => 'tinggi',
                    'output' => 'layak'
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
                'KondisiKeyboard' => 85,
                'RAM' => 8,
                'KesehatanBaterai' => 70,
                'Processor' => 4500
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
                'KondisiKeyboard' => 85,
                'RAM' => 8,
                'KesehatanBaterai' => 70,
                'Processor' => 4500
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
                'KondisiKeyboard' => 40,
                'RAM' => 2,
                'KesehatanBaterai' => 30,
                'Processor' => 1000
            ],
            'rules' => $this->getValidRules()
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success'
                ]);

        $data = $response->json('data');
        $this->assertLessThanOrEqual(65, $data['nilaiKelayakan']);
        $this->assertEquals('Tidak Layak', $data['statusKelayakan']);
    }

    /**
     * Test laptop dengan spesifikasi tinggi
     */
    public function test_laptop_spesifikasi_tinggi(): void
    {
        $response = $this->postJson('/api/evaluator', [
            'input' => [
                'LCD' => 95,
                'KondisiKeyboard' => 95,
                'RAM' => 16,
                'KesehatanBaterai' => 85,
                'Processor' => 5000
            ],
            'rules' => $this->getValidRules()
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertGreaterThan(85, $data['nilaiKelayakan']);
        $this->assertEquals('Layak', $data['statusKelayakan']);
    }

    /**
     * Test nilai Processor harus numerik
     */
    public function test_processor_harus_numerik(): void
    {
        $response = $this->postJson('/api/evaluator', [
            'input' => [
                'LCD' => 80,
                'KondisiKeyboard' => 85,
                'RAM' => 8,
                'KesehatanBaterai' => 70,
                'Processor' => 'delapan'
            ],
            'rules' => $this->getValidRules()
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['input.Processor']);
    }
}
