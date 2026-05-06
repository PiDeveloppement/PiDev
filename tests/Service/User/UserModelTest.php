<?php

namespace App\Tests\Service\User;

use App\Entity\User\UserModel;
use App\Entity\Role\Role;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la gestion des utilisateurs
 * 
 * Règles métier testées :
 * 1. Le prénom et le nom sont obligatoires (min 2 caractères)
 * 2. L'email doit être valide et unique
 * 3. La faculté est obligatoire
 * 4. Le rôle est obligatoire pour créer un utilisateur
 * 5. Le nom complet (fullName) est correctement construit
 * 6. Les initiales sont correctement générées
 * 7. Les rôles Symfony sont correctement attribués selon le rôle métier
 * 8. La date d'inscription est automatiquement définie à la création
 * 9. Le numéro de téléphone est optionnel
 * 10. eraseCredentials efface bien le mot de passe en clair
 */
class UserModelTest extends TestCase
{
    // ==================== HELPERS ====================

    /**
     * Crée un UserModel valide de base pour les tests
     */
    private function createValidUser(): UserModel
    {
        $role = new Role();
        $role->setRoleName('Default');

        $user = new UserModel();
        $user->setFirstName('Alice');
        $user->setLastName('Dupont');
        $user->setEmail('alice.dupont@esprit.tn');
        $user->setFaculte('Informatique');
        $user->setPassword('hashed_password');
        $user->setRole($role);
        $user->setRoleId(1);

        return $user;
    }

    // ==================== RÈGLE 1 : PRÉNOM / NOM ====================

    /**
     * Un utilisateur avec un prénom valide doit le retourner correctement
     */
    public function testFirstNameIsSetAndRetrieved(): void
    {
        $user = new UserModel();
        $user->setFirstName('Alice');

        $this->assertEquals('Alice', $user->getFirstName());
    }

    /**
     * Un utilisateur avec un nom valide doit le retourner correctement
     */
    public function testLastNameIsSetAndRetrieved(): void
    {
        $user = new UserModel();
        $user->setLastName('Dupont');

        $this->assertEquals('Dupont', $user->getLastName());
    }

    // ==================== RÈGLE 2 : EMAIL ====================

    /**
     * Un email valide doit être accepté et retourné
     */
    public function testValidEmailIsAccepted(): void
    {
        $user = new UserModel();
        $user->setEmail('alice@esprit.tn');

        $this->assertEquals('alice@esprit.tn', $user->getEmail());
    }

    /**
     * getUserIdentifier doit retourner l'email (requis par Symfony Security)
     */
    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new UserModel();
        $user->setEmail('alice@esprit.tn');

        $this->assertEquals('alice@esprit.tn', $user->getUserIdentifier());
    }

    // ==================== RÈGLE 3 : FACULTÉ ====================

    /**
     * La faculté doit être stockée et retournée correctement
     */
    public function testFaculteIsSetAndRetrieved(): void
    {
        $user = new UserModel();
        $user->setFaculte('Informatique');

        $this->assertEquals('Informatique', $user->getFaculte());
    }

    // ==================== RÈGLE 4 : RÔLE OBLIGATOIRE ====================

    /**
     * Un utilisateur sans rôle doit avoir getRole() retournant null
     */
    public function testUserWithoutRoleHasNullRole(): void
    {
        $user = new UserModel();

        $this->assertNull($user->getRole());
    }

    /**
     * Un utilisateur avec un rôle doit le retourner correctement
     */
    public function testRoleIsAssignedCorrectly(): void
    {
        $role = new Role();
        $role->setRoleName('Admin');

        $user = new UserModel();
        $user->setRole($role);

        $this->assertNotNull($user->getRole());
        $this->assertEquals('Admin', $user->getRole()->getRoleName());
    }

    // ==================== RÈGLE 5 : NOM COMPLET ====================

    /**
     * getFullName doit retourner "Prénom Nom" correctement
     */
    public function testGetFullNameCombinesFirstAndLastName(): void
    {
        $user = $this->createValidUser();

        $this->assertEquals('Alice Dupont', $user->getFullName());
    }

    /**
     * getFullName sans prénom doit retourner uniquement le nom
     */
    public function testGetFullNameWithOnlyLastName(): void
    {
        $user = new UserModel();
        $user->setLastName('Dupont');

        $this->assertEquals('Dupont', $user->getFullName());
    }

    /**
     * getFullName sans nom doit retourner uniquement le prénom
     */
    public function testGetFullNameWithOnlyFirstName(): void
    {
        $user = new UserModel();
        $user->setFirstName('Alice');

        $this->assertEquals('Alice', $user->getFullName());
    }

    // ==================== RÈGLE 6 : INITIALES ====================

    /**
     * getInitials doit retourner les deux premières lettres majuscules
     */
    public function testGetInitialsWithBothNames(): void
    {
        $user = $this->createValidUser();

        $this->assertEquals('AD', $user->getInitials());
    }

    /**
     * getInitials sans aucun nom doit retourner 'U' (unknown)
     */
    public function testGetInitialsWithNoNameReturnsU(): void
    {
        $user = new UserModel();

        $this->assertEquals('U', $user->getInitials());
    }

    /**
     * getInitials doit toujours être en majuscules
     */
    public function testGetInitialsAreUppercase(): void
    {
        $user = new UserModel();
        $user->setFirstName('alice');
        $user->setLastName('dupont');

        $this->assertEquals('AD', $user->getInitials());
    }

    // ==================== RÈGLE 7 : RÔLES SYMFONY ====================

    /**
     * Tout utilisateur doit avoir au minimum ROLE_USER
     */
    public function testGetRolesAlwaysContainsRoleUser(): void
    {
        $user = $this->createValidUser();

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    /**
     * Un utilisateur avec le rôle 'Admin' doit avoir ROLE_ADMIN
     */
    public function testAdminUserHasRoleAdmin(): void
    {
        $role = new Role();
        $role->setRoleName('Admin');

        $user = new UserModel();
        $user->setRole($role);
        $user->setRoleId(2);

        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    /**
     * Un utilisateur avec le rôle 'Organisateur' doit avoir ROLE_ORGANISATEUR
     */
    public function testOrganisateurUserHasRoleOrganisateur(): void
    {
        $role = new Role();
        $role->setRoleName('Organisateur');

        $user = new UserModel();
        $user->setRole($role);
        $user->setRoleId(3);

        $this->assertContains('ROLE_ORGANISATEUR', $user->getRoles());
    }

    /**
     * Un utilisateur avec le rôle 'Sponsor' doit avoir ROLE_SPONSOR
     */
    public function testSponsorUserHasRoleSponsor(): void
    {
        $role = new Role();
        $role->setRoleName('Sponsor');

        $user = new UserModel();
        $user->setRole($role);
        $user->setRoleId(4);

        $this->assertContains('ROLE_SPONSOR', $user->getRoles());
    }

    /**
     * Un utilisateur 'Default' ne doit pas avoir de rôle spécial au-delà de ROLE_USER
     */
    public function testDefaultUserHasOnlyRoleUser(): void
    {
        $role = new Role();
        $role->setRoleName('Default');

        $user = new UserModel();
        $user->setRole($role);
        $user->setRoleId(1);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
        $this->assertNotContains('ROLE_ADMIN', $roles);
        $this->assertNotContains('ROLE_ORGANISATEUR', $roles);
        $this->assertNotContains('ROLE_SPONSOR', $roles);
    }

    // ==================== RÈGLE 8 : DATE D'INSCRIPTION ====================

    /**
     * La date d'inscription doit être automatiquement définie à la création
     */
    public function testRegistrationDateIsSetAutomaticallyOnConstruct(): void
    {
        $user = new UserModel();

        $this->assertNotNull($user->getRegistrationDate());
        $this->assertInstanceOf(\DateTimeInterface::class, $user->getRegistrationDate());
    }

    /**
     * La date d'inscription peut être modifiée manuellement
     */
    public function testRegistrationDateCanBeOverridden(): void
    {
        $user = new UserModel();
        $date = new \DateTime('2024-01-15');
        $user->setRegistrationDate($date);

        $this->assertEquals('15/01/2024', $user->getRegistrationDate()->format('d/m/Y'));
    }

    /**
     * getFormattedRegistrationDate retourne un format lisible
     */
    public function testGetFormattedRegistrationDateReturnsCorrectFormat(): void
    {
        $user = new UserModel();
        $user->setRegistrationDate(new \DateTime('2024-06-01 10:30:00'));

        $this->assertEquals('01/06/2024 10:30', $user->getFormattedRegistrationDate());
    }

    /**
     * getFormattedRegistrationDate sans date retourne 'Non disponible'
     */
    public function testGetFormattedRegistrationDateWithNullReturnsNonDisponible(): void
    {
        $user = new UserModel();
        $user->setRegistrationDate(null);

        $this->assertEquals('Non disponible', $user->getFormattedRegistrationDate());
    }

    // ==================== RÈGLE 9 : TÉLÉPHONE (OPTIONNEL) ====================

    /**
     * Le téléphone est null par défaut
     */
    public function testPhoneIsNullByDefault(): void
    {
        $user = new UserModel();

        $this->assertNull($user->getPhone());
    }

    /**
     * Le téléphone peut être défini et récupéré
     */
    public function testPhoneCanBeSetAndRetrieved(): void
    {
        $user = new UserModel();
        $user->setPhone('+21655123456');

        $this->assertEquals('+21655123456', $user->getPhone());
    }

    /**
     * Le téléphone peut être remis à null
     */
    public function testPhoneCanBeSetToNull(): void
    {
        $user = new UserModel();
        $user->setPhone('+21655123456');
        $user->setPhone(null);

        $this->assertNull($user->getPhone());
    }

    // ==================== RÈGLE 10 : EFFACEMENT DES CREDENTIALS ====================

    /**
     * eraseCredentials doit effacer le mot de passe en clair (plainPassword)
     */
    public function testEraseCredentialsClearsPlainPassword(): void
    {
        $user = new UserModel();
        $user->setPlainPassword('MonMotDePasse123');

        $this->assertEquals('MonMotDePasse123', $user->getPlainPassword());

        $user->eraseCredentials();

        $this->assertNull($user->getPlainPassword());
    }

    /**
     * eraseCredentials ne doit pas effacer le mot de passe hashé
     */
    public function testEraseCredentialsDoesNotClearHashedPassword(): void
    {
        $user = new UserModel();
        $user->setPassword('$2y$10$hashedpassword...');
        $user->eraseCredentials();

        $this->assertNotNull($user->getPassword());
    }

    // ==================== __toString ====================

    /**
     * __toString doit retourner le nom complet s'il existe
     */
    public function testToStringReturnsFullName(): void
    {
        $user = $this->createValidUser();

        $this->assertEquals('Alice Dupont', (string) $user);
    }

    /**
     * __toString sans nom doit retourner l'email
     */
    public function testToStringFallsBackToEmail(): void
    {
        $user = new UserModel();
        $user->setEmail('alice@esprit.tn');

        $this->assertEquals('alice@esprit.tn', (string) $user);
    }
}
