<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Fuzzy\FuzzyService;

class EvaluationController extends Controller
{
    private FuzzyService $fuzzyService;

    public function __construct(FuzzyService $fuzzyService)
    {
        $this->fuzzyService = $fuzzyService;
    }

    public function evaluator(Request $request)
    {
        // Validasi struktur
        $validated = $request->validate([
            'input' => 'required|array',
            'input.LCD' => 'required|numeric|between:0,100',
            'input.KesehatanBaterai' => 'required|numeric|between:0,100',
            'input.Processor' => 'required|numeric',
            'input.KondisiKeyboard' => 'required|numeric|between:0,100',
            
            // Validasi Rules
            'rules' => 'required|array',
            'rules.fuzzifikasi' => 'required|array',
            'rules.defuzzifikasi' => 'required|array',
        ]);

        // Lempar input dan rules ke Service
        $hasil = $this->fuzzyService->calculate(
            $validated['input'], 
            $validated['rules']
        );
        
        return response()->json([
            'status' => 'success',
            'data' => $hasil,
        ]);
    }
}
