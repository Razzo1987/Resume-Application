<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Azienda, cliente, ateneo, ente certificatore o associazione. Il logo si carica una volta sola.
 *
 * @property int $id
 * @property string $name
 * @property OrganizationType $type
 * @property string|null $website
 * @property string|null $logo
 */
class Organization extends Model
{
    protected $fillable = ['name', 'type', 'website', 'logo'];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
        ];
    }

    /** @return HasMany<Experience, $this> */
    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    /** @return HasMany<Project, $this> */
    public function clientProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    /** @return HasMany<Education, $this> */
    public function educations(): HasMany
    {
        return $this->hasMany(Education::class);
    }

    /** @return HasMany<Certification, $this> */
    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }
}
