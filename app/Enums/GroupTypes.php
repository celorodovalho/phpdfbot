<?php
namespace App\Enums;

use BenSampo\Enum\Enum;

final class GroupTypes extends Enum
{
    const TYPE_CHANNEL  = 1;
    const TYPE_GROUP    = 2;
    const TYPE_MAILING  = 3;
    const TYPE_GITHUB   = 4;
}
