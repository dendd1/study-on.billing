<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
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

        $new_user = new User();
        $new_user->setEmail("admin@mail.ru");
        $new_user->setPassword($this->passwordHasher->hashPassword(
            $new_user,
            '123456'
        ));
        $new_user->setRoles(["ROLE_SUPER_ADMIN"]);
        $new_user->setBalance(0.0);
        $manager->persist($new_user);

        $manager->flush();
    }
}