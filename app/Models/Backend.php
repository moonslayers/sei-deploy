<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Backend extends Model
{
    protected $fillable = ['repo', 'path', 'migrate_command', 'script'];

    protected function casts(): array
    {
        return [];
    }

    public function setPathAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['path'] = null;

            return;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Backend path must be a string or null.');
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Backend path cannot be empty.');
        }

        if (str_starts_with($trimmed, '/') || str_starts_with($trimmed, '\\')) {
            throw new InvalidArgumentException('Backend path must be relative.');
        }

        foreach (explode('/', str_replace('\\', '/', $trimmed)) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Backend path contains invalid segment.');
            }
        }

        if (! preg_match('#^[A-Za-z0-9._\-/]+$#', $trimmed)) {
            throw new InvalidArgumentException('Backend path contains invalid characters.');
        }

        $this->attributes['path'] = $trimmed;
    }

    public function getResolvedPathAttribute(): string
    {
        if ($this->path) {
            return base_path('../'.trim($this->path, '/'));
        }

        $basename = basename(str_replace('\\', '/', $this->repo));

        return base_path('../'.$basename);
    }
}
