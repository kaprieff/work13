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
        $qb = $em->createQueryBuilder();
        
        $qb->select('p.name', 'p.email', 'COUNT(co.id) as order_count')
           ->from('App\Entity\People', 'p')
           ->leftJoin('p.orders', 'co')
           ->groupBy('p.id')
           ->orderBy('order_count', 'DESC');
        
        $people = $qb->getQuery()->getResult();
        
        return $this->render('book_store/people_buy_count.html.twig', [
            'people' => $people
        ]);
    }

    #[Route('/orders-with-total', name: 'orders_with_total')]
    public function ordersWithTotal(EntityManagerInterface $em): Response
    {
        $orders = [];
        
        $qb = $em->createQueryBuilder();
        $qb->select('co')
           ->from('App\Entity\CustomerOrder', 'co')
           ->join('co.buyer', 'p')
           ->orderBy('co.createdAt', 'DESC');
        
        $customerOrders = $qb->getQuery()->getResult();
        
        foreach ($customerOrders as $co) {
            $booksList = [];
            $totalAmount = 0;
            
            foreach ($co->getBooks() as $book) {
                $booksList[] = $book->getTitle();
                $totalAmount += $book->getPrice();
            }
            
            $orders[] = [
                'order_id' => $co->getId(),
                'created_at' => $co->getCreatedAt(),
                'buyer_name' => $co->getBuyer()->getName(),
                'books_list' => implode(', ', $booksList),
                'total_amount' => $totalAmount
            ];
        }
        
        return $this->render('book_store/orders_with_total.html.twig', [
            'orders' => $orders
        ]);
    }

    #[Route('/top-3-customers', name: 'top_3_customers')]
    public function top3Customers(EntityManagerInterface $em): Response
    {
        $qb = $em->createQueryBuilder();
        
        $qb->select('p.name', 'p.email', 'COUNT(DISTINCT co.id) as total_orders')
           ->addSelect('SUM(b.price) as total_spent')
           ->from('App\Entity\People', 'p')
           ->join('p.orders', 'co')
           ->join('co.books', 'b')
           ->groupBy('p.id')
           ->orderBy('total_spent', 'DESC')
           ->setMaxResults(3);
        
        $topCustomers = $qb->getQuery()->getResult();
        
        return $this->render('book_store/top_3_customers.html.twig', [
            'top_customers' => $topCustomers
        ]);
    }

    #[Route('/average-purchase', name: 'average_purchase')]
    public function averagePurchase(EntityManagerInterface $em): Response
    {
        $qb = $em->createQueryBuilder();
        
        $qb->select('co.id', 'SUM(b.price) as order_total')
           ->from('App\Entity\CustomerOrder', 'co')
           ->join('co.books', 'b')
           ->groupBy('co.id');
        
        $orderTotals = $qb->getQuery()->getResult();
        
        $average = 0;
        if (!empty($orderTotals)) {
            $totalSum = 0;
            foreach ($orderTotals as $order) {
                $totalSum += $order['order_total'];
            }
            $average = $totalSum / count($orderTotals);
        }
        
        return $this->render('book_store/average_purchase.html.twig', [
            'average' => round($average, 2)
        ]);
    }

    #[Route('/most-expensive-book', name: 'most_expensive_book')]
    public function mostExpensiveBook(EntityManagerInterface $em): Response
    {
        $qb = $em->createQueryBuilder();
        
        $qb->select('b')
           ->from('App\Entity\Book', 'b')
           ->orderBy('b.price', 'DESC')
           ->setMaxResults(1);
        
        $expensiveBook = $qb->getQuery()->getOneOrNullResult();
        
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