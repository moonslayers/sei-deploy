<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Frontend extends Model
{
    protected $fillable = ['name'];

    protected function casts(): array
    {
        return [];
    }

    public function setNameAttribute($value): void
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Frontend name must be a string.');
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Frontend name cannot be empty.');
        }

        if (! preg_match('#^[A-Za-z0-9._-]+$#', $trimmed)) {
            throw new InvalidArgumentException('Frontend name contains invalid characters.');
        }

        $this->attributes['name'] = $trimmed;
    }
}
