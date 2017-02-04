<?php

class Bank
{

    private $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('mysql:host=localhost;dbname=banking;charset=utf8', 'root', 'root');
    }

    public function getExpenses($date)
    {
        $statement = $this->pdo->query('SELECT * FROM account WHERE type="out" AND date LIKE "' . $date . '%"');
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        usort($rows, function ($a, $b) {
            return $a['date'] >= $b['date'];
        });
        return $rows;
    }

    public function getCategories()
    {
        $statement = $this->pdo->query('SELECT category FROM account GROUP BY category');
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $categories = [];
        foreach($rows as $row)
        {
            $categories[] = $row['category'];
        }
        return $categories;
    }

}
