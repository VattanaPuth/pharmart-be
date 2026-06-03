<?php

namespace App\Http\Controllers\AdminControllers\categoryController;

use App\Http\Controllers\Controller;
use App\Models\Admin\Category;
use App\Services\AdminServices\CategoryServices\CategoryService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categoryService) {}

    public function getAllCategory()
    {
        return $this->categoryService->getAllCategory();
    }

    public function visible()
    {
        return $this->categoryService->visible();
    }

    public function getCategoryById(Category $category)
    {
        return $this->categoryService->getCategoryById($category);
    }

    public function addCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255','unique:category,name'],
            'active' => ['nullable','boolean'],
        ]);

        $category = $this->categoryService->addCategory($data);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    public function updateCategory(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['sometimes','required','string','max:255','unique:category,name,' . $category->id],
            'active' => ['sometimes','boolean'],
        ]);

        $category = $this->categoryService->updateCategory($category, $data);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }
}
