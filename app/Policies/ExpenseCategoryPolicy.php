<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    public function view(User $user, ExpenseCategory $category): bool
    {
        return $category->user_id === null || $category->user_id === $user->id;
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $category->user_id === $user->id;
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $this->update($user, $category);
    }
}
