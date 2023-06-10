<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class UserDTO
{
    /**
     * @Assert\NotBlank(message="Email field is required")
     * @Assert\Email( message="Wrong Email" )
     */
    private ?string $username = null;
    /**
     * @Assert\NotBlank(message="Password field is required")
     * @Assert\Length(min="6", minMessage="The password must contain at least 6 characters",
     *      max=100, maxMessage="The password must contain a maximum of 6 characters")
     */
    private ?string $password = null;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }


    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }
}