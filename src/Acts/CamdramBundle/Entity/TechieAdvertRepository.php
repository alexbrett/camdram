<?php

namespace Acts\CamdramBundle\Entity;

use Doctring\ORM\Mapping as ORM;
use Doctrine\ORM\EntityRepository;

use Doctrine\ORM\Query\Expr;

/**
 * TechieAdvertRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TechieAdvertRepository extends EntityRepository
{
    public function findNotExpiredOrderedByDateName(\DateTime $date)
    {
        $query_res = $this->getEntityManager()->getRepository('ActsCamdramBundle:TechieAdvert');
        $qb = $query_res->createQueryBuilder('a');
        $query = $qb->leftJoin('a.show', 's')
            ->where($qb->expr()->orX('a.expiry > :expiry', $qb->expr()->andX('a.expiry = :expiry', 'a.deadlineTime >= :time')))
            ->andWhere('s.authorised_by is not null')
            ->andWhere('s.entered = 1')
            ->orderBy('a.expiry, s.name, s.society')
            ->setParameter('expiry', $date, \Doctrine\DBAL\Types\Type::DATE)
            ->setParameter('time', $date, \Doctrine\DBAL\Types\Type::TIME)
            ->getQuery();
        return $query->getResult();
    }

    private function getLatestQuery($limit, \DateTime $now)
    {
        $qb = $this->createQueryBuilder('a');
        return $qb->leftJoin('a.show', 's')
            ->where($qb->expr()->orX('a.expiry > :expiry', $qb->expr()->andX('a.expiry = :expiry', 'a.deadlineTime >= :time')))
            ->andWhere('s.authorised_by is not null')
            ->andWhere('s.entered = 1')
            ->orderBy('a.last_updated')
            ->setParameter('expiry', $now, \Doctrine\DBAL\Types\Type::DATE)
            ->setParameter('time', $now, \Doctrine\DBAL\Types\Type::TIME)
            ->setMaxResults($limit);
    }

    public function findLatest($limit, \DateTime $now)
    {
        return $this->getLatestQuery($limit, $now)->getQuery()->getResult();
    }

    public function findLatestBySociety(Society $society, $limit, \DateTime $now)
    {
        return $this->getLatestQuery($limit, $now)
            ->leftJoin('s.society', 'y')->andWhere('y = :society')->setParameter('society', $society)
            ->getQuery()->getResult();
    }

    public function findLatestByVenue(Venue $venue, $limit, \DateTime $now)
    {
        return $this->getLatestQuery($limit, $now)
            ->leftJoin('s.venue', 'v')->andWhere('v = :venue')->setParameter('venue', $venue)
            ->getQuery()->getResult();
    }

    public function findOneByShowSlug($slug, \DateTime $now)
    {
        $qb = $this->createQueryBuilder('a');
        return $qb->leftJoin('a.show', 's')
            ->where($qb->expr()->orX('a.expiry > :expiry', $qb->expr()->andX('a.expiry = :expiry', 'a.deadlineTime >= :time')))
            ->andWhere('s.slug = :slug')
            ->andWhere('s.authorised_by is not null')
            ->andWhere('s.entered = 1')
            ->setParameter('slug', $slug)
            ->setParameter('expiry', $now, \Doctrine\DBAL\Types\Type::DATE)
            ->setParameter('time', $now, \Doctrine\DBAL\Types\Type::TIME)
            ->getQuery()->getOneOrNullResult();
            ;
    }

}
