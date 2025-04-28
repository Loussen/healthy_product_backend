<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';
    case UNCHANGED = 'unchanged'; // price change / defer gibi durumlarda

    case INACTIVE = 'inactive';
}
