<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CertificationType: string implements HasLabel
{
    use HasAdminLabel;

    case Certification = 'certification';
    case Accreditation = 'accreditation';
    case Course = 'course';
    case Workshop = 'workshop';
    case TrainingPath = 'training_path';
    case Exam = 'exam';
    case Badge = 'badge';

    protected static function labelGroup(): string
    {
        return 'certification_type';
    }
}
