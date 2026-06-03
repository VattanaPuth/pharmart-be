<?php

namespace App\Services\AdminServices\CategoryServices\impl;

use App\Models\Admin\Category;
use App\Services\AdminServices\CategoryServices\CategoryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CategoryServiceImpl implements CategoryService
{
    public function getAllCategory(): LengthAwarePaginator
    {
        return Category::with('subcategories')->latest()->paginate(20);
    }

    public function visible(): Collection
    {
        return Category::visible()
            ->with(['subcategories' => function ($query) {
                $query->where('active', true);
            }])
            ->latest()
            ->get();
    }

    public function getCategoryById(Category $category): Category
    {
        return $category->load('subcategories');
    }

    public function addCategory(array $data): Category
    {
        return Category::create($data);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $category->update($data);

        if (array_key_exists('active', $data) && $data['active'] === false) {
            $category->subcategories()->update(['active' => false]);
        }

        return $category->load('subcategories');
    }
}
