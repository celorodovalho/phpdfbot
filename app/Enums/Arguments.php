<?php
namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Class GroupTypes
 *
 * @method static static SEND()
 * @method static static PROCESS()
 * @method static static APPROVAL()
 * @method static static NOTIFY()
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
final class Arguments extends Enum
{
    public const SEND = 'send';
    public const PROCESS = 'process';
    public const APPROVAL = 'approval';
    public const NOTIFY = 'notify';
}
