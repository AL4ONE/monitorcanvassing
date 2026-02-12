<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RegionController extends Controller
{
    // Alternate source (Raw GitHub)
    private $baseUrl = 'https://emsifa.github.io/api-wilayah-indonesia/api';

    public function provinces()
    {
        // Fallback: Hardcoded Provinces to ensure it works even if API fails
        $hardcodedProvinces = [
            ["id" => "11", "name" => "ACEH"],
            ["id" => "12", "name" => "SUMATERA UTARA"],
            ["id" => "13", "name" => "SUMATERA BARAT"],
            ["id" => "14", "name" => "RIAU"],
            ["id" => "15", "name" => "JAMBI"],
            ["id" => "16", "name" => "SUMATERA SELATAN"],
            ["id" => "17", "name" => "BENGKULU"],
            ["id" => "18", "name" => "LAMPUNG"],
            ["id" => "19", "name" => "KEPULAUAN BANGKA BELITUNG"],
            ["id" => "21", "name" => "KEPULAUAN RIAU"],
            ["id" => "31", "name" => "DKI JAKARTA"],
            ["id" => "32", "name" => "JAWA BARAT"],
            ["id" => "33", "name" => "JAWA TENGAH"],
            ["id" => "34", "name" => "DI YOGYAKARTA"],
            ["id" => "35", "name" => "JAWA TIMUR"],
            ["id" => "36", "name" => "BANTEN"],
            ["id" => "51", "name" => "BALI"],
            ["id" => "52", "name" => "NUSA TENGGARA BARAT"],
            ["id" => "53", "name" => "NUSA TENGGARA TIMUR"],
            ["id" => "61", "name" => "KALIMANTAN BARAT"],
            ["id" => "62", "name" => "KALIMANTAN TENGAH"],
            ["id" => "63", "name" => "KALIMANTAN SELATAN"],
            ["id" => "64", "name" => "KALIMANTAN TIMUR"],
            ["id" => "65", "name" => "KALIMANTAN UTARA"],
            ["id" => "71", "name" => "SULAWESI UTARA"],
            ["id" => "72", "name" => "SULAWESI TENGAH"],
            ["id" => "73", "name" => "SULAWESI SELATAN"],
            ["id" => "74", "name" => "SULAWESI TENGGARA"],
            ["id" => "75", "name" => "GORONTALO"],
            ["id" => "76", "name" => "SULAWESI BARAT"],
            ["id" => "81", "name" => "MALUKU"],
            ["id" => "82", "name" => "MALUKU UTARA"],
            ["id" => "91", "name" => "PAPUA BARAT"],
            ["id" => "94", "name" => "PAPUA"]
        ];

        // Try to fetch, if fail use hardcoded
        try {
            return Cache::remember('provinces', 86400, function () {
                // Use ::withoutVerifying() for stricter Laravel compatibility
                $response = Http::withoutVerifying()->get("{$this->baseUrl}/provinces.json");
                if ($response->successful())
                    return $response->json();
                throw new \Exception('Remote Status: ' . $response->status());
            });
        } catch (\Exception $e) {
            Log::warning('Provinces Fetch Error (Using Fallback): ' . $e->getMessage());
            return $hardcodedProvinces; // Return array directly
        }
    }

    public function cities($provinceId)
    {
        try {
            return Cache::remember("cities_{$provinceId}", 86400, function () use ($provinceId) {
                // Note: GitHub Raw URL structure is same: /regencies/{id}.json
                $response = Http::withoutVerifying()->get("{$this->baseUrl}/regencies/{$provinceId}.json");
                if ($response->successful())
                    return $response->json();
                throw new \Exception('Remote Status: ' . $response->status());
            });
        } catch (\Exception $e) {
            Log::error('Cities Error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal memuat kota. Cek koneksi internet.'], 500);
        }
    }

    public function districts($cityId)
    {
        try {
            return Cache::remember("districts_{$cityId}", 86400, function () use ($cityId) {
                $response = Http::withoutVerifying()->get("{$this->baseUrl}/districts/{$cityId}.json");
                if ($response->successful())
                    return $response->json();
                throw new \Exception('Remote Status: ' . $response->status());
            });
        } catch (\Exception $e) {
            Log::error('Districts Error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal memuat kecamatan.'], 500);
        }
    }
}
