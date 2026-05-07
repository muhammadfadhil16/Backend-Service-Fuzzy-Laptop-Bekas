<?php

namespace Tests\Feature;

use Tests\TestCase;

class PenilaianTest extends TestCase
{
    /**
     * Test endpoint penilaian dengan input valid
     */
    public function test_penilaian_dengan_input_valid(): void
    {
        $response = $this->postJson('/api/penilaian', [
            'LCD' => 80,
            'KesehatanBaterai' => 70,
            'RAM' => 8,
            'KondisiKeyboard' => 85
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'input',
                        'fuzzifikasi',
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
        $response = $this->postJson('/api/penilaian', [
            'LCD' => 150,
            'KesehatanBaterai' => 70,
            'RAM' => 8,
            'KondisiKeyboard' => 85
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['LCD']);
    }

    /**
     * Test validasi field wajib diisi
     */
    public function test_validasi_field_wajib_diisi(): void
    {
        $response = $this->postJson('/api/penilaian', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'LCD',
                    'KesehatanBaterai',
                    'RAM',
                    'KondisiKeyboard'
                ]);
    }

    /**
     * Test laptop dengan spesifikasi rendah
     */
    public function test_laptop_spesifikasi_rendah(): void
    {
        $response = $this->postJson('/api/penilaian', [
            'LCD' => 40,
            'KesehatanBaterai' => 30,
            'RAM' => 4,
            'KondisiKeyboard' => 40
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
        $response = $this->postJson('/api/penilaian', [
            'LCD' => 95,
            'KesehatanBaterai' => 85,
            'RAM' => 16,
            'KondisiKeyboard' => 95
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertGreaterThan(50, $data['nilaiKelayakan']);
    }

    /**
     * Test nilai RAM harus numerik
     */
    public function test_ram_harus_numerik(): void
    {
        $response = $this->postJson('/api/penilaian', [
            'LCD' => 80,
            'KesehatanBaterai' => 70,
            'RAM' => 'delapan',
            'KondisiKeyboard' => 85
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['RAM']);
    }
}
