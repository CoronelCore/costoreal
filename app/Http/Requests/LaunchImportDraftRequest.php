<?php

namespace App\Http\Requests;

class LaunchImportDraftRequest extends TripRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'draft_revision' => 'required|integer|min:1', 'mode' => 'required|in:guided,quick'];
    }
}
