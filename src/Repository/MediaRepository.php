<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 *
 * @method Media|null find($id, $lockMode = null, $lockVersion = null)
 * @method Media|null findOneBy(array $criteria, array $orderBy = null)
 * @method Media[]    findAll()
 * @method Media[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

/**
     * Récupère les médias d'un invité de façon paginée.
     */
    public function findByUserPaginated(User $user, int $page = 1, int $limit = 12): array
    {
        $page = max(1, $page);

        $items = $this->createQueryBuilder('m')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $total = $this->count(['user' => $user]);

        return [
            'items' => $items,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $limit)),
        ];
    }

    /**
     * Récupère les médias du portfolio de façon paginée (en excluant les invités désactivés).
     */
    public function findPortfolioPaginated(?Album $album = null, int $page = 1, int $limit = 12): array
    {
        $page = max(1, $page);

        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->andWhere('u.id IS NULL OR u.isActive = :isActive')
            ->setParameter('isActive', true)
            ->orderBy('m.id', 'DESC');

        if ($album !== null) {
            $qb->andWhere('m.album = :album')
               ->setParameter('album', $album);
        } else {
            $qb->andWhere('m.album IS NOT NULL');
        }

        // Compter le total réel respectant les mêmes critères
        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(m.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $limit)),
        ];
    }
}
