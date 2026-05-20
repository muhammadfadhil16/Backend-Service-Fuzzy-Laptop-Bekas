<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Fuzzy\FuzzyKelayakanService;

class PenilaianController extends Controller
{
    private FuzzyKelayakanService $fuzzyKelayakanService;

    public function __construct(FuzzyKelayakanService $fuzzyKelayakanService)
    {
        $this->fuzzyKelayakanService = $fuzzyKelayakanService;
    }

    public function penilaian(Request $request)
    {
        // Validasi struktur
        $validated = $request->validate([
            'input' => 'required|array',
            'input.LCD' => 'required|numeric|between:0,100',
            'input.KesehatanBaterai' => 'required|numeric|between:0,100',
            'input.RAM' => 'required|numeric',
            'input.KondisiKeyboard' => 'required|numeric|between:0,100',
            
            // Validasi Rules
            'rules' => 'required|array',
            'rules.fuzzifikasi' => 'required|array',
            'rules.defuzzifikasi' => 'required|array',
        ]);

        // Lempar input dan rules ke Service
        $hasil = $this->fuzzyKelayakanService->calculate(
            $validated['input'], 
            $validated['rules']
        );
        
        return response()->json([
            'status' => 'success',
            'data' => $hasil,
        ]);
    }
}
