<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModelProvider extends Model
{
    protected $fillable = ['slug', 'name'];

    public function aiModels(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }
}
