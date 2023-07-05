<?php

namespace App\Tests;

use App\Entity\User;
use App\Service\PaymentService;
use App\Tests\AbstractTest;
use App\DataFixtures\AppFixtures;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserControllerTest extends AbstractTest
{
    private string $authURL = '/api/v1/auth';
    private string $registerURL = '/api/v1/register';
    private string $getCurrentUserURL = '/api/v1/users/current';
    private string $userEmail = 'user@mail.ru';
    private string $userPassword = '123456';
    private float $userBalance = 0.0;
    protected function getFixtures(): array
    {
        return [new AppFixtures(
            $this->getContainer()->get(UserPasswordHasherInterface::class),
            $this->getContainer()->get(RefreshTokenGeneratorInterface::class),
            $this->getContainer()->get(RefreshTokenManagerInterface::class),
            $this->getContainer()->get(PaymentService::class),
        )];
    }
    public function testAuthSuccess()
    {
        $client = $this->getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->userEmail,
            "password" => $this->userPassword
        ]);
        $this->assertResponseCode(Response::HTTP_OK);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($responseData['token']);
    }
    public function testAuthEmptyUsername()
    {
        $client = $this->getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => "",
            "password" => $this->userPassword
        ]);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
    }
    public function testAuthEmptyPassword()
    {
        $client = $this->getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->userEmail,
            "password" => ""
        ]);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
    }
    public function testAuthInvalidUsername()
    {
        $client = $this->getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => "notExistUser@mail.ru",
            "password" => $this->userPassword
        ]);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("Invalid credentials.", $responseData['message']);
    }
    public function testAuthInvalidPassword()
    {
        $client = $this->getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->userEmail,
            "password" => "654321"
        ]);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("Invalid credentials.", $responseData['message']);
    }
    public function testRegisterSuccess()
    {
        $client = $this->getClient();
        $email = 'newUser@mail.ru';
        $password = '123456';

        $client->jsonRequest('POST', $this->registerURL, [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_CREATED);

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($responseData['token']);
        $this->assertSame(1, $this->getEntityManager()->getRepository(User::class)->count(['email' => $email]));

        $client = $this->getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_OK);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($responseData['token']);
    }
    public function testRegisterEmptyUsername()
    {
        $client = $this->getClient();
        $password = '123456';
        $client->jsonRequest('POST', $this->registerURL, [
            "username" => "",
            "password" => $password,
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("Email field is required", $responseData['message']);
    }
    public function testRegisterEmptyPassword()
    {
        $client = $this->getClient();
        $email = 'newUser@mail.ru';
        $client->jsonRequest('POST', $this->registerURL, [
            "username" => $email,
            "password" => ""
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(
            "Password field is required, The password must contain at least 6 characters",
            $responseData['message']
        );
    }
    public function testRegisterInvalidPassword()
    {
        $client = $this->getClient();
        $email = 'newUser@mail.ru';
        $client->jsonRequest('POST', $this->registerURL, [
            "username" => $email,
            "password" => "123"
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("The password must contain at least 6 characters", $responseData['message']);
    }
    public function testRegisterInvalidUsername()
    {
        $client = $this->getClient();
        $password = '123456';
        $client->jsonRequest('POST', $this->registerURL, [
            "username" => "mailUser",
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("Wrong Email", $responseData['message']);
    }
    public function testRegisterBusyUsername()
    {
        $client = $this->getClient();
        $client->jsonRequest('POST', $this->registerURL, [
            "username" => $this->userEmail,
            "password" => $this->userPassword
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("This email already exists", $responseData['message']);
    }
    public function testGetCurrentUserSuccess()
    {
        $client = $this->getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->userEmail,
            "password" => $this->userPassword
        ]);
        $this->assertResponseCode(Response::HTTP_OK);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $token = $responseData['token'];
        $this->assertNotNull($responseData['token']);
        $client->request('GET', $this->getCurrentUserURL, [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $this->assertResponseCode(Response::HTTP_OK);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($this->userEmail, $responseData['username']);
        $this->assertTrue(in_array('ROLE_USER', $responseData['roles'], true));
        $this->assertEquals($this->userBalance, $responseData['balance']);
    }
    public function testGetCurrentUserFailed()
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->userEmail,
            "password" => $this->userPassword
        ]);
        $this->assertResponseCode(Response::HTTP_OK);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($responseData['token']);

        $client->request('GET', $this->getCurrentUserURL, [], [], []);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("JWT Token not found", $responseData['message']);
    }
}