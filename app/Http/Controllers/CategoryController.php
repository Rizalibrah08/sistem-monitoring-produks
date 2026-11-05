<?php

namespace App\Http\Controllers;

use App\Actions\SaveMasterRecord;
use App\Http\Requests\SaveCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;

class CategoryController extends Controller
{
    public function store(SaveCategoryRequest $request, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle(new Category, 'category', $request->validated(), $request->user());

        return back()->with('status', 'Kategori berhasil ditambahkan.');
    }

    public function update(SaveCategoryRequest $request, Category $category, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle($category, 'category', $request->validated(), $request->user());

        return back()->with('status', 'Kategori berhasil diperbarui.');
    }
}
