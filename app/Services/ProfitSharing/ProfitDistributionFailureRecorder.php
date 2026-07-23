<?php

namespace App\Services\ProfitSharing;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidProfitDistribution;
use App\Exceptions\Transactions\TransactionDistributionNotProcessable;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Throwable;

final class ProfitDistributionFailureRecorder
{
    private const MAX_REASON_LENGTH = 1000;

    public function record(
        int $transactionId,
        Throwable $exception
    ): ?Transaction {
        return DB::transaction(function () use (
            $transactionId,
            $exception
        ): ?Transaction {
            $transaction = Transaction::query()
                ->whereKey($transactionId)
                ->lockForUpdate()
                ->first();

            if ($transaction === null) {
                return null;
            }

            $status = $transaction->getAttribute('status');

            if (! $status instanceof TransactionStatus) {
                return $transaction->refresh();
            }

            if (! in_array($status, [
                TransactionStatus::PendingDistribution,
                TransactionStatus::ProcessingDistribution,
            ], true)) {
                return $transaction->refresh();
            }

            $transaction->fill([
                'status' => TransactionStatus::DistributionFailed,
                'failure_reason' => $this->failureReason($exception),
                'distributed_at' => null,
            ]);
            $transaction->save();

            return $transaction->refresh();
        });
    }

    private function failureReason(Throwable $exception): string
    {
        if ($exception instanceof QueryException
            || $exception instanceof PDOException) {
            $message = 'A database error prevented profit distribution.';
        } elseif ($exception instanceof InvalidProfitDistribution
            || $exception instanceof TransactionDistributionNotProcessable) {
            $message = $exception->getMessage();
        } else {
            $message = 'An unexpected error prevented profit distribution.';
        }

        $message = strip_tags($message);
        $message = preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            ' ',
            $message
        ) ?? '';
        $message = preg_replace('/\s+/u', ' ', $message) ?? '';
        $message = preg_replace(
            '/\b(password|passwd|secret|token|authorization|credential|api[_-]?key)\b\s*[:=]\s*(?:"[^"]*"|\'[^\']*\'|[^\s,;]+)/iu',
            '$1=[redacted]',
            $message
        ) ?? '';
        $message = trim($message);

        if ($message === '') {
            $message = 'An unexpected error prevented profit distribution.';
        }

        return Str::limit(
            'Profit distribution failed: '.$message,
            self::MAX_REASON_LENGTH,
            ''
        );
    }
}
