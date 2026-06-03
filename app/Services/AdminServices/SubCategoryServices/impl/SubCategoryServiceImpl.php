<?php

namespace App\Services\AdminServices\SubCategoryServices\impl;

use App\Models\Admin\Category;
use App\Models\Admin\SubCategory;
use App\Services\AdminServices\SubCategoryServices\SubCategoryService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SubCategoryServiceImpl implements SubCategoryService
{
    public function getAllSubCategory(Category $category): Collection
    {
        return $category->subcategories;
    }

    public function visible(): Collection
    {
        return SubCategory::visible()
            ->with('category')
            ->latest()
            ->get();
    }

    public function getSubCategoryById(SubCategory $subcategory): SubCategory
    {
        return $subcategory->load('category');
    }

    public function addSubCategory(Category $category, array $data): SubCategory
    {
        if (!$category->active && ($data['active'] ?? true) === true) {
            throw ValidationException::withMessages([
                'active' => ['Cannot create an active subcategory under an inactive category.'],
            ]);
        }

        $subcategory = $category->subcategories()->create($data);

        return $subcategory->load('category');
    }

    public function updateSubCategory(SubCategory $subcategory, array $data): SubCategory
    {
        if (array_key_exists('active', $data) && $data['active'] === true && !$subcategory->category->active) {
            throw ValidationException::withMessages([
                'active' => ['Cannot activate a subcategory while its category is inactive.'],
            ]);
        }

        $subcategory->update($data);

        return $subcategory->load('category');
    }
}
