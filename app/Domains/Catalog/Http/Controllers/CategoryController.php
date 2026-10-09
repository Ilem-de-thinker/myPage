<?php

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();

        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $category = Category::create([
            'seller_id' => $request->user()->id,
            'store_page_id' => $page->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'position' => Category::where('store_page_id', $page->id)->count(),
        ]);

        return response()->json(['id' => $category->id, 'name' => $category->name], 201);
    }
}
