# 📋 Système de Billetterie - Workflow Complet

## Table des Matières
1. [Vue d'ensemble](#vue-densemble)
2. [Architecture des données](#architecture-des-données)
3. [Flux détaillé](#flux-détaillé)
4. [Règles métier](#règles-métier)
5. [API Endpoints](#api-endpoints)
6. [Implémentation Java](#implémentation-java)

---

## Vue d'ensemble

Le système de billetterie gère le cycle complet :
- **Création** : Quand un utilisateur participe à un événement
- **Stockage** : Persistance en base de données
- **Consultation** : Listage et détails des billets
- **Utilisation** : Marquage comme utilisé lors de la validation
- **Suppression** : Gestion des billets archivés

```
Utilisateur participe à événement
         ↓
    Création Ticket
         ↓
  Génération Code + QR
         ↓
   Persistance BD
         ↓
Notification utilisateur
         ↓
Billet visible dans "Mes billets"
```

---

## Architecture des données

### Entity Ticket (Symfony/Doctrine)

```php
// src/Entity/Event/Ticket.php

namespace App\Entity\Event;

use App\Entity\User\UserModel;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "event_ticket")]
class Ticket
{
    // IDENTIFIANT
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    // DONNÉES MÉTIER
    #[ORM\Column(name: "ticket_code", length: 50, unique: true)]
    private ?string $ticketCode = null;          // Format: EVT-{eventId}-{userId}-{timestamp}

    #[ORM\Column(name: "qr_code", type: Types::TEXT, nullable: true)]
    private ?string $qrCode = null;              // Token unique pour scan: tkt_[hex(24bytes)]

    #[ORM\Column(name: "is_used", type: Types::BOOLEAN, options: ["default" => false])]
    private ?bool $isUsed = false;               // Marqueur d'utilisation

    #[ORM\Column(name: "used_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $usedAt = null;  // Timestamp de validation

    // MÉTADONNÉES
    #[ORM\Column(name: "created_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: "user_id", type: "integer", nullable: true)]
    private ?int $userId = null;

    // RELATIONS
    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: "tickets")]
    #[ORM\JoinColumn(name: "event_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: UserModel::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "Id_User", nullable: false)]
    private ?UserModel $user = null;

    // MÉTHODES UTILITAIRES
    public static function generateTicketCode(int $eventId, int $userId): string
    {
        $timestamp = substr((string) time(), -7);
        return sprintf('EVT-%d-%d-%s', $eventId, $userId, $timestamp);
    }

    public function isValid(): bool 
    { 
        return !$this->isUsed; 
    }

    public function markAsUsed(): self
    {
        $this->isUsed = true;
        $this->usedAt = new \DateTime();
        return $this;
    }
}
```

### Schéma Base de Données

```sql
CREATE TABLE event_ticket (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    ticket_code VARCHAR(50) NOT NULL UNIQUE,
    qr_code LONGTEXT DEFAULT NULL,
    is_used TINYINT(1) DEFAULT 0,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT NULL,
    
    INDEX IDX_event (event_id),
    INDEX IDX_user (user_id),
    
    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_model(Id_User) ON DELETE CASCADE
);
```

---

## Flux détaillé

### 1. FLUX DE PARTICIPATION & CRÉATION DE TICKET

```
USER ACTION: Click "Participer"
    ↓
[POST] /events/{id}/participate
    ↓
EventFrontController::participate()
    ├─ Vérification user connecté
    │  └─ Si NON → Redirection login + "pending_event" en session
    └─ Si OUI → Redirection vers confirmation
    ↓
[GET/POST] /events/{id}/participate/confirm
    ↓
EventFrontController::confirmParticipation()
    ├─ GET: Affiche formulaire de confirmation
    ├─ POST: Traite la participation
    │  ├─ Vérification CSRF token
    │  ├─ Vérification capacité max
    │  │  └─ count(tickets) >= capacity → Erreur
    │  ├─ Vérification participation double
    │  │  └─ Ticket existe déjà → Info flash
    │  └─ CRÉATION TICKET:
    │     ├─ new Ticket()
    │     ├─ setEvent($event)
    │     ├─ setUser($user)
    │     ├─ setTicketCode(generateTicketCode(eventId, userId))
    │     │  └─ Format: EVT-{eventId}-{userId}-{timestamp}
    │     ├─ setQrCode(generateUniqueQrToken())
    │     │  └─ Format: tkt_[random_hex(24bytes)]
    │     ├─ setCreatedAt(now)
    │     ├─ $em->persist($ticket)
    │     ├─ $em->flush()
    │     ├─ NotificationService::createConfirmation($user, $event)
    │     └─ Redirection /my-tickets + Flash success
    ↓
USER: Voit son billet en page /my-tickets
```

**Règles de validation lors de la confirmation:**
- Utilisateur DOIT être connecté
- Événement DOIT être published et non expirés
- Capacité max ne doit pas être atteinte
- L'utilisateur ne peut pas avoir 2 billets pour le même événement

---

### 2. FLUX DE CONSULTATION DES BILLETS

```
USER ACTION: Accès /my-tickets
    ↓
EventFrontController::myTickets()
    ├─ Récupère tous les tickets de l'utilisateur
    │  ├─ QueryBuilder avec INNER JOIN event
    │  └─ ORDER BY created_at DESC
    ├─ Trie les billets:
    │  ├─ upcomingTickets : Billets valides, événement à venir
    │  └─ historyTickets : Billets utilisés OU événement terminé
    └─ Rendu front/my_tickets.html.twig
    ↓
Vue affiche:
    ├─ Section "Billets à venir" (avec QR code)
    ├─ Section "Historique"
    └─ Boutons d'action (PDF, détails, scan)
```

---

### 3. FLUX DE DÉTAILS & QR CODE

```
USER ACTION: Click billet ou détails
    ↓
[GET] /ticket/{id}
    ↓
TicketController::show()
    ├─ Récupère Ticket par ID
    ├─ Vérification accès (authorization)
    ├─ Si pas de QR code:
    │  ├─ Génération nouveau QR: tkt_[random_hex(24)]
    │  ├─ Persistance
    │  └─ Flush
    └─ Rendu ticket/show.html.twig
    ↓
Vue affiche:
    ├─ QR Code généré via qr_code_data_uri()
    ├─ Numéro ticket (code)
    ├─ Info événement
    ├─ Info participant
    ├─ Dates (création, utilisation)
    └─ Statut (Valide / Utilisé)
```

**QR Code Details:**
- Chaque ticket peut avoir un QR code unique
- URL du QR: `{APP_URL}/ticket/scan/{token}`
- Le token est persisté en base pour retrouver le billet

---

### 4. FLUX DE SUPPRESSION DE TICKET

```
USER ACTION: Click supprimer billet
    ↓
[POST] /ticket/{id}/delete
    ↓
TicketController::delete()
    ├─ Récupère Ticket par ID
    ├─ Vérification existence
    ├─ EntityManager::remove($ticket)
    ├─ EntityManager::flush()
    └─ Redirection + Flash success
    ↓
Ticket supprimé de la base
```

---

### 5. FLUX DE VALIDATION/SCAN

```
ADMIN/STAFF ACTION: Scanner QR code d'un billet
    ↓
Scan code → URL: /ticket/scan/{token}
    ↓
TicketController::scanTicket()
    ├─ Recherche Ticket par qrCode = token
    ├─ Vérification existence
    ├─ Vérification déjà utilisé
    ├─ Si valide:
    │  ├─ $ticket->markAsUsed()
    │  │  └─ isUsed = true
    │  │  └─ usedAt = now()
    │  └─ EntityManager::flush()
    └─ Affichage résultat (JSON ou page)
    ↓
Ticket marqué comme utilisé
```

---

## Règles métier

### Génération des codes

| Élément | Format | Exemple |
|---------|--------|---------|
| **Ticket Code** | `EVT-{eventId}-{userId}-{timestamp}` | `EVT-42-7-1234567` |
| **QR Token** | `tkt_{hex(random_bytes(24))}` | `tkt_a3f2d8e9c1b4f7e2a9d5c8f1b4e7a2d9` |

### Règles de contrainte

1. **Ticket Code** : UNIQUE, NOT NULL, LENGTH 50
2. **QR Code** : UNIQUE par ticket, généré à la demande
3. **is_used** : DEFAULT false, marqué true à la validation
4. **Capacité événement** : count(tickets) < capacity
5. **Unicité participant** : 1 seul ticket par (user, event)
6. **Événement** : Doit être published et futur

### Transitions d'état

```
État: VALIDE (is_used = false, used_at = NULL)
  ├─ Action: Marquer utilisé
  │  └─ → État: UTILISÉ
  └─ Action: Supprimer
     └─ → État: SUPPRIMÉ (DB)

État: UTILISÉ (is_used = true, used_at = datetime)
  └─ Action: Affichage historique

État: SUPPRIMÉ
  └─ Plus accessible
```

---

## API Endpoints

### Backend (Symfony)

| Méthode | Endpoint | Rôle | Retour |
|---------|----------|------|--------|
| GET | `/events` | Liste événements publics | HTML |
| GET | `/events/{id}` | Détails événement | HTML |
| POST | `/events/{id}/participate` | Participation (step 1) | Redirect |
| GET/POST | `/events/{id}/participate/confirm` | Confirmation (step 2) | HTML/Redirect |
| GET | `/my-tickets` | Liste billets user | HTML |
| GET | `/ticket/{id}` | Détails billet + QR | HTML |
| POST | `/ticket/{id}/delete` | Suppression billet | Redirect |
| GET/POST | `/ticket/scan/{token}` | Validation scan | JSON/HTML |
| GET/POST | `/ticket/{id}/download-pdf` | Export PDF billet | PDF |

### Back-office (Admin)

| Méthode | Endpoint | Rôle | Retour |
|---------|----------|------|--------|
| GET | `/ticket` | Liste tous billets | HTML |
| GET | `/ticket?search=...&event=...&status=...` | Filtrage billets | HTML |
| GET | `/ticket/{id}` | Détails billet | HTML |
| POST | `/ticket/{id}/delete` | Suppression admin | Redirect |

---

## Implémentation Java

### 1. Entity Ticket (JPA/Hibernate)

```java
// com.pidev.event.entity.Ticket.java

package com.pidev.event.entity;

import com.pidev.user.entity.UserModel;
import jakarta.persistence.*;
import java.time.LocalDateTime;
import java.time.Instant;

@Entity
@Table(name = "event_ticket", indexes = {
    @Index(name = "idx_event_id", columnList = "event_id"),
    @Index(name = "idx_user_id", columnList = "user_id"),
    @Index(name = "idx_ticket_code", columnList = "ticket_code")
})
public class Ticket {
    
    // IDENTIFIANT
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id")
    private Long id;
    
    // DONNÉES MÉTIER
    @Column(name = "ticket_code", length = 50, nullable = false, unique = true)
    private String ticketCode;
    
    @Column(name = "qr_code", columnDefinition = "LONGTEXT", nullable = true)
    private String qrCode;
    
    @Column(name = "is_used", nullable = false)
    private Boolean isUsed = false;
    
    @Column(name = "used_at", nullable = true)
    private LocalDateTime usedAt;
    
    @Column(name = "created_at", nullable = true)
    private LocalDateTime createdAt;
    
    @Column(name = "user_id", nullable = true)
    private Integer userId;
    
    // RELATIONS
    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "event_id", nullable = false, foreignKey = 
        @ForeignKey(name = "fk_event_ticket_event", value = ConstraintMode.CONSTRAINT))
    private Event event;
    
    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "user_id", nullable = false, insertable = false, updatable = false, 
        foreignKey = @ForeignKey(name = "fk_event_ticket_user", value = ConstraintMode.CONSTRAINT))
    private UserModel user;
    
    // CONSTRUCTEURS
    public Ticket() {
        this.isUsed = false;
        this.createdAt = LocalDateTime.now();
    }
    
    public Ticket(Event event, UserModel user) {
        this();
        this.event = event;
        this.user = user;
        this.userId = user.getIdUser();
    }
    
    // MÉTHODES UTILITAIRES
    public static String generateTicketCode(Long eventId, Integer userId) {
        long timestamp = System.currentTimeMillis() / 1000 % 10000000;
        return String.format("EVT-%d-%d-%d", eventId, userId, timestamp);
    }
    
    public static String generateQrToken() {
        byte[] randomBytes = new byte[24];
        new java.security.SecureRandom().nextBytes(randomBytes);
        String hex = javax.xml.bind.DatatypeConverter.printHexBinary(randomBytes).toLowerCase();
        return "tkt_" + hex;
    }
    
    public boolean isValid() {
        return !this.isUsed;
    }
    
    public void markAsUsed() {
        this.isUsed = true;
        this.usedAt = LocalDateTime.now();
    }
    
    // GETTERS & SETTERS
    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    
    public String getTicketCode() { return ticketCode; }
    public void setTicketCode(String ticketCode) { this.ticketCode = ticketCode; }
    
    public String getQrCode() { return qrCode; }
    public void setQrCode(String qrCode) { this.qrCode = qrCode; }
    
    public Boolean getIsUsed() { return isUsed; }
    public void setIsUsed(Boolean isUsed) { this.isUsed = isUsed; }
    
    public LocalDateTime getUsedAt() { return usedAt; }
    public void setUsedAt(LocalDateTime usedAt) { this.usedAt = usedAt; }
    
    public LocalDateTime getCreatedAt() { return createdAt; }
    public void setCreatedAt(LocalDateTime createdAt) { this.createdAt = createdAt; }
    
    public Integer getUserId() { return userId; }
    public void setUserId(Integer userId) { this.userId = userId; }
    
    public Event getEvent() { return event; }
    public void setEvent(Event event) { this.event = event; }
    
    public UserModel getUser() { return user; }
    public void setUser(UserModel user) { this.user = user; }
    
    @Override
    public String toString() {
        return ticketCode != null ? ticketCode : "Ticket #" + id;
    }
}
```

---

### 2. Repository Ticket (Spring Data JPA)

```java
// com.pidev.event.repository.TicketRepository.java

package com.pidev.event.repository;

import com.pidev.event.entity.Ticket;
import com.pidev.event.entity.Event;
import com.pidev.user.entity.UserModel;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Optional;

@Repository
public interface TicketRepository extends JpaRepository<Ticket, Long> {
    
    // RECHERCHES SIMPLES
    Optional<Ticket> findByTicketCode(String ticketCode);
    Optional<Ticket> findByQrCode(String qrCode);
    
    List<Ticket> findByUser(UserModel user);
    List<Ticket> findByEvent(Event event);
    
    // VÉRIFICATIONS
    boolean existsByEventAndUser(Event event, UserModel user);
    long countByEvent(Event event);
    long countByUserAndIsUsedTrue(UserModel user);
    long countByEventAndIsUsedTrue(Event event);
    
    // RECHERCHES PAGINÉES
    Page<Ticket> findByUserOrderByCreatedAtDesc(UserModel user, Pageable pageable);
    
    // RECHERCHES FILTRÉES
    @Query("""
        SELECT t FROM Ticket t
        LEFT JOIN FETCH t.event e
        LEFT JOIN FETCH t.user u
        WHERE (:search IS NULL OR 
               LOWER(t.ticketCode) LIKE LOWER(CONCAT('%', :search, '%')) OR
               LOWER(t.qrCode) LIKE LOWER(CONCAT('%', :search, '%')) OR
               LOWER(e.title) LIKE LOWER(CONCAT('%', :search, '%')) OR
               LOWER(u.firstName) LIKE LOWER(CONCAT('%', :search, '%')) OR
               LOWER(u.lastName) LIKE LOWER(CONCAT('%', :search, '%')))
        AND (:eventId IS NULL OR e.id = :eventId)
        AND (:statusFilter IS NULL OR 
             (:statusFilter = 'used' AND t.isUsed = true) OR
             (:statusFilter = 'unused' AND t.isUsed = false))
        ORDER BY t.createdAt DESC
    """)
    Page<Ticket> findFiltered(
        @Param("search") String search,
        @Param("eventId") Long eventId,
        @Param("statusFilter") String statusFilter,
        Pageable pageable
    );
    
    // STATISTIQUES
    @Query("SELECT COUNT(DISTINCT t.event) FROM Ticket t")
    long countDistinctEvents();
    
    @Query("""
        SELECT COUNT(t) FROM Ticket t
        WHERE t.event = :event AND t.isUsed = false
    """)
    long countUnusedByEvent(@Param("event") Event event);
}
```

---

### 3. Service Ticket (Business Logic)

```java
// com.pidev.event.service.TicketService.java

package com.pidev.event.service;

import com.pidev.event.entity.Event;
import com.pidev.event.entity.Ticket;
import com.pidev.event.repository.EventRepository;
import com.pidev.event.repository.TicketRepository;
import com.pidev.user.entity.UserModel;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.data.domain.Pageable;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.*;
import java.util.stream.Collectors;

@Service
@Transactional
public class TicketService {
    
    private final TicketRepository ticketRepository;
    private final EventRepository eventRepository;
    
    public TicketService(TicketRepository ticketRepository, EventRepository eventRepository) {
        this.ticketRepository = ticketRepository;
        this.eventRepository = eventRepository;
    }
    
    /**
     * FLUX CRÉATION: Crée un ticket lors de la participation
     */
    public Ticket createTicket(Event event, UserModel user) throws IllegalStateException {
        // Vérification 1: L'utilisateur n'a pas déjà un ticket
        if (ticketRepository.existsByEventAndUser(event, user)) {
            throw new IllegalStateException("L'utilisateur a déjà un ticket pour cet événement");
        }
        
        // Vérification 2: Capacité de l'événement
        if (event.getCapacity() != null && event.getCapacity() > 0) {
            long ticketCount = ticketRepository.countByEvent(event);
            if (ticketCount >= event.getCapacity()) {
                throw new IllegalStateException("La capacité maximale de l'événement est atteinte");
            }
        }
        
        // Création du ticket
        Ticket ticket = new Ticket(event, user);
        ticket.setTicketCode(Ticket.generateTicketCode(event.getId(), user.getIdUser()));
        ticket.setQrCode(Ticket.generateQrToken());
        
        return ticketRepository.save(ticket);
    }
    
    /**
     * FLUX CONSULTATION: Récupère les billets d'un utilisateur
     */
    public Map<String, Object> getUserTickets(UserModel user) {
        List<Ticket> allTickets = ticketRepository.findByUser(user);
        
        // Triage entre billets à venir et historique
        List<Ticket> upcomingTickets = new ArrayList<>();
        List<Ticket> historyTickets = new ArrayList<>();
        
        LocalDateTime now = LocalDateTime.now();
        
        for (Ticket ticket : allTickets) {
            if (ticket.getIsUsed()) {
                historyTickets.add(ticket);
            } else if (ticket.getEvent().getEndDate() != null && 
                      ticket.getEvent().getEndDate().isBefore(now)) {
                historyTickets.add(ticket);
            } else {
                upcomingTickets.add(ticket);
            }
        }
        
        Map<String, Object> result = new HashMap<>();
        result.put("allTickets", allTickets);
        result.put("upcomingTickets", upcomingTickets);
        result.put("historyTickets", historyTickets);
        
        return result;
    }
    
    /**
     * FLUX VALIDATION: Marque un ticket comme utilisé
     */
    public Ticket validateTicket(String qrToken) throws IllegalArgumentException {
        Ticket ticket = ticketRepository.findByQrCode(qrToken)
            .orElseThrow(() -> new IllegalArgumentException("Billet introuvable"));
        
        if (ticket.getIsUsed()) {
            throw new IllegalArgumentException("Ce billet a déjà été utilisé");
        }
        
        ticket.markAsUsed();
        return ticketRepository.save(ticket);
    }
    
    /**
     * FLUX SUPPRESSION: Supprime un ticket
     */
    public void deleteTicket(Long ticketId) throws IllegalArgumentException {
        Ticket ticket = ticketRepository.findById(ticketId)
            .orElseThrow(() -> new IllegalArgumentException("Billet introuvable ou déjà supprimé"));
        
        ticketRepository.delete(ticket);
    }
    
    /**
     * FLUX BACK-OFFICE: Récupère les billets filtrés pour l'administration
     */
    public Map<String, Object> getBackOfficeListData(
        String search,
        Long eventId,
        String statusFilter,
        int page,
        int pageSize
    ) {
        Pageable pageable = PageRequest.of(page - 1, pageSize);
        Page<Ticket> tickets = ticketRepository.findFiltered(search, eventId, statusFilter, pageable);
        
        long totalTickets = ticketRepository.count();
        long totalEvents = ticketRepository.countDistinctEvents();
        
        List<Event> events = eventRepository.findAll();
        
        Map<String, Object> result = new HashMap<>();
        result.put("tickets", tickets);
        result.put("events", events);
        result.put("totalTickets", totalTickets);
        result.put("totalEvents", totalEvents);
        result.put("search", search);
        result.put("eventFilter", eventId);
        result.put("statusFilter", statusFilter);
        
        return result;
    }
    
    /**
     * Génère un QR code unique
     */
    public String generateUniqueQrToken() {
        String token;
        do {
            token = Ticket.generateQrToken();
        } while (ticketRepository.findByQrCode(token).isPresent());
        return token;
    }
    
    /**
     * Récupère les statistiques des billets
     */
    public Map<String, Long> getTicketStatistics(Event event) {
        Map<String, Long> stats = new HashMap<>();
        stats.put("total", ticketRepository.countByEvent(event));
        stats.put("used", ticketRepository.countByEventAndIsUsedTrue(event));
        stats.put("unused", ticketRepository.countUnusedByEvent(event));
        return stats;
    }
}
```

---

### 4. Controller Ticket (API REST/MVC)

```java
// com.pidev.event.controller.TicketController.java

package com.pidev.event.controller;

import com.pidev.event.entity.Event;
import com.pidev.event.entity.Ticket;
import com.pidev.event.repository.EventRepository;
import com.pidev.event.repository.TicketRepository;
import com.pidev.event.service.TicketService;
import com.pidev.user.entity.UserModel;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.core.Authentication;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;

import java.util.Map;

@Controller
@RequestMapping("/ticket")
public class TicketController {
    
    private final TicketService ticketService;
    private final TicketRepository ticketRepository;
    private final EventRepository eventRepository;
    
    public TicketController(TicketService ticketService, 
                           TicketRepository ticketRepository,
                           EventRepository eventRepository) {
        this.ticketService = ticketService;
        this.ticketRepository = ticketRepository;
        this.eventRepository = eventRepository;
    }
    
    /**
     * BACK-OFFICE: Liste tous les billets avec filtres
     */
    @GetMapping("/")
    @PreAuthorize("hasRole('ADMIN')")
    public String index(
        @RequestParam(value = "search", defaultValue = "") String search,
        @RequestParam(value = "event", required = false) Long eventId,
        @RequestParam(value = "status", required = false) String statusFilter,
        @RequestParam(value = "page", defaultValue = "1") int page,
        Model model
    ) {
        Map<String, Object> listData = ticketService.getBackOfficeListData(
            search, eventId, statusFilter, page, 6
        );
        
        model.addAllAttributes(listData);
        model.addAttribute("pageTitle", "Gestion des billets");
        model.addAttribute("pageSubtitle", "Billets issus des participations front office");
        
        return "ticket/index";
    }
    
    /**
     * Détails d'un billet
     */
    @GetMapping("/{id}")
    public String show(@PathVariable Long id, Model model) {
        Ticket ticket = ticketRepository.findById(id)
            .orElseThrow(() -> new IllegalArgumentException("Billet introuvable"));
        
        // Génère QR si absent
        if (ticket.getQrCode() == null) {
            String qrCode = ticketService.generateUniqueQrToken();
            ticket.setQrCode(qrCode);
            ticketRepository.save(ticket);
        }
        
        model.addAttribute("ticket", ticket);
        model.addAttribute("pageTitle", "Détails du Billet");
        model.addAttribute("pageSubtitle", "Consultation et gestion du billet d'événement");
        
        return "ticket/show";
    }
    
    /**
     * Supprime un billet
     */
    @PostMapping("/{id}/delete")
    @PreAuthorize("hasRole('ADMIN')")
    public String delete(@PathVariable Long id) {
        try {
            ticketService.deleteTicket(id);
            return "redirect:/ticket?success=Billet%20supprimé";
        } catch (IllegalArgumentException e) {
            return "redirect:/ticket?error=" + e.getMessage();
        }
    }
    
    /**
     * API: Validation de ticket via QR code
     */
    @PostMapping("/validate")
    @ResponseBody
    public ResponseEntity<?> validateTicket(@RequestParam String token) {
        try {
            Ticket ticket = ticketService.validateTicket(token);
            return ResponseEntity.ok(Map.of(
                "success", true,
                "message", "Billet validé avec succès",
                "ticketCode", ticket.getTicketCode(),
                "userName", ticket.getUser().getFirstName() + " " + ticket.getUser().getLastName(),
                "eventTitle", ticket.getEvent().getTitle()
            ));
        } catch (IllegalArgumentException e) {
            return ResponseEntity.badRequest().body(Map.of(
                "success", false,
                "message", e.getMessage()
            ));
        }
    }
    
    /**
     * API: Recherche par code
     */
    @GetMapping("/search")
    @ResponseBody
    public ResponseEntity<?> searchByCode(@RequestParam String code) {
        return ticketRepository.findByTicketCode(code)
            .map(ticket -> ResponseEntity.ok(Map.of(
                "id", ticket.getId(),
                "code", ticket.getTicketCode(),
                "eventTitle", ticket.getEvent().getTitle(),
                "userName", ticket.getUser().getFirstName() + " " + ticket.getUser().getLastName(),
                "isUsed", ticket.getIsUsed()
            )))
            .orElseGet(() -> ResponseEntity.notFound().build());
    }
}
```

---

### 5. Controller Front - Participation (API REST/MVC)

```java
// com.pidev.event.controller.EventFrontController.java (extrait)

package com.pidev.event.controller;

import com.pidev.event.entity.Event;
import com.pidev.event.entity.Ticket;
import com.pidev.event.repository.EventRepository;
import com.pidev.event.repository.TicketRepository;
import com.pidev.event.service.TicketService;
import com.pidev.event.service.NotificationService;
import com.pidev.user.entity.UserModel;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;
import jakarta.servlet.http.HttpSession;

@Controller
@RequestMapping("/events")
public class EventFrontController {
    
    private final TicketService ticketService;
    private final TicketRepository ticketRepository;
    private final EventRepository eventRepository;
    private final NotificationService notificationService;
    
    public EventFrontController(TicketService ticketService,
                               TicketRepository ticketRepository,
                               EventRepository eventRepository,
                               NotificationService notificationService) {
        this.ticketService = ticketService;
        this.ticketRepository = ticketRepository;
        this.eventRepository = eventRepository;
        this.notificationService = notificationService;
    }
    
    /**
     * STEP 1: Click sur "Participer" - Redirection vers confirmation
     */
    @PostMapping("/{id}/participate")
    public String participate(
        @PathVariable Long id,
        HttpSession session,
        RedirectAttributes redirectAttributes
    ) {
        UserModel user = getCurrentUser();
        
        if (user == null) {
            session.setAttribute("pending_event", id);
            redirectAttributes.addFlashAttribute("message", 
                "Connectez-vous pour finaliser votre inscription.");
            return "redirect:/login";
        }
        
        return "redirect:/events/" + id + "/participate/confirm";
    }
    
    /**
     * STEP 2: Affichage du formulaire de confirmation
     */
    @GetMapping("/{id}/participate/confirm")
    public String showConfirmation(
        @PathVariable Long id,
        Model model,
        RedirectAttributes redirectAttributes
    ) {
        UserModel user = getCurrentUser();
        
        if (user == null) {
            redirectAttributes.addFlashAttribute("message", 
                "Connectez-vous pour confirmer votre participation.");
            return "redirect:/login";
        }
        
        Event event = eventRepository.findById(id)
            .orElseThrow(() -> new IllegalArgumentException("Événement introuvable"));
        
        // Vérification: L'utilisateur n'a pas déjà un ticket
        if (ticketRepository.existsByEventAndUser(event, user)) {
            redirectAttributes.addFlashAttribute("message", 
                "Vous êtes déjà inscrit à cet événement.");
            return "redirect:/my-tickets";
        }
        
        model.addAttribute("event", event);
        return "front/event_participation_confirm";
    }
    
    /**
     * STEP 3: Confirmation et création du ticket
     */
    @PostMapping("/{id}/participate/confirm")
    public String confirmParticipation(
        @PathVariable Long id,
        @RequestParam String _token,
        HttpSession session,
        RedirectAttributes redirectAttributes
    ) {
        UserModel user = getCurrentUser();
        
        if (user == null) {
            redirectAttributes.addFlashAttribute("error", "Vous devez être connecté.");
            return "redirect:/login";
        }
        
        // Vérification CSRF (implémenter selon votre stratégie)
        // validateCsrfToken(_token, "confirm_participation_" + id);
        
        Event event = eventRepository.findById(id)
            .orElseThrow(() -> new IllegalArgumentException("Événement introuvable"));
        
        try {
            // Création du ticket via le service
            Ticket ticket = ticketService.createTicket(event, user);
            
            // Notification utilisateur
            notificationService.createConfirmation(user, event);
            
            session.removeAttribute("pending_event");
            
            redirectAttributes.addFlashAttribute("success",
                String.format("Participation confirmée. Votre billet pour \"%s\" a été créé automatiquement.",
                event.getTitle()));
            
            return "redirect:/my-tickets";
            
        } catch (IllegalStateException e) {
            redirectAttributes.addFlashAttribute("error", e.getMessage());
            return "redirect:/events/" + id;
        }
    }
    
    /**
     * Page "Mes billets"
     */
    @GetMapping("/my-tickets")
    public String myTickets(Model model) {
        UserModel user = getCurrentUser();
        
        if (user == null) {
            return "redirect:/login";
        }
        
        Map<String, Object> ticketData = ticketService.getUserTickets(user);
        
        model.addAllAttributes(ticketData);
        model.addAttribute("latestNotifications", 
            notificationService.getLatest(user, 5));
        model.addAttribute("unreadNotificationCount",
            notificationService.getUnreadCount(user));
        
        return "front/my_tickets";
    }
    
    /**
     * Détails d'un événement (affiche info participation)
     */
    @GetMapping("/{id}")
    public String showEvent(
        @PathVariable Long id,
        Model model
    ) {
        Event event = eventRepository.findById(id)
            .orElseThrow(() -> new IllegalArgumentException("Événement introuvable"));
        
        UserModel user = getCurrentUser();
        boolean hasTicket = false;
        boolean hasUsedTicket = false;
        
        if (user != null) {
            hasTicket = ticketRepository.existsByEventAndUser(event, user);
            hasUsedTicket = ticketRepository.findByEvent(event).stream()
                .anyMatch(t -> t.getUser().equals(user) && t.getIsUsed());
        }
        
        model.addAttribute("event", event);
        model.addAttribute("hasTicket", hasTicket);
        model.addAttribute("hasUsedTicket", hasUsedTicket);
        
        return "front/event_show";
    }
    
    /**
     * Utilitaire: Récupère l'utilisateur connecté
     */
    private UserModel getCurrentUser() {
        Authentication auth = SecurityContextHolder.getContext().getAuthentication();
        if (auth == null || !auth.isAuthenticated()) {
            return null;
        }
        
        Object principal = auth.getPrincipal();
        return principal instanceof UserModel ? (UserModel) principal : null;
    }
}
```

---

### 6. DTO pour API Response

```java
// com.pidev.event.dto.TicketDTO.java

package com.pidev.event.dto;

import com.fasterxml.jackson.annotation.JsonFormat;
import java.time.LocalDateTime;

public class TicketDTO {
    private Long id;
    private String ticketCode;
    private String qrCode;
    private Boolean isUsed;
    
    @JsonFormat(pattern = "dd/MM/yyyy HH:mm")
    private LocalDateTime createdAt;
    
    @JsonFormat(pattern = "dd/MM/yyyy HH:mm")
    private LocalDateTime usedAt;
    
    private Long eventId;
    private String eventTitle;
    private String eventDate;
    private String eventLocation;
    
    private Integer userId;
    private String userName;
    private String userEmail;
    
    // Constructeur
    public TicketDTO(Ticket ticket) {
        this.id = ticket.getId();
        this.ticketCode = ticket.getTicketCode();
        this.qrCode = ticket.getQrCode();
        this.isUsed = ticket.getIsUsed();
        this.createdAt = ticket.getCreatedAt();
        this.usedAt = ticket.getUsedAt();
        
        if (ticket.getEvent() != null) {
            this.eventId = ticket.getEvent().getId();
            this.eventTitle = ticket.getEvent().getTitle();
            this.eventLocation = ticket.getEvent().getLocation();
            if (ticket.getEvent().getStartDate() != null) {
                this.eventDate = ticket.getEvent().getStartDate()
                    .format(java.time.format.DateTimeFormatter.ofPattern("dd/MM/yyyy"));
            }
        }
        
        if (ticket.getUser() != null) {
            this.userId = ticket.getUser().getIdUser();
            this.userName = ticket.getUser().getFirstName() + " " + ticket.getUser().getLastName();
            this.userEmail = ticket.getUser().getEmail();
        }
    }
    
    // Getters & Setters
    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    
    public String getTicketCode() { return ticketCode; }
    public void setTicketCode(String ticketCode) { this.ticketCode = ticketCode; }
    
    public String getQrCode() { return qrCode; }
    public void setQrCode(String qrCode) { this.qrCode = qrCode; }
    
    public Boolean getIsUsed() { return isUsed; }
    public void setIsUsed(Boolean isUsed) { this.isUsed = isUsed; }
    
    public LocalDateTime getCreatedAt() { return createdAt; }
    public void setCreatedAt(LocalDateTime createdAt) { this.createdAt = createdAt; }
    
    public LocalDateTime getUsedAt() { return usedAt; }
    public void setUsedAt(LocalDateTime usedAt) { this.usedAt = usedAt; }
    
    public Long getEventId() { return eventId; }
    public void setEventId(Long eventId) { this.eventId = eventId; }
    
    public String getEventTitle() { return eventTitle; }
    public void setEventTitle(String eventTitle) { this.eventTitle = eventTitle; }
    
    public String getEventDate() { return eventDate; }
    public void setEventDate(String eventDate) { this.eventDate = eventDate; }
    
    public String getEventLocation() { return eventLocation; }
    public void setEventLocation(String eventLocation) { this.eventLocation = eventLocation; }
    
    public Integer getUserId() { return userId; }
    public void setUserId(Integer userId) { this.userId = userId; }
    
    public String getUserName() { return userName; }
    public void setUserName(String userName) { this.userName = userName; }
    
    public String getUserEmail() { return userEmail; }
    public void setUserEmail(String userEmail) { this.userEmail = userEmail; }
}
```

---

### 7. Validation & Exception Handling

```java
// com.pidev.event.exception.TicketException.java

package com.pidev.event.exception;

public class TicketException extends RuntimeException {
    private String code;
    private int httpStatus;
    
    public TicketException(String message) {
        super(message);
        this.code = "TICKET_ERROR";
        this.httpStatus = 400;
    }
    
    public TicketException(String message, String code, int httpStatus) {
        super(message);
        this.code = code;
        this.httpStatus = httpStatus;
    }
    
    public String getCode() { return code; }
    public int getHttpStatus() { return httpStatus; }
}

// Exceptions spécialisées
public class TicketAlreadyExistsException extends TicketException {
    public TicketAlreadyExistsException() {
        super("L'utilisateur a déjà un ticket pour cet événement", 
              "TICKET_ALREADY_EXISTS", 409);
    }
}

public class EventCapacityExceededException extends TicketException {
    public EventCapacityExceededException() {
        super("La capacité maximale de l'événement est atteinte", 
              "CAPACITY_EXCEEDED", 409);
    }
}

public class InvalidTicketException extends TicketException {
    public InvalidTicketException() {
        super("Billet invalide ou déjà utilisé", 
              "INVALID_TICKET", 400);
    }
}

// Exception Handler
@RestControllerAdvice
public class GlobalExceptionHandler {
    
    @ExceptionHandler(TicketException.class)
    public ResponseEntity<?> handleTicketException(TicketException e) {
        return ResponseEntity
            .status(e.getHttpStatus())
            .body(Map.of(
                "error", e.getCode(),
                "message", e.getMessage()
            ));
    }
}
```

---

## Résumé Comparatif PHP ↔ Java

| Aspect | PHP (Symfony) | Java (Spring Boot) |
|--------|---------------|--------------------|
| **Entity** | Doctrine Entity | JPA Entity |
| **Repository** | ServiceEntityRepository | JpaRepository |
| **Service** | Service class | @Service annotated class |
| **Controller** | AbstractController | @Controller/@RestController |
| **Validation** | Assert annotations | @Valid / Validator |
| **Transactions** | #[ORM\Entity] | @Transactional |
| **Injection** | Constructor | Constructor / @Autowired |
| **Routing** | #[Route()] | @GetMapping / @PostMapping |
| **Session** | SessionInterface | HttpSession |
| **Authentication** | Security component | Spring Security |
| **PDF Export** | KnpSnappy | iText / Apache PDFBox |

---

## Points critiques à synchroniser Web/Java

1. **Format Ticket Code**: `EVT-{eventId}-{userId}-{timestamp}` - IDENTIQUE
2. **Format QR Token**: `tkt_[hex(24bytes)]` - IDENTIQUE
3. **Règles métier**: 
   - 1 seul ticket par (user, event)
   - Respect de la capacité
   - Événement doit être published & futur
4. **Transitions d'état**: VALIDE → UTILISÉ (unidirectionnel)
5. **Dates**: LocalDateTime (format ISO 8601)
6. **Erreurs**: Les codes d'erreur doivent être identiques

