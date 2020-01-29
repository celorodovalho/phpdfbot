<?php
namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Class Days
 *
 * @method static static Monday()
 * @method static static Tuesday()
 * @method static static Wednesday()
 * @method static static Thursday()
 * @method static static Friday()
 * @method static static Saturday()
 * @method static static Sunday()
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
final class Days extends Enum
{
    public const Monday    = 1;
    public const Tuesday   = 2;
    public const Wednesday = 3;
    public const Thursday  = 4;
    public const Friday    = 5;
    public const Saturday  = 6;
    public const Sunday    = 7;
}
