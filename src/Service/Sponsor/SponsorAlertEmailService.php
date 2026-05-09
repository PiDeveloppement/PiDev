<?php

namespace App\Service\Sponsor;

use App\Entity\User\UserModel;
use App\Repository\Sponsor\SponsorRepository;
use App\Repository\User\UserRepository;
use App\Entity\Sponsor\Sponsor;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SponsorAlertEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private SponsorRepository $sponsorRepository,
        private UserRepository $userRepository,
        private SponsorService $sponsorService,
        private string $fromEmail = 'manaimaryem4@gmail.com'
    ) {
    }

    /**
     * @return array{sent:bool,reason:string,error?:string}
     */
    public function sendRecommendationEmailForContact(string $email): array
    {
        // Point d'entree pour envoyer un mail de recommandations a un contact sponsor unique.
        $normalizedEmail = mb_strtolower(trim($email));
        if ($normalizedEmail === '') {
            return ['sent' => false, 'reason' => 'missing_email'];
        }

        // Recuperer a la fois l'historique du sponsor et le catalogue des evenements.
        $mySponsors = $this->sponsorRepository->findByContactEmailWithEvent($normalizedEmail);
        $events = $this->sponsorService->fetchEventsCatalog();

        $user = $this->userRepository->findByEmail($normalizedEmail);
        if ($user instanceof UserModel && $this->sponsorService->isSponsorUser($user)) {
            $recommended = $this->sponsorService->buildRecommendedEvents($events, $user, $normalizedEmail);
        } else {
            $recommended = $this->buildFallbackRecommendations($events, $mySponsors);
        }

        // On filtre les evenements deja sponsorises pour eviter des suggestions redondantes.
        $recommendations = $this->buildUnsponsoredRecommendationRows($recommended, $mySponsors);
        if ($recommendations === []) {
            return ['sent' => false, 'reason' => 'no_recommendations'];
        }

        $recommendations = array_slice($recommendations, 0, 6);

        try {
            $this->mailer->send($this->buildRecommendationsEmail(
                $normalizedEmail,
                $user instanceof UserModel ? $user : null,
                $recommendations
            ));

            return ['sent' => true, 'reason' => 'sent'];
        } catch (\Throwable $exception) {
            return ['sent' => false, 'reason' => 'transport_error', 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array{checked:int,sent:int,withRecommendations:int,errors:int,lastError:string}
     */
    public function sendRecommendedEventsDigestForAllSponsors(): array
    {
        // Traitement batch pour envoyer une campagne de recommandations a tous les contacts sponsor.
        $stats = [
            'checked' => 0,
            'sent' => 0,
            'withRecommendations' => 0,
            'errors' => 0,
            'lastError' => '',
        ];

        $emails = $this->sponsorRepository->getDistinctContactEmails();
        if ($emails === []) {
            return $stats;
        }

        $events = $this->sponsorService->fetchEventsCatalog();

        foreach ($emails as $email) {
            ++$stats['checked'];

            $mySponsors = $this->sponsorRepository->findByContactEmailWithEvent($email);
            $user = $this->userRepository->findByEmail($email);
            if ($user instanceof UserModel && $this->sponsorService->isSponsorUser($user)) {
                $recommended = $this->sponsorService->buildRecommendedEvents($events, $user, $email);
            } else {
                // Fallback so every sponsor contact can still receive a useful digest.
                $recommended = $this->buildFallbackRecommendations($events, $mySponsors);
            }

            $recommendations = $this->buildUnsponsoredRecommendationRows($recommended, $mySponsors);
            if ($recommendations === []) {
                continue;
            }

            ++$stats['withRecommendations'];
            $recommendations = array_slice($recommendations, 0, 6);

            try {
                $this->mailer->send($this->buildRecommendationsEmail($email, $user instanceof UserModel ? $user : null, $recommendations));
                ++$stats['sent'];
            } catch (\Throwable $exception) {
                ++$stats['errors'];
                $stats['lastError'] = $exception->getMessage();
            }
        }

        return $stats;
    }

    /**
     * @param array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeInterface,endDate:?\DateTimeInterface}> $recommendedEvents
     * @param array<int,Sponsor> $mySponsors
     */
    public function sendRecommendationEmailForSponsor(UserModel $user, string $email, array $recommendedEvents, array $mySponsors): bool
    {
        $recommendations = $this->buildUnsponsoredRecommendationRows($recommendedEvents, $mySponsors);
        if ($recommendations === []) {
            return false;
        }

        $recommendations = array_slice($recommendations, 0, 6);

        try {
            $this->mailer->send($this->buildRecommendationsEmail($email, $user, $recommendations));
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<int,array{id:int,title:string,location:string,date:string}> $recommendations
     */
    private function buildRecommendationsEmail(string $to, ?UserModel $user, array $recommendations): Email
    {
        // Construction manuelle du contenu pour garder un message simple et robuste.
        $name = '';
        if ($user instanceof UserModel) {
            $name = trim((string) (($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? '')));
        }
        $displayName = $name !== '' ? $name : 'Sponsor';

        $lines = [];
        foreach ($recommendations as $recommendation) {
            $lines[] = sprintf(
                '- %s (%s) - date %s',
                $recommendation['title'],
                $recommendation['location'],
                $recommendation['date']
            );
        }

        $text = "Bonjour {$displayName},\n\n";
        $text .= "Voici vos evenements recommandes selon votre profil sponsor.\n\n";
        $text .= implode("\n", $lines);
        $text .= "\n\nConsultez votre portail sponsor pour voir plus de details et sponsoriser les evenements qui vous interessent.\n";
        $text .= "\nEventFlow";

        $htmlItems = [];
        foreach ($recommendations as $recommendation) {
            $htmlItems[] = sprintf(
                '<li><strong>%s</strong> (%s) - date %s</li>',
                htmlspecialchars($recommendation['title'], ENT_QUOTES),
                htmlspecialchars($recommendation['location'], ENT_QUOTES),
                htmlspecialchars($recommendation['date'], ENT_QUOTES)
            );
        }

        $html = '<p>Bonjour <strong>' . htmlspecialchars($displayName, ENT_QUOTES) . '</strong>,</p>';
        $html .= '<p>Voici vos evenements recommandes selon votre profil sponsor.</p>';
        $html .= '<ul>' . implode('', $htmlItems) . '</ul>';
        $html .= '<p>Connectez-vous a votre portail sponsor pour voir plus de details et sponsoriser les evenements qui vous interessent.</p>';
        $html .= '<p>EventFlow</p>';

        return (new Email())
            ->from($this->fromEmail)
            ->to($to)
            ->subject('EventFlow: vos recommandations d\'evenements')
            ->text($text)
            ->html($html);
    }

    /**
     * @param array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeInterface,endDate:?\DateTimeInterface}> $events
     * @param array<int,Sponsor> $mySponsors
     * @return array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeInterface,endDate:?\DateTimeInterface}>
     */
    private function buildFallbackRecommendations(array $events, array $mySponsors): array
    {
        // Fallback metier: proposer simplement les prochains evenements non encore sponsorises.
        $sponsoredEventIds = [];
        foreach ($mySponsors as $sponsor) {
            $sponsoredEventIds[(int) $sponsor->getEventId()] = true;
        }

        $upcoming = array_values(array_filter($events, static function (array $event) use ($sponsoredEventIds): bool {
            $eventId = (int) $event['id'];
            if ($eventId <= 0 || isset($sponsoredEventIds[$eventId])) {
                return false;
            }

            $endDate = $event['endDate'];
            if (!$endDate instanceof \DateTimeInterface) {
                return true;
            }

            return $endDate >= new \DateTimeImmutable();
        }));

        usort($upcoming, static function (array $a, array $b): int {
            $aTs = ($a['startDate'] ?? null) instanceof \DateTimeInterface ? $a['startDate']->getTimestamp() : \PHP_INT_MAX;
            $bTs = ($b['startDate'] ?? null) instanceof \DateTimeInterface ? $b['startDate']->getTimestamp() : \PHP_INT_MAX;
            return $aTs <=> $bTs;
        });

        return array_slice($upcoming, 0, 6);
    }

    /**
     * @param array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeInterface,endDate:?\DateTimeInterface}> $recommendedEvents
     * @param array<int,Sponsor> $mySponsors
     * @return array<int,array{id:int,title:string,location:string,date:string}>
     */
    private function buildUnsponsoredRecommendationRows(array $recommendedEvents, array $mySponsors): array
    {
        // Le mail final ne garde que les champs utiles au sponsor: titre, lieu et date.
        $sponsoredEventIds = [];
        foreach ($mySponsors as $sponsor) {
            $sponsoredEventIds[] = (int) $sponsor->getEventId();
        }

        $rows = [];
        foreach ($recommendedEvents as $event) {
            $eventId = (int) $event['id'];
            if ($eventId <= 0 || in_array($eventId, $sponsoredEventIds, true)) {
                continue;
            }

            $startDate = $event['startDate'];
            $rows[] = [
                'id' => $eventId,
                'title' => (string) $event['title'],
                'location' => (string) $event['location'],
                'date' => $startDate instanceof \DateTimeInterface ? $startDate->format('d/m/Y H:i') : '-',
            ];
        }

        return $rows;
    }
}