<?php

namespace App\Http\Controllers;

use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        // Get random products for "New Additions" carousel
        $newAdditions = Product::inRandomOrder()
            ->take(9) // Get 9 for smooth carousel rotation
            ->get();

        return view('home', [
            'newAdditions' => $newAdditions,
            'introNav' => true,
        ]);
    }
}
