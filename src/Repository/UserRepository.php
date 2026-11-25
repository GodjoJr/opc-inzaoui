<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Récupère tous les invités non-admin avec leurs médias en une seule requête SQL.
     *
     * @return User[]
     */
    public function findAllGuestsWithMedias(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.medias', 'm')
            ->addSelect('m')
            ->where('u.admin = :isAdmin')
            ->setParameter('isAdmin', false)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les invités actifs avec leurs médias pour le front.
     *
     * @return User[]
     */
    public function findActiveGuestsWithMedias(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.medias', 'm')
            ->addSelect('m')
            ->where('u.admin = :isAdmin')
            ->andWhere('u.isActive = :isActive')
            ->setParameter('isAdmin', false)
            ->setParameter('isActive', true)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveGuestWithMedias(int $id): ?User
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.medias', 'm')
            ->addSelect('m')
            ->where('u.id = :id')
            ->andWhere('u.admin = :isAdmin')
            ->andWhere('u.isActive = :isActive')
            ->setParameter('id', $id)
            ->setParameter('isAdmin', false)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

/**
     * Front-office : Invités actifs paginés avec leurs médias.
     */
    public function findActiveGuestsPaginated(int $page = 1, int $limit = 10): array
    {
        $page = max(1, $page);

        $query = $this->createQueryBuilder('u')
            ->leftJoin('u.medias', 'm')
            ->addSelect('m')
            ->where('u.admin = :isAdmin')
            ->andWhere('u.isActive = :isActive')
            ->setParameter('isAdmin', false)
            ->setParameter('isActive', true)
            ->orderBy('u.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, true);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $limit)),
        ];
    }

    /**
     * Back-office : Tous les invités (actifs et bloqués) paginés avec leurs médias.
     */
    public function findAllGuestsPaginated(int $page = 1, int $limit = 15): array
    {
        $page = max(1, $page);

        $query = $this->createQueryBuilder('u')
            ->leftJoin('u.medias', 'm')
            ->addSelect('m')
            ->where('u.admin = :isAdmin')
            ->setParameter('isAdmin', false)
            ->orderBy('u.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, true);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $limit)),
        ];
    }
}
