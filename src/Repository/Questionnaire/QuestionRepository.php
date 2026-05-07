<?php

namespace App\Repository\Questionnaire;

use App\Entity\Questionnaire\Question;
use App\Entity\Event\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Question>
 */
class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    //    /**
    //     * @return Question[] Returns an array of Question objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('q')
    //            ->andWhere('q.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('q.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Question
    //    {
    //        return $this->createQueryBuilder('q')
    //            ->andWhere('q.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Récupère les questions pour un quiz (général ou par événement)
     */
    public function findQuestionsForQuiz(?Event $event = null): array
    {
        $qb = $this->createQueryBuilder('q')
            ->orderBy('q.id', 'ASC');

        if ($event) {
            $qb->where('q.event = :event')
               ->setParameter('event', $event);
        } else {
            $qb->where('q.event IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère un nombre limité de questions aléatoires pour un quiz
     */
    public function findRandomQuestionsForQuiz(?Event $event = null, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('q');

        if ($event) {
            $qb->where('q.event = :event')
               ->setParameter('event', $event);
        } else {
            $qb->where('q.event IS NULL');
        }

        $questions = $qb->getQuery()->getResult();
        
        // Mélanger et limiter les questions
        shuffle($questions);
        return array_slice($questions, 0, $limit);
    }
}