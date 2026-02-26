<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $manager
    )
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->createUsers();
        $this->createProducts();
        $manager->flush();
    }

    /**
     * @return void
     * Fonction de création des fixtures d'utilisateur
     */
    private function createUsers(): void
    {
        $faker = Factory::create('fr_FR');
        for ($i = 0; $i < 10; $i++) {
            $user = new User();
            $password = $this->passwordHasher->hashPassword($user, 'password');

            $user
                ->setEmail($faker->email)
                ->setPassword($password)
                ->setFirstname($faker->firstName())
                ->setLastname($faker->lastName())
                ->setBirthday($faker->dateTimeBetween('-40 years', '-18 years'))
                ->setIsActive(true)
            ;

            $this->manager->persist($user);

        }

        $adminUser = new User();
        $adminUser
            ->setEmail('admin@admin.fr')
            ->setPassword($this->passwordHasher->hashPassword($adminUser, 'password'))
            ->setFirstname('Bastien')
            ->setLastname('Admin')
            ->setBirthday($faker->dateTimeBetween('-40 years', '-18 years'))
            ->setIsActive(true);
        $this->manager->persist($adminUser);
    }

    /**
     * @return void
     * Fonction de création des fixtures de catégories et de produits
     */
    private function createProducts(): void
    {

        $categoriesName = [
            'Huile pour corps',
            'Soin visage',
            'Shampoing',
            'Creme de jour',
            'Mascara'
        ];

        foreach ($categoriesName as $iValue) {
            $category = new Category();
            $category
                ->setName($iValue);

            $this->manager->persist($category);
        }

        $this->manager->flush();

        $categories = $this->manager->getRepository(Category::class)->findAll();

        for ($i = 0; $i < 15; $i++) {
            $product = new Product();
            $faker = Factory::create('fr_FR');
            $category = array_rand($categories);

            $product
                ->setName('Produit' . $i)
                ->setPrice($faker->randomFloat(2, 15, 150))
                ->setDescription($faker->paragraph())
                ->addCategory($categories[$category]);
            $this->manager->persist($product);
        }
    }
}
