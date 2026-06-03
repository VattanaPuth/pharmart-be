<?php

namespace App\Services\AdminServices\CategoryServices;

use App\Models\Admin\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CategoryService
{
    public function getAllCategory(): LengthAwarePaginator;
    public function visible(): Collection;
    public function getCategoryById(Category $category): Category;
    public function addCategory(array $data): Category;
    public function updateCategory(Category $category, array $data): Category;
}
