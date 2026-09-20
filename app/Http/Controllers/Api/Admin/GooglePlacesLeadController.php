<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GooglePlacesService;

class GooglePlacesLeadController extends Controller
{
    /**
     * Discover & Ingest B2B leads from Google Places API
     */
    public function discover(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|max:100',
            'city' => 'nullable|string|max:100',
        ]);

        $keyword = $request->input('keyword', 'caterers');
        $city = $request->input('city', 'Mumbai');

        $service = new GooglePlacesService();
        $result = $service->discoverAndIngestLeads($keyword, $city);

        return response()->json($result);
    }
}
