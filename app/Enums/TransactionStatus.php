<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Draft = 'draft';
    case PendingDistribution = 'pending_distribution';
    case ProcessingDistribution = 'processing_distribution';
    case Distributed = 'distributed';
    case DistributionFailed = 'distribution_failed';
    case Cancelled = 'cancelled';
}
