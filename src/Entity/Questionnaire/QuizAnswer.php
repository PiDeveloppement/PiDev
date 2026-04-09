<?php

namespace App\Entity\Questionnaire;

use Symfony\Component\Validator\Constraints as Assert;

class QuizAnswer
{
    #[Assert\NotBlank(message: "Vous devez répondre à cette question.")]
    private ?string $answer = null;

    private ?int $questionId = null;

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(?string $answer): self
    {
        $this->answer = $answer;
        return $this;
    }

    public function getQuestionId(): ?int
    {
        return $this->questionId;
    }

    public function setQuestionId(?int $questionId): self
    {
        $this->questionId = $questionId;
        return $this;
    }
}
