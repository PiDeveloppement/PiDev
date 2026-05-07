<?php

namespace App\Tests\Service\Questionnaire;

use App\Entity\Questionnaire\Question;
use App\Entity\User\UserModel;
use App\Entity\Event\Event;
use PHPUnit\Framework\TestCase;

class QuestionTest extends TestCase
{
    private Question $question;

    protected function setUp(): void
    {
        $this->question = new Question();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->question->getId());
        $this->assertNull($this->question->getTexte());
        $this->assertNull($this->question->getReponse());
        $this->assertEquals(0, $this->question->getPoints());
        $this->assertNull($this->question->getOption1());
        $this->assertNull($this->question->getOption2());
        $this->assertNull($this->question->getOption3());
        $this->assertNull($this->question->getEvent());
        $this->assertNull($this->question->getUser());
    }

    public function testSetAndGetTexte(): void
    {
        $texte = 'Quelle est la capitale de la France?';
        $this->question->setTexte($texte);
        $this->assertEquals($texte, $this->question->getTexte());

        $this->question->setTexte(null);
        $this->assertNull($this->question->getTexte());
    }

    public function testSetAndGetReponse(): void
    {
        $reponse = 'Paris';
        $this->question->setReponse($reponse);
        $this->assertEquals($reponse, $this->question->getReponse());

        $this->question->setReponse(null);
        $this->assertNull($this->question->getReponse());
    }

    public function testSetAndGetPoints(): void
    {
        $points = 10;
        $this->question->setPoints($points);
        $this->assertEquals($points, $this->question->getPoints());

        // Test with zero
        $this->question->setPoints(0);
        $this->assertEquals(0, $this->question->getPoints());

        // Test with negative (should be allowed in setter, validation happens at entity level)
        $this->question->setPoints(-5);
        $this->assertEquals(-5, $this->question->getPoints());
    }

    public function testSetAndGetOption1(): void
    {
        $option1 = 'Lyon';
        $this->question->setOption1($option1);
        $this->assertEquals($option1, $this->question->getOption1());

        $this->question->setOption1(null);
        $this->assertNull($this->question->getOption1());
    }

    public function testSetAndGetOption2(): void
    {
        $option2 = 'Marseille';
        $this->question->setOption2($option2);
        $this->assertEquals($option2, $this->question->getOption2());

        $this->question->setOption2(null);
        $this->assertNull($this->question->getOption2());
    }

    public function testSetAndGetOption3(): void
    {
        $option3 = 'Bordeaux';
        $this->question->setOption3($option3);
        $this->assertEquals($option3, $this->question->getOption3());

        $this->question->setOption3(null);
        $this->assertNull($this->question->getOption3());
    }

    public function testSetAndGetEvent(): void
    {
        $event = $this->createMock(Event::class);
        $this->question->setEvent($event);
        $this->assertEquals($event, $this->question->getEvent());

        $this->question->setEvent(null);
        $this->assertNull($this->question->getEvent());
    }

    public function testSetAndGetUser(): void
    {
        $user = $this->createMock(UserModel::class);
        $this->question->setUser($user);
        $this->assertEquals($user, $this->question->getUser());

        $this->question->setUser(null);
        $this->assertNull($this->question->getUser());
    }

    public function testFluentInterfaceOnSetters(): void
    {
        $user = $this->createMock(UserModel::class);
        $event = $this->createMock(Event::class);

        $result = $this->question
            ->setTexte('Question test')
            ->setReponse('Réponse test')
            ->setPoints(5)
            ->setOption1('Option 1')
            ->setOption2('Option 2')
            ->setOption3('Option 3')
            ->setEvent($event)
            ->setUser($user);

        $this->assertSame($this->question, $result);
    }

    public function testCompleteQuestionSetup(): void
    {
        $user = $this->createMock(UserModel::class);
        $event = $this->createMock(Event::class);

        $this->question
            ->setTexte('Quelle est la plus grande ville du monde?')
            ->setReponse('Tokyo')
            ->setPoints(15)
            ->setOption1('New York')
            ->setOption2('Londres')
            ->setOption3('Paris')
            ->setEvent($event)
            ->setUser($user);

        $this->assertEquals('Quelle est la plus grande ville du monde?', $this->question->getTexte());
        $this->assertEquals('Tokyo', $this->question->getReponse());
        $this->assertEquals(15, $this->question->getPoints());
        $this->assertEquals('New York', $this->question->getOption1());
        $this->assertEquals('Londres', $this->question->getOption2());
        $this->assertEquals('Paris', $this->question->getOption3());
        $this->assertEquals($event, $this->question->getEvent());
        $this->assertEquals($user, $this->question->getUser());
    }

    public function testQuestionWithNullOptions(): void
    {
        $this->question
            ->setTexte('Question sans options')
            ->setReponse('Réponse')
            ->setPoints(10);

        $this->assertNull($this->question->getOption1());
        $this->assertNull($this->question->getOption2());
        $this->assertNull($this->question->getOption3());
    }

    public function testQuestionWithPartialOptions(): void
    {
        $this->question
            ->setTexte('Question avec options partielles')
            ->setReponse('Réponse')
            ->setPoints(5)
            ->setOption1('Option 1')
            ->setOption3('Option 3');

        $this->assertEquals('Option 1', $this->question->getOption1());
        $this->assertNull($this->question->getOption2());
        $this->assertEquals('Option 3', $this->question->getOption3());
    }

    public function testQuestionWithoutUserOrEvent(): void
    {
        $this->question
            ->setTexte('Question standalone')
            ->setReponse('Réponse')
            ->setPoints(5);

        $this->assertNull($this->question->getUser());
        $this->assertNull($this->question->getEvent());
        $this->assertEquals('Question standalone', $this->question->getTexte());
        $this->assertEquals('Réponse', $this->question->getReponse());
        $this->assertEquals(5, $this->question->getPoints());
    }
}
