<?php

namespace App\DataFixtures;

use App\Entity\Chanson;
use App\Entity\Chanteur;
use App\Entity\Disque;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $faker;
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->faker = Factory::create("fr_FR");
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Création des utilisateurs
        $user = new User();
        $user->setEmail("user@disqueapi.com");
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "password"));
        $manager->persist($user);

        //créationd'un user admin
        $userAdmin = new User();
        $userAdmin->setEmail("admin@disqueapi.com");
        $userAdmin->setRoles(['ROLE_ADMIN']);
        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "password"));
        $manager->persist($userAdmin);

        $listChanteur = [];
        $listDisque = [];

        for ($i = 0; $i < 10; $i++) {
            $chanteur = new Chanteur();
            $chanteur->setName($this->faker->name());
            $chanteur->setLastName($this->faker->name());
            $manager->persist($chanteur);

            $listChanteur[] = $chanteur;
        }

        for ($i = 0; $i < 20; $i++) {
            $disque = new Disque();
            $disque->setNameDisque($this->faker->sentence());
            $disque->setChanteur($listChanteur[array_rand($listChanteur)]);

            $manager->persist($disque);
            $listDisque[] = $disque;
        }

        for ($i = 0; $i < 20; $i++) {
            $chanson = new Chanson();
            $chanson->setNameChanson($this->faker->sentence());
            $chanson->setDuree($this->faker->dateTime());
            $chanson->setDisque($listDisque[array_rand($listDisque)]);

            $manager->persist($chanson);
        }

        $manager->flush();
    }
    
}