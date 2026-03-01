<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\CustomerOrder;
use App\Entity\People;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BookStoreController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('book_store/index.html.twig', [
            'controller_name' => 'BookStoreController',
        ]);
    }

    #[Route('/people-buy-count', name: 'people_buy_count')]
    public function peopleBuyCount(EntityManagerInterface $em): Response
    {
        $conn = $em->getConnection();
        
        $sql = "
            SELECT 
                p.name,
                p.email,
                COUNT(co.id) as order_count
            FROM people p
            LEFT JOIN customer_order co ON p.id = co.buyer_id
            GROUP BY p.id, p.name, p.email
            ORDER BY order_count DESC
        ";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $people = $result->fetchAllAssociative();
        
        return $this->render('book_store/people_buy_count.html.twig', [
            'people' => $people
        ]);
    }

    #[Route('/orders-with-total', name: 'orders_with_total')]
    public function ordersWithTotal(EntityManagerInterface $em): Response
    {
        $conn = $em->getConnection();
        
        $sql = "
            SELECT 
                co.id as order_id,
                co.created_at,
                p.name as buyer_name,
                GROUP_CONCAT(b.title SEPARATOR ', ') as books_list,
                SUM(b.price) as total_amount
            FROM customer_order co
            JOIN people p ON co.buyer_id = p.id
            JOIN customer_order_book cob ON co.id = cob.customer_order_id
            JOIN book b ON cob.book_id = b.id
            GROUP BY co.id, co.created_at, p.name
            ORDER BY co.created_at DESC
        ";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $orders = $result->fetchAllAssociative();
        
        return $this->render('book_store/orders_with_total.html.twig', [
            'orders' => $orders
        ]);
    }

    #[Route('/top-3-customers', name: 'top_3_customers')]
    public function top3Customers(EntityManagerInterface $em): Response
    {
        $conn = $em->getConnection();
        
        $sql = "
            SELECT 
                p.name,
                p.email,
                COUNT(DISTINCT co.id) as total_orders,
                SUM(b.price) as total_spent
            FROM people p
            JOIN customer_order co ON p.id = co.buyer_id
            JOIN customer_order_book cob ON co.id = cob.customer_order_id
            JOIN book b ON cob.book_id = b.id
            GROUP BY p.id, p.name, p.email
            ORDER BY total_spent DESC
            LIMIT 3
        ";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $topCustomers = $result->fetchAllAssociative();
        
        return $this->render('book_store/top_3_customers.html.twig', [
            'top_customers' => $topCustomers
        ]);
    }

    #[Route('/average-purchase', name: 'average_purchase')]
    public function averagePurchase(EntityManagerInterface $em): Response
    {
        $conn = $em->getConnection();
        
        $sql = "
            SELECT 
                AVG(order_total) as average_amount
            FROM (
                SELECT 
                    co.id,
                    SUM(b.price) as order_total
                FROM customer_order co
                JOIN customer_order_book cob ON co.id = cob.customer_order_id
                JOIN book b ON cob.book_id = b.id
                GROUP BY co.id
            ) as order_totals
        ";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $average = $result->fetchAssociative();
        
        return $this->render('book_store/average_purchase.html.twig', [
            'average' => $average['average_amount'] ?? 0
        ]);
    }

    #[Route('/most-expensive-book', name: 'most_expensive_book')]
    public function mostExpensiveBook(EntityManagerInterface $em): Response
    {
        $conn = $em->getConnection();
        
        $sql = "
            SELECT 
                title,
                price
            FROM book
            ORDER BY price DESC
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $expensiveBook = $result->fetchAssociative();
        
        return $this->render('book_store/most_expensive_book.html.twig', [
            'book' => $expensiveBook
        ]);
    }

    #[Route('/add-test-data', name: 'add_test_data')]
    public function addTestData(EntityManagerInterface $em): Response
    {
        $peopleCount = (int) $em->createQuery('SELECT COUNT(p) FROM App\Entity\People p')->getSingleScalarResult();
        
        if ($peopleCount > 0) {
            return new Response('Тестовые данные уже существуют! <a href="/">Вернуться на главную</a>');
        }
        
        $people1 = new People();
        $people1->setName('Иван Иванов');
        $people1->setEmail('ivan@example.com');
        
        $people2 = new People();
        $people2->setName('Петр Петров');
        $people2->setEmail('petr@example.com');
        
        $people3 = new People();
        $people3->setName('Мария Сидорова');
        $people3->setEmail('maria@example.com');
        
        $em->persist($people1);
        $em->persist($people2);
        $em->persist($people3);
        
        $book1 = new Book();
        $book1->setTitle('Война и мир');
        $book1->setPrice(1500);
        
        $book2 = new Book();
        $book2->setTitle('Преступление и наказание');
        $book2->setPrice(800);
        
        $book3 = new Book();
        $book3->setTitle('Мастер и Маргарита');
        $book3->setPrice(950);
        
        $book4 = new Book();
        $book4->setTitle('Золотой теленок');
        $book4->setPrice(3000);
        
        $book5 = new Book();
        $book5->setTitle('1984');
        $book5->setPrice(650);
        
        $em->persist($book1);
        $em->persist($book2);
        $em->persist($book3);
        $em->persist($book4);
        $em->persist($book5);
        
        $order1 = new CustomerOrder();
        $order1->setBuyer($people1);
        $order1->addBook($book1);
        $order1->addBook($book2);
        
        $order2 = new CustomerOrder();
        $order2->setBuyer($people1);
        $order2->addBook($book3);
        
        $order3 = new CustomerOrder();
        $order3->setBuyer($people2);
        $order3->addBook($book4);
        $order3->addBook($book5);
        
        $order4 = new CustomerOrder();
        $order4->setBuyer($people3);
        $order4->addBook($book1);
        $order4->addBook($book2);
        $order4->addBook($book3);
        
        $order5 = new CustomerOrder();
        $order5->setBuyer($people1);
        $order5->addBook($book5);
        
        $em->persist($order1);
        $em->persist($order2);
        $em->persist($order3);
        $em->persist($order4);
        $em->persist($order5);
        
        $em->flush();
        
        return new Response('✅ Тестовые данные добавлены! <a href="/">Вернуться на главную</a>');
    }
}