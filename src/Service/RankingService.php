<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class RankingService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function getRankingLanguage(User $user): array
    {
        $statement = $this->em->getConnection()->executeQuery(
            'SELECT * 
             FROM ranking_language r
             LEFT JOIN language l on r.language_id = l.id 
             WHERE user_id=' . $user->getId() . '
             ORDER BY r.stars DESC'
        );

        return $statement->fetchAllAssociative();
    }

    public function getRankingGlobal(User $user): array
    {
        $statement = $this->em->getConnection()->executeQuery(
            'SELECT * 
             FROM ranking_global
             WHERE user_id=' . $user->getId()
        );

        return (array) $statement->fetchAssociative();
    }

    public function getTopLanguage(User $user): array
    {
        $statement = $this->em->getConnection()->executeQuery(
            'SELECT rl.stars, rl.language_id, l.color, l.name, l.slug 
             FROM ranking_language rl
             LEFT JOIN language l on rl.language_id = l.id
             WHERE rl.user_id=' . $user->getId() . '
             ORDER BY stars DESC
             LIMIT 1'
        );

        return (array) $statement->fetchAssociative();
    }
}
