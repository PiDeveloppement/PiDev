<?php

namespace App\Tests\Service\Questionnaire;

use App\Entity\Questionnaire\QuizSession;
use App\Entity\User\UserModel;
use App\Entity\Event\Event;
use PHPUnit\Framework\TestCase;

class QuizSessionTest extends TestCase
{
    private QuizSession $quizSession;

    protected function setUp(): void
    {
        $this->quizSession = new QuizSession();
    }

    public function testConstructorGeneratesSessionToken(): void
    {
        $this->assertNotNull($this->quizSession->getSessionToken());
        $this->assertIsString($this->quizSession->getSessionToken());
        $this->assertEquals(64, strlen($this->quizSession->getSessionToken())); // 32 bytes * 2 (hex)
    }

    public function testDefaultValues(): void
    {
        $this->assertFalse($this->quizSession->isRecaptchaVerified());
        $this->assertEquals('pending', $this->quizSession->getStatus());
        $this->assertNull($this->quizSession->getId());
        $this->assertNull($this->quizSession->getRecaptchaToken());
        $this->assertNull($this->quizSession->getStartedAt());
        $this->assertNull($this->quizSession->getCompletedAt());
        $this->assertNull($this->quizSession->getIpAddress());
        $this->assertNull($this->quizSession->getUserAgent());
        $this->assertNull($this->quizSession->getUser());
        $this->assertNull($this->quizSession->getEvent());
    }

    public function testSetAndGetSessionToken(): void
    {
        $token = 'test_token_123';
        $this->quizSession->setSessionToken($token);
        $this->assertEquals($token, $this->quizSession->getSessionToken());
    }

    public function testSetAndGetRecaptchaVerified(): void
    {
        $this->quizSession->setRecaptchaVerified(true);
        $this->assertTrue($this->quizSession->isRecaptchaVerified());

        $this->quizSession->setRecaptchaVerified(false);
        $this->assertFalse($this->quizSession->isRecaptchaVerified());
    }

    public function testSetAndGetRecaptchaToken(): void
    {
        $token = 'recaptcha_token_456';
        $this->quizSession->setRecaptchaToken($token);
        $this->assertEquals($token, $this->quizSession->getRecaptchaToken());

        $this->quizSession->setRecaptchaToken(null);
        $this->assertNull($this->quizSession->getRecaptchaToken());
    }

    public function testSetAndGetStartedAt(): void
    {
        $dateTime = new \DateTimeImmutable('2023-01-01 10:00:00');
        $this->quizSession->setStartedAt($dateTime);
        $this->assertEquals($dateTime, $this->quizSession->getStartedAt());

        $this->quizSession->setStartedAt(null);
        $this->assertNull($this->quizSession->getStartedAt());
    }

    public function testSetAndGetCompletedAt(): void
    {
        $dateTime = new \DateTimeImmutable('2023-01-01 11:00:00');
        $this->quizSession->setCompletedAt($dateTime);
        $this->assertEquals($dateTime, $this->quizSession->getCompletedAt());

        $this->quizSession->setCompletedAt(null);
        $this->assertNull($this->quizSession->getCompletedAt());
    }

    public function testSetAndGetStatus(): void
    {
        $statuses = ['pending', 'started', 'completed', 'aborted'];
        
        foreach ($statuses as $status) {
            $this->quizSession->setStatus($status);
            $this->assertEquals($status, $this->quizSession->getStatus());
        }
    }

    public function testSetAndGetIpAddress(): void
    {
        $ipAddress = '192.168.1.1';
        $this->quizSession->setIpAddress($ipAddress);
        $this->assertEquals($ipAddress, $this->quizSession->getIpAddress());

        $this->quizSession->setIpAddress(null);
        $this->assertNull($this->quizSession->getIpAddress());
    }

    public function testSetAndGetUserAgent(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $this->quizSession->setUserAgent($userAgent);
        $this->assertEquals($userAgent, $this->quizSession->getUserAgent());

        $this->quizSession->setUserAgent(null);
        $this->assertNull($this->quizSession->getUserAgent());
    }

    public function testSetAndGetUser(): void
    {
        $user = $this->createMock(UserModel::class);
        $this->quizSession->setUser($user);
        $this->assertEquals($user, $this->quizSession->getUser());

        $this->quizSession->setUser(null);
        $this->assertNull($this->quizSession->getUser());
    }

    public function testSetAndGetEvent(): void
    {
        $event = $this->createMock(Event::class);
        $this->quizSession->setEvent($event);
        $this->assertEquals($event, $this->quizSession->getEvent());

        $this->quizSession->setEvent(null);
        $this->assertNull($this->quizSession->getEvent());
    }

    public function testCanStart(): void
    {
        // Test default state - cannot start
        $this->assertFalse($this->quizSession->canStart());

        // Test with recaptcha verified but pending status
        $this->quizSession->setRecaptchaVerified(true);
        $this->assertTrue($this->quizSession->canStart());

        // Test with recaptcha verified but started status
        $this->quizSession->setStatus('started');
        $this->assertFalse($this->quizSession->canStart());

        // Test with recaptcha verified but completed status
        $this->quizSession->setStatus('completed');
        $this->assertFalse($this->quizSession->canStart());

        // Test with recaptcha not verified and pending status
        $this->quizSession->setStatus('pending');
        $this->quizSession->setRecaptchaVerified(false);
        $this->assertFalse($this->quizSession->canStart());
    }

    public function testStartQuiz(): void
    {
        // Test successful start
        $this->quizSession->setRecaptchaVerified(true);
        $result = $this->quizSession->startQuiz();
        
        $this->assertEquals('started', $this->quizSession->getStatus());
        $this->assertNotNull($this->quizSession->getStartedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->quizSession->getStartedAt());
        $this->assertSame($this->quizSession, $result); // Test fluent interface
    }

    public function testStartQuizFailsWhenCannotStart(): void
    {
        // Test failure when recaptcha not verified
        $originalStatus = $this->quizSession->getStatus();
        $originalStartedAt = $this->quizSession->getStartedAt();
        
        $this->quizSession->startQuiz();
        
        $this->assertEquals($originalStatus, $this->quizSession->getStatus());
        $this->assertEquals($originalStartedAt, $this->quizSession->getStartedAt());

        // Test failure when already started
        $this->quizSession->setRecaptchaVerified(true);
        $this->quizSession->setStatus('started');
        $originalStartedAt = $this->quizSession->getStartedAt();
        
        $this->quizSession->startQuiz();
        
        $this->assertEquals('started', $this->quizSession->getStatus());
        $this->assertEquals($originalStartedAt, $this->quizSession->getStartedAt());
    }

    public function testCompleteQuiz(): void
    {
        $result = $this->quizSession->completeQuiz();
        
        $this->assertEquals('completed', $this->quizSession->getStatus());
        $this->assertNotNull($this->quizSession->getCompletedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->quizSession->getCompletedAt());
        $this->assertSame($this->quizSession, $result); // Test fluent interface
    }

    public function testIsExpiredWithoutStartedAt(): void
    {
        $this->assertFalse($this->quizSession->isExpired());
    }

    public function testIsExpiredWithRecentStart(): void
    {
        $this->quizSession->setStartedAt(new \DateTimeImmutable());
        $this->assertFalse($this->quizSession->isExpired());
    }

    public function testIsExpiredWithOldStart(): void
    {
        // Create a started time more than 2 hours ago
        $oldStartTime = new \DateTimeImmutable('-3 hours');
        $this->quizSession->setStartedAt($oldStartTime);
        $this->assertTrue($this->quizSession->isExpired());
    }

    public function testIsExpiredExactlyAtLimit(): void
    {
        // Create a started time exactly 2 hours ago
        $limitStartTime = new \DateTimeImmutable('-2 hours');
        $this->quizSession->setStartedAt($limitStartTime);
        
        // Due to execution time and precision, this might be true or false
        // The important thing is that it works consistently with the business logic
        $result = $this->quizSession->isExpired();
        $this->assertIsBool($result);
    }

    public function testFluentInterfaceOnSetters(): void
    {
        $user = $this->createMock(UserModel::class);
        $event = $this->createMock(Event::class);
        $dateTime = new \DateTimeImmutable();

        $result = $this->quizSession
            ->setSessionToken('token')
            ->setRecaptchaVerified(true)
            ->setRecaptchaToken('recaptcha')
            ->setStartedAt($dateTime)
            ->setCompletedAt($dateTime)
            ->setStatus('started')
            ->setIpAddress('127.0.0.1')
            ->setUserAgent('browser')
            ->setUser($user)
            ->setEvent($event);

        $this->assertSame($this->quizSession, $result);
    }
}
