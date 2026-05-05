# Recap validation technique - Module Evenement (EventFlow)

## 1) Vue d'ensemble architecture

Le module evenement suit une architecture classique Symfony:
- Controllers: gerent routes HTTP, validation d'entree, droits, reponses JSON/HTML.
- Services metier: portent la logique metier (synchronisation Google, generation IA, meteo, stats).
- Twig + JS: consomment les endpoints back (fetch) et affichent les resultats.
- Doctrine: persistance des entites Event, Ticket, Category, Notification.

Pattern principal:
Controller -> Service metier -> API externe (si besoin) -> retour Controller -> Twig/JSON -> UI.

---

## 2) API externes utilisees

### A) Google Calendar API

Objectif metier:
- Synchroniser les evenements EventFlow vers Google (create/update/delete).
- Recuperer les changements Google vers EventFlow (inbound sync).
- Afficher un calendrier fusionne (evenements locaux + Google).

Fichiers cles:
- src/Controller/Event/EventController.php
- src/Service/Event/EventService.php
- src/Service/Event/GoogleCalendarWriteService.php
- templates/event/calendar.html.twig

Comment tu l'as consommee:
1. Lors de creation d'un evenement:
   - EventController::new() appelle EventService::createEvent().
   - EventService::createEvent() persiste puis appelle GoogleCalendarWriteService::syncCreatedEvent().
2. Lors de modification:
   - EventController::edit() -> EventService::updateEvent() -> GoogleCalendarWriteService::syncUpdatedEvent().
3. Lors de suppression:
   - EventController::delete() -> EventService::deleteEvent() -> GoogleCalendarWriteService::syncDeletedEvent().
4. Synchronisation entrante:
   - EventService::syncFromGoogleCalendarToEventFlow() appelle GoogleCalendarWriteService::fetchRemoteEventByLocalId().
   - Si l'evenement n'a pas la propriete privee liee, fallback sur titre + plage date.

Details techniques importants a dire:
- OAuth2 refresh token pour recuperer access token Google (endpoint oauth2 token).
- Mapping local->google via extendedProperties.private.eventflow_event_id.
- Fallback legacy pour anciens evenements sans metadata privee.

Variables d'environnement utilisees:
- GOOGLE_CALENDAR_ID
- GOOGLE_CLIENT_ID
- GOOGLE_CLIENT_SECRET
- GOOGLE_REFRESH_TOKEN
- GOOGLE_API_KEY (pour certains flux de lecture calendrier)


### B) Open-Meteo API

Objectif metier:
- Afficher la meteo liee au gouvernorat de l'evenement (creation + details).

Fichiers cles:
- src/Controller/Event/EventController.php (route /event/weather)
- src/Service/Event/WeatherService.php
- templates/event/new.html.twig (fetch meteo)
- src/Controller/Front/EventFrontController.php (detail evenement front)

Comment tu l'as consommee:
1. Front envoie GET /event/weather?gouvernorat=...
2. Controller valide l'entree puis appelle WeatherService::getCurrentWeatherForGovernorate().
3. WeatherService appelle https://api.open-meteo.com/v1/forecast via HttpClient Symfony.
4. Retour JSON au front, puis rendu dynamique (temperature, humidite, vent, icone/description).

Points oraux:
- Encapsulation API dans un service dedie (bonne separation des responsabilites).
- Gestion des erreurs reseau/format avec RuntimeException + HTTP 502 dans le controller.

---

## 3) IA integree

### Generation d'affiche evenement (Hugging Face)

Objectif metier:
- Generer une affiche automatiquement depuis les donnees evenement.

Fichiers cles:
- src/Controller/Event/EventController.php (route /event/generate-poster)
- src/Service/Event/AiPosterService.php
- templates/event/new.html.twig (bouton + fetch + preview)

Comment tu l'as consommee:
1. Le front collecte titre, description, categorie, lieu, capacite, prix.
2. POST JSON vers /event/generate-poster.
3. EventController construit un prompt metier via buildPosterPrompt().
4. AiPosterService appelle le modele HF:
   - URL: https://router.huggingface.co/hf-inference/models/black-forest-labs/FLUX.1-schnell
   - Header Authorization Bearer token
5. Si image valide:
   - Sauvegarde dans public/uploads/posters
   - Retour imageUrl en JSON
6. Le front met a jour le preview et le champ imageUrl du formulaire.

Variables d'environnement:
- HUGGINGFACE_API_TOKEN
- HUGGINGFACE_TOKEN (fallback legacy pris en charge)

Points oraux:
- Prompt engineering metier dynamique (style random + contraintes du contexte evenement).
- Pipeline complet UX: generation async, gestion erreurs, persistance fichier, preview immediate.

---

## 4) Bundles avances utilises et consommation

### A) KnpPaginatorBundle

Activation:
- config/bundles.php

Consommation:
- src/Controller/Event/EventController.php (injection PaginatorInterface)
- src/Service/Event/EventService.php (paginate sur QueryBuilder)

Valeur metier:
- Pagination de la liste back-office des evenements avec filtres.


### B) KnpSnappyBundle (wkhtmltopdf)

Activation:
- config/bundles.php

Configuration:
- config/packages/knp_snappy.yaml

Consommation:
- src/Controller/Event/EventController.php -> export PDF liste evenements
- src/Controller/Front/EventFrontController.php -> PDF billet utilisateur
- templates/event/index.html.twig -> lien export
- templates/front/my_tickets.html.twig -> lien telechargement billet

Valeur metier:
- Export documentaire propre pour admin + participants.


### C) CalendarBundle (tattali)

Activation:
- config/bundles.php

Consommation:
- src/EventSubscriber/ParticipantUpcomingCalendarSubscriber.php
  - Subscribe a CalendarEvents::SET_DATA
  - Injecte les evenements publies dans le calendrier selon la fenetre demandee

Valeur metier:
- Alimentation dynamique du calendrier participant via subscriber evenementiel.


### D) Symfony UX Chartjs

Activation:
- config/bundles.php

Consommation:
- src/Controller/Event/EventDashboardController.php
- templates/event/dashboard.html.twig (appel endpoint stats)

Valeur metier:
- Dashboard analytique (events par categorie/mois, tickets, repartition gratuits/payants).

---

## 5) Flux de consommation end-to-end (exemples)

### Cas 1: Creation evenement + sync Google
1. Form submit -> EventController::new()
2. Validation metier -> EventService::createEvent()
3. Persist Doctrine
4. Sync Google create -> GoogleCalendarWriteService::syncCreatedEvent()
5. Flash success/warning selon resultat

### Cas 2: Generate IA poster
1. Click bouton IA dans new.html.twig
2. fetch POST /event/generate-poster
3. EventController construit prompt
4. AiPosterService appelle HuggingFace
5. Sauvegarde image locale + retourne imageUrl
6. UI met a jour preview

### Cas 3: Meteo en saisie evenement
1. Choix gouvernorat
2. JS appelle /event/weather
3. WeatherService interroge Open-Meteo
4. JSON renvoye puis rendu meteo live

### Cas 4: Export PDF billet
1. User clique telecharger PDF
2. EventFrontController::myTicketPdf() charge ticket autorise
3. renderView(front/ticket_pdf.html.twig)
4. KnpSnappy Pdf::getOutputFromHtml()
5. Reponse Content-Type application/pdf + attachment

---

## 6) Fichiers de reference rapides (cheat sheet)

- Controller principal evenement: src/Controller/Event/EventController.php
- Service metier evenement: src/Service/Event/EventService.php
- Service Google: src/Service/Event/GoogleCalendarWriteService.php
- Service meteo: src/Service/Event/WeatherService.php
- Service IA poster: src/Service/Event/AiPosterService.php
- Form evenement + JS meteo/IA: templates/event/new.html.twig
- Vue calendrier fusionne: templates/event/calendar.html.twig
- Subscriber calendar bundle: src/EventSubscriber/ParticipantUpcomingCalendarSubscriber.php
- Dashboard stats chartjs: src/Controller/Event/EventDashboardController.php
- Export PDF admin: src/Controller/Event/EventController.php
- Export PDF participant: src/Controller/Front/EventFrontController.php
- Config bundles: config/bundles.php
- Config snappy: config/packages/knp_snappy.yaml
- Config Google Client: config/packages/google_apiclient.yaml
- Dependances: composer.json

---

## 7) Questions probables + reponses courtes

1) Pourquoi un service dedie pour Google au lieu de tout dans le controller?
- Pour isoler la logique externe, faciliter les tests, et eviter un controller surcharge.

2) Comment assures-tu le lien entre event local et event Google?
- Avec extendedProperties.private.eventflow_event_id dans le payload Google.

3) Que se passe-t-il si ce lien manque (anciens events)?
- Fallback sur recherche par titre + fenetre de dates (tolerance).

4) Pourquoi refresh token et pas API key pour ecriture?
- L'ecriture/modification/suppression necessite OAuth2 (Bearer token), pas simple API key.

5) Comment eviter de casser l'UX si API externe tombe?
- try/catch + messages utilisateur + fallback flash warning sans bloquer la persistence locale.

6) Comment tu as integre l'IA proprement?
- Endpoint dedie + prompt metier + validation reponse image + stockage local + preview front.

7) Difference entre KnpPaginator et KnpSnappy?
- Paginator = pagination des listes; Snappy = generation PDF/Images a partir de HTML.

8) Pourquoi CalendarBundle + FullCalendar?
- CalendarBundle alimente les donnees cote serveur; FullCalendar rend l'interface riche cote client.

9) Ou sont les secrets?
- Variables d'environnement (.env.local), pas hardcode en code metier.

10) Quel est ton plus gros risque technique ici?
- Desynchronisation Google/local en cas d'erreurs externes, attenuee par inbound sync + fallback legacy + logs.

---

## 8) Mini pitch oral (30-40 sec)

"Dans mon module evenement, j'ai combine des integrations avancees: Google Calendar pour la synchronisation bidirectionnelle des evenements, Open-Meteo pour enrichir l'experience avec la meteo contextuelle, et une generation d'affiches IA via Hugging Face. Cote Symfony, j'ai structure la logique dans des services metier dedies et j'ai utilise des bundles comme KnpPaginator pour la pagination, KnpSnappy pour les exports PDF, CalendarBundle pour l'alimentation calendrier et Chartjs pour les stats. L'idee etait d'avoir un flux robuste: persistence locale fiable, synchro externe resiliente, et UI reactive avec fetch/JSON."