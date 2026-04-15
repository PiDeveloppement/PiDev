<?php
// src/Service/UserSessionService.php

namespace App\Service;

use App\Entity\User\UserModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Psr\Log\LoggerInterface;

class UserSessionService
{
    private const SESSION_USER_KEY = 'current_user';
    private const SESSION_PENDING_EVENT_KEY = 'pending_event_id';

    private ?UserModel $currentUser = null;
    private SessionInterface $session;
    private LoggerInterface $logger;

    public function __construct(
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->session = $requestStack->getSession();
        $this->logger = $logger;
        $this->loadUserFromSession();
    }

    private function loadUserFromSession(): void
    {
        if ($this->session->has(self::SESSION_USER_KEY)) {
            $this->currentUser = $this->session->get(self::SESSION_USER_KEY);
            $this->logger->info('✅ Utilisateur chargé depuis la session');
        }
    }

    public function setCurrentUser(?UserModel $user): self
    {
        $this->currentUser = $user;
        
        if ($user) {
            $this->session->set(self::SESSION_USER_KEY, $user);
            $this->logger->info('✅ Utilisateur connecté: ' . $user->getEmail());
        } else {
            $this->session->remove(self::SESSION_USER_KEY);
            $this->logger->info('👋 Déconnexion utilisateur');
        }
        
        return $this;
    }

    public function getCurrentUser(): ?UserModel
    {
        return $this->currentUser;
    }

    public function isLoggedIn(): bool
    {
        return $this->currentUser !== null;
    }

    public function clearSession(): self
    {
        $this->currentUser = null;
        $this->session->remove(self::SESSION_USER_KEY);
        $this->session->remove(self::SESSION_PENDING_EVENT_KEY);
        return $this;
    }

    public function getFullName(): string
    {
        if ($this->currentUser) {
            return trim($this->currentUser->getFirstName() . ' ' . $this->currentUser->getLastName());
        }
        return 'Invité';
    }

    public function getInitials(): string
    {
        if ($this->currentUser) {
            $first = !empty($this->currentUser->getFirstName()) 
                ? substr($this->currentUser->getFirstName(), 0, 1) : '';
            $last = !empty($this->currentUser->getLastName()) 
                ? substr($this->currentUser->getLastName(), 0, 1) : '';
            return strtoupper($first . $last);
        }
        return 'U';
    }

    public function getRole(): ?string
    {
        if ($this->currentUser && $this->currentUser->getRole()) {
            return $this->currentUser->getRole()->getRoleName();
        }
        return null;
    }

    public function getUserId(): int
    {
        return $this->currentUser ? $this->currentUser->getId() : -1;
    }

    public function setPendingEventId(int $eventId): self
    {
        $this->session->set(self::SESSION_PENDING_EVENT_KEY, $eventId);
        $this->logger->info('📌 Événement en attente: ' . $eventId);
        return $this;
    }

    public function getPendingEventId(): ?int
    {
        return $this->session->get(self::SESSION_PENDING_EVENT_KEY);
    }

    public function hasPendingEvent(): bool
    {
        return $this->session->has(self::SESSION_PENDING_EVENT_KEY);
    }

    public function clearPendingEventId(): self
    {
        $this->session->remove(self::SESSION_PENDING_EVENT_KEY);
        return $this;
    }
}