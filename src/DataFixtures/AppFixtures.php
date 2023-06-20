<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\User;
use App\Service\PaymentService;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;
    private RefreshTokenGeneratorInterface $refreshTokenGenerator;
    private RefreshTokenManagerInterface $refreshTokenManager;
    private PaymentService $paymentService;
    public function __construct(
        UserPasswordHasherInterface $passwordHasher,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager,
        PaymentService $paymentService
    ) {
        $this->passwordHasher = $passwordHasher;
        $this->refreshTokenGenerator = $refreshTokenGenerator;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->paymentService = $paymentService;
    }
    public function load(ObjectManager $manager): void
    {
        $new_user = new User();
        $new_user->setEmail("user@mail.ru");
        $new_user->setPassword($this->passwordHasher->hashPassword(
            $new_user,
            '123456'
        ));
        $new_user->setBalance(0.0);

        $manager->persist($new_user);

        $refreshToken = $this->refreshTokenGenerator
            ->createForUserWithTtl($new_user, (new \DateTime())->modify('+1 month')->getTimestamp());
        $this->refreshTokenManager->save($refreshToken);

        $new_user = new User();
        $new_user->setEmail("admin@mail.ru");
        $new_user->setPassword($this->passwordHasher->hashPassword(
            $new_user,
            '123456'
        ));
        $new_user->setRoles(["ROLE_SUPER_ADMIN"]);
        $new_user->setBalance(1000.0);
        $manager->persist($new_user);

        $refreshToken = $this->refreshTokenGenerator
            ->createForUserWithTtl($new_user, (new \DateTime())->modify('+1 month')->getTimestamp());
        $this->refreshTokenManager->save($refreshToken);

        $coursesByCode = $this->createCourses($manager);


        $this->paymentService->deposit($new_user, 70.0);

        $transaction = $this->paymentService->payment($new_user, $coursesByCode['cooking-1']);
        $transaction->setCreated((new DateTimeImmutable())->sub(new DateInterval('P1Y3M')));
        $transaction->setExpires((new DateTimeImmutable())->sub(new DateInterval('P1Y2M')));
        $manager->persist($transaction);

        $transaction = $this->paymentService->payment($new_user, $coursesByCode['cleanCourse-1']);
        $manager->persist($transaction);

//        $transaction = $this->paymentService->payment($new_user, $coursesByCode['cooking-1']);
//        $transaction->setExpires((new DateTimeImmutable())->sub(new DateInterval('P1Y3M6D')));

        $manager->persist($transaction);


        $manager->flush();
    }
    public function createCourses(ObjectManager $manager): array
    {
        $coursesByCode = [];

        foreach (self::COURSES_DATA as $courseData) {
            $course = (new Course())
                ->setCode($courseData['code'])
                ->setType($courseData['type']);
            if (isset($courseData['price'])) {
                $course->setPrice($courseData['price']);
            }

            $coursesByCode[$courseData['code']] = $course;
            $manager->persist($course);
        }
        return $coursesByCode;
    }

    private const COURSES_DATA = [
        [
            'code' => 'car-1',
            'type' => 0 // free
        ],
        [
            'code' => 'cooking-1',
            'type' => 1,
            // rent
            'price' => 20
        ],
        [
            'code' => 'cleanCourse-1',
            'type' => 2,
            // buy
            'price' => 30
        ],
        [
            'code' => 'test_buy',
            'type' => 2,
            // buy
            'price' => 40
        ],
        [
            'code' => 'test_rent',
            'type' => 1,
            // rent
            'price' => 10
        ],
    ];
}