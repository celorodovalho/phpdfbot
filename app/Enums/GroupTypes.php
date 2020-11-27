<?php
namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Class GroupTypes
 *
 * @method static static CHANNEL()
 * @method static static GROUP()
 * @method static static MAILING()
 * @method static static GITHUB()
 * @method static static LOG()
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
final class GroupTypes extends Enum
{
    public const CHANNEL  = 1;
    public const GROUP    = 2;
    public const MAILING  = 3;
    public const GITHUB   = 4;
    public const LOG      = 5;
}
