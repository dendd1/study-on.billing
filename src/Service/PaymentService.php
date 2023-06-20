<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Course;
use App\Enum\CourseEnum;
use App\Entity\Transaction;
use App\Enum\TransactionEnum;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class PaymentService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function deposit(User $user, float $amount)
    {
        $this->entityManager->wrapInTransaction(function () use ($user, $amount) {
            $transaction = new Transaction();
            $transaction
                ->setCustomer($user)
                ->setType(TransactionEnum::DEPOSIT)
                ->setAmount($amount)
                ->setCreated(new DateTimeImmutable());
            $this->entityManager->persist($transaction);

            $user->setBalance($user->getBalance() + $amount);
            $this->entityManager->persist($user);
        });
    }

    public function payment(User $user, Course $course) : Transaction
    {
        if ($user->getBalance() < $course->getPrice()) {
            throw new \RuntimeException('На счету недостаточно средств', Response::HTTP_NOT_ACCEPTABLE);
        }

        $transactionRepository = $this->entityManager->getRepository(Transaction::class);
        if ($transactionRepository->ifCoursePaid($course, $user) > 0) {
            throw new \LogicException('Курс уже оплачен', Response::HTTP_NOT_ACCEPTABLE);
        }

        $transaction = new Transaction();
        $this->entityManager->wrapInTransaction(function () use ($user, $course, $transaction) {
            $transaction->setCustomer($user)
                ->setCourse($course)
                ->setType(TransactionEnum::PAYMENT)
                ->setAmount($course->getPrice())
                ->setCreated(new DateTimeImmutable());
            if ($course->getType() === CourseEnum::RENT) {
                $transaction->setExpires((new DateTimeImmutable())->add(new DateInterval('P1W')));
            }
            $this->entityManager->persist($transaction);

            $user->setBalance($user->getBalance() - $course->getPrice());
            $this->entityManager->persist($user);
        });

        return $transaction;
    }

}