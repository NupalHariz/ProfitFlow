<?php

namespace App\Exceptions\Transactions;

use DomainException;

final class TransactionNotCancellable extends DomainException {}
