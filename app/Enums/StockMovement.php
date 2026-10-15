<?php

namespace App\Enums;

enum StockMovement: string
{
    case Add = 'add';
    case Remove = 'remove';
    case Pull = 'pull';
    case Return = 'return';
    case Transfer = 'transfer';
    case TakeBack = 'take_back';
    case GiveOut = 'give_out';
}
