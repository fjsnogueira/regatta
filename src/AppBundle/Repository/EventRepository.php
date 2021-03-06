<?php

namespace AppBundle\Repository;

/**
 * EventRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class EventRepository extends \Doctrine\ORM\EntityRepository
{
    public function findUpcomingEvents() {
        $query = $this->createQueryBuilder('e')
            ->where('e.end > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.start', 'ASC')
            ->getQuery();

        return $query->getResult();
    }

    public function findPastEvents() {
        $query = $this->createQueryBuilder('e')
            ->where('e.end < :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.start', 'DESC')
            ->getQuery();

        return $query->getResult();
    }
}
