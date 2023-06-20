<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Service\PaymentService;
use App\Tests\AbstractTest;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TransactionControllerTest extends AbstractTest
{
    private string $authURL = '/api/v1/auth';
    private string $fixture_email = 'admin@mail.ru';
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
    public function testGetTransactions(): void
    {
        $client = $this->getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixture_email,
            "password" => $this->fixture_password
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request('GET', '/api/v1/transactions', [
            'type' => null,
            'code' => null,
            'skip_expired' => true
        ], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token']]);
        $client->getResponse()->getContent();
        $transactionsInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $transactionsInfo);

        $client->request('GET', '/api/v1/transactions', [
            'type' => 'deposit',
            'code' => null,
            'skip_expired' => true
        ], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token']]);
        $client->getResponse()->getContent();

        $transactionsInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $transactionsInfo);

        $client->request('GET', '/api/v1/transactions', [
            'type' => 'payment',
            'code' => null,
            'skip_expired' => true
        ], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token']]);
        $client->getResponse()->getContent();

        $transactionsInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(1, $transactionsInfo);

        $client->request('GET', '/api/v1/transactions', [
            'type' => 'deposit',
            'code' => null,
            'skip_expired' => false
        ], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token']]);
        $client->getResponse()->getContent();

        $transactionsInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $transactionsInfo);

        $client->request('GET', '/api/v1/transactions', [
            'type' => 'payment',
            'code' => null,
            'skip_expired' => false
        ], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token']]);
        $client->getResponse()->getContent();

        $transactionsInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $transactionsInfo);
    }
}
