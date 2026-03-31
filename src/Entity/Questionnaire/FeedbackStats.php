<?php
// src/Dto/FeedbackStats.php

namespace App\Dto;

class FeedbackStats
{
    private int $id;
    private string $username;
    private string $commentaire;
    private string $score;
    private int $etoiles;

    public function __construct(int $id, string $username, string $commentaire, string $score, int $etoiles)
    {
        $this->id = $id;
        $this->username = $username;
        $this->commentaire = $commentaire;
        $this->score = $score;
        $this->etoiles = $etoiles;
    }

    public function getId(): int { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function getCommentaire(): string { return $this->commentaire; }
    public function getScore(): string { return $this->score; }
    public function getEtoiles(): int { return $this->etoiles; }
}