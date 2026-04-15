<?php

namespace App\Repository\Sponsor;

use App\Entity\Sponsor\Sponsor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sponsor>
 */
class SponsorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sponsor::class);
    }

    /**
     * @return Sponsor[]
     */
    public function searchForAdmin(?string $search = null, ?string $company = null, ?int $eventId = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')
            ->addSelect('u')
            ->orderBy('s.id', 'DESC');

        if ($search !== null && trim($search) !== '') {
            $q = mb_strtolower(trim($search));
            $qb->andWhere('LOWER(s.companyName) LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($company !== null && trim($company) !== '') {
            $qb->andWhere('LOWER(s.companyName) = :company')
                ->setParameter('company', mb_strtolower(trim($company)));
        }

        if ($eventId !== null && $eventId > 0) {
            $qb->andWhere('s.eventId = :eventId')
                ->setParameter('eventId', $eventId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return string[]
     */
    public function getDistinctCompanies(): array
    {
        return $this->createQueryBuilder('s')
            ->select('DISTINCT s.companyName')
            ->where('s.companyName IS NOT NULL')
            ->andWhere("s.companyName != ''")
            ->orderBy('s.companyName', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function getTotalSponsors(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalContribution(): float
    {
        $value = $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.contributionName), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $value;
    }

    public function getAverageContribution(): float
    {
        $value = $this->createQueryBuilder('s')
            ->select('COALESCE(AVG(s.contributionName), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $value;
    }

    /**
     * @return array<string,float>
     */
    public function getTopCompaniesByContribution(int $limit = 5): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT company_name AS companyName, COALESCE(SUM(contribution_name), 0) AS total
             FROM sponsor
             WHERE company_name IS NOT NULL AND company_name <> ""
             GROUP BY company_name
             ORDER BY total DESC, company_name ASC
             LIMIT ' . max(1, (int) $limit)
        );

        $map = [];
        foreach ($rows as $row) {
            $name = (string) ($row['companyName'] ?? '-');
            $map[$name] = (float) ($row['total'] ?? 0);
        }

        return $map;
    }

    /**
     * @return array<string,float>
     */
    public function getContributionsByEvent(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT e.title AS eventTitle, COALESCE(SUM(s.contribution_name), 0) AS total
             FROM sponsor s
             JOIN event e ON e.id = s.event_id
             GROUP BY e.id, e.title
             ORDER BY total DESC'
        );

        $map = [];
        foreach ($rows as $row) {
            $title = (string) ($row['eventTitle'] ?? '-');
            $map[$title] = (float) ($row['total'] ?? 0);
        }

        return $map;
    }

    /**
     * @return Sponsor[]
     */
    public function findByContactEmailWithEvent(string $email): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')
            ->addSelect('u')
            ->andWhere('LOWER(s.contactEmail) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->orderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{count:int,total:float,events:int}
     */
    public function getMyStats(string $email): array
    {
        $email = mb_strtolower(trim($email));

        $count = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('LOWER(s.contactEmail) = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult();

        $total = (float) $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.contributionName), 0)')
            ->andWhere('LOWER(s.contactEmail) = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult();

        $events = (int) $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.eventId)')
            ->andWhere('LOWER(s.contactEmail) = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'count' => $count,
            'total' => $total,
            'events' => $events,
        ];
    }

    /**
     * @return array<string,float>
     */
    public function getMyContributionsByEvent(string $email): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT e.title AS eventTitle, COALESCE(SUM(s.contribution_name), 0) AS total
             FROM sponsor s
             JOIN event e ON e.id = s.event_id
             WHERE LOWER(s.contact_email) = :email
             GROUP BY e.id, e.title
             ORDER BY total DESC',
            ['email' => mb_strtolower(trim($email))]
        );

        $map = [];
        foreach ($rows as $row) {
            $title = (string) ($row['eventTitle'] ?? '-');
            $map[$title] = (float) ($row['total'] ?? 0);
        }

        return $map;
    }

    public function findLastIndustryByEmail(string $email): ?string
    {
        $value = $this->createQueryBuilder('s')
            ->select('s.industry')
            ->andWhere('LOWER(s.contactEmail) = :email')
            ->andWhere("s.industry IS NOT NULL")
            ->andWhere("s.industry != ''")
            ->setParameter('email', mb_strtolower(trim($email)))
            ->orderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!is_array($value)) {
            return null;
        }

        return isset($value['industry']) ? (string) $value['industry'] : null;
    }

    /**
     * @return string[]
     */
    public function getIndustriesByEmail(string $email): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('DISTINCT s.industry AS industry')
            ->andWhere('LOWER(s.contactEmail) = :email')
            ->andWhere('s.industry IS NOT NULL')
            ->andWhere("s.industry != ''")
            ->setParameter('email', mb_strtolower(trim($email)))
            ->getQuery()
            ->getArrayResult();

        $values = [];
        foreach ($rows as $row) {
            $industry = trim((string) ($row['industry'] ?? ''));
            if ($industry !== '') {
                $values[] = $industry;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param int[] $eventIds
     * @return array<int,string>
     */
    public function getEventTitleMap(array $eventIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT id, title FROM event WHERE id IN (?)',
            [$ids],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = (string) ($row['title'] ?? '-');
        }

        return $map;
    }
}
