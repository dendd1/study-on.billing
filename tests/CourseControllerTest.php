<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\Course;
use App\Enum\CourseEnum;
use App\Entity\Transaction;
use App\Tests\AbstractTest;
use App\Service\PaymentService;
use App\DataFixtures\AppFixtures;
use Symfony\Component\HttpFoundation\Response;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;

class CourseControllerTest extends AbstractTest
{
    private string $authURL = '/api/v1/auth';
    private string $fixture_email = 'admin@mail.ru';
    private string $fixture_email_with_no_money = 'user@mail.ru';
    private string $fixture_password = '123456';

    protected function getFixtures(): array
    {
        return [
            new AppFixtures(
                $this->getContainer()->get(UserPasswordHasherInterface::class),
                $this->getContainer()->get(RefreshTokenGeneratorInterface::class),
                $this->getContainer()->get(RefreshTokenManagerInterface::class),
                $this->getContainer()->get(PaymentService::class),
            )
        ];
    }

    public function testGetCourses(): void
    {
        $client = static::getClient();

        $client->request('GET', '/api/v1/courses');
        $courses = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertResponseOk();

        $this->assertCount(3, $courses);
    }

    public function testGetCourse(): void
    {
        $client = static::getClient();

        $entityManager = $this->getEntityManager();
        $courseRepository = $entityManager->getRepository(Course::class);
        $free_course = $courseRepository->findOneBy(['type' => CourseEnum::FREE]);
        $client->request('GET', '/api/v1/courses/' . $free_course->getCode());
        $this->assertResponseOk();

        $course = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals($free_course->getCode(), $course['code']);
        $this->assertEquals(CourseEnum::FREE, CourseEnum::VALUES[$course['type']]);
        $this->assertArrayNotHasKey('price', $course);
    }

    public function testPayCourse(): void
    {
        $client = static::getClient();

        $entityManager = $this->getEntityManager();
        $userRepository = $entityManager->getRepository(User::class);
        $transactionRepository = $entityManager->getRepository(Transaction::class);
        $courseRepository = $entityManager->getRepository(Course::class);
        $pay_course = $courseRepository->findOneBy(['type' => CourseEnum::RENT]);

        $transactionsCount = $transactionRepository->count(['type' => 0]);

        // Неавторизован
        $client->request('POST', '/api/v1/courses/' . $pay_course->getCode() . '/pay');
        $this->assertResponseCode(401);

        // Пользователь, у которого есть средства
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixture_email,
            "password" => $this->fixture_password
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        // Курс не найден
        $client->request('POST', '/api/v1/courses/blahblah/pay');
        $this->assertResponseCode(Response::HTTP_NOT_FOUND);

        // Успешная оплата курса
        $client->request(
            'POST',
            '/api/v1/courses/' . $pay_course->getCode() . '/pay',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $userInfo['token']]
        );
        $this->assertResponseCode(Response::HTTP_OK);
        $payInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(1, $payInfo['success']);
        $this->assertEquals('rent', $payInfo['type']);

        $client->setServerParameter('HTTP_AUTHORIZATION', '');//logout

        // Пользователь без средств
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixture_email_with_no_money,
            "password" => $this->fixture_password
        ]);

        // Недостаточно средств
        $client->request('POST', '/api/v1/courses/cleanCourse-1/pay');
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);

        // Оплата не нужна
        $client->request('POST', '/api/v1/courses/car-1/pay');
        $this->assertResponseCode(Response::HTTP_OK);

        $payInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(1, $payInfo['success']);
        $this->assertEquals('free', $payInfo['type']);

        // Проверка снятия средств за покупку курса
        $user = $userRepository->findOneBy(['email' => $this->fixture_email]);
        $this->assertEquals(
            1000.0,
            $user->getBalance()
        );

        // Добавилось 1 транзакции
        $this->assertEquals($transactionsCount + 1, $transactionRepository->count(['type' => 0]));
    }
}