# Intégration FullCalendar dans Symfony

## Vue d'ensemble

Cette documentation explique comment FullCalendar a été intégré dans le projet Symfony pour afficher les réservations de ressources.

## Fichiers créés/modifiés

### 1. Controller
- **Fichier**: `src/Controller/Resource/ReservationResourceController.php`
- **Nouvelles routes**:
  - `/resource/reservation/calendar` - Affiche le calendrier
  - `/resource/reservation/calendar/events` - API JSON pour les événements

### 2. Template Twig
- **Fichier**: `templates/resource/reservation_resource/calendar.html.twig`
- **Fonctionnalités**: Affiche le calendrier FullCalendar avec les réservations

### 3. Documentation
- **Exemple JSON**: `docs/fullcalendar-json-example.json`
- **Documentation**: `docs/fullcalendar-integration.md`

## Routes disponibles

| Route | URL | Méthode | Description |
|------|-----|---------|-------------|
| `app_reservation_resource_calendar` | `/resource/reservation/calendar` | GET | Affiche la page du calendrier |
| `app_reservation_resource_calendar_events` | `/resource/reservation/calendar/events` | GET | API JSON pour les événements |

## Format JSON des événements

```json
[
  {
    "id": 1,
    "title": "Salle: Conférence A - Événement Tech Summit",
    "start": "2024-01-15",
    "end": "2024-01-17",
    "color": "#dc3545",
    "textColor": "#ffffff",
    "extendedProps": {
      "resourceType": "SALLE",
      "quantity": 1,
      "eventId": 42
    }
  }
]
```

### Champs expliqués

- **id**: Identifiant unique de la réservation
- **title**: Titre affiché dans le calendrier (format: "Type: Nom - Événement")
- **start**: Date de début (format YYYY-MM-DD)
- **end**: Date de fin (format YYYY-MM-DD)
- **color**: Couleur de l'événement (#dc3545 = rouge pour indisponible)
- **textColor**: Couleur du texte (#ffffff = blanc)
- **extendedProps**: Propriétés supplémentaires
  - **resourceType**: Type de ressource ("SALLE" ou "EQUIPEMENT")
  - **quantity**: Quantité réservée
  - **eventId**: ID de l'événement associé

## Fonctionnalités implémentées

### 1. Affichage des réservations
- Les réservations existantes apparaissent en rouge dans le calendrier
- Le titre inclut le type de ressource, le nom et l'événement

### 2. Interaction utilisateur
- **Clic sur une date disponible**: Ouvre un modal pour créer une réservation
- **Clic sur une réservation existante**: Affiche les détails de la réservation
- **Navigation**: Change de vue (mois, semaine, jour) et navigue entre les mois

### 3. Pré-remplissage des dates
- Lors du clic sur une date, le formulaire de réservation est pré-rempli avec cette date

## Personnalisation

### Styles CSS
Les styles sont définis directement dans le template Twig pour faciliter la personnalisation :

```css
.calendar-header { /* En-tête du calendrier */ }
.legend { /* Légende des couleurs */ }
.fc-event { /* Style des événements */ }
.modal { /* Modal de réservation */ }
```

### JavaScript
Le JavaScript utilise FullCalendar v5 avec les configurations suivantes :

```javascript
new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    locale: 'fr',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    events: '/resource/reservation/calendar/events',
    // ... autres configurations
});
```

## Utilisation

### Accéder au calendrier
1. Naviguez vers `/resource/reservation/calendar`
2. Le calendrier s'affiche avec toutes les réservations existantes

### Créer une réservation depuis le calendrier
1. Cliquez sur une date disponible (non colorée en rouge)
2. Un modal s'ouvre avec la date sélectionnée
3. Cliquez sur "Continuer" pour accéder au formulaire de réservation

### Voir les détails d'une réservation
1. Cliquez sur un événement rouge dans le calendrier
2. Une alerte affiche les détails de la réservation

## Dépendances

L'intégration utilise FullCalendar via CDN (pas d'installation npm requise) :

```html
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/fr.js'></script>
```

## Notes importantes

- Les dates sont traitées en format YYYY-MM-DD
- La localisation française est activée
- Le calendrier est responsive et s'adapte à la taille de l'écran
- Les événements sont chargés dynamiquement via AJAX
- La validation des dates est gérée côté client et serveur
