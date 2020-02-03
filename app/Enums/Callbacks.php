<?php
namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Class GroupTypes
 *
 * @method static static REMOVE()
 * @method static static APPROVE()
 * @method static static OPTIONS()
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
final class Callbacks extends Enum
{
    public const REMOVE = 'remove';
    public const APPROVE = 'approve';
    public const OPTIONS = 'options';
}
