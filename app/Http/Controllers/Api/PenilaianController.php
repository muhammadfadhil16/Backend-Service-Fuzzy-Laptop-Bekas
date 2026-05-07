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

    public function perhitungan(Request $request)
    {
        $validated = $request->validate([
            'LCD' => 'required|numeric|between:0,100',
            'KesehatanBaterai' => 'required|numeric|between:0,100',
            'RAM' => 'required|numeric',
            'KondisiKeyboard' => 'required|numeric|between:0,100',
        ]);

        $hasil = $this->fuzzyKelayakanService->calculate($validated);
        
        return response()->json([
            'status' => 'success',
            'data' => $hasil,
        ]);
    }
}
