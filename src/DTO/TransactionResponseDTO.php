<?php

namespace App\DTO;

use App\Entity\Transaction;
use DateTimeImmutable;
use JMS\Serializer\Annotation as Serializer;

class TransactionResponseDTO
{
    public ?int $id;

    public ?string $code;

    public ?string $type;

    public ?float $amount;

    public ?\DateTimeImmutable $created;

    public ?\DateTimeImmutable $expires;

    public function __construct(Transaction $transaction)
    {
        $this->id = $transaction->getId();
        $course = $transaction->getCourse();
        if ($course !== null) {
            $this->code = $course->getCode();
        }
        $this->type = $transaction->getType();
        $this->amount = $transaction->getAmount();
        $this->created = $transaction->getCreated();
        $this->expires = $transaction->getExpires();
    }
}