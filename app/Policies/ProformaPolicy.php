<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Proforma\Proforma;
use Illuminate\Auth\Access\Response;

class ProformaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if($user->can('list_proforma')){
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Proforma $proforma = null): bool
    {
        if($user->can('edit_proforma')){
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if($user->can('register_proforma')){
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Proforma $proforma = null): bool
    {
        if($user->can('edit_proforma')){
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Proforma $proforma = null): bool
    {
        if($user->can('delete_proforma')){
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Proforma $proforma): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Proforma $proforma): bool
    {
        //
    }
}
