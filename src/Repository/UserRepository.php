<?php

namespace App\Repository;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends AbstractBaseRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $this->_em->persist($user);
        $this->_em->flush();
    }

    public function getHighestGithubId(): int
    {
        $user = $this->createQueryBuilder('u')
            ->orderBy('u.githubId', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return !$user ? 0 : $user->getGitHubId();
    }

    public function getOldestNonUpdatedUsers(int $limit, \DateTime $dateTime): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.updated < :dateTime')
            ->setParameter('dateTime', $dateTime)
            ->orderBy('u.updated', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function totalPages(?Country $country, ?City $city): int
    {
        $query = $this->createQueryBuilder('u')
            ->select('count(distinct(u.id)) as total')
            ->join('u.userLanguages', 'ul')
            ->andWhere('ul.stars > 0');

        if ($country) {
            $query->andWhere('u.country = :country')
                ->setParameter('country', $country);
        }

        if ($city) {
            $query->andWhere('u.city = :city')
                ->setParameter('city', $city);
        }

        return $query->getQuery()
            ->getSingleScalarResult();
    }

    public function findSomeUsers(int $start, ?Country $country, ?City $city): array
    {
        $query = $this->createQueryBuilder('u')
            ->select('u', 'SUM(ul.stars) as stars')
            ->join('u.userLanguages', 'ul')
            ->groupBy('u.id')
            ->orderBy('stars', 'DESC');

        if ($country) {
            $query->andWhere('u.country = :country')
                ->setParameter('country', $country);
        }

        if ($city) {
            $query->andWhere('u.city = :city')
                ->setParameter('city', $city);
        }

        return $query->setFirstResult($start)
            ->setMaxResults(25)
            ->getQuery()
            ->getResult();
    }

    public function getTopUsers(int $limit, bool $isCorp): array
    {
        return $this->createQueryBuilder('u')
            ->select('u', 'sum(ul.stars) as stars')
            ->join('u.userLanguages', 'ul')
            ->andWhere('u.organization = :isCorp')
            ->setParameter('isCorp', $isCorp)
            ->orderBy('stars', 'DESC')
            ->groupBy('u.id')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getTodayTop(): array
    {
        $cacheKey = $this->getCacheAdapter()->getItem('top-users-today');

        if ($cacheKey->isHit()) {
            $topToday = $cacheKey->get();
        } else {
            $topToday = $this->createQueryBuilder('u')
                ->select('u', 'sum(ul.stars) as stars')
                ->join('u.userLanguages', 'ul')
                ->andWhere('ul.stars > 1000')
                ->setMaxResults(4)
                ->orderBy('RAND()')
                ->groupBy('u.id')
                ->getQuery()
                ->getResult();

            $cacheKey->set($topToday);
            $cacheKey->expiresAt(new \DateTime('23:59:59'));

            $this->getCacheAdapter()->save($cacheKey);
        }

        return $topToday;
    }
}
