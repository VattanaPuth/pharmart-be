<?php

namespace App\Http\Controllers\AdminControllers\subCategoryController;

use App\Http\Controllers\Controller;
use App\Models\Admin\Category;
use App\Models\Admin\SubCategory;
use App\Services\AdminServices\SubCategoryServices\SubCategoryService;
use Illuminate\Http\Request;

class SubCategoryController extends Controller
{
    public function __construct(private SubCategoryService $subCategoryService) {}

    public function getAllSubCategory(Category $category)
    {
        return $this->subCategoryService->getAllSubCategory($category);
    }

    public function visible()
    {
        return $this->subCategoryService->visible();
    }

    public function getSubCategoryById(SubCategory $subcategory)
    {
        return $this->subCategoryService->getSubCategoryById($subcategory);
    }

    public function addSubCategory(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'active' => ['nullable','boolean'],
        ]);

        $subcategory = $this->subCategoryService->addSubCategory($category, $data);

        return response()->json([
            'message' => 'Subcategory created successfully',
            'data' => $subcategory
        ], 201);
    }

    public function updateSubCategory(Request $request, SubCategory $subcategory)
    {
        $data = $request->validate([
            'name' => ['sometimes','required','string','max:255'],
            'active' => ['sometimes','boolean'],
        ]);

        $subcategory = $this->subCategoryService->updateSubCategory($subcategory, $data);

        return response()->json([
            'message' => 'Subcategory updated successfully',
            'data' => $subcategory
        ]);
    }
}
