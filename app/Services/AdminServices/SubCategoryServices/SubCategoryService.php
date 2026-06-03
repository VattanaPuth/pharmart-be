<?php

namespace App\Services\AdminServices\SubCategoryServices;

use App\Models\Admin\Category;
use App\Models\Admin\SubCategory;
use Illuminate\Support\Collection;

interface SubCategoryService
{
    public function getAllSubCategory(Category $category): Collection;
    public function visible(): Collection;
    public function getSubCategoryById(SubCategory $subcategory): SubCategory;
    public function addSubCategory(Category $category, array $data): SubCategory;
    public function updateSubCategory(SubCategory $subcategory, array $data): SubCategory;
}
