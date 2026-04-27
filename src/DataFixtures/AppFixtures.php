<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Random\RandomException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $manager
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $adminUser = $this->createUsers();
        $this->createProducts();
        if ($adminUser) {
            $this->createOrders($adminUser);
        }
        $manager->flush();
    }

    /**
     * @return void
     * Fonction de création des fixtures d'utilisateur
     */
    private function createUsers(): User
    {
        $faker = Factory::create('fr_FR');
        for ($i = 0; $i < 50; $i++) {
            $user = new User();
            $password = $this->passwordHasher->hashPassword($user, 'password');

            $user
                ->setEmail($faker->email)
                ->setPassword($password)
                ->setFirstname($faker->firstName())
                ->setLastname($faker->lastName())
                ->setBirthday($faker->dateTimeBetween('-40 years', '-18 years'))
                ->setPhoneNumber($faker->phoneNumber())
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
            ->setIsActive(true)
            ->setPhoneNumber($faker->phoneNumber())
            ->setRoles(['ROLE_ADMIN', 'ROLE_USER'])
        ;
        $this->manager->persist($adminUser);

        return $adminUser;
    }

    /**
     * @return void
     * Fonction de création des fixtures de catégories et de produits
     * @throws RandomException
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
                ->setWeight((random_int(1, 200) / 100))
                ->setName('Produit' . $i)
                ->setPrice($faker->randomFloat(2, 15, 80))
                ->setDescription($faker->paragraph())
                ->addCategory($categories[$category]);
            $this->manager->persist($product);
        }
        $this->manager->flush();
    }

    /**
     * @throws RandomException
     */
    private function createOrders(User $adminUser): void
    {
        $faker = Factory::create('fr_FR');
        $products = $this->manager->getRepository(Product::class)->findAll();

        if (empty($products)) {
            return;
        }

        for ($i = 0; $i < 50; $i++) {
            $order = new Order();
            $token = bin2hex(random_bytes(32));
            $sessionKey = bin2hex(random_bytes(32));
            $statuses = [
                OrderStatus::REFUND,
                OrderStatus::DELIVERED,
                OrderStatus::CREATED,
                OrderStatus::CANCELED,
                OrderStatus::PENDING_SHIPPING,
                OrderStatus::PENDING_PAYMENT
            ];

            $numberOfProducts = random_int(1, 5);

            $order
                ->setUser($adminUser)
                ->setEmail($adminUser->getEmail())
                ->setDeliveryMode($numberOfProducts%2 === 1 ? DeliveryMode::HOME : DeliveryMode::RELAY)
                ->setSessionKey($sessionKey)
                ->setToken($token)
                ->setStatus($statuses[array_rand($statuses)])
                ->setFirstname($adminUser->getFirstname())
                ->setLastname($adminUser->getLastname())
                ->setDelivery(true)
                ->setCreationDate($faker->dateTimeBetween('now', '+ 30 days'));

            $total = 0;

            for ($e = 0; $e < $numberOfProducts; $e++) {
                $product = $products[array_rand($products)];
                $quantity = random_int(1, 3);
                $totalProduct = $product->getPrice() * $quantity;
                $orderItem = new OrderItem();
                $orderItem
                    ->setOrder($order)
                    ->setProduct($product)
                    ->setQuantity($quantity)
                    ->setUnitPrice($product->getPrice())
                    ->setTotal($totalProduct);

                $total += $totalProduct;
                $this->manager->persist($orderItem);
            }

            $deliveryPrice = $total > 50 ? null : 15;
            $total += $deliveryPrice;

            $order
                ->setTotal($total)
                ->setDeliveryPrice($deliveryPrice);

            $this->manager->persist($order);
        }
    }
}
